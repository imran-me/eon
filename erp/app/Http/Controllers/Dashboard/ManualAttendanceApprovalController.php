<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ManualAttendanceRequest;
use App\Services\ManualAttendanceService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManualAttendanceApprovalController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureSuperAdmin($request);
        $status = $request->input('status', 'pending');

        $requests = ManualAttendanceRequest::query()
            ->with(['user:id,name,employee_id_no,image', 'reviewer:id,name'])
            ->when(in_array($status, ['pending', 'approved', 'rejected'], true), fn ($q) => $q->where('status', $status))
            ->orderByRaw("CASE WHEN action = 'check_in' THEN 0 ELSE 1 END")
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->map(fn (ManualAttendanceRequest $item) => [
                'id' => $item->id,
                'action' => $item->action,
                'requested_date' => $item->requested_date?->toDateString(),
                'requested_time' => $item->requested_time,
                'submitted_date' => $item->created_at?->toDateString(),
                'submitted_time' => $item->created_at?->format('H:i:s'),
                'created_at' => $item->created_at?->format('d M Y, h:i A'),
                'status' => $item->status,
                'selfie_url' => $item->selfie ? asset($item->selfie) : null,
                'user' => $item->user,
                'reviewer' => $item->reviewer,
            ]);

        return response()->json(['success' => true, 'data' => $requests]);
    }

    public function approve(
        Request $request,
        string $role,
        ManualAttendanceRequest $manualAttendanceRequest,
        ManualAttendanceService $service
    )
    {
        $this->ensureSuperAdmin($request);
        $earliestAllowedDate = now()->subDays(3)->toDateString();
        $validated = $request->validate([
            'approved_date' => ['required', 'date', 'after_or_equal:'.$earliestAllowedDate, 'before_or_equal:today'],
            'approved_time' => ['required', 'date_format:H:i'],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ], [
            'approved_date.after_or_equal' => 'Manual attendance can only be approved for today or the previous 3 days.',
            'approved_date.before_or_equal' => 'A future attendance date cannot be approved.',
        ]);

        $serverDate = $manualAttendanceRequest->created_at?->toDateString();
        $serverTime = $manualAttendanceRequest->created_at?->format('H:i');
        if (($validated['approved_date'] !== $serverDate || $validated['approved_time'] !== $serverTime)
            && blank($validated['review_note'] ?? null)) {
            return response()->json([
                'message' => 'A reason is required when approving with a custom date or time.',
                'errors' => ['review_note' => ['A reason is required when changing the submitted date or time.']],
            ], 422);
        }

        DB::transaction(function () use ($manualAttendanceRequest, $validated, $request, $service) {
            $locked = ManualAttendanceRequest::query()->lockForUpdate()->findOrFail($manualAttendanceRequest->id);
            if ($locked->status !== 'pending') {
                abort(409, 'This request has already been reviewed.');
            }
            $locked->load('user.shift');
            $service->approve($locked, $validated['approved_date'], $validated['approved_time']);
            $locked->update([
                'status' => 'approved',
                'approved_date' => $validated['approved_date'],
                'approved_time' => $validated['approved_time'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $validated['review_note'] ?? null,
            ]);
            $manualAttendanceRequest->setRawAttributes($locked->getAttributes(), true);
        });

        $this->notifyDecision($manualAttendanceRequest);

        return response()->json(['success' => true, 'message' => 'Manual attendance approved successfully.']);
    }

    public function reject(Request $request, string $role, ManualAttendanceRequest $manualAttendanceRequest)
    {
        $this->ensureSuperAdmin($request);
        $validated = $request->validate([
            'review_note' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($manualAttendanceRequest, $validated, $request) {
            $locked = ManualAttendanceRequest::query()->lockForUpdate()->findOrFail($manualAttendanceRequest->id);
            if ($locked->status !== 'pending') {
                abort(409, 'This request has already been reviewed.');
            }
            $locked->update([
                'status' => 'rejected',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $validated['review_note'],
            ]);
            $manualAttendanceRequest->setRawAttributes($locked->getAttributes(), true);
        });

        $this->notifyDecision($manualAttendanceRequest);

        return response()->json(['success' => true, 'message' => 'Manual attendance request rejected.']);
    }

    private function ensureSuperAdmin(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && ($user->is_super_admin || $user->hasRole('super admin')), 403);
    }

    private function notifyDecision(ManualAttendanceRequest $request): void
    {
        try {
            NotificationService::notifyManualAttendanceDecision($request);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
