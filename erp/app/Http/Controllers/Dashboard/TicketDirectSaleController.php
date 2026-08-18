<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\PassportHolder;
use App\Models\PassportHolderCategory;
use App\Models\PaymentSchedule;
use App\Models\Portal;
use App\Models\PortalBalance;
use App\Models\Ticket;
use App\Models\TicketPurchase;
use App\Models\TicketRefund;
use App\Models\TicketReissue;
use App\Models\TicketSale;
use App\Models\TicketSaleItem;
use App\Models\Transaction;
use App\Models\User;
use App\Traits\PostsPartyLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * TicketDirectSaleController
 *
 * "Buy + sell in one form" workflow.
 * One submission creates:
 *   1 × ticket_purchases  (cost side)
 *   1 × ticket_sales      (revenue side)
 *   1 × ticket_sale_items (link)
 *
 * Later due settlement is handled exclusively via:
 *   TicketPurchaseController@make_payment  (pay vendor)
 *   TicketSaleController@make_payment      (receive from agent/customer)
 *
 * Accounting rules enforced:
 *   Purchase recognition : Dr TicketCost / Cr Bank-or-Portal (paid) / Cr AP (due)
 *   Sale recognition     : Dr Bank (received) / Dr AR (due) / Cr Revenue
 *   AP settlement        : Dr AP / Cr Bank   — via make_payment only
 *   AR settlement        : Dr Bank / Cr AR   — via make_payment only
 */
