<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One month of attendance for one employee, day by day.
 *
 * Lifted verbatim out of Report\ReportController::monthlyAttendancePdf(), which
 * now calls this instead of carrying its own copy. The move was made because
 * the payslip PDF grew an attendance page: a second implementation of "what
 * happened on each day of the month" would eventually disagree with the
 * Monthly Attendance report, and the two documents describing the same month
 * differently is precisely the sort of thing employees escalate.
 *
 * The behaviour is deliberately unchanged, quirks included — the shape it
 * returns is what report.monthly_attendances_pdf expects, and the day rules
 * (late threshold from attendence_setting, a ten-minute early grace, overtime
 * only past an hour after shift end) are the same rules that produced the
 * deductions on the payslip.
 *
 * `records` are the per-day rows; `summary` is the month's totals.
 */
class AttendanceMonthService
{
    /**
     * @return array{records: Collection, summary: array, user: ?User, start: Carbon, end: Carbon}
     */
    public function build(?User $user, int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $period = CarbonPeriod::create($startDate, $endDate);

        $summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'holiday' => 0, 'leave' => 0, 'late_minutes' => 0, 'early_out' => 0, 'early_minutes' => 0, 'overtime_days' => 0, 'overtime_minutes' => 0];

        if (!$user) {
            return ['records' => collect(), 'summary' => $summary, 'user' => null, 'start' => $startDate, 'end' => $endDate];
        }

        $userId = $user->id;

        $dbAttendances = Attendance::with('shift', 'attendence_setting')
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->where('user_id', $userId)
            ->get()->keyBy('date');

        $defaultShift = $user->shift ?? $dbAttendances->first()?->shift;

