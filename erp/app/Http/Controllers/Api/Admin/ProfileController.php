<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * Get authenticated admin profile.
     */
    public function show()
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
            'message' => 'Admin profile retrieved successfully.',
            'data' => $user
        ]);
    }

    /**
     * Update authenticated admin profile.
     */
    public function update(Request $request)
    {
         $user = User::findOrFail(Auth::id());

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'required|string|max:15|unique:users,phone,' . $user->id,
            'address' => 'nullable|string|max:500',
            'password' => 'nullable|string|min:8|confirmed',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $payload = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
        ];

        if ($request->filled('password')) {
            $payload['password'] = bcrypt($request->password);
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
            $payload['image'] = $uploadPath . $imageName;
        }

        $user->update($payload);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => $user
        ]);
    }
}
