<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseReimbursement;
use App\Models\PettyCashFloat;
use App\Models\PettyCashTransaction;
use App\Models\User;
use App\Services\DailyFundService;
use App\Services\EmployeeReimbursementService;
use App\Services\PettyCashService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The petty cash desk — who is holding company money, and what they did with it.
 *
 * Balances are never stored. Every figure on these screens is read back from the
 * ledger by PettyCashService, because the same account is written by three
 * different flows (issue, return, and the expense form) and a cached total would
 * only have to be right in three places at once.
 *
 * Company scoping follows the rest of the expense desk: without "view all
 * expense" you see and touch your own company's floats only.
 */
class PettyCashController extends Controller
{
    public function __construct(
        private PettyCashService $pettyCash,
        private DailyFundService $dailyFund
    ) {
    }

    /**
     * Who may be handed company cash: an active employee, and nobody else.
     *
     * The list used to be every non-superadmin user, which meant the dropdown
     * offered 106 names — 71 of them vendors, agents and customers who are only
     * in `users` because the travel desk books against them, and 19 employees
     * who have since resigned. Handing a float to any of them creates a cash
     * balance nobody is employed to account for.
     *
     * `->where('status', 'active')->role('employee')` is the app's existing
     * definition of "a current member of staff" — the same pair
     * AdvanceSalaryController, EmployeeSalaryController, LoanController and
     * PayslipController already use. Kept in one method because both the
     * dropdown and store()'s validation read it: a picker and a guard that
     * disagree are how an ineligible custodian gets in anyway.
     */
    private function eligibleCustodians(?int $scopedCompanyId = null)
    {
        return User::where('is_super_admin', 0)
            ->where('status', 'active')
            ->role('employee')
            ->when($scopedCompanyId, fn ($q) => $q->where('company_id', $scopedCompanyId))
            ->orderBy('name');
    }

