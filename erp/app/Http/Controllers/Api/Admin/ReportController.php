<?php

namespace App\Http\Controllers\Api\Admin;

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

class ReportController extends Controller
{
    public function monthly_attendances(Request $request)
    {
        $users = User::whereNotNull('company_id')->role('employee')->where('status', 'active')->get();
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
                ->where('user_id', $userId)
                ->where('status', 'approved')
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate]);
                })->get();

            foreach ($period as $date) {
                $dateString = $date->format('Y-m-d');
                $dayName = strtolower($date->format('l'));

                if (isset($dbAttendances[$dateString])) {
                    $record = $dbAttendances[$dateString];

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
                                $record->out_status = 'Early Out ' . $earlyMinutes . ' mins';
                                $summary['early_out']++;    // Increment Early Out
                                $summary['present']++; // Also increment Present (as they are physically there)
                                $summary['early_minutes'] += $earlyMinutes; // Accumulate early minutes
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
                        'status' => 'Leave',
                        'note' => $leaveRecord->leave_time
                            ? 'Approved Early Leave (from ' . \Carbon\Carbon::parse($leaveRecord->leave_time)->format('h:i A') . ')'
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

        return response()->json([
            'success' => true,
            'message' => 'Monthly attendance data retrieved successfully',
            'data' => [
                'attendances' => $fullMonthData,
                'users' => $users,
                'summary' => $summary,
                'selectedMonth' => $selectedMonth,
                'selectedYear' => $selectedYear,
            ]
        ]);
    }

    public function monthlyAttendanceExcel(Request $request)
    {
        $month = $request->input('month', date('n'));
        $year = $request->input('year', date('Y'));
        $userId = $request->input('user_id');
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
                    'status' => 'Leave',
                    'note' => $leaveRecord->leave_time
                        ? 'Approved Early Leave (from ' . \Carbon\Carbon::parse($leaveRecord->leave_time)->format('h:i A') . ')'
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
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
            })->get();

        $fullMonthData = collect();
        $summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'holiday' => 0, 'leave' => 0, 'late_minutes' => 0, 'early_out' => 0, 'early_minutes' => 0, 'overtime_days' => 0, 'overtime_minutes' => 0];

        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $dayName = strtolower($date->format('l'));

            if (isset($dbAttendances[$dateString])) {
                $record = $dbAttendances[$dateString];
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
                                $record->out_status = 'Early Out ' . $earlyMinutes . ' mins';
                                $summary['early_out']++;    // Increment Early Out
                                $summary['present']++; // Also increment Present (as they are physically there)
                                $summary['early_minutes'] += $earlyMinutes; // Accumulate early minutes
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
                    'status' => 'Leave',
                    'note' => $leaveRecord->leave_time
                        ? 'Approved Early Leave (from ' . \Carbon\Carbon::parse($leaveRecord->leave_time)->format('h:i A') . ')'
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

        $attendances = AttendanceLog::where('user_id', $attendance_id)
            ->where('date', $date)
            ->get();
        return view('report.attendance_details', compact('attendances'));
    }


    /**
     * GENERAL LEDGER REPORT
    */
    public function generalLedger(Request $request)
    {
        $query = Account::with(['items' => function ($q) use ($request) {
            $q->with('journalEntry')
                ->whereHas('journalEntry', function ($q2) use ($request) {
                    if ($request->filled('date_from')) {
                        $q2->whereDate('date', '>=', $request->date_from);
                    }
                    if ($request->filled('date_to')) {
                        $q2->whereDate('date', '<=', $request->date_to);
                    }
                })
                ->orderBy('created_at');
        }])->where('status', 1);

        // Filter: specific account
        if ($request->filled('account_id')) {
            $query->where('id', $request->account_id);
        }

        // Filter: type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $accounts     = $query->orderBy('code')->get();
        $allAccounts  = Account::active()->orderBy('code')->get();

        // Calculate opening balance per account
        // (sum of all transactions BEFORE date_from)
        $accountsData = $accounts->map(function ($account) use ($request) {
            $openingDebit  = 0;
            $openingCredit = 0;

            if ($request->filled('date_from')) {
                $openingItems = $account->items()
                    ->whereHas('journalEntry', function ($q) use ($request) {
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
        })->filter(fn($a) => $a['rows']->count() > 0 || $request->filled('account_id'));

        return response()->json([
            'success' => true,
            'message' => 'General Ledger data retrieved successfully',
            'data' => [
                'accountsData' => $accountsData,
                'allAccounts' => $allAccounts,
            ]
        ]);
    }


    /**
     * TRIAL BALANCE REPORT
    */  
    public function trialBalance(Request $request)
    {
        $accounts = Account::with(['items' => function ($q) use ($request) {
            $q->whereHas('journalEntry', function ($q2) use ($request) {
                if ($request->filled('date_from')) {
                    $q2->whereDate('date', '>=', $request->date_from);
                }
                if ($request->filled('date_to')) {
                    $q2->whereDate('date', '<=', $request->date_to);
                }
            });
        }])->where('status', 1)->orderBy('code')->get();

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

        return response()->json([
            'success' => true,
            'message' => 'Trial Balance data retrieved successfully',
            'data' => [
                'grouped' => $grouped,
                'grandTotalDebit' => $grandTotalDebit,
                'grandTotalCredit' => $grandTotalCredit,
                'isBalanced' => $isBalanced,
            ]
        ]);
    }


    /**
     * PROFIT LOSS REPORT
    */
    public function profitLoss(Request $request)
    {
        $dateFrom = $request->date_from;
        $dateTo   = $request->date_to;

        $incomeTypes  = ['income'];
        $expenseTypes = ['expense'];

        $accounts = Account::with(['items' => function ($q) use ($dateFrom, $dateTo) {
            $q->whereHas('journalEntry', function ($q2) use ($dateFrom, $dateTo) {
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

        return response()->json([
            'success' => true,
            'message' => 'Profit Loss data retrieved successfully',
            'data' => [
                'incomeAccounts' => $incomeAccounts,
                'expenseAccounts' => $expenseAccounts,
                'totalIncome' => $totalIncome,
                'totalExpense' => $totalExpense,
                'netProfit' => $netProfit
            ]
        ]);
    }


    /**
     * BALANCE SHEET REPORT
    */
    public function balanceSheet(Request $request)
    {
        $asOf = $request->as_of ?? now()->toDateString();

        $accounts = Account::with(['items' => function ($q) use ($asOf) {
            $q->whereHas('journalEntry', function ($q2) use ($asOf) {
                $q2->whereDate('date', '<=', $asOf);
            });
        }])->where('status', 1)
            ->whereIn('type', ['asset', 'liability', 'equity'])
            ->orderBy('code')
            ->get();

        // Also pull net profit from income/expense up to as_of date
        // and fold it into retained earnings
        $incomeAccounts  = Account::with(['items' => function ($q) use ($asOf) {
            $q->whereHas('journalEntry', fn($q2) => $q2->whereDate('date', '<=', $asOf));
        }])->where('status', 1)->where('type', 'income')->get();

        $expenseAccounts = Account::with(['items' => function ($q) use ($asOf) {
            $q->whereHas('journalEntry', fn($q2) => $q2->whereDate('date', '<=', $asOf));
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

        return response()->json([
            'success' => true,
            'message' => 'Balance Sheet data retrieved successfully',
            'data' => [
                'assetRows' => $assetRows,
                'liabilityRows' => $liabilityRows,
                'equityRows' => $equityRows,
                'totalAssets' => $totalAssets,
                'totalLiabilities' => $totalLiabilities,
                'totalEquity' => $totalEquity,
                'totalLiabEquity' => $totalLiabEquity,
                'netProfit' => $netProfit,
                'isBalanced' => $isBalanced,
                'asOf' => $asOf
            ]
        ]);
    }


    /**
     * ACCOUNT LEDGER REPORT
    */
    public function accountLedger(Request $request)
    {
        $allAccounts = Account::active()->orderBy('code')->get();
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
                        $q->whereDate('date', '<', $request->date_from)
                    )
                    ->get();

                $openingBalance = $beforeItems->sum('debit') - $beforeItems->sum('credit');
            } else {
                $openingBalance = (float) $account->opening_balance;
            }

            // Fetch items within the selected period
            $items = JournalItem::with('journalEntry')
                ->where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($request) {
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

        return response()->json([
            'success' => true,
            'message' => 'Account Ledger data retrieved successfully',
            'data' => [
                'allAccounts' => $allAccounts,
                'account' => $account,
                'rows' => $rows,
                'openingBalance' => $openingBalance,
                'closingBalance' => $closingBalance,
                'totalDebit' => $totalDebit,
                'totalCredit' => $totalCredit
            ]
        ]);
    }


    /**
     * ACCOUNT STATEMENT REPORT
    */
    public function accountStatement(Request $request)
    {
        $userType = $request->user_type ?? 'customer'; // customer or supplier

        // Load users based on type
        $users = match ($userType) {
            'supplier' => \App\Models\Supplier::orderBy('name')->get(),
            default    => \App\Models\Customer::orderBy('name')->get(),
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

        return response()->json([
            'success' => true,
            'message' => 'Account Statement data retrieved successfully',
            'data' => [
                'users' => $users,
                'user' => $user,
                'userType' => $userType,
                'rows' => $rows,
                'openingBalance' => $openingBalance,
                'closingBalance' => $closingBalance,
                'totalDebit' => $totalDebit,
                'totalCredit' => $totalCredit,
                'sources' => $sources
            ]
        ]);
    }


    /**
     * JOURNAL ENTRIES REPORT
    */
    public function journalEntries(Request $request)
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

        return response()->json([
            'success' => true,
            'message' => 'Journal Entries data retrieved successfully',
            'data' => [
                'journals' => $journals,
                'grandTotalDebit' => $grandTotalDebit,
                'grandTotalCredit' => $grandTotalCredit,
                'isBalanced' => $isBalanced,
                'sources' => $sources
            ]
        ]);
    }

    /**
     * ACCOUNT BALANCE REPORT
    */
    public function accountBalances(Request $request)
    {
        $query = Account::with(['items' => function ($q) use ($request) {
            $q->whereHas('journalEntry', function ($q2) use ($request) {
                if ($request->filled('date_from')) {
                    $q2->whereDate('date', '>=', $request->date_from);
                }
                if ($request->filled('date_to')) {
                    $q2->whereDate('date', '<=', $request->date_to);
                }
            });
        }])->where('status', 1);

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

        $rows = $accounts->map(function ($account) use ($request) {

            $periodDebit  = $account->items->sum('debit');
            $periodCredit = $account->items->sum('credit');

            // Opening: all transactions before date_from
            $openingDebit  = 0;
            $openingCredit = 0;
            if ($request->filled('date_from')) {
                $openingItems  = $account->items()
                    ->whereHas(
                        'journalEntry',
                        fn($q) =>
                        $q->whereDate('date', '<', $request->date_from)
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

        return response()->json([
            'success' => true,
            'message' => 'Account Balances data retrieved successfully',
            'data' => [
                'grouped' => $grouped,
                'typeOrder' => $typeOrder,
                'grandTotals' => $grandTotals,
                'typeTotals' => $typeTotals,
                'rows' => $rows
            ]
        ]);
    }

}
