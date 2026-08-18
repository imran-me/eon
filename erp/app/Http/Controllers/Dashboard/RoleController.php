<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

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
            return view('role.index', compact('datas', 'permissions'));
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            $permissions = \Spatie\Permission\Models\Permission::all();
            $companies = \App\Models\Company::all();
            return view('role.create', compact('permissions', 'companies'));
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
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

            return redirect()->route('role-permission.index')->with('success', 'Role created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($role,string $id)
    {
        try {
            $role = \Spatie\Permission\Models\Role::findById($id);
            $permissions = \Spatie\Permission\Models\Permission::all();
            $rolePermissions = $role->permissions->pluck('name')->toArray();
            return view('role.edit', compact('role', 'permissions', 'rolePermissions'));
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
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

            return redirect()->route('role.role-permission.edit', ['role' => Str::slug(Auth::user()->getRoleNames()->first())])->with('success', 'Role updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
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
