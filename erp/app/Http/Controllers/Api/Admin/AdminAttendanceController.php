<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAttendanceController extends Controller
{
    /**
     * Get only the authenticated employee's attendance records
     *
     * GET /api/attendance
     */
    public function index(Request $request)
    {
        try {
            $query = Attendance::query()
                ->where("user_id", $request->user_id)
                ->orderBy("date", "desc");

            if ($request->filled("from_date")) {
                $query->whereDate("date", ">=", $request->from_date);
            }

            if ($request->filled("to_date")) {
                $query->whereDate("date", "<=", $request->to_date);
            }

            if ($request->filled("status")) {
                $query->where("status", $request->status);
            }

            $perPage = (int) ($request->per_page ?? 20);
            $attendances = $query->orderBy("date", "desc")->paginate($perPage);

            return response()->json(
                [
                    "success" => true,
                    "data" => $attendances,
                    "message" => "Attendance records retrieved successfully",
                ],
                200,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Failed to retrieve attendance records",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Monthly attendance report for authenticated employee.
     *
     * GET /api/employee/attendance/report?month=03&year=2026
     */
    public function report(Request $request)
    {
        try {
            $userId = $request->user_id;
            $selectedMonth = str_pad(
                (string) ($request->month ?? Carbon::today()->format("m")),
                2,
                "0",
                STR_PAD_LEFT,
            );
            $selectedYear =
                (int) ($request->year ?? Carbon::today()->format("Y"));

            $user = User::with("shift")->findOrFail($userId);

            $startDate = Carbon::create(
                $selectedYear,
                (int) $selectedMonth,
                1,
            )->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
            $period = CarbonPeriod::create($startDate, $endDate);

            $dbAttendances = Attendance::with("shift", "attendence_setting")
                ->whereMonth("date", $selectedMonth)
                ->whereYear("date", $selectedYear)
                ->where("user_id", $userId)
                ->get()
                ->keyBy("date");

            $defaultShift = $user->shift ?? $dbAttendances->first()?->shift;

            $holidays = Holiday::query()
                ->where(function ($query) use ($startDate, $endDate) {
                    $query
                        ->whereBetween("start_date", [$startDate, $endDate])
                        ->orWhereBetween("end_date", [$startDate, $endDate]);
                })
                ->get();

            $leaves = Leave::query()
                ->where("user_id", $userId)
                ->where("status", "approved")
                ->where(function ($query) use ($startDate, $endDate) {
                    $query
                        ->whereBetween("start_date", [$startDate, $endDate])
                        ->orWhereBetween("end_date", [$startDate, $endDate]);
                })
                ->get();

            $summary = [
                "present" => 0,
                "absent" => 0,
                "leave" => 0,
                "holiday" => 0,
                "late" => 0,
                "late_minutes" => 0,
                "early_out" => 0,
                "early_minutes" => 0,
                "overtime_days" => 0,
                "overtime_minutes" => 0,
                "total_days" => $period->count(),
            ];

            $rows = collect();

            foreach ($period as $date) {
                $dateString = $date->format("Y-m-d");
                $dayName = strtolower($date->format("l"));

                $record = $dbAttendances->get($dateString);

                if ($record) {
                    $status = "Present";
                    $note = $record->note ?? "-";
                    $checkIn = $record->check_in;
                    $checkOut = $record->check_out;

                    if (
                        $checkIn &&
                        $record->attendence_setting &&
                        $record->shift
                    ) {
                        $graceMinutes =
                            (int) $record->attendence_setting
                                ->time_after_checkin;
                        $lateThreshold = Carbon::parse(
                            $dateString . " " . $record->shift->start_time,
                        )->addMinutes($graceMinutes);
                        $checkInTime = Carbon::parse(
                            $dateString . " " . $checkIn,
                        );

                        if ($checkInTime->gt($lateThreshold)) {
                            $lateMinutes = $checkInTime->diffInMinutes(
                                $lateThreshold,
                            );
                            $status = "Late";
                            $summary["late"]++;
                            $summary["late_minutes"] += $lateMinutes;
                        }
                    }

                    if ($status === "Present") {
                        $summary["present"]++;
                    } else {
                        // Late is also present physically
                        $summary["present"]++;
                    }

                    if (
                        $checkOut &&
                        $record->attendence_setting &&
                        $record->shift
                    ) {
                        $shiftEnd = Carbon::parse(
                            $dateString . " " . $record->shift->end_time,
                        );
                        $checkOutTime = Carbon::parse(
                            $dateString . " " . $checkOut,
                        );

                        $earlyThreshold = $shiftEnd->copy()->subMinutes(10);
                        $overtimeThreshold = $shiftEnd->copy()->addMinutes(60);

                        if ($checkOutTime->lt($earlyThreshold)) {
                            $earlyMinutes = $earlyThreshold->diffInMinutes(
                                $checkOutTime,
                            );
                            $summary["early_out"]++;
                            $summary["early_minutes"] += $earlyMinutes;
                        } elseif ($checkOutTime->gte($overtimeThreshold)) {
                            $overtimeMinutes = $shiftEnd->diffInMinutes(
                                $checkOutTime,
                            );
                            $summary["overtime_days"]++;
                            $summary["overtime_minutes"] += $overtimeMinutes;
                        }
                    }

                    $rows->push([
                        "date" => $dateString,
                        "day" => ucfirst($dayName),
                        "status" => $status,
                        "note" => $note,
                        "check_in" => $checkIn,
                        "check_out" => $checkOut,
                    ]);

                    continue;
                }

                $leaveRecord = $leaves->first(function ($leave) use (
                    $dateString,
                ) {
                    return $dateString >= $leave->start_date &&
                        $dateString <= $leave->end_date;
                });

                if ($leaveRecord) {
                    $summary["leave"]++;
                    $rows->push([
                        "date" => $dateString,
                        "day" => ucfirst($dayName),
                        "status" => "Leave",
                        "note" => "Approved Leave",
                        "check_in" => null,
                        "check_out" => null,
                    ]);
                    continue;
                }

                $holidayRecord = $holidays->first(function ($holiday) use (
                    $dateString,
                ) {
                    return $dateString >= $holiday->start_date &&
                        $dateString <= $holiday->end_date;
                });

                $isWeekend =
                    $defaultShift &&
                    in_array($dayName, $defaultShift->holidays ?? [], true);
                if ($holidayRecord || $isWeekend) {
                    $summary["holiday"]++;
                    $rows->push([
                        "date" => $dateString,
                        "day" => ucfirst($dayName),
                        "status" => "Holiday",
                        "note" => $holidayRecord
                            ? $holidayRecord->name
                            : "Weekend",
                        "check_in" => null,
                        "check_out" => null,
                    ]);
                    continue;
                }

                $summary["absent"]++;
                $rows->push([
                    "date" => $dateString,
                    "day" => ucfirst($dayName),
                    "status" => "Absent",
                    "note" => "No record",
                    "check_in" => null,
                    "check_out" => null,
                ]);
            }

            return response()->json([
                "success" => true,
                "message" => "Attendance report retrieved successfully.",
                "data" => [
                    "month" => $selectedMonth,
                    "year" => $selectedYear,
                    "summary" => $summary,
                    "records" => $rows,
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Failed to retrieve attendance report",
                    "error" => $th->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Grant or revoke manual attendance permission for an employee.
     * PATCH /api/admin/employees/{userId}/manual-attendance-permission
     *
     * Body: { "allow_manual_attendance": true|false }
     */
    public function toggleManualPermission(Request $request, $userId)
    {
        try {
            $request->validate([
                "allow_manual_attendance" => "required|boolean",
            ]);

            $employee = User::find($userId);

            if (!$employee) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Employee not found.",
                    ],
                    404,
                );
            }

            $employee->update([
                "allow_manual_attendance" =>
                    (bool) $request->allow_manual_attendance,
            ]);

            return response()->json([
                "success" => true,
                "message" => $request->allow_manual_attendance
                    ? "Manual attendance enabled for {$employee->name}."
                    : "Manual attendance disabled for {$employee->name}.",
                "data" => [
                    "user_id" => $employee->id,
                    "name" => $employee->name,
                    "allow_manual_attendance" =>
                        (bool) $employee->allow_manual_attendance,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Validation failed.",
                    "errors" => $e->errors(),
                ],
                422,
            );
        } catch (\Throwable $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" =>
                        "Failed to update manual attendance permission.",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }
}
