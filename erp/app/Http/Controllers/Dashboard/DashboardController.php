<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Attendance;
use App\Models\Bank;
use App\Models\Department;
use App\Models\Chat;
use App\Models\Company;
use App\Models\Holiday;
use App\Models\Lead;
use App\Models\Leave;
use App\Models\Notice;
use App\Models\PaymentSchedule;
use App\Models\OfficeTodo;
use App\Models\OfficeTodoAssignee;
use App\Models\Project;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\TaskActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Portal;
use App\Services\DmApiService;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:view dashboard')->only(['index', 'todoList']);
    }

    /**
     * Display the Super Admin dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // ── Global Date Filter (Month-Year) ───────────────────────────────────
        $period = $request->input('period', '');
        // Guard: if empty, invalid, or missing hyphen — fall back to current month
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = now()->format('Y-m');
        }
        [$selectedYear, $selectedMonth] = array_map('intval', explode('-', $period));
        // Clamp to valid ranges
        $selectedYear  = max(2000, min((int) now()->year + 1, $selectedYear));
        $selectedMonth = max(1, min(12, $selectedMonth));
        $period        = sprintf('%04d-%02d', $selectedYear, $selectedMonth);

        $startDate = Carbon::createFromDate($selectedYear, $selectedMonth, 1)->startOfMonth();
        $endDate   = $startDate->copy()->endOfMonth();
        // For "today-sensitive" logic use today if we are in the current month, else use end of period
        $date      = ($startDate->isSameMonth(Carbon::today())) ? Carbon::today() : $endDate;

        $companyId = Auth::user()->company_id;

        $totalEmployees = User::where('is_super_admin', 0)
            ->where('status', 'active')
            ->whereHas('roles', function ($q) {
                $q->where('name', 'employee');
            })
            ->count();

        $todayStr = Carbon::today()->toDateString();
        $monthStartStr = $startDate->toDateString();
        $monthEndStr = $endDate->toDateString();

        $todayPresentCount = DB::table('attendances')
            ->whereDate('date', $todayStr)
            ->whereIn('status', ['present', 'late', 'Present', 'Late'])
            ->distinct('user_id')
            ->count('user_id');

        $todayAbsentCount = DB::table('attendances')
            ->whereDate('date', $todayStr)
            ->where('status', 'absent')
            ->distinct('user_id')
            ->count('user_id');

        $todayLeaveCount = DB::table('leaves')
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $todayStr)
            ->whereDate('end_date', '>=', $todayStr)
            ->count();

        $monthPresentCount = DB::table('attendances')
            ->whereBetween('date', [$monthStartStr, $monthEndStr])
            ->whereIn('status', ['present', 'late', 'Present', 'Late'])
            ->distinct('user_id')
            ->count('user_id');

        $monthAttendanceRate = $totalEmployees > 0
            ? round(($monthPresentCount / $totalEmployees) * 100, 1)
            : 0;

        $monthAbsentCount = DB::table('attendances')
            ->whereBetween('date', [$monthStartStr, $monthEndStr])
            ->where('status', 'absent')
            ->count();

        $monthLeaveCount = DB::table('leaves')
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $monthEndStr)
            ->whereDate('end_date', '>=', $monthStartStr)
            ->count();

        // $total_employees = $totalEmployees;
        // $total_present   = $todayPresentCount;
        // $total_leave     = $todayLeaveCount;

        // ── Financial summary ─────────────────────────────────────────────────
        // Cumulative balances (liability/payable/receivable) as of end of selected month
        $asOf     = $endDate->toDateString();
        $accounts = Account::with(['items' => function ($query) use ($asOf) {
            $query->whereHas('journalEntry', function ($q) use ($asOf) {
                $q->whereDate('date', '<=', $asOf);
            });
        }])->get();

        $accountBalances = $accounts->map(function ($account) {
            $debits  = $account->items->sum('debit');
            $credits = $account->items->sum('credit');

            if (in_array($account->type, ['asset', 'expense'])) {
                $balance = $account->opening_balance + ($debits - $credits);
            } else {
                $balance = $account->opening_balance + ($credits - $debits);
            }

            return (object) [
                'code'    => $account->code,
                'type'    => $account->type,
                'balance' => $balance,
            ];
        });

        $total_liability  = $accountBalances->where('type', 'liability')->sum('balance');
        $total_payable    = optional($accountBalances->firstWhere('code', config('accounts.accounts_payable')))->balance ?? 0;
        $total_receivable = optional($accountBalances->firstWhere('code', config('accounts.accounts_receivable')))->balance ?? 0;
        $bankAccountIds = Bank::where('status', 1)->whereNotNull('account_id')->pluck('account_id');
        $total_bank_balance = Account::whereIn('id', $bankAccountIds)
            ->with(['items' => function ($query) use ($asOf) {
                $query->whereHas('journalEntry', function ($q) use ($asOf) {
                    $q->whereDate('date', '<=', $asOf)->whereNull('deleted_at');
                })->whereNull('journal_items.deleted_at');
            }])
            ->get()
            ->sum(function ($account) {
                $debits  = $account->items->sum('debit');
                $credits = $account->items->sum('credit');
                return $account->opening_balance + ($debits - $credits);
            });

        // Office Cash — same cumulative as-of-period-end formula as Bank
        // Balance above, but scoped to the dedicated Office Cash ledger
        // account directly (code 1113) since it isn't backed by a Bank record.
        $officeCashAccount = Account::where('code', config('accounts.office_cash'))->first();
        $total_office_cash = 0;
        if ($officeCashAccount) {
            $officeCashItems = DB::table('journal_items')
                ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_items.account_id', $officeCashAccount->id)
                ->whereNull('journal_items.deleted_at')
                ->whereNull('journal_entries.deleted_at')
                ->whereDate('journal_entries.date', '<=', $asOf)
                ->selectRaw('COALESCE(SUM(journal_items.debit), 0) as debits, COALESCE(SUM(journal_items.credit), 0) as credits')
                ->first();
            $total_office_cash = (float) $officeCashAccount->opening_balance
                + (float) $officeCashItems->debits
                - (float) $officeCashItems->credits;
        }

        // Income/Expense/Profit — filtered to the selected month only
        $total_income = (float) DB::table('journal_items')
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->whereNull('journal_items.deleted_at')
            ->whereNull('journal_entries.deleted_at')
            ->where('accounts.type', 'income')
            ->whereBetween('journal_entries.date', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum(DB::raw('journal_items.credit - journal_items.debit'));

        $total_expense = (float) DB::table('journal_items')
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->whereNull('journal_items.deleted_at')
            ->whereNull('journal_entries.deleted_at')
            ->where('accounts.type', 'expense')
            ->whereBetween('journal_entries.date', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum(DB::raw('journal_items.debit - journal_items.credit'));

        $total_profit = $total_income - $total_expense;

        // ── Task statistics — filtered to the selected month ──────────────────
        $completedColumnIds = DB::table('columns')
            ->whereRaw("LOWER(name) REGEXP 'done|completed|complete|closed|finish|finished'")
            ->pluck('id');

        $task_total = DB::table('tasks')
            ->whereNull('deleted_at')
            ->whereBetween(DB::raw('DATE(created_at)'), [$startDate->toDateString(), $endDate->toDateString()])
            ->count();

        $task_completed = DB::table('tasks')
            ->whereNull('deleted_at')
            ->whereBetween(DB::raw('DATE(created_at)'), [$startDate->toDateString(), $endDate->toDateString()])
            ->when($completedColumnIds->isNotEmpty(), function ($query) use ($completedColumnIds) {
                $query->whereIn('column_id', $completedColumnIds);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->count();

        $task_due_today = DB::table('tasks')
            ->whereNull('deleted_at')
            ->whereBetween(DB::raw('DATE(due_date)'), [$startDate->toDateString(), $endDate->toDateString()])
            ->count();

        $task_overdue = DB::table('tasks')
            ->whereNull('deleted_at')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $date)
            ->when($completedColumnIds->isNotEmpty(), function ($query) use ($completedColumnIds) {
                $query->whereNotIn('column_id', $completedColumnIds);
            })
            ->count();

        // ── Task chart data ───────────────────────────────────────────────────
        // Weekly: show the week that contains the last day of the selected month
        $weekRefDay = $endDate->isFuture() ? Carbon::today() : $endDate;
        $weekStart  = $weekRefDay->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd    = $weekRefDay->copy()->endOfWeek(Carbon::SUNDAY);

        $weeklyCreatedRaw = DB::table('tasks')
            ->whereNull('deleted_at')
            ->whereBetween(DB::raw('DATE(created_at)'), [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->selectRaw('DATE(created_at) as day_date, COUNT(*) as total')
            ->groupBy('day_date')
            ->pluck('total', 'day_date');

        $weeklyCompletedRaw = DB::table('tasks')
            ->whereNull('deleted_at')
            ->whereNotNull('completed_at')
            ->whereBetween(DB::raw('DATE(completed_at)'), [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->selectRaw('DATE(completed_at) as day_date, COUNT(*) as total')
            ->groupBy('day_date')
            ->pluck('total', 'day_date');

        $taskWeeklyLabels      = [];
        $taskWeeklyCreatedData = [];
        $taskWeeklyCompletedData = [];

        for ($i = 0; $i < 7; $i++) {
            $day    = $weekStart->copy()->addDays($i);
            $dayKey = $day->toDateString();

            $taskWeeklyLabels[]        = $day->format('D');
            $taskWeeklyCreatedData[]   = (int) ($weeklyCreatedRaw[$dayKey] ?? 0);
            $taskWeeklyCompletedData[] = (int) ($weeklyCompletedRaw[$dayKey] ?? 0);
        }

        // Monthly charts: show the full selected year
        $monthlyCreatedRaw = DB::table('tasks')
            ->whereNull('deleted_at')
            ->whereYear('created_at', $selectedYear)
            ->selectRaw('MONTH(created_at) as month_no, COUNT(*) as total')
            ->groupBy('month_no')
            ->pluck('total', 'month_no');

        $monthlyCompletedRaw = DB::table('tasks')
            ->whereNull('deleted_at')
            ->whereNotNull('completed_at')
            ->whereYear('completed_at', $selectedYear)
            ->selectRaw('MONTH(completed_at) as month_no, COUNT(*) as total')
            ->groupBy('month_no')
            ->pluck('total', 'month_no');

        $incomeMonthlyRaw = DB::table('journal_items')
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->whereNull('journal_items.deleted_at')
            ->whereNull('journal_entries.deleted_at')
            ->where('accounts.type', 'income')
            ->whereYear('journal_entries.date', $selectedYear)
            ->selectRaw('MONTH(journal_entries.date) as month_no, SUM(journal_items.credit - journal_items.debit) as total')
            ->groupBy('month_no')
            ->pluck('total', 'month_no');

        $expenseMonthlyRaw = DB::table('journal_items')
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->whereNull('journal_items.deleted_at')
            ->whereNull('journal_entries.deleted_at')
            ->where('accounts.type', 'expense')
            ->whereYear('journal_entries.date', $selectedYear)
            ->selectRaw('MONTH(journal_entries.date) as month_no, SUM(journal_items.debit - journal_items.credit) as total')
            ->groupBy('month_no')
            ->pluck('total', 'month_no');

        $taskMonthlyLabels       = [];
        $taskMonthlyCreatedData  = [];
        $taskMonthlyCompletedData = [];
        $monthlyIncomeLabels     = [];
        $monthlyIncomeData       = [];
        $monthlyExpenseData      = [];

        for ($month = 1; $month <= 12; $month++) {
            $label                     = Carbon::create()->month($month)->format('M');
            $taskMonthlyLabels[]       = $label;
            $taskMonthlyCreatedData[]  = (int) ($monthlyCreatedRaw[$month] ?? 0);
            $taskMonthlyCompletedData[] = (int) ($monthlyCompletedRaw[$month] ?? 0);
            $monthlyIncomeLabels[]     = $label;
            $monthlyIncomeData[]       = round((float) ($incomeMonthlyRaw[$month] ?? 0), 2);
            $monthlyExpenseData[]      = round((float) ($expenseMonthlyRaw[$month] ?? 0), 2);
        }

        // ── Attendance table — always show today's attendance on dashboard ─────
        $selectedDate = Carbon::today()->toDateString();
        $dayName = strtolower(\Carbon\Carbon::parse($selectedDate)->format('l'));

        // 2. Fetch all users with their shifts
        $users = User::whereNotNull('company_id')
            ->where('is_super_admin', 0)
            ->where('status', 'active')
            ->whereHas('roles', function ($q) {
                $q->where('name', 'employee');
            })
            ->with('shift')
            ->orderBy('name')
            ->get();

        // 3. Fetch all attendance records for this specific date
        $dbAttendances = Attendance::with('attendence_setting')
            ->whereDate('date', $selectedDate)
            ->get()
            ->keyBy('user_id');

        // 4. Fetch Holidays/Leaves for this date
        $holidays = DB::table('holidays')
            ->whereDate('start_date', '<=', $selectedDate)
            ->whereDate('end_date', '>=', $selectedDate)
            ->get();

        $leaves = DB::table('leaves')
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $selectedDate)
            ->whereDate('end_date', '>=', $selectedDate)
            ->get()
            ->keyBy('user_id');

        $reportData = [];

        foreach ($users as $user) {
            $record = $dbAttendances->get($user->id);
            $leaveRecord = $leaves->get($user->id);
            $isHoliday = $holidays->isNotEmpty();
            $isWeekend = $user->shift && in_array($dayName, $user->shift->holidays ?? []);

            $status = 'Absent';
            $note = 'No record';
            $checkIn = $record->check_in ?? null;
            $checkOut = $record->check_out ?? null;
            if ($record) {
                // Late Calculation
                if ($checkIn && $record->attendence_setting) {
                    $minutes = (int) $record->attendence_setting->time_after_checkin;
                    
                    $lateThreshold = Carbon::parse($selectedDate.' '.$record->shift->start_time)
                                            ->addMinutes($minutes);
                    $actualCheckIn = \Carbon\Carbon::parse($selectedDate . ' ' . $checkIn);
                    //late minutes calculation
                    $lateMinutes = $actualCheckIn->gt($lateThreshold) ? $actualCheckIn->diffInMinutes($lateThreshold) : 0;
                    
                    $status = $actualCheckIn->gt($lateThreshold) ? 'Late' : 'Present';
                } else {
                    $status = Str::headline((string) ($record->status ?? 'Present'));
                }
                $note = $record->note ?? '-';
            } elseif ($leaveRecord) {
                $status = 'Leave';
                $note = 'Approved Leave';
            } elseif ($isHoliday || $isWeekend) {
                $status = 'Holiday';
                $note = $isHoliday ? $holidays->first()->name : 'Weekend';
            }

            $reportData[] = (object)[
                'id' => $record->id ?? null,
                'user_id' => $user->id,
                'company_id' => $user->company_id,
                'user_name' => $user->name,
                'shift_id' => $user->shift_id,
                'shift_name' => $user->shift->name ?? '-',
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'status' => $status,
                'note' => $note,
                'date' => $selectedDate
            ];
        }

        $statusOrder = [
            'Present' => 1,
            'Late'    => 2,
            'Absent'  => 3,
            'Leave'   => 4,
            'Holiday' => 5,
        ];

        $reportData = collect($reportData)
            ->sortBy(function ($row) use ($statusOrder) {
                return $statusOrder[$row->status] ?? 999;
            })
            ->values()
            ->all();

        $users = User::where('status', 'active')
            ->where('is_super_admin', 0)
            ->whereHas('roles', function ($q) {
                $q->where('name', 'employee');
            })
            ->get();
        $companies = Company::where('status', 1)->get();
        $shifts = DB::table('shifts')->get();
        $employees = User::where('id','!=',Auth::id())->role('employee')->get();
        $departments = Department::orderBy('name')->get();
         

        
        $currentUser = Auth::user();

        $employeesQuery = User::query()
            ->where('id', '!=', $currentUser->id)
            ->where('status', 'active');

        $lastChatSubQuery = Chat::query()
            ->selectRaw(
                'CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as other_user_id, MAX(created_at) as last_message_at',
                [$currentUser->id]
            )
            ->where(function ($q) use ($currentUser) {
                $q->where('sender_id', $currentUser->id)
                    ->orWhere('receiver_id', $currentUser->id);
            })
            ->groupBy('other_user_id');

        $employees = $employeesQuery
            ->leftJoinSub($lastChatSubQuery, 'last_chat', function ($join) {
                $join->on('users.id', '=', 'last_chat.other_user_id');
            })
            ->select('users.*', 'last_chat.last_message_at')
            ->orderByRaw('CASE WHEN last_chat.last_message_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('last_chat.last_message_at')
            ->orderBy('users.name')
            ->get();

        $unreadBySender = Chat::where('receiver_id', $currentUser->id)
            ->whereNull('read_at')
            ->select('sender_id', DB::raw('COUNT(*) as unread_count'))
            ->groupBy('sender_id')
            ->pluck('unread_count', 'sender_id');

        $employees->transform(function ($employee) use ($unreadBySender) {
            $employee->unread_count = (int) ($unreadBySender[$employee->id] ?? 0);
            return $employee;
        });


        // Notices
        $user = Auth::user();
        $profile = $user->profile;
        if ($profile) {
            $profile->load(['department', 'designation']);
        }

        $notices = collect();
        $departmentId = $profile?->department_id;
        //only date
        $today = Carbon::today()->toDateString();
        if ($user) {
            $notices = Notice::query()
                ->where('status', 'published')
                ->where(function ($query) use ($today) {
                    $query->whereNull('publish_date')
                        ->orWhereDate('publish_date', '<=', $today);
                })
                
                ->where(function ($query) use ($today) {
                    $query->whereNull('expiry_date')
                        ->orWhereDate('expiry_date', '>=', $today);
                })
                // Null scope means "all users"; non-null scope must match current user.
                ->where(function ($query) use ($user) {
                    $query->whereNull('company_id')
                        ->orWhere('company_id', $user->company_id);
                })
                ->where(function ($query) use ($departmentId) {
                    $query->whereNull('department_id');

                    if ($departmentId) {
                        $query->orWhere('department_id', $departmentId);
                    }
                })
                ->get();
        }

        // ── Today's Attendance Rate ───────────────────────────────────────────
        $todayStr = Carbon::today()->toDateString();
        // $todayAttendanceRate = $totalEmployees > 0
        //     ? round(($todayPresentCount / $totalEmployees) * 100, 1)
        //     : 0;

        // Employee profile card stats
        $employeeStats = null;
        $employeeRecentActivities = collect();
        $employeeDueTasks = collect();
        $employeeTodayAttendance = null;
        $employeeUpcomingLeave = null;
        $employeeUpcomingHoliday = null;
        $employeeLatestTaskBoardId = null;
        if ($user->hasRole('employee')) {
            $eAttendance = Attendance::where('user_id', $user->id)
                ->whereYear('date', now()->year)
                ->whereMonth('date', now()->month)
                ->whereIn('status', ['Present', 'Late', 'present', 'late'])
                ->count();
            $eTasks = DB::table('task_user')->where('user_id', $user->id)->count();
            $eLeaves = DB::table('leaves')
                ->where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereYear('start_date', now()->year)
                ->count();
            $employeeStats = (object)[
                'attendance_this_month' => $eAttendance,
                'tasks_count'           => $eTasks,
                'leaves_this_year'      => $eLeaves,
            ];

            $todayAttendanceRecord = Attendance::query()
                ->with(['shift', 'attendence_setting'])
                ->where('user_id', $user->id)
                ->whereDate('date', Carbon::today()->toDateString())
                ->first();

            $todayAttendanceStatus = 'Absent';
            $todayAttendanceNote = 'No check-in found for today';
            $todayLateMinutes = 0;

            if ($todayAttendanceRecord) {
                $todayAttendanceStatus = $todayAttendanceRecord->status
                    ? Str::headline((string) $todayAttendanceRecord->status)
                    : 'Present';
                $todayAttendanceNote = $todayAttendanceRecord->note ?: 'Attendance recorded';

                if ($todayAttendanceRecord->check_in && $todayAttendanceRecord->attendence_setting && $todayAttendanceRecord->shift) {
                    $lateThreshold = Carbon::parse(Carbon::today()->toDateString() . ' ' . $todayAttendanceRecord->shift->start_time)
                        ->addMinutes((int) $todayAttendanceRecord->attendence_setting->time_after_checkin);
                    $actualCheckIn = Carbon::parse(Carbon::today()->toDateString() . ' ' . $todayAttendanceRecord->check_in);

                    if ($actualCheckIn->gt($lateThreshold)) {
                        $todayAttendanceStatus = 'Late';
                        $todayLateMinutes = $actualCheckIn->diffInMinutes($lateThreshold);
                        $todayAttendanceNote = 'Checked in ' . $todayLateMinutes . ' min late';
                    } elseif (blank($todayAttendanceRecord->status)) {
                        $todayAttendanceStatus = 'Present';
                    }
                }
            } else {
                $todayApprovedLeave = Leave::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'approved')
                    ->whereDate('start_date', '<=', Carbon::today()->toDateString())
                    ->whereDate('end_date', '>=', Carbon::today()->toDateString())
                    ->first();

                $todayHoliday = Holiday::query()
                    ->whereDate('start_date', '<=', Carbon::today()->toDateString())
                    ->whereDate('end_date', '>=', Carbon::today()->toDateString())
                    ->first();

                if ($todayApprovedLeave) {
                    $todayAttendanceStatus = 'On Leave';
                    $todayAttendanceNote = $todayApprovedLeave->reason ?: 'Approved leave for today';
                } elseif ($todayHoliday) {
                    $todayAttendanceStatus = 'Holiday';
                    $todayAttendanceNote = $todayHoliday->name ?: 'Holiday today';
                }
            }

            $employeeTodayAttendance = (object) [
                'status' => $todayAttendanceStatus,
                'note' => $todayAttendanceNote,
                'check_in' => $todayAttendanceRecord?->check_in,
                'check_out' => $todayAttendanceRecord?->check_out,
                'shift_name' => $todayAttendanceRecord?->shift?->name,
                'late_minutes' => $todayLateMinutes,
            ];

            $employeeTaskIds = DB::table('task_user')
                ->where('user_id', $user->id)
                ->pluck('task_id');

            $employeeLatestTaskBoardId = Task::query()
                ->whereIn('id', $employeeTaskIds)
                ->whereNotNull('board_id')
                ->latest('created_at')
                ->value('board_id');

            $taskActivities = TaskActivityLog::query()
                ->with(['task:id,title,column_id', 'task.column:id,name'])
                ->whereIn('task_id', $employeeTaskIds)
                ->latest()
                ->limit(6)
                ->get()
                ->map(function ($log) {
                    $statusName = optional(optional($log->task)->column)->name ?: 'Updated';

                    return (object) [
                        'dot_class' => 'bg-indigo-500',
                        'title' => $log->description ?: ('Task updated - ' . (optional($log->task)->title ?? 'Untitled task')),
                        'subtitle' => optional($log->task)->title ?: 'Task activity',
                        'badge' => Str::limit($statusName, 18),
                        'badge_class' => 'bg-indigo-100 text-indigo-700',
                        'created_at' => $log->created_at,
                    ];
                });

            $leaveActivities = Leave::query()
                ->with('leave_type:id,name')
                ->where('user_id', $user->id)
                ->latest()
                ->limit(3)
                ->get()
                ->map(function ($leave) {
                    $status = ucfirst(strtolower((string) $leave->status));
                    $badgeClass = match (strtolower((string) $leave->status)) {
                        'approved' => 'bg-green-100 text-green-700',
                        'rejected' => 'bg-red-100 text-red-700',
                        default => 'bg-yellow-100 text-yellow-700',
                    };

                    return (object) [
                        'dot_class' => 'bg-amber-500',
                        'title' => 'Leave request - ' . ($leave->leave_type->name ?? 'General Leave'),
                        'subtitle' => Carbon::parse($leave->start_date)->format('d M') . ' to ' . Carbon::parse($leave->end_date)->format('d M'),
                        'badge' => $status,
                        'badge_class' => $badgeClass,
                        'created_at' => $leave->created_at,
                    ];
                });

            $ticketActivities = SupportTicket::query()
                ->where(function ($query) use ($user) {
                    $query->where('assigned_to', $user->id)
                        ->orWhere('created_by', $user->id);
                })
                ->latest()
                ->limit(3)
                ->get()
                ->map(function ($ticket) {
                    $status = Str::headline((string) ($ticket->status ?: 'Open'));
                    $statusKey = strtolower((string) $ticket->status);
                    $badgeClass = match ($statusKey) {
                        'resolved', 'closed' => 'bg-green-100 text-green-700',
                        'in_progress', 'progress' => 'bg-blue-100 text-blue-700',
                        default => 'bg-rose-100 text-rose-700',
                    };

                    return (object) [
                        'dot_class' => 'bg-emerald-500',
                        'title' => 'Support ticket - ' . Str::limit((string) $ticket->title, 42),
                        'subtitle' => 'Priority: ' . ucfirst((string) ($ticket->priority ?: 'normal')),
                        'badge' => $status,
                        'badge_class' => $badgeClass,
                        'created_at' => $ticket->created_at,
                    ];
                });

            $employeeRecentActivities = $taskActivities
                ->concat($leaveActivities)
                ->concat($ticketActivities)
                ->sortByDesc('created_at')
                ->take(4)
                ->values();

            $completedColumnIdsForEmployee = DB::table('columns')
                ->whereRaw("LOWER(name) REGEXP 'done|completed|complete|closed|finish|finished'")
                ->pluck('id');

            $employeeDueTasks = Task::query()
                ->with(['column:id,name'])
                ->whereHas('users', function ($query) use ($user) {
                    $query->where('users.id', $user->id);
                })
                ->latest()
                ->limit(5)
                ->get()
                ->map(function ($task) {
                    $priority = strtolower((string) ($task->priority ?: 'medium'));
                    $priorityLabel = ucfirst($priority);
                    $priorityClass = match ($priority) {
                        'high' => 'bg-red-100 text-red-700',
                        'low' => 'bg-green-100 text-green-700',
                        default => 'bg-yellow-100 text-yellow-700',
                    };

                    $columnName = Str::headline((string) (optional($task->column)->name ?: 'In Progress'));
                    $columnKey = strtolower((string) optional($task->column)->name);
                    $statusClass = str_contains($columnKey, 'done') || str_contains($columnKey, 'complete')
                        ? 'bg-green-100 text-green-700'
                        : 'bg-blue-100 text-blue-700';

                    return (object) [
                        'title' => $task->title,
                        'priority' => $priorityLabel,
                        'priority_class' => $priorityClass,
                        'status' => $columnName,
                        'status_class' => $statusClass,
                        'dot_class' => match ($priority) {
                            'high' => 'bg-red-500',
                            'low' => 'bg-emerald-500',
                            default => 'bg-amber-500',
                        },
                    ];
                });

            $employeeUpcomingLeave = Leave::query()
                ->with('leave_type:id,name')
                ->where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereDate('start_date', '>=', Carbon::today()->toDateString())
                ->orderBy('start_date')
                ->first();

            $employeeUpcomingHoliday = Holiday::query()
                ->whereDate('start_date', '>=', Carbon::today()->toDateString())
                ->orderBy('start_date')
                ->first();
        }

        // ── Payment Schedule (Today / Upcoming 7 Days) ────────────────────────
        $scheduleFilter = $request->input('schedule_filter', 'today');

        $buildScheduleQuery = function (string $type) use ($scheduleFilter) {
            $q = PaymentSchedule::with('projectCategory')->where('type', $type);
            if ($scheduleFilter === '7days') {
                $rangeEnd = Carbon::today()->addDays(6)->toDateString();
                $q->where(function ($inner) use ($rangeEnd) {
                    $inner->where(function ($i2) use ($rangeEnd) {
                        $i2->whereBetween('scheduled_date', [Carbon::today()->toDateString(), $rangeEnd])
                            ->whereIn('status', ['pending', 'approved']);
                    })->orWhere('status', 'overdue');
                });
            } else {
                $q->where(function ($inner) {
                    $inner->where(function ($i2) {
                        $i2->whereDate('scheduled_date', Carbon::today())
                            ->whereIn('status', ['pending', 'approved']);
                    })->orWhere('status', 'overdue');
                });
            }
            return $q->orderByRaw("FIELD(priority,'high','medium','low')")
                     ->orderBy('scheduled_date')
                     ->get();
        };

        $dashboardPayable    = $buildScheduleQuery('pay');
        $dashboardReceivable = $buildScheduleQuery('receive');

        // ── Paid / Received Payments (Today or Last 7 Days) ───────────────────
        $buildPaidQuery = function (string $type) use ($scheduleFilter) {
            $q = PaymentSchedule::where('type', $type)->where('status', 'paid');
            if ($scheduleFilter === '7days') {
                $rangeStart = Carbon::today()->subDays(6)->toDateString();
                $q->whereBetween('paid_date', [$rangeStart, Carbon::today()->toDateString()]);
            } else {
                $q->whereDate('paid_date', Carbon::today());
            }
            return $q->orderByDesc('paid_date')->get();
        };

        $dashboardPaidPayments    = $buildPaidQuery('pay');
        $dashboardPaidReceivables = $buildPaidQuery('receive');

        // ── Office Todos Stats ────────────────────────────────────────────────
        $todoCarbonToday = Carbon::today();

        if ($user->hasRole('employee')) {
            $myAssigneeRows = OfficeTodoAssignee::where('user_id', $user->id)->get();

            $urgentCount = OfficeTodoAssignee::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'in_progress'])
                ->whereHas('todo', function ($q) use ($todoCarbonToday) {
                    $q->where('priority', 'high')
                      ->where(function ($q2) use ($todoCarbonToday) {
                          $q2->whereNull('due_date')->orWhereDate('due_date', '<=', $todoCarbonToday);
                      });
                })
                ->count();

            $highCount = OfficeTodoAssignee::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'in_progress'])
                ->whereHas('todo', fn ($q) => $q->where('priority', 'high'))
                ->count();

            $mediumWeekCount = OfficeTodoAssignee::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'in_progress'])
                ->whereHas('todo', fn ($q) => $q->where('priority', 'medium'))
                ->count();

            $resolvedTodayCount = OfficeTodoAssignee::where('user_id', $user->id)
                ->where('status', 'completed')
                ->whereDate('completed_at', $todoCarbonToday)
                ->count();

            $todoStats = [
                'total'          => $myAssigneeRows->count(),
                'pending'        => $myAssigneeRows->where('status', 'pending')->count(),
                'in_progress'    => $myAssigneeRows->where('status', 'in_progress')->count(),
                'completed'      => $myAssigneeRows->where('status', 'completed')->count(),
                'urgent'         => $urgentCount,
                'high'           => $highCount,
                'medium_week'    => $mediumWeekCount,
                'resolved_today' => $resolvedTodayCount,
            ];

            $todoActiveIssues = OfficeTodo::whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
                ->with([
                    'creator:id,name',
                    'department:id,name',
                    'assignees' => fn ($q) => $q->where('users.id', $user->id),
                ])
                ->whereIn('status', ['pending', 'in_progress'])
                ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            $todoUpcoming = OfficeTodoAssignee::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'in_progress'])
                ->with(['todo:id,title,priority,due_date'])
                ->orderBy('created_at', 'asc')
                ->limit(5)
                ->get()
                ->map(fn ($a) => $a->todo)
                ->filter()
                ->values();
        } else {
            $urgentCount = OfficeTodo::whereIn('status', ['pending', 'in_progress'])
                ->where('priority', 'high')
                ->where(function ($q) use ($todoCarbonToday) {
                    $q->whereNull('due_date')->orWhereDate('due_date', '<=', $todoCarbonToday);
                })
                ->count();

            $highCount = OfficeTodo::whereIn('status', ['pending', 'in_progress'])
                ->where('priority', 'high')
                ->count();

            $mediumWeekCount = OfficeTodo::whereIn('status', ['pending', 'in_progress'])
                ->where('priority', 'medium')
                ->count();

            $resolvedTodayCount = OfficeTodo::where('status', 'completed')
                ->whereDate('updated_at', $todoCarbonToday)
                ->count();

            $todoStats = [
                'total'          => OfficeTodo::count(),
                'pending'        => OfficeTodo::where('status', 'pending')->count(),
                'in_progress'    => OfficeTodo::where('status', 'in_progress')->count(),
                'completed'      => OfficeTodo::where('status', 'completed')->count(),
                'urgent'         => $urgentCount,
                'high'           => $highCount,
                'medium_week'    => $mediumWeekCount,
                'resolved_today' => $resolvedTodayCount,
            ];

            $todoActiveIssues = OfficeTodo::with(['creator:id,name', 'department:id,name', 'assignees:id,name,image'])
                ->whereIn('status', ['pending', 'in_progress'])
                ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            $todoUpcoming = OfficeTodo::whereIn('status', ['pending', 'in_progress'])
                ->orderBy('due_date', 'asc')
                ->limit(5)
                ->get(['id', 'title', 'priority', 'due_date', 'status', 'is_self']);
        }

        // ── CRM Pipeline & Ongoing Projects ──────────────────────────────────
        $pipelineStatuses = ['new', 'contacted', 'qualified', 'proposal_sent', 'negotiation'];

        $pipelineCount = Lead::whereIn('status', $pipelineStatuses)->count();

        $pipelineByStage = Lead::whereIn('status', $pipelineStatuses)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $pipelineLeads = Lead::whereIn('status', $pipelineStatuses)
            ->with('assignedEmployee:id,name')
            ->latest()
            ->take(5)
            ->get();

        $ongoingCount = Project::whereNotNull('lead_id')
            ->where('status', 'in_progress')
            ->count();

        $ongoingProjects = Project::whereNotNull('lead_id')
            ->where('status', 'in_progress')
            ->with(['lead:id,name', 'customer:id,name', 'projectCategory:id,name', 'department:id,name', 'company:id,name'])
            ->latest()
            ->take(5)
            ->get();

       
        $banks = Bank::where('status', 1)->orderBy('name')->get();

        // ── Portal Account Wallets ────────────────────────────────────────────
        $mtdStart = Carbon::today()->startOfMonth()->toDateString();
        $mtdEnd   = Carbon::today()->toDateString();

        $portalWallets = Portal::whereNull('deleted_at')
            ->orderBy('name')
            ->get()
            ->map(function ($portal) use ($mtdStart, $mtdEnd) {
                $mtdTickets = DB::table('ticket_purchases')
                    ->where('portal_id', $portal->id)
                    ->whereBetween(DB::raw('DATE(created_at)'), [$mtdStart, $mtdEnd])
                    ->count();

                return (object) [
                    'id'         => $portal->id,
                    'name'       => $portal->name,
                    'type'       => $portal->type,
                    'status'     => $portal->status,
                    'next_payment_date' => $portal->next_payment_date,
                    'next_payment_amount' => $portal->next_payment_amount,
                    'balance'    => (float) $portal->total_used,
                    'mtd_tickets'=> $mtdTickets,
                    'synced_at'  => $portal->updated_at,
                ];
            });

        // Fetch external Data Management (DM) company access/subscription items
        $dmAccesses = [];
        $dmDocuments = [];
        $dmAllItems = [];
        try {
            $dmService = app(DmApiService::class);
            $accessItems = $dmService->fetchAccessItems(1, 200);
            if (is_array($accessItems)) {
                // Renewals somebody has already filed an expense against are not
                // outstanding work, so they leave this panel. withoutSettled()
                // cannot throw: on a server whose dm_renewal_payments migration
                // has not run it returns the list untouched, which is exactly how
                // the Renewal Center behaved before it existed.
                $dmAccesses = \App\Models\DmRenewalPayment::withoutSettled($accessItems, \App\Models\DmRenewalPayment::SUBSCRIPTION);
            }

            // Documents mirrors Subscriptions: lapsed *and* still-upcoming
            // renewals, with the upcoming ones leading and expiries kept last.
            $documentItems = $dmService->fetchExpiredDocuments(1, 200, ['scope' => 'all']);
            if (is_array($documentItems)) {
                $dmDocuments = $this->sortDmDocumentsUpcomingFirst(
                    \App\Models\DmRenewalPayment::withoutSettled($documentItems, \App\Models\DmRenewalPayment::DOCUMENT)
                );
            }

            // "All" digest — everything falling due inside the current month,
            // pulled with an explicit window instead of reusing the lists above
            // (those are capped at 6 rows and ordered by recency, not by date).
            $monthStart = Carbon::today()->startOfMonth();
            $monthEnd = Carbon::today()->endOfMonth();
            $monthWindow = [
                'from' => $monthStart->toDateString(),
                'to' => $monthEnd->toDateString(),
            ];

            $monthAccesses = $dmService->fetchAccessItems(1, 200, $monthWindow);
            $monthDocuments = $dmService->fetchExpiredDocuments(1, 200, $monthWindow);

            $dmAllItems = $this->buildDmRenewalDigest(
                \App\Models\DmRenewalPayment::withoutSettled(is_array($monthAccesses) ? $monthAccesses : [], \App\Models\DmRenewalPayment::SUBSCRIPTION),
                \App\Models\DmRenewalPayment::withoutSettled(is_array($monthDocuments) ? $monthDocuments : [], \App\Models\DmRenewalPayment::DOCUMENT),
                $monthStart,
                $monthEnd
            );
        } catch (\Throwable $e) {
            logger()->warning('Failed to fetch DM renewal data: ' . $e->getMessage());
        }


        return view('panel.dashboard.index', compact('reportData', 'selectedDate', 'total_liability', 'total_payable', 'total_receivable', 'total_bank_balance', 'total_office_cash', 'total_income', 'total_expense', 'total_profit', 'task_total', 'task_completed', 'task_due_today', 'task_overdue', 'taskWeeklyLabels', 'taskWeeklyCreatedData', 'taskWeeklyCompletedData', 'taskMonthlyLabels', 'taskMonthlyCreatedData', 'taskMonthlyCompletedData', 'monthlyIncomeLabels', 'monthlyIncomeData', 'monthlyExpenseData', 'selectedYear', 'selectedMonth', 'period', 'startDate', 'endDate', 'users', 'companies', 'shifts', 'employees', 'notices', 'profile', 'employeeStats', 'employeeRecentActivities', 'employeeDueTasks', 'employeeTodayAttendance', 'employeeUpcomingLeave', 'employeeUpcomingHoliday', 'employeeLatestTaskBoardId', 'todayPresentCount', 'monthAttendanceRate', 'monthAbsentCount', 'monthLeaveCount', 'todayAbsentCount', 'todayLeaveCount', 'totalEmployees', 'scheduleFilter', 'dashboardPayable', 'dashboardReceivable', 'dashboardPaidPayments', 'dashboardPaidReceivables', 'todoStats', 'todoUpcoming', 'todoActiveIssues', 'departments',
            'pipelineCount', 'pipelineByStage', 'pipelineLeads',
            'ongoingCount', 'ongoingProjects', 'dmAccesses', 'dmDocuments', 'dmAllItems', 'banks', 'portalWallets'));
    }

    /**
     * Merge DM subscriptions and document renewals into one current-month list
     * for the Renewal Center's "All" view. Overdue rows come first (most
     * overdue at the top), then the rest of the month by nearest due date.
     *
     * @param array $accesses
     * @param array $documents
     * @return array
     */
    private function buildDmRenewalDigest(array $accesses, array $documents, Carbon $monthStart, Carbon $monthEnd): array
    {
        $today = Carbon::today();
        $items = [];

        foreach ($accesses as $access) {
            $dueRaw = data_get($access, 'expired_date') ?: data_get($access, 'renewal_date');
            $due = $this->parseDmDate($dueRaw);

            if (! $due || $due->lt($monthStart) || $due->gt($monthEnd)) {
                continue;
            }

            $renewal = $this->parseDmDate(data_get($access, 'renewal_date'));
            $expired = $this->parseDmDate(data_get($access, 'expired_date'));
            $amountValue = data_get($access, 'amount');

            $items[] = $this->decorateDmDigestItem([
                'type' => 'subscription',
                // Anchor the Live Bulletin links onto this row.
                'ref' => data_get($access, 'id') ? 'dm-subscription-'.data_get($access, 'id') : '',
                'source_label' => 'Subscription',
                'icon' => '🔔',
                'icon_class' => 'rn-ic-orange',
                'title' => data_get($access, 'name') ?: 'Untitled',
                'company' => data_get($access, 'company.name') ?: data_get($access, 'company_name') ?: 'N/A',
                'access_type' => data_get($access, 'access_type') ?: data_get($access, 'subscription_type') ?: 'Access',
                'subscription_type' => data_get($access, 'subscription_type') ?: '—',
                'currency' => data_get($access, 'currency') ?: '',
                'amount' => is_numeric($amountValue) ? number_format((float) $amountValue, 2) : $amountValue,
                'renewal_text' => $renewal ? $renewal->format('d M Y') : '—',
                'expired_text' => $expired ? $expired->format('d M Y') : '—',
                'email' => data_get($access, 'email') ?: data_get($access, 'username') ?: '—',
                'phone' => data_get($access, 'phone') ?: '—',
                'notes' => data_get($access, 'notes') ?: 'No notes',
                'link_url' => data_get($access, 'url') ?: '#',
            ], $due, $today);
        }

        foreach ($documents as $document) {
            $dueRaw = data_get($document, 'renewal_date')
                ?: data_get($document, 'expired_date')
                ?: data_get($document, 'documents.renewal_date');
            $due = $this->parseDmDate($dueRaw);

            if (! $due || $due->lt($monthStart) || $due->gt($monthEnd)) {
                continue;
            }

            $title = data_get($document, 'documents.title') ?: data_get($document, 'title') ?: 'Untitled';
            $documentType = data_get($document, 'document_type.name') ?: ('Type #' . (data_get($document, 'document_type_id') ?: '—'));
            $documentCategory = data_get($document, 'document_category.name') ?: ('Category #' . (data_get($document, 'document_category_id') ?: '—'));
            $created = $this->parseDmDate(data_get($document, 'created_date') ?: data_get($document, 'documents.created_date'));
            $createdText = $created ? $created->format('d M Y') : '—';

            $items[] = $this->decorateDmDigestItem([
                'type' => 'document',
                'ref' => data_get($document, 'id') ? 'dm-document-'.data_get($document, 'id') : '',
                'source_label' => 'Renewal',
                'icon' => '📄',
                'icon_class' => 'rn-ic-blue',
                'title' => $title,
                'company' => data_get($document, 'company.name') ?: 'Document Renewal',
                'access_type' => $documentType,
                'subscription_type' => $documentCategory,
                'currency' => '',
                'amount' => null,
                'renewal_text' => $createdText,
                'expired_text' => $due->format('d M Y'),
                'email' => '—',
                'phone' => '—',
                'notes' => sprintf('%s · %s · Created %s', $documentType, $documentCategory, $createdText),
                'link_url' => data_get($document, 'file_path') ?: data_get($document, 'image_path') ?: '#',
            ], $due, $today);
        }

        // Overdue block first (oldest lapse at the top), then upcoming by nearest due date.
        usort($items, function ($a, $b) {
            if ($a['is_overdue'] !== $b['is_overdue']) {
                return $a['is_overdue'] ? -1 : 1;
            }

            return $a['due_sort'] <=> $b['due_sort'];
        });

        return $items;
    }

    /**
     * Order raw DM document payloads for the Documents pane: everything still
     * upcoming first (soonest renewal at the top), then everything already
     * lapsed, always last.
     *
     * Inside the lapsed block the most recent expiry leads. Ordering that block
     * the other way ("most overdue first") reads as more urgent but fails on
     * real data — the live set carries stale and mistyped dates (a year 0025
     * row, several from 2010-2022) which would head the block and push anything
     * actionable out of view. Rows without a usable date go last of all.
     *
     * @param array $documents
     * @return array
     */
    private function sortDmDocumentsUpcomingFirst(array $documents): array
    {
        $today = Carbon::today();

        $ranked = array_map(function ($document) use ($today) {
            $due = $this->parseDmDate(
                data_get($document, 'renewal_date')
                    ?: data_get($document, 'expired_date')
                    ?: data_get($document, 'documents.renewal_date')
            );

            return [
                'document' => $document,
                'due' => $due,
                'is_expired' => $due ? $due->lt($today) : false,
            ];
        }, $documents);

        usort($ranked, function ($a, $b) {
            if (($a['due'] === null) !== ($b['due'] === null)) {
                return $a['due'] === null ? 1 : -1;
            }

            if ($a['due'] === null) {
                return 0;
            }

            if ($a['is_expired'] !== $b['is_expired']) {
                return $a['is_expired'] ? 1 : -1;
            }

            // Upcoming: soonest first. Expired: most recently lapsed first.
            return $a['is_expired']
                ? $b['due']->timestamp <=> $a['due']->timestamp
                : $a['due']->timestamp <=> $b['due']->timestamp;
        });

        return array_column($ranked, 'document');
    }

    /**
     * Attach the shared urgency presentation fields to a digest row.
     */
    private function decorateDmDigestItem(array $item, Carbon $due, Carbon $today): array
    {
        $daysLeft = (int) round($today->diffInDays($due, false));
        $isOverdue = $daysLeft < 0;

        if ($isOverdue) {
            $priority = 'critical';
            $daysLabel = 'Expired';
        } elseif ($daysLeft <= 7) {
            $priority = 'high';
            $daysLabel = $daysLeft . 'd';
        } else {
            $priority = 'medium';
            $daysLabel = $daysLeft . 'd';
        }

        return array_merge($item, [
            'priority' => $priority,
            'priority_label' => ucfirst($priority),
            'days_class' => 'rn-days-' . $priority,
            'days_label' => $daysLabel,
            'due_text' => $due->format('d M Y'),
            'due_sort' => $due->timestamp,
            'is_overdue' => $isOverdue,
        ]);
    }

    /**
     * Parse a DM date payload without letting one bad value break the panel.
     */
    private function parseDmDate($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function todoList()
    {
        $user = Auth::user();

        // Leaf rows only — a parent is just a heading for its sub-items, so
        // counting both would inflate the totals.
        $checklistCount = [
            'checklists as checklists_total' => fn ($q) => $q->leafOnly(),
            'checklists as checklists_checked' => fn ($q) => $q->leafOnly()->where('is_checked', true),
        ];

        if ($user->hasRole('employee')) {
            $issues = OfficeTodo::whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
                ->with(['creator:id,name', 'department:id,name'])
                ->withCount($checklistCount)
                ->whereIn('status', ['pending', 'in_progress'])
                ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        } else {
            $issues = OfficeTodo::with(['creator:id,name', 'department:id,name', 'assignees:id,name,image'])
                ->withCount($checklistCount)
                ->whereIn('status', ['pending', 'in_progress'])
                ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        }

        $today = Carbon::today();

        $data = $issues->map(function ($issue) use ($today) {
            $due     = $issue->due_date;
            $overdue = $due && $due->lt($today);
            $urgent  = $issue->priority === 'high' && ($overdue || ($due && $due->isToday()));

            $assignees = $issue->assignees->map(fn ($a) => [
                'id'    => $a->id,
                'name'  => $a->name,
                'image' => $a->image ? asset($a->image) : null,
            ])->values()->toArray();

            return [
                'id'                 => $issue->id,
                'title'              => $issue->title,
                'priority'           => $issue->priority ?? 'low',
                'status'             => $issue->status ?? 'pending',
                'urgent'             => $urgent,
                'is_self'            => (bool) $issue->is_self,
                'creator'            => optional($issue->creator)->name ?? 'Unknown',
                'department'         => optional($issue->department)->name,
                'time_ago'           => $issue->created_at ? $issue->created_at->diffForHumans() : '',
                'due_date'           => $due ? $due->format('Y-m-d') : null,
                'assignees'          => $assignees,
                'checklists_total'   => (int) $issue->checklists_total,
                'checklists_checked' => (int) $issue->checklists_checked,
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }
}
