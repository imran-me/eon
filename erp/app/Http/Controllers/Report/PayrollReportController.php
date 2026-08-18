<?php

namespace App\Http\Controllers\Report;

use App\Exports\PayrollBookExport;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\EmployeeSalary;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Services\PayrollOverviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * One page behind five payroll reports (salary overall/individual, loan,
 * advance, payslip). They share a filter bar, a summary strip and a print
 * layout, so they share a controller rather than repeating the same scoping,
 * month-range and paid/due arithmetic five times over.
 */
class PayrollReportController extends Controller
{
    public const TYPES = ['overall', 'individual', 'loan', 'advance', 'payslip'];

    /**
     * Reports cover current staff only — resigned and inactive people are left
     * out of every tab, including any balance still outstanding against them.
     */
    private const ACTIVE_STATUS = 'active';

    public function index(Request $request)
    {
        return view('report.payroll.index', $this->buildReport($request));
    }

    /**
     * The standing questions, on one page: what we owe, where the money went and
     * what each department costs.
     *
     * Sits alongside the five filter-driven report tabs rather than replacing
     * them — those answer "show me this table, narrowed", which is a different
     * job from "what is our position today".
     */
    public function overview(Request $request, PayrollOverviewService $overview)
    {
        [$team, $holders, $companyId] = $this->overviewScope($request, $overview);

        $months = (int) $request->query('months', 6);
        $months = array_key_exists($months, PayrollOverviewService::PERIODS) ? $months : 6;

        return view('report.payroll.overview', [
            'summary'      => $overview->summary($team, $holders),
            'encashment'   => $overview->encashmentRows($team),
            'salaryDue'    => $overview->salaryDueRows($holders),
            'advances'     => $overview->advanceRows($holders),
            'loans'        => $overview->openLoans($holders),
            'departments'  => $overview->departmentRows($team),
            'flow'         => $overview->moneyFlowRows($holders, $months),
            'months'       => $months,
            'companyId'    => $companyId,
            'companies'    => $this->scopeCompanies(),
            'overview'     => $overview,
        ]);
    }

    /** One section of the overview as a spreadsheet, scoped as on screen. */
    public function overviewExcel(Request $request, PayrollOverviewService $overview)
    {
        [$sheet, $scopeLabel, $filterLabel] = $this->overviewPayload($request, $overview);

        return Excel::download(
            new PayrollBookExport($sheet, $scopeLabel, $filterLabel),
            $sheet['filename'] . '-' . now()->format('Ymd-Hi') . '.xlsx'
        );
    }

    /** The same section as a printable report, in the shared payroll format. */
    public function overviewPdf(Request $request, PayrollOverviewService $overview)
    {
        [$sheet, $scopeLabel, $filterLabel, $companyId] = $this->overviewPayload($request, $overview);

        return view('payroll.print-report', [
            'sheet'       => $sheet,
            'scopeLabel'  => $scopeLabel,
            'filterLabel' => $filterLabel,
            'company'     => $companyId ? Company::find($companyId) : $this->groupCompany(),
            'cards'       => [],
            'note'        => $this->overviewNote($request->query('section')),
        ]);
    }

    /* ── overview helpers ─────────────────────────────────────────────────── */

    /**
     * Who the overview covers. The company lock is the same one the report tabs
     * apply: a user without "view all salary" only ever sees their own company.
     */
    private function overviewScope(Request $request, PayrollOverviewService $overview): array
    {
        $authUser = Auth::user();
        $companyId = $request->filled('company_id') ? (int) $request->company_id : null;

        if (!$authUser->can('view all salary') && !empty($authUser->company_id)) {
            $companyId = (int) $authUser->company_id;
        }

        return [$overview->team($companyId), $overview->balanceHolders($companyId), $companyId];
    }

