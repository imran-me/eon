<?php

namespace App\Http\Controllers\Report;

use App\Exports\AttendanceExport;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;
use PDF;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ReportController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view monthly attendance report', only: ['monthly_attendances','monthlyAttendanceExcel','monthlyAttendancePdf','attendanceDetails',]),
            new Middleware('permission:view general ledger report', only: ['generalLedgerV1','generalLedgerV2','generalLedgerPrint',]),
            new Middleware('permission:view trial balance report', only: ['trialBalanceV1','trialBalanceV2',]),
            new Middleware('permission:view profit loss report', only: ['profitLossV1','profitLossV2',]),
            new Middleware('permission:view balance sheet report', only: ['balanceSheetV1','balanceSheetV2',]),
            new Middleware('permission:view account ledger report', only: ['accountLedgerV1','accountLedgerV2',]),
            new Middleware('permission:view account statement report', only: ['accountStatementV1','accountStatementV2',]),
            new Middleware('permission:view journal entry report', only: [ 'journalEntriesV1','journalEntriesV2',]),
            new Middleware('permission:view account balance report', only: ['accountBalancesV1','accountBalancesV2',]),
        ];
    }

    public function monthly_attendances(Request $request)
    {
        $users = User::whereNotNull('company_id')->role('employee')->get();
        $selectedMonth = $request->input('month', date('n'));
        $selectedYear = $request->input('year', date('Y'));
        $userId = $request->input('user_id');

        if ('employee' == \Str::slug(auth()->user()->getRoleNames()->first())) {
            $userId = auth()->id();
        }

        $fullMonthData = collect();
        // Adjusted summary to match the logic where Late is a subset of Present
        $summary = [
            'present' => 0,
            'absent' => 0,
            'leave' => 0,
            'holiday' => 0,
            'late' => 0,
            'total_days' => 0,
            'late_minutes' => 0,
            'early_out' => 0, // For future use if needed
            'early_minutes' => 0, // For future use if needed
            'overtime_days' => 0, // For future use if needed
            'overtime_minutes' => 0, // For future use if needed
        ];

        if ($userId) {
            $user = User::with('shift')->find($userId);

            $dbAttendances = Attendance::with('shift', 'attendence_setting')
                ->whereMonth('date', $selectedMonth)
                ->whereYear('date', $selectedYear)
                ->where('user_id', $userId)
                ->get()
                ->keyBy('date');

            $startDate = \Carbon\Carbon::create($selectedYear, $selectedMonth, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
            $period = \Carbon\CarbonPeriod::create($startDate, $endDate);

            // Fallback shift from user if no attendance records exist yet
            $defaultShift = $user->shift ?? $dbAttendances->first()?->shift;

            // Fetch Holidays that overlap with the selected month
            $holidays = \DB::table('holidays')
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate]);
                })->get();

            // Fetch Approved Leaves for this user
            $leaves = \DB::table('leaves')
                ->leftJoin('leave_types', 'leave_types.id', '=', 'leaves.leave_type_id')
                ->where('leaves.user_id', $userId)
                ->where('leaves.status', 'approved')
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('leaves.start_date', [$startDate, $endDate])
                        ->orWhereBetween('leaves.end_date', [$startDate, $endDate]);
                })
                ->select('leaves.*', 'leave_types.exempts_early_out_deduction')
                ->get();

            foreach ($period as $date) {
                $dateString = $date->format('Y-m-d');
                $dayName = strtolower($date->format('l'));

                if (isset($dbAttendances[$dateString])) {
                    $record = $dbAttendances[$dateString];
                    $approvedEarlyLeave = $this->findApprovedEarlyLeaveForDate($leaves, $dateString);

                    // --- CONSISTENT LATE CALCULATION ---
                    if ($record->check_in && $record->attendence_setting) {
                        $checkInTime = \Carbon\Carbon::parse($dateString . ' ' . $record->check_in);
                        $minutes = (int) $record->attendence_setting->time_after_checkin;
                        // dd($record->shift->start_time);
                        $lateThreshold = \Carbon\Carbon::parse($dateString . ' ' . $record->shift->start_time)
                            ->addMinutes($minutes);
                        // late min count
                        $lateMinutes = (int) abs($checkInTime->diffInMinutes($lateThreshold, false));
                        if ($checkInTime->gt($lateThreshold) && $lateMinutes > 0) {
                            $record->status = 'Late ' . $lateMinutes . ' mins';
                            $summary['late']++;    // Increment Late
                            $summary['present']++; // Also increment Present (as they are physically there)
                            $summary['late_minutes'] += $lateMinutes; // Accumulate late minutes
                        } else {
                            $record->status = 'Present';
                            $summary['present']++;
                        }

                        if ($approvedEarlyLeave && !$record->check_out) {
                            // Still checked in — show the approved time as a heads-up.
                            // If checkout later turns out normal/overtime, this gets left alone
                            // below and the original note is never overwritten.
                            $record->note = 'Approved Early Leave (until ' . \Carbon\Carbon::parse($approvedEarlyLeave->leave_time)->format('h:i A') . ')';
                        }

                        if ($record->check_out && $record->attendence_setting) {
                            $checkOutTime = \Carbon\Carbon::parse($dateString . ' ' . $record->check_out);

                            $minutes = (int) $record->attendence_setting->time_after_checkout;
                            // dd($record->shift->start_time);
                            $shiftEnd = \Carbon\Carbon::parse($dateString . ' ' . $record->shift->end_time);

                            // Thresholds (assuming $minutes is 10 for early and 60 for late)
                            $earlyThreshold = $shiftEnd->copy()->subMinutes(10); // 6:50 PM
                            $overtimeThreshold = $shiftEnd->copy()->addMinutes(60); // 8:00 PM

                            $earlyMinutes = 0;
                            $overtimeMinutes = 0;

                            if ($checkOutTime->lessThan($earlyThreshold)) {
                                // 1. LEFT TOO EARLY (Before 6:50)
                                $earlyMinutes = (int) abs($checkOutTime->diffInMinutes($earlyThreshold, false));
                            } elseif ($checkOutTime->greaterThanOrEqualTo($overtimeThreshold)) {
                                // 2. WORKED OVERTIME (8:00 or later)
                                // We calculate from 7:00 PM to give them the full hour + extra
                                $overtimeMinutes = (int) abs($checkOutTime->diffInMinutes($shiftEnd, false));
                            } else {
                                // 3. WITHIN GRACE PERIOD (6:50 to 7:59)
                                $earlyMinutes = 0;
                                $overtimeMinutes = 0;
                            }
                            if ($checkOutTime->lt($earlyThreshold) && $earlyMinutes > 0) {
                                if ($approvedEarlyLeave) {
                                    $record->note = 'Approved Early Leave (from ' . \Carbon\Carbon::parse($approvedEarlyLeave->leave_time)->format('h:i A') . ')';
                                    $summary['present']++; // Still present for most of the day, no deduction
                                } else {
                                    $record->out_status = 'Early Out ' . $earlyMinutes . ' mins';
                                    $summary['early_out']++;    // Increment Early Out
                                    $summary['present']++; // Also increment Present (as they are physically there)
                                    $summary['early_minutes'] += $earlyMinutes; // Accumulate early minutes
                                }
                            }
                            // Overtime handling days
                            if ($checkOutTime->gte($overtimeThreshold) && $overtimeMinutes > 0) {
                                $record->out_status = 'Overtime ' . $overtimeMinutes . ' mins';
                                $summary['overtime_minutes'] += $overtimeMinutes; // Accumulate overtime minutes
                                $summary['overtime_days'] = ($summary['overtime_days'] ?? 0) + 1; // Count days with overtime
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
                } elseif ($leaveRecord = $leaves->first(fn($l) => $dateString >= $l->start_date && $dateString <= $l->end_date)) {
                    $record = (object)[
                        'date' => $dateString,
                        'shift' => $defaultShift,
                        'check_in' => null,
                        'check_out' => null,
                        'status' => $leaveRecord->leave_time ? 'Early Leave' : 'Leave',
                        'note' => $leaveRecord->leave_time
                            ? 'Approved (from ' . \Carbon\Carbon::parse($leaveRecord->leave_time)->format('h:i A') . ')'
                            : 'Approved Leave'
                    ];
                    $summary['leave']++;
                } else {
                    $holidayRecord = $holidays->first(fn($h) => $dateString >= $h->start_date && $dateString <= $h->end_date);
                    $isWeekend = $defaultShift && in_array($dayName, $defaultShift->holidays ?? []);

                    if ($holidayRecord || $isWeekend) {
                        $record = (object)[
                            'date' => $dateString,
                            'shift' => $defaultShift,
                            'check_in' => null,
                            'check_out' => null,
                            'status' => 'Holiday',
                            'note' => $holidayRecord ? $holidayRecord->name : 'Weekend'
                        ];
                        $summary['holiday']++;
                    } else {
                        // 4. Default to Absent
                        $record = (object)[
                            'date' => $dateString,
                            'shift' => $defaultShift,
                            'check_in' => null,
                            'check_out' => null,
                            'status' => 'Absent',
                            'note' => 'No record'
                        ];
                        $summary['absent']++;
                    }
                }

                $fullMonthData->push($record);
            }
            $summary['total_days'] = $period->count();
        }
        return view('report.monthly_attendances', [
            'attendances' => $fullMonthData,
            'users' => $users,
            'summary' => $summary,
            'selectedMonth' => $selectedMonth,
            'selectedYear' => $selectedYear,
            'userId' => $userId
        ]);
    }

    public function monthlyAttendanceExcel(Request $request)
    {
        $month = $request->input('month', date('n'));
        $year = $request->input('year', date('Y'));
        $userId = $request->input('user_id');
        if ('employee' == \Str::slug(auth()->user()->getRoleNames()->first())) {
            $userId = auth()->id();
        }
        $user = User::with('shift')->find($userId);

        $dbAttendances = Attendance::with('shift', 'attendence_setting')
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->where('user_id', $userId)
            ->get()->keyBy('date');

        $fullMonthData = collect();
        $startDate = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $period = \Carbon\CarbonPeriod::create($startDate, $endDate);

        $defaultShift = $user->shift ?? $dbAttendances->first()?->shift;

        // Fetch Holidays that overlap with the selected month
        $holidays = \DB::table('holidays')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
            })->get();

        // Fetch Approved Leaves for this user
        $leaves = \DB::table('leaves')
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
            })->get();

        // Initialize counters
        $totalMinutes = 0;
        $present = 0;
        $absent = 0;
        $late = 0;
        $holiday = 0;
        $leave = 0;

        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $dayName = strtolower($date->format('l'));

            if (isset($dbAttendances[$dateString])) {
                $record = $dbAttendances[$dateString];
                $record->shift_name = $record->shift?->name ?? '-';
                $record->worked_display = "Missing Out";

                if ($record->check_in && $record->check_out) {
                    $start2 = \Carbon\Carbon::parse($dateString . ' ' . $record->check_in);
                    $end2 = \Carbon\Carbon::parse($dateString . ' ' . $record->check_out);

                    // Special case: Overnight shift (checkout next day)
                    if ($end2->lt($start2)) {
                        $end2->addDay();
                    }

                    $diffInMinutes2 = $start2->diffInMinutes($end2);
                    $totalMinutes += $diffInMinutes2;

                    // Format for the individual Excel row: "8h 30m"
                    $h2 = floor($diffInMinutes2 / 60);
                    $m2 = $diffInMinutes2 % 60;
                    $record->worked_display = "{$h2}h {$m2}m";
                }

                // --- CONSISTENT LATE CALCULATION ---
                if ($record->check_in && $record->attendence_setting) {
                    // Prepend dateString so Carbon doesn't use "today"
                    $checkInTime = \Carbon\Carbon::parse($dateString . ' ' . $record->check_in);

                    $minutes = (int) $record->attendence_setting->time_after_checkin;
                    $lateThreshold = \Carbon\Carbon::parse($dateString . ' ' . $user->shift->start_time)
                        ->addMinutes($minutes);

                    if ($checkInTime->gt($lateThreshold)) {
                        $record->status = 'Late';
                        $late++;
                        $present++; // Late people are physically present
                    } else {
                        $record->status = 'Present';
                        $present++;
                    }
                } else {
                    // If no check_in (e.g. manual status entries like Leave)
                    $currentStatus = strtolower($record->status);
                    if ($currentStatus == 'leave') $leave++;
                    elseif ($currentStatus == 'holiday') $holiday++;
                    else $absent++;
                }
            } elseif ($leaveRecord = $leaves->first(fn($l) => $dateString >= $l->start_date && $dateString <= $l->end_date)) {
                $record = (object)[
                    'date' => $dateString,
                    'employee_id' => $user->employee_id ?? '',
                    'shift_name' => $defaultShift?->name ?? '-',
                    'check_in' => null,
                    'check_out' => null,
                    'worked_display' => "--",
                    'status' => $leaveRecord->leave_time ? 'Early Leave' : 'Leave',
                    'note' => $leaveRecord->leave_time
                        ? 'Approved (from ' . \Carbon\Carbon::parse($leaveRecord->leave_time)->format('h:i A') . ')'
                        : 'Approved Leave'
                ];
                $leave++;
            } else {
                $holidayRecord = $holidays->first(fn($h) => $dateString >= $h->start_date && $dateString <= $h->end_date);
                $isWeekend = $defaultShift && in_array($dayName, $defaultShift->holidays ?? []);

                if ($holidayRecord || $isWeekend) {
                    $record = (object)[
                        'date' => $dateString,
                        'employee_id' => $user->employee_id ?? '',
                        'shift_name' => $defaultShift?->name ?? '-',
                        'check_in' => null,
                        'check_out' => null,
                        'worked_display' => "--",
                        'status' => 'Holiday',
                        'note' => $holidayRecord ? $holidayRecord->name : 'Weekend'
                    ];
                    $holiday++;
                } else {
                    // 4. Default to Absent
                    $record = (object)[
                        'date' => $dateString,
                        'employee_id' => $user->employee_id ?? '',
                        'shift_name' => $defaultShift?->name ?? '-',
                        'check_in' => null,
                        'check_out' => null,
                        'worked_display' => "--",
                        'status' => 'Absent',
                        'note' => 'No record'
                    ];
                    $absent++;
                }
            }
            $fullMonthData->push($record);
        }

        // --- APPEND SUMMARY ROWS ---
        // We put values in 'shift_name' so the Export's map() function finds them easily
        $summaryRow = function ($label, $value = '') {
            return (object)[
                'date' => $label,
                'shift_name' => $value,
                'check_in' => null,
                'check_out' => null,
                'worked_display' => "--",
                'status' => '',
                'note' => ''
            ];
        };

        $totalH = floor($totalMinutes / 60);
        $totalM = $totalMinutes % 60;
        $totalWorkedString = "{$totalH}H and {$totalM}M";

        $fullMonthData->push($summaryRow('')); // Spacer
        $fullMonthData->push($summaryRow('TOTAL SUMMARY'));
        $fullMonthData->push($summaryRow('Total Present:', $present ?? 0));
        $fullMonthData->push($summaryRow('Total Late:', $late ?? 0));
        $fullMonthData->push($summaryRow('Total Worked:', $totalWorkedString));
        $fullMonthData->push($summaryRow('Total Absent:', $absent ?? 0));
        $fullMonthData->push($summaryRow('Total Holiday:', $holiday ?? 0));
        $fullMonthData->push($summaryRow('Total Leave:', $leave ?? 0));

        return \Excel::download(new AttendanceExport($fullMonthData, $user), "Attendance_Report_{$userId}_{$month}_{$year}.xlsx");
    }

    public function monthlyAttendancePdf(Request $request)
    {
        $month = $request->input('month', date('n'));
        $year = $request->input('year', date('Y'));
        $userId = $request->input('user_id');
        if ('employee' == \Str::slug(auth()->user()->getRoleNames()->first())) {
            $userId = auth()->id();
        }
        $user = User::with('shift')->find($userId);
        // 1. Fetch Data (Same logic as Excel/Web)
        $dbAttendances = Attendance::with('shift', 'attendence_setting')
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->where('user_id', $userId)
            ->get()->keyBy('date');

        $startDate = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
        $defaultShift = $user->shift ?? $dbAttendances->first()?->shift;

        // Fetch Holidays that overlap with the selected month
        $holidays = \DB::table('holidays')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
            })->get();

        // Fetch Approved Leaves for this user
        $leaves = \DB::table('leaves')
            ->leftJoin('leave_types', 'leave_types.id', '=', 'leaves.leave_type_id')
            ->where('leaves.user_id', $userId)
            ->where('leaves.status', 'approved')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('leaves.start_date', [$startDate, $endDate])
                    ->orWhereBetween('leaves.end_date', [$startDate, $endDate]);
            })
            ->select('leaves.*', 'leave_types.exempts_early_out_deduction')
            ->get();

        $fullMonthData = collect();
        $summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'holiday' => 0, 'leave' => 0, 'late_minutes' => 0, 'early_out' => 0, 'early_minutes' => 0, 'overtime_days' => 0, 'overtime_minutes' => 0];

        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $dayName = strtolower($date->format('l'));

            if (isset($dbAttendances[$dateString])) {
                $record = $dbAttendances[$dateString];
                $approvedEarlyLeave = $this->findApprovedEarlyLeaveForDate($leaves, $dateString);
                // $record->shift_name = $defaultShift->name ?? '-';
                if ($record->check_in && $record->attendence_setting) {
                    $checkInTime = \Carbon\Carbon::parse($dateString . ' ' . $record->check_in);
                    $minutes = (int) $record->attendence_setting->time_after_checkin;
                    $lateThreshold = \Carbon\Carbon::parse($dateString . ' ' . $record->shift->start_time)
                        ->addMinutes($minutes);
                    $lateMinutes = (int) abs($checkInTime->diffInMinutes($lateThreshold, false));
                    if ($checkInTime->gt($lateThreshold)) {
                        $record->status = 'Late ' . $lateMinutes . ' mins';
                        $record->late_minutes = $lateMinutes;
                        $summary['late']++;
                        $summary['present']++;
                        $summary['late_minutes'] += $lateMinutes;
                    } else {
                        $record->status = 'Present';
                        $summary['present']++;
                    }

                    if ($approvedEarlyLeave && !$record->check_out) {
                        // Still checked in — show the approved time as a heads-up.
                        // If checkout later turns out normal/overtime, this gets left alone
                        // below and the original note is never overwritten.
                        $record->note = 'Approved Early Leave (until ' . \Carbon\Carbon::parse($approvedEarlyLeave->leave_time)->format('h:i A') . ')';
                    }

                    if ($record->check_out && $record->attendence_setting) {
                            $checkOutTime = \Carbon\Carbon::parse($dateString . ' ' . $record->check_out);

                            $minutes = (int) $record->attendence_setting->time_after_checkout;
                            // dd($record->shift->start_time);
                            $shiftEnd = \Carbon\Carbon::parse($dateString . ' ' . $record->shift->end_time);

                            // Thresholds (assuming $minutes is 10 for early and 60 for late)
                            $earlyThreshold = $shiftEnd->copy()->subMinutes(10); // 6:50 PM
                            $overtimeThreshold = $shiftEnd->copy()->addMinutes(60); // 8:00 PM

                            $earlyMinutes = 0;
                            $overtimeMinutes = 0;

                            if ($checkOutTime->lessThan($earlyThreshold)) {
                                // 1. LEFT TOO EARLY (Before 6:50)
                                $earlyMinutes = (int) abs($checkOutTime->diffInMinutes($earlyThreshold, false));
                            } elseif ($checkOutTime->greaterThanOrEqualTo($overtimeThreshold)) {
                                // 2. WORKED OVERTIME (8:00 or later)
                                // We calculate from 7:00 PM to give them the full hour + extra
                                $overtimeMinutes = (int) abs($checkOutTime->diffInMinutes($shiftEnd, false));
                            } else {
                                // 3. WITHIN GRACE PERIOD (6:50 to 7:59)
                                $earlyMinutes = 0;
                                $overtimeMinutes = 0;
                            }
                            if ($checkOutTime->lt($earlyThreshold) && $earlyMinutes > 0) {
                                if ($approvedEarlyLeave) {
                                    $record->note = 'Approved Early Leave (from ' . \Carbon\Carbon::parse($approvedEarlyLeave->leave_time)->format('h:i A') . ')';
                                    $summary['present']++; // Still present for most of the day, no deduction
                                } else {
                                    $record->out_status = 'Early Out ' . $earlyMinutes . ' mins';
                                    $summary['early_out']++;    // Increment Early Out
                                    $summary['present']++; // Also increment Present (as they are physically there)
                                    $summary['early_minutes'] += $earlyMinutes; // Accumulate early minutes
                                }
                            }
                            // Overtime handling days
                            if ($checkOutTime->gte($overtimeThreshold) && $overtimeMinutes > 0) {
                                $record->out_status = 'Overtime ' . $overtimeMinutes . ' mins';
                                $summary['overtime_minutes'] += $overtimeMinutes; // Accumulate overtime minutes
                                $summary['overtime_days'] = ($summary['overtime_days'] ?? 0) + 1; // Count days with overtime
                            }
                        } else {
                            $status = strtolower($record->status);
                            if (array_key_exists($status, $summary)) {
                                $summary[$status]++;
                            }
                        }
                } else {
                    $status = strtolower($record->status);
                    if (array_key_exists($status, $summary)) $summary[$status]++;
                }
            } elseif ($leaveRecord = $leaves->first(fn($l) => $dateString >= $l->start_date && $dateString <= $l->end_date)) {
                $record = (object)[
                    'date' => $dateString,
                    'shift_name' => $defaultShift->name ?? '-',
                    'check_in' => null,
                    'check_out' => null,
                    'status' => $leaveRecord->leave_time ? 'Early Leave' : 'Leave',
                    'note' => $leaveRecord->leave_time
                        ? 'Approved (from ' . \Carbon\Carbon::parse($leaveRecord->leave_time)->format('h:i A') . ')'
                        : 'Approved Leave'
                ];
                $summary['leave']++;
            } else {
                $holidayRecord = $holidays->first(fn($h) => $dateString >= $h->start_date && $dateString <= $h->end_date);
                $isWeekend = $defaultShift && in_array($dayName, $defaultShift->holidays ?? []);

                if ($holidayRecord || $isWeekend) {
                    $record = (object)[
                        'date' => $dateString,
                        'shift_name' => $defaultShift->name ?? '-',
                        'check_in' => null,
                        'check_out' => null,
                        'status' => 'Holiday',
                        'note' => $holidayRecord ? $holidayRecord->name : 'Weekend'
                    ];
                    $summary['holiday']++;
                } else {
                    // 4. Default to Absent
                    $record = (object)[
                        'date' => $dateString,
                        'shift_name' => $defaultShift->name ?? '-',
                        'check_in' => null,
                        'check_out' => null,
                        'status' => 'Absent',
                        'note' => 'No record'
                    ];
                    $summary['absent']++;
                }
            }
            $fullMonthData->push($record);
        }
        // 2. Generate PDF using a specific blade view
        $pdf = \PDF::loadView('report.monthly_attendances_pdf', [
            'attendances' => $fullMonthData,
            'user' => $user,
            'summary' => (object)$summary,
            'month' => $startDate->format('F'),
            'year' => $year
        ]);

        return $pdf->download("Attendance_Report_{$user->name}_{$month}.pdf");
    }
    public function attendanceDetails($role, $attendance_id, $date)
    {
        if ('employee' == \Str::slug(auth()->user()->getRoleNames()->first()) && (int) $attendance_id !== (int) auth()->id()) {
            abort(403);
        }

        $attendances = AttendanceLog::where('user_id', $attendance_id)
            ->where('date', $date)
            ->get();
        return view('report.attendance_details', compact('attendances'));
    }


    /**
     * GENERAL LEDGER REPORT
    */
    public function generalLedgerV2(Request $request)
    {
        $fromDate = $request->get('from_date', date('Y-m-01'));
        $toDate = $request->get('to_date', date('Y-m-d'));

        // Fetch accounts with items within the date range, and eager-load entries
        $accounts = \App\Models\Account::with(['items' => function ($query) use ($fromDate, $toDate) {
            $query->whereHas('journalEntry', function ($q) use ($fromDate, $toDate) {
                $q->whereBetween('date', [$fromDate, $toDate]);
            })->with('journalEntry');
        }])->orderBy('code')->get();

        // Process each account to calculate its specific opening balance
        $report = $accounts->map(function ($account) use ($fromDate) {
            // Sum all transactions BEFORE the from_date
            $preItems = \App\Models\JournalItem::where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($fromDate) {
                    $q->where('date', '<', $fromDate);
                })->get();

            $preDr = $preItems->sum('debit');
            $preCr = $preItems->sum('credit');

            // Calculate opening based on account type
            if (in_array($account->type, ['asset', 'expense'])) {
                $opening = $account->opening_balance + ($preDr - $preCr);
            } else {
                $opening = $account->opening_balance + ($preCr - $preDr);
            }

            return (object)[
                'account' => $account,
                'opening' => $opening,
                'items' => $account->items
            ];
        });

        return view('report.general_ledger', compact('report', 'fromDate', 'toDate'));
    }
    public function generalLedgerV1($role, Request $request)
    {
        $companyId = auth()->user()->company_id;
        $canViewAll = auth()->user()->can('view all account');

        $allAccountsQuery = Account::active();
        if (!$canViewAll && !empty($companyId)) {
            $allAccountsQuery->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')->orWhere('company_id', $companyId);
            });
        }
        $allAccounts = $allAccountsQuery->orderBy('code')->get();

        // Optional single-account filter — narrows the ledger to just one
        // account when picked, but shows every in-scope account by default.
        // This is the master ledger (every account, whole book); Account
        // Ledger is the dedicated single-account drill-down.
        $filterAccountId = $request->filled('account_id') ? $request->account_id : null;

        if ($filterAccountId && !$canViewAll && !empty($companyId)) {
            $targetAccount = Account::find($filterAccountId);
            abort_if(!$targetAccount || (!is_null($targetAccount->company_id) && (int) $targetAccount->company_id !== (int) $companyId), 403, 'This account belongs to a different company.');
        }

        $accountsData = $this->buildGeneralLedgerData($request, $companyId, $canViewAll, $filterAccountId);

        if ($request->filled('type')) {
            $accountsData = $accountsData->filter(fn($a) => $a['account']->type === $request->type)->values();
        }

        return view('report.general_ledgerV2', compact('accountsData', 'allAccounts'));
    }

    /**
     * Standalone printable ledger for one account — a dedicated page (no app
     * chrome, no print-media hacks) instead of reusing the interactive
     * screen with visibility:hidden tricks, matching how other invoices
     * (e.g. the Visa Sales voucher) are printed in this app.
     */
    public function generalLedgerPrint($role, Request $request)
    {
        $companyId = auth()->user()->company_id;
        $canViewAll = auth()->user()->can('view all account');

        abort_if(!$request->filled('account_id'), 404, 'No account selected to print.');

        if (!$canViewAll && !empty($companyId)) {
            $targetAccount = Account::find($request->account_id);
            abort_if(!$targetAccount || (!is_null($targetAccount->company_id) && (int) $targetAccount->company_id !== (int) $companyId), 403, 'This account belongs to a different company.');
        }

        $accountsData = $this->buildGeneralLedgerData($request, $companyId, $canViewAll, $request->account_id);
        $data = $accountsData->first();
        $company = auth()->user()->company;

        return view('report.general-ledger-print', compact('data', 'company', 'request'));
    }

    /**
     * Shared ledger-building logic (opening balance + running balance per
     * transaction) used by both the interactive General Ledger screen and
     * its standalone print view.
     */
    private function buildGeneralLedgerData(Request $request, $companyId, $canViewAll, $accountId = null)
    {
        $query = Account::with(['items' => function ($q) use ($request, $companyId, $canViewAll) {
            $q->with('journalEntry')
                ->whereHas('journalEntry', function ($q2) use ($request, $companyId, $canViewAll) {
                    if (!$canViewAll && !empty($companyId)) {
                        $q2->where('company_id', $companyId);
                    }
                    if ($request->filled('date_from')) {
                        $q2->whereDate('date', '>=', $request->date_from);
                    }
                    if ($request->filled('date_to')) {
                        $q2->whereDate('date', '<=', $request->date_to);
                    }
                })
                ->orderBy('created_at');
        }])->where('status', 1);

        if (!$canViewAll && !empty($companyId)) {
            $query->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')->orWhere('company_id', $companyId);
            });
        }

        if ($accountId) {
            $query->where('id', $accountId);
        }

        $accounts = $query->orderBy('code')->get();

        // Calculate opening balance per account
        // (sum of all transactions BEFORE date_from)
        return $accounts->map(function ($account) use ($request, $companyId, $canViewAll) {
            $openingDebit  = 0;
            $openingCredit = 0;

            if ($request->filled('date_from')) {
                $openingItems = $account->items()
                    ->whereHas('journalEntry', function ($q) use ($request, $companyId, $canViewAll) {
                        if (!$canViewAll && !empty($companyId)) {
                            $q->where('company_id', $companyId);
                        }
                        $q->whereDate('date', '<', $request->date_from);
                    })->get();

                $openingDebit  = $openingItems->sum('debit');
                $openingCredit = $openingItems->sum('credit');
            } else {
                $openingDebit  = $account->opening_balance;
            }

            $openingBalance = $openingDebit - $openingCredit;

            // Build rows with running balance
            $runningBalance = $openingBalance;
            $rows = $account->items->map(function ($item) use (&$runningBalance) {
                $runningBalance += $item->debit - $item->credit;
                return [
                    'date'        => $item->journalEntry->date,
                    'reference'   => $item->journalEntry->reference,
                    'source'      => $item->journalEntry->source,
                    'description' => $item->journalEntry->description,
                    'note'        => $item->note,
                    'debit'       => $item->debit,
                    'credit'      => $item->credit,
                    'balance'     => $runningBalance,
                ];
            });

            return [
                'account'         => $account,
                'opening_balance' => $openingBalance,
                'total_debit'     => $account->items->sum('debit'),
                'total_credit'    => $account->items->sum('credit'),
                'closing_balance' => $runningBalance,
                'rows'            => $rows,
            ];
        })->filter(fn($a) => $a['rows']->count() > 0 || $accountId);
    }


    /**
     * TRIAL BALANCE REPORT
    */
    public function trialBalanceV2(Request $request)
    {
        $date = $request->get('date', date('Y-m-d'));

        $accounts = \App\Models\Account::with(['items' => function ($query) use ($date) {
            $query->whereHas('journalEntry', function ($q) use ($date) {
                $q->where('date', '<=', $date);
            });
        }])->get();

        $report = $accounts->map(function ($account) {
            $totalDr = $account->items->sum('debit');
            $totalCr = $account->items->sum('credit');

            $debitBalance = 0;
            $creditBalance = 0;

            // Assets and Expenses naturally have Debit balances
            if (in_array($account->type, ['asset', 'expense'])) {
                $net = $account->opening_balance + ($totalDr - $totalCr);
                if ($net >= 0) $debitBalance = $net;
                else $creditBalance = abs($net);
            }
            // Liabilities, Equity, and Income naturally have Credit balances
            else {
                $net = $account->opening_balance + ($totalCr - $totalDr);
                if ($net >= 0) $creditBalance = $net;
                else $debitBalance = abs($net);
            }

            return (object) [
                'code' => $account->code,
                'name' => $account->name,
                'debit' => $debitBalance,
                'credit' => $creditBalance,
            ];
        })->filter(fn($row) => $row->debit != 0 || $row->credit != 0); // Hide zero-balance accounts

        return view('report.trial_balance', compact('report', 'date'));
    }    
    public function trialBalanceV1($role, Request $request)
    {
        $companyId = auth()->user()->company_id;
        $canViewAll = auth()->user()->can('view all account');

        $accountsQuery = Account::with(['items' => function ($q) use ($request, $companyId, $canViewAll) {
            $q->whereHas('journalEntry', function ($q2) use ($request, $companyId, $canViewAll) {
                if (!$canViewAll && !empty($companyId)) {
                    $q2->where('company_id', $companyId);
                }
                if ($request->filled('date_from')) {
                    $q2->whereDate('date', '>=', $request->date_from);
                }
                if ($request->filled('date_to')) {
                    $q2->whereDate('date', '<=', $request->date_to);
                }
            });
        }])->where('status', 1);

        if (!$canViewAll && !empty($companyId)) {
            $accountsQuery->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')->orWhere('company_id', $companyId);
            });
        }

        $accounts = $accountsQuery->orderBy('code')->get();

        $rows = $accounts->map(function ($account) {
            $totalDebit  = $account->items->sum('debit');
            $totalCredit = $account->items->sum('credit');
            $netBalance  = $totalDebit - $totalCredit;

            return [
                'account'      => $account,
                'total_debit'  => $totalDebit,
                'total_credit' => $totalCredit,
                'net_debit'    => $netBalance > 0 ? $netBalance  : 0,  // debit side
                'net_credit'   => $netBalance < 0 ? abs($netBalance) : 0, // credit side
            ];
        })->filter(fn($r) => $r['total_debit'] > 0 || $r['total_credit'] > 0);

        $grandTotalDebit   = $rows->sum('net_debit');
        $grandTotalCredit  = $rows->sum('net_credit');
        $isBalanced        = abs($grandTotalDebit - $grandTotalCredit) < 0.01;

        // Group by type for better readability
        $grouped = $rows->groupBy(fn($r) => $r['account']->type);

        return view('report.trial_balanceV2', compact('grouped', 'grandTotalDebit', 'grandTotalCredit', 'isBalanced'));
    }


    /**
     * PROFIT LOSS REPORT
    */
    public function profitLossV2(Request $request)
    {
        $fromDate = $request->get('from_date', date('Y-m-01'));
        $toDate = $request->get('to_date', date('Y-m-d'));

        // Fetch Income and Expense accounts with their items within the date range
        $accounts = \App\Models\Account::whereIn('type', ['income', 'expense'])
            ->with(['items' => function ($query) use ($fromDate, $toDate) {
                $query->whereHas('journalEntry', function ($q) use ($fromDate, $toDate) {
                    $q->whereBetween('date', [$fromDate, $toDate]);
                });
            }])->get();

        $reportData = $accounts->map(function ($account) {
            $debits = $account->items->sum('debit');
            $credits = $account->items->sum('credit');

            // Income: Credits - Debits | Expense: Debits - Credits
            $balance = ($account->type === 'income') ? ($credits - $debits) : ($debits - $credits);

            return (object) [
                'name' => $account->name,
                'type' => $account->type,
                'balance' => $balance
            ];
        });

        $incomeItems = $reportData->where('type', 'income');
        $expenseItems = $reportData->where('type', 'expense');

        $totalIncome = $incomeItems->sum('balance');
        $totalExpense = $expenseItems->sum('balance');
        $netProfit = $totalIncome - $totalExpense;

        return view('report.profit_loss', compact('incomeItems', 'expenseItems', 'totalIncome', 'totalExpense', 'netProfit', 'fromDate', 'toDate'));
    }
    public function profitLossV1(Request $request)
    {
        $dateFrom = $request->date_from;
        $dateTo   = $request->date_to;
        $companyId = auth()->user()->company_id;
        $canViewAll = auth()->user()->can('view all account');

        $incomeTypes  = ['income'];
        $expenseTypes = ['expense'];

        $accounts = Account::with(['items' => function ($q) use ($dateFrom, $dateTo, $companyId, $canViewAll) {
            $q->whereHas('journalEntry', function ($q2) use ($dateFrom, $dateTo, $companyId, $canViewAll) {
                if (!$canViewAll && !empty($companyId)) {
                    $q2->where('company_id', $companyId);
                }
                if ($dateFrom) $q2->whereDate('date', '>=', $dateFrom);
                if ($dateTo)   $q2->whereDate('date', '<=', $dateTo);
            });
        }])->where('status', 1)
            ->whereIn('type', ['income', 'expense'])
            ->orderBy('code')
            ->get();

        $incomeAccounts  = $accounts->where('type', 'income')->map(function ($acc) {
            // For income: credit increases, debit decreases
            $credit = $acc->items->sum('credit');
            $debit  = $acc->items->sum('debit');
            $net    = $credit - $debit;
            return [
                'account' => $acc,
                'credit'  => $credit,
                'debit'   => $debit,
                'net'     => $net,
            ];
        })->filter(fn($r) => $r['credit'] > 0 || $r['debit'] > 0);

        $expenseAccounts = $accounts->where('type', 'expense')->map(function ($acc) {
            // For expense: debit increases, credit decreases
            $debit  = $acc->items->sum('debit');
            $credit = $acc->items->sum('credit');
            $net    = $debit - $credit;
            return [
                'account' => $acc,
                'debit'   => $debit,
                'credit'  => $credit,
                'net'     => $net,
            ];
        })->filter(fn($r) => $r['debit'] > 0 || $r['credit'] > 0);

        $totalIncome   = $incomeAccounts->sum('net');
        $totalExpense  = $expenseAccounts->sum('net');
        $netProfit     = $totalIncome - $totalExpense;

        return view('report.profit_lossV2', compact(
            'incomeAccounts',
            'expenseAccounts',
            'totalIncome',
            'totalExpense',
            'netProfit'
        ));
    }


    /**
     * BALANCE SHEET REPORT
    */
    public function balanceSheetV2(Request $request)
    {
        $date = $request->get('date', date('Y-m-d'));

        // Retrieve accounts with their aggregated debits and credits up to the date
        $accounts = \App\Models\Account::with(['items' => function ($query) use ($date) {
            $query->whereHas('journalEntry', function ($q) use ($date) {
                $q->where('date', '<=', $date);
            });
        }])->get();

        $data = $accounts->map(function ($account) {
            $debits = $account->items->sum('debit');
            $credits = $account->items->sum('credit');

            // Assets/Expenses: Increase with Debit (+)
            // Liabilities/Equity/Income: Increase with Credit (+)
            if (in_array($account->type, ['asset', 'expense'])) {
                $balance = $account->opening_balance + ($debits - $credits);
            } else {
                $balance = $account->opening_balance + ($credits - $debits);
            }

            return (object) [
                'name' => $account->name,
                'type' => $account->type,
                'balance' => $balance
            ];
        });

        // Categorize data for the report
        $assets = $data->where('type', 'asset');
        $liabilities = $data->where('type', 'liability');
        $equity = $data->where('type', 'equity');

        // Calculate Net Profit for the current period (Income - Expenses)
        $netProfit = $data->where('type', 'income')->sum('balance') - $data->where('type', 'expense')->sum('balance');

        return view('report.balance_sheet', compact('assets', 'liabilities', 'equity', 'netProfit', 'date'));
    }
    public function balanceSheetV1(Request $request)
    {
        $asOf = $request->as_of ?? now()->toDateString();
        $companyId = auth()->user()->company_id;
        $canViewAll = auth()->user()->can('view all account');

        $accountsQuery = Account::with(['items' => function ($q) use ($asOf, $companyId, $canViewAll) {
            $q->whereHas('journalEntry', function ($q2) use ($asOf, $companyId, $canViewAll) {
                if (!$canViewAll && !empty($companyId)) {
                    $q2->where('company_id', $companyId);
                }
                $q2->whereDate('date', '<=', $asOf);
            });
        }])->where('status', 1)
            ->whereIn('type', ['asset', 'liability', 'equity']);

        if (!$canViewAll && !empty($companyId)) {
            $accountsQuery->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')->orWhere('company_id', $companyId);
            });
        }

        $accounts = $accountsQuery->orderBy('code')->get();

        // Also pull net profit from income/expense up to as_of date
        // and fold it into retained earnings
        $incomeAccounts  = Account::with(['items' => function ($q) use ($asOf, $companyId, $canViewAll) {
            $q->whereHas('journalEntry', function ($q2) use ($asOf, $companyId, $canViewAll) {
                if (!$canViewAll && !empty($companyId)) {
                    $q2->where('company_id', $companyId);
                }
                $q2->whereDate('date', '<=', $asOf);
            });
        }])->where('status', 1)->where('type', 'income')->get();

        $expenseAccounts = Account::with(['items' => function ($q) use ($asOf, $companyId, $canViewAll) {
            $q->whereHas('journalEntry', function ($q2) use ($asOf, $companyId, $canViewAll) {
                if (!$canViewAll && !empty($companyId)) {
                    $q2->where('company_id', $companyId);
                }
                $q2->whereDate('date', '<=', $asOf);
            });
        }])->where('status', 1)->where('type', 'expense')->get();

        $totalIncome  = $incomeAccounts->sum(fn($a)  => $a->items->sum('credit') - $a->items->sum('debit'));
        $totalExpense = $expenseAccounts->sum(fn($a) => $a->items->sum('debit')  - $a->items->sum('credit'));
        $netProfit    = $totalIncome - $totalExpense;

        // Build account rows
        $buildRows = function ($type) use ($accounts) {
            return $accounts->where('type', $type)->map(function ($acc) {
                $debit  = $acc->items->sum('debit');
                $credit = $acc->items->sum('credit');

                // Asset: debit increases  → net = debit - credit
                // Liability/Equity: credit increases → net = credit - debit
                $net = match ($acc->type) {
                    'asset'             => $debit - $credit,
                    'liability', 'equity' => $credit - $debit,
                };

                return [
                    'account'       => $acc,
                    'total_debit'   => $debit,
                    'total_credit'  => $credit,
                    'net'           => $net,
                ];
            })->filter(fn($r) => abs($r['net']) > 0);
        };

        $assetRows     = $buildRows('asset');
        $liabilityRows = $buildRows('liability');
        $equityRows    = $buildRows('equity');

        $totalAssets      = $assetRows->sum('net');
        $totalLiabilities = $liabilityRows->sum('net');
        $totalEquity      = $equityRows->sum('net') + $netProfit; // equity + retained profit
        $totalLiabEquity  = $totalLiabilities + $totalEquity;
        $isBalanced       = abs($totalAssets - $totalLiabEquity) < 0.01;

        return view('report.balance_sheetV2', compact(
            'assetRows',
            'liabilityRows',
            'equityRows',
            'totalAssets',
            'totalLiabilities',
            'totalEquity',
            'totalLiabEquity',
            'netProfit',
            'isBalanced',
            'asOf'
        ));
    }


    /**
     * ACCOUNT LEDGER REPORT
    */
    public function accountLedgerV2(Request $request)
    {
        $accountId = $request->get('account_id');
        $fromDate = $request->get('from_date', date('Y-m-01')); // Default to start of month
        $toDate = $request->get('to_date', date('Y-m-d'));

        $accounts = \App\Models\Account::orderBy('name')->get();
        $selectedAccount = null;
        $ledgerItems = [];
        $openingBalance = 0;

        if ($accountId) {
            $selectedAccount = \App\Models\Account::findOrFail($accountId);

            // 1. Calculate Opening Balance (All items BEFORE from_date)
            $preItems = \App\Models\JournalItem::where('account_id', $accountId)
                ->whereHas('journalEntry', function ($q) use ($fromDate) {
                    $q->where('date', '<', $fromDate);
                })->get();

            $preDebits = $preItems->sum('debit');
            $preCredits = $preItems->sum('credit');

            // Normal balance logic
            if (in_array($selectedAccount->type, ['asset', 'expense'])) {
                $openingBalance = $selectedAccount->opening_balance + ($preDebits - $preCredits);
            } else {
                $openingBalance = $selectedAccount->opening_balance + ($preCredits - $preDebits);
            }

            // 2. Get Ledger Items for the period
            $ledgerItems = \App\Models\JournalItem::with('journalEntry')
                ->where('account_id', $accountId)
                ->whereHas('journalEntry', function ($q) use ($fromDate, $toDate) {
                    $q->whereBetween('date', [$fromDate, $toDate]);
                })
                ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
                ->orderBy('journal_entries.date', 'asc')
                ->orderBy('journal_entries.created_at', 'asc')
                ->select('journal_items.*')
                ->get();
        }

        return view('report.account_ledger', compact('accounts', 'selectedAccount', 'ledgerItems', 'openingBalance', 'fromDate', 'toDate'));
    }
    public function accountLedgerV1(Request $request)
    {
        $companyId = auth()->user()->company_id;

        $allAccountsQuery = Account::active();
        if (!empty($companyId)) {
            $allAccountsQuery->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')->orWhere('company_id', $companyId);
            });
        }
        $allAccounts = $allAccountsQuery->orderBy('code')->get();
        $account     = null;
        $rows        = collect();
        $openingBalance  = 0;
        $closingBalance  = 0;
        $totalDebit      = 0;
        $totalCredit     = 0;

        if ($request->filled('account_id')) {
            $account = Account::findOrFail($request->account_id);

            // Opening balance = all transactions BEFORE date_from
            if ($request->filled('date_from')) {
                $beforeItems = JournalItem::where('account_id', $account->id)
                    ->whereHas(
                        'journalEntry',
                        fn($q) =>
                        $q->where('company_id', $companyId)->whereDate('date', '<', $request->date_from)
                    )
                    ->get();

                $openingBalance = $beforeItems->sum('debit') - $beforeItems->sum('credit');
            } else {
                $openingBalance = (float) $account->opening_balance;
            }

            // Fetch items within the selected period
            $items = JournalItem::with('journalEntry')
                ->where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($request, $companyId) {
                    $q->where('company_id', $companyId);
                    if ($request->filled('date_from')) {
                        $q->whereDate('date', '>=', $request->date_from);
                    }
                    if ($request->filled('date_to')) {
                        $q->whereDate('date', '<=', $request->date_to);
                    }
                })
                ->orderBy(
                    JournalEntry::select('date')
                        ->whereColumn('journal_entries.id', 'journal_items.journal_entry_id')
                        ->limit(1)
                )
                ->orderBy('id')
                ->get();

            $totalDebit  = $items->sum('debit');
            $totalCredit = $items->sum('credit');

            // Build rows with running balance
            $runningBalance = $openingBalance;
            $rows = $items->map(function ($item) use (&$runningBalance) {
                $runningBalance += $item->debit - $item->credit;
                return [
                    'date'        => $item->journalEntry->date,
                    'reference'   => $item->journalEntry->reference,
                    'source'      => $item->journalEntry->source,
                    'source_id'   => $item->journalEntry->source_id,
                    'description' => $item->journalEntry->description,
                    'note'        => $item->note,
                    'debit'       => $item->debit,
                    'credit'      => $item->credit,
                    'balance'     => $runningBalance,
                ];
            });

            $closingBalance = $runningBalance;
        }

        return view('report.account_ledgerV2', compact(
            'allAccounts',
            'account',
            'rows',
            'openingBalance',
            'closingBalance',
            'totalDebit',
            'totalCredit'
        ));
    }


    /**
     * ACCOUNT STATEMENT REPORT
    */
    public function accountStatementV2(Request $request)
    {
        $accountId = $request->get('account_id');
        $fromDate = $request->get('from_date', date('Y-m-01'));
        $toDate = $request->get('to_date', date('Y-m-d'));

        // Fetch only accounts that are linked to Banks, Portals, or Loans
        $accounts = \App\Models\Account::whereIn('type', ['asset', 'liability'])
            ->orderBy('name')
            ->get();

        $selectedAccount = null;
        $entries = [];
        $openingBalance = 0;

        if ($accountId) {
            $selectedAccount = \App\Models\Account::findOrFail($accountId);

            // 1. Opening Balance Calculation
            $prevData = \App\Models\JournalItem::where('account_id', $accountId)
                ->whereHas('journalEntry', function ($q) use ($fromDate) {
                    $q->where('date', '<', $fromDate);
                })->get();

            if (in_array($selectedAccount->type, ['asset', 'expense'])) {
                $openingBalance = $selectedAccount->opening_balance + ($prevData->sum('debit') - $prevData->sum('credit'));
            } else {
                $openingBalance = $selectedAccount->opening_balance + ($prevData->sum('credit') - $prevData->sum('debit'));
            }

            // 2. Statement Entries
            $entries = \App\Models\JournalItem::with('journalEntry')
                ->where('account_id', $accountId)
                ->whereHas('journalEntry', function ($q) use ($fromDate, $toDate) {
                    $q->whereBetween('date', [$fromDate, $toDate]);
                })
                ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
                ->orderBy('journal_entries.date', 'asc')
                ->select('journal_items.*')
                ->get();
        }

        return view('report.account_statement', compact('accounts', 'selectedAccount', 'entries', 'openingBalance', 'fromDate', 'toDate'));
    }
    public function accountStatementV1(Request $request)
    {
        $userType = $request->user_type ?? 'customer'; // customer or supplier
        $companyId = auth()->user()->company_id;

        // Load users based on type. Customers are company-scoped; Suppliers
        // have no company_id column yet (shared across companies today), so
        // that side can't be restricted until that schema gap is closed.
        $users = match ($userType) {
            'supplier' => \App\Models\Supplier::orderBy('name')->get(),
            default    => \App\Models\Customer::where('company_id', $companyId)->orderBy('name')->get(),
        };

        $user            = null;
        $rows            = collect();
        $openingBalance  = 0;
        $closingBalance  = 0;
        $totalDebit      = 0;
        $totalCredit     = 0;

        if ($request->filled('user_id')) {

            // Opening balance = all transactions BEFORE date_from
            $txQuery = Transaction::where('user_id', $request->user_id)
                ->where('user_type', $userType);

            if ($request->filled('date_from')) {
                $beforeTx       = (clone $txQuery)
                    ->whereDate('payment_date', '<', $request->date_from)
                    ->get();
                // For customer: credit = money in (sale), debit = refund out
                // For supplier: debit = money out (purchase), credit = refund in
                if ($userType === 'customer') {
                    $openingBalance = $beforeTx->sum('credit') - $beforeTx->sum('debit');
                } else {
                    $openingBalance = $beforeTx->sum('debit') - $beforeTx->sum('credit');
                }
            }

            // Fetch transactions within period
            $periodTx = (clone $txQuery);
            if ($request->filled('date_from')) {
                $periodTx->whereDate('payment_date', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $periodTx->whereDate('payment_date', '<=', $request->date_to);
            }

            // Filter by source type
            if ($request->filled('source')) {
                $periodTx->where('type', $request->source);
            }

            $transactions = $periodTx->orderBy('payment_date')->orderBy('id')->get();

            $totalDebit  = $transactions->sum('debit');
            $totalCredit = $transactions->sum('credit');

            // Build rows with running balance
            $runningBalance = $openingBalance;
            $rows = $transactions->map(function ($tx) use (&$runningBalance, $userType) {
                if ($userType === 'customer') {
                    $runningBalance += $tx->credit - $tx->debit;
                } else {
                    $runningBalance += $tx->debit - $tx->credit;
                }

                return [
                    'date'           => $tx->payment_date,
                    'type'           => $tx->type,
                    'reference_no'   => $tx->reference_no,
                    'payment_method' => $tx->payment_method,
                    'invoice_id'     => $tx->invoice_id,
                    'debit'          => $tx->debit,
                    'credit'         => $tx->credit,
                    'note'           => $tx->note,
                    'balance'        => $runningBalance,
                ];
            });

            $closingBalance = $runningBalance;

            $user = match ($userType) {
                'supplier' => \App\Models\Supplier::find($request->user_id),
                default    => \App\Models\Customer::find($request->user_id),
            };
        }

        $sources = ['sale', 'purchase', 'ticket_sale', 'ticket_purchase', 'expense', 'salary'];

        return view('report.account_statementV2', compact(
            'users',
            'user',
            'userType',
            'rows',
            'openingBalance',
            'closingBalance',
            'totalDebit',
            'totalCredit',
            'sources'
        ));
    }


    /**
     * JOURNAL ENTRIES REPORT
    */
    public function journalEntriesV2(Request $request)
    {
        $fromDate = $request->get('from_date', date('Y-m-01'));
        $toDate = $request->get('to_date', date('Y-m-d'));
        $source = $request->get('source');

        $query = \App\Models\JournalEntry::with(['journalItems.account'])
            ->whereBetween('date', [$fromDate, $toDate]);

        if ($source) {
            $query->where('source', $source);
        }

        $entries = $query->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return view('report.journal_entries', compact('entries', 'fromDate', 'toDate'));
    }
    public function journalEntriesV1(Request $request)
    {
        $query = JournalEntry::with(['items.account', 'createdBy'])
            ->where('company_id', auth()->user()->company_id);

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }
        if ($request->filled('source') && $request->source !== '') {
            $query->where('source', $request->source);
        }
        if ($request->filled('reference')) {
            $query->where('reference', 'like', '%' . $request->reference . '%');
        }

        $journals = $query->orderByDesc('date')->orderByDesc('id')->paginate(20)->withQueryString();

        // Totals across all filtered entries (unpaginated)
        $totalsQuery = (clone $query->getQuery());
        $allItems    = JournalItem::whereIn(
            'journal_entry_id',
            JournalEntry::where('company_id', auth()->user()->company_id)
                ->when($request->date_from,  fn($q) => $q->whereDate('date', '>=', $request->date_from))
                ->when($request->date_to,    fn($q) => $q->whereDate('date', '<=', $request->date_to))
                ->when($request->source,     fn($q) => $q->where('source', $request->source))
                ->when($request->reference,  fn($q) => $q->where('reference', 'like', '%' . $request->reference . '%'))
                ->pluck('id')
        )->get();

        $grandTotalDebit  = $allItems->sum('debit');
        $grandTotalCredit = $allItems->sum('credit');
        $isBalanced       = abs($grandTotalDebit - $grandTotalCredit) < 0.01;

        $sources = ['manual', 'sale', 'purchase', 'expense', 'salary', 'loan', 'ticket_sale', 'ticket_purchase'];

        return view('report.journal_entriesV2', compact(
            'journals',
            'grandTotalDebit',
            'grandTotalCredit',
            'isBalanced',
            'sources'
        ));
    }

    /**
     * ACCOUNT BALANCE REPORT
    */
    public function accountBalancesV2(Request $request)
    {
        $date = $request->get('date', date('Y-m-d'));

        $accounts = \App\Models\Account::with(['items' => function ($query) use ($date) {
            $query->whereHas('journalEntry', function ($q) use ($date) {
                $q->where('date', '<=', $date);
            });
        }])
            ->orderBy('type')
            ->orderBy('code')
            ->get();

        $report = $accounts->map(function ($account) {
            $totalDebit = $account->items->sum('debit');
            $totalCredit = $account->items->sum('credit');

            if (in_array($account->type, ['asset', 'expense'])) {
                $balance = $account->opening_balance + ($totalDebit - $totalCredit);
            } else {
                $balance = $account->opening_balance + ($totalCredit - $totalDebit);
            }

            return (object) [
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'balance' => $balance
            ];
        });

        return view('report.account_balance', compact('report', 'date'));
    }
    public function accountBalancesV1(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $canViewAll = auth()->user()->can('view all account');

        $query = Account::with(['items' => function ($q) use ($request, $companyId, $canViewAll) {
            $q->whereHas('journalEntry', function ($q2) use ($request, $companyId, $canViewAll) {
                if (!$canViewAll && !empty($companyId)) {
                    $q2->where('company_id', $companyId);
                }
                if ($request->filled('date_from')) {
                    $q2->whereDate('date', '>=', $request->date_from);
                }
                if ($request->filled('date_to')) {
                    $q2->whereDate('date', '<=', $request->date_to);
                }
            });
        }])->where('status', 1);

        if (!$canViewAll && !empty($companyId)) {
            $query->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')->orWhere('company_id', $companyId);
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('code', 'like', '%' . $request->search . '%');
            });
        }

        $accounts = $query->orderBy('type')->orderBy('code')->get();

        $rows = $accounts->map(function ($account) use ($request, $companyId, $canViewAll) {

            $periodDebit  = $account->items->sum('debit');
            $periodCredit = $account->items->sum('credit');

            // Opening: all transactions before date_from
            $openingDebit  = 0;
            $openingCredit = 0;
            if ($request->filled('date_from')) {
                $openingItems  = $account->items()
                    ->whereHas(
                        'journalEntry',
                        function ($q) use ($request, $companyId, $canViewAll) {
                            if (!$canViewAll && !empty($companyId)) {
                                $q->where('company_id', $companyId);
                            }
                            $q->whereDate('date', '<', $request->date_from);
                        }
                    )->get();
                $openingDebit  = $openingItems->sum('debit');
                $openingCredit = $openingItems->sum('credit');
            } else {
                $openingDebit = (float) $account->opening_balance;
            }

            // Net balance based on account type
            // Asset/Expense: debit increases → net = debit - credit
            // Liability/Equity/Income: credit increases → net = credit - debit
            $openingBalance = in_array($account->type, ['asset', 'expense'])
                ? $openingDebit - $openingCredit
                : $openingCredit - $openingDebit;

            $periodNet = in_array($account->type, ['asset', 'expense'])
                ? $periodDebit - $periodCredit
                : $periodCredit - $periodDebit;

            $closingBalance = $openingBalance + $periodNet;

            return [
                'account'         => $account,
                'opening_balance' => $openingBalance,
                'period_debit'    => $periodDebit,
                'period_credit'   => $periodCredit,
                'closing_balance' => $closingBalance,
            ];
        });

        // Group by type
        $grouped   = $rows->groupBy(fn($r) => $r['account']->type);
        $typeOrder = ['asset', 'liability', 'equity', 'income', 'expense'];

        // Grand totals
        $grandTotals = [
            'opening' => $rows->sum('opening_balance'),
            'debit'   => $rows->sum('period_debit'),
            'credit'  => $rows->sum('period_credit'),
            'closing' => $rows->sum('closing_balance'),
        ];

        // Type subtotals
        $typeTotals = [];
        foreach ($typeOrder as $type) {
            $typeRows = $grouped->get($type, collect());
            $typeTotals[$type] = [
                'opening' => $typeRows->sum('opening_balance'),
                'debit'   => $typeRows->sum('period_debit'),
                'credit'  => $typeRows->sum('period_credit'),
                'closing' => $typeRows->sum('closing_balance'),
                'count'   => $typeRows->count(),
            ];
        }

        return view('report.account_balanceV2', compact(
            'grouped',
            'typeOrder',
            'grandTotals',
            'typeTotals',
            'rows'
        ));
    }

    /**
     * Check whether the given date falls inside an approved leave that is allowed
     * to waive the automatic early-checkout label (e.g. an approved "Early Leave" request).
     *
     * @param  \Illuminate\Support\Collection  $leaves  Approved leaves for the user this month
     * @param  string  $dateString  The attendance date being checked, format "Y-m-d"
     * @return object|null  The matching leave record, or null if none was found
     */
    private function findApprovedEarlyLeaveForDate($leaves, string $dateString)
    {
        foreach ($leaves as $leave) {
            // Only leave types marked "exempts_early_out_deduction" (e.g. Early Leave) qualify
            if (!$leave->exempts_early_out_deduction) {
                continue;
            }

            $dateIsWithinLeavePeriod = $dateString >= $leave->start_date && $dateString <= $leave->end_date;

            if ($dateIsWithinLeavePeriod) {
                return $leave;
            }
        }

        return null;
    }

}
