<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $modules = [
        'lead project category' => 'project category',
        'lead project' => 'project',
        'task project' => 'project',
    ];

    private array $actions = ['view', 'create', 'edit', 'delete'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->modules as $newModule => $oldModule) {
            foreach (array_merge($this->actions, ['view all']) as $action) {
                $newPermission = "{$action} {$newModule}";
                $oldPermission = "{$action} {$oldModule}";

                Permission::firstOrCreate(['name' => $newPermission, 'guard_name' => 'web']);

                $roles = Role::whereHas('permissions', function ($query) use ($oldPermission) {
                    $query->where('name', $oldPermission);
                })->get();

                foreach ($roles as $role) {
                    $role->givePermissionTo($newPermission);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys($this->modules) as $module) {
            foreach (array_merge($this->actions, ['view all']) as $action) {
                $permission = Permission::where('name', "{$action} {$module}")->first();

                if ($permission) {
                    $permission->roles()->detach();
                    $permission->delete();
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
