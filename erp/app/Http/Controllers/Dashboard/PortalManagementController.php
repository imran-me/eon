<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Portal;
use App\Models\PortalBalance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class PortalManagementController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view portal|view all portal', only: ['index', 'journal']),
            new Middleware('permission:create portal', only: ['create', 'store']),
            new Middleware('permission:edit portal', only: ['edit', 'update']),
            new Middleware('permission:delete portal', only: ['destroy']),
        ];
    }

    public function index()
    {
        try {
            $portals = Portal::with('vendor')
                ->withSum(['balanceLogs as total_added' => fn($q) => $q->where('type', 'opening_balance')], 'debit')
                ->withSum(['balanceLogs as total_used' => fn($q) => $q->whereIn('type', ['ticket_purchase', 'opening_usage'])], 'credit')
                ->latest()
                ->paginate(10);

            $accounts = Account::active()->get();

            return view('dashboard.portal_management.index', compact('portals', 'accounts'));
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to fetch portal management data.']);
        }
    }

    public function journal($role, $id)
    {
        try {
            $portal = Portal::with('account')->findOrFail($id);
            $logs = PortalBalance::with(['sourceAccount', 'createdBy', 'journalEntry'])
                ->where('portal_id', $id)
                ->latest()
                ->paginate(15);

            $totalAdded = PortalBalance::where('portal_id', $id)->where('type', 'opening_balance')->sum('debit');
            $grossUsed  = PortalBalance::where('portal_id', $id)->whereIn('type', ['ticket_purchase', 'opening_usage'])->sum('credit');
            $totalRepaid = PortalBalance::where('portal_id', $id)->where('type', 'add_balance')->sum('debit');
            $totalUsed  = max(0, $grossUsed - $totalRepaid);

            return view('dashboard.portal_management.journal', compact('portal', 'logs', 'totalAdded', 'totalUsed'));
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to load portal journal.']);
        }
    }

    public function updateBalance(Request $request)
    {
        $txnType        = $request->input('txn_type', 'add_balance');
        $isUsage        = ($txnType === 'opening_usage');
        $isOpening      = ($txnType === 'opening_balance');
        $requiresSource = ($txnType === 'add_balance');

        $rules = [
            'portal_id' => 'required|exists:portals,id',
            'txn_type'  => 'required|in:add_balance,opening_balance,opening_usage',
            'amount'    => 'required|numeric|min:0.01',
            'date'      => 'required|date',
            'reference' => 'nullable|string|max:100',
            'remarks'   => 'nullable|string|max:255',
        ];

        if ($requiresSource) {
            $rules['source_account_id'] = 'required|exists:accounts,id';
            $rules['payment_method']    = 'required|string';
        }

        $request->validate($rules);

        DB::beginTransaction();
        try {
            $portal      = Portal::findOrFail($request->portal_id);
            $amount      = (float) $request->amount;
            $oldBalance  = (float) $portal->balance;

            // Usage reduces balance; funding increases balance
            $newBalance  = $isUsage ? ($oldBalance - $amount) : ($oldBalance + $amount);

            $journal = null;
            if ($portal->account_id) {
                $descriptions = [
                    'add_balance'     => 'Regular top-up: ' . $portal->name,
                    'opening_balance' => 'Opening balance entry: ' . $portal->name,
                    'opening_usage'   => 'Opening usage entry: ' . $portal->name,
                ];

                $journal = JournalEntry::create([
                    'date'        => $request->date,
                    'company_id'   => 2,
                    'reference'   => $request->reference ?? 'PRT-' . strtoupper($txnType) . '-' . now()->format('YmdHis'),
                    'source'      => 'portal_balance',
                    'source_id'   => $portal->id,
                    'description' => $descriptions[$txnType],
                    'created_by'  => Auth::id(),
                ]);

                if ($isUsage) {
                    // Opening usage: DR Expense (ticket cost), CR Portal Account
                    $expenseAccount = Account::where('code', config('accounts.ticket_purchase_cost'))->first();
                    JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id'       => $expenseAccount?->id ?? $portal->account_id,
                        'debit'            => $amount,
                        'credit'           => 0,
                        'note'             => 'Opening usage — pre-software ticket purchases',
                    ]);
                    JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id'       => $portal->account_id,
                        'debit'            => 0,
                        'credit'           => $amount,
                        'note'             => 'Portal balance deducted for opening usage',
                    ]);
                } elseif ($isOpening) {
                    // Opening balance: DR Portal Account, CR Opening Balance Equity
                    $obEquity = Account::firstOrCreate(
                        ['code' => config('accounts.opening_balance_equity')],
                        ['name' => 'Opening Balance Equity', 'type' => 'equity', 'status' => true]
                    );
                    JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id'       => $portal->account_id,
                        'debit'            => $amount,
                        'credit'           => 0,
                        'note'             => 'Opening balance — portal',
                    ]);
                    JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id'       => $obEquity->id,
                        'debit'            => 0,
                        'credit'           => $amount,
                        'note'             => 'Opening balance equity offset',
                    ]);
                } else {
                    // Regular top-up: DR Portal Account, CR Source Account
                    JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id'       => $portal->account_id,
                        'debit'            => $amount,
                        'credit'           => 0,
                        'note'             => 'Portal top-up',
                    ]);
                    JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id'       => $request->source_account_id,
                        'debit'            => 0,
                        'credit'           => $amount,
                        'note'             => 'Paid via ' . ($request->payment_method ?? ''),
                    ]);
                }
            }

            PortalBalance::create([
                'portal_id'         => $portal->id,
                'date'              => $request->date,
                'reference'         => $request->reference,
                'journal_entry_id'  => $journal?->id,
                'source_account_id' => $requiresSource ? $request->source_account_id : null,
                'type'              => $txnType,
                'old_balance'       => $oldBalance,
                'debit'             => $isUsage ? 0 : $amount,
                'credit'            => $isUsage ? $amount : 0,
                'current_balance'   => $newBalance,
                'payment_method'    => $requiresSource ? $request->payment_method : null,
                'remarks'           => $request->remarks ?? ($isUsage ? 'Opening usage entry.' : 'Balance added from portal management.'),
                'created_by'        => Auth::id(),
            ]);

            $portal->update(['balance' => $newBalance]);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Something went wrong! ' . $th->getMessage());
        }

        $messages = [
            'add_balance'     => 'Balance added successfully!',
            'opening_balance' => 'Opening balance recorded successfully!',
            'opening_usage'   => 'Opening usage recorded successfully!',
        ];

        return redirect()->back()->with('success', $messages[$txnType]);
    }

    public function create()
    {
        try {
            $accounts = Account::active()->get();
            $vendors  = User::whereHas('roles', fn($q) => $q->where('name', 'vendor'))->get();
            return view('dashboard.portal_management.create', compact('vendors', 'accounts'));
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to load create form.']);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|unique:portals,name',
                'type' => 'required|in:flight,bus,train',
            ]);

            Portal::create([
                'name'       => $request->name,
                'api_key'    => $request->api_key,
                'api_secret' => $request->api_secret,
                'base_url'   => $request->base_url,
                'type'       => $request->type,
                'account_id' => $request->account_id,
                'status'     => $request->status ?? 'active',
                'created_by' => Auth::id(),
            ]);

            return redirect()->back()->with('success', 'Portal created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to create portal.']);
        }
    }

    public function show(string $id)
    {
        //
    }

    public function edit($role, string $id)
    {
        try {
            $vendors  = User::whereHas('roles', fn($q) => $q->where('name', 'vendor'))->get();
            $accounts = Account::active()->get();
            $portal   = Portal::findOrFail($id);
            return view('dashboard.portal_management.edit', compact('portal', 'vendors', 'accounts'));
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to load edit form.']);
        }
    }

    public function update(Request $request, $role, string $id)
    {
        try {
            $request->validate([
                'name' => 'required|unique:portals,name,' . $id,
                'type' => 'required|in:flight,bus,train',
            ]);

            $portal = Portal::findOrFail($id);
            $portal->update($request->only(['name', 'api_key', 'api_secret', 'base_url', 'account_id', 'type', 'status']));

            return redirect()->back()->with('success', 'Portal updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update portal.']);
        }
    }

    public function updatePaymentInfo(Request $request)
    {
        $request->validate([
            'portal_id'           => 'required|exists:portals,id',
            'next_payment_date'   => 'required|date',
            'next_payment_amount' => 'required|numeric|min:0',
        ]);

        try {
            Portal::findOrFail($request->portal_id)->update([
                'next_payment_date'   => $request->next_payment_date,
                'next_payment_amount' => $request->next_payment_amount,
            ]);
            return redirect()->back()->with('success', 'Payment info updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update payment info.');
        }
    }

    public function destroy($_role, string $id)
    {
        try {
            Portal::findOrFail($id)->delete();
            return redirect()->back()->with('success', 'Portal deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete portal.']);
        }
    }
}