    public function index(Request $request)
    {
        $userCompanyId = auth()->user()->company_id;
        $canViewAll = auth()->user()->can('view all expense');

        $query = PettyCashFloat::with(['custodian:id,name', 'company:id,name,short_name', 'account:id,code,name']);

        if (!$canViewAll && !empty($userCompanyId)) {
            $query->where('company_id', $userCompanyId);
        } elseif ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->filled('custodian_id')) {
            $query->where('custodian_id', $request->custodian_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $floats = $query->orderByDesc('status')->orderBy('id')->get();
        $balances = $this->pettyCash->balancesFor($floats);

        // Counted over every float on screen, not the ones that happen to be
        // active — "held" is the company's exposure, and a closed float still
        // holding cash is exactly the thing worth seeing.
        //
        // There is no limit figure here any more. This office hands over whatever
        // the day needs rather than topping a fixed float back up (the fluctuating
        // balance system, not the imprest one), so a ceiling described nothing and
        // only ever coloured rows red. What matters is what is held and what has
        // been accounted for.
        // The pot itself — what is left to issue FROM, before anything reaches a
        // pocket. Worth its own figure because record() now refuses an issue the
        // pot cannot cover, so "why was I refused" and "when do I top up" are the
        // same question and this is its answer. Wrapped because a broken chart
        // must surface when something is posted, not by taking this desk down.
        try {
            $potName    = $this->pettyCash->cashPotAccount()->name;
            $potBalance = $this->pettyCash->cashPotBalance();
        } catch (\Throwable $e) {
            $potName    = 'Cash pot';
            $potBalance = 0.0;
        }

        $summary = [
            'custodians'  => $floats->where('status', true)->count(),
            'total_held'  => array_sum($balances),
            'pot_name'    => $potName,
            'pot_balance' => $potBalance,
            'total_spent' => (float) Expense::whereIn('petty_cash_float_id', $floats->pluck('id'))
                ->where('status', 1)
                ->sum('amount'),
        ];

        // Per-float spend, in one grouped query rather than one per row.
        $spentByFloat = Expense::whereIn('petty_cash_float_id', $floats->pluck('id'))
            ->where('status', 1)
            ->groupBy('petty_cash_float_id')
            ->pluck(DB::raw('SUM(amount)'), 'petty_cash_float_id')
            ->map(fn ($v) => (float) $v)
            ->all();

        // ── The other side of the same relationship ──────────────────────
        // A float is company money sitting in someone's pocket. A reimbursement
        // is a pocket the company has emptied and not refilled. Both answer the
        // one question this desk exists for — "where do I stand with this
        // person" — so they belong on one screen.
        //
        // Shown side by side, never netted. Held is an asset (1015) and owed is a
        // liability (2240); subtracting one from the other would put a single
        // figure on screen that appears nowhere in the accounts.
        //
        // Wrapped, because a chart missing the payable account must not take the
        // petty cash desk down with it — the floats above are still true.
        try {
            $owedRows = app(EmployeeReimbursementService::class)->outstanding(
                (!$canViewAll && !empty($userCompanyId))
                    ? (int) $userCompanyId
                    : ($request->filled('company_id') ? (int) $request->company_id : null)
            );
        } catch (\Throwable $e) {
            $owedRows = collect();
        }

        // Keyed by person AND company: a claim filed under one company's books is
        // settled out of that company's cash, so the two must not be added up.
        $owedByPerson = $owedRows->mapWithKeys(
            fn ($r) => [$r->user_id . '|' . $r->company_id => (float) $r->owed]
        );

        // Anyone owed money who holds no float at all. They have never been issued
        // cash, so a page built only from float rows would never show them — and
        // being owed ৳500 with no float is the most ordinary case there is.
        $floatKeys = $floats->map(fn ($f) => $f->custodian_id . '|' . $f->company_id)->all();

        $claimOnly = $owedRows
            ->reject(fn ($r) => in_array($r->user_id . '|' . $r->company_id, $floatKeys, true))
            ->values();

        $summary['total_owed'] = (float) $owedRows->sum('owed');

        // Recent payments, so a wrong one can be reversed from the same screen
        // that made it. Without this a mistake would have nowhere to be undone.
        $reimbursementPayments = ExpenseReimbursement::with(['user:id,name', 'company:id,name', 'bank:id,name', 'creator:id,name'])
            ->when(!$canViewAll && !empty($userCompanyId), fn ($q) => $q->where('company_id', $userCompanyId))
            ->latest('paid_on')->latest('id')
            ->limit(20)
            ->get();

        $companies = (!$canViewAll && !empty($userCompanyId))
            ? Company::where('id', $userCompanyId)->get()
            : Company::orderBy('name')->get();

        // Who the "New Float" modal may offer.
        $custodians = $this->eligibleCustodians(
            (!$canViewAll && !empty($userCompanyId)) ? (int) $userCompanyId : null
        )->get(['id', 'name', 'company_id']);

        // Who the FILTER above the table may offer — a different question with a
        // different answer. Narrowing this to active employees too would hide any
        // float whose holder has since resigned or was recorded before this rule
        // existed, leaving live cash on screen that cannot be filtered to. So it
        // lists the people who actually hold a float, which is also the only set
        // the filter can ever return a row for.
        // Built from its own query, NOT from $query: by this point $query already
        // carries the custodian filter, so reusing it would leave the dropdown
        // holding only the name already selected and no way back to the others.
        $custodianFilters = User::whereIn(
            'id',
            PettyCashFloat::query()
                ->when(!$canViewAll && !empty($userCompanyId), fn ($q) => $q->where('company_id', $userCompanyId))
                ->select('custodian_id')
        )->orderBy('name')->get(['id', 'name', 'company_id']);

        $banks = Bank::where('status', 1)
            ->when(!$canViewAll && !empty($userCompanyId), fn ($q) => $q->where('company_id', $userCompanyId))
            ->orderBy('name')
            ->get(['id', 'name', 'company_id']);

        // ── The daily cost fund ──────────────────────────────────────────
        // What each company MAY spend in cash on this date, against what it
        // already has. A ceiling, not a pot: nothing below is money, nothing
        // below posts to the ledger, and the figures the rest of this page
        // shows are unaffected by it. See DailyFundService.
        // Deliberately NOT narrowed by the company/custodian/status filters
        // below: those belong to the float table, and this is its own card with
        // its own date. Two cards, two scopes, so neither can be read as the
        // other's filter having silently failed.
        $fundDate = $this->fundDate($request);
        $dailyFund = $this->dailyFund->summaryOn($fundDate, $companies);

        return view('petty-cash.index', compact(
            'floats',
            'balances',
            'spentByFloat',
            'summary',
            'companies',
            'custodians',
            'custodianFilters',
            'banks',
            'dailyFund',
            'fundDate',
            'owedByPerson',
            'claimOnly',
            'reimbursementPayments'
        ));
    }

    /**
     * Set today's (or another day's) ceiling for one or more companies.
     *
     * Posted as a map of company id to amount, because the modal edits the whole
     * table at once — twelve separate saves would leave the set half-applied if
     * one failed. A blank amount clears that company's fund rather than setting
     * it to zero: "no ceiling configured" and "may spend nothing" are different
     * instructions and the panel shows them differently.
     *
     * Writes NO journal entry. If this method is ever found creating one,
     * something has gone badly wrong — see DailyFundService.
     */
    public function saveDailyFunds(Request $request)
    {
        abort_if(
            !auth()->user()->can('create expense'),
            403,
            'You do not have permission to set the daily cost fund.'
        );

        $validated = $request->validate([
            'effective_from' => ['required', 'date'],
            'funds'          => ['required', 'array'],
            'funds.*'        => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'note'           => ['nullable', 'string', 'max:255'],
        ], [
            'funds.required' => 'Nothing was sent to save.',
            'funds.*.min'    => 'A daily fund cannot be negative.',
        ]);

        $date = \Illuminate\Support\Carbon::parse($validated['effective_from'])->toDateString();
        $saved = 0;
        $cleared = 0;

        try {
            DB::transaction(function () use ($validated, $date, &$saved, &$cleared) {
                foreach ($validated['funds'] as $companyId => $amount) {
                    // Whatever the form posts, a company-locked user may only
                    // ever set their own company's ceiling.
                    $this->authoriseCompany((int) $companyId);

                    $isBlank = $amount === null || $amount === '';

                    $this->dailyFund->save(
                        (int) $companyId,
                        $isBlank ? null : (float) $amount,
                        $date,
                        $validated['note'] ?? null
                    );

                    $isBlank ? $cleared++ : $saved++;
                }
            });
        } catch (\Throwable $e) {
            return $this->fail($request, $e->getMessage());
        }

        $parts = [];
        if ($saved)   { $parts[] = $saved . ' fund' . ($saved === 1 ? '' : 's') . ' set'; }
        if ($cleared) { $parts[] = $cleared . ' cleared'; }

        return $this->done(
            $request,
            (empty($parts) ? 'Nothing changed' : ucfirst(implode(', ', $parts)))
                . ' from ' . \Illuminate\Support\Carbon::parse($date)->format('j M Y')
                . '. No money has moved — this only sets a spending ceiling.'
        );
    }

    /**
     * What made up one company's cash spend on a day — the drill-down behind a
     * progress bar.
     */
    public function dailyFundBreakdown($role, Request $request, Company $company)
    {
        $this->authoriseCompany((int) $company->id);

        $date = $this->fundDate($request);
        $rows = $this->dailyFund->breakdownFor($company->id, $date);
        $funds = $this->dailyFund->fundsOn($date, [$company->id]);

        return response()->json([
            'success'  => true,
            'company'  => $company->short_name ?: $company->name,
            'date'     => \Illuminate\Support\Carbon::parse($date)->format('j M Y'),
            'has_fund' => array_key_exists($company->id, $funds),
            'fund'     => (float) ($funds[$company->id] ?? 0),
            'spent'    => (float) $rows->sum('amount'),
            'rows'     => $rows,
        ]);
    }

    /**
     * The day the fund panel is showing.
     *
     * A hand-typed or stale `fund_date` shows today rather than throwing — this
     * is a GET parameter on a page people bookmark, and a 500 is a poor answer
     * to a typo.
     */
    private function fundDate(Request $request): string
    {
        $raw = $request->query('fund_date');

        if (!$raw) {
            return now()->toDateString();
        }

        try {
            return \Illuminate\Support\Carbon::parse($raw)->toDateString();
        } catch (\Throwable $e) {
            return now()->toDateString();
        }
    }

    /**
     * Company-locked users may only touch their own company — the same rule
     * authoriseFloat() applies, stated for a company id rather than a float.
     */
    private function authoriseCompany(int $companyId): void
    {
        $userCompanyId = auth()->user()->company_id;

        abort_if(
            !auth()->user()->can('view all expense')
                && !empty($userCompanyId)
                && $companyId !== (int) $userCompanyId,
            403,
            'That company is not yours to set a daily fund for.'
        );
    }

    /**
     * One custodian's statement — every movement, oldest first, with the running
     * balance beside it.
     *
     * Issues, returns and spending are three different tables, so they are merged
     * here rather than joined: the ledger already agrees on the total, and this
     * view exists to show a person what makes it up.
     */
    public function statement($role, PettyCashFloat $float)
    {
        return view('petty-cash.statement', $this->statementData($float));
    }

    /**
     * Where the cash went over a period, and who is holding what now.
     *
     * A different question from the Expense Report, which is why it is a
     * different page. That one reads `expenses` and answers "what did the money
     * buy" — categories, departments, a total spend. Cash handed to a custodian
     * is not an expense and never appears there; nor is cash handed back. Forcing
     * them in would inflate its totals with money that was only moved.
     *
     * This one reads the float ledger instead: opening, issued, spent, returned,
     * closing, per person. The two meet on the spend column and disagree nowhere,
     * because both ultimately count the same journal lines.
     */
    public function report(Request $request)
    {
        return view('petty-cash.report', $this->reportData($request));
    }

    /** The same report, laid out for paper. */
    public function reportPrint(Request $request)
    {
        $data = $this->reportData($request);
        $data['company']   = Company::first();
        $data['printedBy'] = auth()->user()?->name;

        return view('petty-cash.print-report', $data);
    }

    private function reportData(Request $request): array
    {
        $userCompanyId = auth()->user()->company_id;
        $canViewAll    = auth()->user()->can('view all expense');

        $window = $this->resolveReportWindow($request);

        $scopeCompanyId = (!$canViewAll && !empty($userCompanyId))
            ? (int) $userCompanyId
            : ($request->filled('company_id') ? (int) $request->company_id : null);

        // Every float that could have moved in the window, not just the open ones
        // — a float closed last week still spent money this month, and leaving it
        // out would make the period's totals smaller than the ledger's.
        $floats = PettyCashFloat::with(['custodian:id,name', 'company:id,name,short_name'])
            ->when($scopeCompanyId, fn ($q) => $q->where('company_id', $scopeCompanyId))
            ->when($request->filled('custodian_id'), fn ($q) => $q->where('custodian_id', $request->custodian_id))
            ->orderBy('id')
            ->get();

        $rows = collect($this->pettyCash->movementReport($floats, $window['from'], $window['to']))
            ->map(function ($row) {
                $float = $row['float'];

                $row['custodian_id']   = $float->custodian_id;
                $row['custodian_name'] = $float->custodian->name ?? 'Custodian';
                $row['company_id']     = $float->company_id;
                $row['company_name']   = $float->company->short_name ?: ($float->company->name ?? '—');
                $row['float_id']       = $float->id;
                $row['active']         = (bool) $float->status;
                $row['owed']           = $this->owedToCustodian($float);

                return $row;
            })
            // A pocket with no opening balance and nothing at all in the window is
            // noise on a period report — it says only that this person exists.
            ->reject(fn ($r) => abs($r['opening']) < 0.001 && abs($r['closing']) < 0.001
                && abs($r['issued']) < 0.001 && abs($r['spent']) < 0.001 && abs($r['returned']) < 0.001)
            ->sortByDesc('closing')
            ->values();

        $totals = [
            'opening'  => (float) $rows->sum('opening'),
            'issued'   => (float) $rows->sum('issued'),
            'spent'    => (float) $rows->sum('spent'),
            'returned' => (float) $rows->sum('returned'),
            'other'    => (float) $rows->sum('other'),
            'closing'  => (float) $rows->sum('closing'),
            'owed'     => (float) $rows->sum('owed'),
        ];

        $companies = (!$canViewAll && !empty($userCompanyId))
            ? Company::where('id', $userCompanyId)->get()
            : Company::orderBy('name')->get();

        $custodianFilters = User::whereIn(
            'id',
            PettyCashFloat::query()
                ->when($scopeCompanyId, fn ($q) => $q->where('company_id', $scopeCompanyId))
                ->select('custodian_id')
        )->orderBy('name')->get(['id', 'name']);

        return array_merge($window, compact('rows', 'totals', 'companies', 'custodianFilters'));
    }

    /**
     * Daily / weekly / monthly / custom, resolved exactly the way the expense
     * report resolves them.
     *
     * Same parameter names, same defaults, same labels — the two reports sit one
     * tab apart and a period control that behaved differently on each would be a
     * trap rather than a feature.
     */
    private function resolveReportWindow(Request $request): array
    {
        $period = strtolower($request->input('period', 'monthly'));

        if (!in_array($period, ['daily', 'weekly', 'monthly', 'custom'], true)) {
            $period = 'monthly';
        }

        $anchor        = Carbon::parse($request->input('date', now()->toDateString()));
        $from          = $anchor->copy()->startOfDay();
        $to            = $anchor->copy()->endOfDay();
        $label         = $anchor->format('d M Y');
        $selectedMonth = (int) $anchor->month;
        $selectedYear  = (int) $anchor->year;
        $customFrom    = null;
        $customTo      = null;

        if ($period === 'weekly') {
            $from  = $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $to    = $from->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
            $label = $from->format('d M Y') . ' to ' . $to->format('d M Y');
        } elseif ($period === 'monthly') {
            $selectedMonth = (int) $request->input('month', now()->month);
            $selectedYear  = (int) $request->input('year', now()->year);
            $from  = Carbon::create($selectedYear, $selectedMonth, 1)->startOfMonth();
            $to    = $from->copy()->endOfMonth();
            $label = $from->format('F Y');
        } elseif ($period === 'custom') {
            $customFrom = $request->input('from', now()->startOfMonth()->toDateString());
            $customTo   = $request->input('to', now()->toDateString());
            $from = Carbon::parse($customFrom)->startOfDay();
            $to   = Carbon::parse($customTo)->endOfDay();

            // Dates the wrong way round are a slip, not a request for no rows.
            if ($to->lt($from)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }

            $customFrom = $from->toDateString();
            $customTo   = $to->toDateString();
            $label      = $from->format('d M Y') . ' to ' . $to->format('d M Y');
        }

        return [
            'period'        => $period,
            'from'          => $from->toDateString(),
            'to'            => $to->toDateString(),
            'periodLabel'   => $label,
            'selectedMonth' => $selectedMonth,
            'selectedYear'  => $selectedYear,
            'customFrom'    => $customFrom,
            'customTo'      => $customTo,
        ];
    }

    /**
     * The same statement, laid out for paper.
     *
     * A separate view rather than a print stylesheet on the screen one: the page
     * this is read from carries the app shell, a sidebar and a tab bar, and none
     * of that belongs on something handed to a custodian to sign.
     */
    public function printStatement($role, PettyCashFloat $float)
    {
        $data = $this->statementData($float);
        $data['company'] = $float->company ?? Company::first();
        $data['printedBy'] = auth()->user()?->name;

        return view('petty-cash.print-statement', $data);
    }

    /**
     * Everything both statement views need, built once.
     */
    private function statementData(PettyCashFloat $float): array
    {
        $this->authoriseFloat($float);

        $float->load(['custodian:id,name', 'company:id,name,short_name', 'account:id,code,name']);

        $movements = $float->transactions()->with('bank:id,name')->get()
            ->map(fn (PettyCashTransaction $t) => [
                // Normalised to a plain Y-m-d string, and so is the expense side
                // below. A transaction's `date` arrives as a Carbon instance and an
                // expense's as a string, and sorting a mixed column compared the two
                // against each other — which is why a receipt could sit BELOW the
                // purchases it paid for and drive the running balance negative.
                'date'   => \Illuminate\Support\Carbon::parse($t->date)->toDateString(),
                'type'   => $t->type,
                'label'  => $t->type === PettyCashTransaction::TYPE_ISSUE
                    ? 'Cash received' . ($t->bank ? ' from ' . $t->bank->name : ' from office cash')
                    : 'Cash returned' . ($t->bank ? ' to ' . $t->bank->name : ' to office cash'),
                'note'   => $t->note,
                'in'     => $t->type === PettyCashTransaction::TYPE_ISSUE ? (float) $t->amount : 0.0,
                'out'    => $t->type === PettyCashTransaction::TYPE_RETURN ? (float) $t->amount : 0.0,
                'ref'    => $t->id,
            ])
            ->concat(
                Expense::where('petty_cash_float_id', $float->id)
                    ->where('status', 1)
                    ->with(['expense_category:id,name', 'expense_sub_category:id,name'])
                    ->get()
                    ->map(function (Expense $e) {
                        // Only what the FLOAT paid, not what the receipt said.
                        // A custodian holding ৳170 who spends ৳500 covered ৳330
                        // themselves; the float gave ৳170 and the rest became a
                        // debt on the payable account. Showing the whole ৳500
                        // here drove the running balance to minus ৳330 and left
                        // the column disagreeing with the pocket figure above it,
                        // which is read straight from the ledger.
                        $owed      = (float) ($e->reimbursed_amount ?? 0);
                        $fromFloat = round((float) $e->amount - $owed, 2);

                        return [
                            'date'  => \Illuminate\Support\Carbon::parse($e->expense_date)->toDateString(),
                            'type'  => 'expense',
                            'label' => $e->title,
                            'note'  => collect([
                                $e->expense_category?->name,
                                $e->expense_sub_category?->name,
                            ])->filter()->implode(' › '),
                            'in'    => 0.0,
                            'out'   => $fromFloat,
                            // Said out loud on the row rather than left to be
                            // inferred from a total that no longer matches the
                            // receipt: the difference is money this person is
                            // still owed, and that is worth reading.
                            'owed'  => $owed,
                            'total' => (float) $e->amount,
                            'ref'   => $e->id,
                        ];
                    })
            )
            // Money in before money out on the same day. The date column has no
            // time on it, so within one date the order is a choice — and cash has
            // to arrive before it can be spent. Sorting by id instead put an
            // 11 Aug receipt below an 11 Aug purchase and showed the balance
            // going negative on a float that was never overdrawn.
            ->sortBy([['date', 'asc'], ['in', 'desc'], ['ref', 'asc']])
            ->values();

        $running = 0.0;
        $movements = $movements->map(function ($m) use (&$running) {
            $running += $m['in'] - $m['out'];
            $m['balance'] = $running;

            return $m;
        });

        return [
            'float'     => $float,
            'movements' => $movements,
            'balance'   => $this->pettyCash->balanceOf($float),
            // What this person is owed on top of the float, so the statement can
            // show both sides of the relationship the way the desk now does.
            'owedBack'  => $this->owedToCustodian($float),
        ];
    }

    /** What the company still owes this float's holder, in this company's books. */
    private function owedToCustodian(PettyCashFloat $float): float
    {
        try {
            return app(EmployeeReimbursementService::class)
                ->owedTo((int) $float->custodian_id, (int) $float->company_id);
        } catch (\Throwable $e) {
            // A chart missing the payable account must not take a statement down.
            return 0.0;
        }
    }

    /**
     * Open a float, and optionally hand the opening cash over in the same step.
     *
     * Creating a float on its own moves nothing — it only names who will hold
     * cash. That is correct, but it read as a gap: the custodian showed 0.00 with
     * no explanation, because the only thing that moves money was a second,
     * separate action. So the opening hand-over lives on this form too, through the
     * same PettyCashService::issue() the Issue button uses, so there is exactly one
     * way petty cash is ever funded.
     *
     * Both in one transaction — a float created but not funded because the journal
     * failed would show a custodian holding nothing with no record of why.
     */
    public function store(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);

        $request->validate([
            'custodian_id' => [
                'required',
                Rule::exists('users', 'id'),
                // One live float per person per company — the table enforces it
                // too, but a validation message beats a duplicate-key page.
                Rule::unique('petty_cash_floats', 'custodian_id')
                    ->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
                // The dropdown already offers only active employees, but a
                // dropdown is a convenience, not a guard — this is a POST that
                // opens a cash account, so the rule is enforced where it counts.
                function ($attribute, $value, $fail) {
                    if (! $this->eligibleCustodians()->whereKey($value)->exists()) {
                        $fail('Petty cash can only be held by an active employee.');
                    }
                },
            ],
            'note'        => ['nullable', 'string', 'max:255'],

            // Optional opening hand-over.
            'opening_amount' => ['nullable', 'numeric', 'min:0'],
            'opening_date'   => ['nullable', 'required_with:opening_amount', 'date'],
            'bank_id'        => [
                'nullable',
                Rule::exists('banks', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
        ], [
            'custodian_id.unique'   => 'That person already holds a petty cash float for this company.',
            'opening_date.required_with' => 'Give the date the cash was handed over.',
            'bank_id.exists'        => 'That bank does not belong to the chosen company.',
        ]);

        $opening = round((float) $request->input('opening_amount', 0), 2);

        try {
            $float = DB::transaction(function () use ($request, $companyId, $opening) {
                $float = PettyCashFloat::create([
                    'company_id'   => $companyId,
                    'custodian_id' => $request->custodian_id,
                    'account_id'   => $this->pettyCash->defaultAccount()->id,
                    'float_limit'  => 0,
                    'note'         => $request->note,
                    'status'       => 1,
                    'created_by'   => auth()->id(),
                ]);

                if ($opening > 0) {
                    $this->pettyCash->issue($float, [
                        'amount'  => $opening,
                        'date'    => $request->opening_date ?: now()->toDateString(),
                        'bank_id' => $request->bank_id ?: null,
                        'note'    => $request->input('opening_note') ?: 'Opening float',
                    ]);
                }

                return $float;
            });
        } catch (\Throwable $e) {
            // A missing 1113 / 1165 account throws from the service; saying so
            // beats a 500 on a form the user can do nothing about.
            return $this->fail($request, $e->getMessage());
        }

        if ($opening <= 0) {
            return $this->done($request, 'Petty cash float created. No cash has moved yet — use Issue Cash when you hand it over.');
        }

        $source = $request->bank_id
            ? (Bank::find($request->bank_id)?->name ?? 'the bank')
            : 'office cash';

        return $this->done(
            $request,
            'Float created and ' . number_format($opening, 2) . ' issued to '
                . ($float->custodian->name ?? 'the custodian') . ' from ' . $source . '.'
        );
    }

    public function update($role, Request $request, PettyCashFloat $float)
    {
        $this->authoriseFloat($float);

        $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        // Closing a float while cash is still out would hide a real balance from
        // the list without anyone having handed the money back.
        $status = $request->boolean('status');

        if (!$status && round($this->pettyCash->balanceOf($float), 2) != 0.0) {
            return $this->fail($request, 'This float still holds cash. Take the balance back before closing it.');
        }

        $float->update([
            'note'        => $request->note,
            'status'      => $status,
        ]);

        return $this->done($request, 'Petty cash float updated.');
    }

    /**
     * Hand cash over.
     */
    public function issue($role, Request $request, PettyCashFloat $float)
    {
        $this->authoriseFloat($float);

        $data = $this->validateMovement($request, $float);

        if (!$float->status) {
            return $this->fail($request, 'That float is closed.');
        }

        try {
            $this->pettyCash->issue($float, $data);
        } catch (\Throwable $e) {
            return $this->fail($request, $e->getMessage());
        }

        return $this->done($request, 'Cash issued.');
    }

    /**
     * Take cash back.
     */
    public function returnCash($role, Request $request, PettyCashFloat $float)
    {
        $this->authoriseFloat($float);

        $data = $this->validateMovement($request, $float);

        try {
            $this->pettyCash->receive($float, $data);
        } catch (\Throwable $e) {
            return $this->fail($request, $e->getMessage());
        }

        return $this->done($request, 'Cash returned.');
    }

    public function destroy($role, Request $request, PettyCashFloat $float)
    {
        $this->authoriseFloat($float);

        if (round($this->pettyCash->balanceOf($float), 2) != 0.0) {
            return $this->fail($request, 'This float still holds cash, so it cannot be deleted. Take the balance back first.');
        }

        $float->delete();

        return $this->done($request, 'Petty cash float deleted.');
    }

    private function validateMovement(Request $request, PettyCashFloat $float): array
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date'   => ['required', 'date'],
            'note'   => ['nullable', 'string', 'max:255'],
            // The money has to come from somewhere this company actually holds.
            'bank_id' => [
                'nullable',
                Rule::exists('banks', 'id')->where(fn ($q) => $q->where('company_id', $float->company_id)),
            ],
        ], [
            'bank_id.exists' => 'That bank does not belong to this float\'s company.',
        ]);