    private function overviewPayload(Request $request, PayrollOverviewService $overview): array
    {
        $section = $request->query('section');

        if (!in_array($section, PayrollOverviewService::SECTIONS, true)) {
            abort(404, 'There is no such payroll report section.');
        }

        [$team, $holders, $companyId] = $this->overviewScope($request, $overview);

        $months = (int) $request->query('months', 6);
        $months = array_key_exists($months, PayrollOverviewService::PERIODS) ? $months : 6;

        // The company column earns its place only across more than one company.
        $withCompany = !$companyId
            && $holders->pluck('company_id')->filter()->unique()->count() > 1;

        $scopeLabel = $companyId
            ? (Company::find($companyId)?->name ?: 'Company #' . $companyId)
            : 'All companies';

        $filterLabel = $section === 'money-flow'
            ? PayrollOverviewService::PERIODS[$months]
            : 'As at ' . now()->format('d M Y');

        return [
            $overview->sheet($section, $team, $holders, $withCompany, $months),
            $scopeLabel,
            $filterLabel,
            $companyId,
        ];
    }

    /** What each section's figures mean, printed under the table. */
    private function overviewNote(?string $section): string
    {
        return match ($section) {
            'encashment' => 'This app pays leave encashment once a year in February: one month\'s gross plus a refund '
                . 'of the leave deducted over the service year. The value here is the pro-rata of that payout — how far '
                . 'through the current February–January cycle each employee is, plus the leave already deducted inside '
                . 'it. "Eligible" means a full year of effective service is complete and the payout can be made; '
                . '"Accruing" means it cannot be made yet. Current staff only: someone who has left accrues nothing further.',
            'salary-due' => 'Outstanding is the net salary less whatever has been paid against it through the payment '
                . 'schedule, so a part-paid month counts for what is left rather than for the whole net. People who have '
                . 'left are included and marked: an unpaid final salary is still owed.',
            'advance' => 'Advances that were approved and have not yet been recovered or settled. People who have left '
                . 'are included and marked, because the money is still outstanding either way.',
            'loan' => 'Loans still running, read from the same book the Loans desk reads — "paid till now" folds in '
                . 'anything repaid before individual movements were recorded. People who have left are included and '
                . 'marked: an unrecovered loan is still an asset.',
            'department' => 'Monthly gross by department, merged across the scope, so a department that exists in more '
                . 'than one company appears once with their combined cost. Departments are disjoint, so the headcount '
                . 'column really does sum to the payroll. Current staff only.',
            'money-flow' => 'Every payroll taka by the account it left, counted on the day the money moved rather than '
                . 'the salary month it belonged to — June\'s salary paid in July left the bank in July. It counts what '
                . 'actually left an account: a salary with an EMI recovered inside it took less out of the bank than its '
                . 'headline, and the recovered part never touched an account at all. A bonus is a line inside the salary '
                . 'here rather than a payment of its own, so it is counted under Salary.',
            default => '',
        };
    }

    /** Companies this user may scope to. */
    private function scopeCompanies()
    {
        $authUser = Auth::user();

        return Company::where('status', 1)
            ->when(!$authUser->can('view all salary') && !empty($authUser->company_id),
                fn ($q) => $q->where('id', $authUser->company_id))
            ->orderBy('order')->orderBy('name')->get();
    }

    public function print(Request $request)
    {
        $data = $this->buildReport($request);

        // The letterhead follows the filter: a single company's report carries
        // that company's name and logo, while an all-companies report is the
        // group's, so it goes out under the group's branding instead of
        // whichever company happens to be first in the table.
        $data['company']      = $data['companyId']
            ? Company::find($data['companyId'])
            : $this->groupCompany();
        $data['scopeCompany'] = $data['companyId'] ? $data['company'] : null;

        return view('report.payroll.print', $data);
    }

    /**
     * The parent brand every company sits under. Matched by name rather than a
     * hard-coded id so re-seeding or reordering the table can't silently swap
     * the letterhead to a subsidiary.
     */
    private function groupCompany(): ?Company
    {
        return Company::where('name', 'EPAL GROUP')->first()
            ?? Company::where('name', 'like', '%GROUP%')->first()
            ?? Company::first();
    }

