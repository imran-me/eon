<?php

namespace App\Services;

use App\Models\AdvanceSalary;
use App\Models\Bank;
use App\Models\EmployeeSalary;
use App\Models\Loan;
use App\Models\LoanTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The payroll desk's standing questions, answered from one scope.
 *
 * The five existing report tabs answer "show me this table, filtered" — a
 * browsing tool. These six answer the questions someone opens a report page to
 * ask: what do we owe, where did the money actually go, and what does each
 * department cost. They sit alongside the tabs rather than replacing them.
 *
 * Every section returns the same sheet shape LoanBookService and
 * PayslipBookService return, so the screen, the spreadsheet and the printed
 * report all render from one definition and cannot disagree.
 */
class PayrollOverviewService
{
    /** The sections a report card / export can ask for. */
    public const SECTIONS = ['encashment', 'salary-due', 'advance', 'loan', 'department', 'money-flow'];

    /** Periods offered by "Where the money went". */
    public const PERIODS = [1 => 'This month', 3 => 'Last 3 months', 6 => 'Last 6 months', 12 => 'Last 12 months'];

    public function __construct(
        private LoanBookService $loanBook,
        private PayrollService $payroll,
    ) {
    }

    /* ============================================================== the team */

    /**
     * CURRENT staff — what the payroll costs and what is still accruing.
     *
     * Used by Department Cost and the encashment liability, both of which are
     * forward-looking: a resigned person costs nothing next month and accrues no
     * further leave, so counting them would overstate both.
     */
    public function team(?int $companyId): Collection
    {
        return User::role('employee')
            ->where('status', 'active')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->with(['company:id,name,short_name', 'profile.department:id,name'])
            ->orderBy('name')
            ->get();
    }

    /**
     * ANYONE who can still be carrying a balance — including resigned staff.
     *
     * Used by Salary Due, Advance Outstanding and Loan Outstanding. Those three
     * are not forecasts, they are money owed in one direction or the other, and
     * it does not stop being owed because someone left: an unpaid final salary is
     * still a liability and an unrecovered loan is still an asset.
     *
     * This is also what keeps the page honest against the rest of the app. The
     * Loans desk reads its book withTrashed for exactly this reason, so scoping
     * these three to current staff would have the Reports page showing ৳0
     * outstanding while the Loans tab showed the balance — and one of the two
     * would be believed. Rows for people who have left are flagged rather than
     * dropped.
     */
    public function balanceHolders(?int $companyId): Collection
    {
        return User::role('employee')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->with(['company:id,name,short_name', 'profile.department:id,name'])
            ->orderBy('name')
            ->get();
    }

    /** Has this person left? Shown on a balance row so the figure is explained. */
    public function hasLeft(User $user): bool
    {
        return strtolower((string) $user->status) !== 'active';
    }

    /* =========================================================== the figures */

    /**
     * The four headline figures the page leads with.
     *
     * $team accrues (encashment); $holders owe or are owed (due, advance, loan) —
     * see the two scope methods above for why those are not the same list.
     */
    public function summary(Collection $team, Collection $holders): array
    {
        $encashment = $this->encashmentRows($team);
        $due = $this->salaryDueRows($holders);
        $advance = $this->advanceRows($holders);
        $loans = $this->openLoans($holders);

        return [
            'liability'        => round((float) $encashment->sum('value'), 2),
            'accruing'         => $encashment->where('eligible', false)->count(),
            'encashable'       => $encashment->where('eligible', true)->count(),

            'salary_due'       => round((float) $due->sum('amount'), 2),
            'due_heads'        => $due->count(),

            'advance_out'      => round((float) $advance->sum('amount'), 2),
            'advance_holders'  => $advance->count(),

            'loan_out'         => round((float) $loans->sum(fn (Loan $l) => $l->outstanding), 2),
            'loan_count'       => $loans->count(),
            'emi_total'        => round((float) $loans->sum(fn (Loan $l) => (float) $l->monthly_deduction), 2),

            'headcount'        => $team->count(),
        ];
    }

