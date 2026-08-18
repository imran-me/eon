<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /**
     * Get only the authenticated employee's attendance records
     * 
     * GET /api/attendance
     */
    public function index(Request $request)
    {
        try {
            $userId = Auth::id();

            $query = Attendance::query()
                ->where('user_id', $userId)
                ->orderBy('date', 'desc');

            if ($request->filled('from_date')) {
                $query->whereDate('date', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $query->whereDate('date', '<=', $request->to_date);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $perPage = (int) ($request->per_page ?? 20);
            $attendances = $query
                ->orderBy('date', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $attendances,
                'message' => 'Attendance records retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance records',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process attendance data for a specific month
     * 
     * @return array ['records' => Collection, 'summary' => array]
     */
    private function processMonthAttendanceData($dbAttendances, $defaultShift, $startDate, $endDate, $holidays, $leaves, $today)
    {
        $period = CarbonPeriod::create($startDate, $endDate);
        $records = collect();
        $summary = [
            'present' => 0,
            'absent' => 0,
            'leave' => 0,
            'holiday' => 0,
            'late' => 0,
            'late_minutes' => 0,
            'early_out' => 0,
            'early_minutes' => 0,
            'overtime_days' => 0,
            'overtime_minutes' => 0,
            'total_days' => 0,
        ];

        $weekendDays = collect(optional($defaultShift)->holidays ?? [])
            ->map(fn ($day) => strtolower((string) $day))
            ->values()
            ->all();

        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $dayName = strtolower($date->format('l'));
            $summary['total_days']++;
            $lateMinutes = 0;

            $record = $dbAttendances->get($dateString);

            $leaveRecord = $leaves->first(function ($leave) use ($dateString) {
                return $dateString >= Carbon::parse($leave->start_date)->toDateString()
                    && $dateString <= Carbon::parse($leave->end_date)->toDateString();
            });

            $holidayRecord = $holidays->first(function ($holiday) use ($dateString) {
                return $dateString >= Carbon::parse($holiday->start_date)->toDateString()
                    && $dateString <= Carbon::parse($holiday->end_date)->toDateString();
            });

            $isWeekend = in_array($dayName, $weekendDays, true);

            if ($record) {
                $status = 'Present';
                $note = $record->note ?? '-';
                $checkIn = $record->check_in;
                $checkOut = $record->check_out;

                if ($checkIn && $record->attendence_setting && $record->shift) {
                    $graceMinutes = (int) $record->attendence_setting->time_after_checkin;
                    $lateThreshold = Carbon::parse($dateString . ' ' . $record->shift->start_time)->addMinutes($graceMinutes);
                    $checkInTime = Carbon::parse($dateString . ' ' . $checkIn);

                    if ($checkInTime->gt($lateThreshold)) {
                        $lateMinutes = $checkInTime->diffInMinutes($lateThreshold);
                        $status = 'Late';
                        $summary['late']++;
                        $summary['late_minutes'] += $lateMinutes;
                    }
                }

                $summary['present']++;

                if ($checkOut && $record->attendence_setting && $record->shift) {
                    $shiftEnd = Carbon::parse($dateString . ' ' . $record->shift->end_time);
                    $checkOutTime = Carbon::parse($dateString . ' ' . $checkOut);

                    $earlyThreshold = $shiftEnd->copy()->subMinutes(10);
                    $overtimeThreshold = $shiftEnd->copy()->addMinutes(60);

                    if ($checkOutTime->lt($earlyThreshold)) {
                        $earlyMinutes = $earlyThreshold->diffInMinutes($checkOutTime);
                        $summary['early_out']++;
                        $summary['early_minutes'] += $earlyMinutes;
                    } elseif ($checkOutTime->gte($overtimeThreshold)) {
                        $overtimeMinutes = $shiftEnd->diffInMinutes($checkOutTime);
                        $summary['overtime_days']++;
                        $summary['overtime_minutes'] += $overtimeMinutes;
                    }
                }

                $records->push([
                    'date' => $dateString,
                    'day' => ucfirst($dayName),
                    'status' => $status,
                    'note' => $note,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'late_minutes' => $lateMinutes,
                    'is_today' => $dateString === $today->toDateString(),
                ]);

                continue;
            }

            if ($leaveRecord) {
                $summary['leave']++;
                $records->push([
                    'date' => $dateString,
                    'day' => ucfirst($dayName),
                    'status' => 'Leave',
                    'note' => $leaveRecord->leave_time
                        ? 'Approved Early Leave (from ' . Carbon::parse($leaveRecord->leave_time)->format('h:i A') . ')'
                        : 'Approved Leave',
                    'check_in' => null,
                    'check_out' => null,
                    'late_minutes' => 0,
                    'is_today' => $dateString === $today->toDateString(),
                ]);

                continue;
            }

            if ($holidayRecord || $isWeekend) {
                $summary['holiday']++;
                $records->push([
                    'date' => $dateString,
                    'day' => ucfirst($dayName),
                    'status' => 'Holiday',
                    'note' => $holidayRecord ? $holidayRecord->name : 'Weekend',
                    'check_in' => null,
                    'check_out' => null,
                    'late_minutes' => 0,
                    'is_today' => $dateString === $today->toDateString(),
                ]);

                continue;
            }

            $summary['absent']++;
            $records->push([
                'date' => $dateString,
                'day' => ucfirst($dayName),
                'status' => 'Absent',
                'note' => 'No record',
                'check_in' => null,
                'check_out' => null,
                'late_minutes' => 0,
                'is_today' => $dateString === $today->toDateString(),
            ]);
        }

        return [
            'records' => $records,
            'summary' => $summary,
        ];
    }

    /**
     * Monthly attendance report for authenticated employee.
     *
     * GET /api/employee/attendance/report?month=03&year=2026
     */
    public function report(Request $request)
    {
        try {
            $userId = Auth::id();
            $today = Carbon::today();

            $user = User::with('shift', 'profile')->findOrFail($userId);

            if (!empty(optional($user->profile)->joining_date)) {
                $calendarStart = Carbon::parse($user->profile->joining_date)->startOfDay();
            } elseif (!empty($user->created_at)) {
                $calendarStart = Carbon::parse($user->created_at)->startOfDay();
            } else {
                $calendarStart = $today->copy()->startOfMonth();
            }

            $calendarEnd = $today->copy();

            $dbAttendances = Attendance::with('shift', 'attendence_setting')
                ->where('user_id', $userId)
                ->whereBetween('date', [$calendarStart->toDateString(), $calendarEnd->toDateString()])
                ->get()
                ->keyBy(function ($item) {
                    return Carbon::parse($item->date)->toDateString();
                });

            $defaultShift = $user->shift ?? $dbAttendances->first()?->shift;

            $holidays = Holiday::query()
                ->whereDate('start_date', '<=', $calendarEnd->toDateString())
                ->whereDate('end_date', '>=', $calendarStart->toDateString())
                ->get();

            $leaves = Leave::query()
                ->where('user_id', $userId)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $calendarEnd->toDateString())
                ->whereDate('end_date', '>=', $calendarStart->toDateString())
                ->get();

            $period = CarbonPeriod::create($calendarStart, $calendarEnd);
            $months = collect();
            $summaryTotals = [
                'present' => 0,
                'absent' => 0,
                'leave' => 0,
                'holiday' => 0,
                'late' => 0,
                'late_minutes' => 0,
                'early_out' => 0,
                'early_minutes' => 0,
                'overtime_days' => 0,
                'overtime_minutes' => 0,
                'total_days' => 0,
            ];
            $allRecords = collect();

            foreach ($period as $date) {
                $monthKey = $date->format('Y-m');

                if (!$months->has($monthKey)) {
                    $monthStart = $date->copy()->startOfMonth();
                    $monthEnd = $date->copy()->endOfMonth()->min($today);

                    // Process this month's data
                    $monthDbAttendances = $dbAttendances->filter(function ($item) use ($monthStart, $monthEnd) {
                        $itemDate = Carbon::parse($item->date);
                        return $itemDate->between($monthStart, $monthEnd);
                    });

                    $monthlyData = $this->processMonthAttendanceData(
                        $monthDbAttendances,
                        $defaultShift,
                        $monthStart,
                        $monthEnd,
                        $holidays,
                        $leaves,
                        $today
                    );

                    // Accumulate totals
                    foreach ($monthlyData['summary'] as $key => $value) {
                        if ($key !== 'total_days') {
                            $summaryTotals[$key] += $value;
                        }
                    }
                    $summaryTotals['total_days'] += $monthlyData['summary']['total_days'];

                    $allRecords = $allRecords->merge($monthlyData['records']);

                    $months->put($monthKey, [
                        'month_key' => $monthKey,
                        'month' => $date->format('m'),
                        'year' => (int) $date->format('Y'),
                        'month_name' => $date->format('F Y'),
                        'start_date' => $monthStart->toDateString(),
                        'end_date' => $monthEnd->toDateString(),
                        'summary' => $monthlyData['summary'],
                        'calendar' => $monthlyData['records']->values(),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Attendance report retrieved successfully.',
                'data' => [
                    'view' => 'full_history_grouped',
                    'start_date' => $calendarStart->toDateString(),
                    'end_date' => $calendarEnd->toDateString(),
                    'summary' => $summaryTotals,
                    'records' => $allRecords->values(),
                    'months' => $months->values(),
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance report',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function downloadReport(Request $request)
    {
        $month = $request->input('month', date('n'));
        $year = $request->input('year', date('Y'));
        $userId = Auth::id();

        $user = User::with('shift')->find($userId);
        $today = Carbon::today();

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $dbAttendances = Attendance::with('shift', 'attendence_setting')
            ->where('user_id', $userId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->keyBy('date');

        $holidays = Holiday::query()
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
            })->get();

        $leaves = Leave::query()
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
            })->get();

        $defaultShift = $user->shift ?? $dbAttendances->first()?->shift;

        $attendanceData = $this->processMonthAttendanceData(
            $dbAttendances,
            $defaultShift,
            $startDate,
            $endDate,
            $holidays,
            $leaves,
            $today
        );

        $fullMonthData = $attendanceData['records']->map(fn($r) => (object) $r);
        $summary = $attendanceData['summary'];


        $pdf = PDF::loadView('report.monthly_attendances_pdf', [
            'attendances' => $fullMonthData,
            'user' => $user,
            'summary' => (object)$summary,
            'month' => $startDate->format('F'),
            'year' => $year
        ]);

        $slugName = Str::slug($user->name);
        $fileName = "Attendance_Report_{$slugName}_{$month}.pdf";
        $pdfPath = 'attendance-reports/' . $fileName;
        
        $uploadPath = public_path('uploads/' . $pdfPath);
        

        if (!is_dir(dirname($uploadPath))) {
            mkdir(dirname($uploadPath), 0755, true);
        }
        
        $pdf->save($uploadPath);

        return response()->download($uploadPath, $fileName, ['Content-Type'=>'application/pdf']);
    }
}
