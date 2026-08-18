<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LeaveController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view leave', only: ['index', 'show']),
            new Middleware('permission:create leave', only: ['create', 'store']),
            new Middleware('permission:edit leave', only: ['edit', 'update']),
            new Middleware('permission:delete leave', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!Auth::user()->hasPermissionTo('view all leave')) {
             $request->merge(['user_id' => Auth::id()]);
        }
        $query = Leave::select('leaves.*')
            ->leftJoin('users', 'users.id', '=', 'leaves.user_id')
            ->leftJoin('companies', 'companies.id', '=', 'leaves.company_id')
            ->leftJoin('leave_types', 'leave_types.id', '=', 'leaves.leave_type_id')
            ->orderBy('users.name', 'asc')
            ->orderBy('leave_types.name', 'asc')
            ->orderBy('leaves.id', 'asc');

        if ($request->has('user_id') && !empty($request->user_id)) {
            $query->where('leaves.user_id', $request->user_id);
        }

        if ($request->has('company_id') && !empty($request->company_id)) {
            $query->where('leaves.company_id', $request->company_id);
        }

        if ($request->has('leave_type_id') && !empty($request->leave_type_id)) {
            $query->where('leaves.leave_type_id', $request->leave_type_id);
        }

        if ($request->filled('name')) {
            $query->where('leaves.name', $request->name);
        }

        if ($request->filled('date')) {
            $query->whereRaw('? BETWEEN leaves.start_date AND leaves.end_date', [$request->date]);
        }

        if ($request->filled('status')) {
            $query->where('leaves.status', $request->status);
        }

        $totalCount = (clone $query)->count('leaves.id');
        $pendingCount = (clone $query)->where('leaves.status', 'pending')->count('leaves.id');
        $approvedCount = (clone $query)->where('leaves.status', 'approved')->count('leaves.id');
        $rejectedCount = (clone $query)->where('leaves.status', 'rejected')->count('leaves.id');

        $datas = $query->paginate(20);

        if (!Auth::user()->hasPermissionTo('view all leave')) {
            $users = User::where('id', Auth::id())->get();
            $companies = Company::where('id', Auth::user()->company_id)->get();
        } else {
            $users = User::orderBy('name')->where('status', 'active')->role('employee')->get();
            $companies = Company::orderBy('name')->get();
        }

        $leave_types = LeaveType::orderBy('name')->get();

        return view('leaves.index', compact(
            'datas',
            'users',
            'companies',
            'leave_types',
            'totalCount',
            'pendingCount',
            'approvedCount',
            'rejectedCount'
        ));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('leaves.create-modal');
    }

    /**
     * Display the specified resource.
     */
    public function show($role, string $id)
    {
        $leave = Leave::with([
            'user.profile.designation',
            'user.profile.department',
            'user.company',
            'company',
            'leave_type',
        ])->findOrFail($id);

        if (!Auth::user()->hasPermissionTo('view all leave') && $leave->user_id !== Auth::id()) {
            abort(403);
        }

        return view('leaves.show', compact('leave'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!Auth::user()->hasPermissionTo('view all leave')) {
            $request->merge([
                'user_id' => Auth::id(),
                'company_id' => Auth::user()->company_id,
            ]);
        }

        // Only users who can approve/reject leave are allowed to set the status themselves
        if (!Auth::user()->canAny(['approve leave', 'reject leave'])) {
            $request->merge(['status' => 'pending']);
        }

        $leaveType = LeaveType::find($request->leave_type_id);

        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'leave_type_id' => 'required',
            'company_id' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
            'status' => 'required',
            'leave_time' => [Rule::requiredIf((bool) ($leaveType->requires_time ?? false))],
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        // A leave type that requires a time (e.g. Early Leave) can only ever span a single day
        $endDate = ($leaveType->requires_time ?? false) ? $request->start_date : $request->end_date;

        if (Carbon::parse($endDate)->lt(Carbon::parse($request->start_date))) {
            return response()->json([
                'success' => false,
                'message' => 'End date cannot be earlier than start date.'
            ]);
        }

        $exists = Leave::where('user_id', $request->user_id)
            // ->where('leave_type_id', $request->leave_type_id)
            ->where('status', 'pending')
            ->where(function ($q) use ($request, $endDate) {
                $q->whereBetween('start_date', [$request->start_date, $endDate])
                    ->orWhereBetween('end_date', [$request->start_date, $endDate])
                    ->orWhere(function ($q2) use ($request, $endDate) {
                        $q2->where('start_date', '<=', $request->start_date)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending leave of this type within the selected date range!'
            ]);
        }

        $data = Leave::create([
            'user_id' => $request->user_id,
            'leave_type_id' => $request->leave_type_id,
            'company_id' => $request->company_id,
            'start_date' => $request->start_date,
            'end_date' => $endDate,
            'leave_time' => ($leaveType->requires_time ?? false) ? $request->leave_time : null,
            'reason' => $request->reason,
            'status' => $request->status
        ]);

        // Notify super admin / operation users that a new leave request needs their attention
        if ($data->status === 'pending') {
            NotificationService::notifyLeaveApplied($data);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data created successfully.',
            'data' => $data
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return view('leaves.edit-modal', compact('id'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $id = $request->id;
        $data = Leave::findOrFail($id);
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!'
            ]);
        }

        if (!Auth::user()->hasPermissionTo('view all leave') && $data->user_id !== Auth::id()) {
            abort(403);
        }

        if (!Auth::user()->hasPermissionTo('view all leave')) {
            $request->merge([
                'user_id' => Auth::id(),
                'company_id' => Auth::user()->company_id,
            ]);
        }

        // Only users who can approve/reject leave are allowed to change the status themselves
        if (!Auth::user()->canAny(['approve leave', 'reject leave'])) {
            $request->merge(['status' => $data->status ?: 'pending']);
        }

        $leaveType = LeaveType::find($request->leave_type_id);

        $validated = $request->validate([
            'user_id' => 'required',
            'leave_type_id' => 'required',
            'company_id' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
            'status' => 'required',
            'leave_time' => [Rule::requiredIf((bool) ($leaveType->requires_time ?? false))],
        ]);

        // A leave type that requires a time (e.g. Early Leave) can only ever span a single day
        $endDate = ($leaveType->requires_time ?? false) ? $request->start_date : $request->end_date;

        $previousStatus = $data->status;

        $data->update([
            'user_id' => $request->user_id,
            'leave_type_id' => $request->leave_type_id,
            'company_id' => $request->company_id,
            'start_date' => $request->start_date,
            'end_date' => $endDate,
            'leave_time' => ($leaveType->requires_time ?? false) ? $request->leave_time : null,
            'reason' => $request->reason,
            'status' => $request->status
        ]);

        // Email the employee only when the status actually changes to a final decision
        if ($previousStatus !== $data->status && in_array($data->status, ['approved', 'rejected'])) {
            $this->sendLeaveStatusEmail($data);
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data updated successfully.',
                'data' => $data
            ]);
        }

        return redirect('/super-admin/airport')->with('success', 'Data updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        try {
            $item = Leave::find($request->item_id);
            if ($item) {
                if (!Auth::user()->hasPermissionTo('view all leave') && $item->user_id !== Auth::id()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are not allowed to delete this leave request.'
                    ], 403);
                }

                $item->delete();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Info Not Found!'
                ]);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data deleted successfully.'
        ]);
    }

    /**
     * Email the employee once their leave request is approved or rejected.
     */
    private function sendLeaveStatusEmail(Leave $leave): void
    {
        $leave->loadMissing(['user', 'leave_type']);

        if (!$leave->user || !$leave->user->email) {
            return;
        }

        $status = ucfirst($leave->status);
        $leaveTypeName = optional($leave->leave_type)->name ?? 'Leave';
        $dateRange = $leave->start_date === $leave->end_date
            ? Carbon::parse($leave->start_date)->format('M d, Y')
            : Carbon::parse($leave->start_date)->format('M d, Y') . ' to ' . Carbon::parse($leave->end_date)->format('M d, Y');

        sendBrevoMail(
            $leave->user->email,
            $leave->user->name,
            'Leave Request ' . $status,
            "Your <strong>{$leaveTypeName}</strong> request for <strong>{$dateRange}</strong> has been <strong>" . strtolower($status) . '</strong>.'
        );
    }

}
