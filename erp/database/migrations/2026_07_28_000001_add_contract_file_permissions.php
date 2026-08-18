<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $modules = [
        'contract file category',
        'contract file',
        'contract file sale',
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
