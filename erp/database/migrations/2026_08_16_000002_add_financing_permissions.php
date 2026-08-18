<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions for the Capital & Financing desk (the loan book).
 *
 * Created but deliberately assigned to NO role. A role gains them through
 * Settings > Roles like any other permission, so deploying this migration
 * changes nothing anyone can see until someone grants them — which is what
 * keeps a half-finished desk invisible to the other eleven companies.
 *
 * Follows 2026_07_28_000001_add_contract_file_permissions exactly: view /
 * create / edit / delete, plus "view all financing" for the cross-company
 * view that superadmin needs and a company-locked user must not have.
 */
return new class extends Migration
{
    private array $modules = [
        'financing',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->modules as $module) {
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                Permission::firstOrCreate([
                    'name' => "{$action} {$module}",
                    'guard_name' => 'web',
                ]);
            }

            Permission::firstOrCreate([
                'name' => "view all {$module}",
                'guard_name' => 'web',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->modules as $module) {
            $names = collect(['view', 'create', 'edit', 'delete'])
                ->map(fn ($action) => "{$action} {$module}")
                ->push("view all {$module}");

            Permission::whereIn('name', $names)->where('guard_name', 'web')->get()->each(function ($permission) {
                $permission->roles()->detach();
                $permission->delete();
            });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