    // ── Report building ─────────────────────────────────────────────────────

    private function buildReport(Request $request): array
    {
        $type = in_array($request->type, self::TYPES, true) ? $request->type : 'overall';

        $authUser            = Auth::user();
        $canViewAllCompanies = $authUser->can('view all salary');
        $companyId           = $request->filled('company_id') ? (int) $request->company_id : null;

        if (!$canViewAllCompanies && !empty($authUser->company_id)) {
            $companyId = (int) $authUser->company_id;
        }

        // Y-m for salary/advance (they're stored as month+year, not a date) and
        // plain dates for loan/payslip, which have real date columns.
        $from = $this->monthInput($request->from);
        $to   = $this->monthInput($request->to);

        $employeeId = $request->filled('employee_id') ? (int) $request->employee_id : null;

        // An employee without an accounts/HR hat only ever sees their own figures.
        $ownRecordsOnly = $authUser->hasRole('employee') && !$authUser->hasAnyRole(['accountant', 'hr']);
        if ($ownRecordsOnly) {
            $employeeId = $authUser->id;
        }

        $filters = compact('type', 'companyId', 'from', 'to', 'employeeId') + [
            'search'      => trim((string) $request->search) ?: null,
            'status'      => $request->status,
            'period_mode' => in_array($request->period_mode, ['monthly', 'yearly', 'custom'], true)
                ? $request->period_mode
                : 'custom',
        ];

        $report = match ($type) {
            'individual' => $this->individualReport($filters),
            'loan'       => $this->loanReport($filters),
            'advance'    => $this->advanceReport($filters),
            'payslip'    => $this->payslipReport($filters),
            default      => $this->overallReport($filters),
        };

        $companiesQuery = Company::where('status', 1)->orderBy('order')->orderBy('name');
        if (!$canViewAllCompanies && !empty($authUser->company_id)) {
            $companiesQuery->where('id', $authUser->company_id);
        }

        $employees = User::role('employee')
            ->where('status', 'active')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($ownRecordsOnly, fn ($q) => $q->where('id', $authUser->id))
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id_no']);

        return $report + $filters + [
            'companies'      => $companiesQuery->get(),
            'employees'      => $employees,
            'selectedEmployee' => $employeeId
                ? User::with('profile.designation', 'profile.department')->find($employeeId)
                : null,
            'ownRecordsOnly' => $ownRecordsOnly,
        ];
    }

    /**
     * Paid/due for a salary row. A Pending row can already carry part-payments
     * through PaymentSchedule instalments, so status alone would understate
     * paid and overstate due. Mirrors EmployeeSalaryController's report exactly
     * — the two must never disagree.
     */
    private function paidExpr(): string
    {
        return "CASE WHEN employee_salaries.status = 'Paid' THEN employee_salaries.net_salary
                     ELSE LEAST(COALESCE(schedule_paid.paid_total, 0), employee_salaries.net_salary) END";
    }

    private function dueExpr(): string
    {
        return "CASE WHEN employee_salaries.status = 'Paid' THEN 0
                     ELSE GREATEST(employee_salaries.net_salary - COALESCE(schedule_paid.paid_total, 0), 0) END";
    }

    /**
     * Free-text lookup over the employee behind a row. Matches the employee id
     * first — that's what the box is for — but also falls back to the name, so
     * someone who only remembers who they're after isn't stuck.
     */
    private function applySearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        // Employee ids are stored with a space in them ("WAI25 503"), which
        // nobody types consistently, so the id is also compared with all spaces
        // stripped from both sides.
        $bare = str_replace(' ', '', $search);

