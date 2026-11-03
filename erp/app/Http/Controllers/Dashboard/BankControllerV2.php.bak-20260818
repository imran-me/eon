<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Bank;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\PassportHolderCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BankControllerV2 extends Controller
{
    /**
     * Ids of entries that have been reversed, memoised for the request.
     *
     * The recent-activity feed is rebuilt once per company, so without this the
     * same lookup would run a dozen times to answer one question: may this row's
     * text still be edited?
     *
     * @var \Illuminate\Support\Collection|null
     */
    private $reversedEntryIds = null;

    private function reversedEntryIds()
    {
        if ($this->reversedEntryIds === null) {
            $this->reversedEntryIds = JournalEntry::whereNotNull('reversed_journal_entry_id')
                ->pluck('reversed_journal_entry_id')
                ->flip();
        }

        return $this->reversedEntryIds;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Users without "view all bank" (e.g. a single-company manager/accountant)
        // are locked to their own company's banks, regardless of what's requested.
        $userCompanyId = auth()->user()->company_id;
        $canViewAllBanks = auth()->user()->can('view all bank');

        $query = Bank::select('banks.*')
            ->selectSub(function ($q) {
                $q->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0)')
                    ->from('journal_items')
                    ->whereColumn('journal_items.account_id', 'banks.account_id')
                    ->whereNull('journal_items.deleted_at');
            }, 'journal_balance')
            ->orderBy('banks.name', 'asc');

        if (!$canViewAllBanks && !empty($userCompanyId)) {
            $query->where('banks.company_id', $userCompanyId);
        }

        if ($request->filled('name')) {
            $query->where('banks.name', $request->name);
        }

        if ($request->filled('status')) {
            $query->whereDate('banks.status', $request->status);
        }

        $datas = $query->paginate(20);

        $accounts = Account::active()->where('type','asset')->get();
        $companies = (!$canViewAllBanks && !empty($userCompanyId))
            ? \App\Models\Company::where('id', $userCompanyId)->get()
            : \App\Models\Company::all();

        return view('banks.index', compact(
            'datas',
            'accounts',
            'companies'
        ));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('banks.create-modal');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'company_id' => 'required|exists:companies,id',
            'branch_name' => 'nullable|string|max:255',
            'account_id' => 'required',
            'account_name' => 'required|string|max:255',
            'account_type' => 'nullable|in:savings,current,fixed',
            'bank_type' => 'nullable|in:national,international',
            'type' => 'required|in:bank,cash,mobile_banking,digital_wallet',
            'routing_number' => 'nullable|string|max:20',
            'account_number' => 'nullable|string|max:20|unique:banks,account_number',
            'iban' => 'nullable|string|max:34',
            'swift_code' => 'nullable|string|max:11',
            // 'currency' => 'required|string|max:10',
            'address' => 'nullable|string|max:255',
            'balance' => 'required|numeric|min:0',
        ]);

        // If validation fails
        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ]);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            $data = Bank::create([
                'name' => $request->name,
                'company_id' => $request->company_id,
                'branch_name' => $request->branch_name,
                'account_id' => $request->account_id,
                'account_name' => $request->account_name,
                'account_type' => $request->account_type ?: 'savings',
                'bank_type' => $request->bank_type ?: 'national',
                'type' => $request->type,
                'routing_number' => $request->routing_number,
                'account_number' => $request->account_number,
                'iban' => $request->iban,
                'swift_code' => $request->swift_code,
                'currency' => 'BDT', // Set default currency to BDT
                'address' => $request->address,
                'balance' => $request->balance,
                'last_transaction_date' => $request->last_transaction_date,
                'last_transaction_amount' => $request->last_transaction_amount,
                'last_transaction_type' => $request->last_transaction_type,
                'created_by' => auth()->id(),
                'status' => $request->status ? 1 : 0
            ]);

            // Use the picked ledger account as-is when that is safe, which is what
            // update() has always done. Auto-creating a child unconditionally is
            // what produced the duplicate pair "1113 Office Cash" (real, posted to)
            // and "ACC-36 OFFICE CASH" (empty), indistinguishable in the dropdown.
            //
            // Two cases are still not safe to link directly, and there we keep
            // nesting a bank-specific child underneath the picked account:
            //   - a group/parent account: posting straight to it double-counts
            //     against its own children in any rollup (this is exactly how
            //     "Cash In Hand" ended up holding transactions of its own);
            //   - an account already backing another bank: buildBankStatementRows()
            //     keys purely on account_id, so the two banks would render each
            //     other's transactions and balances.
            $picked = Account::find($request->account_id);
            $pickedIsParent = $picked && Account::where('parent_id', $picked->id)->exists();
            $pickedInUse = Bank::where('account_id', $request->account_id)
                ->where('id', '!=', $data->id)
                ->exists();

            if ($picked && ! $pickedIsParent && ! $pickedInUse) {
                $data->account_id = $picked->id;
            } else {
                $account  = new Account();
                $account->name = $request->name;
                // Cash/mobile-banking accounts often have no account number — fall
                // back to a code derived from the bank's own id, which is always
                // unique, instead of an empty string colliding on the second one.
                $account->code = $request->account_number ? substr($request->account_number, -4) : ('ACC-' . $data->id);
                $account->type = 'asset';
                $account->parent_id = $request->account_id;
                // Bank sub-accounts belong to one specific company (unlike the
                // shared/global chart-of-accounts entries), matching the bank
                // record itself — otherwise this account would default to
                // "shared" and leak into every other company's reports.
                $account->company_id = $data->company_id;
                $account->save();

                $data->account_id = $account->id;
            }

            $data->save();

            if ($data && $request->balance > 0) {
                $equityAccount = Account::where('code', config('accounts.opening_balance_equity'))->first();

                if (!$equityAccount) {
                    throw new \Exception('Equity account not found for journal offset.');
                }
                $journal = JournalEntry::create([
                    'company_id' => $data->company_id,
                    'created_by' => auth()->id(),
                    'date' => now(),
                    'reference' => 'OPENING-BANK-' . $data->name,
                    'source' => 'bank',
                    'source_id' => $data->id,
                    'description' => 'Bank Opening Balance'
                ]);

                JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $account->id,
                    'debit' => $request->balance,
                    'credit' => 0,
                ]);

                JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $equityAccount->id,
                    'debit' => 0,
                    'credit' => $request->balance,
                ]);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $th->getMessage(),
                ]);
            }

            return redirect()->back()->with('error', $th->getMessage())->withInput();
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data created successfully.',
                'data' => $data
            ]);
        }

        return redirect()->route('role.banks.index')->with('success', 'Data created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return view('banks.edit-modal', compact('id'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $id = $request->id;
        $data = Bank::findOrFail($id);
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!'
            ]);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'company_id' => 'required|exists:companies,id',
            'branch_name' => 'nullable|string|max:255',
            'account_id' => ['required', 'exists:accounts,id', Rule::unique('banks', 'account_id')->ignore($data->id)],
            'account_name' => 'required|string|max:255',
            'account_type' => 'nullable|in:savings,current,fixed',
            'bank_type' => 'nullable|in:national,international',
            'type' => 'required|in:bank,cash,mobile_banking,digital_wallet',
            'routing_number' => 'nullable|string|max:20',
            'account_number' => 'nullable|string|max:20|unique:banks,account_number,' . $data->id,
            'iban' => 'nullable|string|max:34',
            'swift_code' => 'nullable|string|max:11',
            'currency' => 'required|string|max:10',
            'address' => 'nullable|string|max:255',
            'balance' => 'required|numeric|min:0',
        ]);

        // If validation fails
        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ]);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            $oldBalance = $data->balance;
            $oldAccountId = $data->account_id;

            $data->update([
                'name' => $request->name,
                'company_id' => $request->company_id,
                'branch_name' => $request->branch_name,
                'account_id' => $request->account_id,
                'account_name' => $request->account_name,
                'account_type' => $request->account_type ?: $data->account_type,
                'bank_type' => $request->bank_type ?: $data->bank_type,
                'type' => $request->type,
                'routing_number' => $request->routing_number,
                'account_number' => $request->account_number,
                'iban' => $request->iban,
                'swift_code' => $request->swift_code,
                'currency' => $request->currency,
                'address' => $request->address,
                'balance' => $request->balance,
                'last_transaction_date' => $request->last_transaction_date,
                'last_transaction_amount' => $request->last_transaction_amount,
                'last_transaction_type' => $request->last_transaction_type,
                'updated_by' => auth()->id(),
                'status' => $request->status ? 1 : 0
            ]);

            // The Ledger Account was changed to a different account (e.g. to
            // fix a broken link to a soft-deleted account) — each linked
            // account belongs to exactly one bank, so move this bank's whole
            // journal history over instead of leaving it split across two
            // accounts or orphaned on the old one.
            //
            // That "whole history" is a bulk move keyed on account_id alone,
            // because a journal item records the ACCOUNT it hit, never the bank.
            // On a dedicated bank account the two mean the same thing. On a
            // SHARED one they do not: 1013 Office Cash carries every company's
            // cash expense and every petty cash movement as well as one company's
            // cash-drawer bank, and there is nothing in the data saying which of
            // those rows "belongs to" the bank. Moving them all would hand seven
            // other companies' cash history to whatever account was picked.
            //
            // So refuse rather than guess. Failing here rolls the whole update
            // back and tells the user why; the alternative is a silent, correct-
            // looking save that quietly rewrites other companies' books.
            if ($oldAccountId && $oldAccountId != $data->account_id) {
                $otherCompanies = JournalEntry::query()
                    ->whereIn('id', JournalItem::query()
                        ->where('account_id', $oldAccountId)
                        ->whereNull('deleted_at')
                        ->select('journal_entry_id'))
                    ->where('company_id', '!=', $data->company_id)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($otherCompanies) {
                    $old = Account::find($oldAccountId);

                    throw new \RuntimeException(
                        'Cannot move the history off [' . ($old->code ?? $oldAccountId) . '] ' . ($old->name ?? 'that account')
                        . ' — other companies have posted to it too, and a journal item records the account it hit, not the bank. '
                        . 'Moving them would take their entries with it. Point this bank at a different account without relinking '
                        . 'the history, or have the entries reassigned individually.'
                    );
                }

                JournalItem::where('account_id', $oldAccountId)->update(['account_id' => $data->account_id]);
            }

            // Keep the linked account's company in sync with the bank's own
            // company, whether the link just changed or the bank's company
            // itself was edited — otherwise the account could stay "shared"
            // or point at the wrong company and leak into other reports.
            //
            // NEVER when another company has already posted to that account.
            // Some accounts are shared on purpose and a bank happens to point at
            // one: 1013 Office Cash is the credit side of EVERY company's cash
            // expense and every petty cash issue/return, and it is also the
            // ledger account behind one company's "OFFICE CASH" bank record.
            // Stamping it would hand a group-wide account to that single company,
            // and from that moment every OTHER company's trial balance silently
            // stops balancing — trialBalanceV1() filters ACCOUNTS by company but
            // JOURNAL ITEMS by entry, so their debits still render while their
            // credits disappear with the account. Nothing errors; the books just
            // stop adding up. That is exactly what the 1113 → 1013 renumber did,
            // and re-doing it is one save of the Bank edit form away.
            //
            // Asking the ledger rather than the chart on purpose: "is this
            // account shared?" is a guess, "has another company posted here?" is
            // the fact that decides whether claiming it breaks anything.
            if ($data->account_id) {
                $postedByAnotherCompany = JournalItem::query()
                    ->where('journal_items.account_id', $data->account_id)
                    ->whereNull('journal_items.deleted_at')
                    ->whereHas('journalEntry', fn ($q) => $q
                        ->where('company_id', '!=', $data->company_id)
                        ->whereNull('deleted_at'))
                    ->exists();

                if (! $postedByAnotherCompany) {
                    Account::where('id', $data->account_id)->update(['company_id' => $data->company_id]);
                }
            }

            // Adjust opening balance journal if balance changed
            if ((float) $request->balance !== (float) $oldBalance) {
                $journal = JournalEntry::where('source', 'bank')
                    ->where('source_id', $data->id)
                    ->where('reference', 'like', 'OPENING-BANK-%')
                    ->first();

                if ($journal) {
                    // Update existing opening balance journal items
                    JournalItem::where('journal_entry_id', $journal->id)
                        ->where('account_id', $data->account_id)
                        ->update(['debit' => $request->balance, 'credit' => 0]);

                    $equityAccount = Account::where('code', config('accounts.opening_balance_equity'))->first();
                    if ($equityAccount) {
                        JournalItem::where('journal_entry_id', $journal->id)
                            ->where('account_id', $equityAccount->id)
                            ->update(['debit' => 0, 'credit' => $request->balance]);
                    }
                } elseif ((float) $request->balance > 0) {
                    // No existing journal — create opening entry now
                    $equityAccount = Account::where('code', config('accounts.opening_balance_equity'))->first();
                    if (!$equityAccount) {
                        throw new \Exception('Equity account (3140) not found for journal offset.');
                    }
                    $journal = JournalEntry::create([
                        'company_id' => $data->company_id,
                        'created_by' => auth()->id(),
                        'date' => now(),
                        'reference' => 'OPENING-BANK-' . $data->name,
                        'source' => 'bank',
                        'source_id' => $data->id,
                        'description' => 'Bank Opening Balance'
                    ]);
                    JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id' => $data->account_id,
                        'debit' => $request->balance,
                        'credit' => 0,
                    ]);
                    JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id' => $equityAccount->id,
                        'debit' => 0,
                        'credit' => $request->balance,
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $th->getMessage(),
                ]);
            }

            return redirect()->back()->with('error', $th->getMessage())->withInput();
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data updated successfully.',
                'data' => $data
            ]);
        }

        return redirect()->back()->with('success', 'Data updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        try {
            $item = Bank::find($request->item_id);
            if ($item) {
                $item->delete();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!'
                ]);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data deleted successfully.'
        ]);
    }

    public function getEmployeeSalary(Request $request)
    {
        try {
            $data = Bank::where('user_id', $request->user_id)->orderBy('id', 'ASC')->get();
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'data' => $data,
            'success' => true,
            'message' => 'Data Found Successfully.'
        ]);
    }

    /**
     * Manage Bank Accounts dashboard — cards per account + combined recent transactions.
     */
    public function dashboard(Request $request)
    {
        return view('banks.dashboard', $this->dashboardPayload());
    }

    /**
     * The dashboard's own figures, as JSON.
     *
     * Exists so recording a Cash In / Cash Out / reversal can refresh the page
     * in place instead of reloading it. The company banner, the bank cards and
     * the recent-activity feed are all computed here and nowhere else, which is
     * why the old code simply called window.location.reload() — and why filing
     * five cash entries meant five full page loads, each one dropping the user
     * back at the top with the ledger closed.
     *
     * Deliberately the SAME payload the page is first rendered from, not a
     * hand-rolled subset: a client that patches balances arithmetically drifts
     * from what a reload would have shown, and the 14-day net-change figure
     * cannot be derived on the client at all.
     */
    public function dashboardData(Request $request)
    {
        return response()->json([
            'companies' => $this->dashboardPayload()['companiesData'],
        ]);
    }

    /** Everything banks/dashboard.blade.php renders from. */
    private function dashboardPayload(): array
    {
        // Same company-lock as index(): without "view all bank", a user only
        // ever sees their own company's banks — no cross-company overview and
        // no "Epal Group" umbrella, since both would leak other companies' data.
        $userCompanyId = auth()->user()->company_id;
        $canViewAllBanks = auth()->user()->can('view all bank');

        $banksQuery = Bank::where('status', true)
            ->with('company')
            ->select('banks.*')
            ->selectSub(function ($q) {
                $q->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0)')
                    ->from('journal_items')
                    ->whereColumn('journal_items.account_id', 'banks.account_id')
                    ->whereNull('journal_items.deleted_at');
            }, 'journal_balance')
            ->orderBy('name');

        if (!$canViewAllBanks && !empty($userCompanyId)) {
            $banksQuery->where('company_id', $userCompanyId);
        }

        $banks = $banksQuery->get();

        $totalBalance = $banks->sum('journal_balance');
        $accountIds   = $banks->pluck('account_id')->filter()->values();

        $bankGroups = $banks->groupBy(fn ($bank) => $bank->company->name ?? 'Unassigned')->sortKeys();

        $companies = \App\Models\Company::whereIn('id', $banks->pluck('company_id')->filter()->unique())
            ->orderBy('name')
            ->get(['id', 'name']);

        $accounts = Account::active()->orderBy('name')->get(['id', 'name', 'code']);

        // Running balance per account, computed chronologically across each account's
        // full history so "balance after" is correct even though the recent-activity
        // feed below only displays the last 14 days.
        $chronological = JournalItem::with('journalEntry')
            ->whereIn('account_id', $accountIds)
            ->get()
            ->sortBy(fn($item) => $item->journalEntry->date . '-' . str_pad($item->id, 10, '0', STR_PAD_LEFT))
            ->values();

        $runningBalance = [];
        foreach ($chronological as $item) {
            $runningBalance[$item->account_id] = ($runningBalance[$item->account_id] ?? 0) + $item->debit - $item->credit;
            $item->balance_after = $runningBalance[$item->account_id];
        }

        $recentTransactions = $chronological
            ->filter(fn($item) => $item->journalEntry->date >= now()->subDays(14)->toDateString())
            ->sortByDesc(fn($item) => $item->journalEntry->date . '-' . str_pad($item->id, 10, '0', STR_PAD_LEFT))
            ->take(50)
            ->values();

        // Per-company slice of the same recent-activity feed, plus each company's
        // own totals, so the dashboard can switch "Recent Bank Transactions" and
        // the banner stats to a single company without another round trip.
        $companiesData = $bankGroups->map(
            fn($companyBanks, $companyName) => $this->buildCompanyDashboardData($companyName, $companyBanks, $chronological, $recentTransactions)
        );

        // "Epal Group" doubles as an umbrella view of every company's banks
        // grouped underneath it — display-only, there's no real parent/child
        // relationship between companies in the data. Every other company card
        // still shows just its own accounts, unchanged. Matched case-insensitively
        // since the real company name's casing varies across environments (e.g.
        // "EPAL GROUP" in production vs "Epal Group" in seeded/dev data).
        $epalGroupKey = $companiesData->keys()->first(fn ($name) => strcasecmp($name, 'Epal Group') === 0);
        if ($epalGroupKey !== null) {
            $epalGroupId = $companiesData[$epalGroupKey]['company_id'];
            $umbrella = $this->buildCompanyDashboardData($epalGroupKey, $banks, $chronological, $recentTransactions, $bankGroups);
            $umbrella['company_id'] = $epalGroupId;
            $companiesData[$epalGroupKey] = $umbrella;
        }

        $companiesData = $companiesData->values();

        return compact('banks', 'bankGroups', 'companies', 'accounts', 'totalBalance', 'recentTransactions', 'companiesData');
    }

    /**
     * Everything the dashboard needs to render one company's card, bank grid,
     * and recent-activity panel. Reused as-is for a single company, and again
     * (scoped to every bank in the system) to build the "Epal Group" umbrella.
     *
     * @param \Illuminate\Support\Collection $scopeBanks banks to include in this view
     * @param \Illuminate\Support\Collection $chronological ALL journal items, oldest first, with ->balance_after pre-computed per account
     * @param \Illuminate\Support\Collection $recentTransactions last-14-days slice of $chronological
     * @param \Illuminate\Support\Collection|null $sections when set, tags each bank card with which real company it belongs to (used for the umbrella view's grouped grid)
     */
    private function buildCompanyDashboardData(
        string $displayName,
        \Illuminate\Support\Collection $scopeBanks,
        \Illuminate\Support\Collection $chronological,
        \Illuminate\Support\Collection $recentTransactions,
        ?\Illuminate\Support\Collection $sections = null
    ): array {
        $accountIds = $scopeBanks->pluck('account_id')->filter()->values();

        // A single running balance across every account in scope combined (not
        // per individual bank) — walked chronologically over the full history
        // so it lands on the true current total, matching the banner stat.
        $scopeRunningBalance = 0;
        $scopeBalanceByItemId = [];
        foreach ($chronological as $item) {
            if (!$accountIds->contains($item->account_id)) {
                continue;
            }
            $scopeRunningBalance += $item->debit - $item->credit;
            $scopeBalanceByItemId[$item->id] = $scopeRunningBalance;
        }

        $inScope = $recentTransactions->filter(fn($item) => $accountIds->contains($item->account_id));

        // Worked out BEFORE the pot's rows are hidden below, and that order is the
        // whole point: topping the pot up is an internal move — office cash down,
        // petty cash up, the same amount — so across the group it nets to nothing.
        // Drop one leg from the arithmetic and the banner would report the group
        // losing ৳35,100 it never lost.
        $netChange = (float) ($inScope->sum('debit') - $inScope->sum('credit'));

        // The petty cash pot has its own desk now — issues, spending, statements,
        // per-custodian balances — so repeating its movements on a page about banks
        // was noise. Its card and balance stay; only the feed rows go.
        //
        // Resolved from config/accounts.php rather than matched on the bank's name,
        // so pointing the pool at another account moves this with it. Resolving to
        // nothing leaves the feed exactly as it was.
        $pettyPotAccountId = $this->pettyCashPotAccountId();

        $transactions = $inScope
            ->when(
                $pettyPotAccountId,
                fn ($items) => $items->reject(fn ($item) => (int) $item->account_id === $pettyPotAccountId)
            )
            ->map(function ($item) use ($scopeBanks, $scopeBalanceByItemId) {
                $bank = $scopeBanks->firstWhere('account_id', $item->account_id);
                // balance_after is that bank's own running balance, already computed
                // chronologically above (per-account, not scope-wide).
                $bankBalanceAfter  = (float) $item->balance_after;
                $bankBalanceBefore = $bankBalanceAfter - (float) $item->debit + (float) $item->credit;

                $entry = $item->journalEntry;

                return [
                    'date'                  => $entry->date->format('Y-m-d'),
                    'recorded_at'           => optional($entry->created_at)->format('Y-m-d H:i:s'),
                    'description'           => $entry->description,
                    // entry_id and note drive the description pencil. edit_scope
                    // mirrors what updateRemarks() will actually do, so the
                    // dialog can say which of the two lines it is about to
                    // change. Every row is editable; only the scope differs.
                    'entry_id'              => $entry->id,
                    'note'                  => $item->note,
                    'edit_scope'            => ($entry->source === 'bank_transaction'
                                                && ! $entry->reversed_journal_entry_id
                                                && ! $this->reversedEntryIds()->has($entry->id))
                                                    ? 'description' : 'note',
                    'debit'                 => (float) $item->debit,
                    'credit'                => (float) $item->credit,
                    'company_balance_after' => (float) ($scopeBalanceByItemId[$item->id] ?? 0),
                    'bank_balance_before'   => $bankBalanceBefore,
                    'bank_balance_after'    => $bankBalanceAfter,
                    'bank'                  => $bank ? $this->bankCardJson($bank) : null,
                     ];
            })
            ->values();

        $bankCards = $scopeBanks->map(function ($bank) use ($sections) {
            $card = $this->bankCardJson($bank) + [
                'branch_name'           => $bank->branch_name,
                'status'                => (bool) $bank->status,
                'last_transaction_date' => $bank->last_transaction_date,
            ];
            if ($sections) {
                $card['section'] = optional($bank->company)->name ?? 'Unassigned';
            }
            return $card;
        })->values();

        return [
            'company_id'    => optional($scopeBanks->first())->company_id,
            'name'          => $displayName,
            'total_balance' => (float) $scopeBanks->sum('journal_balance'),
            'account_count' => $scopeBanks->count(),
            'net_change'    => $netChange,
            'transactions'  => $transactions,
            'banks'         => $bankCards,
        ];
    }

    /**
     * The chart account the petty cash pot posts to, or null if it is not set up.
     *
     * Memoised because buildCompanyDashboardData() runs once per company and this
     * would otherwise be the same lookup thirteen times on one page load. Null on
     * any failure, which leaves the transaction feed untouched — a missing account
     * must not be the reason the bank dashboard changes what it shows.
     */
    private ?int $pettyCashPotAccountIdCache = null;
    private bool $pettyCashPotAccountResolved = false;

    private function pettyCashPotAccountId(): ?int
    {
        if ($this->pettyCashPotAccountResolved) {
            return $this->pettyCashPotAccountIdCache;
        }

        $this->pettyCashPotAccountResolved = true;

        try {
            $code = config('accounts.petty_cash_pool');

            $this->pettyCashPotAccountIdCache = $code
                ? Account::where('code', $code)->value('id')
                : null;
        } catch (\Throwable $e) {
            $this->pettyCashPotAccountIdCache = null;
        }

        return $this->pettyCashPotAccountIdCache;
    }

    /**
     * Live search box behind the dashboard's bank picker — mirrors
     * PartyStatementController::search().
     */
    public function search(Request $request)
    {
        $q = $request->q;

        $query = Bank::where('status', true)
            ->with('company')
            ->select('banks.*')
            ->selectSub(function ($sub) {
                $sub->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0)')
                    ->from('journal_items')
                    ->whereColumn('journal_items.account_id', 'banks.account_id')
                    ->whereNull('journal_items.deleted_at');
            }, 'journal_balance');

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('account_name', 'like', "%{$q}%")
                    ->orWhere('account_number', 'like', "%{$q}%");
            });
        }

        return response()->json(
            $query->orderBy('name')->limit(30)->get()->map(fn($bank) => $this->bankCardJson($bank))
        );
    }

    /**
     * JSON ledger load behind the dashboard's inline statement panel — mirrors
     * PartyStatementController::load(), built on the same row math as statement().
     */
    public function load(Request $request)
    {
        $request->validate(['bank_id' => 'required|exists:banks,id']);

        $bank = Bank::with('company')->findOrFail($request->bank_id);

        [$rows, $openingBalance, $totalDebit, $totalCredit, $closingBalance] = $this->buildBankStatementRows(
            $bank, $request->input('from'), $request->input('to')
        );

        return response()->json([
            'bank' => [
                'id'             => $bank->id,
                'account_id'     => $bank->account_id,
                'name'           => $bank->name,
                'account_name'   => $bank->account_name,
                'account_number' => $bank->account_number,
                'branch_name'    => $bank->branch_name,
                'type'           => $bank->type,
                'account_type'   => $bank->account_type,
                'company_name'   => optional($bank->company)->name,
                'initials'       => $this->initials($bank->name),
            ],
            'opening_balance' => (float) $openingBalance,
            'total_debit'     => $totalDebit,
            'total_credit'    => $totalCredit,
            'closing_balance' => (float) $closingBalance,
            'date_from'       => $request->input('from'),
            'date_to'         => $request->input('to'),
            'transactions'    => $rows->values(),
        ]);
    }

    private function initials(string $name): string
    {
        $words = array_slice(explode(' ', $name), 0, 2);
        return strtoupper(implode('', array_map(fn($w) => $w[0] ?? '', $words)));
    }

    /**
     * The JSON shape used everywhere a bank gets passed into JS — the search
     * dropdown, "open this bank's ledger" row/card clicks, etc.
     */
    private function bankCardJson(Bank $bank): array
    {
        return [
            'id'             => $bank->id,
            'account_id'     => $bank->account_id,
            'name'           => $bank->name,
            'account_name'   => $bank->account_name,
            'account_number' => $bank->account_number,
            'type'           => $bank->type,
            'company_name'   => optional($bank->company)->name,
            'balance'        => (float) $bank->journal_balance,
            'initials'       => $this->initials($bank->name),
        ];
    }

    /**
     * Running-balance statement for a single bank account (same math as
     * ReportController::accountLedgerV1, scoped to this bank's linked account).
     */
    public function statement($role, Bank $bank, Request $request)
    {
        [$rows, $openingBalance, $totalDebit, $totalCredit, $closingBalance] = $this->buildBankStatementRows(
            $bank,
            $request->input('date_from'),
            $request->input('date_to')
        );

        return view('banks.statement', compact(
            'bank',
            'rows',
            'openingBalance',
            'closingBalance',
            'totalDebit',
            'totalCredit'
        ));
    }

    /**
     * Printable, letterhead-styled version of the statement — same layout family
     * as party-statement.print-statement / visa-sales.voucher.
     */
    public function printStatement($role, Bank $bank, Request $request)
    {
        [$rows, $openingBalance, $totalDebit, $totalCredit, $closingBalance] = $this->buildBankStatementRows(
            $bank,
            $request->input('date_from'),
            $request->input('date_to')
        );

        $company  = \App\Models\Company::find($bank->company_id) ?? \App\Models\Company::first();
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        return view('banks.print-statement', compact(
            'bank',
            'company',
            'rows',
            'openingBalance',
            'closingBalance',
            'totalDebit',
            'totalCredit',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Running-balance rows for a bank's statement — shared by the interactive
     * statement() view and the printable printStatement() view.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: float, 2: float, 3: float, 4: float}
     */
    private function buildBankStatementRows(Bank $bank, ?string $dateFrom, ?string $dateTo): array
    {
        $openingBalance = 0;
        $rows           = collect();

        if ($bank->account_id) {
            if ($dateFrom) {
                $beforeItems = JournalItem::where('account_id', $bank->account_id)
                    ->whereHas('journalEntry', fn($q) => $q->whereDate('date', '<', $dateFrom))
                    ->get();

                $openingBalance = $beforeItems->sum('debit') - $beforeItems->sum('credit');
            }

            $items = JournalItem::with('journalEntry')
                ->where('account_id', $bank->account_id)
                ->whereHas('journalEntry', function ($q) use ($dateFrom, $dateTo) {
                    if ($dateFrom) {
                        $q->whereDate('date', '>=', $dateFrom);
                    }
                    if ($dateTo) {
                        $q->whereDate('date', '<=', $dateTo);
                    }
                })
                ->orderBy(
                    JournalEntry::select('date')
                        ->whereColumn('journal_entries.id', 'journal_items.journal_entry_id')
                        ->limit(1)
                )
                ->orderBy('id')
                ->get();

            $entryIds = $items->pluck('journal_entry_id')->unique()->values();

            // Entries that already have a reversal posted against them.
            $reversedEntryIds = JournalEntry::whereIn('reversed_journal_entry_id', $entryIds)
                ->pluck('reversed_journal_entry_id')
                ->flip();

            $runningBalance = $openingBalance;
            $rows = $items->map(function ($item) use (&$runningBalance, $reversedEntryIds) {
                $runningBalance += $item->debit - $item->credit;
                $entry       = $item->journalEntry;
                $isReversal  = (bool) $entry->reversed_journal_entry_id;
                $isReversed  = $reversedEntryIds->has($entry->id);

                return [
                    'entry_id'    => $entry->id,
                    // Y-m-d string rather than the Carbon cast: the ledger table
                    // prints it verbatim, so no browser timezone parsing can
                    // shift the day. recorded_at is when the entry was written —
                    // journal_entries.date is a DATE column with no clock time.
                    'date'        => optional($entry->date)->format('Y-m-d'),
                    'recorded_at' => optional($entry->created_at)->format('Y-m-d H:i:s'),
                    'reference'   => $entry->reference,
                    'source'      => $entry->source,
                    'description' => $entry->description,
                    'note'        => $item->note,
                    'debit'       => $item->debit,
                    'credit'      => $item->credit,
                    'balance'     => $runningBalance,
                    'is_reversal' => $isReversal,
                    'is_reversed' => $isReversed,
                    // Which line the pencil edits — see updateRemarks().
                    'edit_scope'  => (! $isReversal && ! $isReversed && $entry->source === 'bank_transaction')
                                        ? 'description' : 'note',
                    'can_reverse' => $entry->source === 'bank_transaction' && !$isReversal && !$isReversed,
                ];
            });
        }

        $totalDebit     = (float) $rows->sum('debit');
        $totalCredit    = (float) $rows->sum('credit');
        $closingBalance = $rows->isNotEmpty() ? $rows->last()['balance'] : $openingBalance;

        return [$rows, $openingBalance, $totalDebit, $totalCredit, $closingBalance];
    }

    /**
     * Record a manual deposit/withdrawal against a bank account — journal-integrated,
     * mirrors PortalManagementController::updateBalance()'s shape.
     */
    public function recordTransaction(Request $request, $role, Bank $bank)
    {
        $validator = Validator::make($request->all(), [
            'type'               => 'required|in:deposit,withdraw',
            'amount'             => 'required|numeric|min:0.01',
            'date'               => 'required|date',
            'contra_account_id'  => 'required|exists:accounts,id',
            'reference'          => 'nullable|string|max:100',
            'remarks'            => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        if ((int) $request->contra_account_id === (int) $bank->account_id) {
            return response()->json([
                'success' => false,
                'message' => 'Contra account cannot be this bank\'s own account — the two ledger legs would cancel out and no real transfer would be recorded.',
            ]);
        }

        if (!$bank->account_id) {
            return response()->json([
                'success' => false,
                'message' => "Bank '{$bank->name}' is not linked to a chart-of-accounts account.",
            ]);
        }

        $isDeposit = $request->type === 'deposit';

        // Money may not leave an account that does not have it — and BOTH legs
        // have to be checked, because either one can be the side losing money:
        //
        //   Withdraw : Cr this bank      -> this bank is the source
        //   Cash In  : Cr the contra     -> the CONTRA is the source
        //
        // Guarding only the first is what let Office Cash go to -100.00 on
        // 2026-08-13: a ৳35,100 "Cash In" was recorded on the PETTY CASH (GROUP)
        // card with Office Cash as the contra, and Office Cash held ৳35,000. The
        // card being topped up was never short, so nothing objected.
        $amount = (float) $request->amount;

        $source = $isDeposit
            ? ['id' => (int) $request->contra_account_id, 'name' => optional(Account::find($request->contra_account_id))->name ?: 'the contra account']
            : ['id' => (int) $bank->account_id,          'name' => $bank->name];

        // Only guard pots that can actually be emptied — see isMoneyHoldingAccount().
        if (Bank::isMoneyHoldingAccount($source['id'])) {
            $available = Bank::ledgerBalanceForAccount($source['id']);

            if ($available < $amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient balance in ' . $source['name'] . '. Available: ' . number_format($available, 2),
                ]);
            }
        }

        DB::transaction(function () use ($request, $bank, $isDeposit) {
            $journal = JournalEntry::create([
                'company_id'  => $bank->company_id ?? 2,
                'created_by'  => Auth::id(),
                'date'        => $request->date,
                'reference'   => $request->reference ?: ('BANK-' . strtoupper($request->type) . '-' . now()->format('YmdHis')),
                'source'      => 'bank_transaction',
                'source_id'   => $bank->id,
                'description' => ($isDeposit ? 'Deposit to ' : 'Withdrawal from ') . $bank->name . ($request->remarks ? ' — ' . $request->remarks : ''),
            ]);

            JournalItem::create([
                'journal_entry_id' => $journal->id,
                'account_id'       => $bank->account_id,
                'debit'            => $isDeposit ? $request->amount : 0,
                'credit'           => $isDeposit ? 0 : $request->amount,
                'note'             => $request->remarks,
            ]);

            JournalItem::create([
                'journal_entry_id' => $journal->id,
                'account_id'       => $request->contra_account_id,
                'debit'            => $isDeposit ? 0 : $request->amount,
                'credit'           => $isDeposit ? $request->amount : 0,
                'note'             => $request->remarks,
            ]);

            $bank->update([
                'balance'                 => $bank->balance + ($isDeposit ? $request->amount : -$request->amount),
                'last_transaction_date'   => $request->date,
                'last_transaction_amount' => $request->amount,
                'last_transaction_type'   => $isDeposit ? 'credit' : 'debit',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => ucfirst($request->type) . ' recorded successfully.',
        ]);
    }

    /**
     * Post an equal-and-opposite journal entry against a mistaken bank
     * transaction, instead of deleting it — keeps the mistake and its
     * correction both visible in the audit trail. Only entries created
     * through the Deposit/Withdraw flow (source = bank_transaction) are
     * eligible: reversing a sale/payment-sourced entry here would leave the
     * originating invoice/sale record out of sync with the ledger.
     */
    public function reverseTransaction(Request $request, $role, JournalEntry $journalEntry)
    {
        $journalEntry->load('items');

        if ($journalEntry->source !== 'bank_transaction') {
            return response()->json([
                'success' => false,
                'message' => 'Only deposit/withdraw transactions can be reversed here.',
            ]);
        }

        if ($journalEntry->reversed_journal_entry_id) {
            return response()->json([
                'success' => false,
                'message' => 'This entry is itself a reversal — reverse the original transaction instead.',
            ]);
        }

        if (JournalEntry::where('reversed_journal_entry_id', $journalEntry->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This transaction has already been reversed.',
            ]);
        }

        if ($journalEntry->items->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'This entry has no line items to reverse.']);
        }

        DB::transaction(function () use ($journalEntry) {
            $reversal = JournalEntry::create([
                'company_id'                => $journalEntry->company_id,
                'created_by'                => Auth::id(),
                'date'                      => now()->toDateString(),
                'reference'                 => 'REV-' . $journalEntry->reference,
                'source'                    => $journalEntry->source,
                'source_id'                 => $journalEntry->source_id,
                'description'               => 'Reversal of: ' . $journalEntry->description,
                'reversed_journal_entry_id' => $journalEntry->id,
            ]);

            foreach ($journalEntry->items as $item) {
                JournalItem::create([
                    'journal_entry_id' => $reversal->id,
                    'account_id'       => $item->account_id,
                    'debit'            => $item->credit,
                    'credit'           => $item->debit,
                    'note'             => 'Reversal — ' . ($item->note ?: $journalEntry->description),
                    'party_type'       => $item->party_type,
                    'party_id'         => $item->party_id,
                ]);

                // Keep the denormalized bank.balance cache in sync for any bank
                // account touched by this entry.
                $bank = Bank::where('account_id', $item->account_id)->first();
                if ($bank) {
                    $netEffect = $item->credit - $item->debit;
                    $bank->update([
                        'balance'                 => $bank->balance + $netEffect,
                        'last_transaction_date'   => now()->toDateString(),
                        'last_transaction_amount' => abs($netEffect),
                        'last_transaction_type'   => $netEffect >= 0 ? 'credit' : 'debit',
                    ]);
                }
            }
        });

        return response()->json(['success' => true, 'message' => 'Transaction reversed successfully.']);
    }

    /**
     * Edit the human part of a bank transaction's text.
     *
     * Only the remark is editable. The description is REBUILT here from the bank
     * and the direction using the same expression recordTransaction() uses, so
     * the generated half ("Deposit to X") can never drift from what the entry
     * actually does — the caller can only change what follows the em-dash.
     *
     * Nothing numeric is touched. Amounts are double-entry values that every
     * running balance is derived from, and the audit trail for a wrong figure is
     * a reversal, not a silent edit.
     *
     * Gated exactly like the Reverse button: an entry that has been reversed is
     * refused, because the reversal's own description embeds a copy of this text
     * and editing it afterwards would leave the pair contradicting each other.
     */
    public function updateRemarks(Request $request, $role, JournalEntry $journalEntry)
    {
        $journalEntry->load('items');

        $validated = $request->validate([
            'remarks' => 'nullable|string|max:255',
        ]);
        $remarks = trim((string) ($validated['remarks'] ?? ''));

        // Two scopes, decided here rather than trusted from the client.
        //
        // 'description' — our own deposit/withdraw entry, not reversed. We know
        // the formula that produced its text, so the remark can be swapped and
        // the description rebuilt around it.
        //
        // 'note' — everything else: entries owned by another module (party
        // payments, transfers), reversals whose text quotes the original, and
        // opening balances. Their descriptions are derived from records this
        // endpoint does not own, so only the note is written. Nothing computes
        // from a note, and no module regenerates it.
        $canRecompose = $journalEntry->source === 'bank_transaction'
            && ! $journalEntry->reversed_journal_entry_id
            && ! JournalEntry::where('reversed_journal_entry_id', $journalEntry->id)->exists();

        $bank      = $canRecompose ? Bank::find($journalEntry->source_id) : null;
        $bankItem  = $bank ? $journalEntry->items->firstWhere('account_id', $bank->account_id) : null;
        // Without the bank or its line we cannot know the direction, so fall
        // back to writing the note rather than guessing at the description.
        $canRecompose = $canRecompose && $bank && $bankItem;

        DB::transaction(function () use ($journalEntry, $bank, $bankItem, $canRecompose, $remarks) {
            if ($canRecompose) {
                // Direction from the bank's own line — a debit is money in —
                // rather than from parsing the existing description.
                $isDeposit = (float) $bankItem->debit > 0;

                $journalEntry->update([
                    'description' => ($isDeposit ? 'Deposit to ' : 'Withdrawal from ')
                        . $bank->name
                        . ($remarks !== '' ? ' — ' . $remarks : ''),
                ]);
            }

            // Both lines carry the note, exactly as recordTransaction() writes them.
            JournalItem::where('journal_entry_id', $journalEntry->id)
                ->update(['note' => $remarks !== '' ? $remarks : null]);
        });

        return response()->json([
            'success'     => true,
            'message'     => $canRecompose ? 'Description updated.' : 'Note updated.',
            'description' => $journalEntry->fresh()->description,
            'note'        => $remarks !== '' ? $remarks : null,
        ]);
    }
}
