<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmployeeProfileController extends Controller
{
    /**
     * Get authenticated employee profile.
     */
    public function show(Request $request)
    {
        $user = User::query()->findOrFail(Auth::id());
        $user->load([
            'company:id,name',
            'shift:id,name,start_time,end_time,holidays',
            'profile.department:id,name',
            'profile.designation:id,name',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Employee profile retrieved successfully.',
            'data' => $user
        ]);
    }

    /**
     * Create authenticated employee profile.
     */
    public function store(Request $request)
    {
        $user = User::query()->findOrFail(Auth::id());

        if ($user->profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profile already exists. Use update endpoint.',
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:15|unique:users,phone,' . $user->id,
            'address' => 'nullable|string|max:500',
            'shift_id' => 'nullable|exists:shifts,id',
            'image' => 'nullable|file|max:2048',

            'department_id' => 'required|exists:departments,id',
            'designation_id' => 'required|exists:designations,id',
            'joining_date' => 'nullable|date',
            'salary' => 'nullable|numeric|min:0',
            'employment_type' => 'required|in:full_time,part_time,contractual',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $userData = $request->only(['name', 'phone', 'address', 'shift_id']);
            if (!empty($userData)) {
                $user->update($userData);
            }

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = uniqid() . '.' . strtolower($image->getClientOriginalExtension());
                $uploadPath = 'image/user/';

                if (!file_exists(public_path($uploadPath))) {
                    mkdir(public_path($uploadPath), 0777, true);
                }

                $image->move(public_path($uploadPath), $imageName);
                $user->image = $uploadPath . $imageName;
                $user->save();
            }

            $profile = EmployeeProfile::create([
                'user_id' => $user->id,
                'department_id' => $request->department_id,
                'designation_id' => $request->designation_id,
                'joining_date' => $request->joining_date,
                'salary' => $request->salary,
                'employment_type' => $request->employment_type,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Employee profile created successfully.',
                'data' => [
                    'user' => $user->fresh(),
                    'profile' => $profile,
                ],
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Update authenticated employee profile.
     */
    public function update(Request $request)
    {
        $user = User::query()->findOrFail(Auth::id());

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:15|unique:users,phone,' . $user->id,
            'address' => 'nullable|string|max:500',
            'shift_id' => 'nullable|exists:shifts,id',
            'image' => 'nullable|file|max:2048',

            'department_id' => 'sometimes|required|exists:departments,id',
            'designation_id' => 'sometimes|required|exists:designations,id',
            'joining_date' => 'nullable|date',
            'salary' => 'nullable|numeric|min:0',
            'employment_type' => 'sometimes|required|in:full_time,part_time,contractual',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $userData = $request->only(['name', 'phone', 'address', 'shift_id']);
            if (!empty($userData)) {
                $user->update($userData);
            }

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = uniqid() . '.' . strtolower($image->getClientOriginalExtension());
                $uploadPath = 'image/user/';

                if (!file_exists(public_path($uploadPath))) {
                    mkdir(public_path($uploadPath), 0777, true);
                }

                if (!empty($user->image) && file_exists(public_path($user->image))) {
                    unlink(public_path($user->image));
                }

                $image->move(public_path($uploadPath), $imageName);
                $user->image = $uploadPath . $imageName;
                $user->save();
            }

            $profile = EmployeeProfile::updateOrCreate(
                ['user_id' => $user->id],
                $request->only([
                    'department_id',
                    'designation_id',
                    'joining_date',
                    'salary',
                    'employment_type',
                ])
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Employee profile updated successfully.',
                'data' => [
                    'user' => $user->fresh(),
                    'profile' => $profile,
                ],
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
