<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Log\VendorLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class VendorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            // Fetch vendors from the database
            $vendors = User::latest()->get();

            $query = User::query();

            // Filter by name, email, or phone
            if ($request->filled('search')) {
                $query->where(function($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%')
                    ->orWhere('phone', 'like', '%' . $request->search . '%');
                });
            }

            // Only vendors (assuming you use Spatie Roles)
            $query->whereHas('roles', function ($q) {
                $q->where('name', 'vendor');
            });

            $vendors = $query->latest()->paginate(10);

            return response()->json([
                'success' => true,
                'message' => 'Vendors retrieved successfully.',
                'data' => $vendors
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch vendors.',
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
            // Validate the form data
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'contact_person' => 'nullable|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'password' => 'required|string|min:6|confirmed',
                // 'role' => 'required|exists:roles,name',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->with('error', 'Please fix the validation errors.')->withErrors($validator)->withInput();
            }

            // Create the user
            $user = User::create([
                'name' => $request->name,
                'company_id' => 2,
                'contact_person' => $request->contact_person,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'password' => Hash::make($request->password??'1234568'),
            ]);

            // Assign role
           $user->assignRole('vendor'); // ✅ Direct by name

            return response()->json([
                'success' => true,
                'message' => 'Vendor created successfully.',
                'data' => $user
            ]);

        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return response()->json([
                'success' => false,
                'message' => 'Failed to create vendor.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $role, User $vendor)
    {
        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'contact_person' => 'nullable|string|max:255',
                'email' => 'required|email|unique:users,email,' . $vendor->id,
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'password' => 'nullable|string|min:6|confirmed',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->with('error', 'Please fix the validation errors.')->withErrors($validator)->withInput();
            }

            $before = $vendor->only(['name', 'email', 'phone', 'address']);

            // Update fields
            $vendor->name = $request->name;
                $vendor->company_id = 2;
            $vendor->contact_person = $request->contact_person;
            $vendor->email = $request->email;
            $vendor->phone = $request->phone;
            $vendor->address = $request->address;

            // If password is set, update it
            if ($request->filled('password')) {
                $vendor->password = Hash::make($request->password);
            }

            $vendor->save();

            $after = $vendor->only(['name', 'email', 'phone', 'address']);

            // Log the changes
            VendorLog::create([
                'vendor_id' => $vendor->id,
                'changed_by' => auth()->id(),
                'action' => 'updated',
                'before' => $before,
                'after' => $after,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Vendor updated successfully.',
                'data' => $vendor
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update vendor.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
         return response()->json([
             'success' => false,
             'message' => 'hey bro! What are you doing here?.',
             'data' => []
         ], 422);
    }

    public function restore(string $role, int $logId)
    {
        $log = VendorLog::findOrFail($logId);
        $vendor = User::findOrFail($log->vendor_id);

        $vendor->update($log->before);

        return response()->json([
            'success' => true,
            'message' => 'Vendor restored to selected version.',
            'data' => $vendor
        ]);
    }

    public function toggleStatus(string $role, $id)
    {
        $vendor = User::findOrFail($id);

        $vendor->status = $vendor->status === 'active' ? 'inactive' : 'active';
        $vendor->save();

        return response()->json([
            'success' => true,
            'message' => 'Vendor status updated successfully.',
            'data' => $vendor
        ]);
    }
}
