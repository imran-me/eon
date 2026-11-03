<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LeaveController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if('employee' == Str::slug(Auth::user()->getRoleNames()->first())) {
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
            $query->whereDate('leaves.status', $request->status);
        }

        $datas = $query->paginate(20);                
        // $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        // $companies = Company::orderBy('name')->get();
        // $leave_types = LeaveType::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Leave data retrieved successfully.',
            'data' => $datas
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $leaveType = LeaveType::find($request->leave_type_id);

        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'leave_type_id' => 'required|exists:leave_types,id',
            'company_id' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
            'status' => 'required',
            'leave_time' => [
                Rule::requiredIf((bool) ($leaveType->requires_time ?? false)),
                'nullable',
                'date_format:H:i,H:i:s',
            ],
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ]);
        }

        $leaveType = LeaveType::findOrFail($request->leave_type_id);

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

        return response()->json([
            'success' => true,
            'message' => 'Data created successfully.',
            'data' => $data
        ]);
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
        $data = Leave::findOrFail($id);
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!'
            ]);
        }
        $leaveType = LeaveType::find($request->leave_type_id);

        $validated = $request->validate([
            'user_id' => 'required',
            'leave_type_id' => 'required|exists:leave_types,id',
            'company_id' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
            'status' => 'required',
            'leave_time' => [
                Rule::requiredIf((bool) ($leaveType->requires_time ?? false)),
                'nullable',
                'date_format:H:i,H:i:s',
            ],
        ]);

        $leaveType = LeaveType::findOrFail($request->leave_type_id);

        $endDate = ($leaveType->requires_time ?? false) ? $request->start_date : $request->end_date;

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

        return response()->json([
            'success' => true,
            'message' => 'Data updated successfully.',
            'data' => $data
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $item = Leave::find($id);
            if ($item) {
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

}
