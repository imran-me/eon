<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Account;
use App\Models\Bank;
use App\Models\Company;
use App\Models\ContractFlight;
use App\Models\InvoiceTemplate;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\PassportHolder;
use App\Models\PaymentSchedule;
use App\Models\Portal;
use App\Models\PortalBalance;
use App\Models\Ticket;
use App\Models\TicketLeg;
use App\Models\TicketPurchase;
use App\Models\TicketSaleItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TicketBaggage;
use App\Support\TravelCompany;
use App\Traits\PostsPartyLedger;
use App\Traits\PostsPurchaseJournal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TicketPurchaseController implements HasMiddleware
{
    use PostsPartyLedger, PostsPurchaseJournal;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view ticket purchase|view all ticket purchase|view ticket direct sale|view all ticket direct sale', only: ['index', 'show']),
            new Middleware('permission:create ticket purchase|create ticket direct sale', only: ['create', 'store']),
            new Middleware('permission:edit ticket purchase|edit ticket direct sale', only: ['edit', 'update', 'legs', 'updateLegs']),
            new Middleware('permission:delete ticket purchase|delete ticket direct sale', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $datas = TicketPurchase::query()
                ->where('company_id', TravelCompany::ID)
                ->when($request->passport_holder_id, function ($query, $id) {
                    return $query->where('passport_holder_id', $id);
                })
                ->when($request->vendor_id, function ($query, $id) {
                    return $query->where('vendor_id', $id);
                })
                ->when($request->portal_id, function ($query, $id) {
                    return $query->where('portal_id', $id);
                })
                ->when($request->bank_id, function ($query, $id) {
                    return $query->where('bank_id', $id);
                })
                ->when($request->ticket_id, function ($query, $id) {
                    return $query->where('ticket_id', $id);
                })
                ->when($request->ticket_type, function ($query, $type) {
                    return $query->where('ticket_type', $type);
                })
                ->when($request->trip_type, function ($query, $type) {
                    return $query->where('trip_type', $type);
                })
                ->when($request->ticket_no, function ($query, $no) {
                    return $query->where('ticket_no', 'like', '%'.$no.'%');
                })
                ->when($request->status, function ($query, $status) {
                    return $query->where('status', $status);
                })
                ->when($request->created_by, function ($query, $id) {
                    return $query->where('created_by', $id);
                })
                ->when($request->from_date, function ($query, $date) {
                    return $query->whereDate('purchase_date', '>=', $date);
                })
                ->when($request->to_date, function ($query, $date) {
                    return $query->whereDate('purchase_date', '<=', $date);
                })
                ->latest()
                ->paginate(10)
                ->withQueryString();

            $banks = Bank::where('status', 1)->get();
            $tickets = Ticket::where('status', 1)->get();
            $users = User::orderBy('name')->get();
            $vendors = User::orderBy('name')->role('vendor')->get();
            $portals = Portal::orderBy('name')->where('status', 'active')->get();
            $passport_holders = PassportHolder::where('status', 1)->get();

            // Return the view with ticket purchases data
            return view('dashboard.ticket_purchases.index', compact(
                'datas',
                'passport_holders',
                'portals',
                'banks',
                'tickets',
                'users',
                'vendors'
            ));
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return redirect()->back()->withErrors(['error' => 'Failed to fetch ticket purchases.']);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            $banks = Bank::where('status', 1)->get();
            $tickets = Ticket::where('status', 1)->get();
            $users = User::orderBy('name')->get();
            $vendors = User::orderBy('name')->role('vendor')->get();
            $portals = Portal::orderBy('name')->where('status', 'active')->get();
            $passport_holders = PassportHolder::where('status', 1)->get();

            // Optional: Generate default ticket_no
            $lastTicket = TicketPurchase::latest()->first();
            if ($lastTicket) {
                $lastNumber = (int) substr($lastTicket->ticket_no, 2);
                $ticketNo = 'TP'.str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
            } else {
                $ticketNo = 'TP00001';
            }

            return view('dashboard.ticket_purchases.create', compact(
                'banks',
                'tickets',
                'users',
                'vendors',
                'portals',
                'passport_holders',
                'ticketNo'
            ));
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to load ticket purchase data.']);
        }
    }

    public function manageBankPayment($request, $ticket_purchase_id, $paid_amount)
    {

        $acc = Bank::find($request->bank_id);
        $bank_new_balance = (float) $acc->balance - $paid_amount;

        // `old_balance`/`balance` are the vendor's own running party-ledger
        // balance (what the Party Statement page displays), not the bank's —
        // the bank's balance is tracked separately below.
        $partyId = $request->vendor_id;
        $party_old_balance = $partyId
            ? (float) (Transaction::where('user_id', $partyId)->orderByDesc('id')->value('balance') ?? 0)
            : 0.0;
        $party_new_balance = $party_old_balance - $paid_amount;

        Transaction::create([
            'user_id' => $partyId,
            'user_type' => 'supplier',
            'type' => 'ticket_purchase',
            'account_id' => $request->bank_id,
            'payment_date' => $request->purchase_date ?? now(),
            'reference_no' => $request->reference_no,
            'payment_method' => $request->payment_method,
            'invoice_id' => $ticket_purchase_id,
            'old_balance' => $party_old_balance,
            'debit' => 0,
            'credit' => $paid_amount,
            'balance' => $party_new_balance,
            'remarks' => 'Ticket purchase with bank from purchase.',
        ]);

        if ($acc) {
            $acc->update([
                'balance' => $bank_new_balance,
            ]);
        }
    }

    public function managePortalPayment($request, $ticket_purchase_id, $paid_amount)
    {
        $acc = Portal::find($request->portal_id);
        $opening_balance = (float) $acc->balance;
        $new_balance = $opening_balance - $paid_amount;

        PortalBalance::create([
            'invoice_id' => $ticket_purchase_id,
            'portal_id' => $request->portal_id,
            'type' => 'ticket_purchase',
            'old_balance' => $opening_balance,
            'payment_method' => $request->payment_method,
            'debit' => 0,
            'credit' => $paid_amount,
            'current_balance' => $new_balance,
            'remarks' => 'Ticket purchase with portal from purchase.',
            'created_by' => Auth::id(),
        ]);

        if ($acc) {
            $acc->update([
                'balance' => $new_balance,
            ]);
        }
    }

    // ── JOURNAL HELPERS ───────────────────────────────────────
    // Backed by the shared PostsPurchaseJournal trait (also used by
    // VisaProcessingController) so ticket/visa/future purchase modules post
    // AP entries through one implementation instead of each hand-rolling it.

    /**
     * Resolve the chart-of-accounts account id funding the paid portion of a
     * ticket purchase — bank or portal, whichever the request specifies.
     *
     * On an edit the form does not have to re-pick the account, so when the
     * request carries neither we fall back to what the purchase already
     * records. Without that, re-syncing the journal of a paid purchase
     * dropped its "Paid" line and left the entry unbalanced.
     */
    private function resolveTicketPurchasePaymentAccountId($request, ?TicketPurchase $fallback = null): ?int
    {
        $bankId = $request->bank_id ?: $fallback?->bank_id;
        $portalId = $request->portal_id ?: $fallback?->portal_id;

        if ($bankId) {
            $bank = Bank::find($bankId);
            if (! $bank || ! $bank->account_id) {
                throw new \Exception('Bank is not linked to a chart-of-accounts account.');
            }

            return $bank->account_id;
        }

        if ($portalId) {
            $portal = Portal::find($portalId);
            if (! $portal || ! $portal->account_id) {
                throw new \Exception('Portal is not linked to a chart-of-accounts account.');
            }

            return $portal->account_id;
        }

        return null;
    }

    private function createTicketPurchaseJournal($ticketPurchase, $request)
    {
        $this->createPurchaseJournal(
            'ticket_purchase',
            $ticketPurchase->id,
            $ticketPurchase->company_id,
            $ticketPurchase->purchase_date,
            $ticketPurchase->ticket_no,
            'Ticket purchase — '.$ticketPurchase->ticket_no,
            (float) $ticketPurchase->amount,
            (float) $ticketPurchase->paid_amount,
            (float) $ticketPurchase->due_amount,
            $ticketPurchase->paid_amount > 0 ? $this->resolveTicketPurchasePaymentAccountId($request) : null,
            config('accounts.ticket_purchase_cost'),
            'vendor',
            $ticketPurchase->vendor_id
        );
    }

    /**
     * Re-sync the purchase's OWN journal after an edit. Money settled later
     * through make_payment() lives in its own "Payable cleared" journals, so
     * the purchase journal must carry only the paid portion that is NOT
     * already in one of those — otherwise re-syncing it to the cumulative
     * paid_amount posts the settlement a second time.
     *
     *   purchase journal : Dr Cost = amount, Cr Bank = paid − settled, Cr AP = amount − (paid − settled)
     *   settlement(s)    : Dr AP = settled,  Cr Bank = settled
     *   net AP           : amount − paid = due   ✓
     */
    private function updateTicketPurchaseJournal($ticketPurchase, $request)
    {
        if ($ticketPurchase->status === 'cancelled') {
            // A cancel never touches the purchase's journals (owner,
            // 2026-09-28: nothing is deleted). postCancellationReversal()
            // posts its own reversing entry beside them.
            return;
        }

        $settled = $this->settledThroughPaymentJournals($ticketPurchase->id);
        $paidOnPurchaseJournal = max(0.0, (float) $ticketPurchase->paid_amount - $settled);
        $owedOnPurchaseJournal = max(0.0, (float) $ticketPurchase->amount - $paidOnPurchaseJournal);

        $this->updatePurchaseJournal(
            'ticket_purchase',
            $ticketPurchase->id,
            $ticketPurchase->company_id,
            $ticketPurchase->purchase_date,
            $ticketPurchase->ticket_no,
            'Ticket purchase (edited) — '.$ticketPurchase->ticket_no,
            (float) $ticketPurchase->amount,
            $paidOnPurchaseJournal,
            $owedOnPurchaseJournal,
            $paidOnPurchaseJournal > 0 ? $this->resolveTicketPurchasePaymentAccountId($request, $ticketPurchase) : null,
            config('accounts.ticket_purchase_cost'),
            false,
            'vendor',
            $ticketPurchase->vendor_id
        );
    }

    /**
     * Correct the original cost of an already-cancelled ticket without
     * rewriting its recorded airline refund. The difference remains the
     * vendor penalty, so both the original purchase liability and journal
     * must change while the cancellation/refund posting stays untouched.
     */
    private function updateCancelledTicketPurchaseAmount(TicketPurchase $ticketPurchase, Request $request): void
    {
        $amount = (float) $ticketPurchase->amount;
        $this->reconcilePartyLedgerRow(
            (int) $ticketPurchase->vendor_id,
            'ticket_purchase',
            (int) $ticketPurchase->id,
            true,
            $amount,
            [
                'payment_date' => $ticketPurchase->purchase_date,
                'reference_no' => $ticketPurchase->ticket_no,
                'remarks' => 'Ticket purchase cost — '.$ticketPurchase->ticket_no,
            ]
        );

        $settled = $this->settledThroughPaymentJournals((int) $ticketPurchase->id);
        $paidOnPurchaseJournal = max(0.0, (float) $ticketPurchase->paid_amount - $settled);
        $owedOnPurchaseJournal = max(0.0, $amount - $paidOnPurchaseJournal);

        $this->updatePurchaseJournal(
            'ticket_purchase',
            (int) $ticketPurchase->id,
            $ticketPurchase->company_id,
            $ticketPurchase->purchase_date,
            $ticketPurchase->ticket_no,
            'Ticket purchase (corrected after cancellation) — '.$ticketPurchase->ticket_no,
            $amount,
            $paidOnPurchaseJournal,
            $owedOnPurchaseJournal,
            $paidOnPurchaseJournal > 0 ? $this->resolveTicketPurchasePaymentAccountId($request, $ticketPurchase) : null,
            config('accounts.ticket_purchase_cost'),
            false,
            $ticketPurchase->vendor_id ? 'vendor' : null,
            $ticketPurchase->vendor_id
        );
    }

    /** Sum of AP debits in the settlement journals (every journal for this purchase except the original). */
    private function settledThroughPaymentJournals(int $ticketPurchaseId): float
    {
        $journalIds = JournalEntry::where('source', 'ticket_purchase')
            ->where('source_id', $ticketPurchaseId)
            ->orderBy('id')
            ->pluck('id');

        if ($journalIds->count() < 2) {
            return 0.0;
        }

        $payableAccountId = Account::where('code', config('accounts.accounts_payable'))->value('id');

        return (float) JournalItem::whereIn('journal_entry_id', $journalIds->slice(1)->values())
            ->where('account_id', $payableAccountId)
            ->sum('debit');
    }

    private function deleteTicketPurchaseJournal($ticketPurchaseId)
    {
        $this->deletePurchaseJournal('ticket_purchase', $ticketPurchaseId);
    }

    // ── EDIT PROPAGATION ──────────────────────────────────────
    // A purchase may be edited at any status, so everything that was
    // derived from it when it was first posted has to be re-derived after.

    /**
     * Re-point every ledger and journal row of this purchase from one vendor
     * to another, including cancellation refunds, then re-chain both parties'
     * running balances. This is also used to correct an already-cancelled
     * ticket: its purchase and refund remain immutable business events, but
     * both must belong to the same corrected vendor.
     */
    private function movePurchaseLedgerToVendor(int $ticketPurchaseId, int $fromVendorId, int $toVendorId): void
    {
        $rows = Transaction::where('invoice_id', $ticketPurchaseId)
            ->whereIn('type', ['ticket_purchase', 'ticket_purchase_refund'])
            ->where('user_id', $fromVendorId)
            ->get();

        if ($rows->isNotEmpty()) {
            Transaction::whereIn('id', $rows->pluck('id'))->update([
                'user_id' => $toVendorId,
                'user_type' => 'supplier',
            ]);

            $from = (int) $rows->min('id');
            $this->rebuildPartyRunningBalancesFrom($fromVendorId, $from);
            $this->rebuildPartyRunningBalancesFrom($toVendorId, $from);
        }

        $vendorName = User::whereKey($toVendorId)->value('name');
        $journalIds = JournalEntry::where('source_id', $ticketPurchaseId)
            ->whereIn('source', ['ticket_purchase', 'ticket_purchase_cancel'])
            ->pluck('id');

        JournalEntry::whereIn('id', $journalIds)
            ->where('beneficiary_party_id', $fromVendorId)
            ->update(['beneficiary_party_id' => $toVendorId, 'beneficiary' => $vendorName]);

        JournalItem::whereIn('journal_entry_id', $journalIds)
            ->where('party_type', 'vendor')
            ->where('party_id', $fromVendorId)
            ->update(['party_id' => $toVendorId]);

        PaymentSchedule::where('schedulable_type', TicketPurchase::class)
            ->where('schedulable_id', $ticketPurchaseId)
            ->where('party_type', 'vendor')
            ->where('party_id', $fromVendorId)
            ->update(['party_id' => $toVendorId, 'party_name' => $vendorName]);
    }

    /**
     * Recompute old_balance/balance for one party's ledger rows from a given
     * row id onward, seeded from the last untouched row before it. Rows
     * before $fromId are history and are not rewritten.
     */
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

    /**
     * Keep the vendor payable on the payment schedule equal to what this
     * purchase still owes. Mirrors the rule store() creates it under: a
     * vendor (not a portal) and a due balance. Anything else — paid off,
     * cancelled, moved to a portal — closes the open schedule rather than
     * leaving a phantom payable on the Pay list.
     */
    private function syncPayableSchedule(TicketPurchase $ticketPurchase, $costDueDate = null): void
    {
        $open = $ticketPurchase->schedules()
            ->where('type', 'pay')
            ->whereIn('status', PaymentSchedule::OPEN_STATUSES)
            ->orderByDesc('id')
            ->first();

        $due = (float) $ticketPurchase->due_amount;
        $wants = $ticketPurchase->status !== 'cancelled'
            && $ticketPurchase->vendor_id
            && ! $ticketPurchase->portal_id
            && $due > 0;

        if (! $wants) {
            if ($open) {
                $settled = $ticketPurchase->status !== 'cancelled' && $due <= 0;
                $open->update($settled
                    ? ['status' => 'paid', 'paid_date' => now()->toDateString()]
                    : ['status' => 'cancelled']);
            }

            return;
        }

        $attrs = [
            'party_id' => $ticketPurchase->vendor_id,
            'party_name' => $ticketPurchase->vendor?->name ?? 'Vendor (unassigned)',
            'source_label' => $ticketPurchase->ticket_no,
            'amount' => $due,
            'scheduled_date' => $costDueDate ?: ($open->scheduled_date ?? $ticketPurchase->purchase_date),
        ];

        if ($open) {
            $open->update($attrs);

            return;
        }

        PaymentSchedule::create($attrs + [
            'company_id' => $ticketPurchase->company_id,
            'schedulable_type' => TicketPurchase::class,
            'schedulable_id' => $ticketPurchase->id,
            'type' => 'pay',
            'party_type' => 'vendor',
            'paid_amount' => 0,
            'status' => 'pending',
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * A contract flight's stored cost_price/revenue are a sum over its
     * passengers, and a passenger's cost is read off the PNRs linked to it —
     * so a purchase edited here changes flights that were never saved.
     */
    private function resyncLinkedContractFlights(int $ticketPurchaseId): void
    {
        $flightIds = DB::table('contract_flight_passenger_tickets as t')
            ->join('contract_flight_passengers as p', 'p.id', '=', 't.contract_flight_passenger_id')
            ->where('t.ticket_purchase_id', $ticketPurchaseId)
            ->distinct()
            ->pluck('p.contract_flight_id');

        foreach ($flightIds as $flightId) {
            ContractFlight::find($flightId)?->syncMoneyFromPassengers();
        }
    }

    // ── Ticket Trail hooks (2026-10-26) ───────────────────────────────
    // The Trail rewrites a ticket's postings through this controller's own
    // code; these expose the re-derivation steps it needs, nothing more.

    /** Everything downstream of a purchase re-derived: the payable schedule and any contract flight's totals. */
    public function trailResync(TicketPurchase $ticketPurchase): void
    {
        $ticketPurchase->refresh();
        $this->syncPayableSchedule($ticketPurchase);
        $this->resyncLinkedContractFlights($ticketPurchase->id);
    }

    /** The purchase's own journal re-synced to its current amount / paid (same as an edit does). */
    public function trailSyncPurchaseJournal(TicketPurchase $ticketPurchase, ?int $bankId = null): void
    {
        $this->updateTicketPurchaseJournal($ticketPurchase->refresh(), new Request(array_filter(['bank_id' => $bankId])));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $role)
    {
        $validator = Validator::make($request->all(), [
            'passport_holder_id' => 'required|exists:passport_holders,id',
            'ticket_id' => 'required|exists:tickets,id',
            'vendor_id' => 'required_without:portal_id|prohibits:portal_id|nullable|exists:users,id',
            'portal_id' => 'required_without:vendor_id|prohibits:vendor_id|nullable|exists:portals,id',
            'bank_id' => 'nullable|exists:banks,id',
            'ticket_type' => 'required|in:air,bus,train,other',
            'trip_type' => 'required|in:one-way,two-way,multi-city',
            'ticket_class' => 'nullable|in:economy,business,first',
            'purchase_date' => 'required|date',
            // Shared by everyone on the booking — see the PNR notes on
            // TicketPurchase. No uniqueness guard; the suffix is allocated below.
            'ticket_no' => 'required|string|max:200',
            'name_ticket_no' => 'nullable|string|max:100',
            'status' => 'required|in:pending,confirm,cancelled,draft',
            'price' => 'nullable|numeric',
            'amount' => 'nullable|numeric',
            'currency' => 'required|string|max:5',
            'cost_due_date' => 'nullable|date',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png',
            'legs' => 'nullable|array',
            'legs.*.from_location' => 'nullable|string|max:255',
            'legs.*.to_location' => 'nullable|string|max:255',
            'legs.*.travel_date' => 'nullable|date',
            'legs.*.seat_number' => 'nullable|string|max:50',
            'legs.*.cost' => 'nullable|numeric|min:0',
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        DB::beginTransaction();
        try {
            // Prepare main ticket purchase data
            $ticketPurchaseData = $request->only([
                'passport_holder_id',
                'ticket_id',
                'vendor_id',
                'portal_id',
                'bank_id',
                'ticket_type',
                'trip_type',
                'ticket_class',
                'purchase_date',
                'ticket_no',
                'status',
                'amount',
                'currency',
                'paid_amount',
                'due_amount',
                'payment_status',
            ]);

            $ticketPurchaseData['company_id'] = TravelCompany::ID;
            $ticketPurchaseData['created_by'] = Auth::user()->id;

            // The PNR as it will actually be stored — bare if this is the
            // first person on the booking, " (a)", " (b)" … after that. Used
            // for the ledger and schedule references below too, so the
            // vendor's statement names a PNR that exists on a ticket row.
            $pnr = TicketPurchase::allocateTicketNo($request->ticket_no, $request->passport_holder_id);
            $ticketPurchaseData['ticket_no'] = $pnr;
            $ticketPurchaseData += TicketPurchase::nameTicketNoAttributes(
                'ticket_purchases', $request->name_ticket_no, $request->has('name_ticket_no')
            );

            // Handle main attachment if provided
            if ($request->hasFile('attachment')) {
                $ticketPurchaseData['attachment'] = $request->file('attachment')->store('ticket_attachments', 'public');
            }

            // Create TicketPurchase
            $ticketPurchase = TicketPurchase::create($ticketPurchaseData);

            $this->syncLegs($ticketPurchase, $request);

            // Liability recognition on the vendor's party statement — the
            // full cost owed, separate from the paid-portion credit row
            // below, so the statement shows what's owed even before
            // anything is paid.
            if ($request->vendor_id && (float) $request->amount > 0) {
                $this->reconcilePartyLedgerRow(
                    $request->vendor_id, 'ticket_purchase', $ticketPurchase->id, true, (float) $request->amount,
                    [
                        'account_id' => null,
                        'payment_date' => $request->purchase_date,
                        'reference_no' => $pnr,
                        'remarks' => 'Ticket purchase cost — '.$pnr,
                    ]
                );
            }

            if ($request->bank_id && $request->paid_amount > 0) {
                $this->manageBankPayment($request, $ticketPurchase->id, (float) $request->paid_amount);
            } elseif ($request->portal_id && $request->paid_amount > 0) {
                $this->managePortalPayment($request, $ticketPurchase->id, (float) $request->paid_amount);
            }

            // Cost payable schedule — same rule as the Ticketing (Direct
            // Sale) form: only when the liability sits with a vendor (not a
            // portal, which is paid at time of purchase) and something is
            // still owed after the payment above.
            $dueAmount = (float) $request->due_amount;
            if ($dueAmount > 0 && $request->vendor_id && ! $request->portal_id) {
                PaymentSchedule::create([
                    'company_id' => $ticketPurchase->company_id,
                    'schedulable_type' => TicketPurchase::class,
                    'schedulable_id' => $ticketPurchase->id,
                    'type' => 'pay',
                    'party_type' => 'vendor',
                    'party_id' => $request->vendor_id,
                    'party_name' => $ticketPurchase->vendor?->name ?? 'Vendor (unassigned)',
                    'source_label' => $pnr,
                    'amount' => $dueAmount,
                    'paid_amount' => 0,
                    'scheduled_date' => $request->cost_due_date ?: $request->purchase_date,
                    'status' => 'pending',
                    'created_by' => Auth::id(),
                ]);
            }

            // JOURNAL start
            $this->createTicketPurchaseJournal($ticketPurchase, $request);

            if ($request->status === 'confirm') {
                if ($request->ticket_id) {
                    $ticketAcc = Ticket::find($request->ticket_id);
                    if ($ticketAcc) {
                        $ticketAcc->update([
                            'qty' => $ticketAcc->qty + 1,
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket purchase created successfully!',
                // The Add/Edit Flight modal creates a PNR through this same
                // endpoint (so it books identically to one typed on the
                // Ticketing screen) and needs the id back to link it to the
                // passenger. Purely additive — the Ticketing screen's own
                // handler reads success/message and ignores this.
                'ticket_purchase_id' => $ticketPurchase->id,
                'ticket_no' => $ticketPurchase->ticket_no,
                'amount' => (float) $ticketPurchase->amount,
                'status' => $ticketPurchase->status,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket purchase: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Replace this purchase's route legs with whatever the repeater
     * submitted — simple delete-and-recreate is safe here since a leg is
     * just a value (from/to/date/seat/cost), not a record with its own
     * independent state to preserve across edits (unlike a contract-flight
     * passenger, which carries pipeline stage/agent/ticket-links).
     */
    /**
     * The money half of cancelling a purchase (owner, 2026-09-28: "no data
     * should be deleted at all, upon cancellation, will be reversed").
     *
     * Every row the purchase posted stays exactly as it is — the vendor's
     * liability, each bank or portal payment, the purchase journal and any
     * settlement journals. Beside them one reversal is ADDED for what the
     * airline gives back ($refund); the rest (amount − refund) is the
     * airline's penalty and simply stays in the ticket's cost:
     *
     *   vendor purchase  vendor ledger  Cr refund  ("Cancelled — refund due")
     *                    journal        Dr AP (vendor) refund / Cr 5110 refund
     *   portal purchase  portal wallet  Dr refund  (credited back to the wallet)
     *                    journal        Dr portal account refund / Cr 5110 refund
     *
     * No bank moves: the airline refunds to the vendor, so the vendor's
     * balance carries it (as their Advance on Party Statement) until it is
     * set against the next purchase. Who finally bears the penalty — company
     * or agent — is the contract flight's call, not Ticketing's.
     *
     * Shared by update()'s cancel and cancelFromContractFlight(), so there is
     * one cancel, not two. Idempotent: a purchase already reversed
     * (cancelled_at set) posts nothing.
     */
    private function postCancellationReversal(TicketPurchase $ticketPurchase, float $refund, ?string $note = null, $date = null): void
    {
        $hasCols = Schema::hasColumn('ticket_purchases', 'cancelled_at');
        if ($hasCols && $ticketPurchase->cancelled_at) {
            return;
        }
        // The refund is usually PROCESSED days after the cancellation itself
        // (owner, 2026-09-29 — same "Refund Date" field the standalone
        // Ticketing Refund tab already asks for). $date is when it landed on
        // the ledger; entry_time still records when this save actually ran.
        $date = $date ? \Illuminate\Support\Carbon::parse($date) : now();
        $amount = (float) $ticketPurchase->amount;
        $refund = round(max(0.0, min($refund, $amount)), 2);
        $penalty = round($amount - $refund, 2);
        $pnr = (string) $ticketPurchase->ticket_no;
        $why = 'Cancelled — '.$pnr.' · airline refund ৳'.number_format($refund, 2)
            .($penalty > 0 ? ' · penalty ৳'.number_format($penalty, 2) : '')
            .($note ? ' · '.$note : '');

        $refund = $this->postAirlineRefund($ticketPurchase, $refund, $why, $date);

        if ($hasCols) {
            $ticketPurchase->update(['refund_amount' => $refund, 'cancelled_at' => $date]);
        }
    }

    /**
     * The money half of an airline refund on a purchase — moved verbatim out of
     * postCancellationReversal() (2026-10-14) so a cancelled RETURN on a round
     * trip (refundReturnPart) posts exactly what a cancel does:
     *   vendor purchase  vendor row Cr refund · Dr AP / Cr 5110
     *   portal purchase  wallet Dr refund · Dr portal account / Cr 5110
     * Returns the refund posted — 0 when the purchase never posted a payable
     * (data older than the ledger), so no advance is invented.
     */
    private function postAirlineRefund(TicketPurchase $ticketPurchase, float $refund, string $why, \Illuminate\Support\Carbon $date): float
    {
        $pnr = (string) $ticketPurchase->ticket_no;
        // Reverse only what was actually posted. A purchase whose payable was
        // never recorded (data older than the ledger) gets no refund row —
        // posting one would invent an advance the vendor never had.
        $original = JournalEntry::where('source', 'ticket_purchase')
            ->where('source_id', $ticketPurchase->id)->orderBy('id')->first();
        $vendorPosted = $ticketPurchase->vendor_id && Transaction::where('invoice_id', $ticketPurchase->id)
            ->where('type', 'ticket_purchase')->where('user_id', $ticketPurchase->vendor_id)->where('debit', '>', 0)->exists();
        $portalPaid = ! $ticketPurchase->vendor_id && $ticketPurchase->portal_id
            ? (float) PortalBalance::where('invoice_id', $ticketPurchase->id)->where('type', 'ticket_purchase')->sum('credit')
            : 0.0;
        if (! $vendorPosted && ! $original && $portalPaid <= 0) {
            $refund = 0.0;
        }

        if ($refund > 0) {
            if ($ticketPurchase->vendor_id && $vendorPosted) {
                $this->postPartyLedgerRow((int) $ticketPurchase->vendor_id, [
                    'type' => 'ticket_purchase_refund',
                    'invoice_id' => $ticketPurchase->id,
                    'account_id' => null,
                    'payment_date' => $date,
                    'reference_no' => $pnr,
                    'debit' => 0,
                    'credit' => $refund,
                    'remarks' => mb_substr($why, 0, 250),
                ]);
                $lines = [[Account::where('code', config('accounts.accounts_payable'))->value('id'), $refund]];
                $partyType = 'vendor';
                $partyId = (int) $ticketPurchase->vendor_id;
            } else {
                // Portal: the wallet gets back at most what it paid; any rest
                // of the refund reduces what is still owed on the purchase.
                $portal = $ticketPurchase->portal_id ? Portal::find($ticketPurchase->portal_id) : null;
                $toWallet = $portal ? min($refund, $portalPaid) : 0.0;
                if ($portal && $toWallet > 0) {
                    $opening = (float) $portal->balance;
                    PortalBalance::create([
                        'invoice_id' => $ticketPurchase->id,
                        'portal_id' => $portal->id,
                        'type' => 'ticket_purchase_refund',
                        'old_balance' => $opening,
                        'debit' => $toWallet,
                        'credit' => 0,
                        'current_balance' => $opening + $toWallet,
                        'remarks' => mb_substr($why, 0, 250),
                        'created_by' => Auth::id(),
                    ]);
                    $portal->update(['balance' => $opening + $toWallet]);
                }
                $lines = array_values(array_filter([
                    $toWallet > 0 ? [$portal?->account_id, $toWallet] : null,
                    $refund - $toWallet > 0.004 ? [Account::where('code', config('accounts.accounts_payable'))->value('id'), round($refund - $toWallet, 2)] : null,
                ]));
                $partyType = null;
                $partyId = null;
            }

            $costAccountId = Account::where('code', config('accounts.ticket_purchase_cost'))->value('id');
            $lines = array_values(array_filter($lines, fn ($l) => $l[0]));
            if ($lines && $costAccountId && $original) {
                $entry = JournalEntry::create([
                    'company_id' => $ticketPurchase->company_id ?? $original?->company_id,
                    'created_by' => Auth::id(),
                    'date' => $date->toDateString(),
                    'entry_time' => now()->format('H:i:s'),
                    'reference' => $pnr,
                    'source' => 'ticket_purchase_cancel',
                    'source_id' => $ticketPurchase->id,
                    'description' => mb_substr($why, 0, 250),
                    'reversed_journal_entry_id' => $original?->id,
                    'beneficiary' => $partyId ? User::whereKey($partyId)->value('name') : null,
                    'beneficiary_party_id' => $partyId,
                ]);
                $posted = 0.0;
                foreach ($lines as [$accountId, $amt]) {
                    JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $accountId, 'debit' => $amt, 'credit' => 0,
                        'note' => 'Airline refund due — '.$pnr, 'party_type' => $partyType, 'party_id' => $partyId]);
                    $posted += $amt;
                }
                JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $costAccountId, 'debit' => 0, 'credit' => round($posted, 2),
                    'note' => 'Ticket cost reversed — '.$pnr]);
            }
        }

        return $refund;
    }

    /**
     * Cancel return on a ROUND-TRIP ticket (owner, 2026-10-14): the outbound
     * was flown, the return half is not used. The ticket stays a live purchase;
     * the airline's refund for the return is posted beside it exactly like a
     * cancel's (postAirlineRefund) and recorded as return_refund_amount. What
     * the airline kept for that half is the caller's to place (penalty).
     * Once only: a return already cancelled posts nothing. Returns the refund posted.
     */
    public function refundReturnPart(TicketPurchase $ticketPurchase, float $refund, string $note, $date = null, float $penalty = 0.0): float
    {
        if ($ticketPurchase->return_cancelled_at) {
            return 0.0;
        }
        $date = $date ? \Illuminate\Support\Carbon::parse($date) : now();
        $refund = round(max(0.0, min($refund, (float) $ticketPurchase->amount)), 2);
        $why = 'Return cancelled — '.$ticketPurchase->ticket_no.' · airline refund ৳'.number_format($refund, 2).($note ? ' · '.$note : '');
        $posted = $refund > 0 ? $this->postAirlineRefund($ticketPurchase, $refund, $why, $date) : 0.0;
        $ticketPurchase->update(['return_refund_amount' => $posted, 'return_penalty' => round(max(0.0, $penalty), 2), 'return_cancelled_at' => $date]);
        $ticketPurchase->refresh();
        $this->syncPayableSchedule($ticketPurchase);

        return $posted;
    }

    /**
     * The airline's refund turned out different from what was entered (owner,
     * 2026-10-10: "post immediately, editable later"). Never edits the refund
     * already posted: ADDS one vendor row for the difference (Cr when the refund
     * went up, Dr when it came down) and its journal (Dr AP / Cr 5110, or the
     * reverse), same source as the cancel so every reader sums it with it.
     * Vendor-paid tickets only — a portal refund went into the wallet, and
     * correcting that is not built yet. Returns the difference, or null when
     * the ticket cannot be corrected this way. $date: the day the difference posts on
     * (a contract-flight correction lets staff choose it — 2026-10-19); today when not given.
     */
    public function correctRefundAmount(TicketPurchase $ticketPurchase, float $newRefund, string $why, ?string $date = null): ?float
    {
        if ($ticketPurchase->status !== 'cancelled' || ! Schema::hasColumn('ticket_purchases', 'refund_amount')) {
            return null;
        }
        $on = $date ?: now()->toDateString();
        $amount = (float) $ticketPurchase->amount;
        $newRefund = round(max(0.0, min($newRefund, $amount)), 2);
        $delta = round($newRefund - (float) $ticketPurchase->refund_amount, 2);
        if (abs($delta) < 0.005) {
            return 0.0;
        }
        $vendorPosted = $ticketPurchase->vendor_id && Transaction::where('invoice_id', $ticketPurchase->id)
            ->where('type', 'ticket_purchase')->where('user_id', $ticketPurchase->vendor_id)->where('debit', '>', 0)->exists();
        $original = JournalEntry::where('source', 'ticket_purchase')->where('source_id', $ticketPurchase->id)->orderBy('id')->first();
        $pnr = (string) $ticketPurchase->ticket_no;
        $text = 'Refund corrected — '.$pnr.' · ৳'.number_format((float) $ticketPurchase->refund_amount, 2)
            .' → ৳'.number_format($newRefund, 2).($why ? ' · '.$why : '');
        // Bought through a portal: the refund went back into the wallet, so the difference
        // does too — a wallet row + journal Dr portal account / Cr 5110 (reversed when it
        // came down), never more out of the wallet than it got back.
        $portal = ! $ticketPurchase->vendor_id && $ticketPurchase->portal_id ? Portal::find($ticketPurchase->portal_id) : null;
        if ($portal && $portal->account_id && $original) {
            if ($delta < 0) {
                $back = (float) PortalBalance::where('invoice_id', $ticketPurchase->id)->where('type', 'ticket_purchase_refund')->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) n')->value('n');
                if ($back + $delta < -0.005) {
                    return null;
                }
            }
            $opening = (float) $portal->balance;
            PortalBalance::create(['invoice_id' => $ticketPurchase->id, 'portal_id' => $portal->id, 'type' => 'ticket_purchase_refund',
                'old_balance' => $opening, 'debit' => $delta > 0 ? $delta : 0, 'credit' => $delta < 0 ? -$delta : 0,
                'current_balance' => $opening + $delta, 'remarks' => mb_substr($text, 0, 250), 'created_by' => Auth::id()]);
            $portal->update(['balance' => $opening + $delta]);
            $cost = Account::where('code', config('accounts.ticket_purchase_cost'))->value('id');
            if ($cost) {
                $entry = JournalEntry::create(['company_id' => $ticketPurchase->company_id ?? $original->company_id, 'created_by' => Auth::id(),
                    'date' => $on, 'entry_time' => now()->format('H:i:s'), 'reference' => $pnr,
                    'source' => 'ticket_purchase_cancel', 'source_id' => $ticketPurchase->id, 'description' => mb_substr($text, 0, 250)]);
                $abs = abs($delta);
                JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $delta > 0 ? $portal->account_id : $cost, 'debit' => $abs, 'credit' => 0, 'note' => $text]);
                JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $delta > 0 ? $cost : $portal->account_id, 'debit' => 0, 'credit' => $abs, 'note' => $text]);
            }
            $ticketPurchase->update(['refund_amount' => $newRefund]);

            return $delta;
        }
        if (! $vendorPosted || ! $original) {
            return null;
        }
        $this->postPartyLedgerRow((int) $ticketPurchase->vendor_id, [
            'type' => 'ticket_purchase_refund',
            'invoice_id' => $ticketPurchase->id,
            'account_id' => null,
            'payment_date' => $on,
            'reference_no' => $pnr,
            'debit' => $delta < 0 ? -$delta : 0,
            'credit' => $delta > 0 ? $delta : 0,
            'remarks' => mb_substr($text, 0, 250),
        ]);
        $ap = Account::where('code', config('accounts.accounts_payable'))->value('id');
        $cost = Account::where('code', config('accounts.ticket_purchase_cost'))->value('id');
        if ($ap && $cost) {
            $vendorId = (int) $ticketPurchase->vendor_id;
            $entry = JournalEntry::create([
                'company_id' => $ticketPurchase->company_id ?? $original->company_id,
                'created_by' => Auth::id(),
                'date' => $on,
                'entry_time' => now()->format('H:i:s'),
                'reference' => $pnr,
                'source' => 'ticket_purchase_cancel',
                'source_id' => $ticketPurchase->id,
                'description' => mb_substr($text, 0, 250),
                'beneficiary' => User::whereKey($vendorId)->value('name'),
                'beneficiary_party_id' => $vendorId,
            ]);
            $abs = abs($delta);
            JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $delta > 0 ? $ap : $cost, 'debit' => $abs, 'credit' => 0,
                'note' => $text, 'party_type' => $delta > 0 ? 'vendor' : null, 'party_id' => $delta > 0 ? $vendorId : null]);
            JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $delta > 0 ? $cost : $ap, 'debit' => 0, 'credit' => $abs,
                'note' => $text, 'party_type' => $delta > 0 ? null : 'vendor', 'party_id' => $delta > 0 ? null : $vendorId]);
        }
        $ticketPurchase->update(['refund_amount' => $newRefund]);
        $this->syncPayableSchedule($ticketPurchase);

        return $delta;
    }

    /**
     * Cancel a passenger's ticket because he was taken off (or re-booked
     * from) a contract flight. The row, its PNR, its legs and its amount
     * stay; the reversal above is added beside them; the note records why.
     * $refund defaults to the whole amount (the airline gave everything
     * back) — the contract flight passes a smaller one when it kept a penalty.
     * Not a route: called by the contract flight rebook code inside its own
     * transaction. Already cancelled → no-op.
     */
    public function cancelFromContractFlight(TicketPurchase $ticketPurchase, string $note, ?float $refund = null, $date = null): void
    {
        if ($ticketPurchase->status === 'cancelled') {
            return;
        }

        // A confirmed purchase counted one seat into the route's stock.
        if ($ticketPurchase->status === 'confirm') {
            $ticket = Ticket::find($ticketPurchase->ticket_id);
            if ($ticket && $ticket->qty > 0) {
                $ticket->decrement('qty');
            }
        }

        $ticketPurchase->update([
            'status' => 'cancelled',
            'cancel_note' => mb_substr($note, 0, 255),
            'updated_by' => Auth::id(),
        ]);
        $this->postCancellationReversal($ticketPurchase, $refund ?? (float) $ticketPurchase->amount, $note, $date);

        $ticketPurchase->refresh();
        $this->syncPayableSchedule($ticketPurchase);
        $this->resyncLinkedContractFlights($ticketPurchase->id);
    }

    /**
     * The exact mirror of cancelFromContractFlight(), for a cancellation that was
     * a mistake (owner, 2026-10-26: "undo ... remove if any ledger entry done").
     * Removes the refund it posted on or after $since — seller row, wallet row,
     * its journal — puts the ticket back live and the seat back in stock.
     * Returns what was removed (for the undo log) and where balances must be
     * re-walked. Throws when what it finds is not what the cancel posted.
     */
    public function undoCancelFromContractFlight(TicketPurchase $ticketPurchase, \Illuminate\Support\Carbon $since): array
    {
        if ($ticketPurchase->status !== 'cancelled') {
            throw new \RuntimeException('Ticket '.$ticketPurchase->ticket_no.' is not cancelled.');
        }
        $tx = Transaction::where('invoice_id', $ticketPurchase->id)->where('type', 'ticket_purchase_refund')->where('created_at', '>=', $since)->get();
        $pb = Schema::hasTable('portal_balances')
            ? PortalBalance::where('invoice_id', $ticketPurchase->id)->where('type', 'ticket_purchase_refund')->where('created_at', '>=', $since)->get() : collect();
        $je = JournalEntry::where('source', 'ticket_purchase_cancel')->where('source_id', $ticketPurchase->id)->where('created_at', '>=', $since)->get();
        $refund = round((float) ($ticketPurchase->refund_amount ?? 0), 2);
        $journalRefund = round((float) JournalItem::whereIn('journal_entry_id', $je->pluck('id'))->sum('debit'), 2);
        if ($refund > 0 && abs($journalRefund - $refund) > 0.01) {
            throw new \RuntimeException('The refund on '.$ticketPurchase->ticket_no.' was changed after the cancel (৳'.number_format($journalRefund, 2).' posted, ৳'.number_format($refund, 2).' recorded).');
        }
        $removed = [
            'ticket' => $ticketPurchase->getAttributes(),
            'transactions' => $tx->map(fn ($r) => $r->getAttributes())->all(),
            'portal_balances' => $pb->map(fn ($r) => $r->getAttributes())->all(),
            'journals' => $je->map(fn ($e) => ['entry' => $e->getAttributes(), 'items' => JournalItem::where('journal_entry_id', $e->id)->get()->map(fn ($i) => $i->getAttributes())->all()])->all(),
        ];
        $parties = [];
        foreach ($tx as $r) {
            $parties[(int) $r->user_id] = min($parties[(int) $r->user_id] ?? PHP_INT_MAX, (int) $r->id);
        }
        $portals = [];
        foreach ($pb as $r) {
            $portals[(int) $r->portal_id] = min($portals[(int) $r->portal_id] ?? PHP_INT_MAX, (int) $r->id);
            Portal::whereKey($r->portal_id)->decrement('balance', (float) $r->debit - (float) $r->credit);
        }
        Transaction::whereIn('id', $tx->pluck('id'))->delete();
        PortalBalance::whereIn('id', $pb->pluck('id'))->delete();
        JournalItem::whereIn('journal_entry_id', $je->pluck('id'))->forceDelete();
        JournalEntry::whereIn('id', $je->pluck('id'))->forceDelete();

        $back = ['status' => 'confirm', 'cancel_note' => null, 'updated_by' => Auth::id()];
        if (Schema::hasColumn('ticket_purchases', 'cancelled_at')) {
            $back += ['refund_amount' => null, 'cancelled_at' => null];
        }
        $ticketPurchase->update($back);
        Ticket::whereKey($ticketPurchase->ticket_id)->increment('qty');
        $ticketPurchase->refresh();
        $this->syncPayableSchedule($ticketPurchase);
        $this->resyncLinkedContractFlights($ticketPurchase->id);

        return ['removed' => $removed, 'parties' => $parties, 'portals' => $portals];
    }

    private function syncLegs(TicketPurchase $ticketPurchase, Request $request): void
    {
        // a sector's airline survives editors that do not show it (see TicketLeg::syncFor)
        TicketLeg::syncFor($ticketPurchase, (array) $request->input('legs', []), true);
    }

    /**
     * Route legs (from/to/date/seat/cost) carry no GL/ledger impact — see
     * syncLegs() above, they're a pure informational cost breakdown used only
     * by the Leg-wise Cost report, so it never touches amount/vendor/journal/
     * payment fields and needs none of update()'s re-posting.
     */
    public function legs($role, $id)
    {
        $ticketPurchase = TicketPurchase::with('legs')->findOrFail($id);

        return response()->json([
            'success' => true,
            'ticket_no' => $ticketPurchase->ticket_no,
            'legs' => $ticketPurchase->legs->map(fn ($leg) => [
                // travel_date has no Carbon cast on TicketLeg — it's a plain
                // string, so optional(...)->format() would silently null it.
                'from_location' => $leg->from_location,
                'to_location' => $leg->to_location,
                'travel_date' => $leg->travel_date,
                'seat_number' => $leg->seat_number,
                'cost' => $leg->cost,
            ]),
        ]);
    }

    public function updateLegs(Request $request, $role, $id)
    {
        $ticketPurchase = TicketPurchase::findOrFail($id);

        $request->validate([
            'legs' => 'nullable|array',
            'legs.*.from_location' => 'nullable|string|max:255',
            'legs.*.to_location' => 'nullable|string|max:255',
            'legs.*.travel_date' => 'nullable|date',
            'legs.*.seat_number' => 'nullable|string|max:50',
            'legs.*.cost' => 'nullable|numeric|min:0',
        ]);

        $this->syncLegs($ticketPurchase, $request);

        return response()->json(['success' => true, 'message' => 'Route legs updated.']);
    }

    /**
     * Display the specified resource.
     */
    public function show($role, $id)
    {
        try {
            $data = TicketPurchase::with('ticket', 'transactions.account', 'portalBalances.portal')->findOrFail($id);
            $company = Company::findOrFail($data->company_id);
            $invoiceTemplate = InvoiceTemplate::with('fields', 'style')->where('type', 'purchase')->where('is_default', 1)->first();

            return view('dashboard.ticket_purchases.show-v2', compact('data', 'company', 'invoiceTemplate'));
        } catch (\Exception $e) {
            // throw $e;
            return redirect()->back()->withErrors(['error' => 'Failed to load ticket purchase data.']);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($role, $id)
    {
        try {
            // Every status is editable — owner's decision, 2026-09-14. A
            // confirmed or cancelled purchase is not frozen; update() re-derives
            // and re-posts everything that depends on it.
            $data = TicketPurchase::with('legs')->findOrFail($id);
            $banks = Bank::where('status', 1)->get();
            $tickets = Ticket::where('status', 1)->get();
            $users = User::orderBy('name')->get();
            $vendors = User::orderBy('name')->role('vendor')->get();
            $portals = Portal::orderBy('name')->where('status', 'active')->get();
            $passport_holders = PassportHolder::where('status', 1)->get();

            return view('dashboard.ticket_purchases.edit', compact(
                'data',
                'banks',
                'tickets',
                'users',
                'vendors',
                'portals',
                'passport_holders'
            ));
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to load ticket purchase data.']);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $role, $id)
    {
        $validator = Validator::make($request->all(), [
            'passport_holder_id' => 'required|exists:passport_holders,id',
            'ticket_id' => 'required|exists:tickets,id',
            'vendor_id' => 'required_without:portal_id|prohibits:portal_id|nullable|exists:users,id',
            'portal_id' => 'required_without:vendor_id|prohibits:vendor_id|nullable|exists:portals,id',
            'bank_id' => 'nullable|exists:banks,id',
            'ticket_type' => 'required|in:air,bus,train,other',
            'trip_type' => 'required|in:one-way,two-way,multi-city',
            'ticket_class' => 'nullable|in:economy,business,first',
            'purchase_date' => 'required|date',
            // Shared by everyone on the booking — see the PNR notes on
            // TicketPurchase. No uniqueness guard; the suffix is allocated below.
            'ticket_no' => 'required|string|max:200',
            'name_ticket_no' => 'nullable|string|max:100',
            'status' => 'required|in:pending,confirm,cancelled,draft',
            'price' => 'nullable|numeric',
            'amount' => 'nullable|numeric',
            'currency' => 'required|string|max:5',
            'cost_due_date' => 'nullable|date',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png',
            'legs' => 'nullable|array',
            'legs.*.from_location' => 'nullable|string|max:255',
            'legs.*.to_location' => 'nullable|string|max:255',
            'legs.*.travel_date' => 'nullable|date',
            'legs.*.seat_number' => 'nullable|string|max:50',
            'legs.*.cost' => 'nullable|numeric|min:0',
            // What the airline gives back when this save cancels the ticket.
            // Blank = the whole amount. amount − refund = the penalty.
            'refund_amount' => 'nullable|numeric|min:0',
            // The Edit page now carries everything Ticket Entry does (2026-10-07).
            // All optional: other callers of update() that never send them are unchanged.
            'base_fare' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'tax_breakdown' => 'nullable|string|max:255',
            'commission_pct' => 'nullable|numeric|min:0|max:100',
            'commission_amount' => 'nullable|numeric|min:0',
            'validating_carrier_id' => 'nullable|exists:airlines,id',
            'flight_no' => 'nullable|string|max:20',
            'departure_at' => 'nullable|date',
            'arrival_at' => 'nullable|date',
            'rbd' => 'nullable|string|max:4',
            'baggage' => 'nullable|string|max:50',
            ...TicketBaggage::rules(),
            'sale_client_id' => 'required_with:sale_price|nullable|exists:users,id',
            'sale_price' => 'required_with:sale_client_id|nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        // A cancel's postings are final (owner, 2026-09-28): the reversal sits
        // beside the original rows, so reopening would need a reversal of the
        // reversal. A reopened ticket is entered again as a new one instead.
        $existing = TicketPurchase::find($id);
        if ($existing && $existing->status === 'cancelled' && $request->status !== 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'This ticket is cancelled and its refund is posted. Enter the ticket again as a new purchase instead of reopening it.',
            ]);
        }
        // A PNR linked to a contract flight passenger is cancelled from his
        // card (Re-assign / Refund / money flag): only there is whose side
        // chosen, the penalty placed on the Company Ledger or billed to the
        // agent, and the move recorded. Cancelling it here would skip all of it.
        if ($existing && $existing->status !== 'cancelled' && $request->status === 'cancelled'
            && DB::table('contract_flight_passenger_tickets')->where('ticket_purchase_id', $existing->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket belongs to a contract flight passenger. Cancel or refund it from his card on Flights & Passengers (Re-assign or the ticket\'s Refund button), so the penalty and the agent\'s charge are posted correctly.',
            ]);
        }

        // Cancelling freezes the money fields at what was posted: the
        // reversal is computed against them, never against a new figure
        // typed in the same save.
        if ($existing && $request->status === 'cancelled') {
            $frozen = [
                'portal_id' => $existing->portal_id,
                'paid_amount' => 0,
            ];
            // The act of cancelling uses the vendor that owned the original
            // liability. A later edit may correct that vendor identity, while
            // the already-posted amount/refund remains frozen.
            if ($existing->status !== 'cancelled') {
                $frozen['amount'] = $existing->amount;
                $frozen['vendor_id'] = $existing->vendor_id;
            }
            $request->merge($frozen);
        }

        DB::beginTransaction();
        try {
            $ticketPurchase = TicketPurchase::findOrFail($id);

            $oldStatus = $ticketPurchase->status;
            $newStatus = $request->status;
            $oldAmount = (float) $ticketPurchase->amount;
            $oldVendorId = $ticketPurchase->vendor_id ? (int) $ticketPurchase->vendor_id : null;
            $newVendorId = $request->vendor_id ? (int) $request->vendor_id : null;
            $cancelNow = $newStatus === 'cancelled' && $oldStatus !== 'cancelled';

            // Reverse old ticket qty
            if ($oldStatus === 'confirm') {
                $oldTicket = Ticket::find($ticketPurchase->ticket_id);
                if ($oldTicket && $oldTicket->qty > 0) {
                    $oldTicket->decrement('qty');
                }
            }

            $amount = (float) $request->amount;
            $oldPaid = (float) $ticketPurchase->paid_amount;
            $correctingCancelledAmount = $oldStatus === 'cancelled' && abs($amount - $oldAmount) > 0.005;
            if ($correctingCancelledAmount && $ticketPurchase->refund_amount !== null
                && $amount + 0.005 < (float) $ticketPurchase->refund_amount) {
                throw new \RuntimeException('Ticket cost cannot be lower than the already-posted airline refund of ৳'.number_format((float) $ticketPurchase->refund_amount, 2).'. Correct the refund first.');
            }

            // $paid_amount is the NEW payment made on this save — the only
            // figure that gets posted to a bank/portal below. Never the
            // cumulative total, and nothing at all on a cancel.
            $paid_amount = 0.0;
            $merge_due_amount = null;
            $merge_payment_status = null;
            $merge_paid_amount = null;

            // -------------------------------------------------------
            // 1. HANDLE PAYMENTS BASED ON STATUS
            // -------------------------------------------------------
            if ($newStatus === 'cancelled') {
                // Nothing here — the reversal is posted after the row is
                // saved as cancelled (below), and every original row stays.
            } else {
                // Money fields are DERIVED here, never copied from the form.
                // "Pay Amount" is a new payment on top of what is already
                // paid, capped at what is still owed so an edit can never
                // overpay; due and payment_status then follow from amount
                // and paid, so a changed price re-flows through them on its
                // own instead of trusting whatever the browser computed.
                $paid_amount = min(max(0.0, (float) $request->paid_amount), max(0.0, $amount - $oldPaid));
                $merge_paid_amount = $oldPaid + $paid_amount;
                $merge_due_amount = max(0.0, $amount - $merge_paid_amount);
                $merge_payment_status = $merge_due_amount <= 0.0
                    ? 'paid'
                    : ($merge_paid_amount > 0.0 ? 'partial' : 'due');
            }

            // -------------------------------------------------------
            // 2. PREPARE MAIN DATA
            // -------------------------------------------------------
            $ticketData = $request->only([
                'passport_holder_id',
                'ticket_id',
                'vendor_id',
                'portal_id',
                'bank_id',
                'ticket_type',
                'trip_type',
                'ticket_class',
                'purchase_date',
                'ticket_no',
                'status',
                'amount',
                'currency',
            ]);

            $ticketData['updated_by'] = Auth::id();

            // An order is ONE passenger on ONE route. A ticket whose order also holds another
            // passenger's (or another route's) live ticket leaves it for an order of its own —
            // entered on the wrong card, then corrected here (owner, 2026-10-18: UFUMMB moved from
            // SALA UDDIN to MD RIDOY MIA stayed in his order, and Ticket Orders showed it as "same
            // passenger" under him). A ticket already in that state is healed by its next save.
            if ($group = $ticketPurchase->order_group) {
                $holderId = (int) ($request->passport_holder_id ?: $ticketPurchase->passport_holder_id);
                $routeId = (int) ($request->ticket_id ?: $ticketPurchase->ticket_id);
                $foreign = fn (string $table) => DB::table($table)->whereNull('deleted_at')->where('order_group', $group)
                    ->where('status', '!=', 'cancelled')
                    ->when($table === 'ticket_purchases', fn ($q) => $q->where('id', '!=', $ticketPurchase->id))
                    ->where(fn ($q) => $q->where('passport_holder_id', '!=', $holderId)->orWhere('ticket_id', '!=', $routeId))
                    ->exists();
                if ($foreign('ticket_purchases') || $foreign('ticket_bookings')) {
                    $ticketData['order_group'] = 'TO'.now()->format('ymdHis').strtoupper(substr(uniqid(), -4)).'-1';
                }
            }

            // Re-saving a row without touching its PNR must leave the PNR
            // exactly as it is, letter included — hence ignore_purchase_id,
            // which stops the row competing with itself and renaming
            // "JK2564VB (a)" on every edit. A PNR genuinely changed to one
            // someone else already holds gets the next free letter instead.
            $pnr = TicketPurchase::allocateTicketNo(
                $request->ticket_no,
                $request->passport_holder_id ?? $ticketPurchase->passport_holder_id,
                ['ignore_purchase_id' => $ticketPurchase->id]
            );
            $ticketData['ticket_no'] = $pnr;
            $ticketData += TicketPurchase::nameTicketNoAttributes(
                'ticket_purchases', $request->name_ticket_no, $request->has('name_ticket_no')
            );

            if ($newStatus != 'cancelled') {
                $ticketData['paid_amount'] = $merge_paid_amount;
                $ticketData['due_amount'] = $merge_due_amount;
                $ticketData['payment_status'] = $merge_payment_status;
            } elseif ($correctingCancelledAmount) {
                $refund = (float) ($ticketPurchase->refund_amount ?? 0);
                $ticketData['due_amount'] = max(0.0, $amount - $refund);
            }

            // An edit that leaves the payment-account picker blank must not
            // erase the bank on file: the journal's "Paid" line and the
            // bank's own statement are resolved from it.
            if (empty($ticketData['bank_id'])) {
                unset($ticketData['bank_id']);
            }

            // -------------------------------------------------------
            // 3. HANDLE ATTACHMENT
            // -------------------------------------------------------
            if ($request->hasFile('attachment')) {
                // Delete old file from storage
                if ($ticketPurchase->attachment) {
                    Storage::disk('public')->delete($ticketPurchase->attachment);
                }
                $ticketData['attachment'] = $request->file('attachment')->store('ticket_attachments', 'public');
            }

            // -------------------------------------------------------
            // 4. UPDATE THE MAIN RECORD
            // -------------------------------------------------------
            $ticketPurchase->update($ticketData);
            // Fare build-up, carrier and flight detail — only the fields this request sent
            // and this server's table has (entryDetailAttributes checks both).
            if ($detail = TicketPurchase::entryDetailAttributes('ticket_purchases', $request->all())) {
                $ticketPurchase->update($detail);
            }

            if ($cancelNow) {
                $refund = $request->filled('refund_amount') ? (float) $request->refund_amount : (float) $ticketPurchase->amount;
                $this->postCancellationReversal($ticketPurchase, $refund);
            }

            $this->syncLegs($ticketPurchase, $request);

            // Vendor changed: every ledger row this purchase posted — the
            // liability and each payment — moves to the new vendor's
            // statement and both running balances are re-chained. Vendor
            // removed (switched to a portal): the liability is withdrawn from
            // the old vendor; payments already made to them stay on record.
            // On a cancel the rows are already gone (step 1) and this is a no-op.
            if ($oldVendorId && $oldVendorId !== $newVendorId) {
                if ($newVendorId) {
                    $this->movePurchaseLedgerToVendor($ticketPurchase->id, $oldVendorId, $newVendorId);
                } else {
                    $this->reconcilePartyLedgerRow($oldVendorId, 'ticket_purchase', $ticketPurchase->id, true, 0.0);
                    Transaction::where('invoice_id', $ticketPurchase->id)
                        ->where('type', 'ticket_purchase')
                        ->where('debit', 0)->where('credit', 0)
                        ->delete();
                }
            }

            if ($correctingCancelledAmount && $newVendorId) {
                $ticketPurchase->refresh();
                $this->updateCancelledTicketPurchaseAmount($ticketPurchase, $request);
            }

            // Liability recognition on the vendor's party statement — kept
            // in sync with the (possibly changed) vendor/amount. Cancelled
            // purchases already had their debit row deleted above alongside
            // the bank/portal transactions.
            if ($newStatus !== 'cancelled' && $newVendorId && $amount > 0) {
                $this->reconcilePartyLedgerRow(
                    $newVendorId, 'ticket_purchase', $ticketPurchase->id, true, $amount,
                    [
                        'account_id' => null,
                        'payment_date' => $request->purchase_date,
                        'reference_no' => $pnr,
                        'remarks' => 'Ticket purchase cost — '.$pnr,
                    ]
                );
            }

            // -------------------------------------------------------
            // 5. APPLY NEW FINANCIALS (only if new status is confirm)
            // -------------------------------------------------------
            if ($request->bank_id && $paid_amount > 0) {
                $this->manageBankPayment($request, $ticketPurchase->id, $paid_amount);
            } elseif ($request->portal_id && $paid_amount > 0) {
                $this->managePortalPayment($request, $ticketPurchase->id, $paid_amount);
            }

            // JOURNAL UPDATE
            $this->updateTicketPurchaseJournal($ticketPurchase, $request);

            // Commission changed on the edit page → post the difference, never rewrite.
            if ($request->has('commission_amount') && $newStatus !== 'cancelled') {
                $ticketPurchase->refresh()->syncCommissionJournal();
            }

            if ($newStatus === 'confirm') {
                // Increment new ticket qty
                $newTicket = Ticket::find($request->ticket_id);
                if ($newTicket) {
                    $newTicket->increment('qty');
                }
            }

            // Everything downstream of this purchase re-derives from the row
            // as it now stands: the vendor payable on the schedule, and the
            // stored totals of any contract flight whose passenger this PNR
            // is linked to (their cost is read off the linked purchase).
            $ticketPurchase->refresh();
            $this->syncPayableSchedule($ticketPurchase, $request->cost_due_date);
            $this->resyncLinkedContractFlights($ticketPurchase->id);

            // Agent price / Sold to from the edit page (owner, 2026-10-07: always editable).
            if ($request->has('sale_client_id') && $newStatus === 'confirm') {
                $this->syncSaleFromEdit($ticketPurchase, $request, (string) $role);
            }

            app(TicketBaggage::class)->sync($ticketPurchase);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket purchase updated successfully!',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket purchase: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * The edit page's Sold to + Agent price, applied through Manage Sales' own
     * store() / update() so the invoice, the buyer's ledger row and the sale
     * journal come out exactly as if done there. Throws to roll the whole
     * save back when Manage Sales refuses.
     *
     *   not sold + buyer and price      → a new sale (as Ticket Entry does)
     *   sold, same buyer, new price     → that sale re-priced (its other tickets keep theirs)
     *   sold, new buyer                 → only when the invoice holds this ticket alone
     *   buyer / price cleared when sold → refused; take it off in Manage Sales
     * A contract-flight ticket is billed per head on the flight: never sold here.
     */
    private function syncSaleFromEdit(TicketPurchase $tp, Request $request, string $role): void
    {
        $buyer = $request->filled('sale_client_id') ? (int) $request->sale_client_id : null;
        $price = $request->filled('sale_price') ? round((float) $request->sale_price, 2) : null;
        $item = TicketSaleItem::where('ticket_purchase_id', $tp->id)
            ->whereHas('sale', fn ($q) => $q->where('status', '<>', 'cancelled'))
            ->with('sale.items')->latest('id')->first();

        if (DB::table('contract_flight_passenger_tickets')->where('ticket_purchase_id', $tp->id)->exists()) {
            if ($buyer || $price !== null) {
                throw new \RuntimeException('This ticket is on a contract flight — the agent is billed per head on the flight, not here. Clear the agent price.');
            }

            return;
        }
        if (! $item) {
            if (! $buyer && $price === null) {
                return;
            }
            if (! $buyer || $price === null) {
                throw new \RuntimeException('Choose who the ticket is sold to and type the agent price — or clear both.');
            }
            if (! auth()->user()?->can('create ticket sale')) {
                throw new \RuntimeException('Selling to an agent needs the "create ticket sale" permission.');
            }
            $res = app(TicketSaleController::class)->store($this->saleRequest($request, [
                'client_id' => $buyer, 'status' => 'confirm', 'currency' => $tp->currency ?: 'BDT', 'sale_date' => $tp->purchase_date,
                'ticket_purchase_ids' => [$tp->id], 'sale_prices' => [$tp->id => $price],
                'paid_amount' => 0, 'payment_method' => 'cash', 'payment_status' => 'due',
            ]), $role)->getData(true);
            if (empty($res['success'])) {
                throw new \RuntimeException('Agent price not posted: '.($res['message'] ?? 'sale refused'));
            }

            return;
        }

        $sale = $item->sale;
        $inv = $sale->invoice_no ?: '#'.$sale->id;
        if ($price === null || ! $buyer) {
            throw new \RuntimeException('This ticket is sold on invoice '.$inv.'. To take it off the sale, edit or delete that invoice in Manage Sales.');
        }
        $sameBuyer = (int) $sale->client_id === $buyer;
        if ($sameBuyer && abs((float) $item->price - $price) < 0.01) {
            return;
        }
        if (! $sameBuyer && $sale->items->count() > 1) {
            throw new \RuntimeException('Invoice '.$inv.' holds other tickets for its buyer — change the buyer from Manage Sales.');
        }
        $prices = $sale->items->mapWithKeys(fn ($i) => [$i->ticket_purchase_id => (float) $i->price])->all();
        $prices[$tp->id] = $price;
        $res = app(TicketSaleController::class)->update($this->saleRequest($request, [
            'client_id' => $buyer, 'bank_id' => $sale->bank_id, 'payment_instruction_bank_id' => $sale->payment_instruction_bank_id,
            'status' => $sale->status, 'currency' => $sale->currency ?: 'BDT',
            'sale_date' => Carbon::parse($sale->sale_date)->toDateString(),
            'due_date' => $sale->due_date ? Carbon::parse($sale->due_date)->toDateString() : null,
            'ticket_purchase_ids' => array_keys($prices), 'sale_prices' => $prices,
            'paid_amount' => 0, 'payment_method' => $sale->payment_method ?: 'cash', 'payment_status' => $sale->payment_status ?: 'due',
        ]), $role, $sale)->getData(true);
        if (empty($res['success'])) {
            throw new \RuntimeException('Agent price not updated: '.($res['message'] ?? 'sale refused'));
        }
    }

    private function saleRequest(Request $from, array $data): Request
    {
        $r = Request::create($from->url(), 'POST', $data);
        $r->setUserResolver(fn () => $from->user());

        return $r;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $role, $id)
    {
        try {
            $ticketPurchase = TicketPurchase::findOrFail($request->item_id);

            if (JournalEntry::whereIn('source', [TicketBaggage::COST, TicketBaggage::CHARGE])->where('source_id', $ticketPurchase->id)->exists()) {
                return response()->json(['success' => false, 'message' => 'This ticket has baggage accounting entries. Use Delete wrong entry so its connected postings are handled together.']);
            }

            // Any status may be deleted (owner's decision, 2026-09-14) — but
            // not a PNR that something else still stands on. A sold ticket
            // has an invoice behind it; a linked one is a passenger's cost.
            // The FK on the flight link is ON DELETE CASCADE, so without this
            // guard the passenger would silently lose its ticket.
            $soldOn = TicketSaleItem::where('ticket_purchase_id', $ticketPurchase->id)
                ->with('sale:id,invoice_no')
                ->first();
            if ($soldOn) {
                return response()->json([
                    'success' => false,
                    'message' => 'This ticket is sold on invoice '.($soldOn->sale?->invoice_no ?? '#'.$soldOn->ticket_sale_id)
                        .'. Refund or remove it from that sale first.',
                ]);
            }
            $linkedPassengers = DB::table('contract_flight_passenger_tickets')
                ->where('ticket_purchase_id', $ticketPurchase->id)
                ->count();
            if ($linkedPassengers > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'This ticket is linked to '.$linkedPassengers.' contract flight passenger'
                        .($linkedPassengers === 1 ? '' : 's').'. Unlink it from the passenger card first.',
                ]);
            }
            // Re-issued / refunded from Ticket Orders (2026-10-06): its change
            // postings (seller fee, agent charge, sale reversal) stand on it.
            if (Schema::hasTable('ticket_changes')
                && DB::table('ticket_changes')->where('ticket_purchase_id', $ticketPurchase->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This ticket has been re-issued, rescheduled or refunded — its postings stand on it, so it cannot be deleted.',
                ]);
            }

            DB::beginTransaction();

            // -------------------------------------------------------
            // 1. REVERSE EVERY BANK PAYMENT — the purchase's own and any
            //    later make_payment settlement. Each row goes back to the
            //    bank it came from; the old code refunded only the latest
            //    row's amount and then deleted them all.
            // -------------------------------------------------------
            $bankTransactions = Transaction::where('invoice_id', $ticketPurchase->id)
                ->where('type', 'ticket_purchase')
                ->get();
            foreach ($bankTransactions as $transaction) {
                if ($transaction->account_id && (float) $transaction->credit > 0) {
                    Bank::find($transaction->account_id)?->increment('balance', (float) $transaction->credit);
                }
            }
            $firstRemovedRowId = $bankTransactions->min('id');
            Transaction::where('invoice_id', $ticketPurchase->id)->where('type', 'ticket_purchase')->delete();
            if ($ticketPurchase->vendor_id && $firstRemovedRowId) {
                $this->rebuildPartyRunningBalancesFrom((int) $ticketPurchase->vendor_id, (int) $firstRemovedRowId);
            }

            // -------------------------------------------------------
            // 1b. REVERSE EVERY PORTAL PAYMENT
            // -------------------------------------------------------
            $portalTransactions = PortalBalance::where('invoice_id', $ticketPurchase->id)
                ->where('type', 'ticket_purchase')
                ->get();
            foreach ($portalTransactions as $portalTransaction) {
                Portal::find($portalTransaction->portal_id)?->increment('balance', (float) $portalTransaction->credit);
            }
            PortalBalance::where('invoice_id', $ticketPurchase->id)->where('type', 'ticket_purchase')->delete();

            // The vendor payable that was scheduled for this purchase has
            // nothing to pay any more.
            $ticketPurchase->schedules()->delete();

            if ($ticketPurchase->status === 'confirm') {

                // -------------------------------------------------------
                // 2. REVERSE TICKET QTY
                // -------------------------------------------------------
                if ($ticketPurchase->ticket_id) {
                    $ticket = Ticket::find($ticketPurchase->ticket_id);
                    if ($ticket && $ticket->qty > 0) {
                        $ticket->decrement('qty');
                    }
                }
            }

            // JOURNAL CLEANUP
            $this->deleteTicketPurchaseJournal($ticketPurchase->id);

            // -------------------------------------------------------
            // 3. DELETE ATTACHMENT FROM STORAGE
            // -------------------------------------------------------
            if ($ticketPurchase->attachment) {
                Storage::disk('public')->delete($ticketPurchase->attachment);
            }

            // -------------------------------------------------------
            // 4. DELETE THE TICKET PURCHASE RECORD
            // -------------------------------------------------------
            $ticketPurchase->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket purchase deleted successfully!',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ticket purchase: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * One payment to the ticket's seller out of a bank: the vendor's ledger row
     * (Cr), the bank's balance down, and the journal Dr AP / Cr bank. Moved
     * verbatim out of make_payment() so the Ticket Trail re-posts a corrected
     * payment through the same code. paid / due on the purchase are the caller's.
     */
    public function postSellerBankPayment(TicketPurchase $ticketPurchase, int $bankId, float $amount, $date, ?string $method, ?string $reference, string $remarks): void
    {
        $acc = Bank::find($bankId);
        $opening_balance = (float) ($acc?->balance ?? 0);

        // `old_balance`/`balance` are the vendor's own running
        // party-ledger balance, not the bank's — bank balance is
        // tracked separately below.
        $partyId = $ticketPurchase->vendor_id;
        $party_old_balance = $partyId
            ? (float) (Transaction::where('user_id', $partyId)->orderByDesc('id')->value('balance') ?? 0)
            : 0.0;
        $party_new_balance = $party_old_balance - $amount;

        Transaction::create([
            'user_id' => $partyId,
            'user_type' => 'supplier',
            'type' => 'ticket_purchase',
            'account_id' => $bankId,
            'payment_date' => $date,
            'reference_no' => $reference,
            'payment_method' => $method,
            'invoice_id' => $ticketPurchase->id,
            'old_balance' => $party_old_balance,
            'debit' => 0,
            'credit' => $amount,
            'balance' => $party_new_balance,
            'remarks' => $remarks,
        ]);

        if ($acc) {
            $acc->update(['balance' => $opening_balance - $amount]);
        }

        // ── JOURNAL: Debit AP 2110 / Credit Bank ─────────────────────
        $bank = Bank::find($bankId);
        if (! $bank || ! $bank->account_id) {
            throw new \Exception('Bank is not linked to a chart-of-accounts account.');
        }

        $this->postPurchaseJournalPayment(
            'ticket_purchase',
            $ticketPurchase->id,
            TravelCompany::ID,
            $date,
            $reference ?? $ticketPurchase->ticket_no,
            'Ticket purchase payment made — '.$ticketPurchase->ticket_no,
            $amount,
            $bank->account_id,
            'vendor',
            $ticketPurchase->vendor_id
        );
    }

    public function make_payment(Request $request, $role, $id)
    {
        try {
            $validated = $request->validate([
                'payment_method' => 'required|in:cash,card,advance,checque,bank_transfer,mobile_banking,other',
                'bank_id' => 'required|exists:banks,id',
                'payment_date' => 'required|date',
                'reference_no' => 'nullable|string|max:255',
                'payment_amount' => 'required|numeric|min:0.01',
                'schedule_id' => 'nullable|exists:payment_schedules,id',
            ]);

            $ticketPurchase = TicketPurchase::findOrFail($id);

            if ($validated['payment_amount'] > $ticketPurchase->due_amount) {
                return redirect()->back()->with('error', 'Payment amount cannot exceed due amount!');
            }

            DB::transaction(function () use ($validated, $ticketPurchase) {

                $this->postSellerBankPayment($ticketPurchase, (int) $validated['bank_id'], (float) $validated['payment_amount'],
                    $validated['payment_date'], $validated['payment_method'], $validated['reference_no'] ?? null,
                    'Ticket purchase due payment (make_payment).');

                $newPaid = min($ticketPurchase->paid_amount + $validated['payment_amount'], $ticketPurchase->amount);
                $newDue = max($ticketPurchase->due_amount - $validated['payment_amount'], 0);

                $ticketPurchase->update([
                    'paid_amount' => $newPaid,
                    'due_amount' => $newDue,
                    'payment_status' => $newDue <= 0 ? 'paid' : 'partial',
                ]);

                if (! empty($validated['schedule_id'])) {
                    PaymentSchedule::where('id', $validated['schedule_id'])
                        ->where('schedulable_type', TicketPurchase::class)
                        ->where('schedulable_id', $ticketPurchase->id)
                        ->update(['status' => 'paid', 'paid_date' => $validated['payment_date']]);
                }
            });
        } catch (\Throwable $th) {
            DB::rollBack();

            return redirect()->back()->with('error', $th->getMessage());
        }

        return redirect()->back()->with('success', 'Payment of ৳'.number_format($validated['payment_amount'], 2).' sent successfully!');
    }
}