        return $query->where(function ($q) use ($search, $bare) {
            $q->where('users.employee_id_no', 'like', "%{$search}%")
                ->orWhereRaw("REPLACE(users.employee_id_no, ' ', '') LIKE ?", ["%{$bare}%"])
                ->orWhere('users.name', 'like', "%{$search}%");
        });
    }

    private function salaryPaidSubquery()
    {
        return PaymentSchedule::settledTotalsSubquery(EmployeeSalary::class);
    }

    private function salaryBaseQuery(array $f)
    {
        return $this->applySearch(EmployeeSalary::query()
            ->join('users', 'users.id', '=', 'employee_salaries.user_id')
            ->where('users.status', self::ACTIVE_STATUS), $f['search'])
            ->leftJoinSub($this->salaryPaidSubquery(), 'schedule_paid',
                fn ($join) => $join->on('schedule_paid.schedulable_id', '=', 'employee_salaries.id'))
            ->when($f['companyId'], fn ($q) => $q->where('users.company_id', $f['companyId']))
            ->when($f['employeeId'], fn ($q) => $q->where('employee_salaries.user_id', $f['employeeId']))
            ->when($f['status'], fn ($q) => $q->where('employee_salaries.status', $f['status']))
            ->when($f['from'], function ($q) use ($f) {
                [$y, $m] = array_map('intval', explode('-', $f['from']));
                $q->whereRaw('(employee_salaries.year * 100 + CAST(employee_salaries.month AS UNSIGNED)) >= ?', [$y * 100 + $m]);
            })
            ->when($f['to'], function ($q) use ($f) {
                [$y, $m] = array_map('intval', explode('-', $f['to']));
                $q->whereRaw('(employee_salaries.year * 100 + CAST(employee_salaries.month AS UNSIGNED)) <= ?', [$y * 100 + $m]);
            });
    }

    // ── 1. Overall: one row per employee ────────────────────────────────────

    private function overallReport(array $f): array
    {
        $rows = $this->salaryBaseQuery($f)
            ->groupBy('users.id', 'users.name', 'users.employee_id_no')
            ->orderBy('users.name')
            ->selectRaw('users.id as user_id, users.name as employee_name, users.employee_id_no')
            ->selectRaw('COUNT(*) as months_count')
            ->selectRaw('SUM(employee_salaries.gross_salary) as total_gross')
            ->selectRaw('SUM(employee_salaries.total_deductions) as total_deductions')
            ->selectRaw('SUM(employee_salaries.net_salary) as total_net')
            ->selectRaw('SUM(' . $this->paidExpr() . ') as total_paid')
            ->selectRaw('SUM(' . $this->dueExpr() . ') as total_due')
            ->get();

        return [
            'rows'    => $rows,
            'summary' => [
                'Employees'  => ['value' => $rows->count(),                   'type' => 'count'],
                'Gross'      => ['value' => (float) $rows->sum('total_gross'), 'type' => 'money'],
                'Deductions' => ['value' => (float) $rows->sum('total_deductions'), 'type' => 'money'],
                'Net'        => ['value' => (float) $rows->sum('total_net'),   'type' => 'money'],
                'Paid'       => ['value' => (float) $rows->sum('total_paid'),  'type' => 'good'],
                'Due'        => ['value' => (float) $rows->sum('total_due'),   'type' => 'bad'],
            ],
        ];
    }

    // ── 2. Individual: one row per month, with a running due balance ────────

    private function individualReport(array $f): array
    {
        if (!$f['employeeId']) {
            return ['rows' => collect(), 'summary' => [], 'needsEmployee' => true];
        }

        $rows = $this->salaryBaseQuery($f)
            ->orderBy('employee_salaries.year')
            ->orderByRaw('CAST(employee_salaries.month AS UNSIGNED)')
            ->selectRaw('employee_salaries.id, employee_salaries.year, employee_salaries.month')
            ->selectRaw('employee_salaries.gross_salary, employee_salaries.bonus_amount')
            ->selectRaw('employee_salaries.total_deductions, employee_salaries.net_salary')
            ->selectRaw('employee_salaries.status, employee_salaries.payment_method')
            ->selectRaw('employee_salaries.salary_generation_date')
            ->selectRaw($this->paidExpr() . ' as paid_amount')
            ->selectRaw($this->dueExpr() . ' as due_amount')
            ->get();

        // Cumulative unpaid, so it reads like the party statement's balance column.
        $running = 0.0;
        $rows->each(function ($r) use (&$running) {
            $running += (float) $r->due_amount;
            $r->running_due = $running;
        });

        return [
            'rows'    => $rows,
            'summary' => [
                'Months'     => ['value' => $rows->count(),                        'type' => 'count'],
                'Gross'      => ['value' => (float) $rows->sum('gross_salary'),    'type' => 'money'],
                'Deductions' => ['value' => (float) $rows->sum('total_deductions'),'type' => 'money'],
                'Net'        => ['value' => (float) $rows->sum('net_salary'),      'type' => 'money'],
                'Paid'       => ['value' => (float) $rows->sum('paid_amount'),     'type' => 'good'],
                'Due'        => ['value' => (float) $rows->sum('due_amount'),      'type' => 'bad'],
            ],
        ];
    }

    // ── 3. Loan ─────────────────────────────────────────────────────────────

    private function loanReport(array $f): array
    {
        $rows = $this->applySearch(DB::table('loans')
            ->join('users', 'users.id', '=', 'loans.user_id')
            ->leftJoin('banks', 'banks.id', '=', 'loans.bank_id')
            ->where('users.status', self::ACTIVE_STATUS), $f['search'])
            ->whereNull('loans.deleted_at')
            ->when($f['companyId'], fn ($q) => $q->where('users.company_id', $f['companyId']))
            ->when($f['employeeId'], fn ($q) => $q->where('loans.user_id', $f['employeeId']))
            ->when($f['status'], fn ($q) => $q->where('loans.status', $f['status']))
            ->when($f['from'], fn ($q) => $q->whereDate('loans.start_date', '>=', $f['from'] . '-01'))
            ->when($f['to'], fn ($q) => $q->whereRaw('loans.start_date <= LAST_DAY(?)', [$f['to'] . '-01']))
            ->orderBy('users.name')
            ->orderByDesc('loans.start_date')
            ->selectRaw('loans.*, users.name as employee_name, users.employee_id_no, banks.name as bank_name')
            ->selectRaw('GREATEST(loans.amount - COALESCE(loans.remaining_amount, 0), 0) as recovered_amount')
            ->get();

        return [
            'rows'    => $rows,
            'summary' => [
                'Loans'     => ['value' => $rows->count(),                            'type' => 'count'],
                'Disbursed' => ['value' => (float) $rows->sum('amount'),              'type' => 'money'],
                'Recovered' => ['value' => (float) $rows->sum('recovered_amount'),    'type' => 'good'],
                'Remaining' => ['value' => (float) $rows->sum('remaining_amount'),    'type' => 'bad'],
                'Running'   => ['value' => $rows->where('status', 'Running')->count(),   'type' => 'count'],
                'Completed' => ['value' => $rows->where('status', 'Completed')->count(), 'type' => 'count'],
            ],
        ];
    }

    // ── 4. Advance salary ───────────────────────────────────────────────────

    private function advanceReport(array $f): array
    {
        $rows = $this->applySearch(DB::table('advance_salaries')
            ->join('users', 'users.id', '=', 'advance_salaries.user_id')
            ->where('users.status', self::ACTIVE_STATUS), $f['search'])
            ->whereNull('advance_salaries.deleted_at')
            ->when($f['companyId'], fn ($q) => $q->where('users.company_id', $f['companyId']))
            ->when($f['employeeId'], fn ($q) => $q->where('advance_salaries.user_id', $f['employeeId']))
            ->when($f['status'], fn ($q) => $q->where('advance_salaries.status', $f['status']))
            ->when($f['from'], fn ($q) => $q->whereDate('advance_salaries.schedule_date', '>=', $f['from'] . '-01'))
            ->when($f['to'], fn ($q) => $q->whereRaw('advance_salaries.schedule_date <= LAST_DAY(?)', [$f['to'] . '-01']))
            ->orderByDesc('advance_salaries.schedule_date')
            ->selectRaw('advance_salaries.*, users.name as employee_name, users.employee_id_no')
            ->get();

        $byStatus = fn (string $s) => (float) $rows->where('status', $s)->sum('amount');

        return [
            'rows'    => $rows,
            'summary' => [
                'Requests' => ['value' => $rows->count(),               'type' => 'count'],
                'Total'    => ['value' => (float) $rows->sum('amount'), 'type' => 'money'],
                'Approved' => ['value' => $byStatus('Approved'),        'type' => 'good'],
                'Pending'  => ['value' => $byStatus('Pending'),         'type' => 'warn'],
                'Rejected' => ['value' => $byStatus('Rejected'),        'type' => 'bad'],
            ],
        ];
    }

    // ── 5. Payslip ──────────────────────────────────────────────────────────

    private function payslipReport(array $f): array
    {
        $rows = $this->applySearch(DB::table('payslips')
            ->join('employee_salaries', 'employee_salaries.id', '=', 'payslips.employee_salary_id')
            ->join('users', 'users.id', '=', 'payslips.user_id')
            ->leftJoinSub($this->salaryPaidSubquery(), 'schedule_paid',
                fn ($join) => $join->on('schedule_paid.schedulable_id', '=', 'employee_salaries.id'))
            ->where('users.status', self::ACTIVE_STATUS), $f['search'])
            ->whereNull('employee_salaries.deleted_at')
            ->when($f['companyId'], fn ($q) => $q->where('users.company_id', $f['companyId']))
            ->when($f['employeeId'], fn ($q) => $q->where('payslips.user_id', $f['employeeId']))
            ->when($f['status'], fn ($q) => $q->where('employee_salaries.status', $f['status']))
            ->when($f['from'], function ($q) use ($f) {
                [$y, $m] = array_map('intval', explode('-', $f['from']));
                $q->whereRaw('(employee_salaries.year * 100 + CAST(employee_salaries.month AS UNSIGNED)) >= ?', [$y * 100 + $m]);
            })
            ->when($f['to'], function ($q) use ($f) {
                [$y, $m] = array_map('intval', explode('-', $f['to']));
                $q->whereRaw('(employee_salaries.year * 100 + CAST(employee_salaries.month AS UNSIGNED)) <= ?', [$y * 100 + $m]);
            })
            ->orderByDesc('employee_salaries.year')
            ->orderByRaw('CAST(employee_salaries.month AS UNSIGNED) DESC')
            ->orderBy('users.name')
            ->selectRaw('payslips.id, payslips.payslip_number, payslips.issue_date, payslips.pdf_path')
            ->selectRaw('users.name as employee_name, users.id as user_id, users.employee_id_no')
            ->selectRaw('employee_salaries.year, employee_salaries.month, employee_salaries.status')
            ->selectRaw('employee_salaries.gross_salary, employee_salaries.total_deductions, employee_salaries.net_salary')
            ->selectRaw($this->paidExpr() . ' as paid_amount')
            ->selectRaw($this->dueExpr() . ' as due_amount')
            ->get();

        return [
            'rows'    => $rows,
            'summary' => [
                'Payslips'   => ['value' => $rows->count(),                         'type' => 'count'],
                'Gross'      => ['value' => (float) $rows->sum('gross_salary'),     'type' => 'money'],
                'Deductions' => ['value' => (float) $rows->sum('total_deductions'), 'type' => 'money'],
                'Net'        => ['value' => (float) $rows->sum('net_salary'),       'type' => 'money'],
                'Paid'       => ['value' => (float) $rows->sum('paid_amount'),      'type' => 'good'],
                'Due'        => ['value' => (float) $rows->sum('due_amount'),       'type' => 'bad'],
            ],
        ];
    }

    private function monthInput($value): ?string
    {
        return (is_string($value) && preg_match('/^\d{4}-\d{1,2}$/', $value)) ? $value : null;
    }
}