        return [
            'amount'  => $validated['amount'],
            'date'    => $validated['date'],
            'bank_id' => $validated['bank_id'] ?? null,
            'note'    => $validated['note'] ?? null,
        ];
    }

    /**
     * Company-locked users may only touch their own company's floats.
     */
    private function authoriseFloat(PettyCashFloat $float): void
    {
        $userCompanyId = auth()->user()->company_id;

        abort_if(
            !auth()->user()->can('view all expense')
                && !empty($userCompanyId)
                && (int) $float->company_id !== (int) $userCompanyId,
            403,
            'That petty cash float belongs to a different company.'
        );
    }

    private function resolveCompanyId(Request $request): int
    {
        $userCompanyId = auth()->user()->company_id;

        if (!auth()->user()->can('view all expense') && !empty($userCompanyId)) {
            return (int) $userCompanyId;
        }

        $request->validate(['company_id' => ['required', 'exists:companies,id']]);

        return (int) $request->company_id;
    }

    private function done(Request $request, string $message)
    {
        return $request->ajax()
            ? response()->json(['success' => true, 'message' => $message])
            : redirect()->back()->with('success', $message);
    }

    private function fail(Request $request, string $message)
    {
        return $request->ajax()
            ? response()->json(['success' => false, 'message' => $message])
            : redirect()->back()->with('error', $message);
    }
}