    /* ────────────────────────────────────── 1. leave encashment liability ── */

    /**
     * What the group would owe in leave encashment if every clock stopped today.
     *
     * This app does not accrue encashment day by day. It pays one month's gross
     * plus a refund of the leave deducted over the service year, once a year in
     * February (PayrollService::maybeProcessAnniversaryReconciliation). So the
     * provision is the pro-rata of that payout: how far through the current
     * Feb–Jan cycle the employee is, times one month's gross, plus the leave
     * already deducted inside the cycle — which is refunded in full whenever the
     * payout lands.
     *
     * "Eligible" is the same test the reconciliation applies: a full year of
     * effective service completed. Anyone short of it is still accruing and
     * cannot be paid out yet, which is why the column exists.
     */
    public function encashmentRows(Collection $team): Collection
    {
        $cycleStart = $this->currentCycleStart();
        $today = Carbon::today();

        return $team->map(function (User $user) use ($cycleStart, $today) {
            $joining = $user->profile?->joining_date;

            if (! $joining) {
                return null;
            }

            $effectiveStart = $this->payroll->encashmentEffectiveStart(Carbon::parse($joining));

            // Nothing accrues before the clock starts.
            if ($effectiveStart->gt($today)) {
                return null;
            }

            $accrualFrom = $effectiveStart->greaterThan($cycleStart) ? $effectiveStart : $cycleStart;

            // Worked in DAYS over the 365-day cycle rather than in months: a
            // month-diff is coarse near the boundaries, and Carbon's fractional
            // month helper is deprecated in this version.
            $daysAccrued = min(365, max(0, $accrualFrom->diffInDays($today)));
            $monthsAccrued = $daysAccrued / 365 * 12;

            $monthGross = (float) ($user->profile?->salary ?? 0);

            // Leave deducted inside this cycle comes back with the payout, so it
            // is part of what is owed today.
            $leaveRefund = (float) EmployeeSalary::where('user_id', $user->id)
                ->whereRaw("STR_TO_DATE(CONCAT(year, '-', LPAD(month, 2, '0'), '-01'), '%Y-%m-%d') >= ?", [$accrualFrom->toDateString()])
                ->sum('leave_deduction');

            $value = round($monthGross * ($daysAccrued / 365) + $leaveRefund, 2);

            if ($value <= 0) {
                return null;
            }

            return [
                'user'     => $user,
                'company'  => $user->company,
                'dept'     => $user->profile?->department?->name ?: 'Unassigned',
                'months'   => round($monthsAccrued, 2),
                'value'    => $value,
                'eligible' => $effectiveStart->copy()->addYear()->lte($today),
            ];
        })->filter()->values();
    }

    /** The Feb 1 that opened the encashment year we are currently inside. */
    private function currentCycleStart(): Carbon
    {
        $today = Carbon::today();
        $feb = Carbon::create($today->year, 2, 1)->startOfDay();

        return $today->lt($feb) ? $feb->subYear() : $feb;
    }

    /* ─────────────────────────────────────────────────── 2. salary due ── */

    /**
     * Who is still owed salary, and how much.
     *
     * Derived from the payment schedules the same way Salary Manage and the
     * Payslip desk derive it, so a part-paid month counts for what is left rather
     * than for the whole net.
     */
    public function salaryDueRows(Collection $team): Collection
    {
        $ids = $team->pluck('id');

        $salaries = EmployeeSalary::whereIn('user_id', $ids)
            ->with('schedules')
            ->get()
            ->groupBy('user_id');

        return $team->map(function (User $user) use ($salaries) {
            $due = ($salaries[$user->id] ?? collect())->sum(function (EmployeeSalary $s) {
                if (strtolower((string) $s->status) === 'paid') {
                    return 0;
                }

                $paid = (float) $s->schedules->sum(fn ($x) => (float) ($x->paid_amount ?? 0));

                return max(0, round((float) $s->net_salary - $paid, 2));
            });

            return $due > 0 ? [
                'user'    => $user,
                'company' => $user->company,
                'dept'    => $user->profile?->department?->name ?: 'Unassigned',
                'amount'  => round((float) $due, 2),
                'left'    => $this->hasLeft($user),
            ] : null;
        })->filter()->sortByDesc('amount')->values();
    }