        $holidays = DB::table('holidays')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
            })->get();

        $leaves = DB::table('leaves')
            ->leftJoin('leave_types', 'leave_types.id', '=', 'leaves.leave_type_id')
            ->where('leaves.user_id', $userId)
            ->where('leaves.status', 'approved')
            ->whereNull('leaves.deleted_at')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('leaves.start_date', [$startDate, $endDate])
                    ->orWhereBetween('leaves.end_date', [$startDate, $endDate]);
            })
            ->select(
                'leaves.*',
                'leave_types.exempts_early_out_deduction',
                'leave_types.exempts_late_deduction'
            )
            ->get();

        $fullMonthData = collect();

        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $dayName = strtolower($date->format('l'));

            if (isset($dbAttendances[$dateString])) {
                $record = $dbAttendances[$dateString];
                $approvedEarlyLeave = $this->findApprovedEarlyLeaveForDate($leaves, $dateString);
                $approvedLateIn = $this->findApprovedLateInForDate($leaves, $dateString);

                if ($record->check_in && $record->attendence_setting) {
                    $checkInTime = Carbon::parse($dateString . ' ' . $record->check_in);
                    $minutes = (int) $record->attendence_setting->time_after_checkin;
                    $lateThreshold = Carbon::parse($dateString . ' ' . $record->shift->start_time)
                        ->addMinutes($minutes);
                    $lateMinutes = (int) abs($checkInTime->diffInMinutes($lateThreshold, false));

                    if ($checkInTime->gt($lateThreshold) && !$approvedLateIn) {
                        $record->status = 'Late ' . $lateMinutes . ' mins';
                        $record->late_minutes = $lateMinutes;
                        $summary['late']++;
                        $summary['present']++;
                        $summary['late_minutes'] += $lateMinutes;
                    } else {
                        $record->status = 'Present';
                        $summary['present']++;
                    }

                    if ($approvedLateIn) {
                        $record->note = 'Approved Late In (until ' . Carbon::parse($approvedLateIn->leave_time)->format('h:i A') . ')';
                    }

                    if ($approvedEarlyLeave && !$record->check_out) {
                        // Still checked in — show the approved time as a heads-up.
                        // If checkout later turns out normal/overtime, this gets left alone
                        // below and the original note is never overwritten.
                        $record->note = 'Approved Early Leave (until ' . Carbon::parse($approvedEarlyLeave->leave_time)->format('h:i A') . ')';
                    }

                    if ($record->check_out && $record->attendence_setting) {
                        $checkOutTime = Carbon::parse($dateString . ' ' . $record->check_out);

                        $minutes = (int) $record->attendence_setting->time_after_checkout;
                        $shiftEnd = Carbon::parse($dateString . ' ' . $record->shift->end_time);

                        // Thresholds (assuming $minutes is 10 for early and 60 for late)
                        $earlyThreshold = $shiftEnd->copy()->subMinutes(10);
                        $overtimeThreshold = $shiftEnd->copy()->addMinutes(60);

                        $earlyMinutes = 0;
                        $overtimeMinutes = 0;

                        if ($checkOutTime->lessThan($earlyThreshold)) {
                            // 1. LEFT TOO EARLY
                            $earlyMinutes = (int) abs($checkOutTime->diffInMinutes($earlyThreshold, false));
                        } elseif ($checkOutTime->greaterThanOrEqualTo($overtimeThreshold)) {
                            // 2. WORKED OVERTIME
                            $overtimeMinutes = (int) abs($checkOutTime->diffInMinutes($shiftEnd, false));
                        } else {
                            // 3. WITHIN GRACE PERIOD
                            $earlyMinutes = 0;
                            $overtimeMinutes = 0;
                        }

                        if ($checkOutTime->lt($earlyThreshold) && $earlyMinutes > 0) {
                            if ($approvedEarlyLeave) {
                                $record->note = 'Approved Early Leave (from ' . Carbon::parse($approvedEarlyLeave->leave_time)->format('h:i A') . ')';
                                $summary['present']++; // Still present for most of the day, no deduction
                            } else {
                                $record->out_status = 'Early Out ' . $earlyMinutes . ' mins';
                                $summary['early_out']++;
                                $summary['present']++;
                                $summary['early_minutes'] += $earlyMinutes;
                            }
                        }

                        if ($checkOutTime->gte($overtimeThreshold) && $overtimeMinutes > 0) {
                            $record->out_status = 'Overtime ' . $overtimeMinutes . ' mins';
                            $summary['overtime_minutes'] += $overtimeMinutes;
                            $summary['overtime_days'] = ($summary['overtime_days'] ?? 0) + 1;
                        }
                    } else {
                        $status = strtolower($record->status);
                        if (array_key_exists($status, $summary)) {
                            $summary[$status]++;
                        }
                    }
                } else {
                    $status = strtolower($record->status);
                    if (array_key_exists($status, $summary)) {
                        $summary[$status]++;
                    }
                }
            } elseif ($leaveRecord = $leaves->first(fn ($l) => !$l->exempts_late_deduction && $dateString >= $l->start_date && $dateString <= $l->end_date)) {
                $record = (object) [
                    'date' => $dateString,
                    'shift_name' => $defaultShift->name ?? '-',
                    'check_in' => null,
                    'check_out' => null,
                    'status' => $leaveRecord->leave_time ? 'Early Leave' : 'Leave',
                    'note' => $leaveRecord->leave_time
                        ? 'Approved (from ' . Carbon::parse($leaveRecord->leave_time)->format('h:i A') . ')'
                        : 'Approved Leave',
                ];
                $summary['leave']++;
            } else {
                $holidayRecord = $holidays->first(fn ($h) => $dateString >= $h->start_date && $dateString <= $h->end_date);
                $isWeekend = $defaultShift && in_array($dayName, $defaultShift->holidays ?? []);

                if ($holidayRecord || $isWeekend) {
                    $record = (object) [
                        'date' => $dateString,
                        'shift_name' => $defaultShift->name ?? '-',
                        'check_in' => null,
                        'check_out' => null,
                        'status' => 'Holiday',
                        'note' => $holidayRecord ? $holidayRecord->name : 'Weekend',
                    ];
                    $summary['holiday']++;
                } else {
                    // 4. Default to Absent
                    $record = (object) [
                        'date' => $dateString,
                        'shift_name' => $defaultShift->name ?? '-',
                        'check_in' => null,
                        'check_out' => null,
                        'status' => 'Absent',
                        'note' => 'No record',
                    ];
                    $summary['absent']++;
                }
            }

            $fullMonthData->push($record);
        }

        return [
            'records' => $fullMonthData,
            'summary' => $summary,
            'user' => $user,
            'start' => $startDate,
            'end' => $endDate,
        ];
    }

    /**
     * Day counts derived by counting the rows, not by the running tallies in
     * build().
     *
     * build()'s `$summary['present']` is incremented more than once for a
     * single day — once when the check-in is classified, again in the early-out
     * branch, and again in the no-checkout fallback that re-reads the status it
     * just wrote. The Monthly Attendance report has always printed those
     * inflated figures (a 31-day month can show "Total Present: 33"), and this
     * method deliberately does NOT change that report: correcting a number that
     * twelve companies have been reading for months is the owner's call, not a
     * side effect of adding a payslip page.
     *
     * What it does guarantee is that the payslip's attendance page never
     * contradicts itself — its footer counts exactly the rows printed above it.
     * Minute accumulations (late/early/overtime) are taken from build(), where
     * they are only ever added once per day.
     */
    public function summaryFromRows(Collection $records, array $walkSummary): array
    {
        $statusOf = fn ($r) => strtolower(trim((string) ($r->status ?? '')));

        $isLate = fn ($r) => str_starts_with($statusOf($r), 'late');
        $isPresent = fn ($r) => str_starts_with($statusOf($r), 'present') || $isLate($r);

        return [
            'total_days' => $records->count(),
            'present' => $records->filter($isPresent)->count(),
            'late' => $records->filter($isLate)->count(),
            'absent' => $records->filter(fn ($r) => $statusOf($r) === 'absent')->count(),
            'holiday' => $records->filter(fn ($r) => $statusOf($r) === 'holiday')->count(),
            'leave' => $records->filter(fn ($r) => in_array($statusOf($r), ['leave', 'early leave'], true))->count(),
            'early_out' => $records->filter(fn ($r) => str_starts_with(strtolower((string) ($r->out_status ?? '')), 'early out'))->count(),
            'overtime_days' => $records->filter(fn ($r) => str_starts_with(strtolower((string) ($r->out_status ?? '')), 'overtime'))->count(),
            'late_minutes' => (int) ($walkSummary['late_minutes'] ?? 0),
            'early_minutes' => (int) ($walkSummary['early_minutes'] ?? 0),
            'overtime_minutes' => (int) ($walkSummary['overtime_minutes'] ?? 0),
        ];
    }

    /**
     * Plain arrays rather than Eloquent models — this is what gets frozen into
     * employee_salaries.summary_snapshot, so it must survive a JSON round trip
     * and must not drag a whole Attendance model into the column.
     *
     * Only the fields report.monthly_attendances_pdf actually reads are kept.
     */
    public function rowsForSnapshot(Collection $records): array
    {
        return $records->map(fn ($r) => [
            'date' => (string) ($r->date instanceof \DateTimeInterface ? $r->date->format('Y-m-d') : $r->date),
            'check_in' => $r->check_in ? (string) $r->check_in : null,
            'check_out' => $r->check_out ? (string) $r->check_out : null,
            'status' => (string) ($r->status ?? ''),
            'note' => $r->note !== null ? (string) $r->note : null,
            'out_status' => isset($r->out_status) ? (string) $r->out_status : null,
        ])->values()->all();
    }

    /**
     * Only leave types marked "exempts_early_out_deduction" (e.g. Early Leave)
     * qualify. Same rule as ReportController and PayrollService.
     */
    private function findApprovedEarlyLeaveForDate($leaves, string $dateString)
    {
        foreach ($leaves as $leave) {
            if (!$leave->exempts_early_out_deduction) {
                continue;
            }

            if ($dateString >= $leave->start_date && $dateString <= $leave->end_date) {
                return $leave;
            }
        }

        return null;
    }

    private function findApprovedLateInForDate($leaves, string $dateString)
    {
        foreach ($leaves as $leave) {
            if (!$leave->exempts_late_deduction) {
                continue;
            }

            if ($dateString >= $leave->start_date && $dateString <= $leave->end_date) {
                return $leave;
            }
        }

        return null;
    }
}
