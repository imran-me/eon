<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const MODULES = ['bank account', 'bank transfer'];

    private const ACTIONS = ['view', 'create', 'edit', 'delete'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::MODULES as $module) {
            foreach (self::ACTIONS as $action) {
                $permission = Permission::firstOrCreate([
                    'name' => "{$action} {$module}",
                    'guard_name' => 'web',
                ]);

                Role::whereHas('permissions', fn ($query) => $query->where('name', "{$action} bank"))
                    ->each(fn (Role $role) => $role->givePermissionTo($permission));
            }

            $viewAllPermission = Permission::firstOrCreate([
                'name' => "view all {$module}",
                'guard_name' => 'web',
            ]);

            Role::whereHas('permissions', fn ($query) => $query->where('name', 'view all bank'))
                ->each(fn (Role $role) => $role->givePermissionTo($viewAllPermission));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::MODULES as $module) {
            $permissions = collect(self::ACTIONS)
                ->map(fn ($action) => "{$action} {$module}")
                ->push("view all {$module}");

            Permission::where('guard_name', 'web')->whereIn('name', $permissions)->get()
                ->each(function (Permission $permission) {
                    $permission->roles()->detach();
                    $permission->delete();
                });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
