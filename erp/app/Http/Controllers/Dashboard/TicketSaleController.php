<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Account;
use App\Models\Bank;
use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\PaymentSchedule;
use App\Models\Ticket;
use App\Models\TicketPurchase;
use App\Models\TicketSale;
use App\Models\TicketSaleItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PartyInvoiceAllocation;
use App\Services\TicketBaggage;
use App\Support\TravelCompany;
use App\Traits\PostsSaleJournal;
use App\Traits\ResolvesPartyType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class TicketSaleController implements HasMiddleware
{
    use PostsSaleJournal, ResolvesPartyType;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view ticket sale|view all ticket sale', only: ['index']),
            new Middleware('permission:create ticket sale', only: ['create', 'store']),
            new Middleware('permission:edit ticket sale', only: ['edit', 'update']),
            new Middleware('permission:delete ticket sale', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $data['users'] = User::get();
        $data['agents'] = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['vendor', 'agent']))
            ->orderBy('name')->get(['id', 'name', 'phone']);
        $data['customers'] = User::orderBy('name')->role('customer')->get(['id', 'name', 'phone']);
        $data['tickets'] = Ticket::where('status', 1)->get();
        $data['banks'] = Bank::where('status', 1)->get();

        $query = TicketSale::with([
            'client.roles:id,name',
            'ticket',
            'item.ticketPurchase.passportHolder',
            'item.ticketPurchase.vendor',
            'item.ticketPurchase.portal',
            'item.ticketPurchase.legs',
            'items.ticketPurchase.passportHolder',
            'items.ticketPurchase.ticket.from_airport',
            'items.ticketPurchase.ticket.to_airport',
        ])->where('company_id', TravelCompany::ID);

        // Filter by Invoice Number
        // Any text the table shows (owner, 2026-10-06): every word must match the
        // invoice, status, total, the client (or "EP-AG-116" / "116"), or one of
        // its tickets — PNR, passenger, passport, route.
        $query->when($request->filled('invoice_no'), function ($q) use ($request) {
            foreach (preg_split('/[[:space:]]+/', trim((string) $request->invoice_no)) as $w) {
                if ($w === '') {
                    continue;
                }
                $like = '%'.$w.'%';
                $q->where(function ($x) use ($w, $like) {
                    $x->where('invoice_no', 'like', $like)->orWhere('status', 'like', $like)
                        ->orWhereHas('client', fn ($r) => $r->where('name', 'like', $like))
                        ->orWhereHas('items.ticketPurchase', fn ($r) => $r->where('ticket_no', 'like', $like)
                            ->orWhereHas('passportHolder', fn ($h) => $h->where('name', 'like', $like)->orWhere('passport_no', 'like', $like))
                            ->orWhereHas('ticket', fn ($t) => $t->where('title', 'like', $like)));
                    $id = preg_replace('/^EP-(VN|AG|CU)-0*/i', '', $w);
                    if ($id !== '' && ctype_digit($id)) {
                        $x->orWhere('client_id', (int) $id);
                    }
                    $amount = str_replace(',', '', $w);
                    if (is_numeric($amount)) {
                        $x->orWhere('total_amount', 'like', $amount.'%');
                    }
                });
            }

        });

        // Filter by Client (agent/vendor or customer)
        $query->when($request->filled('client_id'), function ($q) use ($request) {
            $q->where('client_id', $request->client_id);
        });

        // Filter by Sale Date range
        $query->when($request->filled('from_date'), function ($q) use ($request) {
            $q->whereDate('sale_date', '>=', $request->from_date);
        });
        $query->when($request->filled('to_date'), function ($q) use ($request) {
            $q->whereDate('sale_date', '<=', $request->to_date);
        });

        // Filter by Status
        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->where('status', $request->status);
        });

        // Filter by Creator
        $query->when($request->filled('created_by'), function ($q) use ($request) {
            $q->where('created_by', $request->created_by);
        });

        // Filter by Ticket ID
        $query->when($request->filled('ticket_id'), function ($q) use ($request) {
            $q->where('ticket_id', $request->ticket_id);
        });

        $data['datas'] = $query->latest()->paginate(10)->withQueryString();

        $mtdSales = TicketSale::whereMonth('sale_date', now()->month)->whereYear('sale_date', now()->year);

        $data['stats'] = [
            'total_sales_mtd' => (clone $mtdSales)->count(),
            'revenue_mtd' => (clone $mtdSales)->sum('total_amount'),
            'total_due' => TicketSale::where('status', '!=', 'cancelled')->where('payment_status', '!=', 'paid')->sum('due_amount'),
            'profit_mtd' => TicketSaleItem::whereHas('sale', function ($q) {
                $q->whereMonth('sale_date', now()->month)->whereYear('sale_date', now()->year);
            })
                ->join('ticket_purchases', 'ticket_sale_items.ticket_purchase_id', '=', 'ticket_purchases.id')
                ->selectRaw('SUM(ticket_sale_items.price - ticket_purchases.amount) as profit')
                ->value('profit') ?? 0,
        ];

        return view('dashboard.ticket_sales.index', $data);
    }

    public function create(Request $request, $role)
    {
        // Create/Edit now happen via modal on the index page.
        return redirect()->route('role.ticket-sales.index', ['role' => $role]);
    }

    /**
     * AJAX: confirmed ticket purchases available to bundle into a sale.
     * Pass `exclude_sale_id` when editing, so that sale's own already-bundled
     * purchases are still offered (allowing the user to keep/deselect them).
     */
    public function availablePurchases(Request $request, $role)
    {
        $excludeSaleId = $request->input('exclude_sale_id');

        $query = TicketPurchase::where('status', 'confirm')
            ->with(['passportHolder', 'ticket']);

        $query->where(function ($q) use ($excludeSaleId) {
            $q->whereNotIn('id', function ($sub) {
                $sub->select('ticket_purchase_id')->from('ticket_sale_items');
            });
            if ($excludeSaleId) {
                $q->orWhereIn('id', function ($sub) use ($excludeSaleId) {
                    $sub->select('ticket_purchase_id')->from('ticket_sale_items')
                        ->where('ticket_sale_id', $excludeSaleId);
                });
            }
        });

        $purchases = $query->latest()->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'ticket_no' => $p->ticket_no,
                'name_ticket_no' => $p->name_ticket_no,
                'passenger' => $p->passportHolder?->name ?? '—',
                'passport_no' => $p->passportHolder?->passport_no,
                'route' => $p->ticket?->title ?? '—',
                // 'price'     => (float) ($p->ticket?->price ?? 0),
                'price' => (float) ($p->amount ?? 0),
            ];
        })->values();

        return response()->json(['purchases' => $purchases]);
    }

    /**
     * Post a row onto the party's own party-statement ledger, chaining
     * old_balance/balance off that party's last transaction (not a bank's
     * balance). Debit increases what the party owes us, credit decreases it.
     */
    private function postPartyLedger(int $partyId, array $attrs): Transaction
    {
        $last = Transaction::where('user_id', $partyId)->orderByDesc('id')->lockForUpdate()->first();
        $oldBalance = $last ? (float) $last->balance : 0;
        $newBalance = $oldBalance + (float) ($attrs['debit'] ?? 0) - (float) ($attrs['credit'] ?? 0);

        // Party statements show the ledger clock from created_at. Keep the
        // actual posting time, but attach it to the user-selected sale date so
        // back-dated ticket entries do not appear under today's timestamp.
        if (! isset($attrs['created_at']) && ! empty($attrs['payment_date'])) {
            $attrs['created_at'] = Carbon::parse($attrs['payment_date'])
                ->setTimeFrom(now());
        }

        return Transaction::create(array_merge([
            'user_id' => $partyId,
            'user_type' => 'customer',
            'old_balance' => $oldBalance,
            'balance' => $newBalance,
        ], $attrs));
    }

    /** Move every invoice/payment row when the sale's agent/customer changes. */
    private function moveSaleLedgerToParty(int $ticketSaleId, int $fromPartyId, int $toPartyId): void
    {
        $rows = Transaction::where('invoice_id', $ticketSaleId)
            ->where('type', 'ticket_sale')
            ->where('user_id', $fromPartyId)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        Transaction::whereIn('id', $rows->pluck('id'))->update([
            'user_id' => $toPartyId,
            // A ticket sale is receivable even if the selected user also has a
            // vendor role; this column describes the side of this transaction.
            'user_type' => 'customer',
        ]);

        $fromId = (int) $rows->min('id');
        $this->rebuildPartyRunningBalancesFrom($fromPartyId, $fromId);
        $this->rebuildPartyRunningBalancesFrom($toPartyId, $fromId);
    }

    private function rebuildPartyRunningBalancesFrom(int $partyId, int $fromId): void
    {
        $running = (float) (Transaction::where('user_id', $partyId)
            ->where('id', '<', $fromId)
            ->orderByDesc('id')
            ->value('balance') ?? 0);

        Transaction::where('user_id', $partyId)
            ->where('id', '>=', $fromId)
            ->orderBy('id')
            ->get()
            ->each(function (Transaction $row) use (&$running) {
                $old = $running;
                $running = round($running + (float) $row->debit - (float) $row->credit, 2);
                if (abs((float) $row->old_balance - $old) > 0.005 || abs((float) $row->balance - $running) > 0.005) {
                    $row->update(['old_balance' => $old, 'balance' => $running]);
                }
            });
    }

    public function manageBankPayment($request, $ticket_sale_id, $paid_amount)
    {
        $ticketSale = TicketSale::find($ticket_sale_id);

        $this->postPartyLedger($ticketSale->client_id, [
            'type' => 'ticket_sale',
            'account_id' => $request->bank_id,
            'payment_date' => $request->sale_date ?? now(),
            'reference_no' => $request->reference_no,
            'payment_method' => $request->payment_method,
            'invoice_id' => $ticket_sale_id,
            'debit' => 0,
            'credit' => $paid_amount,
            'remarks' => 'Ticket sale payment received — '.$ticketSale->invoice_no,
        ]);

        $acc = Bank::find($request->bank_id);
        if ($acc) {
            $acc->increment('balance', $paid_amount);
        }
    }

    public function reverseBankPayment($ticketSale)
    {
        $transactions = Transaction::where('invoice_id', $ticketSale->id)
            ->where('type', 'ticket_sale')
            ->get();

        if ($transactions->isEmpty()) {
            return;
        }

        $transactions
            ->filter(fn ($transaction) => $transaction->account_id && (float) $transaction->credit > 0)
            ->groupBy('account_id')
            ->each(function ($bankTransactions, $bankId) {
                $bank = Bank::find($bankId);
                if ($bank?->account_id) {
                    Bank::assertCanCover(
                        (int) $bank->account_id,
                        (float) $bankTransactions->sum('credit'),
                        $bank->name
                    );
                }
            });

        foreach ($transactions as $transaction) {
            // Only rows with a credit represent real cash that went into a bank
            // (the invoice-charge row has no account_id and nothing to reverse there).
            if ($transaction->account_id && (float) $transaction->credit > 0) {
                Bank::find($transaction->account_id)?->decrement('balance', (float) $transaction->credit);
            }
        }

        $firstRemovedRowId = $transactions->min('id');
        Transaction::where('invoice_id', $ticketSale->id)
            ->where('type', 'ticket_sale')
            ->delete();

        if ($ticketSale->client_id && $firstRemovedRowId) {
            $this->rebuildPartyRunningBalancesFrom((int) $ticketSale->client_id, (int) $firstRemovedRowId);
        }
    }

    // ── JOURNAL HELPERS ───────────────────────────────────────
    // Thin wrappers around the shared PostsSaleJournal trait, keeping the
    // ticket-sale-specific field mapping (invoice_no, sale_date, bank_id
    // from the request) in one place.

    private function createTicketSaleJournal($ticketSale, $request)
    {
        $this->createSaleJournal(
            'ticket_sale',
            $ticketSale->id,
            $ticketSale->company_id,
            $ticketSale->sale_date,
            $ticketSale->invoice_no,
            'Ticket sale — '.$ticketSale->invoice_no,
            (float) $ticketSale->total_amount,
            (float) $ticketSale->paid_amount,
            (float) $ticketSale->due_amount,
            $request->bank_id,
            config('accounts.ticket_sales_revenue'),
            $this->resolvePartyType($ticketSale->client),
            $ticketSale->client_id
        );
    }

    private function updateTicketSaleJournal($ticketSale, $request)
    {
        // Receipts posted after the invoice have their own journals. Keep the
        // recognition entry limited to the amount paid at creation/edit time;
        // otherwise an edit posts those later receipts a second time.
        $settled = $this->settledThroughTicketSalePaymentJournals($ticketSale->id);
        $paidOnSaleJournal = max(0.0, (float) $ticketSale->paid_amount - $settled);
        $dueOnSaleJournal = max(0.0, (float) $ticketSale->total_amount - $paidOnSaleJournal);

        $this->updateSaleJournal(
            'ticket_sale',
            $ticketSale->id,
            $ticketSale->company_id,
            $ticketSale->sale_date,
            $ticketSale->invoice_no,
            'Ticket sale (edited) — '.$ticketSale->invoice_no,
            (float) $ticketSale->total_amount,
            $paidOnSaleJournal,
            $dueOnSaleJournal,
            $request->bank_id ?: $ticketSale->bank_id,
            config('accounts.ticket_sales_revenue'),
            $ticketSale->status === 'cancelled',
            $this->resolvePartyType($ticketSale->client),
            $ticketSale->client_id
        );
    }

    /** Sum of AR credits in receipt journals (every journal after recognition). */
    private function settledThroughTicketSalePaymentJournals(int $ticketSaleId): float
    {
        $journalIds = JournalEntry::where('source', 'ticket_sale')
            ->where('source_id', $ticketSaleId)
            ->orderBy('id')
            ->pluck('id');

        if ($journalIds->count() < 2) {
            return 0.0;
        }

        $receivableAccountId = Account::where('code', config('accounts.accounts_receivable'))->value('id');

        return (float) JournalItem::whereIn('journal_entry_id', $journalIds->slice(1)->values())
            ->where('account_id', $receivableAccountId)
            ->sum('credit');
    }

    private function deleteTicketSaleJournal($ticketSaleId)
    {
        $this->deleteSaleJournal('ticket_sale', $ticketSaleId);
    }

    /**
     * The sale price (receivable from the agent) for a purchased ticket — defaults
     * to the purchase cost if the admin didn't enter/edit a sale price for that row.
     */
    private function resolveSalePrice(Request $request, TicketPurchase $p): float
    {
        return (float) ($request->input('sale_prices')[$p->id] ?? $p->amount);
    }

    public function store(Request $request, $role)
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|exists:users,id',
            'bank_id' => 'nullable|exists:banks,id',
            'payment_instruction_bank_id' => 'nullable|exists:banks,id',
            'status' => 'required|in:pending,confirm,cancelled,draft',
            'currency' => 'required|string|max:5',
            'sale_prices' => 'nullable|array',
            'sale_prices.*' => 'nullable|numeric|min:0',
            'sale_date' => 'required|date',
            'ticket_purchase_ids' => 'required|array|min:1',
            'ticket_purchase_ids.*' => 'exists:ticket_purchases,id',
            'paid_amount' => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date',
            'payment_method' => 'required',
            'payment_status' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $purchaseIds = $request->ticket_purchase_ids;

        $alreadySold = TicketSaleItem::whereIn('ticket_purchase_id', $purchaseIds)->pluck('ticket_purchase_id')->toArray();
        if (! empty($alreadySold)) {
            return response()->json([
                'success' => false,
                'message' => 'Some tickets are already sold: '.implode(', ', $alreadySold),
            ]);
        }

        $purchases = TicketPurchase::whereIn('id', $purchaseIds)->with('ticket')->get();
        if ($purchases->where('status', '!=', 'confirm')->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Only confirmed tickets can be sold.',
            ]);
        }

        $totalAmount = $purchases->sum(fn ($p) => $this->resolveSalePrice($request, $p));
        $paidAmount = min((float) ($request->paid_amount ?? 0), $totalAmount);
        $dueAmount = $totalAmount - $paidAmount;

        DB::beginTransaction();
        try {
            $ticketSale = TicketSale::create([
                'invoice_no' => TicketSale::nextInvoiceNumber(),
                'ticket_id' => $purchases->first()->ticket_id,
                'client_id' => $request->client_id,
                'bank_id' => $request->bank_id,
                'payment_instruction_bank_id' => $request->payment_instruction_bank_id,
                'status' => $request->status,
                'currency' => $request->currency,
                'sale_date' => $request->sale_date,
                'due_date' => $request->due_date,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'payment_status' => $request->payment_status,
                'company_id' => 2,
                'created_by' => Auth::id(),
            ]);

            foreach ($purchases as $p) {
                TicketSaleItem::create([
                    'ticket_sale_id' => $ticketSale->id,
                    'ticket_purchase_id' => $p->id,
                    'price' => $this->resolveSalePrice($request, $p),
                ]);
            }

            // Post the full sale amount as a charge on the party's ledger,
            // regardless of whether anything was paid at sale time.
            $this->postPartyLedger($ticketSale->client_id, [
                'type' => 'ticket_sale',
                'account_id' => null,
                'payment_date' => $request->sale_date ?? now(),
                'reference_no' => $ticketSale->invoice_no,
                'payment_method' => null,
                'invoice_id' => $ticketSale->id,
                'debit' => $totalAmount,
                'credit' => 0,
                'remarks' => 'Ticket sale invoice — '.$ticketSale->invoice_no,
            ]);

            if ($request->bank_id && $paidAmount > 0) {
                $this->manageBankPayment($request, $ticketSale->id, $paidAmount);
            }

            // ── JOURNAL (auto) ────────────────────────────────────────
            $this->createTicketSaleJournal($ticketSale, $request);
            // ── END JOURNAL ───────────────────────────────────────────

            if ($request->status === 'confirm') {
                $grouped = $purchases->groupBy('ticket_id');
                foreach ($grouped as $ticketId => $items) {
                    $ticketAcc = Ticket::find($ticketId);
                    if ($ticketAcc) {
                        $count = $items->count();
                        $ticketAcc->update([
                            'qty' => $ticketAcc->qty - $count,
                            'total_sale_qty' => $ticketAcc->total_sale_qty + $count,
                            'total_sale_amount' => $ticketAcc->total_sale_amount + $items->sum(fn ($p) => $this->resolveSalePrice($request, $p)),
                        ]);
                    }
                }
            }

            // ── RECEIVABLE SCHEDULE — track the due amount for follow-up ──────
            $this->syncTicketSaleReceivableSchedule($ticketSale);

            foreach ($purchases as $purchase) {
                app(TicketBaggage::class)->sync($purchase);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket sale created successfully! Invoice: '.$ticketSale->invoice_no,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket sale: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Keep the sale's receivable PaymentSchedule row in sync with its
     * current due amount and receivable date (deletes and recreates it,
     * matching how Contract Flight/File Sale keep theirs in sync).
     */
    /**
     * Ticket Trail (2026-10-26): one ticket's line on this invoice was re-priced,
     * added or taken off. Re-derive what update() derives from the items — the
     * total, due, the invoice charge row (in place, later rows shifted), the sale
     * journal and the receivable schedule — without touching receipts. With
     * $newClientId the invoice and its rows move to that buyer (update()'s own
     * move). Throws when receipts exceed the new total.
     */
    public function resyncFromItems(TicketSale $ticketSale, ?int $newClientId = null): void
    {
        $ticketSale->refresh();
        $total = round((float) TicketSaleItem::where('ticket_sale_id', $ticketSale->id)->sum('price'), 2);
        $paid = round((float) $ticketSale->paid_amount, 2);
        if ($paid - $total > 0.005) {
            throw new \RuntimeException('Invoice '.$ticketSale->invoice_no.' has ৳'.number_format($paid, 2).' received — more than its new total ৳'.number_format($total, 2).'.');
        }
        $oldClientId = (int) $ticketSale->client_id;
        $due = round($total - $paid, 2);
        $ticketSale->update([
            'total_amount' => $total,
            'due_amount' => $due,
            'payment_status' => $due <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'due'),
        ] + ($newClientId ? ['client_id' => $newClientId] : []));
        if ($newClientId && $newClientId !== $oldClientId) {
            $this->moveSaleLedgerToParty($ticketSale->id, $oldClientId, $newClientId);
        }

        $invoiceRow = Transaction::where('invoice_id', $ticketSale->id)->where('type', 'ticket_sale')->whereNull('account_id')->first();
        if ($invoiceRow) {
            $delta = round($total - (float) $invoiceRow->debit, 2);
            if ($delta != 0) {
                $invoiceRow->update(['debit' => $total, 'balance' => (float) $invoiceRow->balance + $delta]);
                Transaction::where('user_id', $invoiceRow->user_id)->where('id', '>', $invoiceRow->id)->increment('old_balance', $delta);
                Transaction::where('user_id', $invoiceRow->user_id)->where('id', '>', $invoiceRow->id)->increment('balance', $delta);
            }
        } elseif ($total > 0) {
            $this->postPartyLedger($ticketSale->client_id, [
                'type' => 'ticket_sale', 'account_id' => null, 'payment_date' => $ticketSale->sale_date ?? now(),
                'reference_no' => $ticketSale->invoice_no, 'payment_method' => null, 'invoice_id' => $ticketSale->id,
                'debit' => $total, 'credit' => 0, 'remarks' => 'Ticket sale invoice — '.$ticketSale->invoice_no,
            ]);
        }

        $this->updateTicketSaleJournal($ticketSale->refresh(), new Request);
        $this->syncTicketSaleReceivableSchedule($ticketSale);
        foreach ($ticketSale->items as $item) {
            if ($item->ticketPurchase) {
                app(TicketBaggage::class)->sync($item->ticketPurchase);
            }
        }
    }

    private function syncTicketSaleReceivableSchedule(TicketSale $ticketSale): void
    {
        PaymentSchedule::where('schedulable_type', TicketSale::class)
            ->where('schedulable_id', $ticketSale->id)
            ->delete();

        if ((float) $ticketSale->due_amount <= 0 || $ticketSale->status === 'cancelled') {
            return;
        }

        PaymentSchedule::create([
            'company_id' => $ticketSale->company_id,
            'schedulable_type' => TicketSale::class,
            'schedulable_id' => $ticketSale->id,
            'type' => 'receive',
            'party_type' => $this->resolvePartyType($ticketSale->client),
            'party_id' => $ticketSale->client_id,
            'source_label' => $ticketSale->invoice_no,
            'amount' => (float) $ticketSale->due_amount,
            'paid_amount' => 0,
            'scheduled_date' => $ticketSale->due_date ?: $ticketSale->sale_date,
            'status' => 'pending',
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show($role, $id)
    {
        try {
            $data = TicketSale::with(
                'ticket',
                'client',
                'item',
                'item.ticketPurchase',
                'item.ticketPurchase.passportHolder',
                'items.ticketPurchase.passportHolder',
                'items.ticketPurchase.ticket.from_airport',
                'items.ticketPurchase.ticket.to_airport',
                'transactions.account',
                'paymentInstructionBank'
            )->findOrFail($id);
            $company = Company::findOrFail($data->company_id);
            $invoiceTemplate = InvoiceTemplate::with('fields', 'style')->where('type', 'ticket_sale')->where('is_default', 1)->first();

            return view('dashboard.ticket_sales.show-v2', compact('data', 'company', 'invoiceTemplate'));
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to load ticket purchase data.']);
        }
    }

    /**
     * AJAX: voucher-style detail payload for the "View Details" modal.
     */
    public function detail($role, TicketSale $ticketSale)
    {
        $ticketSale->load([
            'client',
            'items.ticketPurchase.passportHolder',
            'items.ticketPurchase.ticket.from_airport',
            'items.ticketPurchase.ticket.to_airport',
        ]);

        return response()->json([
            'success' => true,
            'invoice_no' => $ticketSale->invoice_no,
            'client' => $ticketSale->client?->name ?? '—',
            'sale_date' => optional($ticketSale->sale_date)->format('d M Y') ?? $ticketSale->sale_date,
            'currency' => $ticketSale->currency,
            'status' => $ticketSale->status,
            'payment_status' => $ticketSale->payment_status,
            'total_amount' => (float) $ticketSale->total_amount,
            'paid_amount' => (float) $ticketSale->paid_amount,
            'due_amount' => (float) $ticketSale->due_amount,
            'items' => $ticketSale->items->map(fn ($i) => [
                'ticket_no' => $i->ticketPurchase?->ticket_no ?? '—',
                'passenger' => $i->ticketPurchase?->passportHolder?->name ?? '—',
                'route' => $i->ticketPurchase?->ticket?->title ?? '—',
                'from' => $i->ticketPurchase?->ticket?->from_airport?->code,
                'to' => $i->ticketPurchase?->ticket?->to_airport?->code,
                'price' => (float) $i->price,
            ])->values(),
        ]);
    }

    /**
     * AJAX: return sale data for the edit modal (no longer a separate page).
     */
    public function edit($role, TicketSale $ticketSale)
    {
        $ticketSale->load('items.ticketPurchase.ticket', 'items.ticketPurchase.passportHolder');

        return response()->json([
            'success' => true,
            'sale' => [
                'id' => $ticketSale->id,
                'invoice_no' => $ticketSale->invoice_no,
                'client_id' => $ticketSale->client_id,
                'bank_id' => $ticketSale->bank_id,
                'payment_instruction_bank_id' => $ticketSale->payment_instruction_bank_id,
                'status' => $ticketSale->status,
                'currency' => $ticketSale->currency,
                'sale_date' => optional($ticketSale->sale_date)->format('Y-m-d') ?? $ticketSale->sale_date,
                'due_date' => optional($ticketSale->due_date)->format('Y-m-d') ?? $ticketSale->due_date,
                'total_amount' => (float) $ticketSale->total_amount,
                'paid_amount' => (float) $ticketSale->paid_amount,
                'due_amount' => (float) $ticketSale->due_amount,
                'payment_status' => $ticketSale->payment_status,
                'items' => $ticketSale->items->map(fn ($i) => [
                    'purchase_id' => $i->ticket_purchase_id,
                    'ticket_no' => $i->ticketPurchase?->ticket_no,
                    'passenger' => $i->ticketPurchase?->passportHolder?->name ?? '—',
                    'route' => $i->ticketPurchase?->ticket?->title ?? '—',
                    'price' => (float) $i->price,
                ])->values(),
            ],
        ]);
    }

    public function update(Request $request, $role, TicketSale $ticketSale)
    {
        if ($blocked = $this->changedTicketOnSale($ticketSale->id)) {
            return response()->json(['success' => false, 'message' => $blocked]);
        }
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|exists:users,id',
            'bank_id' => 'nullable|exists:banks,id',
            'payment_instruction_bank_id' => 'nullable|exists:banks,id',
            'status' => 'required|in:pending,confirm,cancelled,draft',
            'currency' => 'required|string|max:5',
            'sale_prices' => 'nullable|array',
            'sale_prices.*' => 'nullable|numeric|min:0',
            'sale_date' => 'required|date',
            'ticket_purchase_ids' => 'required|array|min:1',
            'ticket_purchase_ids.*' => 'exists:ticket_purchases,id',
            'paid_amount' => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date',
            'payment_method' => 'required',
            'payment_status' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $purchaseIds = $request->ticket_purchase_ids;

        // Check if any ticket is already sold by another sale (excluding current sale)
        $alreadySold = TicketSaleItem::whereIn('ticket_purchase_id', $purchaseIds)
            ->where('ticket_sale_id', '!=', $ticketSale->id)
            ->pluck('ticket_purchase_id')
            ->toArray();

        if (! empty($alreadySold)) {
            return response()->json([
                'success' => false,
                'message' => 'Some tickets are already sold: '.implode(', ', $alreadySold),
            ]);
        }

        $purchases = TicketPurchase::whereIn('id', $purchaseIds)->with('ticket')->get();
        if ($purchases->where('status', '!=', 'confirm')->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Only confirmed tickets can be sold.',
            ]);
        }

        $totalAmount = $purchases->sum(fn ($p) => $this->resolveSalePrice($request, $p));
        $newPaidAmount = (float) ($request->paid_amount ?? 0);

        DB::beginTransaction();
        try {
            $oldStatus = $ticketSale->status;
            $oldPaidAmount = (float) $ticketSale->paid_amount;
            $oldClientId = (int) $ticketSale->client_id;
            $newStatus = $request->status;

            // Snapshot old items (with their ticket_id via ticketPurchase) before they get replaced
            $oldItems = $ticketSale->items()->with('ticketPurchase')->get();

            // -------------------------------------------------------
            // 1. HANDLE PAYMENTS BASED ON STATUS
            // -------------------------------------------------------
            $merge_paid_amount = null;
            $merge_due_amount = null;
            $merge_payment_status = null;

            if ($newStatus === 'cancelled') {

                // Reverse ALL ledger rows for this sale → refund cash back out of the bank
                // (only rows with a credit represent real cash that went into a bank).
                $ledgerTransactions = Transaction::where('invoice_id', $ticketSale->id)
                    ->where('type', 'ticket_sale')
                    ->get();

                $ledgerTransactions
                    ->filter(fn ($transaction) => $transaction->account_id && (float) $transaction->credit > 0)
                    ->groupBy('account_id')
                    ->each(function ($bankTransactions, $bankId) {
                        $bank = Bank::find($bankId);
                        if ($bank?->account_id) {
                            Bank::assertCanCover(
                                (int) $bank->account_id,
                                (float) $bankTransactions->sum('credit'),
                                $bank->name
                            );
                        }
                    });

                foreach ($ledgerTransactions as $transaction) {
                    if ($transaction->account_id && (float) $transaction->credit > 0) {
                        Bank::find($transaction->account_id)?->decrement('balance', (float) $transaction->credit);
                    }
                }
                Transaction::where('invoice_id', $ticketSale->id)
                    ->where('type', 'ticket_sale')
                    ->delete();
                if ($oldClientId && ($firstRemovedRowId = $ledgerTransactions->min('id'))) {
                    $this->rebuildPartyRunningBalancesFrom($oldClientId, (int) $firstRemovedRowId);
                }

                // Reset paid/due amounts on the sale record
                $ticketSale->update([
                    'paid_amount' => 0,
                    'due_amount' => $totalAmount,
                    'payment_status' => 'due',
                ]);
            } else {
                // Accumulate paid amount on top of what was already paid
                $merge_paid_amount = min($oldPaidAmount + $newPaidAmount, $totalAmount);
                $merge_due_amount = $totalAmount - $merge_paid_amount;
                $merge_payment_status = $merge_due_amount <= 0 ? 'paid' : ($merge_paid_amount > 0 ? 'partial' : 'due');
            }

            // -------------------------------------------------------
            // 2. UPDATE THE MAIN RECORD
            // -------------------------------------------------------
            $requestData = [
                'client_id' => $request->client_id,
                'bank_id' => $request->bank_id,
                'payment_instruction_bank_id' => $request->payment_instruction_bank_id,
                'status' => $newStatus,
                'currency' => $request->currency,
                'sale_date' => $request->sale_date,
                'due_date' => $request->due_date,
                'total_amount' => $totalAmount,
                'ticket_id' => $purchases->first()->ticket_id,
                'updated_by' => Auth::id(),
            ];

            if ($newStatus !== 'cancelled') {
                $requestData['paid_amount'] = $merge_paid_amount;
                $requestData['due_amount'] = $merge_due_amount;
                $requestData['payment_status'] = $merge_payment_status;
            }

            $ticketSale->update($requestData);

            if ($newStatus !== 'cancelled' && $oldClientId !== (int) $ticketSale->client_id) {
                $this->moveSaleLedgerToParty($ticketSale->id, $oldClientId, (int) $ticketSale->client_id);
            }

            // -------------------------------------------------------
            // 3. REPLACE THE SALE ITEMS
            // -------------------------------------------------------
            TicketSaleItem::where('ticket_sale_id', $ticketSale->id)->delete();

            foreach ($purchases as $p) {
                TicketSaleItem::create([
                    'ticket_sale_id' => $ticketSale->id,
                    'ticket_purchase_id' => $p->id,
                    'price' => $this->resolveSalePrice($request, $p),
                ]);
            }

            // -------------------------------------------------------
            // 3b. RECONCILE THE INVOICE-CHARGE LEDGER ROW (total may have changed)
            // -------------------------------------------------------
            if ($newStatus !== 'cancelled') {
                $invoiceRow = Transaction::where('invoice_id', $ticketSale->id)
                    ->where('type', 'ticket_sale')
                    ->whereNull('account_id')
                    ->first();

                if ($invoiceRow) {
                    $delta = $totalAmount - (float) $invoiceRow->debit;
                    $ledgerChanges = [
                        'payment_date' => $request->sale_date,
                        'created_at' => Carbon::parse($request->sale_date)->setTimeFrom($invoiceRow->created_at ?? now()),
                    ];
                    if ($delta != 0) {
                        $ledgerChanges += [
                            'debit' => $totalAmount,
                            'balance' => (float) $invoiceRow->balance + $delta,
                        ];
                    }
                    $invoiceRow->update($ledgerChanges);

                    if ($delta != 0) {
                        // Every later transaction on this party's ledger was chained off
                        // the old balance — shift them all by the same delta so the
                        // running balance stays consistent down the whole chain.
                        Transaction::where('user_id', $ticketSale->client_id)
                            ->where('id', '>', $invoiceRow->id)
                            ->increment('old_balance', $delta);
                        Transaction::where('user_id', $ticketSale->client_id)
                            ->where('id', '>', $invoiceRow->id)
                            ->increment('balance', $delta);
                    }
                } else {
                    $this->postPartyLedger($ticketSale->client_id, [
                        'type' => 'ticket_sale',
                        'account_id' => null,
                        'payment_date' => $request->sale_date ?? now(),
                        'reference_no' => $ticketSale->invoice_no,
                        'payment_method' => null,
                        'invoice_id' => $ticketSale->id,
                        'debit' => $totalAmount,
                        'credit' => 0,
                        'remarks' => 'Ticket sale invoice — '.$ticketSale->invoice_no,
                    ]);
                }
            }

            // -------------------------------------------------------
            // 4. APPLY NEW BANK PAYMENT (only if a new payment is made)
            // -------------------------------------------------------
            if ($request->bank_id && $newPaidAmount > 0 && $newStatus !== 'cancelled') {
                $this->manageBankPayment($request, $ticketSale->id, $newPaidAmount);
            }

            // -------------------------------------------------------
            // JOURNAL UPDATE
            // -------------------------------------------------------
            $this->updateTicketSaleJournal($ticketSale, $request);

            // -------------------------------------------------------
            // RECEIVABLE SCHEDULE — keep in sync with the current due amount
            // -------------------------------------------------------
            $this->syncTicketSaleReceivableSchedule($ticketSale);

            // -------------------------------------------------------
            // 5. MANAGE TICKET QTY
            // -------------------------------------------------------

            // Revert qty changes on old tickets if status was previously confirm
            if ($oldStatus === 'confirm') {
                $oldGrouped = $oldItems->groupBy(fn ($item) => $item->ticketPurchase->ticket_id ?? null);
                foreach ($oldGrouped as $ticketId => $items) {
                    if (! $ticketId) {
                        continue;
                    }
                    $oldTicket = Ticket::find($ticketId);
                    if ($oldTicket) {
                        $count = $items->count();
                        $oldTicket->update([
                            'qty' => $oldTicket->qty + $count,
                            'total_sale_qty' => $oldTicket->total_sale_qty - $count,
                            'total_sale_amount' => $oldTicket->total_sale_amount - $items->sum('price'),
                        ]);
                    }
                }
            }

            // Apply qty changes on new tickets if new status is confirm
            if ($newStatus === 'confirm') {
                $newGrouped = $purchases->groupBy('ticket_id');
                foreach ($newGrouped as $ticketId => $items) {
                    $ticketAcc = Ticket::find($ticketId);
                    if ($ticketAcc) {
                        $count = $items->count();
                        $ticketAcc->update([
                            'qty' => $ticketAcc->qty - $count,
                            'total_sale_qty' => $ticketAcc->total_sale_qty + $count,
                            'total_sale_amount' => $ticketAcc->total_sale_amount + $items->sum(fn ($p) => $this->resolveSalePrice($request, $p)),
                        ]);
                    }
                }
            }

            foreach ($oldItems->pluck('ticket_purchase_id')->merge($purchases->pluck('id'))->unique() as $purchaseId) {
                if ($purchase = TicketPurchase::find($purchaseId)) {
                    app(TicketBaggage::class)->sync($purchase);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket sale updated successfully!',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket sale: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $role, $id)
    {
        $ticketSale = TicketSale::find($request->item_id);
        if (! $ticketSale) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket sale not found.',
            ]);
        }
        if ($blocked = $this->changedTicketOnSale($ticketSale->id)) {
            return response()->json(['success' => false, 'message' => $blocked]);
        }

        DB::beginTransaction();
        try {
            // Revert ticket qty if the sale was confirmed
            if ($ticketSale->status === 'confirm') {
                $ticketAcc = Ticket::find($ticketSale->ticket_id);
                if ($ticketAcc) {
                    $ticketAcc->update([
                        'qty' => $ticketAcc->qty + 1,
                        'total_sale_qty' => $ticketAcc->total_sale_qty - 1,
                        'total_sale_amount' => $ticketAcc->total_sale_amount - (float) $ticketSale->paid_amount,
                    ]);
                }
            }

            $baggagePurchases = TicketPurchase::whereIn('id', $ticketSale->items()->pluck('ticket_purchase_id'))->get();

            // Delete related sale items
            TicketSaleItem::where('ticket_sale_id', $request->item_id)->delete();

            // Reverse any bank payments and remove the invoice-charge row from the agent's ledger
            $this->reverseBankPayment($ticketSale);

            // JOURNAL CLEANUP ← ADD HERE
            $this->deleteTicketSaleJournal($ticketSale->id);

            // Delete the sale itself
            $ticketSale->forceDelete();
            foreach ($baggagePurchases as $purchase) {
                app(TicketBaggage::class)->sync($purchase);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket sale deleted successfully!',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ticket sale: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * A ticket on this sale was refunded / voided / re-issued from Ticket
     * Orders (2026-10-06): its agent price was reversed, or a change charged,
     * by rows of their own. Editing or deleting the invoice would reverse the
     * same price a second time — refused, with the PNR named.
     */
    private function changedTicketOnSale(int $saleId): ?string
    {
        try {
            if (! Schema::hasTable('ticket_changes')) {
                return null;
            }
            $pnr = DB::table('ticket_sale_items as i')
                ->join('ticket_changes as c', 'c.ticket_purchase_id', '=', 'i.ticket_purchase_id')
                ->join('ticket_purchases as p', 'p.id', '=', 'i.ticket_purchase_id')
                ->where('i.ticket_sale_id', $saleId)
                ->where(fn ($q) => $q->where('c.sale_reversed', '>', 0)->orWhere('c.charged', '>', 0))
                ->value('p.ticket_no');

            return $pnr ? 'Ticket '.$pnr.' on this invoice was changed from Ticket Orders (refund / void / re-issue) and its postings stand on this sale — the invoice can no longer be edited or deleted.' : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function make_payment(Request $request, $role, $id)
    {
        try {
            $validated = $request->validate([
                'payment_method' => 'required|in:cash,card,cheque,bank_transfer,mobile_banking,other',
                'bank_id' => 'required|exists:banks,id',
                'payment_date' => 'required|date|before_or_equal:today',
                'transaction_time' => 'required|date_format:H:i',
                'reference_no' => 'nullable|string|max:255',
                'payment_amount' => 'required|numeric|min:0.01',
                'payer_bank' => 'required_if:payment_method,bank_transfer,cheque,mobile_banking|nullable|string|max:150',
                'payer_reference' => 'required_if:payment_method,bank_transfer,cheque,mobile_banking|nullable|string|max:100',
                'notes' => 'nullable|string|max:500',
            ]);

            $bank = Bank::findOrFail($validated['bank_id']);
            $allowedTypes = match ($validated['payment_method']) {
                'cash' => ['cash'], 'mobile_banking' => ['mobile_banking', 'digital_wallet'],
                'bank_transfer', 'cheque', 'card' => ['bank'], default => null,
            };
            if ($allowedTypes && ! in_array($bank->type, $allowedTypes, true)) {
                return redirect()->back()->with('error', 'The selected company account does not match the payment method.');
            }

            DB::transaction(function () use ($validated, $id, $bank) {
                $ticketSale = TicketSale::whereKey($id)->with('client.roles')->lockForUpdate()->firstOrFail();
                $reference = $validated['reference_no'] ?: $ticketSale->invoice_no.'-PAY-'.now()->format('YmdHis');
                app(PartyInvoiceAllocation::class)->allocateMany(
                    (int) $ticketSale->client_id, (float) $validated['payment_amount'], 'manual',
                    [['key' => 'ticket_sale:'.$ticketSale->id, 'amount' => $validated['payment_amount']]],
                    'sale', (int) $bank->id, $validated['payment_method'], $validated['payment_date'], $reference
                );
                $bank->increment('balance', $validated['payment_amount']);

                $journal = $this->postSaleJournalPayment(
                    'ticket_sale',
                    $ticketSale->id,
                    TravelCompany::ID,
                    $validated['payment_date'],
                    $reference,
                    'Ticket sale payment received — '.$ticketSale->invoice_no,
                    (float) $validated['payment_amount'],
                    $validated['bank_id'],
                    $this->resolvePartyType($ticketSale->client),
                    $ticketSale->client_id
                );
                $journal->update([
                    'entry_time' => $validated['transaction_time'].':00',
                    'beneficiary' => $ticketSale->client?->name,
                    'beneficiary_party_id' => $ticketSale->client_id,
                    'beneficiary_method' => match ($validated['payment_method']) {
                        'cash' => 'cash', 'cheque' => 'cheque',
                        'bank_transfer', 'mobile_banking', 'card' => 'bank', default => null,
                    },
                    'beneficiary_bank' => $validated['payer_bank'] ?? null,
                    'beneficiary_ref' => $validated['payer_reference'] ?? null,
                    'description' => 'Ticket sale payment received — '.$ticketSale->invoice_no
                        .(filled($validated['notes'] ?? null) ? ' — '.$validated['notes'] : ''),
                ]);
            });
        } catch (\Throwable $th) {
            DB::rollBack();

            return redirect()->back()->with('error', $th->getMessage());
        }

        return redirect()->back()->with('success', 'Payment of ৳'.number_format($validated['payment_amount'], 2).' added successfully!');
    }
}