    /* ──────────────────────────────────────────── 3. advance outstanding ── */

    /**
     * Who is HOLDING an advance — released to them and not yet recovered.
     *
     * Read through AdvanceSalary::$outstanding so this and the Advance desk share
     * one definition. They must: an advance has two states that both look like
     * "not settled" and pull in opposite directions. Approved-but-not-released is
     * money we owe THEM; released-but-not-recovered is money they owe US. Only
     * the second is an outstanding advance, and an earlier version of this method
     * summed the first under that name — which had this page reporting ৳41,000
     * outstanding while the Advance desk correctly reported ৳0.
     *
     * What is still queued to go out is a separate figure, and it lives on the
     * Advance desk as "Awaiting Release".
     */
    public function advanceRows(Collection $team): Collection
    {
        $advances = AdvanceSalary::whereIn('user_id', $team->pluck('id'))
            ->with('recoveries')
            ->get()
            ->groupBy('user_id');

        return $team->map(function (User $user) use ($advances) {
            $held = round((float) ($advances[$user->id] ?? collect())
                ->sum(fn (AdvanceSalary $a) => $a->outstanding), 2);

            return $held > 0 ? [
                'user'    => $user,
                'company' => $user->company,
                'dept'    => $user->profile?->department?->name ?: 'Unassigned',
                'amount'  => round($held, 2),
                'count'   => ($advances[$user->id] ?? collect())->count(),
                'left'    => $this->hasLeft($user),
            ] : null;
        })->filter()->sortByDesc('amount')->values();
    }

    /* ─────────────────────────────────────────────── 4. loan outstanding ── */

    /** Loans still running, read through the same book the Loans desk reads. */
    public function openLoans(Collection $team): Collection
    {
        return Loan::whereIn('user_id', $team->pluck('id'))
            ->with(['user' => fn ($q) => $q->withTrashed()->with(['company:id,name,short_name', 'profile.department:id,name']), 'transactions'])
            ->get()
            ->filter(fn (Loan $l) => ! $l->is_cleared)
            ->sortByDesc(fn (Loan $l) => $l->outstanding)
            ->values();
    }

    /* ──────────────────────────────────────────────── 5. department cost ── */

    /**
     * Monthly gross by department, merged across the scope.
     *
     * "Design" exists in more than one concern, and the group's Design line is
     * their sum — not six rows with the same name. Departments are disjoint, so
     * the headcount column really does sum to the payroll.
     */
    public function departmentRows(Collection $team): Collection
    {
        return $team
            ->groupBy(fn (User $u) => $u->profile?->department?->name ?: 'Unassigned')
            ->map(fn (Collection $members, string $dept) => [
                'dept'  => $dept,
                'heads' => $members->count(),
                'cost'  => round((float) $members->sum(fn (User $u) => (float) ($u->profile?->salary ?? 0)), 2),
            ])
            ->sortByDesc('cost')
            ->values();
    }

    /* ───────────────────────────────────────────── 6. where the money went ── */

