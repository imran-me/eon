<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\AdvanceSalary;
use App\Models\Attendance;
use App\Models\Bank;
use App\Models\Company;
use App\Models\Department;
use App\Models\DeviceSetting;
use App\Models\DeviceUser;
use App\Models\EmployeeDocument;
use App\Models\EmployeePayment;
use App\Models\EmployeePaymentAllocation;
use App\Models\EmployeeProfile;
use App\Models\EmployeeSalary;
use App\Models\Leave as LeaveModel;
use App\Models\Loan;
use App\Models\PaymentSchedule;
use App\Models\SalaryTemplate;
use App\Models\User;
use App\Services\AdvancePostingService;
use App\Services\AttendanceMonthService;
use App\Services\PayrollService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController implements HasMiddleware
{
    public function __construct(private PayrollService $payroll) {}

    /**
     * Every public method must appear here. A method left out of this map is
     * NOT unprotected-by-design — Laravel simply runs it with no check and
     * warns about nothing. `search`, both print reports, `documents` and
     * `updateDocuments` were missing, which left any logged-in account able to
     * print any employee's full salary ledger and to overwrite their documents.
     *
     * Permission answers "may you use this screen at all". It does NOT answer
     * "may you use it on this person" — that is authorizeEmployeeAccess() and
     * applyUserScope() below, and both are required.
     */
    public static function middleware(): array
    {
        return [
            // Read-only screens. `search` belongs here even though it powers the
            // header suggestion box on every page: its results are only ever
            // actionable on a screen that already needs `view users`, and both
            // callers catch a non-2xx and just hide the dropdown.
            new Middleware('permission:view users', only: [
                'index', 'show', 'summary', 'search', 'documents',
                'sendEmployeeLedger',
                'salaryTransactionReportPrint', 'payslipsReportPrint', 'leaveEncashmentReportPrint', 'attendanceReportPrint',
            ]),
            new Middleware('permission:create users', only: ['create', 'store']),
            // updateDocuments uploads files onto another user's record. That is
            // a write, not a view, so it sits with edit rather than with the
            // documents listing above.
            new Middleware('permission:edit users', only: ['edit', 'update', 'updateDocuments']),
            new Middleware('permission:delete users', only: ['destroy']),
        ];
    }

    // ── Record-level access ───────────────────────────────────────────────
    //
    // Two tiers above "your own record", keyed on permissions rather than role
    // names. Role names cannot be trusted for this: users here hold several at
    // once (#12 is accountant+employee+hr, #6 is employee+Operation), so
    // getRoleNames()->first() picks an arbitrary one.
    //
    //   view all users      → every employee, every company   (super admin, admin)
    //   view company users  → every employee in own company   (hr, Operation)
    //   neither             → own record only                 (employee)

    /**
     * May the caller open THIS employee's record? Permission alone is not
     * enough — `view users` says the screen exists, not whose data it may show.
     *
     * Call this in every action that resolves a {user} from the URL.
     */
    private function authorizeEmployeeAccess(User $employee): void
    {
        $authUser = Auth::user();

        // Your own record, whatever your role. An employee must always be able
        // to open their own payslips and salary ledger.
        if ($authUser->id === $employee->id) {
            return;
        }

        if ($authUser->can('view all users')) {
            return;
        }

        // Own company only, and only for roles trusted to look past themselves.
        // company_id must be present on BOTH sides — a null on either would
        // otherwise make null === null true and open every unassigned user.
        if ($authUser->can('view company users')
            && $authUser->company_id
            && $employee->company_id
            && $authUser->company_id === $employee->company_id) {
            return;
        }

        abort(403, 'You can only open your own profile.');
    }

    /**
     * The list-level half of the same rule, for queries that return many users.
     * Kept beside authorizeEmployeeAccess() deliberately: if one tier changes,
     * both must change together or the list and the detail page disagree.
     */
    private function applyUserScope($query): void
    {
        $authUser = Auth::user();

        if ($authUser->can('view all users')) {
            return;
        }

        if ($authUser->can('view company users') && $authUser->company_id) {
            $query->where('users.company_id', $authUser->company_id);

            return;
        }

        $query->where('users.id', $authUser->id);
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index($role, Request $request)
    {
        if (! $request->role) {
            $role = $role;
        } else {
            $role = $request->role;
        }
        $role = normalizeRoleName($role);

        // Logic to list users with filters
        $query = User::with(['roles', 'company', 'profile.department'])->orderBy('id', 'desc')->role($role);

        // Before any filter: a caller may only ever be shown users they are
        // allowed to open. The company chip below is a filter within that set,
        // never a way out of it.
        $this->applyUserScope($query);

        // Apply filters
        if ($request->filled('name')) {
            $query->where('name', 'like', '%'.$request->input('name').'%');
        }

        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%'.$request->input('phone').'%');
        }

        if ($request->filled('company')) {
            $query->where('company_id', $request->input('company'));
        }

        if ($request->filled('department')) {
            $query->whereHas('profile', function ($q) {
                $q->where('department_id', request()->input('department'));
            });
        }

        $statusCounts = [
            'active' => (clone $query)->where('status', 'active')->count(),
            'resigned' => (clone $query)->where('status', 'resigned')->count(),
            'inactive' => (clone $query)->where('status', 'inactive')->count(),
            'all' => (clone $query)->count(),
        ];
        $status = in_array($request->input('status'), ['active', 'resigned', 'inactive', 'all'], true)
            ? $request->input('status')
            : 'active';
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $users = $query->paginate(20);

        // Get dropdown options
        $departments = Department::orderBy('name')->get();
        $companies = Company::orderBy('name')->get();

        return view('panel.users.index', compact('users', 'departments', 'companies', 'role', 'status', 'statusCounts'));
    }

    public function printList($role, Request $request)
    {
        $role = normalizeRoleName($request->filled('role') ? $request->input('role') : $role);
        $query = User::with(['roles', 'company', 'profile.department', 'profile.designation'])
            ->orderBy('name')
            ->role($role);

        $this->applyUserScope($query);

        if ($request->filled('name')) {
            $query->where('name', 'like', '%'.$request->input('name').'%');
        }
        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%'.$request->input('phone').'%');
        }
        if ($request->filled('company')) {
            $query->where('company_id', $request->input('company'));
        }
        if ($request->filled('department')) {
            $query->whereHas('profile', fn ($profileQuery) => $profileQuery
                ->where('department_id', $request->input('department')));
        }

        $status = in_array($request->input('status'), ['active', 'resigned', 'inactive', 'all'], true)
            ? $request->input('status')
            : 'active';
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $users = $query->get();
        $company = $request->filled('company')
            ? Company::find($request->input('company'))
            : auth()->user()->company;
        $department = $request->filled('department')
            ? Department::find($request->input('department'))
            : null;
        $groupCompany = Company::whereRaw('LOWER(name) = ?', ['epal group'])->first()
            ?? Company::find(4)
            ?? $company;

        return view('panel.users.print', compact('users', 'company', 'groupCompany', 'department', 'role', 'status'));
    }

    public function search($role, Request $request)
    {
        $query = trim($request->query('q', ''));
        if ($query === '') {
            return response()->json([]);
        }

        $normalizedRole = $this->normalizeRoleName($role);

        // Only the three fields both suggestion boxes actually render. This used
        // to return whole User models, which serialised every column the model
        // does not hide — including two_factor_secret and is_super_admin.
        $users = User::select('id', 'name', 'email')
            ->when($normalizedRole, function ($q) use ($normalizedRole) {
                return $q->role($normalizedRole);
            })
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->tap(fn ($q) => $this->applyUserScope($q))
            ->orderBy('name')
            ->limit(10)
            ->get();

        return response()->json($users);
    }

    protected function normalizeRoleName($role)
    {
        $sluggedRole = Str::slug($role);

        $roleRecord = Role::all()->firstWhere(function ($roleModel) use ($sluggedRole) {
            return Str::slug($roleModel->name) === $sluggedRole;
        });

        return $roleRecord ? $roleRecord->name : $role;
    }

    public function summary($role, $user, Request $request)
    {
        $employee = User::with(['company', 'profile.department', 'profile.designation', 'ledgerEntries.source'])
            ->findOrFail($user);

        $this->authorizeEmployeeAccess($employee);

        if (! $employee->hasRole('employee')) {
            return redirect()->back()->with('error', 'Profile summary is only available for employee users.');
        }

        $returnUrl = $this->safeUserListReturnUrl(
            $request->query('return_url', url()->previous()),
            $role
        );

        $employeeOptionsQuery = User::role('employee')
            ->with(['company:id,name'])
            ->select('users.id', 'users.name', 'users.employee_id_no', 'users.company_id', 'users.email', 'users.phone')
            ->where('users.status', 'active')
            ->orderBy('users.name');
        $this->applyUserScope($employeeOptionsQuery);
        $employeeOptions = $employeeOptionsQuery->get();

        $today = Carbon::today();
        $currentYear = (int) $today->year;
        $currentMonth = (int) $today->month;

        $completedColumnIds = DB::table('columns')
            ->whereRaw("LOWER(name) REGEXP 'done|completed|complete|closed|finish|finished'")
            ->pluck('id');

        $taskTotal = DB::table('task_user')
            ->where('user_id', $employee->id)
            ->distinct()
            ->count('task_id');

        $taskCompleted = DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.user_id', $employee->id)
            ->whereNull('tasks.deleted_at')
            ->when($completedColumnIds->isNotEmpty(), function ($query) use ($completedColumnIds) {
                $query->whereIn('tasks.column_id', $completedColumnIds);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->distinct()
            ->count('task_user.task_id');

        $taskPending = max($taskTotal - $taskCompleted, 0);

        // Weekly task performance (Mon-Sun)
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $today->copy()->endOfWeek(Carbon::SUNDAY);

        $weeklyCreatedRaw = DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.user_id', $employee->id)
            ->whereNull('tasks.deleted_at')
            ->whereBetween(DB::raw('DATE(tasks.created_at)'), [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->selectRaw('DATE(tasks.created_at) as day_date, COUNT(DISTINCT task_user.task_id) as total')
            ->groupBy('day_date')
            ->pluck('total', 'day_date');

        $weeklyCompletedRaw = DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.user_id', $employee->id)
            ->whereNull('tasks.deleted_at')
            ->when($completedColumnIds->isNotEmpty(), function ($query) use ($completedColumnIds) {
                $query->whereIn('tasks.column_id', $completedColumnIds);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->whereBetween(DB::raw('DATE(tasks.updated_at)'), [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->selectRaw('DATE(tasks.updated_at) as day_date, COUNT(DISTINCT task_user.task_id) as total')
            ->groupBy('day_date')
            ->pluck('total', 'day_date');

        $taskWeeklyLabels = [];
        $taskWeeklyCreatedData = [];
        $taskWeeklyCompletedData = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->copy()->addDays($i);
            $dayKey = $day->toDateString();

            $taskWeeklyLabels[] = $day->format('D');
            $taskWeeklyCreatedData[] = (int) ($weeklyCreatedRaw[$dayKey] ?? 0);
            $taskWeeklyCompletedData[] = (int) ($weeklyCompletedRaw[$dayKey] ?? 0);
        }

        // Monthly task performance (current year)
        $monthlyCreatedRaw = DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.user_id', $employee->id)
            ->whereNull('tasks.deleted_at')
            ->whereYear('tasks.created_at', $currentYear)
            ->selectRaw('MONTH(tasks.created_at) as month_no, COUNT(DISTINCT task_user.task_id) as total')
            ->groupBy('month_no')
            ->pluck('total', 'month_no');

        $monthlyCompletedRaw = DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.user_id', $employee->id)
            ->whereNull('tasks.deleted_at')
            ->when($completedColumnIds->isNotEmpty(), function ($query) use ($completedColumnIds) {
                $query->whereIn('tasks.column_id', $completedColumnIds);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->whereYear('tasks.updated_at', $currentYear)
            ->selectRaw('MONTH(tasks.updated_at) as month_no, COUNT(DISTINCT task_user.task_id) as total')
            ->groupBy('month_no')
            ->pluck('total', 'month_no');

        $taskMonthlyLabels = [];
        $taskMonthlyCreatedData = [];
        $taskMonthlyCompletedData = [];

        for ($month = 1; $month <= 12; $month++) {
            $taskMonthlyLabels[] = Carbon::create()->month($month)->format('M');
            $taskMonthlyCreatedData[] = (int) ($monthlyCreatedRaw[$month] ?? 0);
            $taskMonthlyCompletedData[] = (int) ($monthlyCompletedRaw[$month] ?? 0);
        }

        $attendanceMonth = Attendance::where('user_id', $employee->id)
            ->whereYear('date', $currentYear)
            ->whereMonth('date', $currentMonth)
            ->selectRaw("SUM(CASE WHEN LOWER(status) IN ('present', 'late') THEN 1 ELSE 0 END) as present_count")
            ->selectRaw("SUM(CASE WHEN LOWER(status) = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->selectRaw("SUM(CASE WHEN LOWER(status) = 'leave' THEN 1 ELSE 0 END) as leave_count")
            ->first();

        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();
        $attendanceMonthRaw = DB::table('attendances as a')
            ->leftJoin('shifts as s', 's.id', '=', 'a.shift_id')
            ->leftJoin('attendence_settings as aset', 'aset.id', '=', 'a.attendence_setting_id')
            ->where('a.user_id', $employee->id)
            ->whereBetween('a.date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->selectRaw('DATE(a.date) as day_date')
            ->selectRaw(
                'SUM(CASE
                    WHEN a.check_in IS NOT NULL
                    THEN 1 ELSE 0 END) as present_count'
            )
            ->selectRaw(
                'SUM(CASE
                    WHEN a.check_in IS NOT NULL
                        AND s.start_time IS NOT NULL
                        AND TIME_TO_SEC(a.check_in) > (TIME_TO_SEC(s.start_time) + (COALESCE(aset.time_after_checkin, 0) * 60))
                    THEN 1 ELSE 0 END) as late_count'
            )
            ->selectRaw("SUM(CASE WHEN LOWER(a.status) = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->selectRaw(
                'SUM(CASE
                    WHEN a.check_in IS NOT NULL
                        AND s.start_time IS NOT NULL
                        AND TIME_TO_SEC(a.check_in) > (TIME_TO_SEC(s.start_time) + (COALESCE(aset.time_after_checkin, 0) * 60))
                    THEN (TIME_TO_SEC(a.check_in) - (TIME_TO_SEC(s.start_time) + (COALESCE(aset.time_after_checkin, 0) * 60))) / 60
                    ELSE 0 END) as late_minutes'
            )
            ->selectRaw(
                'SUM(CASE
                    WHEN a.check_in IS NOT NULL AND a.check_out IS NOT NULL
                    THEN (CASE
                        WHEN TIME_TO_SEC(a.check_out) >= TIME_TO_SEC(a.check_in)
                        THEN (TIME_TO_SEC(a.check_out) - TIME_TO_SEC(a.check_in))
                        ELSE (TIME_TO_SEC(a.check_out) + 86400 - TIME_TO_SEC(a.check_in))
                    END) / 3600
                    ELSE 0 END) as working_hours'
            )
            ->groupBy('day_date')
            ->get()
            ->keyBy('day_date');

        $attendanceMonthLabels = [];
        $attendanceMonthPresentData = [];
        $attendanceMonthLateData = [];
        $attendanceMonthAbsentData = [];
        $attendanceMonthLateMinutesData = [];
        $attendanceMonthWorkingHoursData = [];

        $daysInMonth = $monthStart->diffInDays($monthEnd) + 1;
        for ($i = 0; $i < $daysInMonth; $i++) {
            $day = $monthStart->copy()->addDays($i);
            $dayKey = $day->toDateString();
            $dayRecord = $attendanceMonthRaw->get($dayKey);
            $attendanceMonthLabels[] = $day->format('M d');
            $attendanceMonthPresentData[] = (int) ($dayRecord->present_count ?? 0);
            $attendanceMonthLateData[] = (int) ($dayRecord->late_count ?? 0);
            $attendanceMonthAbsentData[] = (int) ($dayRecord->absent_count ?? 0);
            $attendanceMonthLateMinutesData[] = round((float) ($dayRecord->late_minutes ?? 0), 2);
            $attendanceMonthWorkingHoursData[] = round((float) ($dayRecord->working_hours ?? 0), 2);
        }

        $attendanceMonthTotalLateMinutes = round(array_sum($attendanceMonthLateMinutesData), 2);
        $attendanceMonthTotalWorkingHours = round(array_sum($attendanceMonthWorkingHoursData), 2);

        $leaveSummary = LeaveModel::where('user_id', $employee->id)
            ->whereYear('start_date', $currentYear)
            ->selectRaw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count")
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count")
            ->selectRaw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count")
            ->first();

        $leaveDaysUsed = LeaveModel::where('user_id', $employee->id)
            ->where('status', 'approved')
            ->whereYear('start_date', $currentYear)
            ->get()
            ->sum(function ($leave) {
                return Carbon::parse($leave->start_date)->diffInDays(Carbon::parse($leave->end_date)) + 1;
            });

        $salaryStats = EmployeeSalary::where('user_id', $employee->id)
            ->whereYear('created_at', $currentYear)
            ->selectRaw("SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) as paid_count")
            ->selectRaw("SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_count")
            ->selectRaw('SUM(net_salary) as total_net_salary')
            ->first();

        $latestSalary = EmployeeSalary::where('user_id', $employee->id)
            ->latest('id')
            ->first();

        $loanStats = Loan::where('user_id', $employee->id)
            ->selectRaw("SUM(CASE WHEN status = 'Running' THEN 1 ELSE 0 END) as running_count")
            ->selectRaw("SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_count")
            ->selectRaw('SUM(remaining_amount) as total_remaining')
            ->first();

        // The cached remaining_amount can lag behind transaction history on
        // older loans. The account card must show what is genuinely still
        // recoverable from this employee.
        $loanStats->total_remaining = Loan::with('transactions')
            ->where('user_id', $employee->id)
            ->get()
            ->sum(fn (Loan $loan) => $loan->outstanding);

        $advanceSalaryStats = AdvanceSalary::where('user_id', $employee->id)
            ->selectRaw("SUM(CASE WHEN status = 'Pending' THEN amount ELSE 0 END) as pending_amount")
            ->selectRaw("SUM(CASE WHEN status = 'Approved' THEN amount ELSE 0 END) as approved_amount")
            ->selectRaw("SUM(CASE WHEN status = 'Rejected' THEN amount ELSE 0 END) as rejected_amount")
            ->first();

        // A historical correction can be inserted later with an older
        // entry_date, so neither the greatest id nor the visually last dated
        // row is guaranteed to carry the true closing stored balance. The
        // closing balance is order-independent: total owed minus total paid.
        // Derive it from all active ledger movements so backdated repairs and
        // imports cannot make the Company Owes card read a stale row.
        $allLedgerEntries = $employee->ledgerEntries;
        $ledgerBalance = round((float) $allLedgerEntries->sum(
            fn ($entry) => (float) $entry->debit - (float) $entry->credit
        ), 2);

        // Build every running balance before filtering/pagination. This keeps
        // Net Due historically correct even when the first visible row is on
        // page two or an earlier transaction has been filtered out.
        $orderedLedgerEntries = $this->ledgerEntriesInDateOrder($allLedgerEntries);
        $ledgerSearch = trim((string) $request->input('ledger_q', ''));
        $ledgerType = trim((string) $request->input('ledger_type', ''));
        $ledgerStatus = in_array($request->input('ledger_status', 'active'), ['active', 'all'], true)
            ? $request->input('ledger_status', 'active')
            : 'active';
        $isReversalEntry = static function ($entry): bool {
            if (str_ends_with((string) $entry->type, '_reversal')) {
                return true;
            }
            if ($entry->source instanceof EmployeePayment) {
                return $entry->source->status === 'reversed';
            }
            if ($entry->source instanceof AdvanceSalary) {
                return $entry->source->is_reversed;
            }

            return false;
        };
        $ledgerReversalHistory = $orderedLedgerEntries
            ->filter($isReversalEntry)
            ->groupBy(fn ($entry) => $entry->source_type && $entry->source_id
                ? $entry->source_type.':'.$entry->source_id
                : 'entry:'.$entry->id)
            ->map(function ($rows) {
                $rows = $rows->sortBy(fn ($entry) => sprintf('%s|%012d', $entry->entry_date->format('Y-m-d'), $entry->id))->values();
                $latest = $rows->last();
                $source = $latest->source;

                return [
                    'title' => data_get($source, 'reference') ?: $latest->reference ?: 'Ledger reversal #'.$latest->id,
                    'amount' => (float) $rows->max(fn ($entry) => max((float) $entry->debit, (float) $entry->credit)),
                    'date' => $latest->entry_date->format('d M Y'),
                    'rows' => $rows->map(fn ($entry) => [
                        'type' => Str::headline($entry->type),
                        'date' => $entry->entry_date->format('d M Y'),
                        'time' => $entry->display_time,
                        'debit' => (float) $entry->debit,
                        'credit' => (float) $entry->credit,
                        'reference' => $entry->reference,
                        'note' => $entry->note,
                    ])->all(),
                ];
            })
            ->sortByDesc('date')
            ->values();
        $ledgerReversalCount = $ledgerReversalHistory->count();
        $ledgerDate = static function ($value, bool $endOfDay = false) {
            if (! is_string($value) || ! Carbon::hasFormat($value, 'Y-m-d')) {
                return null;
            }

            $date = Carbon::createFromFormat('Y-m-d', $value);

            return $endOfDay ? $date->endOfDay() : $date->startOfDay();
        };
        $ledgerFrom = $ledgerDate($request->input('ledger_from'));
        $ledgerTo = $ledgerDate($request->input('ledger_to'), true);

        // Rebuild the running balance from the rows that belong to the chosen
        // status view. Active hides both a reversed source row and its
        // equal-and-opposite audit row, so carrying the balance calculated
        // from the unfiltered collection would make hidden payments appear as
        // an advance until their later reversal date.
        $statusLedgerEntries = $orderedLedgerEntries
            ->filter(function ($entry) use ($ledgerStatus, $isReversalEntry) {
                $reversalEntry = $isReversalEntry($entry);

                return ! ($ledgerStatus === 'active' && $reversalEntry);
            })
            ->values();
        $statusRunningBalance = 0.0;
        $statusLedgerEntries->each(function ($entry) use (&$statusRunningBalance) {
            $statusRunningBalance += (float) $entry->debit - (float) $entry->credit;
            $entry->running_balance = round($statusRunningBalance, 2);
        });

        $filteredLedgerEntries = $statusLedgerEntries->filter(function ($entry) use ($ledgerSearch, $ledgerType, $ledgerFrom, $ledgerTo) {
            if ($ledgerType !== '' && $entry->type !== $ledgerType) {
                return false;
            }

            $entryDate = $entry->entry_date ? Carbon::parse($entry->entry_date) : null;
            if ($ledgerFrom && (! $entryDate || $entryDate->lt($ledgerFrom))) {
                return false;
            }
            if ($ledgerTo && (! $entryDate || $entryDate->gt($ledgerTo))) {
                return false;
            }

            if ($ledgerSearch !== '') {
                $source = $entry->source;
                $haystack = Str::lower(implode(' ', array_filter([
                    $entry->type,
                    $entry->reference,
                    $entry->note,
                    $entry->debit,
                    $entry->credit,
                    data_get($source, 'reference'),
                    data_get($source, 'payment_method'),
                    data_get($source, 'recipient_bank'),
                    data_get($source, 'recipient_account'),
                ])));
                if (! Str::contains($haystack, Str::lower($ledgerSearch))) {
                    return false;
                }
            }

            return true;
        })->sortByDesc(fn ($entry) => sprintf(
            '%s|%012d',
            $entry->entry_date ? $entry->entry_date->format('Y-m-d') : '0000-00-00',
            $entry->id
        ))->values();

        $ledgerPerPage = in_array((int) $request->input('ledger_per_page', 15), [10, 15, 25, 50], true)
            ? (int) $request->input('ledger_per_page', 15)
            : 15;
        $ledgerPage = LengthAwarePaginator::resolveCurrentPage('ledger_page');
        $ledgerEntries = (new LengthAwarePaginator(
            $filteredLedgerEntries->forPage($ledgerPage, $ledgerPerPage)->values(),
            $filteredLedgerEntries->count(),
            $ledgerPerPage,
            $ledgerPage,
            ['path' => $request->url(), 'pageName' => 'ledger_page']
        ))->appends($request->except('ledger_page'))->fragment('employee-ledger-transactions');

        // ── Header KPI strip ────────────────────────────────────────────
        // "Salary Due" is the outstanding balance of only the most-recently
        // -generated payslip month (e.g. if June is the latest generated
        // salary but April/May are still unpaid, this shows June's due only
        // — older unpaid months are intentionally excluded here). "Company
        // Owes" above still reflects the true running total via the ledger.
        $payslips = $this->getPayslipsWithPaidDue($employee->id);
        $latestSalaryPayslip = $payslips->firstWhere('document_type', 'salary');
        $salaryDue = (float) ($latestSalaryPayslip->due_amount ?? 0);

        // "Paid" means the approved advance was handed to the employee; it
        // does not mean the employee has repaid it. Show the unrecovered
        // balance, which payroll will deduct from the matching month's salary.
        $advanceOut = (float) AdvanceSalary::with('recoveries')
            ->where('user_id', $employee->id)
            ->where('status', 'Approved')
            ->get()
            ->sum(fn (AdvanceSalary $advance) => $advance->outstanding);

        $pendingReconciliation = $this->payroll->projectPendingReconciliation($employee, $today);

        $banks = Bank::where('status', 1)->orderBy('name')->get();
        // An advance journal belongs to the employee's company, so its credit
        // side must come from that same company's cash/bank book. Keep the
        // broad list for Pay Due (its existing flow), but give the Advance
        // modal a company-scoped list so an invalid cross-company account is
        // never offered in the first place. Null-company rows are legacy/shared
        // accounts and remain eligible; the posting service performs the final
        // server-side ownership check.
        $advanceBanks = Bank::where('status', 1)
            ->whereNotNull('account_id')
            ->where(function ($query) use ($employee) {
                $query->where('company_id', $employee->company_id)
                    ->orWhereNull('company_id');
            })
            ->orderBy('name')
            ->get();
        $advancePosting = app(AdvancePostingService::class);
        $advanceAccounts = $advancePosting->contraAccounts();
        $advanceDefaultId = optional($advanceAccounts->firstWhere('code', AdvancePostingService::defaultAccountCode()))->id;

        // ── Payslips tab (reuses $payslips computed above for the KPI) ──

        // ── Attendance tab (month-scoped, independent of the Overview chart) ──
        $attMonthInput = $request->filled('att_month') ? $request->att_month : $today->format('Y-m');
        if (! preg_match('/^\d{4}-\d{2}$/', $attMonthInput)) {
            $attMonthInput = $today->format('Y-m');
        }
        [$attYear, $attMonthNum] = array_map('intval', explode('-', $attMonthInput));
        $attMonthStart = Carbon::createFromDate($attYear, $attMonthNum, 1)->startOfMonth();
        $attMonthEnd = $attMonthStart->copy()->endOfMonth();

        $attendanceMonthReport = app(AttendanceMonthService::class)->build($employee, $attYear, $attMonthNum);
        $attendanceHistory = $attendanceMonthReport['records'];
        $attendanceSummary = app(AttendanceMonthService::class)->summaryFromRows(
            $attendanceHistory,
            $attendanceMonthReport['summary']
        );
        $attendanceWorkedMinutes = (int) $attendanceHistory->sum(function ($record) {
            if (! $record->check_in || ! $record->check_out) {
                return 0;
            }
            $start = Carbon::parse($record->date.' '.$record->check_in);
            $end = Carbon::parse($record->date.' '.$record->check_out);
            if ($end->lt($start)) {
                $end->addDay();
            }

            return $start->diffInMinutes($end);
        });

        // ── All Details tab ─────────────────────────────────────────────
        $employeeDocument = EmployeeDocument::where('user_id', $employee->id)->first();

        return view('panel.users.summary', compact(
            'role',
            'employee',
            'taskTotal',
            'taskCompleted',
            'taskPending',
            'taskWeeklyLabels',
            'taskWeeklyCreatedData',
            'taskWeeklyCompletedData',
            'taskMonthlyLabels',
            'taskMonthlyCreatedData',
            'taskMonthlyCompletedData',
            'attendanceMonth',
            'attendanceMonthLabels',
            'attendanceMonthPresentData',
            'attendanceMonthLateData',
            'attendanceMonthAbsentData',
            'attendanceMonthLateMinutesData',
            'attendanceMonthWorkingHoursData',
            'attendanceMonthTotalLateMinutes',
            'attendanceMonthTotalWorkingHours',
            'leaveSummary',
            'leaveDaysUsed',
            'salaryStats',
            'latestSalary',
            'loanStats',
            'advanceSalaryStats',
            'currentYear',
            'currentMonth',
            'ledgerEntries',
            'ledgerBalance',
            'ledgerStatus',
            'ledgerReversalCount',
            'ledgerReversalHistory',
            'salaryDue',
            'advanceOut',
            'pendingReconciliation',
            'banks',
            'advanceBanks',
            'advanceAccounts',
            'advanceDefaultId',
            'payslips',
            'attendanceHistory',
            'attendanceSummary',
            'attendanceWorkedMinutes',
            'attMonthInput',
            'employeeDocument',
            'employeeOptions',
            'returnUrl'
        ));
    }

    /** Email or SMS the employee's complete Accounts ledger. */
    public function sendEmployeeLedger($role, $user, Request $request)
    {
        $employee = User::with(['company', 'ledgerEntries.source'])->findOrFail($user);
        $this->authorizeEmployeeAccess($employee);
        abort_unless($employee->hasRole('employee'), 404);

        $validated = $request->validate(['channel' => 'required|in:email,sms']);
        $entries = $this->ledgerEntriesInDateOrder($employee->ledgerEntries);
        $totalDebit = (float) $entries->sum('debit');
        $totalCredit = (float) $entries->sum('credit');
        $closingBalance = round($totalDebit - $totalCredit, 2);
        $company = $employee->company ?? Company::first();

        if ($validated['channel'] === 'email') {
            abort_if(! filter_var($employee->email, FILTER_VALIDATE_EMAIL), 422, 'This employee has no valid email address.');
            $pdf = Pdf::loadView('panel.users.print-salary-transaction-report', [
                'employee' => $employee, 'ledgerEntries' => $entries,
                'totalDebit' => $totalDebit, 'totalCredit' => $totalCredit,
                'closingBalance' => $closingBalance, 'company' => $company,
            ])->output();
            $response = sendBrevoMail(
                $employee->email,
                $employee->name,
                'Employee account statement - '.now()->format('d M Y'),
                view('emails.employee-ledger-statement', compact('employee', 'totalDebit', 'totalCredit', 'closingBalance', 'company'))->render(),
                [['name' => 'Employee-Statement-'.($employee->employee_id_no ?: $employee->id).'.pdf', 'content' => $pdf]],
            );
            if (is_object($response) && method_exists($response, 'successful') && ! $response->successful()) {
                return response()->json(['success' => false, 'message' => 'Email gateway rejected the statement.'], 502);
            }

            return response()->json(['success' => true, 'message' => 'Statement emailed to '.$employee->email.'.']);
        }

        abort_if(! trim((string) $employee->phone), 422, 'This employee has no phone number.');
        $balanceText = $closingBalance > 0
            ? 'Company owes Tk '.number_format($closingBalance, 2)
            : ($closingBalance < 0 ? 'Advance/overpaid Tk '.number_format(abs($closingBalance), 2) : 'Balance settled');
        $sent = sendSms($employee->phone, "Dear {$employee->name}, employee account statement: {$balanceText}. Total earned Tk ".number_format($totalDebit, 2).', total paid/recovered Tk '.number_format($totalCredit, 2).'.');

        return response()->json($sent === false
            ? ['success' => false, 'message' => app()->bound('sms.last_error') ? (string) app('sms.last_error') : 'SMS gateway failed.']
            : ['success' => true, 'message' => 'Statement SMS sent to '.$employee->phone.'.'], $sent === false ? 502 : 200);
    }

    /**
     * Standalone print layout for an employee's full ledger transaction
     * history — same EmployeeLedger data as the profile Accounts tab,
     * styled like party-statement's print-statement (letterhead, summary
     * strip, ledger table, signature block, auto window.print() on load).
     */
    /**
     * The ledger in the order a reader expects it — oldest entry first —
     * with the running balance rebuilt to match that order.
     *
     * ledgerEntries() deliberately returns id (insertion) order, because
     * that is the order each row's stored `balance` was calculated in. The
     * two orders diverge as soon as salary months are generated out of
     * sequence: generating July, then June, then May files the rows in that
     * order, so the screen listed July at the top and the stored balances
     * climbed backwards through time.
     *
     * Re-sorting on its own would be worse than leaving it alone — the Net
     * Due column would count *down* the page. So the running balance is
     * recomputed here in the order actually displayed. The closing figure
     * is identical either way: a running balance is the cumulative sum of
     * debit - credit, and a sum does not depend on the order it is taken
     * in. The last row therefore still agrees with $ledgerBalance,
     * $closingBalance and every stored total elsewhere.
     *
     * Display only — the recomputed figure is written to a `running_balance`
     * attribute and never to `balance`, so nothing here can be mistaken for
     * a correction to what is stored. entry_date is free-typed and can be
     * backdated, so id breaks ties: rows filed under the same date keep the
     * sequence they were really calculated in.
     *
     * @param  Collection  $entries  id-ordered ledger rows
     * @return Collection date-ordered copy
     */
    private function ledgerEntriesInDateOrder($entries)
    {
        $running = 0.0;

        return $entries
            ->sortBy(fn ($entry) => sprintf(
                '%s|%012d',
                $entry->entry_date ? $entry->entry_date->format('Y-m-d') : '0000-00-00',
                $entry->id
            ))
            ->values()
            ->each(function ($entry) use (&$running) {
                $running += (float) $entry->debit - (float) $entry->credit;
                $entry->running_balance = round($running, 2);
            });
    }

    public function salaryTransactionReportPrint($role, $user, Request $request)
    {
        $employee = User::with(['company', 'profile.department', 'profile.designation', 'ledgerEntries.source'])
            ->findOrFail($user);

        $this->authorizeEmployeeAccess($employee);

        $allEntries = $this->ledgerEntriesInDateOrder($employee->ledgerEntries);
        $scope = in_array($request->print_scope, ['today', 'yesterday', 'day', 'range', 'current'], true)
            ? $request->print_scope
            : 'current';
        $from = $to = null;
        $search = '';
        $reportPeriod = 'All Time';

        if ($scope === 'today') {
            $from = $to = today();
            $reportPeriod = 'Today · '.today()->format('d M Y');
        } elseif ($scope === 'yesterday') {
            $from = $to = today()->subDay();
            $reportPeriod = 'Yesterday · '.$from->format('d M Y');
        } elseif ($scope === 'day' && Carbon::hasFormat((string) $request->print_day, 'Y-m-d')) {
            $from = $to = Carbon::createFromFormat('Y-m-d', $request->print_day);
            $reportPeriod = $from->format('d M Y');
        } elseif ($scope === 'range') {
            $from = Carbon::hasFormat((string) $request->print_from, 'Y-m-d') ? Carbon::createFromFormat('Y-m-d', $request->print_from) : null;
            $to = Carbon::hasFormat((string) $request->print_to, 'Y-m-d') ? Carbon::createFromFormat('Y-m-d', $request->print_to) : null;
            $reportPeriod = ($from?->format('d M Y') ?? 'Beginning').' – '.($to?->format('d M Y') ?? 'Today');
        } elseif ($scope === 'current') {
            $from = Carbon::hasFormat((string) $request->current_from, 'Y-m-d') ? Carbon::createFromFormat('Y-m-d', $request->current_from) : null;
            $to = Carbon::hasFormat((string) $request->current_to, 'Y-m-d') ? Carbon::createFromFormat('Y-m-d', $request->current_to) : null;
            $search = trim((string) $request->current_q);
            $reportPeriod = $from || $to ? (($from?->format('d M Y') ?? 'Beginning').' – '.($to?->format('d M Y') ?? 'Today')) : 'Current table · All Time';
        }

        $ledgerEntries = $allEntries->filter(function ($entry) use ($from, $to, $search) {
            $date = $entry->entry_date ? Carbon::parse($entry->entry_date) : null;
            if ($from && (! $date || $date->lt($from->copy()->startOfDay()))) {
                return false;
            }
            if ($to && (! $date || $date->gt($to->copy()->endOfDay()))) {
                return false;
            }
            if ($search !== '') {
                $source = $entry->source;
                $haystack = Str::lower(implode(' ', array_filter([$entry->type, $entry->reference, $entry->note, $entry->debit, $entry->credit, data_get($source, 'reference'), data_get($source, 'payment_method')])));
                if (! Str::contains($haystack, Str::lower($search))) {
                    return false;
                }
            }

            return true;
        })->values();

        $totalDebit = (float) $ledgerEntries->sum('debit');
        $totalCredit = (float) $ledgerEntries->sum('credit');
        $closingBalance = round((float) ($ledgerEntries->last()->running_balance ?? 0), 2);

        $company = $employee->company ?? Company::first();

        return view('panel.users.print-salary-transaction-report', compact(
            'employee', 'ledgerEntries', 'totalDebit', 'totalCredit', 'closingBalance', 'company', 'reportPeriod'
        ));
    }

    public function leaveEncashmentReportPrint($role, $user)
    {
        $employee = User::with(['company', 'profile.department', 'profile.designation'])->findOrFail($user);
        $this->authorizeEmployeeAccess($employee);

        $projection = $this->payroll->projectPendingReconciliation($employee, Carbon::today());
        abort_unless($projection, 404, 'No leave encashment accrual is available for this employee.');

        $company = $employee->company ?? Company::first();

        return view('panel.users.print-leave-encashment-report', compact('employee', 'projection', 'company'));
    }

    public function attendanceReportPrint($role, $user, Request $request)
    {
        $employee = User::with(['company', 'shift'])->findOrFail($user);
        $this->authorizeEmployeeAccess($employee);

        $period = preg_match('/^\d{4}-\d{2}$/', (string) $request->month)
            ? (string) $request->month
            : now()->format('Y-m');
        [$year, $month] = array_map('intval', explode('-', $period));
        $report = app(AttendanceMonthService::class)->build($employee, $year, $month);

        return view('report.monthly_attendances_pdf', [
            'attendances' => $report['records'],
            'user' => $employee,
            'summary' => (object) app(AttendanceMonthService::class)->summaryFromRows($report['records'], $report['summary']),
            'month' => $report['start']->format('F'),
            'year' => $year,
            'autoPrint' => true,
        ]);
    }

    /**
     * Every EmployeeSalary row for an employee, with paid_amount/due_amount/
     * display_status computed via PaymentSchedule installments — shared by
     * the profile Payslips tab and its print report so the two can never
     * disagree (see the "Salary Due" KPI fix this logic was lifted from).
     */
    private function getPayslipsWithPaidDue(int $employeeId)
    {
        $salaries = EmployeeSalary::where('user_id', $employeeId)
            ->with('leaveEncashments.schedules')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        $paidPerPayslip = EmployeePaymentAllocation::query()
            ->whereIn('employee_salary_id', $salaries->pluck('id'))
            ->whereHas('payment', fn ($q) => $q->where('status', 'posted'))
            ->selectRaw('employee_salary_id, SUM(amount) as paid_total')
            ->groupBy('employee_salary_id')->pluck('paid_total', 'employee_salary_id');

        $salaries->each(function ($slip) use ($paidPerPayslip) {
            $salaryPaid = min((float) $slip->net_salary, (float) ($paidPerPayslip[$slip->id] ?? 0));
            $encashmentAmount = (float) $slip->leaveEncashments->sum('total_amount');
            $encashmentPaid = (float) $slip->leaveEncashments->sum(
                fn ($encashment) => min((float) $encashment->total_amount, PaymentSchedule::paidTotal($encashment->schedules))
            );
            $netPay = (float) $slip->net_salary + $encashmentAmount;
            $paid = $salaryPaid + $encashmentPaid;
            $isFullyPaid = $paid >= $netPay - .01;

            $slip->document_type = 'salary';
            $slip->document_label = 'Monthly Salary';
            $slip->period_label = Carbon::createFromDate((int) $slip->year, (int) $slip->month, 1)->format('F Y');
            $slip->sort_date = sprintf('%04d-%02d-01', (int) $slip->year, (int) $slip->month);
            $slip->encashment_amount = $encashmentAmount;
            $slip->encashment_earned_days = (float) $slip->leaveEncashments->sum('earned_days');
            $slip->encashment_days = (float) $slip->leaveEncashments->sum('encashable_days');
            $slip->earnings_amount = (float) $slip->net_salary + (float) $slip->total_deductions + $encashmentAmount;
            $slip->net_pay_amount = $netPay;
            $slip->paid_amount = $paid;
            $slip->due_amount = max(0, $netPay - $paid);
            $slip->display_status = $isFullyPaid ? 'Paid' : ($paid > 0 ? 'Partial' : 'Pending');
        });

        return $salaries;
    }

    /**
     * Standalone print layout for an employee's payslip list — same
     * gross/deductions/net/paid/due/status breakdown as the profile
     * Payslips tab, styled like the salary transaction report print view.
     */
    public function payslipsReportPrint($role, $user)
    {
        $employee = User::with(['company', 'profile.department', 'profile.designation'])
            ->findOrFail($user);

        $this->authorizeEmployeeAccess($employee);

        $payslips = $this->getPayslipsWithPaidDue($employee->id);

        $totalGross = (float) $payslips->sum('earnings_amount');
        $totalDeductions = (float) $payslips->sum('total_deductions');
        $totalNet = (float) $payslips->sum('net_pay_amount');
        $totalPaid = (float) $payslips->sum('paid_amount');
        $totalDue = (float) $payslips->sum('due_amount');

        $company = $employee->company ?? Company::first();

        return view('panel.users.print-payslips-report', compact(
            'employee', 'payslips', 'totalGross', 'totalDeductions', 'totalNet', 'totalPaid', 'totalDue', 'company'
        ));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        // Logic to show user creation form
        $salaryTemplates = SalaryTemplate::where('status', 1)->get();

        return view('panel.users.create', compact('salaryTemplates'));
    }

    public function documents($role, Request $request)
    {
        $query = User::with(['profile.department', 'profile.designation', 'company', 'employeeDocument'])
            ->role('employee')
            ->orderBy('id', 'desc');

        // Same list-level rule as index() — this page shows NID, passport and
        // appointment letters, so it must never reach past the caller's tier.
        $this->applyUserScope($query);

        if ($request->filled('name')) {
            $query->where('name', 'like', '%'.$request->name.'%');
        }

        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%'.$request->phone.'%');
        }

        if ($request->filled('company')) {
            $query->where('company_id', $request->company);
        }

        if ($request->filled('department')) {
            $query->whereHas('profile', function ($q) use ($request) {
                $q->where('department_id', $request->department);
            });
        }

        $users = $query->paginate(15);
        $departments = Department::orderBy('name')->get();
        $companies = Company::orderBy('name')->get();

        return view('panel.users.documents', compact('users', 'departments', 'companies', 'role'));
    }

    public function updateDocuments(Request $request, $role, User $user)
    {
        $this->authorizeEmployeeAccess($user);

        $validator = Validator::make($request->all(), [
            'passport_size_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'nid' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'appointment_letter' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        if (! $request->hasFile('passport_size_image') && ! $request->hasFile('nid') && ! $request->hasFile('appointment_letter')) {
            return redirect()->back()->with('error', 'Please upload at least one document.');
        }

        $employeeDocument = EmployeeDocument::firstOrCreate(['user_id' => $user->id]);

        $passportSizeImage = $request->file('passport_size_image');
        $nid = $request->file('nid');
        $appointmentLetter = $request->file('appointment_letter');

        if ($passportSizeImage instanceof UploadedFile) {
            $passportImagePath = $this->storePublicFile(
                $passportSizeImage,
                'uploads/employee-documents/'.$user->id,
                $employeeDocument->passport_size_image
            );
            $employeeDocument->passport_size_image = $passportImagePath;
        }

        if ($nid instanceof UploadedFile) {
            $nidPath = $this->storePublicFile(
                $nid,
                'uploads/employee-documents/'.$user->id,
                $employeeDocument->nid
            );
            $employeeDocument->nid = $nidPath;
        }

        if ($appointmentLetter instanceof UploadedFile) {
            $appointmentLetterPath = $this->storePublicFile(
                $appointmentLetter,
                'uploads/employee-documents/'.$user->id,
                $employeeDocument->appointment_letter
            );
            $employeeDocument->appointment_letter = $appointmentLetterPath;
        }

        $employeeDocument->save();

        return redirect()->back()->with('success', 'Employee documents updated successfully.');
    }

    /**
     * Map the "User Status" dropdown onto the users.status column.
     *
     * The dropdown posts 1 / 0 / "resigned". Anything that is not the literal
     * string "resigned" keeps the original truthy check, so every existing
     * caller (the API sending true/false or 1/0) behaves exactly as before.
     */
    private function resolveUserStatus($isActive): string
    {
        if (is_string($isActive) && strtolower(trim($isActive)) === 'resigned') {
            return 'resigned';
        }

        return $isActive ? 'active' : 'inactive';
    }

    /**
     * `users.device_id` is meant to hold a JSON array of device ids, but some
     * legacy rows hold a bare scalar ("1"). json_decode() turns that into an
     * int, which array_map() then rejects — a 500 on the edit page for every
     * such user. Normalise whatever is stored into a list of ints.
     */
    private function normalizeDeviceIds($deviceId): array
    {
        if ($deviceId === null || $deviceId === '') {
            return [];
        }

        $decoded = json_decode((string) $deviceId, true);

        if (! is_array($decoded)) {
            // A bare scalar, or not JSON at all — treat it as a single id
            $decoded = [$decoded ?? $deviceId];
        }

        $numeric = array_filter($decoded, fn ($id) => is_numeric($id));

        return array_values(array_map('intval', $numeric));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // User Table Validations
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone' => 'required|string|max:15|unique:users,phone',
            // 'device_user_id' => [
            //     'required',
            //     'string',
            //     'max:255',
            //     Rule::unique('users', 'device_user_id')
            //         ->where(fn($q) => $q->where('company_id', request('company_id'))),
            // ],
            'password' => 'required|string|min:8|confirmed',
            'company_id' => 'required|exists:companies,id',
            'salary_template_id' => 'required|exists:salary_templates,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'address' => 'nullable|string|max:500',
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,name',
            'image' => 'nullable|max:2048',

            // EmployeeProfile Table Validations
            'department_id' => 'required|exists:departments,id',
            'designation_id' => 'required|exists:designations,id',
            'joining_date' => 'nullable|date',
            'salary' => 'nullable|numeric|min:0',
            'employment_type' => 'required|in:full_time,part_time,contractual',
        ], [
            'device_user_id.required' => 'Device User ID is required.',
            // 'device_user_id.unique'   => 'This Device User ID is already assigned to another employee in this company.',
            'salary_template_id.required' => 'Salary Template is required.',
            'salary_template_id.exists' => 'Selected Salary Template does not exist.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            // 1. Create the User (Auth Table)
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                // 'device_user_id'  => $request->device_user_id,
                'company_id' => $request->company_id,
                'salary_template_id' => $request->salary_template_id,
                'shift_id' => $request->shift_id,
                'address' => $request->address,
                'password' => bcrypt($request->password),
                'status' => $this->resolveUserStatus($request->is_active),
            ]);

            // 2. Handle Image Upload (Optional: belongs to User or Profile? usually User)
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $image_name = uniqid().'.'.strtolower($image->getClientOriginalExtension());
                $upload_path = 'image/user/';
                if (! file_exists(public_path($upload_path))) {
                    mkdir(public_path($upload_path), 0777, true);
                }
                $success = $image->move(public_path($upload_path), $image_name);
                if ($success) {
                    if (! empty($data->image) && file_exists(public_path($data->image))) {
                        unlink(public_path($data->image));
                    }
                    $user->image = $upload_path.$image_name;
                    $user->save();
                }
            }

            // 3. Create the Employee Profile (Details Table)
            if ($request->designation_id) {
                EmployeeProfile::create([
                    'user_id' => $user->id,
                    'department_id' => $request->department_id,
                    'designation_id' => $request->designation_id,
                    'joining_date' => $request->joining_date,
                    'salary' => $request->salary,
                    'employment_type' => $request->employment_type,
                ]);
            }

            // 4. Assign Roles (multiple roles supported)
            $roles = $request->roles ?? ['employee'];
            $user->syncRoles($roles);

            DB::commit();

            $primaryRole = Str::slug($roles[0]);

            return redirect()->route('role.user.index', ['role' => $primaryRole])->with('success', 'User and Profile created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Something went wrong: '.$e->getMessage())->withInput();
        }
    }

    // Other methods like show, edit, update, destroy can be added similarly
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        // Logic to show a specific user
        return view('panel.users.show', compact('id'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Request $request, $role, $id)
    {
        // Logic to show user edit form
        $data = User::findOrFail($id);
        $data->device_ids = $this->normalizeDeviceIds($data->device_id);
        $devices = DeviceSetting::get();
        $device_users = DeviceUser::where('user_id', $data->id)->get();
        $salaryTemplates = SalaryTemplate::where('status', 1)->get();
        $manualAttendanceRecipients = User::query()
            ->where('id', '!=', $data->id)
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('name', 'employee'))
            ->withCount('deviceTokens')
            ->orderBy('name')
            ->get(['id', 'name']);
        $manualAttendanceRecipientIds = Schema::hasTable('manual_attendance_notification_recipients')
            ? $data->manualAttendanceNotificationRecipients()->pluck('users.id')->all()
            : [];
        $returnUrl = $this->safeUserListReturnUrl(
            $request->query('return_url', url()->previous()),
            $role
        );

        return view('panel.users.edit', compact(
            'data',
            'devices',
            'device_users',
            'salaryTemplates',
            'manualAttendanceRecipients',
            'manualAttendanceRecipientIds',
            'returnUrl'
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $role, $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
                'phone' => 'required|string|max:15|unique:users,phone,'.$user->id,
                // 'device_user_id' => [
                //     'required',
                //     'string',
                //     'max:255',
                //     Rule::unique('users', 'device_user_id')
                //         ->where(fn($q) => $q->where('company_id', request('company_id')))
                //         ->ignore($user->id),
                // ],
                'company_id' => 'required|exists:companies,id',
                'salary_template_id' => 'required|exists:salary_templates,id',
                'device_id.*' => 'exists:device_settings,id',
                // 'device_user_id' => 'required|string|regex:/^[0-9, ]+$/',
                'shift_id' => 'nullable|exists:shifts,id',
                'roles' => 'required|array|min:1',
                'roles.*' => 'exists:roles,name',
                'address' => 'nullable|string|max:500',
                'image' => 'nullable|max:2048',
                'password' => 'nullable|string|min:8|confirmed',
                'allow_manual_attendance' => 'nullable|boolean',
                'manual_attendance_recipient_ids' => 'nullable|array',
                'manual_attendance_recipient_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('status', 'active')],
                'overtime_eligible' => 'nullable|boolean',
                'auto_payslip_enabled' => 'nullable|boolean',
                'fixed_salary' => 'nullable|boolean',

                // Profile Validations
                'department_id' => 'required|exists:departments,id',
                'designation_id' => 'required|exists:designations,id',
                'joining_date' => 'nullable|date',
                'salary' => 'nullable|numeric|min:0',
                'employment_type' => 'required|in:full_time,part_time,contractual',
            ],
        );

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            // 1. Update User Basic Info
            $userData = [
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'device_id' => json_encode($request->device_id),
                'employee_id_no' => $request->employee_id_no,
                'company_id' => $request->company_id,
                'salary_template_id' => $request->salary_template_id,
                'shift_id' => $request->shift_id,
                'address' => $request->address,
                'status' => $this->resolveUserStatus($request->is_active),
                'allow_manual_attendance' => $request->has('allow_manual_attendance') ? (bool) $request->allow_manual_attendance : false,
                'overtime_eligible' => $request->has('overtime_eligible') ? (bool) $request->overtime_eligible : false,
                'auto_payslip_enabled' => $request->has('auto_payslip_enabled') ? (bool) $request->auto_payslip_enabled : false,
            ];

            // Written only once the column exists. `users` is the one table
            // every company's login, profile and payroll screen touches, so a
            // server whose migrations have not been run yet must still be able
            // to save a user rather than dying on an unknown column — the
            // toggle simply has no effect until `php artisan migrate` has run.
            if (Schema::hasColumn('users', 'fixed_salary')) {
                $userData['fixed_salary'] = $request->has('fixed_salary')
                    ? (bool) $request->fixed_salary
                    : false;
            }

            // Update password only if provided
            if ($request->filled('password')) {
                $userData['password'] = bcrypt($request->password);
            }

            $user->update($userData);

            if (Schema::hasTable('manual_attendance_notification_recipients')) {
                $recipientIds = $request->has('allow_manual_attendance')
                    ? collect($request->input('manual_attendance_recipient_ids', []))
                        ->map(fn ($recipientId) => (int) $recipientId)
                        ->reject(fn ($recipientId) => $recipientId === (int) $user->id)
                        ->unique()
                        ->values()
                        ->all()
                    : [];
                $user->manualAttendanceNotificationRecipients()->sync($recipientIds);
            }

            // 2. Handle Image Update
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $image_name = uniqid().'.'.strtolower($image->getClientOriginalExtension());
                $upload_path = 'image/user/';

                if (! file_exists(public_path($upload_path))) {
                    mkdir(public_path($upload_path), 0777, true);
                }

                // Remove old image if it exists
                if (! empty($user->image) && file_exists(public_path($user->image))) {
                    unlink(public_path($user->image));
                }

                $image->move(public_path($upload_path), $image_name);
                $user->image = $upload_path.$image_name;
                $user->save();
            }

            // 3. Update or Create Employee Profile
            // using updateOrCreate in case a user exists but somehow lacks a profile record
            EmployeeProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'department_id' => $request->department_id,
                    'designation_id' => $request->designation_id,
                    'joining_date' => $request->joining_date,
                    'salary' => $request->salary,
                    'employment_type' => $request->employment_type,
                ]
            );

            $deviceIds = $request->device_id; // [1,2,3]
            if (! empty($deviceIds)) {
                $deviceUserIds = $request->device_user_id;
                // [ 1 => 'ABC123', 2 => 'XYZ456' ]
                DeviceUser::where('user_id', $user->id)->delete();
                foreach ($deviceIds as $deviceId) {
                    $userId = $deviceUserIds[$deviceId] ?? null;

                    if ($userId) {
                        $deviceUser = new DeviceUser;
                        $deviceUser->user_id = $user->id;
                        $deviceUser->device_id = $deviceId;
                        $deviceUser->device_user_id = $userId;
                        $deviceUser->save();
                    }
                }
            }

            // 4. Sync Roles (supports multiple roles)
            $roles = $request->roles ?? ['employee'];
            $user->syncRoles($roles);

            DB::commit();

            return redirect()->back()->with('success', 'User and Profile updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Update failed: '.$e->getMessage())->withInput();
        }
    }

    private function safeUserListReturnUrl(?string $returnUrl, string $role): string
    {
        $fallback = route('role.user.index', ['role' => $role]);

        if (! $returnUrl) {
            return $fallback;
        }

        $target = parse_url($returnUrl);
        // Compare against the current request host instead of APP_URL. Local
        // installations commonly use a Laragon *.test domain while APP_URL
        // is still localhost.
        $application = parse_url(request()->getSchemeAndHttpHost());

        if (($target['scheme'] ?? null) !== ($application['scheme'] ?? null)
            || ($target['host'] ?? null) !== ($application['host'] ?? null)
            || ($target['port'] ?? null) !== ($application['port'] ?? null)) {
            return $fallback;
        }

        return $returnUrl;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        // Logic to delete a user
        // Delete user data here
        return redirect()->route('role.user.index')->with('success', 'User deleted successfully.');
    }

    private function storePublicFile(UploadedFile $file, string $directory, ?string $oldPath = null): string
    {
        $fileName = uniqid().'_'.time().'.'.strtolower($file->getClientOriginalExtension());

        if (! file_exists(public_path($directory))) {
            mkdir(public_path($directory), 0777, true);
        }

        if (! empty($oldPath) && file_exists(public_path($oldPath))) {
            unlink(public_path($oldPath));
        }

        $file->move(public_path($directory), $fileName);

        return trim($directory, '/').'/'.$fileName;
    }
}
