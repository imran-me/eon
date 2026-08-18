<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Models\PaymentSchedule;
use App\Models\Ticket;
use App\Models\TicketPurchase;
use App\Models\TicketSale;
use App\Models\TicketSaleItem;
use App\Models\Transaction;
use App\Models\User;
use App\Traits\PostsSaleJournal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;

class TicketSaleController implements HasMiddleware
{
    use PostsSaleJournal;

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
            'client',
            'ticket',
            'item.ticketPurchase.passportHolder',
            'item.ticketPurchase.vendor',
            'item.ticketPurchase.portal',
            'item.ticketPurchase.legs',
            'items.ticketPurchase.passportHolder',
            'items.ticketPurchase.ticket.from_airport',
            'items.ticketPurchase.ticket.to_airport',
        ]);

        // Filter by Invoice Number
        $query->when($request->filled('invoice_no'), function ($q) use ($request) {
            $q->where('invoice_no', 'like', "%{$request->invoice_no}%");
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
            'revenue_mtd'     => (clone $mtdSales)->sum('total_amount'),
            'total_due'       => TicketSale::where('status', '!=', 'cancelled')->where('payment_status', '!=', 'paid')->sum('due_amount'),
            'profit_mtd'      => TicketSaleItem::whereHas('sale', function ($q) {
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
                'id'        => $p->id,
                'ticket_no' => $p->ticket_no,
                'passenger' => $p->passportHolder?->name ?? '—',
                'route'     => $p->ticket?->title ?? '—',
                // 'price'     => (float) ($p->ticket?->price ?? 0),
                'price'     => (float) ($p->amount ?? 0),
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

        return Transaction::create(array_merge([
            'user_id'     => $partyId,
            'user_type'   => 'customer',
            'old_balance' => $oldBalance,
            'balance'     => $newBalance,
        ], $attrs));
    }

    public function manageBankPayment($request, $ticket_sale_id, $paid_amount)
    {
        $ticketSale = TicketSale::find($ticket_sale_id);

        $this->postPartyLedger($ticketSale->client_id, [
            'type'           => 'ticket_sale',
            'account_id'     => $request->bank_id,
            'payment_date'   => $request->sale_date ?? now(),
            'reference_no'   => $request->reference_no,
            'payment_method' => $request->payment_method,
            'invoice_id'     => $ticket_sale_id,
            'debit'          => 0,
            'credit'         => $paid_amount,
            'remarks'        => 'Ticket sale payment received — ' . $ticketSale->invoice_no,
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

        if ($transactions->isEmpty()) return;

        foreach ($transactions as $transaction) {
            // Only rows with a credit represent real cash that went into a bank
            // (the invoice-charge row has no account_id and nothing to reverse there).
            if ($transaction->account_id && (float) $transaction->credit > 0) {
                Bank::find($transaction->account_id)?->decrement('balance', (float) $transaction->credit);
            }
        }

        Transaction::where('invoice_id', $ticketSale->id)
            ->where('type', 'ticket_sale')
            ->delete();
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
            'Ticket sale — ' . $ticketSale->invoice_no,
            (float) $ticketSale->total_amount,
            (float) $ticketSale->paid_amount,
            (float) $ticketSale->due_amount,
            $request->bank_id,
            config('accounts.ticket_sales_revenue')
        );
    }

    private function updateTicketSaleJournal($ticketSale, $request)
    {
        $this->updateSaleJournal(
            'ticket_sale',
            $ticketSale->id,
            $ticketSale->company_id,
            $ticketSale->sale_date,
            $ticketSale->invoice_no,
            'Ticket sale (edited) — ' . $ticketSale->invoice_no,
            (float) $ticketSale->total_amount,
            (float) $ticketSale->paid_amount,
            (float) $ticketSale->due_amount,
            $request->bank_id,
            config('accounts.ticket_sales_revenue'),
            $ticketSale->status === 'cancelled'
        );
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
            'client_id'              => 'required|exists:users,id',
            'bank_id'                => 'nullable|exists:banks,id',
            'status'                 => 'required|in:pending,confirm,cancelled,draft',
            'currency'               => 'required|string|max:5',
            'sale_prices'            => 'nullable|array',
            'sale_prices.*'          => 'nullable|numeric|min:0',
            'sale_date'              => 'required|date',
            'ticket_purchase_ids'    => 'required|array|min:1',
            'ticket_purchase_ids.*'  => 'exists:ticket_purchases,id',
            'paid_amount'            => 'nullable|numeric|min:0',
            'due_date'               => 'nullable|date',
            'payment_method'         => 'required',
            'payment_status'         => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        $purchaseIds = $request->ticket_purchase_ids;

        $alreadySold = TicketSaleItem::whereIn('ticket_purchase_id', $purchaseIds)->pluck('ticket_purchase_id')->toArray();
        if (!empty($alreadySold)) {
            return response()->json([
                'success' => false,
                'message' => 'Some tickets are already sold: ' . implode(', ', $alreadySold)
            ]);
        }

        $purchases = TicketPurchase::whereIn('id', $purchaseIds)->with('ticket')->get();
        if ($purchases->where('status', '!=', 'confirm')->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Only confirmed tickets can be sold.'
            ]);
        }

        $totalAmount = $purchases->sum(fn ($p) => $this->resolveSalePrice($request, $p));
        $paidAmount  = min((float) ($request->paid_amount ?? 0), $totalAmount);
        $dueAmount   = $totalAmount - $paidAmount;

        DB::beginTransaction();
        try {
            $ticketSale = TicketSale::create([
                'invoice_no'     => TicketSale::nextInvoiceNumber(),
                'ticket_id'      => $purchases->first()->ticket_id,
                'client_id'      => $request->client_id,
                'bank_id'        => $request->bank_id,
                'status'         => $request->status,
                'currency'       => $request->currency,
                'sale_date'      => $request->sale_date,
                'due_date'       => $request->due_date,
                'total_amount'   => $totalAmount,
                'paid_amount'    => $paidAmount,
                'due_amount'     => $dueAmount,
                'payment_status' => $request->payment_status,
                'company_id'     => 2,
                'created_by'     => Auth::id(),
            ]);

            foreach ($purchases as $p) {
                TicketSaleItem::create([
                    'ticket_sale_id'     => $ticketSale->id,
                    'ticket_purchase_id' => $p->id,
                    'price'              => $this->resolveSalePrice($request, $p),
                ]);
            }

            // Post the full sale amount as a charge on the party's ledger,
            // regardless of whether anything was paid at sale time.
            $this->postPartyLedger($ticketSale->client_id, [
                'type'           => 'ticket_sale',
                'account_id'     => null,
                'payment_date'   => $request->sale_date ?? now(),
                'reference_no'   => $ticketSale->invoice_no,
                'payment_method' => null,
                'invoice_id'     => $ticketSale->id,
                'debit'          => $totalAmount,
                'credit'         => 0,
                'remarks'        => 'Ticket sale invoice — ' . $ticketSale->invoice_no,
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

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Ticket sale created successfully! Invoice: ' . $ticketSale->invoice_no,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket sale: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Keep the sale's receivable PaymentSchedule row in sync with its
     * current due amount and receivable date (deletes and recreates it,
     * matching how Contract Flight/File Sale keep theirs in sync).
     */
    private function syncTicketSaleReceivableSchedule(TicketSale $ticketSale): void
    {
        PaymentSchedule::where('schedulable_type', TicketSale::class)
            ->where('schedulable_id', $ticketSale->id)
            ->delete();

        if ((float) $ticketSale->due_amount <= 0 || $ticketSale->status === 'cancelled') {
            return;
        }

        PaymentSchedule::create([
            'company_id'       => $ticketSale->company_id,
            'schedulable_type' => TicketSale::class,
            'schedulable_id'   => $ticketSale->id,
            'type'             => 'receive',
            'party_type'       => 'agent',
            'party_id'         => $ticketSale->client_id,
            'source_label'     => $ticketSale->invoice_no,
            'amount'           => (float) $ticketSale->due_amount,
            'paid_amount'      => 0,
            'scheduled_date'   => $ticketSale->due_date ?: $ticketSale->sale_date,
            'status'           => 'pending',
            'created_by'       => Auth::id(),
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
                'transactions.account'
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
            'success'        => true,
            'invoice_no'     => $ticketSale->invoice_no,
            'client'         => $ticketSale->client?->name ?? '—',
            'sale_date'      => optional($ticketSale->sale_date)->format('d M Y') ?? $ticketSale->sale_date,
            'currency'       => $ticketSale->currency,
            'status'         => $ticketSale->status,
            'payment_status' => $ticketSale->payment_status,
            'total_amount'   => (float) $ticketSale->total_amount,
            'paid_amount'    => (float) $ticketSale->paid_amount,
            'due_amount'     => (float) $ticketSale->due_amount,
            'items' => $ticketSale->items->map(fn ($i) => [
                'ticket_no' => $i->ticketPurchase?->ticket_no ?? '—',
                'passenger' => $i->ticketPurchase?->passportHolder?->name ?? '—',
                'route'     => $i->ticketPurchase?->ticket?->title ?? '—',
                'from'      => $i->ticketPurchase?->ticket?->from_airport?->code,
                'to'        => $i->ticketPurchase?->ticket?->to_airport?->code,
                'price'     => (float) $i->price,
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
                'id'             => $ticketSale->id,
                'invoice_no'     => $ticketSale->invoice_no,
                'client_id'      => $ticketSale->client_id,
                'bank_id'        => $ticketSale->bank_id,
                'status'         => $ticketSale->status,
                'currency'       => $ticketSale->currency,
                'sale_date'      => optional($ticketSale->sale_date)->format('Y-m-d') ?? $ticketSale->sale_date,
                'due_date'       => optional($ticketSale->due_date)->format('Y-m-d') ?? $ticketSale->due_date,
                'total_amount'   => (float) $ticketSale->total_amount,
                'paid_amount'    => (float) $ticketSale->paid_amount,
                'due_amount'     => (float) $ticketSale->due_amount,
                'payment_status' => $ticketSale->payment_status,
                'items' => $ticketSale->items->map(fn ($i) => [
                    'purchase_id' => $i->ticket_purchase_id,
                    'ticket_no'   => $i->ticketPurchase?->ticket_no,
                    'passenger'   => $i->ticketPurchase?->passportHolder?->name ?? '—',
                    'route'       => $i->ticketPurchase?->ticket?->title ?? '—',
                    'price'       => (float) $i->price,
                ])->values(),
            ],
        ]);
    }

    public function update(Request $request, $role, TicketSale $ticketSale)
    {
        $validator = Validator::make($request->all(), [
            'client_id'              => 'required|exists:users,id',
            'bank_id'                => 'nullable|exists:banks,id',
            'status'                 => 'required|in:pending,confirm,cancelled,draft',
            'currency'               => 'required|string|max:5',
            'sale_prices'            => 'nullable|array',
            'sale_prices.*'          => 'nullable|numeric|min:0',
            'sale_date'              => 'required|date',
            'ticket_purchase_ids'    => 'required|array|min:1',
            'ticket_purchase_ids.*'  => 'exists:ticket_purchases,id',
            'paid_amount'            => 'nullable|numeric|min:0',
            'due_date'               => 'nullable|date',
            'payment_method'         => 'required',
            'payment_status'         => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        $purchaseIds = $request->ticket_purchase_ids;

        // Check if any ticket is already sold by another sale (excluding current sale)
        $alreadySold = TicketSaleItem::whereIn('ticket_purchase_id', $purchaseIds)
            ->where('ticket_sale_id', '!=', $ticketSale->id)
            ->pluck('ticket_purchase_id')
            ->toArray();

        if (!empty($alreadySold)) {
            return response()->json([
                'success' => false,
                'message' => 'Some tickets are already sold: ' . implode(', ', $alreadySold)
            ]);
        }

        $purchases = TicketPurchase::whereIn('id', $purchaseIds)->with('ticket')->get();
        if ($purchases->where('status', '!=', 'confirm')->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Only confirmed tickets can be sold.'
            ]);
        }

        $totalAmount = $purchases->sum(fn ($p) => $this->resolveSalePrice($request, $p));
        $newPaidAmount = (float) ($request->paid_amount ?? 0);

        DB::beginTransaction();
        try {
            $oldStatus     = $ticketSale->status;
            $oldPaidAmount = (float) $ticketSale->paid_amount;
            $newStatus     = $request->status;

            // Snapshot old items (with their ticket_id via ticketPurchase) before they get replaced
            $oldItems = $ticketSale->items()->with('ticketPurchase')->get();

            // -------------------------------------------------------
            // 1. HANDLE PAYMENTS BASED ON STATUS
            // -------------------------------------------------------
            $merge_paid_amount    = null;
            $merge_due_amount     = null;
            $merge_payment_status = null;

            if ($newStatus === 'cancelled') {

                // Reverse ALL ledger rows for this sale → refund cash back out of the bank
                // (only rows with a credit represent real cash that went into a bank).
                $ledgerTransactions = Transaction::where('invoice_id', $ticketSale->id)
                    ->where('type', 'ticket_sale')
                    ->get();

                foreach ($ledgerTransactions as $transaction) {
                    if ($transaction->account_id && (float) $transaction->credit > 0) {
                        Bank::find($transaction->account_id)?->decrement('balance', (float) $transaction->credit);
                    }
                }
                Transaction::where('invoice_id', $ticketSale->id)
                    ->where('type', 'ticket_sale')
                    ->delete();

                // Reset paid/due amounts on the sale record
                $ticketSale->update([
                    'paid_amount'    => 0,
                    'due_amount'     => $totalAmount,
                    'payment_status' => 'due',
                ]);
            } else {
                // Accumulate paid amount on top of what was already paid
                $merge_paid_amount    = min($oldPaidAmount + $newPaidAmount, $totalAmount);
                $merge_due_amount     = $totalAmount - $merge_paid_amount;
                $merge_payment_status = $merge_due_amount <= 0 ? 'paid' : ($merge_paid_amount > 0 ? 'partial' : 'due');
            }

            // -------------------------------------------------------
            // 2. UPDATE THE MAIN RECORD
            // -------------------------------------------------------
            $requestData = [
                'client_id'    => $request->client_id,
                'bank_id'      => $request->bank_id,
                'status'       => $newStatus,
                'currency'     => $request->currency,
                'sale_date'    => $request->sale_date,
                'due_date'     => $request->due_date,
                'total_amount' => $totalAmount,
                'ticket_id'    => $purchases->first()->ticket_id,
                'updated_by'   => Auth::id(),
            ];

            if ($newStatus !== 'cancelled') {
                $requestData['paid_amount']    = $merge_paid_amount;
                $requestData['due_amount']     = $merge_due_amount;
                $requestData['payment_status'] = $merge_payment_status;
            }

            $ticketSale->update($requestData);

            // -------------------------------------------------------
            // 3. REPLACE THE SALE ITEMS
            // -------------------------------------------------------
            TicketSaleItem::where('ticket_sale_id', $ticketSale->id)->delete();

            foreach ($purchases as $p) {
                TicketSaleItem::create([
                    'ticket_sale_id'     => $ticketSale->id,
                    'ticket_purchase_id' => $p->id,
                    'price'              => $this->resolveSalePrice($request, $p),
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
                    if ($delta != 0) {
                        $invoiceRow->update([
                            'debit'   => $totalAmount,
                            'balance' => (float) $invoiceRow->balance + $delta,
                        ]);

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
                        'type'           => 'ticket_sale',
                        'account_id'     => null,
                        'payment_date'   => $request->sale_date ?? now(),
                        'reference_no'   => $ticketSale->invoice_no,
                        'payment_method' => null,
                        'invoice_id'     => $ticketSale->id,
                        'debit'          => $totalAmount,
                        'credit'         => 0,
                        'remarks'        => 'Ticket sale invoice — ' . $ticketSale->invoice_no,
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
                $oldGrouped = $oldItems->groupBy(fn($item) => $item->ticketPurchase->ticket_id ?? null);
                foreach ($oldGrouped as $ticketId => $items) {
                    if (!$ticketId) continue;
                    $oldTicket = Ticket::find($ticketId);
                    if ($oldTicket) {
                        $count = $items->count();
                        $oldTicket->update([
                            'qty'               => $oldTicket->qty + $count,
                            'total_sale_qty'    => $oldTicket->total_sale_qty - $count,
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
                            'qty'               => $ticketAcc->qty - $count,
                            'total_sale_qty'    => $ticketAcc->total_sale_qty + $count,
                            'total_sale_amount' => $ticketAcc->total_sale_amount + $items->sum(fn ($p) => $this->resolveSalePrice($request, $p)),
                        ]);
                    }
                }
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Ticket sale updated successfully!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket sale: ' . $e->getMessage()
            ]);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $role, $id)
    {
        $ticketSale = TicketSale::find($request->item_id);
        if (!$ticketSale) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket sale not found.'
            ]);
        }

        DB::beginTransaction();
        try {
            // Revert ticket qty if the sale was confirmed
            if ($ticketSale->status === 'confirm') {
                $ticketAcc = Ticket::find($ticketSale->ticket_id);
                if ($ticketAcc) {
                    $ticketAcc->update([
                        'qty'               => $ticketAcc->qty + 1,
                        'total_sale_qty'    => $ticketAcc->total_sale_qty - 1,
                        'total_sale_amount' => $ticketAcc->total_sale_amount - (float) $ticketSale->paid_amount,
                    ]);
                }
            }

            // Delete related sale items
            TicketSaleItem::where('ticket_sale_id', $request->item_id)->delete();

            // Reverse any bank payments and remove the invoice-charge row from the agent's ledger
            $this->reverseBankPayment($ticketSale);

            // JOURNAL CLEANUP ← ADD HERE
            $this->deleteTicketSaleJournal($ticketSale->id);

            // Delete the sale itself
            $ticketSale->forceDelete();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Ticket sale deleted successfully!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ticket sale: ' . $e->getMessage()
            ]);
        }
    }

    public function make_payment(Request $request, $role, $id)
    {
        try {
            $validated = $request->validate([
                'payment_method' => 'required|in:cash,card,advance,checque,bank_transfer,mobile_banking,other',
                'bank_id'        => 'required|exists:banks,id',
                'payment_date'   => 'required|date',
                'reference_no'   => 'nullable|string|max:255',
                'payment_amount' => 'required|numeric|min:0.01',
                'schedule_id'    => 'nullable|exists:payment_schedules,id',
            ]);

            $ticketSale = TicketSale::findOrFail($id);

            if ($validated['payment_amount'] > $ticketSale->due_amount) {
                return redirect()->back()->with('error', 'Payment amount cannot exceed due amount!');
            }

            DB::transaction(function () use ($validated, $ticketSale) {

                $this->postPartyLedger($ticketSale->client_id, [
                    'type'           => 'ticket_sale',
                    'account_id'     => $validated['bank_id'],
                    'payment_date'   => $validated['payment_date'],
                    'reference_no'   => $validated['reference_no'],
                    'payment_method' => $validated['payment_method'],
                    'invoice_id'     => $ticketSale->id,
                    'debit'          => 0,
                    'credit'         => $validated['payment_amount'],
                    'remarks'        => 'Ticket sale due receipt (make_payment).',
                ]);

                $acc = Bank::find($validated['bank_id']);
                if ($acc) {
                    $acc->increment('balance', $validated['payment_amount']);
                }

                $newPaid = min($ticketSale->paid_amount + $validated['payment_amount'], $ticketSale->total_amount);
                $newDue  = max($ticketSale->due_amount - $validated['payment_amount'], 0);

                $ticketSale->update([
                    'paid_amount'    => $newPaid,
                    'due_amount'     => $newDue,
                    'payment_status' => $newDue <= 0 ? 'paid' : 'partial',
                ]);

                // ── JOURNAL: Debit Bank / Credit AR 1130 ─────────────────────
                $this->postSaleJournalPayment(
                    'ticket_sale',
                    $ticketSale->id,
                    auth()->user()->company_id ?? 2,
                    $validated['payment_date'],
                    $validated['reference_no'] ?? $ticketSale->invoice_no,
                    'Ticket sale payment received — ' . $ticketSale->invoice_no,
                    (float) $validated['payment_amount'],
                    $validated['bank_id']
                );
                // ── END JOURNAL ───────────────────────────────────────────────

                if (!empty($validated['schedule_id'])) {
                    \App\Models\PaymentSchedule::where('id', $validated['schedule_id'])
                        ->where('schedulable_type', \App\Models\TicketSale::class)
                        ->where('schedulable_id', $ticketSale->id)
                        ->update(['status' => 'paid', 'paid_date' => $validated['payment_date']]);
                }
            });
        } catch (\Throwable $th) {
            DB::rollBack();
            return redirect()->back()->with('error', $th->getMessage());
        }

        return redirect()->back()->with('success', 'Payment of ৳' . number_format($validated['payment_amount'], 2) . ' added successfully!');
    }
}