    /**
     * Every payroll taka by the account it left, on the day it moved.
     *
     * Two rules make this figure honest, both borrowed from the question it
     * answers ("from WHERE have I paid, and how much"):
     *
     *  · IT COUNTS WHAT LEFT AN ACCOUNT, not the headline. A ৳30,000 salary with a
     *    ৳5,000 EMI recovered inside it took ৳25,000 out of the bank. The payment
     *    rows carry what was actually handed over; the recovered part never
     *    touched an account and is reported separately below the table rather
     *    than added to a column, which would claim money left when none did.
     *
     *  · IT COUNTS BY THE DAY THE MONEY MOVED, not by the salary month it belonged
     *    to. "What went out of the bank last month" is a question about the bank,
     *    and June's salary paid on 4 July left it in July.
     *
     * Bonus has no column here, unlike the reference design: in this app a bonus
     * is a line inside the salary, not a payment of its own, so a Bonus column
     * would be zero on every row. It is inside Salary and this note says so.
     */
    public function moneyFlowRows(Collection $team, int $months): array
    {
        $ids = $team->pluck('id');
        $from = Carbon::today()->startOfMonth()->subMonths(max(0, $months - 1));
        $to = Carbon::today()->endOfDay();

        $banks = Bank::pluck('name', 'id');
        $rows = [];

        $bucket = function (?int $bankId) use (&$rows, $banks): string {
            $key = $bankId ? 'bank:' . $bankId : 'cash';
            $label = $bankId ? ($banks[$bankId] ?? 'Account #' . $bankId) : 'Cash / not recorded';

            $rows[$key] ??= [
                'account' => $label,
                'salary'  => 0.0,
                'advance' => 0.0,
                'loan'    => 0.0,
                'out'     => 0.0,
                'back'    => 0.0,
            ];

            return $key;
        };

        // ── salary paid out ──
        foreach (Payment::whereIn('user_id', $ids)->whereBetween('payment_date', [$from, $to])->get() as $p) {
            $k = $bucket($p->bank_id);
            $rows[$k]['salary'] += (float) $p->amount;
            $rows[$k]['out'] += (float) $p->amount;
        }

        // ── loans out, and repayments that actually moved money back in ──
        foreach (LoanTransaction::whereIn('user_id', $ids)->whereBetween('date', [$from, $to])->get() as $t) {
            if ($t->type === 'disburse') {
                $k = $bucket($t->bank_id);
                $rows[$k]['loan'] += (float) $t->amount;
                $rows[$k]['out'] += (float) $t->amount;
                continue;
            }

            // A salary-deducted EMI never touched an account — it is counted
            // under "recovered inside a salary payment" below, not here.
            if ($t->method === 'salary') {
                continue;
            }

            $k = $bucket($t->bank_id);
            $rows[$k]['back'] += (float) $t->amount;
        }

        // ── advances released ──
        // advance_salaries records no account, so these land in the unattributed
        // row rather than being spread across banks on a guess.
        $advancePaid = (float) AdvanceSalary::whereIn('user_id', $ids)
            ->where('payment_status', 'Paid')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount');

        if ($advancePaid > 0) {
            $k = $bucket(null);
            $rows[$k]['advance'] += $advancePaid;
            $rows[$k]['out'] += $advancePaid;
        }

        // ── what was recovered inside a salary and never touched an account ──
        $recovered = (float) EmployeeSalary::whereIn('user_id', $ids)
            ->whereRaw("STR_TO_DATE(CONCAT(year, '-', LPAD(month, 2, '0'), '-01'), '%Y-%m-%d') BETWEEN ? AND ?", [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COALESCE(SUM(loan_deduction), 0) + COALESCE(SUM(advance_salary_deduction), 0) as total')
            ->value('total');

        $rows = collect($rows)->sortByDesc('out')->values();

        return [
            'rows'      => $rows,
            'out'       => round((float) $rows->sum('out'), 2),
            'back'      => round((float) $rows->sum('back'), 2),
            'recovered' => round($recovered, 2),
            'from'      => $from,
            'to'        => $to,
            'months'    => $months,
        ];
    }

    /* ================================================== sheets for export */

    /**
     * One section, flattened to the sheet shape the spreadsheet and the printed
     * report both render from — the same contract the Loans and Payslip desks use.
     */
    public function sheet(string $section, Collection $team, Collection $holders, bool $withCompany, int $months = 6): array
    {
        $sheet = match ($section) {
            'encashment'  => $this->encashmentSheet($this->encashmentRows($team), $withCompany),
            'salary-due'  => $this->peopleSheet($this->salaryDueRows($holders), $withCompany, 'Salary Due',
                'who is still owed salary, and how much', 'salary-due', 'Outstanding'),
            'advance'     => $this->peopleSheet($this->advanceRows($holders), $withCompany, 'Advance Outstanding',
                'who is holding an advance right now', 'advance-outstanding', 'Advance Held'),
            'loan'        => $this->loanSheet($this->openLoans($holders), $withCompany),
            'department'  => $this->departmentSheet($this->departmentRows($team)),
            'money-flow'  => $this->moneyFlowSheet($this->moneyFlowRows($holders, $months)),
            default       => throw new \InvalidArgumentException('Unknown payroll report section: ' . $section),
        };

        // The unit is stated once in the heading, as the party statement's export
        // heads its "Debit (৳)" column — a cell prefixed with a currency sign
        // stops being a number you can add up.
        foreach ($sheet['headings'] as $i => $heading) {
            if (! empty($heading['money'])) {
                $sheet['headings'][$i]['label'] .= ' (৳)';
            }
        }

        return $sheet;
    }

    private function encashmentSheet(Collection $rows, bool $withCompany): array
    {
        $eligible = $rows->where('eligible', true)->count();

        return [
            'key'      => 'encashment',
            'title'    => 'Leave Encashment Liability',
            'subtitle' => 'what would be owed if every clock stopped today',
            'filename' => 'leave-encashment-liability',
            'headings' => array_values(array_filter([
                ['label' => 'Employee', 'money' => false],
                ['label' => 'Employee ID', 'money' => false],
                $withCompany ? ['label' => 'Company', 'money' => false] : null,
                ['label' => 'Dept', 'money' => false],
                ['label' => 'Accrued Months', 'money' => false, 'align' => 'right'],
                ['label' => 'Value', 'money' => true],
                ['label' => 'Eligibility', 'money' => false],
            ])),
            'rows' => $rows->map(fn (array $r) => array_values(array_filter([
                $r['user']->name,
                $r['user']->employee_id_no ?: '—',
                $withCompany ? ($r['company']?->short_name ?: $r['company']?->name ?: '—') : null,
                $r['dept'],
                number_format($r['months'], 2),
                $r['value'],
                $r['eligible'] ? 'Eligible' : 'Accruing',
            ], fn ($v) => $v !== null)))->all(),
            /* Months and value both sum — they are quantities, not balances — and
             * ELIGIBILITY counts instead: the split between who could be paid out
             * today and who is still inside the service condition is the whole
             * point of the column. */
            'totals' => array_values(array_filter([
                $rows->count() . ' ' . Str::plural('employee', $rows->count()),
                null,
                $withCompany ? null : false,
                null,
                number_format($rows->sum('months'), 2),
                round((float) $rows->sum('value'), 2),
                $eligible . ' eligible · ' . ($rows->count() - $eligible) . ' accruing',
            ], fn ($v) => $v !== false)),
        ];
    }

    /** Salary Due and Advance Outstanding are the same shape: a person and a number. */
    private function peopleSheet(Collection $rows, bool $withCompany, string $title, string $subtitle, string $filename, string $amountLabel): array
    {
        return [
            'key'      => $filename,
            'title'    => $title,
            'subtitle' => $subtitle,
            'filename' => $filename,
            'headings' => array_values(array_filter([
                ['label' => 'Employee', 'money' => false],
                ['label' => 'Employee ID', 'money' => false],
                $withCompany ? ['label' => 'Company', 'money' => false] : null,
                ['label' => 'Dept', 'money' => false],
                ['label' => $amountLabel, 'money' => true],
            ])),
            'rows' => $rows->map(fn (array $r) => array_values(array_filter([
                $r['user']->name . (!empty($r['left']) ? ' (left)' : ''),
                $r['user']->employee_id_no ?: '—',
                $withCompany ? ($r['company']?->short_name ?: $r['company']?->name ?: '—') : null,
                $r['dept'],
                $r['amount'],
            ], fn ($v) => $v !== null)))->all(),
            'totals' => array_values(array_filter([
                $rows->count() . ' ' . Str::plural('person', $rows->count()),
                null,
                $withCompany ? null : false,
                null,
                round((float) $rows->sum('amount'), 2),
            ], fn ($v) => $v !== false)),
        ];
    }

    private function loanSheet(Collection $loans, bool $withCompany): array
    {
        return [
            'key'      => 'loan',
            'title'    => 'Loan Outstanding',
            'subtitle' => 'loans in progress — taken · paid till now · still due',
            'filename' => 'loan-outstanding',
            'headings' => array_values(array_filter([
                ['label' => 'Employee', 'money' => false],
                ['label' => 'Employee ID', 'money' => false],
                $withCompany ? ['label' => 'Company', 'money' => false] : null,
                ['label' => 'Dept', 'money' => false],
                ['label' => 'Taken On', 'money' => false],
                ['label' => 'Loan Taken', 'money' => true],
                ['label' => 'Paid Till Now', 'money' => true],
                ['label' => 'Still Due', 'money' => true],
                ['label' => 'EMI', 'money' => true],
            ])),
            'rows' => $loans->map(fn (Loan $l) => array_values(array_filter([
                $l->user?->name ?? 'Employee #' . $l->user_id,
                $l->user?->employee_id_no ?: '—',
                $withCompany ? ($l->user?->company?->short_name ?: $l->user?->company?->name ?: '—') : null,
                $l->user?->profile?->department?->name ?: 'Unassigned',
                $l->start_date ? Carbon::parse($l->start_date)->format('d M Y') : '—',
                (float) $l->amount,
                $l->paid_amount,
                $l->outstanding,
                (float) $l->monthly_deduction,
            ], fn ($v) => $v !== null)))->all(),
            /* The money columns sum, and EMI sums only where a plan exists — a
             * loan with no plan contributes nothing, which is what "no plan" means. */
            'totals' => array_values(array_filter([
                $loans->count() . ' ' . Str::plural('loan', $loans->count()),
                null,
                $withCompany ? null : false,
                null,
                null,
                round((float) $loans->sum(fn (Loan $l) => (float) $l->amount), 2),
                round((float) $loans->sum(fn (Loan $l) => $l->paid_amount), 2),
                round((float) $loans->sum(fn (Loan $l) => $l->outstanding), 2),
                round((float) $loans->sum(fn (Loan $l) => (float) $l->monthly_deduction), 2),
            ], fn ($v) => $v !== false)),
        ];
    }

    private function departmentSheet(Collection $rows): array
    {
        return [
            'key'      => 'department',
            'title'    => 'Department Cost (monthly gross)',
            'subtitle' => 'salary cost by department',
            'filename' => 'department-cost',
            'headings' => [
                ['label' => 'Department', 'money' => false],
                ['label' => 'Headcount', 'money' => false, 'align' => 'right'],
                ['label' => 'Monthly Cost', 'money' => true],
            ],
            'rows' => $rows->map(fn (array $r) => [$r['dept'], $r['heads'], $r['cost']])->all(),
            'totals' => [
                $rows->count() . ' ' . Str::plural('department', $rows->count()),
                (int) $rows->sum('heads'),
                round((float) $rows->sum('cost'), 2),
            ],
        ];
    }

    private function moneyFlowSheet(array $flow): array
    {
        return [
            'key'      => 'money-flow',
            'title'    => 'Where The Money Went',
            'subtitle' => 'every payroll taka by the account it left, on the day it moved',
            'filename' => 'payroll-money-flow',
            'headings' => [
                ['label' => 'Paid From', 'money' => false],
                ['label' => 'Salary', 'money' => true],
                ['label' => 'Advance', 'money' => true],
                ['label' => 'Staff Loan', 'money' => true],
                ['label' => 'Total Paid Out', 'money' => true],
                ['label' => 'Came Back In', 'money' => true],
            ],
            'rows' => $flow['rows']->map(fn (array $r) => [
                $r['account'], $r['salary'], $r['advance'], $r['loan'], $r['out'], $r['back'],
            ])->all(),
            'totals' => [
                $flow['rows']->count() . ' ' . Str::plural('account', $flow['rows']->count()),
                round((float) $flow['rows']->sum('salary'), 2),
                round((float) $flow['rows']->sum('advance'), 2),
                round((float) $flow['rows']->sum('loan'), 2),
                $flow['out'],
                $flow['back'],
            ],
        ];
    }
}