class TicketDirectSaleController implements HasMiddleware
{
    use PostsPartyLedger;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view ticket direct sale|view all ticket direct sale',   only: ['index']),
            new Middleware('permission:create ticket direct sale', only: ['create', 'store']),
            new Middleware('permission:edit ticket direct sale',   only: ['edit', 'update']),
            new Middleware('permission:delete ticket direct sale', only: ['destroy']),
        ];
    }

    // =========================================================================
    // INDEX
    // =========================================================================

    public function index(Request $request)
    {
        $query = TicketPurchase::with(['passportHolder', 'ticket', 'vendor', 'portal', 'bank'])
            ->where('source', 'direct_sale');

        $query->when($request->filled('ticket_no'),     fn($q) => $q->where('ticket_no', 'like', "%{$request->ticket_no}%"));
        $query->when($request->filled('purchase_date'), fn($q) => $q->whereDate('purchase_date', $request->purchase_date));
        $query->when($request->filled('status'),        fn($q) => $q->where('status', $request->status));
        $query->when($request->filled('ticket_id'),     fn($q) => $q->where('ticket_id', $request->ticket_id));

        $data['datas']   = $query->latest()->paginate(10)->withQueryString();
        $data['tickets'] = Ticket::where('status', 1)->get();

        return view('dashboard.ticket_direct_sales.index', $data);
    }

    // =========================================================================
    // CREATE
    // =========================================================================

    public function create()
    {
        $banks            = Bank::where('status', 1)->get();
        $tickets          = Ticket::where('status', 1)->get();
        $vendors          = User::role('vendor')->orderBy('name')->get();
        $agents           = User::role('agent')->orderBy('name')->get();
        $portals          = Portal::where('status', 'active')->orderBy('name')->get();
        $passport_holders = PassportHolder::where('status', 1)->get();
        $ph_categories    = PassportHolderCategory::orderBy('name')->get();
        $airports         = \App\Models\Airport::orderBy('name')->get();
        $airlines         = \App\Models\Airline::orderBy('name')->get();

        $lastPurchase = TicketPurchase::latest('id')->first();
        $lastPNum     = $lastPurchase ? (int) substr($lastPurchase->ticket_no, 2) : 0;
        $ticketNo     = 'TP' . str_pad($lastPNum + 1, 5, '0', STR_PAD_LEFT);

        return view('dashboard.ticket_direct_sales.create', compact(
            'banks', 'tickets', 'vendors', 'agents', 'portals',
            'passport_holders', 'ph_categories', 'ticketNo',
            'airports', 'airlines'
        ));
    }

    // =========================================================================
    // QUICK CREATE TICKET ROUTE (AJAX)
    // =========================================================================

    public function quickCreateTicket(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $ticket = Ticket::create([
            'title'           => $request->title,
            'airline_id'      => $request->airline_id   ?: null,
            'from_airport_id' => $request->from_airport_id ?: null,
            'to_airport_id'   => $request->to_airport_id   ?: null,
            'price'           => (float) ($request->price ?? 0),
            'qty'             => 0,
            'status'          => 1,
        ]);

        return response()->json([
            'success' => true,
            'ticket'  => ['id' => $ticket->id, 'title' => $ticket->title, 'price' => (float) $ticket->price],
        ]);
    }

    // =========================================================================
    // STORE
    // =========================================================================

    public function store(Request $request, $role)
    {
        $validator = Validator::make($request->all(), [
            // Per-passenger rows
            'passengers'                           => 'required|array|min:1',
            'passengers.*.passport_holder_id'      => 'required|exists:passport_holders,id',
            'passengers.*.ticket_id'               => 'required|exists:tickets,id',
            'passengers.*.vendor_id'               => 'nullable|exists:users,id',
            'passengers.*.portal_id'               => 'nullable|exists:portals,id',
            'passengers.*.purchase_bank_id'        => 'nullable|exists:banks,id',
            'passengers.*.ticket_type'             => 'required|in:air,bus,train,other',
            'passengers.*.trip_type'               => 'required|in:one-way,two-way',
            'passengers.*.purchase_date'           => 'required|date',
            'passengers.*.ticket_no'               => 'required|string',
            'passengers.*.cost_amount'             => 'required|numeric|min:0',
            'passengers.*.purchase_paid'           => 'nullable|numeric|min:0',
            'passengers.*.purchase_pay_method'     => 'nullable|string',
            'passengers.*.purchase_pay_status'     => 'required|in:due,paid,partial',
            'currency'                              => 'required|string|max:5',
            'attachment'                            => 'nullable|file|mimes:pdf,jpg,jpeg,png',
        ]);

        $validator->after(function ($v) use ($request) {
            $passengers = $request->passengers ?? [];

            // Per-row checks
            foreach ($passengers as $i => $row) {
                $cost = (float) ($row['cost_amount'] ?? 0);
                $paid = (float) ($row['purchase_paid'] ?? 0);

                if ($paid > $cost) {
                    $v->errors()->add("passengers.{$i}.purchase_paid", 'Cost paid cannot exceed cost price.');
                }
                if ($paid > 0 && empty($row['purchase_bank_id']) && empty($row['portal_id'])) {
                    $v->errors()->add("passengers.{$i}.purchase_bank_id", 'Bank or portal required when cost has an immediate payment.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $passengers = $request->passengers;
        $companyId  = auth()->user()->company_id ?? 2;

        // Store attachment once; attach to the first purchase record only
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('ticket_attachments', 'public');
        }

        DB::beginTransaction();
        try {
            $createdPurchases = [];
            $isFirstRow = true;

            // ── 1. CREATE ONE TICKET PURCHASE PER PASSENGER ROW ──────────────
            foreach ($passengers as $row) {
                $costAmount   = (float) ($row['cost_amount'] ?? 0);
                $purchasePaid = min((float) ($row['purchase_paid'] ?? 0), $costAmount);
                $purchaseDue  = $costAmount - $purchasePaid;
                $purchasePSt  = $purchaseDue <= 0 ? 'paid' : ($purchasePaid > 0 ? 'partial' : 'due');

                $ticketPurchase = TicketPurchase::create([
                    'passport_holder_id' => $row['passport_holder_id'],
                    'ticket_id'          => $row['ticket_id'],
                    'vendor_id'          => $row['vendor_id'] ?? null,
                    'portal_id'          => $row['portal_id'] ?? null,
                    'bank_id'            => $row['purchase_bank_id'] ?? null,
                    'ticket_type'        => $row['ticket_type'],
                    'trip_type'          => $row['trip_type'],
                    'purchase_date'      => $row['purchase_date'],
                    'ticket_no'          => $row['ticket_no'],
                    'status'             => 'confirm',
                    'source'             => 'direct_sale',
                    'amount'             => $costAmount,
                    'paid_amount'        => $purchasePaid,
                    'due_amount'         => $purchaseDue,
                    'payment_status'     => $purchasePSt,
                    'currency'           => $request->currency,
                    'company_id'         => $companyId,
                    'created_by'         => Auth::id(),
                    'attachment'         => $isFirstRow ? $attachmentPath : null,
                ]);

                // Liability recognition on the vendor's party statement — the
                // full cost owed, separate from the paid-portion credit row
                // below, so the statement shows what's owed even before
                // anything is paid. Mirrors the debit-row-on-invoice-creation
                // pattern already used on the sale/AR side.
                if (!empty($row['vendor_id']) && $costAmount > 0) {
                    $this->reconcilePartyLedgerRow(
                        $row['vendor_id'], 'ticket_purchase', $ticketPurchase->id, true, $costAmount,
                        [
                            'account_id'   => null,
                            'payment_date' => $row['purchase_date'],
                            'reference_no' => $row['ticket_no'] ?? null,
                            'remarks'      => 'Ticket purchase cost — ' . ($row['ticket_no'] ?? $ticketPurchase->id),
                        ]
                    );
                }

                // Bank or portal payment for this row's cost
                $bankId   = $row['purchase_bank_id'] ?? null;
                $portalId = $row['portal_id'] ?? null;

                if ($bankId && $purchasePaid > 0) {
                    $this->debitFromBank(
                        $bankId, $ticketPurchase->id, 'ticket_purchase',
                        $row['vendor_id'] ?? null, $row['purchase_date'],
                        null, $row['purchase_pay_method'] ?? 'cash', $purchasePaid
                    );
                } elseif ($portalId && $purchasePaid > 0) {
                    $this->debitFromPortal($portalId, $ticketPurchase->id, $row['purchase_pay_method'] ?? 'cash', $purchasePaid);
                }

                // Stock: purchase confirmed → +1 qty
                $ticket = Ticket::find($row['ticket_id']);
                if ($ticket) {
                    $ticket->increment('qty');
                }

                $this->createPurchaseJournal($ticketPurchase, $bankId, $portalId);

                // Cost payable schedule — only when vendor-based (not portal) and there is a due
                if ($purchaseDue > 0 && !$portalId) {
                    $schedDate = !empty($row['cost_due_date']) ? $row['cost_due_date'] : $row['purchase_date'];
                    PaymentSchedule::create([
                        'company_id'       => $companyId,
                        'schedulable_type' => TicketPurchase::class,
                        'schedulable_id'   => $ticketPurchase->id,
                        'type'             => 'pay',
                        'party_type'       => 'vendor',
                        'party_id'         => $row['vendor_id'] ?? null,
                        'party_name'       => $ticketPurchase->vendor?->name ?? 'Vendor (unassigned)',
                        'source_label'     => $row['ticket_no'] ?? null,
                        'amount'           => $purchaseDue,
                        'paid_amount'      => 0,
                        'scheduled_date'   => $schedDate,
                        'status'           => 'pending',
                        'created_by'       => Auth::id(),
                    ]);
                }

                $createdPurchases[] = ['purchase' => $ticketPurchase, 'row' => $row, 'ticket' => $ticket];
                $isFirstRow = false;
            }

            DB::commit();

            $count = count($createdPurchases);
            return response()->json([
                'success' => true,
                'message' => "{$count} ticket(s) purchased and confirmed successfully! Sell them anytime from Manage Sales.",
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed: ' . $e->getMessage(),
            ]);
        }
    }

    // =========================================================================
    // EDIT
    // =========================================================================

    public function edit($role, $ticket_direct_sale)
    {
        $ticketPurchase = TicketPurchase::where('source', 'direct_sale')->findOrFail($ticket_direct_sale);

        $banks            = Bank::where('status', 1)->get();
        $tickets          = Ticket::where('status', 1)->get();
        $vendors          = User::role('vendor')->orderBy('name')->get();
        $portals          = Portal::where('status', 'active')->orderBy('name')->get();
        $passport_holders = PassportHolder::where('status', 1)->get();

        return view('dashboard.ticket_direct_sales.edit', compact(
            'ticketPurchase', 'banks', 'tickets', 'vendors', 'portals', 'passport_holders'
        ));
    }

    // =========================================================================
    // UPDATE
    // =========================================================================

    public function update(Request $request, $role, $ticket_direct_sale)
    {
        $ticketPurchase = TicketPurchase::where('source', 'direct_sale')->findOrFail($ticket_direct_sale);

        $validator = Validator::make($request->all(), [
            'passport_holder_id'  => 'required|exists:passport_holders,id',
            'ticket_id'           => 'required|exists:tickets,id',
            'vendor_id'           => 'nullable|exists:users,id',
            'portal_id'           => 'nullable|exists:portals,id',
            'purchase_bank_id'    => 'nullable|exists:banks,id',
            'ticket_type'         => 'required|in:air,bus,train,other',
            'trip_type'           => 'required|in:one-way,two-way',
            'purchase_date'       => 'required|date',
            'ticket_no'           => 'required|string|unique:ticket_purchases,ticket_no,' . $ticketPurchase->id,
            'cost_amount'         => 'required|numeric|min:0',
            'purchase_paid'       => 'nullable|numeric|min:0',
            'purchase_pay_status' => 'required|in:due,paid,partial',
            'purchase_pay_method' => 'required|string',
            'currency'            => 'required|string|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        // Block cost changes after settlements have been posted
        $purchaseSettled = \App\Models\JournalEntry::where('source', 'ticket_purchase')
            ->where('source_id', $ticketPurchase->id)->count() > 1;

        if ($purchaseSettled && (float)$request->cost_amount !== (float)$ticketPurchase->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Cost amount cannot be changed after vendor settlement. Use Make Payment instead.',
            ]);
        }

        DB::beginTransaction();
        try {
            $costAmount = (float) $request->cost_amount;

            // Accumulate: form's purchase_paid = incremental new payment this edit
            $prevPurchasePaid  = (float) $ticketPurchase->paid_amount;
            $addlPurchasePaid  = max(0, (float) ($request->purchase_paid ?? 0));
            // Clamp to avoid exceeding cost
            $addlPurchasePaid  = min($addlPurchasePaid, $costAmount - $prevPurchasePaid);
            $totalPurchasePaid = $prevPurchasePaid + $addlPurchasePaid;
            $totalPurchaseDue  = $costAmount - $totalPurchasePaid;
            $purchasePSt       = $totalPurchaseDue <= 0 ? 'paid' : ($totalPurchasePaid > 0 ? 'partial' : 'due');

            $ticketPurchase->update([
                'passport_holder_id' => $request->passport_holder_id,
                'ticket_id'          => $request->ticket_id,
                'vendor_id'          => $request->vendor_id,
                'portal_id'          => $request->portal_id,
                'bank_id'            => $request->purchase_bank_id,
                'ticket_type'        => $request->ticket_type,
                'trip_type'          => $request->trip_type,
                'purchase_date'      => $request->purchase_date,
                'ticket_no'          => $request->ticket_no,
                'amount'             => $costAmount,
                'paid_amount'        => $totalPurchasePaid,
                'due_amount'         => $totalPurchaseDue,
                'payment_status'     => $purchasePSt,
                'currency'           => $request->currency,
                'updated_by'         => Auth::id(),
            ]);

            if ($request->hasFile('attachment')) {
                if ($ticketPurchase->attachment) {
                    Storage::disk('public')->delete($ticketPurchase->attachment);
                }
                $ticketPurchase->update([
                    'attachment' => $request->file('attachment')->store('ticket_attachments', 'public'),
                ]);
            }

            // Bank/portal transaction for the incremental purchase payment
            if ($addlPurchasePaid > 0) {
                if ($request->purchase_bank_id) {
                    $this->debitFromBank(
                        $request->purchase_bank_id, $ticketPurchase->id, 'ticket_purchase',
                        $request->vendor_id, $request->purchase_date,
                        $request->reference_no ?? null,
                        $request->purchase_pay_method, $addlPurchasePaid
                    );
                } elseif ($request->portal_id) {
                    $this->debitFromPortal(
                        $request->portal_id, $ticketPurchase->id,
                        $request->purchase_pay_method, $addlPurchasePaid
                    );
                }
            }

            // Recognition journal — settlement-aware update
            $this->updatePurchaseJournal($ticketPurchase, $request->purchase_bank_id, $request->portal_id);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Record updated successfully!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // DESTROY
    // =========================================================================

    public function destroy(Request $request, $role, $id)
    {
        $ticketPurchase = TicketPurchase::where('source', 'direct_sale')->find($request->item_id ?? $id);
        if (!$ticketPurchase) {
            return response()->json(['success' => false, 'message' => 'Record not found.']);
        }

        if (TicketSaleItem::where('ticket_purchase_id', $ticketPurchase->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket has already been sold via Manage Sales. Delete that sale first.',
            ]);
        }

        DB::beginTransaction();
        try {
            if ($ticketPurchase->status === 'confirm') {
                $ticket = Ticket::find($ticketPurchase->ticket_id);
                if ($ticket && $ticket->qty > 0) {
                    $ticket->decrement('qty');
                }
            }

            // Reverse purchase bank transactions (money went out — credit field holds amount)
            Transaction::where('invoice_id', $ticketPurchase->id)->where('type', 'ticket_purchase')
                ->each(function ($t) {
                    $bank = Bank::find($t->account_id);
                    if ($bank) $bank->increment('balance', (float) $t->credit);
                });
            Transaction::where('invoice_id', $ticketPurchase->id)->where('type', 'ticket_purchase')->delete();

            // Reverse portal transactions
            PortalBalance::where('invoice_id', $ticketPurchase->id)->where('type', 'ticket_purchase')
                ->each(function ($pb) {
                    $portal = Portal::find($pb->portal_id);
                    if ($portal) $portal->increment('balance', (float) $pb->credit);
                });
            PortalBalance::where('invoice_id', $ticketPurchase->id)->where('type', 'ticket_purchase')->delete();

            // Delete ALL purchase journals (recognition + any settlement journals)
            \App\Models\JournalEntry::where('source', 'ticket_purchase')
                ->where('source_id', $ticketPurchase->id)
                ->each(function ($j) { $j->items()->forceDelete(); $j->forceDelete(); });

            if ($ticketPurchase->attachment) {
                Storage::disk('public')->delete($ticketPurchase->attachment);
            }
            $ticketPurchase->forceDelete();

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Record deleted successfully!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // INVOICE LOOKUP (AJAX)
    // =========================================================================

    public function invoiceLookup(Request $request)
    {
        $invoiceNo = trim($request->invoice_no ?? '');
        $pnr       = trim($request->pnr ?? '');

        if ($invoiceNo) {
            $sale = TicketSale::with(['items.ticketPurchase.passportHolder', 'items.ticketPurchase.ticket', 'items.ticketPurchase.vendor', 'items.ticketPurchase.portal'])
                ->where('invoice_no', $invoiceNo)
                ->first();

            if (!$sale) {
                return response()->json(['success' => false, 'message' => "Invoice '{$invoiceNo}' not found."]);
            }
        } elseif ($pnr) {
            $saleItem = \App\Models\TicketSaleItem::with(['sale', 'ticketPurchase'])
                ->whereHas('ticketPurchase', fn($q) => $q->where('ticket_no', $pnr))
                ->latest()
                ->first();

            if (!$saleItem) {
                return response()->json(['success' => false, 'message' => "PNR '{$pnr}' not found."]);
            }

            $sale = $saleItem->sale;

            if (!$sale) {
                return response()->json(['success' => false, 'message' => "No sale found for PNR '{$pnr}'."]);
            }

            $sale->load(['items.ticketPurchase.passportHolder', 'items.ticketPurchase.ticket', 'items.ticketPurchase.vendor', 'items.ticketPurchase.portal']);
        } else {
            return response()->json(['success' => false, 'message' => 'Invoice number or PNR required.']);
        }

        $passengers = $sale->items->map(function ($item) {
            $p = $item->ticketPurchase;
            return [
                'purchase_id'  => $p?->id,
                'passenger'    => $p?->passportHolder?->name ?? '—',
                'pnr'          => $p?->ticket_no ?? '—',
                'route'        => $p?->ticket?->title ?? '—',
                'purchase_date'=> $p?->purchase_date ?? '—',
                'org_cost'     => (float) ($p?->amount ?? 0),
                'sale_price'   => (float) ($item->price ?? 0),
                'vendor_id'    => $p?->vendor_id,
                'portal_id'    => $p?->portal_id,
            ];
        });

        return response()->json([
            'success'    => true,
            'sale_id'    => $sale->id,
            'invoice_no' => $sale->invoice_no,
            'status'     => $sale->status,
            'currency'   => $sale->currency,
            'passengers' => $passengers,
        ]);
    }

    // =========================================================================
    // STORE REFUND
    // =========================================================================

    public function storeRefund(Request $request, $role)
    {
        $validator = Validator::make($request->all(), [
            'agent_id'        => 'required|exists:users,id',
            'refund_date'     => 'required|date',
            'original_invoice'=> 'required|string',
            'status'          => 'required|in:confirm,processing,pending_airline,completed',
            'org_cost'        => 'required|numeric|min:0',
            'airline_refund'  => 'required|numeric|min:0',
            'penalty'         => 'nullable|numeric|min:0',
            'service_charge'  => 'nullable|numeric|min:0',
            'org_sale'        => 'required|numeric|min:0',
            'net_refund'      => 'required|numeric|min:0',
            'paid_amount'     => 'nullable|numeric|min:0',
            'pay_method'      => 'nullable|string',
            'payment_status'  => 'required|in:due,partial,paid',
            'bank_id'         => 'nullable|exists:banks,id',
            'vendor_id'       => 'nullable|exists:users,id',
            'portal_id'       => 'nullable|exists:portals,id',
            'refund_ref_no'   => 'nullable|string',
            'currency'        => 'required|string|max:5',
            'attachment'      => 'nullable|file|mimes:pdf,jpg,jpeg,png',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $sale = TicketSale::where('invoice_no', $request->original_invoice)->first();
        if (!$sale) {
            return response()->json(['success' => false, 'message' => "Invoice '{$request->original_invoice}' not found."]);
        }

        $netRefund  = (float) $request->net_refund;
        $paidAmount = min((float) ($request->paid_amount ?? 0), $netRefund);
        $dueAmount  = $netRefund - $paidAmount;

        DB::beginTransaction();
        try {
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('ticket_attachments', 'public');
            }

            $refund = TicketRefund::create([
                'company_id'       => auth()->user()->company_id ?? 2,
                'ticket_sale_id'   => $sale->id,
                'agent_id'         => $request->agent_id,
                'vendor_id'        => $request->vendor_id,
                'portal_id'        => $request->portal_id,
                'bank_id'          => $request->bank_id,
                'original_invoice' => $request->original_invoice,
                'refund_ref_no'    => $request->refund_ref_no,
                'refund_date'      => $request->refund_date,
                'org_cost'         => $request->org_cost,
                'airline_refund'   => $request->airline_refund,
                'penalty'          => $request->penalty ?? 0,
                'service_charge'   => $request->service_charge ?? 0,
                'org_sale'         => $request->org_sale,
                'net_refund'       => $netRefund,
                'paid_amount'      => $paidAmount,
                'due_amount'       => $dueAmount,
                'payment_status'   => $dueAmount <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'due'),
                'pay_method'       => $request->pay_method,
                'status'           => $request->status,
                'currency'         => $request->currency,
                'attachment'       => $attachmentPath,
                'created_by'       => Auth::id(),
            ]);

            // Refund payout: money leaves the bank
            if ($request->bank_id && $paidAmount > 0) {
                $this->debitFromBank(
                    $request->bank_id, $refund->id, 'ticket_refund',
                    $request->agent_id, $request->refund_date,
                    $request->refund_ref_no, $request->pay_method ?? 'cash', $paidAmount
                );
            }

            // Airline refund restores portal balance
            if ($request->portal_id && (float) $request->airline_refund > 0) {
                $this->creditToPortal($request->portal_id, $refund->id, (float) $request->airline_refund);
            }

            // Accounting journal
            $this->createRefundJournal($refund);

            DB::commit();
            return response()->json(['success' => true, 'message' => "Refund processed for invoice {$request->original_invoice}."]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Refund failed: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // STORE REISSUE
    // =========================================================================

    public function storeReissue(Request $request, $role)
    {
        $validator = Validator::make($request->all(), [
            'agent_id'         => 'required|exists:users,id',
            'reissue_date'     => 'required|date',
            'original_invoice' => 'required|string',
            'new_ticket_id'    => 'required|exists:tickets,id',
            'new_trip_type'    => 'required|in:one-way,two-way',
            'new_ticket_no'    => 'required|string|unique:ticket_purchases,ticket_no',
            'new_vendor_id'    => 'nullable|exists:users,id',
            'new_portal_id'    => 'nullable|exists:portals,id',
            'new_travel_date'  => 'nullable|date',
            'new_purchase_date'=> 'required|date',
            'org_cost'         => 'nullable|numeric|min:0',
            'penalty'          => 'required|numeric|min:0',
            'fare_diff'        => 'required|numeric',
            'new_cost'         => 'required|numeric|min:0',
            'service_charge'   => 'nullable|numeric|min:0',
            'new_sale_price'   => 'required|numeric|min:0',
            'paid_amount'      => 'nullable|numeric|min:0',
            'pay_method'       => 'nullable|string',
            'payment_status'   => 'required|in:due,partial,paid',
            'bank_id'          => 'nullable|exists:banks,id',
            'currency'         => 'required|string|max:5',
            'attachment'       => 'nullable|file|mimes:pdf,jpg,jpeg,png',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $sale = TicketSale::with(['items.ticketPurchase'])->where('invoice_no', $request->original_invoice)->first();
        if (!$sale) {
            return response()->json(['success' => false, 'message' => "Invoice '{$request->original_invoice}' not found."]);
        }

        $originalPurchase = $sale->items()->first()?->ticketPurchase;

        $newSalePrice = (float) $request->new_sale_price;
        $paidAmount   = min((float) ($request->paid_amount ?? 0), $newSalePrice);
        $dueAmount    = $newSalePrice - $paidAmount;

        DB::beginTransaction();
        try {
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('ticket_attachments', 'public');
            }

            // Create new TicketPurchase for the re-issued ticket
            $newCost = (float) $request->new_cost;
            $newPurchase = TicketPurchase::create([
                'passport_holder_id' => $originalPurchase?->passport_holder_id,
                'ticket_id'          => $request->new_ticket_id,
                'vendor_id'          => $request->new_vendor_id,
                'portal_id'          => $request->new_portal_id,
                'ticket_type'        => $originalPurchase?->ticket_type ?? 'air',
                'trip_type'          => $request->new_trip_type,
                'ticket_no'          => $request->new_ticket_no,
                'purchase_date'      => $request->new_purchase_date,
                'amount'             => $newCost,
                'paid_amount'        => 0,
                'due_amount'         => $newCost,
                'payment_status'     => 'due',
                'status'             => 'confirm',
                'currency'           => $request->currency,
                'company_id'         => auth()->user()->company_id ?? 2,
                'created_by'         => Auth::id(),
                'attachment'         => $attachmentPath,
            ]);

            TicketReissue::create([
                'company_id'          => auth()->user()->company_id ?? 2,
                'original_sale_id'    => $sale->id,
                'original_purchase_id'=> $originalPurchase?->id,
                'agent_id'            => $request->agent_id,
                'new_ticket_id'       => $request->new_ticket_id,
                'new_vendor_id'       => $request->new_vendor_id,
                'new_portal_id'       => $request->new_portal_id,
                'bank_id'             => $request->bank_id,
                'original_invoice'    => $request->original_invoice,
                'new_ticket_no'       => $request->new_ticket_no,
                'new_trip_type'       => $request->new_trip_type,
                'reissue_date'        => $request->reissue_date,
                'new_travel_date'     => $request->new_travel_date,
                'new_purchase_date'   => $request->new_purchase_date,
                'org_cost'            => $request->org_cost ?? 0,
                'penalty'             => $request->penalty,
                'fare_diff'           => $request->fare_diff,
                'new_cost'            => $newCost,
                'service_charge'      => $request->service_charge ?? 0,
                'new_sale_price'      => $newSalePrice,
                'paid_amount'         => $paidAmount,
                'due_amount'          => $dueAmount,
                'payment_status'      => $dueAmount <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'due'),
                'pay_method'          => $request->pay_method,
                'status'              => 'confirm',
                'currency'            => $request->currency,
                'created_by'          => Auth::id(),
            ]);

            // Additional payment received from agent into bank
            if ($request->bank_id && $paidAmount > 0) {
                $this->creditToBank(
                    $request->bank_id, $sale->id, 'ticket_reissue',
                    $request->agent_id, $request->reissue_date,
                    null, $request->pay_method ?? 'cash', $paidAmount
                );
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => "Re-issue saved. New ticket: {$request->new_ticket_no}."]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Re-issue failed: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // PRIVATE PAYMENT HELPERS
    // =========================================================================

    /**
     * Outgoing bank payment (purchase). Decrements bank balance.
     * Transaction: credit = amount, debit = 0 (bank is credited when paying out).
     */
    private function debitFromBank($bankId, $invoiceId, $type, $userId, $date, $refNo, $method, float $amount)
    {
        $acc         = Bank::findOrFail($bankId);
        $bank_new_balance = (float) $acc->balance - $amount;

        // `old_balance`/`balance` are the vendor's own running party-ledger
        // balance, not the bank's — the bank's balance is tracked separately
        // below.
        $party_old_balance = $userId
            ? (float) (Transaction::where('user_id', $userId)->orderByDesc('id')->value('balance') ?? 0)
            : 0.0;
        $party_new_balance = $party_old_balance - $amount;

        Transaction::create([
            'user_id'        => $userId,
            'user_type'      => 'supplier',
            'type'           => $type,
            'account_id'     => $bankId,
            'payment_date'   => $date ?? now(),
            'reference_no'   => $refNo,
            'payment_method' => $method,
            'invoice_id'     => $invoiceId,
            'old_balance'    => $party_old_balance,
            'debit'          => 0,
            'credit'         => $amount,
            'balance'        => $party_new_balance,
            'remarks'        => 'Direct ticket purchase payment.',
        ]);

        $acc->update(['balance' => $bank_new_balance]);
    }

    /**
     * Incoming bank receipt (sale). Increments bank balance.
     * Transaction: debit = amount, credit = 0 (bank is debited when receiving).
     */
    private function creditToBank($bankId, $invoiceId, $type, $userId, $date, $refNo, $method, float $amount)
    {
        $acc             = Bank::findOrFail($bankId);
        $opening_balance = (float) $acc->balance;
        $new_balance     = $opening_balance + $amount;

        Transaction::create([
            'user_id'        => $userId,
            'user_type'      => 'customer',
            'type'           => $type,
            'account_id'     => $bankId,
            'payment_date'   => $date ?? now(),
            'reference_no'   => $refNo,
            'payment_method' => $method,
            'invoice_id'     => $invoiceId,
            'old_balance'    => $opening_balance,
            'debit'          => $amount,
            'credit'         => 0,
            'balance'        => $new_balance,
            'remarks'        => 'Direct ticket sale receipt.',
        ]);

        $acc->update(['balance' => $new_balance]);
    }

    /** Outgoing portal payment (purchase). Decrements portal balance. */
    private function debitFromPortal($portalId, $invoiceId, $method, float $amount)
    {
        $acc             = Portal::findOrFail($portalId);
        $opening_balance = (float) $acc->balance;
        $new_balance     = $opening_balance - $amount;

        PortalBalance::create([
            'invoice_id'      => $invoiceId,
            'portal_id'       => $portalId,
            'type'            => 'ticket_purchase',
            'old_balance'     => $opening_balance,
            'payment_method'  => $method,
            'debit'           => 0,
            'credit'          => $amount,
            'current_balance' => $new_balance,
            'remarks'         => 'Direct ticket purchase via portal.',
            'created_by'      => Auth::id(),
        ]);

        $acc->update(['balance' => $new_balance]);
    }

    // =========================================================================
    // JOURNAL HELPERS — Purchase (recognition only)
    // =========================================================================

    private function createPurchaseJournal(TicketPurchase $tp, $bankId, $portalId)
    {
        $costAccount    = \App\Models\Account::where('code', config('accounts.ticket_purchase_cost'))->first();
        $payableAccount = \App\Models\Account::where('code', config('accounts.accounts_payable'))->first();

        if (!$costAccount) {
            throw new \Exception('Ticket Purchase Cost account (code 5001) not found. Please create it in Chart of Accounts.');
        }
        if ($tp->due_amount > 0 && !$payableAccount) {
            throw new \Exception('Accounts Payable account (code ' . config('accounts.accounts_payable') . ') not found. Please create it in Chart of Accounts.');
        }

        $journal = \App\Models\JournalEntry::create([
            'company_id'  => $tp->company_id ?? 2,
            'created_by'  => auth()->id(),
            'date'        => $tp->purchase_date,
            'reference'   => $tp->ticket_no,
            'source'      => 'ticket_purchase',
            'source_id'   => $tp->id,
            'description' => 'Direct ticket purchase — ' . $tp->ticket_no,
        ]);

        $items = [[
            'account_id' => $costAccount?->id,
            'debit'      => $tp->amount,
            'credit'     => 0,
            'note'       => 'Ticket acquired — ' . $tp->ticket_no,
        ]];

        if ($tp->paid_amount > 0) {
            if ($bankId) {
                $bank = \App\Models\Bank::find($bankId);
                if (!$bank || !$bank->account_id) throw new \Exception('Bank not linked to chart of accounts.');
                $items[] = ['account_id' => $bank->account_id, 'debit' => 0, 'credit' => $tp->paid_amount, 'note' => 'Paid via bank'];
            } elseif ($portalId) {
                $portal = \App\Models\Portal::find($portalId);
                if (!$portal || !$portal->account_id) throw new \Exception('Portal not linked to chart of accounts.');
                $items[] = ['account_id' => $portal->account_id, 'debit' => 0, 'credit' => $tp->paid_amount, 'note' => 'Paid via portal'];
            }
        }

        if ($tp->due_amount > 0) {
            $items[] = ['account_id' => $payableAccount?->id, 'debit' => 0, 'credit' => $tp->due_amount, 'note' => 'Amount owed to vendor'];
        }

        foreach ($items as $item) {
            \App\Models\JournalItem::create([...$item, 'journal_entry_id' => $journal->id]);
        }
    }

    /**
     * Update the recognition journal for a purchase.
     *
     * If settlement journals exist (make_payment was called), only update metadata —
     * never recreate items, which would corrupt the historical recognition amounts.
     */
    private function updatePurchaseJournal(TicketPurchase $tp, $bankId, $portalId)
    {
        if ($tp->status === 'cancelled') {
            // Delete recognition journal only (leave settlement journals intact for audit)
            $journal = \App\Models\JournalEntry::where('source', 'ticket_purchase')
                ->where('source_id', $tp->id)->orderBy('id')->first();
            if ($journal) { $journal->items()->delete(); $journal->delete(); }
            return;
        }

        $journalCount = \App\Models\JournalEntry::where('source', 'ticket_purchase')
            ->where('source_id', $tp->id)->count();

        $journal = \App\Models\JournalEntry::where('source', 'ticket_purchase')
            ->where('source_id', $tp->id)->orderBy('id')->first();

        // Settlement journals exist → only touch metadata, preserve item amounts
        if ($journalCount > 1) {
            if ($journal) {
                $journal->update([
                    'date'        => $tp->purchase_date,
                    'description' => 'Direct ticket purchase (edited) — ' . $tp->ticket_no,
                ]);
            }
            return;
        }

        // No settlements yet → safe to recreate items
        if ($journal) {
            $journal->items()->delete();
            $journal->update([
                'date'        => $tp->purchase_date,
                'description' => 'Direct ticket purchase (edited) — ' . $tp->ticket_no,
            ]);
        } else {
            $journal = \App\Models\JournalEntry::create([
                'company_id'  => $tp->company_id ?? 2,
                'created_by'  => auth()->id(),
                'date'        => $tp->purchase_date,
                'reference'   => $tp->ticket_no,
                'source'      => 'ticket_purchase',
                'source_id'   => $tp->id,
                'description' => 'Direct ticket purchase (edited) — ' . $tp->ticket_no,
            ]);
        }

        $costAccount    = \App\Models\Account::where('code', config('accounts.ticket_purchase_cost'))->first();
        $payableAccount = \App\Models\Account::where('code', config('accounts.accounts_payable'))->first();

        $items = [['account_id' => $costAccount?->id, 'debit' => $tp->amount, 'credit' => 0, 'note' => 'Ticket acquired']];

        if ($tp->paid_amount > 0) {
            if ($bankId) {
                $bank = \App\Models\Bank::find($bankId);
                if ($bank?->account_id) {
                    $items[] = ['account_id' => $bank->account_id, 'debit' => 0, 'credit' => $tp->paid_amount, 'note' => 'Paid via bank'];
                }
            } elseif ($portalId) {
                $portal = \App\Models\Portal::find($portalId);
                if ($portal?->account_id) {
                    $items[] = ['account_id' => $portal->account_id, 'debit' => 0, 'credit' => $tp->paid_amount, 'note' => 'Paid via portal'];
                }
            }
        }

        if ($tp->due_amount > 0) {
            $items[] = ['account_id' => $payableAccount?->id, 'debit' => 0, 'credit' => $tp->due_amount, 'note' => 'Amount owed to vendor'];
        }

        foreach ($items as $item) {
            \App\Models\JournalItem::create([...$item, 'journal_entry_id' => $journal->id]);
        }
    }

    // =========================================================================
    // JOURNAL HELPERS — Sale (recognition only)
    // =========================================================================

    private function createSaleJournal(TicketSale $ts, $bankId)
    {
        $revenueAccount    = \App\Models\Account::where('code', config('accounts.ticket_sales_revenue'))->first();
        $receivableAccount = \App\Models\Account::where('code', config('accounts.accounts_receivable'))->first();

        if (!$revenueAccount) {
            throw new \Exception('Ticket Sales Revenue account (code ' . config('accounts.ticket_sales_revenue') . ') not found. Please create it in Chart of Accounts.');
        }
        if ($ts->due_amount > 0 && !$receivableAccount) {
            throw new \Exception('Accounts Receivable account (code ' . config('accounts.accounts_receivable') . ') not found. Please create it in Chart of Accounts.');
        }

        $journal = \App\Models\JournalEntry::create([
            'company_id'  => $ts->company_id ?? 2,
            'created_by'  => auth()->id(),
            'date'        => $ts->sale_date,
            'reference'   => $ts->invoice_no,
            'source'      => 'ticket_sale',
            'source_id'   => $ts->id,
            'description' => 'Direct ticket sale — ' . $ts->invoice_no,
        ]);

        $items = [];

        if ($ts->paid_amount > 0 && $bankId) {
            $bank = \App\Models\Bank::find($bankId);
            if (!$bank || !$bank->account_id) throw new \Exception('Bank not linked to chart of accounts.');
            $items[] = ['account_id' => $bank->account_id, 'debit' => $ts->paid_amount, 'credit' => 0, 'note' => 'Cash received'];
        }

        if ($ts->due_amount > 0) {
            $items[] = ['account_id' => $receivableAccount?->id, 'debit' => $ts->due_amount, 'credit' => 0, 'note' => 'Amount due from agent'];
        }

        $items[] = ['account_id' => $revenueAccount?->id, 'debit' => 0, 'credit' => $ts->total_amount, 'note' => 'Ticket sales revenue'];

        foreach ($items as $item) {
            \App\Models\JournalItem::create([...$item, 'journal_entry_id' => $journal->id]);
        }
    }

    /**
     * Update the recognition journal for a sale.
     *
     * Same settlement-detection guard as updatePurchaseJournal.
     */
    private function updateSaleJournal(TicketSale $ts, $bankId)
    {
        if ($ts->status === 'cancelled') {
            $journal = \App\Models\JournalEntry::where('source', 'ticket_sale')
                ->where('source_id', $ts->id)->orderBy('id')->first();
            if ($journal) { $journal->items()->delete(); $journal->delete(); }
            return;
        }

        $journalCount = \App\Models\JournalEntry::where('source', 'ticket_sale')
            ->where('source_id', $ts->id)->count();

        $journal = \App\Models\JournalEntry::where('source', 'ticket_sale')
            ->where('source_id', $ts->id)->orderBy('id')->first();

        // Settlement journals exist → only touch metadata
        if ($journalCount > 1) {
            if ($journal) {
                $journal->update([
                    'date'        => $ts->sale_date,
                    'description' => 'Direct ticket sale (edited) — ' . $ts->invoice_no,
                ]);
            }
            return;
        }

        // No settlements yet → safe to recreate items
        if ($journal) {
            $journal->items()->delete();
            $journal->update([
                'date'        => $ts->sale_date,
                'description' => 'Direct ticket sale (edited) — ' . $ts->invoice_no,
            ]);
        } else {
            $journal = \App\Models\JournalEntry::create([
                'company_id'  => $ts->company_id ?? 2,
                'created_by'  => auth()->id(),
                'date'        => $ts->sale_date,
                'reference'   => $ts->invoice_no,
                'source'      => 'ticket_sale',
                'source_id'   => $ts->id,
                'description' => 'Direct ticket sale (edited) — ' . $ts->invoice_no,
            ]);
        }

        $revenueAccount    = \App\Models\Account::where('code', config('accounts.ticket_sales_revenue'))->first();
        $receivableAccount = \App\Models\Account::where('code', config('accounts.accounts_receivable'))->first();
        $items             = [];

        if ($ts->paid_amount > 0 && $bankId) {
            $bank = \App\Models\Bank::find($bankId);
            if ($bank?->account_id) {
                $items[] = ['account_id' => $bank->account_id, 'debit' => $ts->paid_amount, 'credit' => 0, 'note' => 'Cash received'];
            }
        }

        if ($ts->due_amount > 0) {
            $items[] = ['account_id' => $receivableAccount?->id, 'debit' => $ts->due_amount, 'credit' => 0, 'note' => 'Amount due from agent'];
        }

        $items[] = ['account_id' => $revenueAccount?->id, 'debit' => 0, 'credit' => $ts->total_amount, 'note' => 'Ticket sales revenue'];

        foreach ($items as $item) {
            \App\Models\JournalItem::create([...$item, 'journal_entry_id' => $journal->id]);
        }
    }

    // =========================================================================
    // JOURNAL HELPERS — Refund
    // =========================================================================

    /**
     * Accounting journal for a ticket refund.
     *
     * Portal ticket:
     *   DR  Ticket Revenue        org_sale        (reverse original sale)
     *   DR  Portal Account        airline_refund  (airline returns money to portal)
     *   CR  Ticket Purchase Cost  org_cost        (reverse original cost)
     *   CR  Bank                  net paid        (customer refund paid from bank)
     *   CR  AR                    net due         (remaining customer refund due)
     *   CR  Service Charge Income service_charge  (our fee)
     *
     * Vendor ticket (no portal):
     *   DR  Ticket Revenue        org_sale
     *   CR  Bank                  net paid
     *   CR  AR                    net due
     *   CR  Service Charge Income service_charge
     *
     * Penalty is implicitly absorbed — it causes airline_refund < org_cost and
     * net_refund < org_sale, so the entry balances without a separate penalty line.
     */
    private function createRefundJournal(TicketRefund $refund): void
    {
        $revenueAccount = \App\Models\Account::where('code', config('accounts.ticket_sales_revenue'))->first();
        $costAccount    = \App\Models\Account::where('code', config('accounts.ticket_purchase_cost'))->first();
        $arAccount      = \App\Models\Account::where('code', config('accounts.accounts_receivable'))->first();
        $scAccount      = \App\Models\Account::where('code', config('accounts.service_charge_income'))->first();

        if (!$revenueAccount) {
            throw new \Exception('Ticket Sales Revenue account (code ' . config('accounts.ticket_sales_revenue') . ') not found.');
        }

        $journal = \App\Models\JournalEntry::create([
            'company_id'  => $refund->company_id,
            'created_by'  => auth()->id(),
            'date'        => $refund->refund_date,
            'reference'   => 'REF-' . $refund->id,
            'source'      => 'ticket_refund',
            'source_id'   => $refund->id,
            'description' => 'Ticket refund — ' . $refund->original_invoice,
        ]);

        $items = [];

        // DR: Reverse original sale revenue
        $items[] = [
            'account_id' => $revenueAccount->id,
            'debit'      => $refund->org_sale,
            'credit'     => 0,
            'note'       => 'Reverse ticket sale — ' . $refund->original_invoice,
        ];

        // DR: Portal balance restored by airline (portal ticket only)
        if ($refund->portal_id && (float) $refund->airline_refund > 0) {
            $portal = Portal::find($refund->portal_id);
            if ($portal?->account_id) {
                $items[] = [
                    'account_id' => $portal->account_id,
                    'debit'      => (float) $refund->airline_refund,
                    'credit'     => 0,
                    'note'       => 'Airline refund credited to portal',
                ];
            }

            // CR: Reverse original purchase cost (portal ticket only)
            if ($costAccount) {
                $items[] = [
                    'account_id' => $costAccount->id,
                    'debit'      => 0,
                    'credit'     => (float) $refund->org_cost,
                    'note'       => 'Reverse ticket purchase cost',
                ];
            }
        }

        // CR: Bank — customer refund already paid out
        if ((float) $refund->paid_amount > 0 && $refund->bank_id) {
            $bank = \App\Models\Bank::find($refund->bank_id);
            if ($bank?->account_id) {
                $items[] = [
                    'account_id' => $bank->account_id,
                    'debit'      => 0,
                    'credit'     => (float) $refund->paid_amount,
                    'note'       => 'Customer refund paid from bank',
                ];
            }
        }

        // CR: AR — remaining refund still owed to customer
        if ((float) $refund->due_amount > 0 && $arAccount) {
            $items[] = [
                'account_id' => $arAccount->id,
                'debit'      => 0,
                'credit'     => (float) $refund->due_amount,
                'note'       => 'Customer refund due',
            ];
        }

        // CR: Service charge income
        if ((float) $refund->service_charge > 0 && $scAccount) {
            $items[] = [
                'account_id' => $scAccount->id,
                'debit'      => 0,
                'credit'     => (float) $refund->service_charge,
                'note'       => 'Service charge on refund',
            ];
        }

        foreach ($items as $item) {
            \App\Models\JournalItem::create([...$item, 'journal_entry_id' => $journal->id]);
        }
    }

    /** Incoming airline refund restores portal balance. */
    private function creditToPortal(int $portalId, int $invoiceId, float $amount): void
    {
        $portal          = Portal::findOrFail($portalId);
        $opening_balance = (float) $portal->balance;
        $new_balance     = $opening_balance + $amount;

        PortalBalance::create([
            'invoice_id'      => $invoiceId,
            'portal_id'       => $portalId,
            'type'            => 'ticket_refund',
            'old_balance'     => $opening_balance,
            'payment_method'  => 'airline_refund',
            'debit'           => $amount,
            'credit'          => 0,
            'current_balance' => $new_balance,
            'remarks'         => 'Airline refund credited back to portal.',
            'created_by'      => Auth::id(),
        ]);

        $portal->update(['balance' => $new_balance]);
    }
}
