<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LeaveController extends Controller
{
    /**
     * Leave balance by type.
     */
    public function balance(Request $request)
    {
        $userId = Auth::id();
        $year = (int) ($request->year ?? Carbon::today()->year);
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->endOfDay();

        // dd($userId, $year, $start, $end);

        $leaveTypes = LeaveType::orderBy('name')->get();

        $rows = $leaveTypes->map(function ($type) use ($userId, $start, $end) {
            $approvedLeaves = Leave::query()
                ->where('user_id', $userId)
                ->where('leave_type_id', $type->id)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $end->toDateString())
                ->whereDate('end_date', '>=', $start->toDateString())
                ->get(['start_date', 'end_date']);

            $consumedDays = $approvedLeaves->sum(function ($leave) use ($start, $end) {
                return $this->overlapDays($leave->start_date, $leave->end_date, $start, $end);
            });

            $max = (int) $type->max_leaves_count;

            return [
                'leave_type_id' => $type->id,
                'leave_type_name' => $type->name,
                'year' => $start->year,
                'max_days' => $max,
                'consumed_days' => $consumedDays,
                'remaining_days' => max(0, $max - $consumedDays),
            ];
        })->values();

        $totals = [
            'max_days' => $rows->sum('max_days'),
            'consumed_days' => $rows->sum('consumed_days'),
            'remaining_days' => $rows->sum('remaining_days'),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Leave balance retrieved successfully.',
            'data' => [
                'summary' => $totals,
                'types' => $rows,
            ],
        ]);
    }

    /**
     * Leave consumed grouped with leave type details.
     */
    public function consumed(Request $request)
    {
        $userId = Auth::id();
        $year = (int) ($request->year ?? Carbon::today()->year);
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->endOfDay();

        $leaveTypes = LeaveType::orderBy('name')->get();

        $data = $leaveTypes->map(function ($type) use ($userId, $start, $end) {
            $approved = Leave::query()
                ->where('user_id', $userId)
                ->where('leave_type_id', $type->id)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $end->toDateString())
                ->whereDate('end_date', '>=', $start->toDateString())
                ->get(['start_date', 'end_date']);

            $pending = Leave::query()
                ->where('user_id', $userId)
                ->where('leave_type_id', $type->id)
                ->where('status', 'pending')
                ->whereDate('start_date', '<=', $end->toDateString())
                ->whereDate('end_date', '>=', $start->toDateString())
                ->get(['start_date', 'end_date']);

            $rejected = Leave::query()
                ->where('user_id', $userId)
                ->where('leave_type_id', $type->id)
                ->where('status', 'rejected')
                ->whereDate('start_date', '<=', $end->toDateString())
                ->whereDate('end_date', '>=', $start->toDateString())
                ->get(['start_date', 'end_date']);

            return [
                'leave_type_id' => $type->id,
                'leave_type_name' => $type->name,
                'year' => $start->year,
                'approved_days' => $approved->sum(fn ($leave) => $this->overlapDays($leave->start_date, $leave->end_date, $start, $end)),
                'pending_days' => $pending->sum(fn ($leave) => $this->overlapDays($leave->start_date, $leave->end_date, $start, $end)),
                'rejected_days' => $rejected->sum(fn ($leave) => $this->overlapDays($leave->start_date, $leave->end_date, $start, $end)),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Leave consumed with types retrieved successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Apply leave request.
     */
    public function apply(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'leave_type_id' => 'required|integer|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        if ($endDate->lt($startDate)) {
            return response()->json([
                'success' => false,
                'message' => 'End date cannot be earlier than start date.',
            ], 422);
        }

        $hasOverlap = Leave::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhereBetween('end_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->whereDate('start_date', '<=', $startDate->toDateString())
                            ->whereDate('end_date', '>=', $endDate->toDateString());
                    });
            })
            ->exists();

        if ($hasOverlap) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending/approved leave within this date range.',
            ], 422);
        }

        $leaveType = LeaveType::findOrFail($request->leave_type_id);

        $currentYearStart = Carbon::create($startDate->year, 1, 1)->startOfDay();
        $currentYearEnd = Carbon::create($startDate->year, 12, 31)->endOfDay();

        $consumedInYear = Leave::query()
            ->where('user_id', $user->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $currentYearEnd->toDateString())
            ->whereDate('end_date', '>=', $currentYearStart->toDateString())
            ->get(['start_date', 'end_date'])
            ->sum(fn ($leave) => $this->overlapDays($leave->start_date, $leave->end_date, $currentYearStart, $currentYearEnd));

        $requestedDays = $this->overlapDays($startDate->toDateString(), $endDate->toDateString(), $currentYearStart, $currentYearEnd);
        $maxDays = (int) $leaveType->max_leaves_count;

        if (($consumedInYear + $requestedDays) > $maxDays) {
            return response()->json([
                'success' => false,
                'message' => 'Leave request exceeds leave balance for this leave type.',
                'data' => [
                    'max_days' => $maxDays,
                    'consumed_days' => $consumedInYear,
                    'requested_days' => $requestedDays,
                    'remaining_days' => max(0, $maxDays - $consumedInYear),
                ],
            ], 422);
        }

        $leave = Leave::create([
            'user_id' => $user->id,
            'leave_type_id' => $request->leave_type_id,
            'company_id' => $user->company_id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave applied successfully.',
            'data' => $leave->load('leave_type:id,name,max_leaves_count'),
        ], 201);
    }

    /**
     * Leave history of authenticated employee.
     */
    public function history(Request $request)
    {
        $userId = Auth::id();
        $query = Leave::query()
            ->with('leave_type:id,name,max_leaves_count')
            ->where('user_id', $userId)
            ->orderBy('start_date', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('start_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('end_date', '<=', $request->to_date);
        }

        $perPage = (int) ($request->per_page ?? 20);
        $data = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Leave history retrieved successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Leave types list for dropdowns.
     */
    public function types()
    {
        $data = LeaveType::orderBy('name', 'asc')->get(['id', 'name', 'max_leaves_count']);

        return response()->json([
            'success' => true,
            'message' => 'Leave types retrieved successfully.',
            'data' => $data,
        ]);
    }

    private function overlapDays(string $fromDate, string $toDate, Carbon $rangeStart, Carbon $rangeEnd): int
    {
        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->endOfDay();

        if ($to->lt($rangeStart) || $from->gt($rangeEnd)) {
            return 0;
        }

        $start = $from->greaterThan($rangeStart) ? $from->copy() : $rangeStart->copy();
        $end = $to->lessThan($rangeEnd) ? $to->copy() : $rangeEnd->copy();

        return $start->diffInDays($end) + 1;
    }
}
