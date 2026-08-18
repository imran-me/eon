<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeResignation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ResignationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $resignations = EmployeeResignation::with('user')->orderBy('created_at', 'desc')->paginate(15);
            return response()->json([
                'success' => true,
                'message' => 'Resignations retrieved successfully.',
                'data' => $resignations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while loading the resignations page.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'employee_id' => 'required|exists:users,id',
                'resign_date' => 'required|date',
                'notice_period_days' => 'nullable|integer|min:0',
                'reason' => 'nullable|string',
            ]);
            if ($request->status == 'approved') {
                $request->merge([
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]);
            }
            EmployeeResignation::create([
                'employee_id' => $request->employee_id,
                'resign_date' => $request->resign_date,
                'last_working_day' => $request->last_working_day,
                'resign_type' => $request->resign_type,
                'notice_period_days' => $request->notice_period_days,
                'status' => $request->status ?? 'pending',
                'reason' => $request->reason,
                'exit_note' => $request->exit_note,
                'approved_by' => $request->status === 'approved' ? auth()->id() : null,
                'approved_at' => $request->status === 'approved' ? now() : null,
            ]);
            //user status update can be handled via observer or event listener
            if ($request->status === 'approved') {
                User::findOrFail($request->employee_id)
                    ->update(['status' => 'resigned']);
            }
            $roleSlug = Str::slug(Auth::user()->getRoleNames()->first());
            return response()->json([
                'success' => true,
                'message' => 'Resignation updated successfully.',
                'data' => []
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while submitting the resignation.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $role, string $id)
    {
        try {
            $resignation = EmployeeResignation::findOrFail($id);
            $request->validate([
                'employee_id' => 'required|exists:users,id',
                'resign_date' => 'required|date',
                'notice_period_days' => 'nullable|integer|min:0',
                'reason' => 'nullable|string',
            ]);
            if ($request->status == 'approved' && $resignation->status != 'approved') {
                $request->merge([
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]);
            }
            $resignation->update([
                'employee_id' => $request->employee_id,
                'resign_date' => $request->resign_date,
                'last_working_day' => $request->last_working_day,
                'resign_type' => $request->resign_type,
                'notice_period_days' => $request->notice_period_days,
                'status' => $request->status,
                'reason' => $request->reason,
                'exit_note' => $request->exit_note,
                'approved_by' => $request->status === 'approved' ? auth()->id() : null,
                'approved_at' => $request->status === 'approved' ? now() : null,
            ]);
            //user status update can be handled via observer or event listener
            if ($request->status === 'approved') {
                User::findOrFail($request->employee_id)
                    ->update(['status' => 'resigned']);
            }
            $roleSlug = Str::slug(Auth::user()->getRoleNames()->first());
            return response()->json([
                'success' => true,
                'message' => 'Resignation updated successfully.',
                'data' => []
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the resignation.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
