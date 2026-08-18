<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\EmployeeSalary;
use App\Models\Payslip;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\SalaryTemplate;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Auth;

class EmployeeSalaryController extends Controller
{
    public function __construct(private PayrollService $payroll)
    {
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Plain employees (and business-unit managers like "Travel Manager" who
        // aren't a dedicated payroll/HR role) only ever see their own salary here.
        // Accountant/HR keep the company-wide view they need to run payroll, even
        // without "view all salary". This replaces an order-dependent check on
        // getRoleNames()->first(), which broke for any user holding more than
        // one role depending on which happened to be assigned/returned first.
        $authUser = Auth::user();
        $payrollManagerRoles = ['accountant', 'hr'];
        $isPlainEmployee = $authUser->hasRole('employee') && !$authUser->hasAnyRole($payrollManagerRoles);
        if ($isPlainEmployee) {
            $request->merge(['user_id' => auth()->id()]);
        }

        $companyId = $request->filled('company_id') ? (int) $request->company_id : null;

        // Users without the "view all salary" permission (e.g. single-company HR/accountants)
        // are locked to their own company, regardless of what company_id is requested.
        // Everyone with "view all salary" (HQ admins, owners, super admin) sees every company,
        // even though their user record itself still belongs to one company (e.g. HQ).
        $userCompanyId = auth()->user()->company_id;
        $canViewAllCompanies = auth()->user()->can('view all salary');
        if (!$canViewAllCompanies && !empty($userCompanyId)) {
            $companyId = (int) $userCompanyId;
        }

        // Salary sheet is scoped to one payroll period at a time. An explicit
        // ?date always wins — including one with no rows, so you can confirm a
        // month hasn't been run. With no ?date we open on the newest month that
        // actually has payroll instead of the calendar month, which is usually
        // empty until someone generates it (in August you want July's sheet).
        // Resolved after $companyId so the default follows the company in view.
        $period = null;

        if ($request->filled('date') && preg_match('/^\d{4}-\d{1,2}$/', $request->date)) {
            [$periodYear, $periodMonth] = explode('-', $request->date);
        } else {
            $latest = EmployeeSalary::query()
                ->join('users', 'users.id', '=', 'employee_salaries.user_id')
                ->when($companyId, fn ($q) => $q->where('users.company_id', $companyId))
                ->when($request->filled('user_id'), fn ($q) => $q->where('employee_salaries.user_id', $request->user_id))
                ->orderByDesc('employee_salaries.year')
                ->orderByDesc('employee_salaries.month')
                ->first(['employee_salaries.year', 'employee_salaries.month']);

            $periodYear = $latest->year ?? now()->year;
            $periodMonth = $latest->month ?? now()->format('m');
        }

        $periodYear = (int) $periodYear;
        $periodMonth = str_pad((int) $periodMonth, 2, '0', STR_PAD_LEFT);
        $period = $periodYear . '-' . $periodMonth;

        $query = EmployeeSalary::select('employee_salaries.*')
            ->with(['user.company', 'salary_template', 'schedules' => function ($q) {
                $q->orderByDesc('id');
            }])
            ->join('users', 'users.id', '=', 'employee_salaries.user_id')
            ->join('salary_templates', 'salary_templates.id', '=', 'employee_salaries.salary_template_id')
            ->where('employee_salaries.year', $periodYear)
            ->where('employee_salaries.month', $periodMonth)
            ->orderBy('users.name', 'asc');

        if ($request->has('user_id') && !empty($request->user_id)) {
            $query->where('employee_salaries.user_id', $request->user_id);
        }

        if ($request->has('salary_template_id') && !empty($request->salary_template_id)) {
            $query->where('employee_salaries.salary_template_id', $request->salary_template_id);
        }

        $this->applyEmployeeSearch($query, $request->search);

        if ($companyId) {
            $query->where('users.company_id', $companyId);
        }

        if ($request->filled('status')) {
            $query->whereRaw('LOWER(employee_salaries.status) = ?', [Str::lower($request->status)]);
        }

        $datas = $query->paginate(20)->appends($request->query());

        // Which ledger account each salary was actually paid out of. Read from the
        // journal rather than employee_salaries.bank_id, because cash payments
        // leave bank_id null while still posting a real credit line — the journal
        // is the only place that always knows. Salary entries carry one credit
        // (the money-out account) against one Salary Expense debit; the asset
        // filter keeps it on the cash/bank side should deductions ever be posted
        // as extra credit lines.
        $paidFromAccounts = collect();

        if ($datas->count()) {
            $paidFromAccounts = DB::table('journal_entries')
                ->join('journal_items', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
                ->join('accounts', 'accounts.id', '=', 'journal_items.account_id')
                ->where('journal_entries.source', 'salary')
                ->whereIn('journal_entries.source_id', $datas->pluck('id'))
                ->whereNull('journal_entries.deleted_at')
                ->whereNull('journal_items.deleted_at')
                ->where('journal_items.credit', '>', 0)
                ->where('accounts.type', 'asset')
                ->orderByDesc('journal_entries.id')
                ->get([
                    'journal_entries.source_id as salary_id',
                    'accounts.code as account_code',
                    'accounts.name as account_name',
                ])
                ->groupBy('salary_id')
                // Newest entry wins: an edited salary re-posts, and the latest
                // posting is the one that reflects where the money went.
                ->map(fn ($rows) => $rows->first());
        }

        // ── Company-wise payable / due summary for this period ───────────────
        $companiesQuery = Company::where('status', 1)->orderBy('order')->orderBy('name');
        if (!$canViewAllCompanies && !empty($userCompanyId)) {
            $companiesQuery->where('id', $userCompanyId);
        }
        $companies = $companiesQuery->get();

        $companyStatsRaw = EmployeeSalary::query()
            ->join('users', 'users.id', '=', 'employee_salaries.user_id')
            ->where('employee_salaries.year', $periodYear)
            ->where('employee_salaries.month', $periodMonth)
            ->when($request->filled('user_id'), function ($q) use ($request) {
                // Keeps the summary cards in lockstep with the row-level scope above:
                // self-only mode (or a manual employee filter) must not let the cards
                // leak an aggregate across coworkers the rows themselves don't show.
                $q->where('employee_salaries.user_id', $request->user_id);
            })
            ->tap(fn ($q) => $this->applyEmployeeSearch($q, $request->search))
            ->selectRaw("users.company_id,
                COUNT(*) as headcount,
                SUM(employee_salaries.gross_salary) as gross,
                SUM(employee_salaries.total_deductions) as deductions,
                SUM(COALESCE(employee_salaries.overtime_salary, 0)
                    + COALESCE(employee_salaries.bonus_amount, 0)
                    + COALESCE(employee_salaries.salary_adjustment, 0)) as additions,
                SUM(employee_salaries.net_salary) as net_payable,
                SUM(CASE WHEN LOWER(employee_salaries.status) = 'paid' THEN employee_salaries.net_salary ELSE 0 END) as paid,
                SUM(CASE WHEN LOWER(employee_salaries.status) != 'paid' THEN employee_salaries.net_salary ELSE 0 END) as due")
            ->groupBy('users.company_id')
            ->get()
            ->keyBy('company_id');

        // deductions/additions mirror the Salary Sheet's own columns so its
        // company-wise footer can total the same figures the rows show.
        $companyStats = $companies->mapWithKeys(function ($company) use ($companyStatsRaw) {
            $row = $companyStatsRaw->get($company->id);
            return [$company->id => [
                'headcount' => (int) ($row->headcount ?? 0),
                'gross' => (float) ($row->gross ?? 0),
                'deductions' => (float) ($row->deductions ?? 0),
                'additions' => (float) ($row->additions ?? 0),
                'net_payable' => (float) ($row->net_payable ?? 0),
                'paid' => (float) ($row->paid ?? 0),
                'due' => (float) ($row->due ?? 0),
            ]];
        });

        $summary = $companyId && $companyStats->has($companyId)
            ? $companyStats->get($companyId)
            : [
                'headcount' => $companyStats->sum('headcount'),
                'gross' => $companyStats->sum('gross'),
                'net_payable' => $companyStats->sum('net_payable'),
                'paid' => $companyStats->sum('paid'),
                'due' => $companyStats->sum('due'),
            ];

        // ── Payroll history — one row per payroll month ──────────────────────
        // Deliberately not scoped to $period (that's the point of a history), but
        // it does follow the company / employee / search filters so the months
        // you're comparing are the same slice the sheet above is showing.
        $payrollHistory = EmployeeSalary::query()
            ->join('users', 'users.id', '=', 'employee_salaries.user_id')
            ->when($companyId, fn ($q) => $q->where('users.company_id', $companyId))
            ->when($request->filled('user_id'), fn ($q) => $q->where('employee_salaries.user_id', $request->user_id))
            ->tap(fn ($q) => $this->applyEmployeeSearch($q, $request->search))
            ->selectRaw("employee_salaries.year,
                employee_salaries.month,
                COUNT(*) as staff_total,
                SUM(CASE WHEN LOWER(employee_salaries.status) = 'paid' THEN 1 ELSE 0 END) as staff_paid,
                SUM(employee_salaries.gross_salary) as gross,
                SUM(CASE WHEN LOWER(employee_salaries.status) = 'paid' THEN employee_salaries.net_salary ELSE 0 END) as net_paid,
                SUM(CASE WHEN LOWER(employee_salaries.status) != 'paid' THEN employee_salaries.net_salary ELSE 0 END) as outstanding,
                COUNT(DISTINCT users.company_id) as runs")
            ->groupBy('employee_salaries.year', 'employee_salaries.month')
            ->orderByDesc('employee_salaries.year')
            ->orderByDesc('employee_salaries.month')
            ->get()
            ->map(function ($row) {
                $row->period = sprintf('%04d-%02d', $row->year, $row->month);
                $row->label = \Carbon\Carbon::createFromDate($row->year, $row->month, 1)->format('F Y');
                $row->fully_paid = (int) $row->staff_paid === (int) $row->staff_total;

                return $row;
            });

        // $users drives the "Add New" form — only currently active employees, since
        // payroll shouldn't be generated for someone who has left.
        $users = User::orderBy('name')->where('is_super_admin', 0)
                        ->when($isPlainEmployee, function ($q) {
                            return $q->where('id', auth()->id());
                        })
                        ->where('status', 'active')
                        ->role('employee')->get();

        // $salaryUsers additionally keeps anyone who already has payroll history,
        // active or not. Roughly half the employees on these sheets have resigned,
        // and without them the edit modal renders a blank Users field for their
        // rows and the employee filter can't reach their sheet at all.
        $salaryUsers = $users;

        if (! $isPlainEmployee) {
            $pastSalaryUserIds = EmployeeSalary::distinct()->pluck('user_id');

            $salaryUsers = $users->merge(
                User::whereIn('id', $pastSalaryUserIds)
                    ->whereNotIn('id', $users->pluck('id'))
                    ->where('is_super_admin', 0)
                    ->get()
            )->sortBy('name')->values();
        }

        $templates = SalaryTemplate::when($isPlainEmployee, function ($q) {
                        return $q->where('id', auth()->user()->salary_template_id);
                    })
                    ->orderBy('name')->get();

        $banks = \App\Models\Bank::where('status', 1)->orderBy('name')->get();

        return view('employee-salaries.index', compact(
            'datas',
            'users',
            'salaryUsers',
            'templates',
            'banks',
            'companies',
            'companyStats',
            'summary',
            'companyId',
            'period',
            'payrollHistory',
            'paidFromAccounts'
        ));
    }

    /**
     * Printable per-employee Paid-vs-Due summary — running totals across a
     * date range (defaults to all-time), for management to see who's owed
     * what without needing to open every individual payslip. Complements
     * the month-scoped Salary Sheet (index()) which only shows one period
     * at a time and doesn't aggregate per employee.
     */
    /**
     * Superseded by the Payroll Reports hub, which shows this same Paid/Due
     * data as its "Overall" tab alongside the loan, advance and payslip
     * reports. Kept as a redirect so existing links and bookmarks still land
     * somewhere useful instead of 404ing.
     */
    public function paidDueReport(Request $request)
    {
        return redirect()->route('role.report.payroll', array_merge(
            ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'type' => 'overall'],
            $request->only(['company_id', 'from', 'to'])
        ));
    }

    /**
     * Standalone print/PDF layout for the same report — same data as
     * paidDueReport() (via the shared private method, so the two can never
     * disagree), rendered on its own page (no app shell) styled like
     * party-statement.print-statement: letterhead, summary strip, compact
     * table, signature block, auto window.print() on load.
     */
    public function paidDueReportPrint(Request $request)
    {
        $data = $this->computePaidDueReportData($request);
        $data['company'] = Company::first();

        return view('employee-salaries.print-paid-due-report', $data);
    }

    /**
     * Narrow a salary query to one employee by id or name. Employee ids carry a
     * space ("WAI25 503") that nobody types consistently, so the id is also
     * compared with the spaces stripped from both sides.
     *
     * Applied to the rows and the summary cards alike — if only one of them
     * honoured the search, the totals would describe people the table isn't showing.
     */
    private function applyEmployeeSearch($query, $search): void
    {
        $search = trim((string) $search);
        if ($search === '') {
            return;
        }

        $bare = str_replace(' ', '', $search);

        $query->where(function ($q) use ($search, $bare) {
            $q->where('users.employee_id_no', 'like', "%{$search}%")
                ->orWhereRaw("REPLACE(users.employee_id_no, ' ', '') LIKE ?", ["%{$bare}%"])
                ->orWhere('users.name', 'like', "%{$search}%");
        });
    }

    private function computePaidDueReportData(Request $request): array
    {
        $authUser = Auth::user();
        $canViewAllCompanies = $authUser->can('view all salary');
        $userCompanyId = $authUser->company_id;

        $companyId = $request->filled('company_id') ? (int) $request->company_id : null;
        if (!$canViewAllCompanies && !empty($userCompanyId)) {
            $companyId = (int) $userCompanyId;
        }

        $from = $request->filled('from') && preg_match('/^\d{4}-\d{1,2}$/', $request->from) ? $request->from : null;
        $to = $request->filled('to') && preg_match('/^\d{4}-\d{1,2}$/', $request->to) ? $request->to : null;

        // A Pending EmployeeSalary row can already carry a partial payment,
        // so treating status alone as "0 paid / full amount due" understates
        // paid and overstates due for any partially-settled month. Net out
        // whatever has already been settled — via the one shared rule, so
        // this can never drift from the profile's Salary Due KPI, the
        // Payslips tab or the payroll report again.
        $paidPerSalary = PaymentSchedule::settledTotalsSubquery(EmployeeSalary::class);

        $query = EmployeeSalary::query()
            ->join('users', 'users.id', '=', 'employee_salaries.user_id')
            ->leftJoinSub($paidPerSalary, 'schedule_paid', function ($join) {
                $join->on('schedule_paid.schedulable_id', '=', 'employee_salaries.id');
            })
            ->when($companyId, fn($q) => $q->where('users.company_id', $companyId))
            ->when($from, function ($q) use ($from) {
                [$fy, $fm] = array_map('intval', explode('-', $from));
                $q->whereRaw('(employee_salaries.year * 100 + CAST(employee_salaries.month AS UNSIGNED)) >= ?', [$fy * 100 + $fm]);
            })
            ->when($to, function ($q) use ($to) {
                [$ty, $tm] = array_map('intval', explode('-', $to));
                $q->whereRaw('(employee_salaries.year * 100 + CAST(employee_salaries.month AS UNSIGNED)) <= ?', [$ty * 100 + $tm]);
            });

        if ($authUser->hasRole('employee') && !$authUser->hasAnyRole(['accountant', 'hr'])) {
            $query->where('employee_salaries.user_id', $authUser->id);
        }

        $rows = $query->groupBy('users.id', 'users.name')
            ->orderBy('users.name')
            ->selectRaw('users.id as user_id, users.name as employee_name')
            ->selectRaw('SUM(employee_salaries.gross_salary) as total_gross')
            ->selectRaw("
                SUM(CASE
                    WHEN employee_salaries.status = 'Paid' THEN employee_salaries.net_salary
                    ELSE LEAST(COALESCE(schedule_paid.paid_total, 0), employee_salaries.net_salary)
                END) as total_paid
            ")
            ->selectRaw("
                SUM(CASE
                    WHEN employee_salaries.status = 'Paid' THEN 0
                    ELSE GREATEST(employee_salaries.net_salary - COALESCE(schedule_paid.paid_total, 0), 0)
                END) as total_due
            ")
            ->selectRaw("COUNT(CASE WHEN employee_salaries.status = 'Paid' THEN 1 END) as paid_count")
            ->selectRaw("COUNT(CASE WHEN employee_salaries.status != 'Paid' AND COALESCE(schedule_paid.paid_total, 0) > 0 THEN 1 END) as partial_count")
            ->selectRaw("COUNT(CASE WHEN employee_salaries.status != 'Paid' AND COALESCE(schedule_paid.paid_total, 0) <= 0 THEN 1 END) as due_count")
            ->get();

        $grandTotals = [
            'total_gross' => $rows->sum('total_gross'),
            'total_paid'  => $rows->sum('total_paid'),
            'total_due'   => $rows->sum('total_due'),
        ];

        $companiesQuery = Company::where('status', 1)->orderBy('order')->orderBy('name');
        if (!$canViewAllCompanies && !empty($userCompanyId)) {
            $companiesQuery->where('id', $userCompanyId);
        }
        $companies = $companiesQuery->get();

        return compact('rows', 'grandTotals', 'from', 'to', 'companyId', 'companies');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('employee-salaries.create-modal');
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
            'user_id' => 'required',
            'loan_id' => 'nullable',
            'advance_salary_id' => 'nullable',
            'date' => 'required',
            'salary_generation_date' => 'required',
            'scheduled_date' => 'required|date',
            'payment_method' => 'required',
            'status' => 'required'
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ]);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }
        $employeeSalary = EmployeeSalary::where('user_id', $request->user_id)
            ->where('month', explode('-', $request->date)[1])
            ->where('year', explode('-', $request->date)[0])
            ->first();
        if ($employeeSalary) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Salary for this employee already exists for the selected month and year.'
                ]);
            }
            return redirect()->back()->with('error', 'Salary for this employee already exists for the selected month and year.')->withInput();
        }
        try {
            [$year, $month] = explode('-', $request->date);

            $empSalary = $this->payroll->createEmployeeSalaryRecord([
                'user_id' => $request->user_id,
                'loan_id' => $request->loan_id,
                'advance_salary_id' => $request->advance_salary_id,
                'month' => (string) $month,
                'year' => (int) $year,
                'bonus_label' => $request->bonus_label,
                'bonus_amount' => $request->bonus_amount,
                'gross_salary' => $request->gross_salary,
                'loan_deduction' => $request->loan_deduction ?? 0,
                'advance_salary_deduction' => $request->advance_salary_deduction ?? 0,
                'early_leave_deduction' => $request->early_leave_deduction ?? 0,
                'over_time' => $request->over_time ?? 0,
                'over_time_days' => $request->over_time_days ?? 0,
                'overtime_salary' => $request->overtime_salary ?? 0,
                'absent_deduction' => $request->absent_deduction ?? 0,
                'leave_deduction' => $request->leave_deduction ?? 0,
                'late_deduction' => $request->late_deduction ?? 0,
                'salary_adjustment' => $request->salary_adjustment ?? 0,
                'total_deductions' => $request->total_deductions ?? 0,
                'net_salary' => $request->net_salary ?? 0,
                'salary_generation_date' => $request->salary_generation_date,
                'scheduled_date' => $request->scheduled_date,
                'payment_method' => $request->payment_method,
                'status' => $request->status,
                'notes' => $request->notes ?? null,
                'bank_id' => $request->bank_id,
            ]);

            // Same payslip issuance + notifications (in-app, push, email,
            // SMS) as the automated monthly payroll job — one shared path
            // via PayrollService so a manually created salary record
            // notifies the employee identically to an automated one.
            ['payslip' => $payslip] = $this->payroll->issuePayslip($empSalary);
            $this->payroll->sendPayslipNotifications($empSalary, $payslip);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data created successfully.'
                ]);
            }
        } catch (\Throwable $th) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $th->getMessage()
                ]);
            }
            return redirect()->back()->with('error', $th->getMessage());
        }



        return redirect()->route('role.employee-salaries.index')->with('success', 'Data created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return view('employee-salaries.edit-modal', compact('id'));
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
        $empSalary = EmployeeSalary::findOrFail($id);
        if (empty($empSalary)) {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!'
            ]);
        }
        $validated = $request->validate([
            'user_id' => 'required',
            'salary_template_id' => 'required',
            'date' => 'required',
            'payment_date' => 'required',
            'scheduled_date' => 'nullable|date',
            'payment_method' => 'required',
            'status' => 'required',
            // Bank account is only required when the salary is Paid via a
            // non-cash method — cash payments credit the Cash account
            // instead, so no bank needs to be selected.
            'bank_id' => Rule::requiredIf(
                in_array($request->status, ['Paid', 'paid']) && $request->payment_method !== 'cash'
            ),
        ]);

        try {
            DB::transaction(function () use ($request, $empSalary) {
                [$year, $month] = explode('-', $request->date);
                $empSalary->update([
                    'user_id' => $request->user_id,
                    'salary_template_id' => $request->salary_template_id,
                    'month' => (string) $month,
                    'year' => (int) $year,
                    'gross_salary' => $request->gross_salary,
                    'total_deductions' => $request->total_deductions ?? 0,
                    'net_salary' => $request->net_salary ?? 0,
                    'payment_date' => $request->payment_date,
                    'scheduled_date' => $request->scheduled_date,
                    'bank_id' => $request->bank_id,
                    'status' => $request->status
                ]);

                // ── JOURNAL UPDATE (auto) ─────────────────────────────────
                $salaryExpenseAccount = \App\Models\Account::where('code', config('accounts.salary_expense'))->firstOrFail();
                $salaryPayableAccount = \App\Models\Account::where('code', config('accounts.salary_payable'))->firstOrFail();

                $journal = \App\Models\JournalEntry::where('source', 'salary')
                                                ->where('source_id', $empSalary->id)
                                                ->first();

                // Always the employee's own company, not the editing user's —
                // an admin/super-admin (company_id null, falling back to 2)
                // editing someone else's salary must not misfile it under
                // their own or the fallback company.
                $journalCompanyId = $empSalary->user->company_id ?? auth()->user()->company_id ?? 2;

                if ($journal) {
                    $journal->items()->delete();
                    $journal->update([
                        'company_id'  => $journalCompanyId,
                        'date'        => $request->payment_date,
                        'description' => 'Salary (edited) — ' . ($empSalary->user->name ?? 'Employee'),
                    ]);
                } else {
                    $journal = \App\Models\JournalEntry::create([
                        'company_id'  => $journalCompanyId,
                        'created_by'  => auth()->id(),
                        'date'        => $request->payment_date,
                        'reference'   => 'SAL-' . $empSalary->user_id . '-' . $empSalary->month . '-' . $empSalary->year,
                        'source'      => 'salary',
                        'source_id'   => $empSalary->id,
                        'description' => 'Salary (edited) — ' . ($empSalary->user->name ?? 'Employee'),
                    ]);
                }

                // Debit: Salary Expense — always, full net salary
                \App\Models\JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $salaryExpenseAccount->id,
                    'debit'            => $empSalary->net_salary,
                    'credit'           => 0,
                    'note'             => 'Net salary — ' . ($empSalary->user->name ?? 'Employee'),
                ]);

                // Credit: Cash (if paid in cash) OR Bank (if paid otherwise) OR Salary Payable (if unpaid)
                if (in_array($request->status, ['paid', 'Paid']) && $request->payment_method === 'cash') {
                    $cashAccount = \App\Models\Account::where('code', config('accounts.office_cash'))->firstOrFail();
                    \App\Models\JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id'       => $cashAccount->id,
                        'debit'            => 0,
                        'credit'           => $empSalary->net_salary,
                        'note'             => 'Salary paid via cash',
                    ]);
                } elseif (in_array($request->status, ['paid', 'Paid'])) {
                    if (!$request->bank_id) {
                        throw new \Exception('Bank account is required when salary status is paid.');
                    }
                    $bank = \App\Models\Bank::find($request->bank_id);
                    if (!$bank || !$bank->account_id) {
                        throw new \Exception('Bank is not linked to a chart-of-accounts account.');
                    }
                    \App\Models\JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id'       => $bank->account_id,
                        'debit'            => 0,
                        'credit'           => $empSalary->net_salary,
                        'note'             => 'Salary paid via ' . $request->payment_method,
                    ]);
                } else {
                    \App\Models\JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id'       => $salaryPayableAccount->id,
                        'debit'            => 0,
                        'credit'           => $empSalary->net_salary,
                        'note'             => 'Salary payable — ' . ($empSalary->user->name ?? 'Employee'),
                    ]);
                }
                // ── END JOURNAL ───────────────────────────────────────────

                $paymentDate = $request->payment_date ?? $empSalary->salary_generation_date;

                // $havePayment = Payment::where('user_id', $empSalary->user_id)
                //     ->where('employee_salary_id', $empSalary->id)
                //     ->first();

                // if ($havePayment) {
                //     $havePayment->update([
                //         'payment_date' => $paymentDate,
                //         'payment_method' => $request->payment_method,
                //         'amount' => $empSalary->net_salary,
                //         'transaction_no' => $request->transaction_no,
                //         'notes' => $request->notes
                //     ]);
                // } else {
                //     Payment::create([
                //         'user_id' => $empSalary->user_id,
                //         'employee_salary_id' => $empSalary->id,
                //         'payment_date' => $paymentDate,
                //         'payment_method' => $request->payment_method,
                //         'amount' => $empSalary->net_salary,
                //         'transaction_no' => $request->transaction_no,
                //         'notes' => $request->notes
                //     ]);
                // }

                // ── PAYMENT SCHEDULE (auto) ───────────────────────────────
                // Editing a payslip may only ever touch a schedule that has
                // taken NO money yet. A schedule carrying a paid_amount is a
                // settled financial record — a journal entry, a Payment row
                // and an employee-ledger row were all written against it, and
                // rewriting it here leaves those three behind.
                //
                // That is exactly what happened on 2026-08-18: a ৳10,000
                // partial payment was recorded, the payslip was re-saved ten
                // minutes later, and the old updateOrCreate() (keyed on
                // schedulable_id alone, so it matched the *settled* row)
                // reset status 'paid' → 'pending' and amount back to the full
                // net salary while leaving paid_amount = 10000 stranded on it.
                // The employee then showed ৳20,000 due for a month he had
                // already been paid half of.
                $scheduleQuery = fn () => PaymentSchedule::where('schedulable_type', EmployeeSalary::class)
                    ->where('schedulable_id', $empSalary->id);

                $alreadySettled = round((float) $scheduleQuery()->settled()->sum('paid_amount'), 2);

                // The one schedule this edit is allowed to write to: still in
                // the queue, and nothing has been paid against it.
                $openSchedule = $scheduleQuery()
                    ->whereIn('status', ['pending', 'overdue', 'approved'])
                    ->where(fn ($q) => $q->whereNull('paid_amount')->orWhere('paid_amount', '<=', 0))
                    ->orderBy('id')
                    ->first();

                if (in_array($request->status, ['paid', 'Paid'])) {
                    if ($openSchedule) {
                        $openSchedule->update([
                            'status' => 'paid',
                            'paid_date' => $request->payment_date,
                        ]);
                    }
                } elseif ($empSalary->net_salary > 0) {
                    // What is still owed, not the whole net salary — billing
                    // the full amount again is how a part-paid month turns
                    // back into a fully-unpaid one. Never negative: an edit
                    // that drops the salary below what was already paid
                    // leaves nothing outstanding, not a negative payable.
                    $outstanding = max(0, round((float) $empSalary->net_salary - $alreadySettled, 2));

                    $attributes = [
                        'type' => 'pay',
                        'party_type' => 'employee',
                        'party_id' => $empSalary->user_id,
                        'party_name' => $empSalary->user?->name,
                        'source_label' => 'Salary - ' . $empSalary->month . '/' . $empSalary->year,
                        'amount' => $outstanding,
                        'scheduled_date' => $empSalary->scheduled_date ?? $request->payment_date,
                    ];

                    // Only overwrite the note when the edit actually carries
                    // one. A remainder schedule's note is its provenance
                    // ("Remainder from partial payment (SCH-357)") and blanking
                    // it on an unrelated re-save loses that trail.
                    if (filled($request->notes)) {
                        $attributes['note'] = $request->notes;
                    }

                    if ($openSchedule) {
                        // Status is deliberately not written: an already
                        // approved schedule must not silently drop back to
                        // pending just because the payslip was edited.
                        $openSchedule->update($attributes);
                    } elseif ($alreadySettled <= 0 && $outstanding > 0) {
                        // No schedule at all yet and nothing settled — this is
                        // the first one for this payslip.
                        PaymentSchedule::create($attributes + [
                            'schedulable_type' => EmployeeSalary::class,
                            'schedulable_id' => $empSalary->id,
                            'status' => 'pending',
                            'created_by' => auth()->id(),
                        ]);
                    }
                    // Otherwise money has already moved against this payslip
                    // and there is no open schedule left to carry the rest.
                    // Leave the settled records exactly as they are.
                }
                // ── END PAYMENT SCHEDULE ──────────────────────────────────
            });
        } catch (\Throwable $th) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $th->getMessage()
                ]);
            }
            return redirect()->back()->with('error', $th->getMessage());
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data updated successfully.'
            ]);
        }

        return redirect()->route('role.employee-salaries.index')->with('success', 'Data updated successfully.');
    }

    public function show($role, $id)
    {
        $data = EmployeeSalary::findOrFail($id);
        return view('employee-salaries.show', compact('data'));
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
            $item = EmployeeSalary::find($request->item_id);
            if ($item) {

                DB::transaction(function () use ($item) {

                    // ── JOURNAL CLEANUP ───────────────────────────────────
                    $journal = \App\Models\JournalEntry::where('source', 'salary')
                        ->where('source_id', $item->id)
                        ->first();
                    if ($journal) {
                        $journal->items()->forceDelete();
                        $journal->forceDelete();
                    }
                    // ── END JOURNAL ───────────────────────────────────────

                    // ── PAYMENT SCHEDULE CLEANUP ───────────────────────────
                    \App\Models\PaymentSchedule::where('schedulable_type', EmployeeSalary::class)
                        ->where('schedulable_id', $item->id)
                        ->delete();
                    // ── END PAYMENT SCHEDULE ───────────────────────────────

                    // ── EMPLOYEE LEDGER CLEANUP ─────────────────────────────
                    \App\Models\EmployeeLedger::where('source_type', EmployeeSalary::class)
                        ->where('source_id', $item->id)
                        ->forceDelete();
                    // ── END EMPLOYEE LEDGER ─────────────────────────────────

                    // ── LOAN EMI CLEANUP ────────────────────────────────────
                    // Give back whatever this payslip took off a loan. Leaving it
                    // would show the employee as having repaid an instalment out
                    // of a salary that no longer exists, and the loan register
                    // reads those rows directly.
                    app(\App\Services\LoanLedgerService::class)->reverseForSalary($item->id);
                    // ── END LOAN EMI ────────────────────────────────────────

                    Payment::where('user_id', $item->user_id)
                        ->where('employee_salary_id', $item->id)
                        ->delete();

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

    public function getEmployeeSalary(Request $request)
    {
        try {
            $issuedSalaryIds = Payslip::where('user_id', $request->user_id)
                ->pluck('employee_salary_id')
                ->filter()
                ->toArray();

            $data = EmployeeSalary::where('user_id', $request->user_id)
                ->when(!empty($issuedSalaryIds), function ($q) use ($issuedSalaryIds) {
                    return $q->whereNotIn('id', $issuedSalaryIds);
                })
                ->orderBy('id', 'ASC')
                ->get();
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
    public function getAttendanceData(Request $request)
    {
        $userId = $request->input('user_id');
        $dateInput = $request->month; // This is "2026-01"

        if (!$userId || !$dateInput) {
            return response()->json(['absent' => 0, 'late' => 0]);
        }

        $timestamp = strtotime($dateInput);
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['absent' => 0, 'late' => 0]);
        }

        $result = $this->payroll->calculateAttendanceDeductions($user, (int) date('Y', $timestamp), (int) date('m', $timestamp));

        return response()->json($result);
    }

    public function handleAction(Request $request, $role, $id, $action)
    {
        // Explicitly find the salary by ID
        $data = \App\Models\EmployeeSalary::findOrFail($id);


        if ($action === 'download') {
            $logoUrl = asset($data->user->company->logo) ?? asset('default-logo.png');
            $logoData = base64_encode(file_get_contents($logoUrl));
            $logoBase64 = 'data:image/png;base64,' . $logoData;
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('employee-salaries.pdf', compact('data', 'logoBase64'));
            return $pdf->download("{$data->user->name}_Payslip_{$data->month}_{$data->year}.pdf");
        }

        if ($action === 'email') {
            $data = EmployeeSalary::with('user')->findOrFail($id);

            $viewUrl = URL::temporarySignedRoute(
                'salary.view',
                now()->addDays(30),
                ['id' => $id]
            );

            $subject = 'আপনার বেতন বিবরণী (Payslip) - ' . $data->month . '/' . $data->year;
            $htmlContent = view('emails.payslip-notice', [
                'user' => $data->user,
                'empSalary' => $data,
                'viewUrl' => $viewUrl,
            ])->render();

            $response = sendBrevoMail($data->user->email, $data->user->name, $subject, $htmlContent);

            if ($response->successful()) {
                return back()->with('success', 'ইমেল সফলভাবে পাঠানো হয়েছে।');
            } else {
                return back()->with('error', 'ইমেল পাঠাতে সমস্যা হয়েছে।');
            }
        }

        return back();
    }
    public function view(Request $request, $id)
    {
        if (!$request->hasValidSignature()) {
            abort(401);
        }

        $data = EmployeeSalary::with('user')->findOrFail($id);
        return view('employee-salaries.view', compact('data'));
    }

    public function download(Request $request, $id)
    {
        if (!$request->hasValidSignature()) {
            abort(401);
        }

        $data = EmployeeSalary::with('user.company', 'user.salary_template')->findOrFail($id);

        $logoUrl = asset($data->user->company->logo ?? 'images/site-setting/69401c60d0949.png');
        $logoData = @file_get_contents($logoUrl);

        if ($logoData === false) {
            $fallbackLogoUrl = 'https://epal.com.bd/images/site-setting/69401c60d0949.png';
            $logoData = @file_get_contents($fallbackLogoUrl);
        }

        $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData ?: '');
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('employee-salaries.pdf', compact('data', 'logoBase64'));

        return $pdf->download("{$data->user->name}_Payslip_{$data->month}_{$data->year}.pdf");
    }
}
