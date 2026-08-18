<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\AttendenceSetting;
use App\Models\Leave;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ManualAttendanceController extends Controller
{
    /**
     * Check if the authenticated employee is allowed to submit manual attendance.
     */
    public function eligibility()
    {
        try {
            $user = Auth::user();
            $today = Carbon::today()->toDateString();

            $todayRecord = AttendanceLog::where('user_id', $user->id)
                ->where('date', $today)
                ->where('source', 'manual')
                ->latest('id')
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'allowed' => (bool) $user->allow_manual_attendance,
                    'already_checked_in' => (bool) $todayRecord,
                    'already_checked_out' => $todayRecord ? (bool) $todayRecord->check_out : false,
                    'today_record_id' => $todayRecord?->id,
                ],
                'message' => $user->allow_manual_attendance
                    ? 'Manual attendance is enabled for your account.'
                    : 'Manual attendance is not enabled for your account.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check eligibility.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit manual check-in for today.
     */
    public function checkIn(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user->allow_manual_attendance) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to submit manual attendance.',
                ], 403);
            }

            $request->validate([
                'check_in' => 'required|date_format:H:i,H:i:s',
                'selfie' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            ]);

            $today = Carbon::today()->toDateString();

            $openLog = AttendanceLog::where('user_id', $user->id)
                ->where('date', $today)
                ->whereNull('check_out')
                ->latest('id')
                ->first();

            if ($openLog) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an open attendance record for today. Please check out first.',
                    'data' => ['attendance_log_id' => $openLog->id],
                ], 409);
            }

            $firstPunchToday = !AttendanceLog::where('user_id', $user->id)
                ->where('date', $today)
                ->exists();

            $selfiePath = $this->storeSelfie($request);

            $attendanceLog = AttendanceLog::create([
                'user_id' => $user->id,
                'date' => $today,
                'check_in' => $request->check_in,
                'check_out' => null,
                'shift_id' => $user->shift_id ?? optional($user->shift)->id,
                'note' => 'Manual attendance check-in',
                'source' => 'manual',
            ]);

            $attendance = $this->syncDailyAttendance(
                $user,
                $today,
                $request->check_in,
                null,
                $selfiePath,
                Carbon::now(),
            );

            if ($firstPunchToday) {
                $this->notifyLateIfNeeded($user, $today, $request->check_in);
            }

            return response()->json([
                'success' => true,
                'message' => 'Check-in submitted successfully.',
                'data' => [
                    'attendance_log_id' => $attendanceLog->id,
                    'attendance_id' => $attendance->id,
                    'date' => $attendance->date,
                    'check_in' => $attendance->check_in,
                    'source' => $attendanceLog->source,
                    'selfie_url' => $selfiePath,
                    'submitted_at' => $attendance->submitted_at,
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit check-in.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit manual check-out for the current open attendance record.
     */
    public function checkOut(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user->allow_manual_attendance) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to submit manual attendance.',
                ], 403);
            }

            $request->validate([
                'check_out' => 'required|date_format:H:i,H:i:s',
                'selfie' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            ]);

            $today = Carbon::today()->toDateString();

            $openLog = AttendanceLog::where('user_id', $user->id)
                ->where('date', $today)
                ->whereNull('check_out')
                ->latest('id')
                ->first();

            if (!$openLog) {
                return response()->json([
                    'success' => false,
                    'message' => 'Open attendance record not found for today.',
                ], 404);
            }

            $checkInTime = Carbon::parse($today . ' ' . $openLog->check_in);
            $checkOutTime = Carbon::parse($today . ' ' . $request->check_out);

            if ($checkOutTime->lte($checkInTime)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Check-out time must be after check-in time (' . $openLog->check_in . ').',
                ], 422);
            }

            $selfiePath = $this->storeSelfie($request);

            $openLog->update([
                'check_out' => $request->check_out,
            ]);

            $attendance = $this->syncDailyAttendance(
                $user,
                $today,
                $openLog->check_in,
                $request->check_out,
                $selfiePath,
                Carbon::now(),
            );

            return response()->json([
                'success' => true,
                'message' => 'Check-out submitted successfully.',
                'data' => [
                    'attendance_log_id' => $openLog->id,
                    'attendance_id' => $attendance->id,
                    'date' => $attendance->date,
                    'check_in' => $attendance->check_in,
                    'check_out' => $attendance->check_out,
                    'source' => $openLog->source,
                    'selfie_url' => $selfiePath,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit check-out.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get today's manual attendance record for the authenticated employee.
     */
    public function today()
    {
        try {
            $user = Auth::user();
            $today = Carbon::today()->toDateString();

            $attendance = Attendance::where('user_id', $user->id)
                ->where('date', $today)
                ->first();

            $attendanceLogs = AttendanceLog::where('user_id', $user->id)
                ->where('date', $today)
                ->orderBy('id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'attendance' => $attendance ? [
                        'id' => $attendance->id,
                        'date' => $attendance->date,
                        'check_in' => $attendance->check_in,
                        'check_out' => $attendance->check_out,
                        'selfie_url' => $attendance->selfie,
                        'submitted_at' => $attendance->submitted_at,
                    ] : null,
                    'attendance_logs' => $attendanceLogs,
                ],
                'message' => $attendanceLogs->isNotEmpty()
                    ? 'Today\'s attendance logs found.'
                    : 'No attendance logs found for today.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve today\'s attendance.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function storeSelfie(Request $request): ?string
    {
        if (!$request->hasFile('selfie')) {
            return null;
        }

        $image = $request->file('selfie');
        $imageName = uniqid() . '.' . strtolower($image->getClientOriginalExtension());
        $uploadPath = 'uploads/attendance/selfies/';

        if (!file_exists(public_path($uploadPath))) {
            mkdir(public_path($uploadPath), 0777, true);
        }

        $image->move(public_path($uploadPath), $imageName);

        return $uploadPath . $imageName;
    }

    private function syncDailyAttendance(
        $user,
        string $date,
        string $checkInTime,
        ?string $checkOutTime = null,
        ?string $selfiePath = null,
        ?Carbon $submittedAt = null,
    ): Attendance {
        $attendance = Attendance::firstOrNew([
            'user_id' => $user->id,
            'date' => $date,
        ]);

        // Approved Early Leave check — waives the generic manual-attendance note in favor of a clear message
        $approvedEarlyLeave = Leave::findApprovedEarlyLeaveForDate($user->id, $date);

        if (!$attendance->exists) {
            $attendance->company_id = $user->company_id ?? optional($user->company)->id ?? null;
            $attendance->shift_id = $user->shift_id ?? optional($user->shift)->id ?? null;
            // Same grace-period setting the device flow stamps on its rows. Every
            // late / early-out / overtime calculation is gated on this relation,
            // so a null here silently reports manual punches as plain "Present"
            // no matter how late the punch was.
            $attendance->attendence_setting_id = AttendenceSetting::find(1)?->id
                ?? AttendenceSetting::orderBy('id')->value('id');
            $attendance->status = 'present';
            $attendance->note = $approvedEarlyLeave
                ? 'Approved Early Leave (until ' . Carbon::parse($approvedEarlyLeave->leave_time)->format('h:i A') . ')'
                : 'Manual attendance';
        }

        if (!$attendance->check_in) {
            $attendance->check_in = $checkInTime;
        }

        if ($checkOutTime !== null) {
            $attendance->check_out = $checkOutTime;

            if ($approvedEarlyLeave) {
                $attendance->note = 'Approved Early Leave (from ' . Carbon::parse($approvedEarlyLeave->leave_time)->format('h:i A') . ')';
            }
        }

        if ($selfiePath !== null) {
            $attendance->selfie = $selfiePath;
        }

        if (!$attendance->submitted_at && $submittedAt !== null) {
            $attendance->submitted_at = $submittedAt;
        }

        $attendance->save();

        return $attendance;
    }

    private function notifyLateIfNeeded($user, string $date, string $checkInTime): void
    {
        $attendanceSetting = AttendenceSetting::find(1);

        if (!$user->shift || !$attendanceSetting) {
            return;
        }

        $graceMinutes = (int) $attendanceSetting->time_after_checkin;
        $shiftStart = Carbon::parse($date . ' ' . $user->shift->start_time);
        $lateThreshold = $shiftStart->copy()->addMinutes($graceMinutes);
        $checkInAt = Carbon::parse($date . ' ' . $checkInTime);

        if ($checkInAt->lte($lateThreshold)) {
            return;
        }

        $lateMinutes = (int) $lateThreshold->diffInMinutes($checkInAt);

        if (!empty($user->email) && $lateMinutes > 0) {
            $this->sendMail($lateMinutes, $user->email, $user->name);
        }

        if (!empty($user->phone) && $lateMinutes > 0) {
            $smsMessage = "Dear {$user->name}, today you arrived at the office {$lateMinutes} minutes late. Please try to adhere to office hours in the future.";
            sendSms($user->phone, $smsMessage);
        }
    }

    private function sendMail($lateMinutes, $email, $name)
    {
        $subject = 'দেরিতে অফিসে উপস্থিতির নোটিশ';
        $htmlContent = '<p>প্রিয় ' . $name . ',</p>
                        <p>আজ আপনি অফিসে ' . $lateMinutes . ' মিনিট দেরিতে উপস্থিত হয়েছেন। অনুগ্রহ করে ভবিষ্যতে অফিস সময় মেনে চলবেন।</p>';

        sendBrevoMail(
            $email,
            $name,
            $subject,
            $htmlContent
        );
    }
}
