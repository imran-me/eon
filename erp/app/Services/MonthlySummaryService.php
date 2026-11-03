<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\EmployeePaymentAllocation;
use App\Models\EmployeeSalary;
use App\Models\PaymentSchedule;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Builds the month's attendance + task summary that rides along with the
 * payslip.
 *
 * Two rules shape this class:
 *
 * 1. It is only ever called once per payslip, at issue time, and the result is
 *    frozen into `employee_salaries.summary_snapshot`. Nothing re-derives these
 *    figures at render time — see the migration note on that column for why.
 * 2. It must never be able to fail payroll. Each block is independently
 *    try/caught and degrades to "not available" rather than throwing, because
 *    the caller is in the middle of issuing a payslip and posting a salary.
 *
 * The attendance figures deliberately use the same keys and the same meaning as
 * the Monthly Attendance report (Report\ReportController::monthly_attendances())
 * so the payslip and that screen never disagree. The task figures mirror
 * Report\TaskReportController, scoped to the one month.
 */
class MonthlySummaryService
{
    public const VERSION = 1;

    /** How many months page 2 of the payslip lists, newest first. */
    public const HISTORY_MONTHS = 12;

    /**
     * @param  array|null  $attendanceSummary  The summary PayrollService::calculateAttendanceDeductions()
     *                                         already produced, when the caller has it. Passing it avoids
     *                                         recomputing a full month of attendance a second time.
     */
    public function build(EmployeeSalary $empSalary, ?array $attendanceSummary = null): array
    {
        $year = (int) $empSalary->year;
        $month = (int) $empSalary->month;

        // One walk of the month, shared by the summary totals and the day-by-day
        // page, so the attendance page's own rows always add up to the totals
        // printed beneath them.
        $days = null;
        try {
            $days = app(AttendanceMonthService::class)->build($empSalary->user, $year, $month);
        } catch (\Throwable $e) {
            Log::warning('Monthly summary: attendance day build failed.', [
                'employee_salary_id' => $empSalary->id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'version' => self::VERSION,
            'generated_at' => now()->toDateTimeString(),
            'attendance' => $this->attendanceBlock($empSalary, $year, $month, $attendanceSummary, $days),
            'attendance_days' => $this->attendanceDaysBlock($days),
            'payroll_history' => $this->payrollHistoryBlock($empSalary),
            'tasks' => $this->taskBlock((int) $empSalary->user_id, $year, $month),
            'payment' => $this->paymentBlock($empSalary),
        ];
    }

    /**
     * The day-by-day rows for the payslip's attendance page, frozen.
     *
     * Stored as plain arrays (see AttendanceMonthService::rowsForSnapshot) so
     * the JSON column round-trips cleanly.
     */
    private function attendanceDaysBlock(?array $days): array
    {
        if (! $days || $days['records']->isEmpty()) {
            return ['available' => false];
        }

        try {
            $svc = app(AttendanceMonthService::class);

            return [
                'available' => true,
                'rows' => $svc->rowsForSnapshot($days['records']),
                // Counted off the rows — see summaryFromRows() for why the
                // walk's own tallies are not trusted here.
                'summary' => $svc->summaryFromRows($days['records'], $days['summary']),
            ];
        } catch (\Throwable $e) {
            Log::warning('Monthly summary: attendance day rows failed.', ['error' => $e->getMessage()]);

            return ['available' => false];
        }
    }

    /**
     * Every month this employee has been paid for, with what is still owed on
     * each — page 2 of the payslip.
     *
     * Frozen "as at issue", and the page says so. A saved PDF cannot track
     * payments made after it was written, so the honest thing is to date it
     * rather than to imply it is current. The signed view page carries the
     * live version.
     *
     * "Paid" follows PaymentSchedule::paidTotal(), the same rule the profile's
     * Payslips tab uses, so the two never disagree.
     */
    private function payrollHistoryBlock(EmployeeSalary $empSalary): array
    {
        try {
            $rows = EmployeeSalary::with('schedules')
                ->where('user_id', $empSalary->user_id)
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->get()
                ->map(function ($slip) use ($empSalary) {
                    $net = round((float) $slip->net_salary, 2);
                    $paid = EmployeePaymentAllocation::paidForSalary($slip->id);
                    $isPaid = $paid >= $net - .01;

                    return [
                        'label' => $this->monthLabel((int) $slip->year, (int) $slip->month),
                        'year' => (int) $slip->year,
                        'month' => (int) $slip->month,
                        'gross' => round((float) $slip->gross_salary, 2),
                        'deductions' => round((float) $slip->total_deductions, 2),
                        'net' => $net,
                        'paid' => $paid,
                        'due' => max(0, round($net - $paid, 2)),
                        'status' => $isPaid ? 'Paid' : ($paid > 0 ? 'Partial' : 'Pending'),
                        'is_current' => (int) $slip->id === (int) $empSalary->id,
                    ];
                })
                ->values();

            if ($rows->isEmpty()) {
                return ['available' => false];
            }

            // Page 2 is one page. An employee three years in would otherwise
            // push the payslip to four, so the table shows the last twelve
            // months and the totals cover exactly those twelve — a footer that
            // summed months the reader cannot see would be the same defect the
            // attendance page was just fixed for.
            $olderCount = max(0, $rows->count() - self::HISTORY_MONTHS);
            $rows = $rows->take(self::HISTORY_MONTHS)->values();

            return [
                'available' => true,
                'as_at' => now()->toDateString(),
                'rows' => $rows->all(),
                'older_months' => $olderCount,
                'totals' => [
                    'gross' => round((float) $rows->sum('gross'), 2),
                    'deductions' => round((float) $rows->sum('deductions'), 2),
                    'net' => round((float) $rows->sum('net'), 2),
                    'paid' => round((float) $rows->sum('paid'), 2),
                    'due' => round((float) $rows->sum('due'), 2),
                ],
            ];
        } catch (\Throwable $e) {
            Log::warning('Monthly summary: payroll history failed.', [
                'employee_salary_id' => $empSalary->id,
                'error' => $e->getMessage(),
            ]);

            return ['available' => false];
        }
    }

    private function monthLabel(int $year, int $month): string
    {
        try {
            return Carbon::createFromDate($year, $month, 1)->format('F Y');
        } catch (\Throwable $e) {
            return $month.'/'.$year;
        }
    }

    /**
     * Where this one month's salary stood at the moment the payslip was issued.
     *
     * Frozen on purpose, and it is the ONLY payment figure the PDF carries. A
     * payslip is a document people save and forward, so a "still due" number
     * inside it must describe a fixed moment — "unpaid when issued" stays true
     * forever, while a live balance baked into a saved file is wrong the day
     * after the first instalment lands.
     *
     * The employee's current outstanding across all months is a different
     * question with a different answer every day, so it is rendered live on the
     * signed view page instead (partials/outstanding.blade.php) and never
     * written here.
     *
     * "Paid" follows PaymentSchedule::paidTotal() — the documented rule that
     * cancelled schedules do not count as money received.
     */
    private function paymentBlock(EmployeeSalary $empSalary): array
    {
        try {
            $net = round((float) $empSalary->net_salary, 2);
            $paid = EmployeePaymentAllocation::paidForSalary($empSalary->id);
            $isPaid = $paid >= $net - .01;

            return [
                'available' => true,
                'net' => $net,
                'paid' => $paid,
                'due' => max(0, round($net - $paid, 2)),
                'status' => $isPaid ? 'Paid' : ($paid > 0 ? 'Partial' : 'Pending'),
            ];
        } catch (\Throwable $e) {
            Log::warning('Monthly summary: payment block failed.', [
                'employee_salary_id' => $empSalary->id,
                'error' => $e->getMessage(),
            ]);

            return ['available' => false];
        }
    }

    /**
     * Counts on the left, the taka they produced on the right — the whole point
     * of putting this next to a payslip is that "Absent 3 days" and "Absent
     * deduction 2,400" stop being two unrelated numbers.
     */
    private function attendanceBlock(EmployeeSalary $empSalary, int $year, int $month, ?array $payrollSummary, ?array $days): array
    {
        try {
            // Prefer the totals produced by the same month-walk that produced
            // the day rows on the attendance page — a page whose lines do not
            // add up to its own footer is worse than no page.
            $summary = isset($days['summary'])
                ? app(AttendanceMonthService::class)->summaryFromRows($days['records'], $days['summary'])
                : null;

            if (is_array($summary)) {

                // Payroll computes attendance a second time, independently, and
                // that copy is what the deductions were built from. They should
                // agree; if they ever stop agreeing that is a real defect, so
                // say so in the log rather than quietly printing one of them.
                if (is_array($payrollSummary)) {
                    foreach (['present', 'absent', 'late', 'leave', 'holiday'] as $k) {
                        if ((int) ($payrollSummary[$k] ?? 0) !== (int) ($summary[$k] ?? 0)) {
                            Log::warning('Monthly summary: attendance totals disagree with payroll.', [
                                'employee_salary_id' => $empSalary->id,
                                'field' => $k,
                                'payroll' => $payrollSummary[$k] ?? null,
                                'report' => $summary[$k] ?? null,
                            ]);
                            break;
                        }
                    }
                }
            } else {
                $summary = $payrollSummary;
            }

            if ($summary === null) {
                $user = $empSalary->user;
                if (! $user) {
                    return ['available' => false];
                }
                $summary = app(PayrollService::class)
                    ->calculateAttendanceDeductions($user, $year, $month)['summary'] ?? null;
            }

            if (! is_array($summary)) {
                return ['available' => false];
            }

            return [
                'available' => true,
                'total_days' => (int) ($summary['total_days'] ?? 0),
                'present' => (int) ($summary['present'] ?? 0),
                'absent' => (int) ($summary['absent'] ?? 0),
                'leave' => (int) ($summary['leave'] ?? 0),
                'holiday' => (int) ($summary['holiday'] ?? 0),
                'late' => (int) ($summary['late'] ?? 0),
                'late_minutes' => (int) ($summary['late_minutes'] ?? 0),
                'early_out' => (int) ($summary['early_out'] ?? 0),
                'early_minutes' => (int) ($summary['early_minutes'] ?? 0),
                'overtime_days' => (int) ($summary['overtime_days'] ?? 0),
                'overtime_minutes' => (int) ($summary['overtime_minutes'] ?? 0),
                'worked_minutes' => $this->workedMinutes((int) $empSalary->user_id, $year, $month),
                // The taka each block of counts turned into on this payslip.
                'amounts' => [
                    'absent' => (float) ($empSalary->absent_deduction ?? 0),
                    'leave' => (float) ($empSalary->leave_deduction ?? 0),
                    'late' => (float) ($empSalary->late_deduction ?? 0),
                    'early_out' => (float) ($empSalary->early_leave_deduction ?? 0),
                    'overtime' => (float) ($empSalary->overtime_salary ?? 0),
                ],
            ];
        } catch (\Throwable $e) {
            Log::warning('Monthly summary: attendance block failed.', [
                'employee_salary_id' => $empSalary->id,
                'error' => $e->getMessage(),
            ]);

            return ['available' => false];
        }
    }

    /**
     * Same arithmetic as the Monthly Attendance report's "Total Worked Hours",
     * including its overnight-shift correction (a checkout earlier than the
     * checkin means the shift crossed midnight).
     */
    private function workedMinutes(int $userId, int $year, int $month): int
    {
        $total = 0;

        $records = Attendance::query()
            ->where('user_id', $userId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->whereNotNull('check_in')
            ->whereNotNull('check_out')
            ->get(['date', 'check_in', 'check_out']);

        foreach ($records as $record) {
            $dateString = Carbon::parse($record->date)->format('Y-m-d');
            $start = Carbon::parse($dateString.' '.$record->check_in);
            $end = Carbon::parse($dateString.' '.$record->check_out);

            if ($end->lt($start)) {
                $end->addDay();
            }

            $total += (int) $start->diffInMinutes($end);
        }

        return $total;
    }

    /** Board columns that mean "finished", matching Report\TaskReportController. */
    private const DONE_COLUMNS = ['done', 'completed', 'complete', 'closed'];

    /** Board columns that mean "dropped" — not finished, but not outstanding either. */
    private const CANCELLED_COLUMNS = ['cancelled', 'canceled'];

    /**
     * The month's task picture for one employee.
     *
     * Two figures are reported, and they are deliberately built from different
     * evidence:
     *
     *   completed — `completed_at` falls inside the month. Only a timestamp can
     *               attribute a finish to a month, so this is the strict test.
     *   open      — still outstanding when the month ended.
     *
     * A task can sit in a DONE column with `completed_at` NULL: `completed_at`
     * was added later (2026_04_02_174908) and not every path that closes a task
     * sets it. Treating those as open would print "Overdue" on a payslip for
     * work the employee's own board shows as finished, so the open test honours
     * the column name as well as the timestamp. Cancelled columns are excluded
     * from open for the same reason — a dropped task is not an employee's debt.
     * Tasks that are finished-by-column but carry no timestamp belong to no
     * month at all and are simply left out.
     *
     * `by_status` is grouped over exactly the completed + open set, so the
     * status counts always add up to the two numbers printed above them.
     *
     * `tracked` is false when neither group has anything. Accounts, reception
     * and drivers do real work that never reaches a task board, and printing
     * "0 tasks completed" beside their salary reads as an accusation — the
     * renderers drop the whole block instead.
     */
    private function taskBlock(int $userId, int $year, int $month): array
    {
        try {
            $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $tasks = Task::query()
                ->with('column:id,name')
                ->whereHas('users', fn ($q) => $q->where('users.id', $userId))
                ->where('created_at', '<=', $monthEnd)
                ->where(function ($q) use ($monthStart) {
                    $q->whereNull('completed_at')
                        ->orWhere('completed_at', '>=', $monthStart);
                })
                ->get(['id', 'column_id', 'priority', 'due_date', 'completed_at', 'created_at']);

            $columnName = fn ($t) => strtolower(trim($t->column->name ?? ''));

            $completed = $tasks->filter(
                fn ($t) => $t->completed_at && $t->completed_at->between($monthStart, $monthEnd)
            );

            $completedLate = $completed->filter(
                fn ($t) => $t->due_date && $t->completed_at->gt(Carbon::parse($t->due_date)->endOfDay())
            );

            $open = $tasks->filter(function ($t) use ($columnName, $monthEnd) {
                // Finished on the board, whether or not anyone stamped a date.
                if (in_array($columnName($t), self::DONE_COLUMNS, true)) {
                    return false;
                }
                if (in_array($columnName($t), self::CANCELLED_COLUMNS, true)) {
                    return false;
                }

                // Still open at month end includes a task only finished later.
                return is_null($t->completed_at) || $t->completed_at->gt($monthEnd);
            });

            $overdue = $open->filter(
                fn ($t) => $t->due_date && Carbon::parse($t->due_date)->endOfDay()->lt($monthEnd)
            );

            if ($completed->isEmpty() && $open->isEmpty()) {
                return ['tracked' => false];
            }

            $reported = $completed->concat($open)->unique('id');

            return [
                'tracked' => true,
                'total' => $reported->count(),
                'completed' => $completed->count(),
                'completed_on_time' => $completed->count() - $completedLate->count(),
                'completed_late' => $completedLate->count(),
                'open' => $open->count(),
                'overdue' => $overdue->count(),
                // Mirrors the "Tasks by Status" block on the Employee Task
                // Report — board column names, not a fixed enum, so whatever
                // columns a company actually uses is what shows up.
                'by_status' => $reported->groupBy(fn ($t) => $t->column->name ?? 'Unassigned')
                    ->map->count()
                    ->sortDesc()
                    ->all(),
            ];
        } catch (\Throwable $e) {
            Log::warning('Monthly summary: task block failed.', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return ['tracked' => false];
        }
    }
}
