<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Bank;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseDepartment;
use App\Models\ExpenseItem;
use App\Models\ExpenseSubCategory;
use App\Services\ExpenseClassificationService;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class ExpenseController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view expense', only: ['index', 'printList', 'printSlip', 'report', 'reportExport', 'reportPrint', 'getItems', 'dailyFund']),
            new Middleware('permission:create expense', only: ['create', 'store']),
            new Middleware('permission:edit expense', only: ['edit', 'update']),
            new Middleware('permission:delete expense', only: ['destroy']),
            // Approving is what puts money in the accounts; reversing is what
            // takes it back out. Both sit behind their own permission, so
            // holding `edit expense` no longer moves the ledger on its own.
            new Middleware('permission:approve expense', only: ['approve', 'reverse']),
        ];
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $userCompanyId = auth()->user()->company_id;
        $canViewAll = auth()->user()->can('view all expense');

        $req_subdatas = [];
        if ($request->filled('expense_category_id')) {
            $req_subdatas = ExpenseSubCategory::where('expense_category_id', $request->expense_category_id)->get();
        }

        $listQuery = $this->expenseListQuery($request)
            ->select('expenses.*')
            // Everything the table renders, in one query each. Only `approver`
            // was eager loaded before — the joins that used to sit here carried
            // the sort but left the relations lazy, so a page of twenty rows
            // asked for its company, bank, user and category one row at a time.
            // The float's custodian comes along because the Paid From column
            // names the person holding the money, not the float's id.
            ->with([
                'approver:id,name',
                'user',
                'company',
                'bank',
                'expense_category',
                'expense_sub_category',
                'expenseDepartment',
                'pettyCashFloat.custodian',
                'reimburseTo',
            ]);

        // Column sorting — the keys partials/sortable-th posts as ?sort=.
        //
        // The key is only ever a lookup into this map, so nothing from the URL
        // reaches the query as SQL; an unknown key falls through to the default
        // order below.
        //
        // Four of these sort on another table's name, and each names the join it
        // needs so the join is added for THAT sort alone. They used to sit on
        // every query unconditionally; once the relations above were eager
        // loaded, nothing but the sort still wanted them, and five joins on a
        // page nobody sorted is five joins for nothing. A leftJoin on a
        // belongs-to cannot multiply rows, and select('expenses.*') means no
        // column is read from the joined table — so the page and its count stay
        // exactly what they are without it.
        // One entry per clickable header in expenses/index, and no more. `bank`
        // and `user` were dropped with the columns they ordered: the table shows
        // "Paid From" rather than a Bank column now — a bank is only one of the
        // four things it can say — and it never had a User column at all, so both
        // keys offered to order the list by something nobody could see.
        $sortable = [
            'title'      => ['expenses.title', null],
            'category'   => ['expense_categories.name', ['expense_categories', 'expense_categories.id', 'expenses.expense_category_id']],
            'company'    => ['companies.name', ['companies', 'companies.id', 'expenses.company_id']],
            'department' => ['expense_departments.name', ['expense_departments', 'expense_departments.id', 'expenses.expense_department_id']],
            'date'       => ['expenses.expense_date', null],
            'amount'     => ['expenses.amount', null],
            'status'     => ['expenses.status', null],
        ];

        // is_string before any cast: ?sort[]=title arrives as an array, and
        // casting that to string raises a PHP warning which Laravel promotes to
        // an ErrorException — a 500 before the whitelist is ever consulted.
        // Anything that is not a plain string is treated as "no sort requested".
        $sortRaw = $request->query('sort');
        $dirRaw  = $request->query('dir');
        $sortKey = is_string($sortRaw) ? $sortRaw : '';
        $sortDir = (is_string($dirRaw) && strtolower($dirRaw) === 'asc') ? 'asc' : 'desc';

        if ($sortKey !== '' && isset($sortable[$sortKey])) {
            [$sortColumn, $sortJoin] = $sortable[$sortKey];

            if ($sortJoin) {
                [$sortTable, $sortLeft, $sortRight] = $sortJoin;
                $listQuery->leftJoin($sortTable, $sortLeft, '=', $sortRight);
            }

            $listQuery->orderBy($sortColumn, $sortDir);
        } else {
            // Newest first. An expense ledger is read in the order the money left,
            // and the old default — user name, then category, then title — put a
            // 11 Aug row above a 13 Aug one on alphabetical accident. That is the
            // DEFAULT only; a header click still sorts by whatever was clicked.
            $listQuery->orderBy('expenses.expense_date', 'desc');
        }

        // id last either way, so rows sharing a value keep a stable order between
        // pages — without it, page 2 can repeat a row from page 1.
        $datas = $listQuery->orderBy('expenses.id', 'desc')->paginate(20);

        // What the strip above the table reports. Over the FILTERED set, not the
        // page: "amount on this page" was an honest number answering a question
        // nobody asks, since it changes when you turn the page.
        $summary = $this->expenseListQuery($request)
            ->selectRaw('COUNT(*) as rows_count, COALESCE(SUM(expenses.amount), 0) as amount_total')
            ->first();

        // Deliberately blind to the approval filter — see expenseListQuery().
        $pendingSummary = $this->expenseListQuery($request, false)
            ->where('expenses.approval_status', Expense::PENDING)
            ->selectRaw('COUNT(*) as rows_count, COALESCE(SUM(expenses.amount), 0) as amount_total')
            ->first();
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();

        if (!$canViewAll && !empty($userCompanyId)) {
            $companies = Company::where('id', $userCompanyId)->get();
            // Shared categories included — a category names a company only when it
            // is that company's own, and nearly all of them are shared. Matching
            // the column alone left this list empty for a company-locked user.
            $expense_categories = ExpenseCategory::orderBy('name')->where('status', 1)
                ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $userCompanyId))
                ->get();
            $banks = Bank::orderBy('name')->where('status', 1)->where('company_id', $userCompanyId)->get();
        } else {
            $companies = Company::orderBy('name')->get();
            $expense_categories = ExpenseCategory::orderBy('name')->where('status', 1)->get();
            $banks = Bank::orderBy('name')->where('status', 1)->get();
        }

        // Nothing to offer someone who may not spend from a company account. The
        // Bank card is hidden from them in the view, but an empty list is what
        // makes that a rule rather than a decoration — a hand-posted bank_id has
        // nothing to match, and store() refuses it outright besides.
        if (!auth()->user()->can('pay expense from bank')) {
            $banks = collect();
        }

        // Active floats, so a cash expense can be settled against the pocket it
        // actually came out of rather than the drawer. Company-scoped for the same
        // reason banks are: spending one company's float on another's expense
        // would move cash between two sets of books with no transfer recorded.
        //
        // And scoped to the HOLDER as well, for anyone who cannot see the whole
        // company's expenses. Until now every custodian's float was offered to
        // everyone: an employee could file a receipt against a colleague's pocket
        // and that colleague's balance would fall for a purchase they never made.
        // Settling from someone else's float is an administrative act, so it stays
        // with the people who already oversee the company's spending.
        $pettyCashFloats = \App\Models\PettyCashFloat::with('custodian:id,name')
            ->where('status', 1)
            ->when(!$canViewAll && !empty($userCompanyId), fn ($q) => $q->where('company_id', $userCompanyId))
            ->when(
                !auth()->user()->can('view company expense') && !$canViewAll,
                fn ($q) => $q->where('custodian_id', auth()->id())
            )
            ->get();

        // What each custodian is holding right now, so the form can say so before
        // the receipt is filed instead of refusing it afterwards. One batched
        // query for the lot — balancesFor() exists so this is not one aggregate
        // per float.
        $floatBalances = app(\App\Services\PettyCashService::class)->balancesFor($pettyCashFloats);

        // Today's spend per float, for the panel on the form. One grouped query.
        $floatSpentToday = Expense::whereIn('petty_cash_float_id', $pettyCashFloats->pluck('id'))
            ->where('status', 1)
            ->whereDate('expense_date', now()->toDateString())
            ->groupBy('petty_cash_float_id')
            ->pluck(DB::raw('SUM(amount)'), 'petty_cash_float_id');

        $pettyCashFloats->each(function ($float) use ($floatBalances, $floatSpentToday) {
            $float->held = $floatBalances[$float->id] ?? 0;
            $float->spent_today = (float) ($floatSpentToday[$float->id] ?? 0);
        });
        $accounts = Account::orderBy('name')->where('status', 1)->where('type', 'expense')->get();

        // Everything the classification picker needs, in one payload — the whole
        // Company/Department/Category/Sub-category graph, so the form can resolve
        // any level from any other without a round trip per keystroke.
        $classification = app(ExpenseClassificationService::class)
            ->payload(!$canViewAll && !empty($userCompanyId) ? $userCompanyId : null);

        // Today's cash ceiling per company, so the form can say "this company has
        // ৳150 of today's allowance left" while the amount is being typed instead
        // of after the click. A CEILING, not a pot: it changes no account, blocks
        // no save and is never posted — see DailyFundService. The figures the
        // float panel shows above are real cash and stay untouched by it.
        $dailyFundToday = app(\App\Services\DailyFundService::class)
            ->summaryOn(now()->toDateString(), $companies);

        // The pot a cash expense will actually be credited to. Read rather than
        // hardcoded, because "Office Cash" stopped being the true answer the
        // moment a petty cash pool was configured, and a form that names the
        // wrong account is worse than one that names none.
        //
        // In a try/catch because a LABEL must never be the reason the expense
        // desk fails to load — a broken chart should surface when something is
        // posted, not when the page is opened.
        // The balance goes with it: a custodian's float already shows what is in
        // the pocket before the receipt is filed, and the pot was the one source
        // on this form that showed nothing. Someone picking it had no way to know
        // it was empty until the save was refused.
        try {
            $pettyCash        = app(\App\Services\PettyCashService::class);
            $cashPotName      = $pettyCash->cashPotAccount()->name;
            $cashPotBalance   = $pettyCash->cashPotBalance();
        } catch (\Throwable $e) {
            $cashPotName    = 'Office Cash';
            $cashPotBalance = null;   // null = "unknown", which the form shows as no figure
        }

        return view('expenses.index', compact(
            'datas',
            'users',
            'banks',
            'companies',
            'req_subdatas',
            'expense_categories',
            'accounts',
            'classification',
            'pettyCashFloats',
            'dailyFundToday',
            'cashPotName',
            'cashPotBalance',
            'summary',
            'pendingSummary'
        ));
    }


    /**
     * Every filter the expense list offers, applied to a fresh query.
     *
     * Pulled out because the page now asks the same question three times — the
     * rows, the total underneath them, and how much is still waiting for
     * approval. Three copies of nine filters is three chances for the summary
     * strip to contradict the table it sits above, and a summary that disagrees
     * with the rows under it is worse than no summary.
     *
     * $includeApproval is false for the pending figure alone. That tile has to
     * survive the approval filter itself: with it applied, choosing "Approved"
     * would leave the tile reading zero and nothing to click back to.
     */
    private function expenseListQuery(Request $request, bool $includeApproval = true)
    {
        $query = Expense::query();

        // Who is allowed to see what. The company_id filter below only narrows
        // further — it can never widen past this.
        $this->applyExpenseVisibility($query);

        if (auth()->user()->can('view all expense') && $request->filled('company_id')) {
            $query->where('expenses.company_id', $request->company_id);
        }

        foreach (['user_id', 'bank_id', 'expense_category_id', 'expense_sub_category_id'] as $column) {
            if ($request->filled($column)) {
                $query->where('expenses.' . $column, $request->input($column));
            }
        }

        // Substring, not equality. The box is labelled "Search title…" but asked
        // for the whole title character-for-character, so "Item" found nothing
        // and only "Item 01" worked. Escaped, because _ and % in a title would
        // otherwise be read as wildcards.
        if ($request->filled('title')) {
            $query->where('expenses.title', 'like', '%' . addcslashes($request->title, '%_\\') . '%');
        }

        // `status` is a 0/1 flag. whereDate() cast it to a date before comparing,
        // so neither Active nor Inactive could ever match.
        if ($request->filled('status')) {
            $query->where('expenses.status', (int) $request->status);
        }

        // The filter form has posted this input all along and the query never
        // read it, so picking a date silently returned the unfiltered list.
        if ($request->filled('expense_date')) {
            $query->whereDate('expenses.expense_date', $request->expense_date);
        }

        // Mirrors REPORT_SOURCE_SQL — same precedence, and the same parameter
        // name the report already uses, so "petty" cannot come to mean one thing
        // on this screen and another on that one.
        switch ($request->input('payment_source')) {
            case 'petty':
                $query->whereNotNull('expenses.petty_cash_float_id');
                break;
            case 'bank':
                $query->whereNull('expenses.petty_cash_float_id')->whereNotNull('expenses.bank_id');
                break;
            case 'cash':
                $query->whereNull('expenses.petty_cash_float_id')->whereNull('expenses.bank_id');
                break;
        }

        // Whether the money is in the accounts. `status` above is the record's
        // own active flag and says nothing about that — it was the only status
        // this page could filter on, and it is not the one anyone needs.
        if ($includeApproval && in_array($request->approval_status, [Expense::PENDING, Expense::APPROVED], true)) {
            $query->where('expenses.approval_status', $request->approval_status);
        }

        return $query;
    }

    public function printList(Request $request)
    {
        // The same filters the list applied, not a second copy of them. Print is
        // handed the list's own query string, so any drift between the two — an
        // exact title match here against a substring match there, a source filter
        // one of them has not heard of — prints a different set of rows than the
        // one the user was looking at when they pressed the button.
        $datas = $this->expenseListQuery($request)
            ->select('expenses.*')
            ->with([
                'user',
                'company',
                'bank',
                'expense_category',
                'expense_sub_category',
                'expenseDepartment',
                'pettyCashFloat.custodian',
                'reimburseTo',
            ])
            ->orderBy('expenses.expense_date', 'desc')
            ->orderBy('expenses.id', 'desc')
            ->get();

        $filterLabels = [
            'user'        => $request->filled('user_id')                 ? optional(\App\Models\User::find($request->user_id))->name : null,
            'company'     => $request->filled('company_id')              ? optional(\App\Models\Company::find($request->company_id))->name : null,
            'bank'        => $request->filled('bank_id')                 ? optional(\App\Models\Bank::find($request->bank_id))->name : null,
            'category'    => $request->filled('expense_category_id')     ? optional(\App\Models\ExpenseCategory::find($request->expense_category_id))->name : null,
            'subcategory' => $request->filled('expense_sub_category_id') ? optional(\App\Models\ExpenseSubCategory::find($request->expense_sub_category_id))->name : null,
            // Both are filters the list has offered for a while and the printed
            // copy never named, so a report narrowed to one department printed
            // looking like the whole company's spending.
            'department'  => $request->filled('expense_department_id')    ? optional(\App\Models\ExpenseDepartment::find($request->expense_department_id))->name : null,
            'source'      => $request->filled('payment_source')
                ? (['petty' => 'Petty Cash', 'bank' => 'Bank', 'cash' => 'Cash in Hand'][$request->input('payment_source')] ?? null)
                : null,
        ];

        // Whose letterhead the printed copy carries.
        //
        // A named company filter answers it outright — that report IS that
        // company's. Failing that it is the reader's own company, and failing
        // that a group-wide report, which the first company row heads the way
        // printSlip() already does it. Resolved by id rather than through a
        // relation so a user with no company set cannot make this throw on a page
        // whose entire job is to render.
        $company = ($request->filled('company_id') ? \App\Models\Company::find($request->company_id) : null)
            ?: (!empty(auth()->user()->company_id) ? \App\Models\Company::find(auth()->user()->company_id) : null)
            ?: \App\Models\Company::first();

        return view('expenses.print', compact('datas', 'filterLabels', 'company'));
    }

    /**
     * How many ledger rows the report will render before it stops.
     *
     * The breakdown above it is aggregated in SQL and always covers everything;
     * only this list is capped, and the view says so out loud rather than
     * quietly showing a short table that reads like the whole period.
     */
    private const REPORT_LEDGER_LIMIT = 200;

    /**
     * The same cap for the printable copy, raised.
     *
     * Higher because a printed report is the copy someone files or hands to an
     * auditor, so a short list is worse there than a long one. Still capped:
     * "all companies, this year" unbounded is a 50,000-row PDF and an
     * out-of-memory error, and the footer says when it truncated.
     */
    private const REPORT_PRINT_LEDGER_LIMIT = 1000;

    /**
     * Where the money actually left, as a SQL expression.
     *
     * Deliberately the same precedence as settlementAccountId(): a petty cash
     * float outranks a bank, and a bank outranks the drawer. payment_mode is
     * ignored here for the same reason it is ignored there — the two drift when
     * somebody picks "cash" and then names a bank, and the bank is the specific
     * claim. If this ever disagreed with the posting, the report would be
     * telling the owner money left somewhere it did not.
     */
    private const REPORT_SOURCE_SQL = "CASE WHEN expenses.petty_cash_float_id IS NOT NULL THEN 'Petty Cash' WHEN expenses.bank_id IS NOT NULL THEN 'Bank' ELSE 'Cash in Hand' END";

    /**
     * The five ways the same filtered set can be regrouped.
     *
     * `source` is the odd one out — it has no table to join, because "where the
     * money came from" is derived (REPORT_SOURCE_SQL) rather than stored.
     */
    private const REPORT_GROUPS = [
        'company'     => ['label' => 'Company',            'table' => 'companies',              'fk' => 'expenses.company_id',              'drill' => 'company_id'],
        'department'  => ['label' => 'Department / Project','table' => 'expense_departments',   'fk' => 'expenses.expense_department_id',   'drill' => 'expense_department_id'],
        'category'    => ['label' => 'Category',            'table' => 'expense_categories',     'fk' => 'expenses.expense_category_id',     'drill' => 'expense_category_id'],
        'subcategory' => ['label' => 'Sub-Category',        'table' => 'expense_sub_categories', 'fk' => 'expenses.expense_sub_category_id', 'drill' => 'expense_sub_category_id'],
        'source'      => ['label' => 'Money Source',        'table' => null,                     'fk' => null,                               'drill' => 'payment_source'],
    ];

    /**
     * The expense report desk.
     *
     * One report seen five ways: the same filtered set of expenses regrouped by
     * company, department, category, sub-category or money source.
     *
     * Every figure is aggregated in SQL. The previous version pulled the whole
     * period into a collection and summed it in PHP, which for a superadmin
     * asking "all companies, this quarter" meant loading tens of thousands of
     * rows — with their user, company, bank and category relations — to produce
     * four numbers on a card.
     */
    public function report(Request $request)
    {
        return view('expenses.report', $this->buildReportData($request, self::REPORT_LEDGER_LIMIT));
    }

    /**
     * The same report as a standalone printable page.
     *
     * A dedicated route and a layout-free view, the way
     * party-statement/print-statement.blade.php and expenses/print.blade.php
     * already do it. window.print() on the report page itself printed the
     * sidebar, header and tab bar along with it, because the print stylesheet
     * that would hide them lives in the shared layout — and adding print rules
     * there would reach all twelve companies' pages for the sake of this one.
     */
    public function reportPrint(Request $request)
    {
        $data = $this->buildReportData($request, self::REPORT_PRINT_LEDGER_LIMIT);

        // The letterhead. Named company when the report is scoped to exactly one,
        // otherwise the report legitimately covers the whole group.
        $canViewAll = auth()->user()->can('view all expense');
        $scopedCompanyId = $canViewAll ? $request->input('company_id') : auth()->user()->company_id;
        $data['scopeCompany'] = $scopedCompanyId ? Company::find($scopedCompanyId) : null;

        // Two different questions, so two variables. `scopeCompany` is null on a
        // group-wide report and the page says "All Companies" because of it;
        // `company` is whose letterhead the paper carries, which can never be
        // nothing. Same resolution printList() uses.
        $data['company'] = $data['scopeCompany']
            ?: (!empty(auth()->user()->company_id) ? Company::find(auth()->user()->company_id) : null)
            ?: Company::first();

        return view('expenses.report-print', $data);
    }

    /**
     * The daily cost fund for ONE date, as JSON.
     *
     * So the expense form's meter can follow the date field instead of always
     * describing today. Back-dating is normal on this desk — yesterday's
     * receipt gets entered this morning — and the meter answered for today
     * regardless: today's ceiling, today's spend, and the save-time "over the
     * limit" confirmation judged against the wrong day entirely.
     *
     * The fund itself was always date-versioned (effective_from / effective_to)
     * and summaryOn() always took a date, so the service had the right answer
     * all along. Only the form was asking the wrong question.
     */
    public function dailyFund(Request $request)
    {
        $userCompanyId = auth()->user()->company_id;
        $canViewAll = auth()->user()->can('view all expense');

        try {
            $date = $request->filled('date')
                ? Carbon::parse($request->date)->toDateString()
                : now()->toDateString();
        } catch (\Throwable $e) {
            // A half-typed date arrives here on every keystroke in some browsers.
            // Today is the honest fallback; it is what the page loaded with.
            $date = now()->toDateString();
        }

        $companies = (!$canViewAll && !empty($userCompanyId))
            ? Company::where('id', $userCompanyId)->get()
            : Company::orderBy('name')->get();

        return response()->json(
            app(\App\Services\DailyFundService::class)->summaryOn($date, $companies)
        );
    }

    /**
     * The one-line label every list, slip and report shows for an expense.
     *
     * The form stopped asking for it: each cost line already carries its own
     * description, so a separate title meant typing the same words twice.
     *
     * Derived rather than dropped. `expenses.title` is NOT NULL and is read by
     * the expense list, the delete confirmation, the print slip, the printed
     * list and both expense reports — making it nullable would have meant a
     * migration plus a blank column on five screens. So the first cost line
     * that says something becomes the title, which is what a person would have
     * typed anyway.
     *
     * A title that DOES arrive is kept untouched: editing an expense recorded
     * before this change must not silently rewrite its title.
     */
    private function resolveExpenseTitle(Request $request): string
    {
        if (filled($request->input('title'))) {
            return \Illuminate\Support\Str::limit(trim($request->input('title')), 250, '');
        }

        foreach ((array) $request->input('items', []) as $item) {
            $description = is_array($item) ? ($item['description'] ?? null) : null;

            if (filled($description)) {
                return \Illuminate\Support\Str::limit(trim($description), 250, '');
            }
        }

        // Every line was left blank. The classification is required, so it can
        // always answer — better a category name than an empty column.
        $name = ExpenseSubCategory::whereKey($request->expense_sub_category_id)->value('name')
            ?: ExpenseCategory::whereKey($request->expense_category_id)->value('name');

        return $name ?: 'Expense';
    }

    /**
     * "Others…" is an answer, not an id — stop it before anything treats it as one.
     *
     * The picker posts the literal string `__other` for that option, and every
     * level it can be chosen at is an id column: expense_department_id and
     * expense_category_id are NOT NULL foreign keys, expense_sub_category_id is a
     * nullable one, and ExpenseClassificationService::accountFor() is typed ?int.
     * So the sentinel reached accountFor() and killed the save with
     * "must be of type ?int, string given" — a 500, not a message anyone could
     * act on. Left to reach the insert instead it would have failed just as hard,
     * on the foreign key.
     *
     * What the user actually meant is carried by other_note, a free-text column,
     * and is untouched by any of this.
     *
     * Only the SUB-CATEGORY can honestly be "none": its column is nullable, so
     * "Others…" there stores a blank sub-category beside the typed note. A
     * category cannot — expenses.expense_category_id is NOT NULL — which is why
     * the dropdown offers the real shared "Miscellaneous" row instead of an
     * Others option, and why a request that still posts one is refused by the
     * required rule with a message naming the fix rather than crashing.
     *
     * Empty strings are folded to null in the same pass: an unanswered <select>
     * posts '', and '' into an unsigned bigint is a 0 that no row has.
     */
    private function normaliseClassification(Request $request): void
    {
        $clean = [];

        foreach (['expense_department_id', 'expense_category_id', 'expense_sub_category_id'] as $field) {
            $value = $request->input($field);

            if (is_string($value) && in_array(trim($value), ['__other', ''], true)) {
                $clean[$field] = null;
            }
        }

        if ($clean) {
            $request->merge($clean);
        }
    }

    /** Everything both the screen and the printable copy need. */
    private function buildReportData(Request $request, int $ledgerLimit): array
    {
        $userCompanyId = auth()->user()->company_id;
        $canViewAll = auth()->user()->can('view all expense');

        $window = $this->resolveReportWindow($request);
        extract($window); // period, from, to, periodLabel, selectedMonth, selectedYear, customFrom, customTo, prevFrom, prevTo

        $groupKey = $request->input('group_by', 'category');
        if (!array_key_exists($groupKey, self::REPORT_GROUPS)) {
            $groupKey = 'category';
        }
        $groupMeta = self::REPORT_GROUPS[$groupKey];

        // --- Totals for the selected window, and the same window one step back.
        $summary = $this->reportTotals($this->reportQuery($request, $from, $to));
        $previous = $this->reportTotals($this->reportQuery($request, $prevFrom, $prevTo));

        $summary['previous_amount'] = $previous['total_amount'];
        $summary['previous_expenses'] = $previous['total_expenses'];
        $summary['change_pct'] = $previous['total_amount'] > 0
            ? round((($summary['total_amount'] - $previous['total_amount']) / $previous['total_amount']) * 100, 1)
            : null;
        $summary['average_amount'] = $summary['total_expenses'] > 0
            ? round($summary['total_amount'] / $summary['total_expenses'], 2)
            : 0.0;

        // --- The breakdown, in whichever grouping was asked for.
        $groupRows = $this->reportGrouping($request, $from, $to, $groupKey);

        // --- Day-by-day trend. Pointless for a single-day report, so skip it.
        $timeline = collect();
        if ($from->copy()->startOfDay()->lt($to->copy()->startOfDay())) {
            $daily = $this->reportQuery($request, $from, $to)
                ->selectRaw('expenses.expense_date as day, COUNT(*) as txn_count, COALESCE(SUM(expenses.amount), 0) as amount')
                ->groupBy('expenses.expense_date')
                ->get()
                ->keyBy(fn ($row) => Carbon::parse($row->day)->toDateString());

            foreach (CarbonPeriod::create($from->copy()->startOfDay(), '1 day', $to->copy()->startOfDay()) as $day) {
                $dateKey = $day->toDateString();
                $hit = $daily->get($dateKey);

                $timeline->push([
                    'date' => $dateKey,
                    'label' => $day->format('d M'),
                    'count' => (int) ($hit->txn_count ?? 0),
                    'amount' => (float) ($hit->amount ?? 0),
                ]);
            }
        }

        // --- The ledger itself, capped. reorder()/getQuery() are not needed: the
        //     base query carries no ordering of its own.
        $ledgerTotal = $summary['total_expenses'];
        $expenses = $this->reportQuery($request, $from, $to)
            ->with(['user', 'company', 'bank', 'expenseDepartment', 'expense_category', 'expense_sub_category'])
            ->orderBy('expenses.expense_date', 'desc')
            ->orderBy('expenses.id', 'desc')
            ->limit($ledgerLimit)
            ->get();

        // --- Filter option lists, scoped to what this user may see at all.
        if (!$canViewAll && !empty($userCompanyId)) {
            $companies = Company::where('id', $userCompanyId)->get();
            $expenseDepartments = ExpenseDepartment::orderBy('name')->where('company_id', $userCompanyId)->get();
            $expenseCategories = ExpenseCategory::orderBy('name')->where('company_id', $userCompanyId)->get();
            $banks = Bank::orderBy('name')->where('status', 1)->where('company_id', $userCompanyId)->get();
        } else {
            $companies = Company::orderBy('name')->get();
            $expenseDepartments = ExpenseDepartment::orderBy('name')->get();
            $expenseCategories = ExpenseCategory::orderBy('name')->get();
            $banks = Bank::orderBy('name')->where('status', 1)->get();
        }

        // Sub-categories follow the chosen category — an unfiltered list of every
        // sub-category in the group is unusable once there are a few hundred.
        $subCategoryQuery = ExpenseSubCategory::orderBy('name');
        if (!$canViewAll && !empty($userCompanyId)) {
            $subCategoryQuery->where('company_id', $userCompanyId);
        }
        if ($request->filled('expense_category_id')) {
            $subCategoryQuery->where('expense_category_id', $request->expense_category_id);
        }
        $expenseSubCategories = $subCategoryQuery->get();

        $activeFilters = $this->reportActiveFilters($request, $canViewAll);

        return compact(
            'expenses',
            'ledgerTotal',
            'companies',
            'expenseDepartments',
            'expenseCategories',
            'expenseSubCategories',
            'banks',
            'summary',
            'timeline',
            'groupRows',
            'groupKey',
            'groupMeta',
            'activeFilters',
            'canViewAll',
            'period',
            'periodLabel',
            'from',
            'to',
            'prevFrom',
            'prevTo',
            'selectedMonth',
            'selectedYear',
            'customFrom',
            'customTo'
        );
    }

    /**
     * The same report as a CSV of the current grouping.
     *
     * Exports what is on screen — the grouped breakdown under the active
     * filters, not the raw ledger — because that is the figure people are
     * asked to justify. The route carries "export" in its path, which is what
     * keeps the layout's speculation-rules block from prefetching it on hover.
     */
    public function reportExport(Request $request)
    {
        $window = $this->resolveReportWindow($request);
        extract($window);

        $groupKey = $request->input('group_by', 'category');
        if (!array_key_exists($groupKey, self::REPORT_GROUPS)) {
            $groupKey = 'category';
        }

        $groupMeta = self::REPORT_GROUPS[$groupKey];
        $rows = $this->reportGrouping($request, $from, $to, $groupKey);
        $totalAmount = (float) $rows->sum('amount');

        $filename = 'expense-report-' . $groupKey . '-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($rows, $groupMeta, $periodLabel, $totalAmount) {
            $handle = fopen('php://output', 'w');

            // Excel reads a UTF-8 CSV as Windows-1252 without this, which turns
            // every Bengali company name into mojibake.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Expense Report — ' . $periodLabel]);
            fputcsv($handle, []);
            fputcsv($handle, [$groupMeta['label'], 'Transactions', 'Amount', '% of Total']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->name,
                    $row->count,
                    number_format($row->amount, 2, '.', ''),
                    $totalAmount > 0 ? number_format($row->amount / $totalAmount * 100, 1) : '0.0',
                ]);
            }

            fputcsv($handle, ['Total', $rows->sum('count'), number_format($totalAmount, 2, '.', ''), '100.0']);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Turn the period inputs into a date window, plus the comparable window
     * immediately before it.
     *
     * Monthly steps back a calendar month rather than a fixed 30 days, because
     * "last month" is what an accounts team means and February would otherwise
     * be compared against a stretch of January.
     */
    private function resolveReportWindow(Request $request): array
    {
        $period = strtolower($request->input('period', 'daily'));

        if (!in_array($period, ['daily', 'weekly', 'monthly', 'custom'], true)) {
            $period = 'daily';
        }

        $anchorDate = Carbon::parse($request->input('date', now()->toDateString()));
        $from = $anchorDate->copy()->startOfDay();
        $to = $anchorDate->copy()->endOfDay();
        $periodLabel = $anchorDate->format('d M Y');
        $selectedMonth = (int) $anchorDate->month;
        $selectedYear = (int) $anchorDate->year;
        $customFrom = null;
        $customTo = null;

        if ($period === 'weekly') {
            $from = $anchorDate->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $to = $from->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
            $periodLabel = $from->format('d M Y') . ' to ' . $to->format('d M Y');
        } elseif ($period === 'monthly') {
            $selectedMonth = (int) $request->input('month', now()->month);
            $selectedYear = (int) $request->input('year', now()->year);
            $from = Carbon::create($selectedYear, $selectedMonth, 1)->startOfMonth();
            $to = $from->copy()->endOfMonth();
            $periodLabel = $from->format('F Y');
        } elseif ($period === 'custom') {
            $customFrom = $request->input('from', now()->subDays(29)->toDateString());
            $customTo = $request->input('to', now()->toDateString());
            $from = Carbon::parse($customFrom)->startOfDay();
            $to = Carbon::parse($customTo)->endOfDay();

            if ($to->lt($from)) {
                $swapFrom = $from;
                $from = $to->copy()->startOfDay();
                $to = $swapFrom->copy()->endOfDay();
            }

            $customFrom = $from->toDateString();
            $customTo = $to->toDateString();
            $periodLabel = $from->format('d M Y') . ' to ' . $to->format('d M Y');
        }

        if ($period === 'monthly') {
            $prevFrom = $from->copy()->subMonthNoOverflow()->startOfMonth();
            $prevTo = $prevFrom->copy()->endOfMonth();
        } else {
            $lengthDays = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
            $prevTo = $from->copy()->subDay()->endOfDay();
            $prevFrom = $prevTo->copy()->subDays($lengthDays - 1)->startOfDay();
        }

        return compact(
            'period', 'from', 'to', 'periodLabel',
            'selectedMonth', 'selectedYear', 'customFrom', 'customTo',
            'prevFrom', 'prevTo'
        );
    }

    /**
     * The filtered expense set for a window, with every column qualified.
     *
     * Qualified on purpose: the grouping query joins a name table onto this, and
     * an unqualified `status` or `company_id` is ambiguous the moment it does.
     *
     * Returns a fresh builder each call so callers can aggregate it more than
     * once without cloning.
     */
    private function reportQuery(Request $request, Carbon $from, Carbon $to)
    {
        $query = Expense::query()
            ->whereBetween('expenses.expense_date', [$from->toDateString(), $to->toDateString()]);

        // The same three tiers the list uses. The report reaches the same rows by
        // a different door, so gating one and not the other would leave the door
        // open — and the company_id in the URL is IGNORED below unless the user
        // may actually see other companies, not merely hidden in the UI.
        $this->applyExpenseVisibility($query);

        if (auth()->user()->can('view all expense') && $request->filled('company_id')) {
            $query->where('expenses.company_id', $request->company_id);
        }

        foreach (['expense_department_id', 'expense_category_id', 'expense_sub_category_id', 'bank_id'] as $column) {
            if ($request->filled($column)) {
                $query->where('expenses.' . $column, $request->input($column));
            }
        }

        if ($request->filled('payment_mode')) {
            $query->where('expenses.payment_mode', $request->payment_mode);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('expenses.status', (int) $request->status);
        }

        // Mirrors REPORT_SOURCE_SQL — same precedence, expressed as a filter.
        switch ($request->input('payment_source')) {
            case 'petty':
                $query->whereNotNull('expenses.petty_cash_float_id');
                break;
            case 'bank':
                $query->whereNull('expenses.petty_cash_float_id')->whereNotNull('expenses.bank_id');
                break;
            case 'cash':
                $query->whereNull('expenses.petty_cash_float_id')->whereNull('expenses.bank_id');
                break;
        }

        return $query;
    }

    /** Count, total, and the money-source split, in one round trip. */
    private function reportTotals($query): array
    {
        $row = $query->selectRaw("
            COUNT(*) as txn_count,
            COALESCE(SUM(expenses.amount), 0) as total_amount,
            COALESCE(SUM(CASE WHEN expenses.petty_cash_float_id IS NOT NULL THEN expenses.amount ELSE 0 END), 0) as petty_amount,
            COALESCE(SUM(CASE WHEN expenses.petty_cash_float_id IS NULL AND expenses.bank_id IS NOT NULL THEN expenses.amount ELSE 0 END), 0) as bank_amount,
            COALESCE(SUM(CASE WHEN expenses.petty_cash_float_id IS NULL AND expenses.bank_id IS NULL THEN expenses.amount ELSE 0 END), 0) as cash_amount,
            COALESCE(MAX(expenses.amount), 0) as largest_amount,
            COALESCE(SUM(CASE WHEN expenses.status = 1 THEN 1 ELSE 0 END), 0) as active_count
        ")->first();

        return [
            'total_expenses'  => (int) ($row->txn_count ?? 0),
            'total_amount'    => (float) ($row->total_amount ?? 0),
            'petty_amount'    => (float) ($row->petty_amount ?? 0),
            'bank_amount'     => (float) ($row->bank_amount ?? 0),
            'cash_amount'     => (float) ($row->cash_amount ?? 0),
            'largest_amount'  => (float) ($row->largest_amount ?? 0),
            'active_expenses' => (int) ($row->active_count ?? 0),
        ];
    }

    /**
     * The breakdown rows for one grouping, largest first.
     *
     * Each row carries the id it was grouped on so the view can turn it into a
     * drill-down link back into this same report. A row with no id (nothing was
     * classified) stays plain text — there is no id to filter by.
     */
    private function reportGrouping(Request $request, Carbon $from, Carbon $to, string $groupKey)
    {
        $meta = self::REPORT_GROUPS[$groupKey];
        $query = $this->reportQuery($request, $from, $to);

        if ($groupKey === 'source') {
            $source = self::REPORT_SOURCE_SQL;

            return $query
                ->selectRaw("{$source} as group_name, COUNT(*) as txn_count, COALESCE(SUM(expenses.amount), 0) as amount")
                ->groupBy(DB::raw($source))
                ->orderByDesc('amount')
                ->get()
                ->map(fn ($row) => (object) [
                    'id'     => ['Petty Cash' => 'petty', 'Bank' => 'bank', 'Cash in Hand' => 'cash'][$row->group_name] ?? null,
                    'name'   => $row->group_name,
                    'count'  => (int) $row->txn_count,
                    'amount' => (float) $row->amount,
                ]);
        }

        $table = $meta['table'];
        $fk = $meta['fk'];

        return $query
            ->leftJoin($table, $table . '.id', '=', $fk)
            ->selectRaw("{$fk} as group_id, {$table}.name as group_name, COUNT(*) as txn_count, COALESCE(SUM(expenses.amount), 0) as amount")
            ->groupBy(DB::raw($fk), DB::raw($table . '.name'))
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => (object) [
                'id'     => $row->group_id,
                'name'   => $row->group_name ?: 'Unassigned',
                'count'  => (int) $row->txn_count,
                'amount' => (float) $row->amount,
            ]);
    }

    /**
     * The applied filters, as chips the view can render with a "remove" link.
     *
     * Resolving the names here rather than in Blade keeps the lookups out of a
     * loop and lets a deleted category degrade to its id instead of a blank chip.
     */
    private function reportActiveFilters(Request $request, bool $canViewAll): array
    {
        $chips = [];

        $lookups = [
            'company_id'              => ['Company', \App\Models\Company::class],
            'expense_department_id'   => ['Department', ExpenseDepartment::class],
            'expense_category_id'     => ['Category', ExpenseCategory::class],
            'expense_sub_category_id' => ['Sub-Category', ExpenseSubCategory::class],
            'bank_id'                 => ['Bank', Bank::class],
        ];

        foreach ($lookups as $key => [$label, $model]) {
            if ($key === 'company_id' && !$canViewAll) {
                continue; // Pinned, not chosen — a chip implies it can be removed.
            }

            if ($request->filled($key)) {
                $name = optional($model::find($request->input($key)))->name;
                $chips[] = ['key' => $key, 'label' => $label, 'value' => $name ?: ('#' . $request->input($key))];
            }
        }

        if ($request->filled('payment_source')) {
            $names = ['petty' => 'Petty Cash', 'bank' => 'Bank', 'cash' => 'Cash in Hand'];
            $chips[] = [
                'key' => 'payment_source',
                'label' => 'Source',
                'value' => $names[$request->input('payment_source')] ?? $request->input('payment_source'),
            ];
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $chips[] = [
                'key' => 'status',
                'label' => 'Status',
                'value' => (int) $request->status === 1 ? 'Active' : 'Inactive',
            ];
        }

        return $chips;
    }

    public function printSlip($role, int $id)
    {
        $expense = Expense::with(['user', 'company', 'bank', 'expense_category', 'expense_sub_category', 'items'])->findOrFail($id);

        $this->authoriseExpense($expense);

        $company = $expense->company ?? \App\Models\Company::first();
        return view('expenses.print-slip', compact('expense', 'company'));
    }

    public function getItems(string $role, int $id)
    {
        $expense = Expense::with('items')->findOrFail($id);

        $this->authoriseExpense($expense);

        return response()->json(['success' => true, 'items' => $expense->items]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('expenses.create-modal');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Before validation, deliberately. A source this user may never use is not
        // a field-level mistake to be listed alongside a missing date — answering
        // a forbidden request with "and your title is too long" both leaks that
        // the rest of the payload was examined and buries the actual refusal.
        $this->assertSettlementSourceAllowed($request);

        // Before validation as well, so "Others…" is already null by the time the
        // required / exists rules judge it — and can never reach an id column.
        $this->normaliseClassification($request);

        $validator = Validator::make($request->all(), [
            // Optional now — the form no longer asks. resolveExpenseTitle() fills
            // it from the first cost line, because the column is NOT NULL and
            // seven screens read it.
            'title' => 'nullable|string|max:255',
            'expense_date' => 'required',
            // exists, not just required: the column is a NOT NULL foreign key, so
            // anything that is not a real category id fails at the insert with a
            // 500 rather than a sentence. See normaliseClassification().
            'expense_category_id' => 'required|exists:expense_categories,id',
            'expense_sub_category_id' => 'nullable|exists:expense_sub_categories,id',
            // An expense always leaves ONE company's books. A category may belong
            // to no company — that means shared, and it is true — but the expense
            // itself cannot: journal_entries.company_id is NOT NULL, so a blank
            // company did not save "for everyone", it 500d on the journal insert.
            'company_id' => 'required|exists:companies,id',
            'payment_mode' => 'nullable|in:cash,mobile_banking,bank_transfer,digital_wallet,other',
            // The cost centre. Required because it is the only thing that
            // separates a company's own costs from what it paid on everyone's
            // behalf — "Group Office" against "Common".
            'expense_department_id' => 'required|exists:expense_departments,id',
            'petty_cash_float_id' => 'nullable|exists:petty_cash_floats,id',
            // Who is owed, when the money was their own. Whether this user is
            // ALLOWED to name that person is decided by
            // assertSettlementSourceAllowed() before validation runs.
            'reimburse_to_user_id' => 'nullable|exists:users,id',
            'other_note' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.amount' => 'required|numeric|min:0',
            // Set only when this form was opened from the Subscriptions desk
            // (ExpenseSubscriptionController::recordUrl). All nullable, so a
            // hand-filed expense validates exactly as it always did — but a
            // malformed value is refused here rather than silently producing an
            // expense that is linked to nothing.
            'dm_source_type' => 'nullable|in:subscription,document',
            'dm_id' => 'nullable|integer|min:1',
            'dm_group_id' => 'nullable|integer|min:1',
            'dm_due_date' => 'nullable|date',
        ], [
            'company_id.required' => 'Choose the company this expense belongs to — an expense always leaves the books of exactly one company.',
            'expense_department_id.required' => 'Choose the department this cost belongs to — it is what tells a company\'s own costs apart from the ones it paid on everyone\'s behalf.',
            'expense_category_id.required' => 'Choose the category this cost belongs to — pick Miscellaneous and type what it was if nothing on the list fits.',
            'expense_category_id.exists' => 'That expense category is not one on the list — pick Miscellaneous and type what it was if nothing else fits.',
            'expense_sub_category_id.exists' => 'That sub-category is not one on the list — choose “Others…” and type what it was instead.',
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

        // Company-locked users can only create expenses for their own
        // company, regardless of what's posted; only "view all expense"
        // holders can target a different company via the request. Also
        // verify the chosen bank / category / sub-category actually belong
        // to that company (account_id is intentionally left unscoped —
        // chart of accounts is global by design).
        $userCompanyId = auth()->user()->company_id;
        $canViewAll = auth()->user()->can('view all expense');
        $companyId = (!$canViewAll && !empty($userCompanyId)) ? $userCompanyId : $request->company_id;

        if (!$canViewAll && !empty($userCompanyId)) {
            if ($request->filled('bank_id')) {
                $selectedBank = \App\Models\Bank::find($request->bank_id);
                abort_if(!$selectedBank || (int) $selectedBank->company_id !== (int) $userCompanyId, 403, 'Selected bank belongs to a different company.');
            }

            // A category with no company is shared by the whole group and every
            // company may book against it — which is nearly all of them. Comparing
            // the column straight to the user's company turned NULL into 0, so a
            // company-locked user could not file ANY expense; only a category
            // naming a DIFFERENT company is actually out of bounds.
            $selectedCategory = ExpenseCategory::find($request->expense_category_id);
            abort_if(!$selectedCategory, 403, 'Selected expense category no longer exists.');
            abort_if(
                $selectedCategory->company_id !== null
                    && (int) $selectedCategory->company_id !== (int) $userCompanyId,
                403,
                'Selected expense category belongs to a different company.'
            );

            if ($request->filled('expense_sub_category_id')) {
                $selectedSubCategory = ExpenseSubCategory::find($request->expense_sub_category_id);
                abort_if(!$selectedSubCategory, 403, 'Selected expense sub-category no longer exists.');
                abort_if(
                    $selectedSubCategory->company_id !== null
                        && (int) $selectedSubCategory->company_id !== (int) $userCompanyId,
                    403,
                    'Selected expense sub-category belongs to a different company.'
                );
            }
        }

        // An expense department belongs to exactly one company — that is what
        // lets the form fill the company in from it. The form keeps the two in
        // step; this makes sure a request that did not come from the form cannot
        // file an expense under one company and a department of another.
        if ($request->filled('expense_department_id')) {
            $dept = \App\Models\ExpenseDepartment::find($request->expense_department_id);

            abort_if(
                !$dept || (int) $dept->company_id !== (int) $companyId,
                422,
                'That department belongs to a different company than the one this expense is filed under.'
            );
        }

        // Same reasoning for a petty cash float, and it matters more here: the
        // float is real money held by a real person, and settling one company's
        // expense against another company's float would move cash between two sets
        // of books without either of them recording a transfer.
        if ($request->filled('petty_cash_float_id')) {
            $float = \App\Models\PettyCashFloat::find($request->petty_cash_float_id);

            abort_if(
                !$float || (int) $float->company_id !== (int) $companyId,
                422,
                'That petty cash float belongs to a different company than the one this expense is filed under.'
            );

            abort_if(!$float->status, 422, 'That petty cash float is closed.');
        }

        $items = collect($request->input('items', []))
            ->filter(fn($i) => isset($i['amount']) && $i['amount'] > 0);

        $total = $items->sum('amount');

        // Every cost line was blank or zero, so there is nothing to record.
        // Without this an expense could be filed for zero taka — the amount is
        // the sum of the lines, and 'items.*.amount => min:0' lets a 0 through
        // validation, so submitting the form with an untouched line stored a
        // real expense worth nothing and a balanced zero journal behind it.
        if ($total <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Add at least one cost line with an amount above zero — the expense total is what those lines add up to.',
            ]);
        }

        $data = null;

        try {
        DB::transaction(function () use ($request, $items, $total, &$data, $companyId) {
            $bank = \App\Models\Bank::find($request->bank_id);

            $data = Expense::create([
                'user_id' => auth()->id(),
                // The taxonomy knows which expense account it posts to, so the
                // form no longer asks anyone to pick a chart code by hand. The
                // sub-category is passed as well as the category because it is the
                // narrower answer — snacks land on 8101 Tea & Snacks rather than
                // the whole of 8100. An explicitly posted account_id still wins,
                // for the accountant reclassifying something by exception.
                'account_id' => $request->account_id
                    ?: app(ExpenseClassificationService::class)->accountFor(
                        $request->expense_category_id,
                        $request->expense_sub_category_id
                    ),
                'company_id' => $companyId,
                'expense_department_id' => $request->expense_department_id,
                'expense_category_id' => $request->expense_category_id,
                'expense_sub_category_id' => $request->expense_sub_category_id,
                'other_note' => $request->other_note,
                'title' => $this->resolveExpenseTitle($request),
                'description' => $request->description,
                'amount' => $total,
                'bank_id' => $request->bank_id,
                'petty_cash_float_id' => $request->petty_cash_float_id ?: null,
                'reimburse_to_user_id' => $request->reimburse_to_user_id ?: null,
                'payment_mode' => $request->payment_mode ?: 'cash',
                'reference' => $request->reference,
                'expense_date' => $request->expense_date,
                'status' => $request->status ? 1 : 0
            ]);

            foreach ($items as $item) {
                ExpenseItem::create([
                    'expense_id'  => $data->id,
                    'description' => $item['description'] ?? null,
                    'amount'      => $item['amount'],
                ]);
            }
    
    
            $attachment = $request->file('attachment');
            if ($attachment) {
                $attachment_name = uniqid() . '.' . strtolower($attachment->getClientOriginalExtension());
                $upload_path = 'image/expense/';
                if (!file_exists(public_path($upload_path))) {
                    mkdir(public_path($upload_path), 0777, true);
                }
                $success = $attachment->move(public_path($upload_path), $attachment_name);
                if ($success) {
                    if (!empty($data->attachment) && file_exists(public_path($data->attachment))) {
                        unlink(public_path($data->attachment));
                    }
                    $data->attachment = $upload_path . $attachment_name;
                }
            }

            $data->save();

            // Ties this expense to the DM renewal period it settles, when the
            // form was opened from that desk. Inside the transaction on purpose:
            // if the period is already recorded the unique index throws and the
            // expense rolls back with it, so an attempted double payment cannot
            // leave a stray expense behind.
            $this->linkDmRenewal($request, $data);

            // NO JOURNAL HERE. Filing an expense records a claim; it does not
            // move money. postJournal() runs from approve() instead, so the one
            // thing that reaches the accounts is the one thing that needed
            // somebody's say-so. See the 2026_08_13 approval migration.
        });
        } catch (\App\Exceptions\PettyCashOverdrawnException $e) {
            // A refusal the person filing can act on, not a 500.
            return $request->ajax()
                ? response()->json(['success' => false, 'message' => $e->getMessage()])
                : redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Illuminate\Database\QueryException $e) {
            // dm_renewal_period_unique fired: this renewal period was already
            // recorded. Preventing the SECOND payment is the whole reason that
            // index exists, so it is answered as a sentence rather than a 500.
            // Anything else is a real database fault and is left to bubble.
            if (! $this->isDuplicateRenewalPayment($e)) {
                throw $e;
            }

            $message = 'That renewal period has already been recorded against another expense. '
                . 'Open the Subscriptions tab and use the Paid link to see which one — '
                . 'if that expense is wrong, reverse it rather than filing a second.';

            return $request->ajax()
                ? response()->json(['success' => false, 'message' => $message])
                : redirect()->back()->withInput()->with('error', $message);
        }

        $message = auth()->user()->can('approve expense')
            ? 'Expense recorded. Approve it to post it to the ledger.'
            : 'Expense recorded. It will post to the ledger once approved.';

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $data
            ]);
        }

        return redirect()->route('role.expenses.index')->with('success', $message);
    }

    /**
     * Approve an expense — the moment it becomes money rather than a claim.
     *
     * This is the ONLY place an expense journal is written. Filing one records
     * what somebody says they spent; approving one puts it in the accounts, and
     * from then on it can only be reversed, never quietly edited or deleted.
     */
    public function approve(Request $request, $role, $id)
    {
        $expense = Expense::findOrFail($id);

        $this->authoriseExpense($expense);

        if ($expense->approval_status === Expense::APPROVED) {
            return $this->respond($request, false, 'That expense is already approved.');
        }

        if (!$expense->status) {
            return $this->respond($request, false, 'That expense is inactive. Make it active before approving it.');
        }

        try {
            DB::transaction(function () use ($expense) {
                $this->postJournal($expense);

                $expense->update([
                    'approval_status' => Expense::APPROVED,
                    'approved_by'     => auth()->id(),
                    'approved_at'     => now(),
                ]);
            });
        } catch (\App\Exceptions\PettyCashOverdrawnException $e) {
            return $this->respond($request, false, $e->getMessage());
        } catch (\Throwable $e) {
            return $this->respond($request, false, $e->getMessage());
        }

        return $this->respond($request, true, 'Expense approved and posted to the ledger.');
    }

    /**
     * Take an approved expense back out of the accounts.
     *
     * By writing the opposite entry, never by deleting the original — the same
     * shape BankControllerV2::reverseTransaction() uses. A deleted journal leaves
     * a closed month quietly different from what was reported at the time; a
     * reversal leaves both facts on the record, which is the whole point.
     */
    public function reverse(Request $request, $role, $id)
    {
        $expense = Expense::findOrFail($id);

        $this->authoriseExpense($expense);

        if ($expense->approval_status !== Expense::APPROVED) {
            return $this->respond($request, false, 'That expense is not approved, so there is nothing to reverse.');
        }

        $journal = \App\Models\JournalEntry::where('source', 'expense')
            ->where('source_id', $expense->id)
            ->whereNull('reversed_journal_entry_id')
            ->with('items')
            ->first();

        if (!$journal) {
            // No posting to undo — put the expense back to pending so it can be
            // corrected and approved again, rather than leaving it stuck.
            $expense->update(['approval_status' => Expense::PENDING, 'approved_by' => null, 'approved_at' => null]);

            return $this->respond($request, true, 'No ledger posting was found, so the expense has been returned to pending.');
        }

        if (\App\Models\JournalEntry::where('reversed_journal_entry_id', $journal->id)->exists()) {
            return $this->respond($request, false, 'That posting has already been reversed.');
        }

        DB::transaction(function () use ($expense, $journal) {
            $reversal = \App\Models\JournalEntry::create([
                'company_id'                => $journal->company_id,
                'created_by'                => auth()->id(),
                'date'                      => now()->toDateString(),
                'reference'                 => 'REV-' . ($journal->reference ?: $journal->id),
                'source'                    => 'expense',
                'source_id'                 => $expense->id,
                'description'               => 'Reversal of: ' . $journal->description,
                'reversed_journal_entry_id' => $journal->id,
            ]);

            foreach ($journal->items as $item) {
                \App\Models\JournalItem::create([
                    'journal_entry_id' => $reversal->id,
                    'account_id'       => $item->account_id,
                    'debit'            => $item->credit,
                    'credit'           => $item->debit,
                    'note'             => 'Reversal — ' . ($item->note ?: $journal->description),
                    'party_type'       => $item->party_type,
                    'party_id'         => $item->party_id,
                ]);
            }

            $expense->update([
                'approval_status' => Expense::PENDING,
                'approved_by'     => null,
                'approved_at'     => null,
            ]);
        });

        return $this->respond($request, true, 'Expense reversed. The ledger now carries both the original entry and its reversal.');
    }

    /**
     * Write the two-sided posting for one expense.
     *
     * Shared by approve() and by update() re-posting a pending expense, so the
     * debit account, the settlement account and the float check can never drift
     * between the two paths — which is exactly how a cash expense once ended up
     * postable from one screen and not the other.
     */
    private function postJournal(Expense $data): \App\Models\JournalEntry
    {
        $expenseAccount = \App\Models\Account::find($data->account_id);

        if (!$expenseAccount) {
            throw new \Exception('Expense account not found.');
        }

        // Where the money came from — one pot, or two when a custodian spent past
        // what they were holding. Resolved before the journal exists so a bad
        // chart or a missing float stops the posting rather than half-writing it.
        $creditLines = $this->settlementLines($data);

        $journal = \App\Models\JournalEntry::create([
            'company_id'  => $data->company_id,
            'created_by'  => auth()->id(),
            'date'        => $data->expense_date,
            'reference'   => $data->reference,
            'source'      => 'expense',
            'source_id'   => $data->id,
            'description' => 'Expense — ' . $data->title,
        ]);

        // Debit: Expense account — money spent
        \App\Models\JournalItem::create([
            'journal_entry_id' => $journal->id,
            'account_id'       => $expenseAccount->id,
            'debit'            => $data->amount,
            'credit'           => 0,
            'note'             => $data->title,
        ]);

        // Credit: cash, bank, float, or what the company now owes someone —
        // usually one line, two when the float ran short.
        foreach ($creditLines as $line) {
            \App\Models\JournalItem::create([
                'journal_entry_id' => $journal->id,
                'account_id'       => $line['account_id'],
                'debit'            => 0,
                'credit'           => $line['amount'],
                'note'             => $line['note'],
                'party_type'       => $line['party_type'] ?? null,
                'party_id'         => $line['party_id'] ?? null,
            ]);
        }

        // What of this the filer ended up covering themselves. A posting fact, so
        // it is recorded here rather than trusted from the form.
        $owed = collect($creditLines)->where('is_debt', true)->sum('amount');
        $data->forceFill(['reimbursed_amount' => $owed > 0 ? $owed : null])->saveQuietly();

        // Belt and braces. settlementLines() floors the float at zero, so this can
        // no longer fire on a normal path — it stays as a guard against a future
        // change that reintroduces the overdraw.
        $this->assertFloatNotOverdrawn($data);

        return $journal;
    }

    /**
     * Narrow a query to the expense rows this user is allowed to see.
     *
     * Three tiers, widest first:
     *
     *   view all expense      every company
     *   view company expense  their own company
     *   neither               only the rows they filed themselves
     *
     * The last tier is the DEFAULT, not a special case, and that is deliberate.
     * A role nobody remembered to grant `view company expense` sees too little
     * rather than too much — a forgotten grant should produce a confused
     * accountant, never a junior reading the whole group's spending.
     *
     * `company_id` empty on tier two means the user belongs to no company. That
     * is how a group-level account is set up, so it keeps the old behaviour of
     * seeing everything; the permission is what they were trusted with.
     *
     * Every query that returns expense ROWS must run through here. Taxonomy
     * (categories, departments, budgets) deliberately does not — an employee has
     * to see the shared categories to file anything at all.
     */
    private function applyExpenseVisibility($query): void
    {
        $user = auth()->user();

        if ($user->can('view all expense')) {
            return;
        }

        if ($user->can('view company expense')) {
            if (!empty($user->company_id)) {
                $query->where('expenses.company_id', $user->company_id);
            }

            return;
        }

        $query->where('expenses.user_id', $user->id);
    }

    /**
     * The single-record form of applyExpenseVisibility(): why this user may not
     * touch this expense, or null if they may.
     *
     * Returns the message rather than aborting because the delete path answers in
     * JSON and an abort() there would reach the browser as a bare 403 the user
     * never sees. Two wordings on purpose — "someone else filed that" and "that
     * is another company's" are different mistakes with different fixes.
     */
    private function expenseVisibilityError(Expense $expense): ?string
    {
        $user = auth()->user();

        if ($user->can('view all expense')) {
            return null;
        }

        if ($user->can('view company expense')) {
            return (!empty($user->company_id) && (int) $expense->company_id !== (int) $user->company_id)
                ? 'That expense belongs to a different company.'
                : null;
        }

        return (int) $expense->user_id !== (int) $user->id
            ? 'That expense was filed by someone else.'
            : null;
    }

    private function authoriseExpense(Expense $expense): void
    {
        $message = $this->expenseVisibilityError($expense);

        abort_if($message !== null, 403, (string) $message);
    }

    /**
     * Which pot this user may claim the money came out of.
     *
     * The entry form hides what it should not offer, but hiding a card removes
     * nothing: the fields still post, and a form submitted from a stale tab or by
     * hand carries whatever it likes. These two rules are where the restriction
     * actually lives; the view is only the courtesy version of them.
     *
     * Called by both store() and update() so a source refused on the way in
     * cannot be introduced on the way past.
     */
    private function assertSettlementSourceAllowed(Request $request): void
    {
        $user = auth()->user();

        // Whoever already oversees a company's spending. They keep every source:
        // the drawer is really theirs to spend, and settling another person's
        // float or filing a claim on their behalf is ordinary desk work.
        $oversees = $user->can('view all expense') || $user->can('view company expense');

        // Exactly one pot, or the posting is ambiguous. settlementAccountId()
        // resolves these in a fixed order, so a request naming two would silently
        // credit one and ignore the other — the form cannot produce that, but a
        // hand-made request can.
        $named = collect(['bank_id', 'petty_cash_float_id', 'reimburse_to_user_id'])
            ->filter(fn ($field) => $request->filled($field));

        abort_if(
            $named->count() > 1,
            422,
            'An expense comes out of one pot. Choose a bank, a petty cash float, or your own pocket — not more than one.'
        );

        abort_if(
            $request->filled('bank_id') && !$user->can('pay expense from bank'),
            403,
            'You are not allowed to record an expense paid from a company bank account.'
        );

        // Spending from a float you do not hold is an administrative act, so it
        // stays with the people who already oversee the company's expenses.
        // Everyone else gets their own pocket and no one else's.
        if ($request->filled('petty_cash_float_id') && !$oversees) {
            $custodianId = \App\Models\PettyCashFloat::whereKey($request->petty_cash_float_id)
                ->value('custodian_id');

            abort_if(
                (int) $custodianId !== (int) $user->id,
                403,
                'That petty cash float is held by someone else. You may only spend from your own.'
            );
        }

        // Claiming money back into someone else's name is how a reimbursement
        // becomes a way to pay a person. Only the desk may do it.
        abort_if(
            $request->filled('reimburse_to_user_id')
                && !$oversees
                && (int) $request->reimburse_to_user_id !== (int) $user->id,
            403,
            'You may only claim money back for yourself.'
        );

        // Naming no pot at all means the cash pot — the office drawer. That is a
        // real answer for the people who keep it, and a hole for everyone else:
        // it books money out of the group's central cash for someone who was
        // never handed any, leaving the pot short on paper and full in fact.
        //
        // So for them the drawer is not an option. Their own float and their own
        // pocket are, and both say something true about where the money was.
        abort_if(
            $named->isEmpty() && !$oversees,
            422,
            'Say where the money came from: your own petty cash float, or your own pocket to be paid back. '
                . 'The office cash pot can only be spent by the people who keep it.'
        );
    }

    private function respond(Request $request, bool $ok, string $message)
    {
        if ($request->ajax()) {
            return response()->json(['success' => $ok, 'message' => $message]);
        }

        return redirect()->back()->with($ok ? 'success' : 'error', $message);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return view('expenses.edit-modal', compact('id'));
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
        $data = Expense::findOrFail($id);
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!'
            ]);
        }

        $userCompanyId = auth()->user()->company_id;
        $canViewAll = auth()->user()->can('view all expense');
        $this->authoriseExpense($data);

        // Same gate store() applies, and before validation for the same reason: a
        // source refused on the way in must not be introducible on the way past.
        $this->assertSettlementSourceAllowed($request);

        // An approved expense is money already in the accounts, and editing it
        // used to wipe its journal and write a new one in its place — a closed
        // month could change amount, date or account with nothing left to show
        // it had. Reverse it first: that leaves both the original posting and
        // its reversal on the record, then the corrected expense is approved as
        // its own entry.
        if ($data->approval_status === Expense::APPROVED) {
            return $this->respond(
                $request,
                false,
                'That expense is already approved and posted. Reverse it first, then edit and approve the correction.'
            );
        }

        // Same as store(), and before validation for the same reason: "Others…"
        // is an answer, not an id, and every level it can be picked at is an id
        // column. See normaliseClassification().
        $this->normaliseClassification($request);

        $request->validate([
            // Optional now — the form no longer asks. resolveExpenseTitle() fills
            // it from the first cost line, because the column is NOT NULL and
            // seven screens read it.
            'title' => 'nullable|string|max:255',
            'expense_date' => 'required',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'expense_sub_category_id' => 'nullable|exists:expense_sub_categories,id',
            // An expense always leaves ONE company's books. A category may belong
            // to no company — that means shared, and it is true — but the expense
            // itself cannot: journal_entries.company_id is NOT NULL, so a blank
            // company did not save "for everyone", it 500d on the journal insert.
            'company_id' => 'required|exists:companies,id',
            'payment_mode' => 'nullable|in:cash,mobile_banking,bank_transfer,digital_wallet,other',
            // The cost centre. Required because it is the only thing that
            // separates a company's own costs from what it paid on everyone's
            // behalf — "Group Office" against "Common".
            'expense_department_id' => 'required|exists:expense_departments,id',
            'petty_cash_float_id' => 'nullable|exists:petty_cash_floats,id',
            // Who is owed, when the money was their own. Whether this user is
            // ALLOWED to name that person is decided by
            // assertSettlementSourceAllowed() before validation runs.
            'reimburse_to_user_id' => 'nullable|exists:users,id',
            'other_note' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.amount' => 'required|numeric|min:0',
        ], [
            'company_id.required' => 'Choose the company this expense belongs to — an expense always leaves the books of exactly one company.',
            'expense_department_id.required' => 'Choose the department this cost belongs to — it is what tells a company\'s own costs apart from the ones it paid on everyone\'s behalf.',
            'expense_category_id.required' => 'Choose the category this cost belongs to — pick Miscellaneous and type what it was if nothing on the list fits.',
            'expense_category_id.exists' => 'That expense category is not one on the list — pick Miscellaneous and type what it was if nothing else fits.',
            'expense_sub_category_id.exists' => 'That sub-category is not one on the list — choose “Others…” and type what it was instead.',
        ]);

        $companyId = (!$canViewAll && !empty($userCompanyId)) ? $userCompanyId : $request->company_id;

        if (!$canViewAll && !empty($userCompanyId)) {
            if ($request->filled('bank_id')) {
                $selectedBank = \App\Models\Bank::find($request->bank_id);
                abort_if(!$selectedBank || (int) $selectedBank->company_id !== (int) $userCompanyId, 403, 'Selected bank belongs to a different company.');
            }

            // A category with no company is shared by the whole group and every
            // company may book against it — which is nearly all of them. Comparing
            // the column straight to the user's company turned NULL into 0, so a
            // company-locked user could not file ANY expense; only a category
            // naming a DIFFERENT company is actually out of bounds.
            $selectedCategory = ExpenseCategory::find($request->expense_category_id);
            abort_if(!$selectedCategory, 403, 'Selected expense category no longer exists.');
            abort_if(
                $selectedCategory->company_id !== null
                    && (int) $selectedCategory->company_id !== (int) $userCompanyId,
                403,
                'Selected expense category belongs to a different company.'
            );

            if ($request->filled('expense_sub_category_id')) {
                $selectedSubCategory = ExpenseSubCategory::find($request->expense_sub_category_id);
                abort_if(!$selectedSubCategory, 403, 'Selected expense sub-category no longer exists.');
                abort_if(
                    $selectedSubCategory->company_id !== null
                        && (int) $selectedSubCategory->company_id !== (int) $userCompanyId,
                    403,
                    'Selected expense sub-category belongs to a different company.'
                );
            }
        }

        // An expense department belongs to exactly one company — that is what
        // lets the form fill the company in from it. The form keeps the two in
        // step; this makes sure a request that did not come from the form cannot
        // file an expense under one company and a department of another.
        if ($request->filled('expense_department_id')) {
            $dept = \App\Models\ExpenseDepartment::find($request->expense_department_id);

            abort_if(
                !$dept || (int) $dept->company_id !== (int) $companyId,
                422,
                'That department belongs to a different company than the one this expense is filed under.'
            );
        }

        // Same reasoning for a petty cash float, and it matters more here: the
        // float is real money held by a real person, and settling one company's
        // expense against another company's float would move cash between two sets
        // of books without either of them recording a transfer.
        if ($request->filled('petty_cash_float_id')) {
            $float = \App\Models\PettyCashFloat::find($request->petty_cash_float_id);

            abort_if(
                !$float || (int) $float->company_id !== (int) $companyId,
                422,
                'That petty cash float belongs to a different company than the one this expense is filed under.'
            );

            abort_if(!$float->status, 422, 'That petty cash float is closed.');
        }

        $items = collect($request->input('items', []))
            ->filter(fn($i) => isset($i['amount']) && $i['amount'] > 0);

        $total = $items->sum('amount');

        // Every cost line was blank or zero, so there is nothing to record.
        // Without this an expense could be filed for zero taka — the amount is
        // the sum of the lines, and 'items.*.amount => min:0' lets a 0 through
        // validation, so submitting the form with an untouched line stored a
        // real expense worth nothing and a balanced zero journal behind it.
        if ($total <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Add at least one cost line with an amount above zero — the expense total is what those lines add up to.',
            ]);
        }

        try {
        DB::transaction(function () use ($request, $data, $items, $total, $companyId) {

            // Handle attachment upload
            if ($request->hasFile('attachment')) {
                $upload_path = 'image/expense/';
                if (!file_exists(public_path($upload_path))) {
                    mkdir(public_path($upload_path), 0777, true);
                }

                if (!empty($data->attachment) && file_exists(public_path($data->attachment))) {
                    unlink(public_path($data->attachment));
                }

                $file = $request->file('attachment');
                $filename = uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path($upload_path), $filename);
                $data->attachment = $upload_path . $filename;
            }

            $bank = \App\Models\Bank::find($request->bank_id);
            $data->update([
                'company_id' => $companyId,
                // Sub-category first, exactly as store() resolves it. Passing the
                // category alone re-derived the account one level too coarse on
                // every edit, so an expense filed to 8101 Tea & Snacks moved to
                // 8100 Food & Beverage the first time anyone touched it.
                'account_id' => $request->account_id
                    ?: app(ExpenseClassificationService::class)->accountFor(
                        $request->expense_category_id,
                        $request->expense_sub_category_id
                    ),
                'expense_department_id' => $request->expense_department_id,
                'expense_category_id' => $request->expense_category_id,
                'expense_sub_category_id' => $request->expense_sub_category_id,
                'other_note' => $request->other_note,
                'title' => $this->resolveExpenseTitle($request),
                'description' => $request->description,
                'amount' => $total,
                'bank_id' => $request->bank_id,
                'petty_cash_float_id' => $request->petty_cash_float_id ?: null,
                'reimburse_to_user_id' => $request->reimburse_to_user_id ?: null,
                'payment_mode' => $request->payment_mode ?: 'cash',
                'reference' => $request->reference,
                'expense_date' => $request->expense_date,
                'status' => $request->status ? 1 : 0
            ]);

            // Replace old items with new ones
            $data->items()->delete();
            foreach ($items as $item) {
                ExpenseItem::create([
                    'expense_id'  => $data->id,
                    'description' => $item['description'] ?? null,
                    'amount'      => $item['amount'],
                ]);
            }

            // NO JOURNAL HERE. Only a PENDING expense reaches this method —
            // update() refuses an approved one above — and a pending expense has
            // no posting yet. It gets exactly one, written by approve(), from the
            // values as they stand at that moment. That is what stops an edit
            // from silently rewriting a posting that has already been reported.
        });
        } catch (\App\Exceptions\PettyCashOverdrawnException $e) {
            // Raising an edited expense above what the custodian holds is refused
            // the same way a new one is — and the transaction has already put the
            // old posting back.
            return $request->ajax()
                ? response()->json(['success' => false, 'message' => $e->getMessage()])
                : redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data updated successfully.',
                'data' => $data
            ]);
        }

        return redirect('/super-admin/airport')->with('success', 'Data updated successfully.');
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
            $item = Expense::find($request->item_id);
            if ($item) {
                if ($message = $this->expenseVisibilityError($item)) {
                    return response()->json([
                        'success' => false,
                        'message' => $message
                    ]);
                }

                // An approved expense is money in the accounts. Deleting it used
                // to forceDelete() its journal — the expense soft-deleted and
                // recoverable, its posting gone for good, and a month that had
                // already been reported quietly worth less than it was. Reverse
                // it instead, which needs `approve expense`, then delete.
                if ($item->approval_status === Expense::APPROVED) {
                    return response()->json([
                        'success' => false,
                        'message' => 'That expense is approved and posted to the ledger. Reverse it first, then delete it.',
                    ]);
                }

                DB::transaction(function () use ($request, $item) {
                    // A pending expense has no posting of its own, but it may
                    // still carry a reversed pair from an earlier approval. Those
                    // are left exactly where they are: they are the record of what
                    // was posted and undone, and deleting the expense does not
                    // make that stop having happened.
                    $item->delete();
                });
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

    /**
     * Which account the money actually left — the credit side of the posting.
     *
     * A bank was named, so the money left that bank; otherwise it came out of the
     * cash box, and office cash is the account that holds it. That is the same
     * rule LoanLedgerService applies to a cash repayment, so the two never
     * disagree about where cash lives.
     *
     * Reading the BANK rather than payment_mode on purpose: the two can drift —
     * somebody picks "cash" and then names a bank, or the reverse — and the bank
     * is the specific claim. payment_mode only decides whether the form asks for
     * a bank at all.
     *
     * A petty cash float outranks both. Money spent from a custodian's pocket did
     * not leave the drawer today — it left weeks ago, when it was issued — so
     * crediting office cash here would take the same money out twice and leave the
     * custodian holding a balance they had already spent. Crediting the float is
     * what makes their balance fall as receipts come in.
     */
    /**
     * Who the credit side belongs to, when it belongs to anyone.
     *
     * Only a petty cash settlement has a party: one float account holds every
     * custodian's money, and party_id is the only thing separating Karim's ৳2,000
     * from Rahim's. Leave it off and the spend still balances, but it lands in the
     * pool without a name and every custodian's balance is wrong from then on.
     *
     * A bank or the drawer needs none — the account itself is the answer.
     */
    private function settlementParty(\App\Models\Expense $expense): array
    {
        // One payable account carries what is owed to everyone, exactly as one
        // float account carries what every custodian holds. Without the party the
        // claim still balances and the total is right, but "how much do we owe
        // Afiqur" becomes unanswerable — and that is the only question the
        // account exists to answer.
        if ($expense->reimburse_to_user_id) {
            return ['party_type' => 'employee', 'party_id' => $expense->reimburse_to_user_id];
        }

        if (!$expense->petty_cash_float_id) {
            return [];
        }

        $custodianId = \App\Models\PettyCashFloat::whereKey($expense->petty_cash_float_id)
            ->value('custodian_id');

        return $custodianId
            ? ['party_type' => 'employee', 'party_id' => $custodianId]
            : [];
    }

    /**
     * A custodian cannot spend cash they are not holding.
     *
     * Called AFTER the journal is written, on purpose: by then the float's balance
     * already reflects this expense, so one check covers both a new expense and an
     * edit that raised the amount — no need to reason about what the old posting
     * contributed. Everything runs inside DB::transaction(), so throwing here
     * unwinds the expense, its items and its journal together.
     *
     * The same rule PettyCashService already applies to a return: you cannot hand
     * back more than you hold. A negative float means either the receipt belongs to
     * a different pocket, or cash was handed over and never recorded.
     */
    private function assertFloatNotOverdrawn(\App\Models\Expense $expense): void
    {
        if (!$expense->petty_cash_float_id) {
            return;
        }

        $float = \App\Models\PettyCashFloat::with('custodian:id,name')->find($expense->petty_cash_float_id);

        if (!$float) {
            return;
        }

        $balance = round(app(\App\Services\PettyCashService::class)->balanceOf($float), 2);

        if ($balance >= 0) {
            return;
        }

        // What they had before this expense landed.
        $available = round($balance + (float) $expense->amount, 2);

        throw new \App\Exceptions\PettyCashOverdrawnException(
            ($float->custodian->name ?? 'That custodian') . ' is holding only '
            . number_format($available, 2) . ', so an expense of '
            . number_format((float) $expense->amount, 2) . ' cannot be settled from that float. '
            . 'Issue the cash first, or pay this from office cash.'
        );
    }

    /**
     * The credit side of one expense, as one line or two.
     *
     * Two only in the case this method exists for: a custodian who spent more
     * than they were holding. Sending someone out with ৳1,000 and having them
     * come back having spent ৳1,500 is ordinary — the shop had what was wanted,
     * the fare was higher, a second errand came up — and until now the ERP simply
     * refused it, because crediting the float would drive it negative and
     * assertFloatNotOverdrawn() stopped that. The receipt then had to be entered
     * twice, once against the float and once as a claim, which is two rows for
     * one purchase and a total nobody could tie back.
     *
     * So the float gives what it has and the rest is what the custodian covered:
     *
     *     Dr  expense                    1,500
     *         Cr  float (their pocket)   1,000   ← emptied, never overdrawn
     *         Cr  owed to them             500   ← their own money
     *
     * The float lands on exactly zero rather than minus five hundred, which is
     * both true and the only version that does not read as the company owing its
     * own cash to a member of staff.
     *
     * Balances are read HERE, at posting time, not when the expense was typed.
     * Two receipts filed against the same float on the same morning therefore
     * split against what was actually left when each of them was approved.
     */
    private function settlementLines(\App\Models\Expense $expense): array
    {
        $amount = round((float) $expense->amount, 2);

        // A float is the only source that can run short, so it is the only one
        // that splits. Checked first for that reason — on an overspend the ledger
        // already records what is owed, and re-posting after a reversal must
        // re-split rather than treat the whole amount as a claim.
        if ($expense->petty_cash_float_id) {
            $float = \App\Models\PettyCashFloat::with('custodian:id,name')
                ->find($expense->petty_cash_float_id);

            if (!$float) {
                throw new \Exception('That petty cash float no longer exists.');
            }

            $who  = $float->custodian->name ?? 'custodian';
            $held = round(app(\App\Services\PettyCashService::class)->balanceOf($float), 2);

            // An empty or already-overdrawn pocket covers nothing, so max(0, …).
            $fromFloat  = max(0, min($held, $amount));
            $fromPocket = round($amount - $fromFloat, 2);

            $lines = [];

            if ($fromFloat > 0) {
                $lines[] = [
                    'account_id' => (int) $float->account_id,
                    'amount'     => $fromFloat,
                    'note'       => 'Expense paid — ' . $expense->title,
                    'party_type' => 'employee',
                    'party_id'   => $float->custodian_id,
                ];
            }

            if ($fromPocket > 0) {
                $lines[] = [
                    'account_id' => app(\App\Services\EmployeeReimbursementService::class)->payableAccount()->id,
                    'amount'     => $fromPocket,
                    'note'       => 'Paid by ' . $who . ' beyond their float — ' . $expense->title,
                    'party_type' => 'employee',
                    'party_id'   => $float->custodian_id,
                    'is_debt'    => true,
                ];
            }

            return $lines;
        }

        // Everything else comes from exactly one place.
        return [array_merge(
            [
                'account_id' => $this->settlementAccountId($expense),
                'amount'     => $amount,
                'note'       => 'Expense paid — ' . $expense->title,
                'is_debt'    => (bool) $expense->reimburse_to_user_id,
            ],
            $this->settlementParty($expense)
        )];
    }

    /**
     * Record that this expense settled a DM renewal period.
     *
     * Only fires when the form was opened from the Subscriptions desk, which is
     * the only place the dm_* inputs come from. A hand-filed expense never
     * carries them and is completely unaffected by this method.
     *
     * The row stores the PAYMENT, not the commitment — DM remains the register
     * and is never written to (its API is read-only anyway). The amount is what
     * actually left the accounts in taka, which for a document renewal is the
     * only figure that exists at all: DM carries no cost for those, so this is
     * what pre-fills the same licence next year.
     */
    private function linkDmRenewal(Request $request, \App\Models\Expense $expense): void
    {
        if (! $request->filled(['dm_source_type', 'dm_id', 'dm_due_date'])) {
            return;
        }

        \App\Models\DmRenewalPayment::create([
            'source_type' => $request->input('dm_source_type'),
            'dm_id'       => (int) $request->input('dm_id'),
            // Falls back to dm_id so the column is never null for a subscription,
            // where the DM row and the commitment are the same thing.
            'dm_group_id' => (int) ($request->input('dm_group_id') ?: $request->input('dm_id')),
            'due_date'    => $request->input('dm_due_date'),
            // Captured now because DM rows get deleted, and a payment whose
            // subject cannot be named is useless as a record.
            'title'       => (string) ($request->input('dm_title') ?: $expense->title),
            // The expense total, in taka. NOT the DM figure: a subscription billed
            // at USD 65.00 leaves the bank as several thousand taka, and it is the
            // taka that the ledger and next year's estimate both need.
            'amount'      => $expense->amount,
            'currency'    => 'BDT',
            'expense_id'  => $expense->id,
            'recorded_by' => auth()->id(),
            'paid_at'     => now(),
        ]);
    }

    /**
     * Is this the renewal-period unique index, rather than some other constraint?
     *
     * Matched on the index name so a violation elsewhere in the insert can never
     * be reported as "already paid" — that would turn a real fault into a
     * reassuring message and hide it.
     */
    private function isDuplicateRenewalPayment(\Illuminate\Database\QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062
            && str_contains((string) $e->getMessage(), 'dm_renewal_period_unique');
    }

    private function settlementAccountId(\App\Models\Expense $expense): int
    {
        // Their money, not the company's. Outranks every source below because it
        // is the one case where no company cash moved at all: nothing left the
        // drawer, no float shrank, no bank was touched. What changed is that the
        // company now owes a person, and that is a liability.
        //
        // Crediting cash here instead would be the mirror of the float bug this
        // method already guards against — it would report money leaving a pot
        // that still holds every taka of it.
        if ($expense->reimburse_to_user_id) {
            $code = config('accounts.employee_reimbursement_payable');

            $account = \App\Models\Account::where('code', $code)->where('status', 1)->first();

            if (!$account) {
                throw new \Exception(
                    'The employee reimbursement account (' . $code . ') is missing from the chart of '
                    . 'accounts, so a claim cannot be posted. Run php artisan accounts:check.'
                );
            }

            return (int) $account->id;
        }

        if ($expense->petty_cash_float_id) {
            $float = \App\Models\PettyCashFloat::find($expense->petty_cash_float_id);

            if (!$float) {
                throw new \Exception('That petty cash float no longer exists.');
            }

            return (int) $float->account_id;
        }

        if ($expense->bank_id) {
            $bank = \App\Models\Bank::find($expense->bank_id);

            if (!$bank || !$bank->account_id) {
                throw new \Exception('That bank is not linked to a chart-of-accounts account.');
            }

            return (int) $bank->account_id;
        }

        // Neither a float nor a bank, so it came out of the cash pot: the small
        // petty cash pool when one is configured and exists, and Office Cash
        // otherwise — which is what this branch did before the pool existed.
        // Resolved through PettyCashService so an expense and a petty cash issue
        // filed the same minute cannot credit two different accounts.
        return (int) app(\App\Services\PettyCashService::class)->cashPotAccount()->id;
    }

}
