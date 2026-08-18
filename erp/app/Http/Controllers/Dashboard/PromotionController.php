<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\PromotionRequest;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmployeeProfile;
use App\Models\EmployeePromotion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PromotionController extends Controller
{
    // List all promotions
    public function index(Request $request)
    {
        $departments = Department::orderBy('name')->get();
        $designations = Designation::orderBy('name')->get();
        $users = User::whereNotNull('company_id')
            ->where('is_super_admin', 0)
            ->whereHas('roles', function ($q) {
                $q->where('name', 'employee');
            })
            ->with('shift')
            ->orderBy('name')
            ->get();

        $promotions = EmployeePromotion::with(['user', 'previousDesignation', 'newDesignation'])
            ->when($request->user_id, function ($query, $user_id) {
                $query->where('user_id', $user_id);
            })
            ->when($request->new_designation_id, function ($query, $designation_id) {
                $query->where('new_designation_id', $designation_id);
            })
            ->when($request->new_department_id, function ($query, $dept_id) {
                $query->where('new_department_id', $dept_id);
            })
            ->when($request->effective_from, function ($query, $date) {
                $query->whereDate('effective_from', $date);
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('panel.promotions.index', compact('users', 'departments', 'designations', 'promotions'));
    }

    public function create()
    {
        $users = User::orderBy('name')->with('profile')->where('is_super_admin', 0)->get();
        $designations = Designation::orderBy('name')->get();
        $departments = Department::orderBy('name')->get();
        return view('panel.promotions.create', compact('users', 'designations', 'departments'));
    }

    // Store a new promotion request
    public function store(PromotionRequest $request)
    {        
        $exists = EmployeePromotion::where('user_id', $request->user_id)->where('status', 'pending')->exists();
        if ($exists) {
            return back()->withErrors(['error' => 'User already has a pending promotion']);
        }

        $profile = EmployeeProfile::where('user_id', $request->user_id)->first();
        if (!$profile && empty($request->previous_designation_id) && empty($request->previous_department_id)) {
            return back()->withErrors(['error' => 'Previous Department & Designation data is required!!!']);
        }

        EmployeePromotion::create([
            'user_id' => $request->user_id,
            'previous_designation_id' => $profile?->designation_id ?? $request->previous_designation_id,
            'new_designation_id' => $request->new_designation_id,

            'previous_department_id' => $profile?->department_id ?? $request->previous_department_id,
            'new_department_id' => $request->new_department_id,

            'previous_salary' => $profile?->salary,
            'new_salary' => $request->new_salary ?? $profile?->salary,

            'effective_from' => $request->effective_from,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return redirect()->back()->with('success', 'Promotion request created');
    }


    public function edit($role, $id)
    {
        $users = User::orderBy('name')->where('is_super_admin', 0)->get();
        $designations = Designation::orderBy('name')->get();
        $departments = Department::orderBy('name')->get();
        $promotion = EmployeePromotion::findOrFail($id);
        return view('panel.promotions.edit', compact('users', 'designations', 'departments', 'promotion'));
    }

    public function update(PromotionRequest $request, $role, $id)
    {
        $promotion = EmployeePromotion::find($id);
        if (!$promotion) {
            abort(403, 'Data Not Found!!!');
        }        
        if ($promotion->status !== 'pending') {
            abort(403, 'Cannot modify approved/rejected promotion');
        }
        $exists = EmployeePromotion::where('user_id', $request->user_id)->where('id', '!=', $id)->where('status', 'pending')->exists();
        if ($exists) {
            return back()->withErrors(['error' => 'User already has a pending promotion']);
        }
        $profile = EmployeeProfile::where('user_id', $request->user_id)->first();
        if (!$profile && empty($request->previous_designation_id) && empty($request->previous_department_id)) {
            return back()->withErrors(['error' => 'Previous Department & Designation data is required!!!']);
        }

        $promotion->update([
            'user_id' => $request->user_id,
            'previous_department_id' => $profile?->department_id ?? $request->previous_department_id,
            'new_department_id' => $request->new_department_id,

            'previous_designation_id' => $profile?->designation_id ?? $request->previous_designation_id,
            'new_designation_id' => $request->new_designation_id,

            'new_salary' => $request->new_salary ?? $promotion->previous_salary,
            'effective_from' => $request->effective_from,
            'reason' => $request->reason,
        ]);

        return back()->with('success', 'Promotion updated');
    }


    // The Critical Approval Logic
    public function approve($role, $id)
    {
        $promotion = EmployeePromotion::find($id);
        if ($promotion && ($promotion->status !== 'pending')) {            
            abort(403, 'Promotion already processed');
        }

        DB::transaction(function () use ($promotion) {

            $existingOccupant = EmployeeProfile::where('department_id', $promotion->new_department_id)
                ->where('designation_id', $promotion->new_designation_id)
                ->where('user_id', '!=', $promotion->user_id) // Exclude the person being promoted
                ->first();

            if ($existingOccupant) {
                throw new \Exception("The Department and Designation is already occupied by another employee.");
            }

            $profile = EmployeeProfile::where('user_id', $promotion->user_id)->lockForUpdate()->first();

            // Update promotion
            $promotion->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            // If effective immediately or in past → apply now
            if (!$promotion->effective_from || $promotion->effective_from <= now()->toDateString()) {
                if ($profile) {                    
                    $profile->update([
                        'department_id' => $promotion->new_department_id,
                        'designation_id' => $promotion->new_designation_id,
                        'salary' => $promotion->new_salary,
                    ]);
                } else{
                    EmployeeProfile::create([
                        'user_id'         => $promotion->user_id,
                        'department_id'   => $promotion->new_department_id,
                        'designation_id'  => $promotion->new_designation_id,
                        'joining_date'    => $promotion->effective_from,
                        'salary'          => $promotion->new_salary,
                        'employment_type' => 'full_time',
                    ]);
                }
            }
        });

        return back()->with('success', 'Promotion approved');
    }

    public function reject($role, $id)
    {
        $promotion = EmployeePromotion::find($id);
        if ($promotion && ($promotion->status !== 'pending')) {
            abort(403);
        }

        $promotion->update([
            'status' => 'rejected',
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Promotion rejected');
    }

    public function destroy($role, $id)
    {
        $promotion = EmployeePromotion::findOrFail($id);
        // if ($promotion && ($promotion->status !== 'pending')) {        
        //     abort(403, 'Cannot delete processed promotion');
        // }

        DB::transaction(function () use ($promotion) {
            $profile = EmployeeProfile::where('user_id', $promotion->user_id)
                ->where('department_id', $promotion->new_department_id)
                ->where('designation_id', $promotion->new_designation_id)
                ->where('joining_date', $promotion->effective_from)
                ->first();

            if ($profile) {
                // $profile->delete();
            }
            $promotion->delete();
        });

        return back()->with('success', 'Promotion removed');
    }
}
