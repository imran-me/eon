<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Models\PassportHolder;
use App\Models\Portal;
use App\Models\PortalBalance;
use App\Models\Ticket;
use App\Models\TicketPurchase;
use App\Models\Transaction;
use App\Models\User;
use App\Traits\PostsPartyLedger;
use App\Traits\PostsPurchaseJournal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TicketPurchaseController implements HasMiddleware
{
    use PostsPartyLedger, PostsPurchaseJournal;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view ticket purchase|view all ticket purchase', only: ['index']),
            new Middleware('permission:create ticket purchase', only: ['create', 'store']),
            new Middleware('permission:edit ticket purchase', only: ['edit', 'update']),
            new Middleware('permission:delete ticket purchase', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $datas = TicketPurchase::query()
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
                    return $query->where('ticket_no', 'like', '%' . $no . '%');
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

            $banks   = Bank::where('status', 1)->get();
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
            $banks   = Bank::where('status', 1)->get();
            $tickets = Ticket::where('status', 1)->get();
            $users = User::orderBy('name')->get();
            $vendors = User::orderBy('name')->role('vendor')->get();
            $portals = Portal::orderBy('name')->where('status', 'active')->get();
            $passport_holders = PassportHolder::where('status', 1)->get();

            // Optional: Generate default ticket_no
            $lastTicket = TicketPurchase::latest()->first();
            if ($lastTicket) {
                $lastNumber = (int) substr($lastTicket->ticket_no, 2);
                $ticketNo = 'TP' . str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
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

    public function manageBankPayment($request, $ticket_purchase_id, $paid_amount){

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
                'balance' => $bank_new_balance
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
                'balance' => $new_balance
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
     */
    private function resolveTicketPurchasePaymentAccountId($request): ?int
    {
        if ($request->bank_id) {
            $bank = \App\Models\Bank::find($request->bank_id);
            if (!$bank || !$bank->account_id) {
                throw new \Exception('Bank is not linked to a chart-of-accounts account.');
            }
            return $bank->account_id;
        }

        if ($request->portal_id) {
            $portal = \App\Models\Portal::find($request->portal_id);
            if (!$portal || !$portal->account_id) {
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
            'Ticket purchase — ' . $ticketPurchase->ticket_no,
            (float) $ticketPurchase->amount,
            (float) $ticketPurchase->paid_amount,
            (float) $ticketPurchase->due_amount,
            $ticketPurchase->paid_amount > 0 ? $this->resolveTicketPurchasePaymentAccountId($request) : null,
            config('accounts.ticket_purchase_cost')
        );
    }

    private function updateTicketPurchaseJournal($ticketPurchase, $request)
    {
        $this->updatePurchaseJournal(
            'ticket_purchase',
            $ticketPurchase->id,
            $ticketPurchase->company_id,
            $ticketPurchase->purchase_date,
            $ticketPurchase->ticket_no,
            'Ticket purchase (edited) — ' . $ticketPurchase->ticket_no,
            (float) $ticketPurchase->amount,
            (float) $ticketPurchase->paid_amount,
            (float) $ticketPurchase->due_amount,
            $ticketPurchase->paid_amount > 0 ? $this->resolveTicketPurchasePaymentAccountId($request) : null,
            config('accounts.ticket_purchase_cost'),
            $ticketPurchase->status === 'cancelled'
        );
    }

    private function deleteTicketPurchaseJournal($ticketPurchaseId)
    {
        $this->deletePurchaseJournal('ticket_purchase', $ticketPurchaseId);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $role)
    {
        $validator = Validator::make($request->all(), [
            'passport_holder_id' => 'required|exists:passport_holders,id',
            'ticket_id'          => 'required|exists:tickets,id',
            'vendor_id'          => 'nullable|exists:users,id',
            'portal_id'          => 'nullable|exists:portals,id',
            'bank_id'            => 'nullable|exists:banks,id',
            'ticket_type'        => 'required|in:air,bus,train,other',
            'trip_type'          => 'required|in:one-way,two-way',
            'purchase_date'      => 'required|date',
            'ticket_no'          => 'required|string|unique:ticket_purchases,ticket_no',
            'status'             => 'required|in:pending,confirm,cancelled,draft',
            'price'              => 'nullable|numeric',
            'amount'       => 'nullable|numeric',
            'currency'           => 'required|string|max:5',
            'attachment'         => 'nullable|file|mimes:pdf,jpg,jpeg,png',
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
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
                'purchase_date',
                'ticket_no',
                'status',
                'amount',
                'currency',
                'paid_amount',
                'due_amount',
                'payment_status',
            ]);

            $ticketPurchaseData['company_id'] = 2;
            $ticketPurchaseData['created_by'] = Auth::user()->id;

            // Handle main attachment if provided
            if ($request->hasFile('attachment')) {
                $ticketPurchaseData['attachment'] = $request->file('attachment')->store('ticket_attachments', 'public');
            }

            // Create TicketPurchase
            $ticketPurchase = TicketPurchase::create($ticketPurchaseData);

            // Liability recognition on the vendor's party statement — the
            // full cost owed, separate from the paid-portion credit row
            // below, so the statement shows what's owed even before
            // anything is paid.
            if ($request->vendor_id && (float) $request->amount > 0) {
                $this->reconcilePartyLedgerRow(
                    $request->vendor_id, 'ticket_purchase', $ticketPurchase->id, true, (float) $request->amount,
                    [
                        'account_id'   => null,
                        'payment_date' => $request->purchase_date,
                        'reference_no' => $request->ticket_no,
                        'remarks'      => 'Ticket purchase cost — ' . $request->ticket_no,
                    ]
                );
            }

            if ($request->bank_id && $request->paid_amount > 0) {
                $this->manageBankPayment($request, $ticketPurchase->id, (float) $request->paid_amount);
            } elseif($request->portal_id && $request->paid_amount > 0){
                $this->managePortalPayment($request, $ticketPurchase->id, (float) $request->paid_amount);
            }

            // JOURNAL start
            $this->createTicketPurchaseJournal($ticketPurchase, $request);

            if ($request->status === 'confirm') {
                if ($request->ticket_id) {
                    $ticketAcc = Ticket::find($request->ticket_id);
                    if ($ticketAcc) {
                        $ticketAcc->update([
                            'qty' => $ticketAcc->qty + 1
                        ]);
                    }
                }
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Ticket purchase created successfully!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket purchase: ' . $e->getMessage()
            ]);
        }
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

            return view('dashboard.ticket_purchases.show-v2', compact('data','company','invoiceTemplate'));
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
            $data = TicketPurchase::with('legs')->findOrFail($id);
            if(in_array($data->status, ['confirm', 'cancelled'])) {
                return redirect()->back()->withErrors(['error' => 'Can not edit anymore!!!']);
            }
            $banks   = Bank::where('status', 1)->get();
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
            'ticket_id'          => 'required|exists:tickets,id',
            'vendor_id'          => 'nullable|exists:users,id',
            'portal_id'          => 'nullable|exists:portals,id',
            'bank_id'            => 'nullable|exists:banks,id',
            'ticket_type'        => 'required|in:air,bus,train,other',
            'trip_type'          => 'required|in:one-way,two-way',
            'purchase_date'      => 'required|date',
            'ticket_no'          => 'required|string|unique:ticket_purchases,ticket_no,' . $id,
            'status'             => 'required|in:pending,confirm,cancelled,draft',
            'price'              => 'nullable|numeric',
            'amount'             => 'nullable|numeric',
            'currency'           => 'required|string|max:5',
            'attachment'         => 'nullable|file|mimes:pdf,jpg,jpeg,png',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        DB::beginTransaction();
        try {
            $ticketPurchase = TicketPurchase::findOrFail($id);

            $oldStatus = $ticketPurchase->status;
            $newStatus = $request->status;

            // Reverse old ticket qty
            if ($oldStatus === 'confirm') {
                $oldTicket = Ticket::find($ticketPurchase->ticket_id);
                if ($oldTicket && $oldTicket->qty > 0) {
                    $oldTicket->decrement('qty');
                }
            }

            $paid_amount = $request->paid_amount;
            $merge_due_amount = null;
            $merge_payment_status = null;
            $merge_paid_amount = null;

            // -------------------------------------------------------
            // 1. HANDLE PAYMENTS BASED ON STATUS
            // -------------------------------------------------------
            if ($newStatus === 'cancelled') {

                // Reverse ALL bank transactions → give money back to banks
                $bankTransactions = Transaction::where('invoice_id', $ticketPurchase->id)
                    ->where('type', 'ticket_purchase')
                    ->get();

                foreach ($bankTransactions as $transaction) {
                    $bank = Bank::find($transaction->account_id);
                    if ($bank) {
                        $bank->increment('balance', $transaction->credit);
                    }
                }
                Transaction::where('invoice_id', $ticketPurchase->id)
                    ->where('type', 'ticket_purchase')
                    ->delete();

                // Reverse ALL portal transactions → give money back to portals
                $portalTransactions = PortalBalance::where('invoice_id', $ticketPurchase->id)
                    ->where('type', 'ticket_purchase')
                    ->get();

                foreach ($portalTransactions as $portalTransaction) {
                    $portal = Portal::find($portalTransaction->portal_id);
                    if ($portal) {
                        $portal->increment('balance', $portalTransaction->credit);
                    }
                }
                PortalBalance::where('invoice_id', $ticketPurchase->id)
                    ->where('type', 'ticket_purchase')
                    ->delete();

                // Reset paid/due amounts on the invoice
                $ticketPurchase->update([
                    'paid_amount'    => 0,
                    'due_amount'     => $ticketPurchase->amount,
                    'payment_status' => 'due',
                ]);
            } else{
                $merge_due_amount = $request->due_amount;
                $merge_payment_status = $request->payment_status;
                $merge_paid_amount = $ticketPurchase->paid_amount + $paid_amount;
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
                'purchase_date',
                'ticket_no',
                'status',
                'amount',
                'currency'
            ]);

            $ticketData['updated_by'] = Auth::id();
            if ($newStatus != 'cancelled') {
                $ticketData['paid_amount']    = $merge_paid_amount;
                $ticketData['due_amount']     = $merge_due_amount;
                $ticketData['payment_status'] = $merge_payment_status;
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

            // Liability recognition on the vendor's party statement — kept
            // in sync with the (possibly changed) vendor/amount. Cancelled
            // purchases already had their debit row deleted above alongside
            // the bank/portal transactions.
            if ($newStatus !== 'cancelled' && $request->vendor_id && (float) $request->amount > 0) {
                $this->reconcilePartyLedgerRow(
                    $request->vendor_id, 'ticket_purchase', $ticketPurchase->id, true, (float) $request->amount,
                    [
                        'account_id'   => null,
                        'payment_date' => $request->purchase_date,
                        'reference_no' => $request->ticket_no,
                        'remarks'      => 'Ticket purchase cost — ' . $request->ticket_no,
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

            if ($newStatus === 'confirm') {
                // Increment new ticket qty
                $newTicket = Ticket::find($request->ticket_id);
                if ($newTicket) {
                    $newTicket->increment('qty');
                }
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Ticket purchase updated successfully!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket purchase: ' . $e->getMessage()
            ]);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $role, $id)
    {
        try {
            $ticketPurchase = TicketPurchase::findOrFail($request->item_id);
            if (in_array($ticketPurchase->status, ['confirm'])) {
                return redirect()->back()->withErrors(['error' => 'Can not delete anymore!!!']);
            }
            DB::beginTransaction();

            // -------------------------------------------------------
            // 1. REVERSE BANK TRANSACTION (if one exists)
            // -------------------------------------------------------
            $oldTransaction = Transaction::where('invoice_id', $ticketPurchase->id)->where('type', 'ticket_purchase')->latest()->first();
            if ($oldTransaction) {
                $oldBank = Bank::find($oldTransaction->account_id);
                if ($oldBank) {
                    $oldBank->update([
                        'balance' => $oldBank->balance + $oldTransaction->credit
                    ]);
                }
                Transaction::where('invoice_id', $ticketPurchase->id)->where('type', 'ticket_purchase')->delete();
            }

            // -------------------------------------------------------
            // 1. REVERSE PORTAL TRANSACTION (if one exists)
            // -------------------------------------------------------
            $oldPorTrans = PortalBalance::where('invoice_id', $ticketPurchase->id)->where('type', 'ticket_purchase')->latest()->first();
            if ($oldPorTrans) {
                $oldPortal = Portal::find($oldPorTrans->portal_id);
                if ($oldPortal) {
                    $oldPortal->update([
                        'balance' => $oldPortal->balance + $oldPorTrans->credit
                    ]);
                }
                PortalBalance::where('invoice_id', $ticketPurchase->id)->where('type', 'ticket_purchase')->delete();
            }

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
                'message' => 'Ticket purchase deleted successfully!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ticket purchase: ' . $e->getMessage()
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

            $ticketPurchase = TicketPurchase::findOrFail($id);

            if ($validated['payment_amount'] > $ticketPurchase->due_amount) {
                return redirect()->back()->with('error', 'Payment amount cannot exceed due amount!');
            }

            DB::transaction(function () use ($validated, $ticketPurchase) {

                $acc = Bank::find($validated['bank_id']);
                $opening_balance = (float) ($acc?->balance ?? 0);

                // `old_balance`/`balance` are the vendor's own running
                // party-ledger balance, not the bank's — bank balance is
                // tracked separately below.
                $partyId = $ticketPurchase->vendor_id;
                $party_old_balance = $partyId
                    ? (float) (Transaction::where('user_id', $partyId)->orderByDesc('id')->value('balance') ?? 0)
                    : 0.0;
                $party_new_balance = $party_old_balance - $validated['payment_amount'];

                Transaction::create([
                    'user_id'        => $partyId,
                    'user_type'      => 'supplier',
                    'type'           => 'ticket_purchase',
                    'account_id'     => $validated['bank_id'],
                    'payment_date'   => $validated['payment_date'],
                    'reference_no'   => $validated['reference_no'],
                    'payment_method' => $validated['payment_method'],
                    'invoice_id'     => $ticketPurchase->id,
                    'old_balance'    => $party_old_balance,
                    'debit'          => 0,
                    'credit'         => $validated['payment_amount'],
                    'balance'        => $party_new_balance,
                    'remarks'        => 'Ticket purchase due payment (make_payment).',
                ]);

                if ($acc) {
                    $acc->update(['balance' => $opening_balance - $validated['payment_amount']]);
                }

                $newPaid = min($ticketPurchase->paid_amount + $validated['payment_amount'], $ticketPurchase->amount);
                $newDue  = max($ticketPurchase->due_amount - $validated['payment_amount'], 0);

                $ticketPurchase->update([
                    'paid_amount'    => $newPaid,
                    'due_amount'     => $newDue,
                    'payment_status' => $newDue <= 0 ? 'paid' : 'partial',
                ]);

                // ── JOURNAL: Debit AP 2110 / Credit Bank ─────────────────────
                $bank = Bank::find($validated['bank_id']);
                if (!$bank || !$bank->account_id) {
                    throw new \Exception('Bank is not linked to a chart-of-accounts account.');
                }

                $this->postPurchaseJournalPayment(
                    'ticket_purchase',
                    $ticketPurchase->id,
                    auth()->user()->company_id ?? 2,
                    $validated['payment_date'],
                    $validated['reference_no'] ?? $ticketPurchase->ticket_no,
                    'Ticket purchase payment made — ' . $ticketPurchase->ticket_no,
                    (float) $validated['payment_amount'],
                    $bank->account_id
                );
                // ── END JOURNAL ───────────────────────────────────────────────

                if (!empty($validated['schedule_id'])) {
                    \App\Models\PaymentSchedule::where('id', $validated['schedule_id'])
                        ->where('schedulable_type', \App\Models\TicketPurchase::class)
                        ->where('schedulable_id', $ticketPurchase->id)
                        ->update(['status' => 'paid', 'paid_date' => $validated['payment_date']]);
                }
            });
        } catch (\Throwable $th) {
            DB::rollBack();
            return redirect()->back()->with('error', $th->getMessage());
        }

        return redirect()->back()->with('success', 'Payment of ৳' . number_format($validated['payment_amount'], 2) . ' sent successfully!');
    }
}
