<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Models\Ticket;
use App\Models\TicketPurchase;
use App\Models\TicketSale;
use App\Models\TicketSaleItem;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TicketSaleController extends Controller
{
     /**
     * Display a listing of the resource.
     */

    public function index(Request $request)
    {        
        $data['users'] = User::get();
        $data['agents'] = User::role('agent')->get();
        $data['tickets'] = Ticket::where('status', 1)->get();

        $query = TicketSale::with(['client', 'ticket', 'item.ticketPurchase', 'items.ticketPurchase.passportHolder']);

        // Filter by Invoice Number
        $query->when($request->filled('invoice_no'), function ($q) use ($request) {
            $q->where('invoice_no', 'like', "%{$request->invoice_no}%");
        });

        // Filter by Client ID (agent/vendor or customer)
        $query->when($request->filled('agent_id'), function ($q) use ($request) {
            $q->where('client_id', $request->agent_id);
        });

        // Filter by Sale Date 
        $query->when($request->filled('sale_date'), function ($q) use ($request) {
            $q->whereDate('sale_date', $request->sale_date);
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

        return response()->json([
            'success' => true,
            'message' => 'Ticket sales retrieved successfully.',
            'data' => $data
        ]);
    }

    public function create(Request $request, $role)
    {        
        $agents = User::role('agent')->get();
        $banks   = Bank::where('status', 1)->get();        

        // Tickets not yet sold (and ideally already 'confirm')
        $tickets = TicketPurchase::distinct()
            ->whereNotIn('id', function ($q) {
                $q->select('ticket_purchase_id')->from('ticket_sale_items');
            })
            ->where('status', 'confirm')
            ->with(['passportHolder', 'ticket']) 
            ->latest()
            ->get()
            ->unique('ticket_id');

        // Generate next invoice number like INV000001
        $last = TicketSale::latest('id')->first();
        $next = $last ? $last->id + 1 : 1;
        $invoiceNo = 'INV' . str_pad((string)$next, 6, '0', STR_PAD_LEFT);

        // return view('dashboard.ticket-sell.create', compact('agents', 'tickets', 'invoiceNo'));
        return response()->json([
            'success' => true,
            'message' => 'Data for creating ticket sale retrieved successfully.',
            'data' => [
                'agents' => $agents,
                'banks' => $banks,
                'tickets' => $tickets,
                'invoice_no' => $invoiceNo
            ]
        ]);
    }

    public function manageBankPayment($request, $ticket_sale_id, $paid_amount)
    {

        $acc = Bank::find($request->bank_id);
        $opening_balance = (float) $acc->balance;
        $new_balance = $opening_balance + $paid_amount;

        Transaction::create([
            'user_id' => $request->agent_id,
            'user_type' => 'customer',
            'type' => 'ticket_sale',
            'account_id' => $request->bank_id,
            'payment_date' => $request->sale_date ?? now(),
            'reference_no' => $request->reference_no,
            'payment_method' => $request->payment_method,
            'invoice_id' => $ticket_sale_id,
            'old_balance' => $opening_balance,
            'debit' => $paid_amount,
            'credit' => 0,
            'balance' => $new_balance,
            'remarks' => 'Ticket sale with bank from sale.',
        ]);

        if ($acc) {
            $acc->update([
                'balance' => $new_balance
            ]);
        }
    }

    public function reverseBankPayment($ticketSale)
    {
        $transactions = Transaction::where('invoice_id', $ticketSale->id)
            ->where('type', 'ticket_sale')
            ->get();

        if ($transactions->isEmpty()) return;

        foreach ($transactions as $transaction) {
            $acc = Bank::find($transaction->bank_id); 
            if ($acc) {
                $acc->decrement('balance', (float) $transaction->amount); 
            }
        }

        Transaction::where('invoice_id', $ticketSale->id)
            ->where('type', 'ticket_sale')
            ->delete();
    }

    // ── JOURNAL HELPERS ───────────────────────────────────────

    private function createTicketSaleJournal($ticketSale, $request)
    {
        $revenueAccount    = \App\Models\Account::where('code', config('accounts.ticket_sales_revenue'))->firstOrFail();
        $receivableAccount = \App\Models\Account::where('code', config('accounts.accounts_receivable'))->firstOrFail();

        $journal = \App\Models\JournalEntry::create([
            'company_id'  => $ticketSale->company_id,
            'created_by'  => auth()->id(),
            'date'        => $ticketSale->sale_date,
            'reference'   => $ticketSale->invoice_no,
            'source'      => 'ticket_sale',
            'source_id'   => $ticketSale->id,
            'description' => 'Ticket sale — ' . $ticketSale->invoice_no,
        ]);

        $journalItems = [];

        // Debit: Cash/Bank — only if payment received
        if ($ticketSale->paid_amount > 0 && $request->bank_id) {
            $bank = \App\Models\Bank::find($request->bank_id);
            if (!$bank || !$bank->account_id) {
                throw new \Exception('Bank is not linked to a chart-of-accounts account.');
            }
            $journalItems[] = [
                'account_id' => $bank->account_id,
                'debit'      => $ticketSale->paid_amount,
                'credit'     => 0,
                'note'       => 'Cash received — ' . $ticketSale->invoice_no,
            ];
        }

        // Debit: Accounts Receivable — only if due exists
        if ($ticketSale->due_amount > 0) {
            $journalItems[] = [
                'account_id' => $receivableAccount->id,
                'debit'      => $ticketSale->due_amount,
                'credit'     => 0,
                'note'       => 'Amount due — ' . $ticketSale->invoice_no,
            ];
        }

        // Credit: Ticket Sales Revenue — always, full total
        $journalItems[] = [
            'account_id' => $revenueAccount->id,
            'debit'      => 0,
            'credit'     => $ticketSale->total_amount,
            'note'       => 'Ticket sales revenue — ' . $ticketSale->invoice_no,
        ];

        foreach ($journalItems as $item) {
            \App\Models\JournalItem::create([...$item, 'journal_entry_id' => $journal->id]);
        }
    }

    private function updateTicketSaleJournal($ticketSale, $request)
    {
        $journal = \App\Models\JournalEntry::where('source', 'ticket_sale')
            ->where('source_id', $ticketSale->id)
            ->first();

        // ── Cancelled: delete journal entirely ────────────────
        if ($ticketSale->status === 'cancelled') {
            if ($journal) {
                $journal->items()->delete();
                $journal->delete();
            }
            return; // stop here, no need to recreate
        }
        // ── rest of method unchanged from before ──────────────

        $revenueAccount    = \App\Models\Account::where('code', config('accounts.ticket_sales_revenue'))->firstOrFail();
        $receivableAccount = \App\Models\Account::where('code', config('accounts.accounts_receivable'))->firstOrFail();

        if ($journal) {
            $journal->items()->delete();
            $journal->update([
                'date'        => $ticketSale->sale_date,
                'description' => 'Ticket sale (edited) — ' . $ticketSale->invoice_no,
            ]);
        } else {
            $journal = \App\Models\JournalEntry::create([
                'company_id'  => $ticketSale->company_id,
                'created_by'  => auth()->id(),
                'date'        => $ticketSale->sale_date,
                'reference'   => $ticketSale->invoice_no,
                'source'      => 'ticket_sale',
                'source_id'   => $ticketSale->id,
                'description' => 'Ticket sale (edited) — ' . $ticketSale->invoice_no,
            ]);
        }

        $journalItems = [];

        if ($ticketSale->paid_amount > 0 && $request->bank_id) {
            $bank = \App\Models\Bank::find($request->bank_id);
            if (!$bank || !$bank->account_id) {
                throw new \Exception('Bank is not linked to a chart-of-accounts account.');
            }
            $journalItems[] = [
                'account_id' => $bank->account_id,
                'debit'      => $ticketSale->paid_amount,
                'credit'     => 0,
                'note'       => 'Cash received — ' . $ticketSale->invoice_no,
            ];
        }

        if ($ticketSale->due_amount > 0) {
            $journalItems[] = [
                'account_id' => $receivableAccount->id,
                'debit'      => $ticketSale->due_amount,
                'credit'     => 0,
                'note'       => 'Amount due — ' . $ticketSale->invoice_no,
            ];
        }

        $journalItems[] = [
            'account_id' => $revenueAccount->id,
            'debit'      => 0,
            'credit'     => $ticketSale->total_amount,
            'note'       => 'Ticket sales revenue — ' . $ticketSale->invoice_no,
        ];

        foreach ($journalItems as $item) {
            \App\Models\JournalItem::create([...$item, 'journal_entry_id' => $journal->id]);
        }
    }

    private function deleteTicketSaleJournal($ticketSaleId)
    {
        $journal = \App\Models\JournalEntry::where('source', 'ticket_sale')
            ->where('source_id', $ticketSaleId)
            ->first();
        if ($journal) {
            $journal->items()->forceDelete();
            $journal->forceDelete();
        }
    }

    public function store(Request $request, $role)
    {
        $validator = Validator::make($request->all(), [            
            'agent_id'           => 'required|exists:users,id',
            'bank_id'            => 'nullable|exists:banks,id',
            'status'             => 'required|in:pending,confirm,cancelled,draft',
            'currency'           => 'required|string|max:5',
            'sale_date'          => 'required|date',
            'invoice_no'         => 'required|string|unique:ticket_sales,invoice_no',
            'ticket_id'          => 'required|exists:tickets,id',
            'ticket_purchase_id' => 'required|exists:ticket_purchases,id',
            'price'              => 'required|numeric',
            'paid_amount'        => 'nullable|numeric',
            'payment_method'     => 'required',
            'payment_status'     => 'required',
            'due_amount'         => 'nullable|numeric',
            'total_amount'       => 'required|numeric',
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        // Check already sold ones
        $alreadySold = TicketSaleItem::where('ticket_purchase_id', $request->ticket_id)->pluck('ticket_purchase_id')->toArray();
        if (!empty($alreadySold)) {
            return response()->json([
                'success' => false,
                'message' => 'Some tickets are already sold: ' . implode(', ', $alreadySold)
            ]);
        }

        // Optional: only allow selling confirm tickets
        $notconfirm = TicketPurchase::where('ticket_id', $request->ticket_id)->where('status', 'confirm')->pluck('ticket_no')->toArray();
        if (empty($notconfirm)) {
            return response()->json([
                'success' => false,
                'message' => 'Only confirm tickets can be sold. Not confirm: ' . implode(', ', $notconfirm)
            ]);
        }

        DB::beginTransaction();
        try {
            // Prepare main ticket purchase data
            $requestData = $request->only([
                'agent_id',
                'bank_id',
                'status',
                'currency',
                'sale_date',
                'invoice_no',
                'ticket_id',
                'paid_amount',
                'payment_status',
                'due_amount',
                'total_amount'
            ]);

            $requestData['company_id'] = 2;
            $requestData['created_by'] = Auth::user()->id;

            // Create TicketSale
            $ticketSale = TicketSale::create($requestData);

            TicketSaleItem::updateOrCreate([                
                'ticket_purchase_id' => $request->ticket_purchase_id,    
                'ticket_sale_id' => $ticketSale->id,    
            ],[
                'price' => $request->price,
            ]);

            if ($request->bank_id && $request->paid_amount > 0) {
                $this->manageBankPayment($request, $ticketSale->id, (float) $request->paid_amount);
            }

            // ── JOURNAL (auto) ────────────────────────────────────────
            $this->createTicketSaleJournal($ticketSale, $request);
            // ── END JOURNAL ───────────────────────────────────────────

            if ($request->status === 'confirm') {
                if ($request->ticket_id) {
                    $ticketAcc = Ticket::find($request->ticket_id);
                    if ($ticketAcc) {
                        $ticketAcc->update([
                            'qty' => $ticketAcc->qty - 1,
                            'total_sale_qty' => $ticketAcc->total_sale_qty + 1,
                            'total_sale_amount' => $ticketAcc->total_sale_amount + $request->paid_amount,
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
            $data = TicketSale::with('ticket', 'agent', 'item', 'item.ticketPurchase', 'item.ticketPurchase.passportHolder', 'transactions.account')->findOrFail($id);
            $company = Company::findOrFail($data->company_id);
            $invoiceTemplate = InvoiceTemplate::with('fields', 'style')->where('type', 'ticket_sale')->where('is_default', 1)->first();

            return view('dashboard.ticket_sales.show-v2', compact('data', 'company', 'invoiceTemplate'));
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to load ticket purchase data.']);
        }
    }


    public function edit($role, TicketSale $ticketSale)
    {
        $agents = User::role('agent')->get();
        $banks   = Bank::where('status', 1)->get();

        // Tickets not yet sold (and ideally already 'confirm')
        $tickets = TicketPurchase::distinct()
            ->whereNotIn('id', function ($q) {
                $q->select('ticket_purchase_id')->from('ticket_sale_items');
            })
            ->where('status', 'confirm')
            ->with(['passportHolder', 'ticket'])
            ->latest()
            ->get();

        // But also include tickets already linked to this sale
        $tickets = $tickets->merge($ticketSale->items->map->ticketPurchase)->unique('ticket_id');

        return view('dashboard.ticket_sales.edit', compact('ticketSale', 'agents', 'banks', 'tickets'));
    }

    public function update(Request $request, $role, TicketSale $ticketSale)
    {
        if (!$ticketSale) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket sale not found.'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'agent_id'           => 'required|exists:users,id',
            'bank_id'            => 'nullable|exists:banks,id',
            'status'             => 'required|in:pending,confirm,cancelled,draft',
            'currency'           => 'required|string|max:5',
            'sale_date'          => 'required|date',
            'invoice_no'         => 'required|string|unique:ticket_sales,invoice_no,' . $ticketSale->id,
            'ticket_id'          => 'required|exists:tickets,id',
            'ticket_purchase_id' => 'required|exists:ticket_purchases,id',
            'price'              => 'required|numeric',
            'paid_amount'        => 'nullable|numeric',
            'payment_method'     => 'required',
            'payment_status'     => 'required',
            'due_amount'         => 'nullable|numeric',
            'total_amount'       => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        // Check if ticket is already sold by another sale (excluding current sale)
        $alreadySold = TicketSaleItem::where('ticket_purchase_id', $request->ticket_id)
            ->where('ticket_sale_id', '!=', $ticketSale->id)
            ->pluck('ticket_purchase_id')
            ->toArray();

        if (!empty($alreadySold)) {
            return response()->json([
                'success' => false,
                'message' => 'Some tickets are already sold: ' . implode(', ', $alreadySold)
            ]);
        }

        // Only allow selling confirmed tickets
        $notconfirm = TicketPurchase::where('ticket_id', $request->ticket_id)
            ->where('status', 'confirm')
            ->pluck('ticket_no')
            ->toArray();

        if (empty($notconfirm)) {
            return response()->json([
                'success' => false,
                'message' => 'Only confirm tickets can be sold. Not confirm: ' . implode(', ', $notconfirm)
            ]);
        }

        DB::beginTransaction();
        try {
            $oldStatus     = $ticketSale->status;
            $oldPaidAmount = (float) $ticketSale->paid_amount;
            $oldTicketId   = $ticketSale->ticket_id;
            $newStatus     = $request->status;
            $newPaidAmount = (float) $request->paid_amount;

            // -------------------------------------------------------
            // 1. HANDLE PAYMENTS BASED ON STATUS
            // -------------------------------------------------------
            $merge_paid_amount    = null;
            $merge_due_amount     = null;
            $merge_payment_status = null;

            if ($newStatus === 'cancelled') {

                // Reverse ALL bank transactions → refund balance back to bank
                $bankTransactions = Transaction::where('invoice_id', $ticketSale->id)
                    ->where('type', 'ticket_sale')
                    ->get();

                foreach ($bankTransactions as $transaction) {
                    $bank = Bank::find($transaction->account_id);
                    if ($bank) {
                        $bank->increment('balance', $transaction->debit); // sales debit the bank, so reverse with increment
                    }
                }
                Transaction::where('invoice_id', $ticketSale->id)
                    ->where('type', 'ticket_sale')
                    ->delete();

                // Reset paid/due amounts on the sale record
                $ticketSale->update([
                    'paid_amount'    => 0,
                    'due_amount'     => $ticketSale->total_amount,
                    'payment_status' => 'due',
                ]);
            } else {
                // Accumulate paid amount on top of what was already paid
                $merge_paid_amount    = $oldPaidAmount + $newPaidAmount;
                $merge_due_amount     = $request->due_amount;
                $merge_payment_status = $request->payment_status;
            }

            // -------------------------------------------------------
            // 2. PREPARE MAIN DATA
            // -------------------------------------------------------
            $requestData = $request->only([
                'agent_id',
                'bank_id',
                'status',
                'currency',
                'sale_date',
                'invoice_no',
                'ticket_id',
                'total_amount',
            ]);

            $requestData['updated_by'] = Auth::id();

            if ($newStatus !== 'cancelled') {
                $requestData['paid_amount']    = $merge_paid_amount;
                $requestData['due_amount']     = $merge_due_amount;
                $requestData['payment_status'] = $merge_payment_status;
            }

            // -------------------------------------------------------
            // 3. UPDATE THE MAIN RECORD
            // -------------------------------------------------------
            $ticketSale->update($requestData);

            // -------------------------------------------------------
            // 4. UPDATE THE SALE ITEM
            // -------------------------------------------------------
            TicketSaleItem::updateOrCreate(
                [
                    'ticket_purchase_id' => $request->ticket_purchase_id,
                    'ticket_sale_id'     => $ticketSale->id,
                ],
                [
                    'price' => $request->price,
                ]
            );

            // -------------------------------------------------------
            // 5. APPLY NEW BANK PAYMENT (only if a new payment is made)
            // -------------------------------------------------------
            if ($request->bank_id && $newPaidAmount > 0 && $newStatus !== 'cancelled') {
                $this->manageBankPayment($request, $ticketSale->id, $newPaidAmount);
            }

            // -------------------------------------------------------
            // JOURNAL UPDATE ← ADD HERE
            // -------------------------------------------------------
            $this->updateTicketSaleJournal($ticketSale, $request);

            // -------------------------------------------------------
            // 6. MANAGE TICKET QTY
            // -------------------------------------------------------

            // Revert qty changes on old ticket if status was previously confirm
            if ($oldStatus === 'confirm') {
                $oldTicket = Ticket::find($oldTicketId);
                if ($oldTicket) {
                    $oldTicket->update([
                        'qty'               => $oldTicket->qty + 1,
                        'total_sale_qty'    => $oldTicket->total_sale_qty - 1,
                        'total_sale_amount' => $oldTicket->total_sale_amount - $oldPaidAmount,
                    ]);
                }
            }

            // Apply qty changes if new status is confirm
            if ($newStatus === 'confirm') {
                $ticketAcc = Ticket::find($request->ticket_id);
                if ($ticketAcc) {
                    $ticketAcc->update([
                        'qty'               => $ticketAcc->qty - 1,
                        'total_sale_qty'    => $ticketAcc->total_sale_qty + 1,
                        'total_sale_amount' => $ticketAcc->total_sale_amount + $merge_paid_amount,
                    ]);
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

            // Reverse any bank payments linked to this sale
            if ($ticketSale->paid_amount > 0) {
                $this->reverseBankPayment($ticketSale);
            }

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
}
