<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\AttendenceSetting;
use App\Models\ManualAttendanceRequest;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class ManualAttendanceService
{
    public function approve(ManualAttendanceRequest $manualRequest, string $date, string $time): Attendance
    {
        $user = $manualRequest->user;

        if ($manualRequest->action === 'check_in') {
            $existingAttendance = Attendance::query()
                ->where('user_id', $user->id)
                ->whereDate('date', $date)
                ->whereNotNull('check_in')
                ->exists();
            if ($existingAttendance) {
                throw ValidationException::withMessages([
                    'approved_date' => 'Attendance has already been recorded for this employee on the selected date.',
                ]);
            }

            $openLog = AttendanceLog::query()
                ->where('user_id', $user->id)
                ->whereDate('date', $date)
                ->whereNull('check_out')
                ->latest('id')
                ->first();

            if ($openLog) {
                throw ValidationException::withMessages([
                    'approved_time' => 'This employee already has an open attendance record for the selected date.',
                ]);
            }

            $log = AttendanceLog::create([
                'user_id' => $user->id,
                'date' => $date,
                'check_in' => $time,
                'check_out' => null,
                'shift_id' => $user->shift_id ?? optional($user->shift)->id,
                'note' => 'Approved mobile manual attendance check-in',
                'source' => 'manual',
            ]);

            $attendance = $this->syncAttendance($manualRequest, $log, $date, $time);
        } else {
            $log = AttendanceLog::query()
                ->where('user_id', $user->id)
                ->whereDate('date', $date)
                ->whereNull('check_out')
                ->latest('id')
                ->first();

            if (! $log) {
                throw ValidationException::withMessages([
                    'approved_date' => 'Approve the employee check-in for this date before approving check-out.',
                ]);
            }

            if (Carbon::parse($date.' '.$time)->lte(Carbon::parse($date.' '.$log->check_in))) {
                throw ValidationException::withMessages([
                    'approved_time' => 'Approved check-out time must be after check-in time.',
                ]);
            }

            $log->update([
                'check_out' => $time,
                'note' => 'Approved mobile manual attendance',
            ]);

            $attendance = $this->syncAttendance($manualRequest, $log, $date, $log->check_in, $time);
        }

        $manualRequest->update(['attendance_log_id' => $log->id]);

        return $attendance;
    }

    private function syncAttendance(
        ManualAttendanceRequest $manualRequest,
        AttendanceLog $log,
        string $date,
        string $checkIn,
        ?string $checkOut = null,
    ): Attendance {
        $user = $manualRequest->user;
        $attendance = Attendance::firstOrNew([
            'user_id' => $user->id,
            'date' => $date,
        ]);

        if (! $attendance->exists) {
            $attendance->company_id = $user->company_id;
            $attendance->shift_id = $log->shift_id;
            $attendance->attendence_setting_id = AttendenceSetting::query()->orderBy('id')->value('id');
            $attendance->status = 'present';
            $attendance->submitted_at = $manualRequest->created_at;
        }

        if (! $attendance->check_in) {
            $attendance->check_in = $checkIn;
        }

        if ($checkOut !== null) {
            $attendance->check_out = $checkOut;
        }

        if ($manualRequest->selfie) {
            $attendance->selfie = $manualRequest->selfie;
        }

        $attendance->note = 'Manual attendance approved by Super Admin';
        $attendance->save();

        return $attendance;
    }
}
