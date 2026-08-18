<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AgentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            // Fetch agents from the database
            $agents = User::latest()->get();

            $query = User::query();

            // Filter by name, email, or phone
            if ($request->filled('search')) {
                $query->where(function($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%')
                    ->orWhere('phone', 'like', '%' . $request->search . '%');
                });
            }

            // Only agents (assuming you use Spatie Roles)
            $query->whereHas('roles', function ($q) {
                $q->where('name', 'agent');
            });

            $agents = $query->latest()->paginate(10);

            return response()->json([
                'success' => true,
                'message' => 'Agents retrieved successfully.',
                'data' => $agents
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch agents.',
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
            $validator = Validator::make($request->all(), $this->validationRules(), $this->validationMessages());

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors.',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create the user
            $user = User::create([
                'name' => $request->name,
                'company_id' => 2,
                'contact_person' => $request->contact_person,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                // 'password' => Hash::make($request->password??'1234568'),
            ]);

            // Assign role
           $user->assignRole('agent'); // ✅ Direct by name

            return response()->json([
                'success' => true,
                'message' => 'Agent created successfully.',
                'data' => $user
            ]);

        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return response()->json([
                'success' => false,
                'message' => 'Failed to create agent.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request,$role, string $id)
    {
        try {
            // Fetch the agent by ID
            $agent = User::findOrFail($id);

            // Validate the form data
            $validator = Validator::make($request->all(), $this->validationRules($agent->id), $this->validationMessages());
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors.',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update fields
            $agent->name = $request->name;
            $agent->company_id = 2;
            $agent->contact_person = $request->contact_person;
            $agent->email = $request->email;
            $agent->phone = $request->phone;
            $agent->address = $request->address;

            // If password is set, update it
            if ($request->filled('password')) {
                $agent->password = Hash::make($request->password);
            }

            $agent->save();

            return response()->json([
                'success' => true,
                'message' => 'Agent updated successfully.',
                'data' => $agent
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update agent.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            // Fetch the agent by ID
            $agent = User::findOrFail($id);

            // Delete the agent
            $agent->delete();

            return response()->json([
                'success' => true,
                'message' => 'Agent deleted successfully.',
                'data' => []
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete agent.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Validation rules for agent forms.
     */
    protected function validationRules($agentId = null)
    {
        return [
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email' . ($agentId ? ',' . $agentId : ''),
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string|max:500',
        ];
    }

    /**
     * Custom validation messages for agent forms.
     */
    protected function validationMessages()
    {
        return [
            'name.required' => 'The agent name is required.',
            'name.string' => 'The agent name must be a string.',
            'name.max' => 'The agent name may not be greater than :max characters.',
            'contact_person.string' => 'The contact person must be a string.',
            'contact_person.max' => 'The contact person may not be greater than :max characters.',
            'email.required' => 'The email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'The email address is already taken.',
            'phone.required' => 'The phone number is required.',
            'phone.string' => 'The phone number must be a valid string.',
            'phone.max' => 'The phone number may not be greater than :max characters.',
            'address.string' => 'The address must be a string.',
            'address.max' => 'The address may not be greater than :max characters.',
        ];
    }
    public function toggleStatus(string $role, $id)
    {
        $vendor = User::findOrFail($id);
        $vendor->status = $vendor->status === 'active' ? 'inactive' : 'active';
        $vendor->save();

        return response()->json([
            'success' => true,
            'message' => 'Agent status updated successfully.',
            'data' => $vendor
        ]);
    }
}
