<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $datas = \Spatie\Permission\Models\Role::with('permissions')->get();
            $permissions = \Spatie\Permission\Models\Permission::all();

            return response()->json([
                'success' => true,
                'message' => 'Roles retrieved successfully.',
                'data' => $datas,
                'permissions' => $permissions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve roles: ' . $e->getMessage()
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
                'name' => 'required|unique:roles,name',
                'permissions' => 'required|array',
            ]);

            $role = \Spatie\Permission\Models\Role::create(['name' => $request->name]);
            $role->syncPermissions($request->permissions);

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request,$role, string $id)
    {
        try {
            $request->validate([
                'name' => 'required|unique:roles,name,' . $id,
                'permissions' => 'required|array',
            ]);

            $role = \Spatie\Permission\Models\Role::findById($id);
            $role->update(['name' => $request->name]);
            $role->syncPermissions($request->permissions);

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $role = \Spatie\Permission\Models\Role::findById($id);
            $role->delete();

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete role: ' . $e->getMessage()
            ], 500);
        }
    }
}

