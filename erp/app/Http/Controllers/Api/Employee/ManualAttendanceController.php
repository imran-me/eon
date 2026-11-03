<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\ManualAttendanceRequest;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ManualAttendanceController extends Controller
{
    public function eligibility()
    {
        try {
            $user = Auth::user();
            $requests = $this->todayRequests($user->id);
            $checkIn = $requests->firstWhere('action', 'check_in');
            $checkOut = $requests->firstWhere('action', 'check_out');

            return response()->json(['success' => true, 'data' => [
                'allowed' => (bool) $user->allow_manual_attendance,
                'already_checked_in' => (bool) $checkIn,
                'already_checked_out' => (bool) $checkOut,
                'check_in_status' => $checkIn?->status,
                'check_out_status' => $checkOut?->status,
                'today_request_id' => $checkIn?->id,
                'today_record_id' => $checkIn?->attendance_log_id,
            ], 'message' => $user->allow_manual_attendance
                ? 'Manual attendance is enabled. Each submission requires Super Admin approval.'
                : 'Manual attendance is not enabled for your account.']);
        } catch (\Throwable $e) {
            return $this->error('Failed to check eligibility.', $e);
        }
    }

    public function checkIn(Request $request)
    {
        try {
            $user = Auth::user();
            $this->ensureAllowed($user);
            $validated = $request->validate([
                'check_in' => 'required|date_format:H:i,H:i:s',
                'selfie' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            ]);
            $today = Carbon::today()->toDateString();
            $exists = ManualAttendanceRequest::query()->where('user_id', $user->id)
                ->whereDate('requested_date', $today)->where('action', 'check_in')
                ->whereIn('status', ['pending', 'approved'])->exists();

            if ($exists || AttendanceLog::query()->where('user_id', $user->id)->whereDate('date', $today)->exists()) {
                return response()->json(['success' => false, 'message' => 'A check-in already exists or is awaiting approval for today.'], 409);
            }

            $manualRequest = ManualAttendanceRequest::create([
                'user_id' => $user->id,
                'action' => 'check_in',
                'requested_date' => $today,
                'requested_time' => $validated['check_in'],
                'selfie' => $this->storeSelfie($request),
            ]);
            $this->notifySubmitted($manualRequest);

            return response()->json(['success' => true,
                'message' => 'Your manual attendance check-in has been submitted and is pending Super Admin approval.',
                'data' => array_merge($this->requestData($manualRequest), [
                    'manual_attendance_request_id' => $manualRequest->id,
                    'attendance_log_id' => null,
                    'attendance_id' => null,
                    'check_in' => $manualRequest->requested_time,
                    'source' => 'manual',
                    'selfie_url' => $manualRequest->selfie,
                ])], 202);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (\Throwable $e) {
            return $this->error('Failed to submit check-in request.', $e);
        }
    }

    public function checkOut(Request $request)
    {
        try {
            $user = Auth::user();
            $this->ensureAllowed($user);
            $validated = $request->validate([
                'check_out' => 'required|date_format:H:i,H:i:s',
                'selfie' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            ]);
            $today = Carbon::today()->toDateString();
            $checkInRequest = ManualAttendanceRequest::query()->where('user_id', $user->id)
                ->whereDate('requested_date', $today)->where('action', 'check_in')
                ->whereIn('status', ['pending', 'approved'])->latest('id')->first();
            $openLog = AttendanceLog::query()->where('user_id', $user->id)
                ->whereDate('date', $today)->whereNull('check_out')->latest('id')->first();

            if (! $checkInRequest && ! $openLog) {
                return response()->json(['success' => false, 'message' => 'Submit a check-in before requesting check-out.'], 422);
            }

            $checkInTime = $openLog?->check_in ?? $checkInRequest->requested_time;
            if (Carbon::parse($today.' '.$validated['check_out'])->lte(Carbon::parse($today.' '.$checkInTime))) {
                return response()->json(['success' => false, 'message' => 'Check-out time must be after check-in time.'], 422);
            }

            $exists = ManualAttendanceRequest::query()->where('user_id', $user->id)
                ->whereDate('requested_date', $today)->where('action', 'check_out')
                ->whereIn('status', ['pending', 'approved'])->exists();
            if ($exists) {
                return response()->json(['success' => false, 'message' => 'A check-out already exists or is awaiting approval for today.'], 409);
            }

            $manualRequest = ManualAttendanceRequest::create([
                'user_id' => $user->id,
                'action' => 'check_out',
                'requested_date' => $today,
                'requested_time' => $validated['check_out'],
                'selfie' => $this->storeSelfie($request),
            ]);
            $this->notifySubmitted($manualRequest);

            return response()->json(['success' => true,
                'message' => 'Your manual attendance check-out has been submitted and is pending Super Admin approval.',
                'data' => array_merge($this->requestData($manualRequest), [
                    'manual_attendance_request_id' => $manualRequest->id,
                    'attendance_log_id' => null,
                    'attendance_id' => null,
                    'check_out' => $manualRequest->requested_time,
                    'source' => 'manual',
                    'selfie_url' => $manualRequest->selfie,
                ])], 202);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (\Throwable $e) {
            return $this->error('Failed to submit check-out request.', $e);
        }
    }

    public function today()
    {
        try {
            $user = Auth::user();
            $today = Carbon::today()->toDateString();
            $attendance = Attendance::where('user_id', $user->id)->whereDate('date', $today)->first();
            $attendanceLogs = AttendanceLog::where('user_id', $user->id)->whereDate('date', $today)->orderBy('id')->get();
            $requests = $this->todayRequests($user->id)->map(fn ($item) => $this->requestData($item));

            return response()->json(['success' => true, 'data' => [
                'attendance' => $attendance ? [
                    'id' => $attendance->id,
                    'date' => $attendance->date,
                    'check_in' => $attendance->check_in,
                    'check_out' => $attendance->check_out,
                    'selfie_url' => $attendance->selfie,
                    'submitted_at' => $attendance->submitted_at,
                ] : null,
                'attendance_logs' => $attendanceLogs,
                'manual_requests' => $requests,
            ], 'message' => $requests->isNotEmpty() ? 'Today\'s manual attendance requests found.' : 'No manual attendance request found for today.']);
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve today\'s attendance.', $e);
        }
    }

    private function todayRequests(int $userId)
    {
        return ManualAttendanceRequest::query()->where('user_id', $userId)
            ->whereDate('requested_date', Carbon::today()->toDateString())->latest('id')->get();
    }

    private function ensureAllowed($user): void
    {
        if (! $user->allow_manual_attendance) {
            throw new AuthorizationException('Manual attendance is not enabled for your account.');
        }
    }

    private function notifySubmitted(ManualAttendanceRequest $request): void
    {
        try {
            NotificationService::notifyManualAttendanceSubmitted($request);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function storeSelfie(Request $request): ?string
    {
        if (! $request->hasFile('selfie')) {
            return null;
        }
        $image = $request->file('selfie');
        $imageName = uniqid().'.'.strtolower($image->getClientOriginalExtension());
        $uploadPath = 'uploads/attendance/selfies/';
        if (! file_exists(public_path($uploadPath))) {
            mkdir(public_path($uploadPath), 0777, true);
        }
        $image->move(public_path($uploadPath), $imageName);

        return $uploadPath.$imageName;
    }

    private function requestData(ManualAttendanceRequest $request): array
    {
        return [
            'id' => $request->id, 'action' => $request->action,
            'date' => $request->requested_date?->toDateString(),
            'requested_time' => $request->requested_time, 'status' => $request->status,
            'approved_date' => $request->approved_date?->toDateString(),
            'approved_time' => $request->approved_time, 'review_note' => $request->review_note,
            'submitted_at' => $request->created_at,
        ];
    }

    private function error(string $message, \Throwable $e)
    {
        report($e);

        return response()->json(['success' => false, 'message' => $message], 500);
    }
}
