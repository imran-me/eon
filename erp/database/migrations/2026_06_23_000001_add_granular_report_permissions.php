<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $reportPermissions = [
        'view monthly attendance report',
        'view task report',
        'view general ledger report',
        'view trial balance report',
        'view profit loss report',
        'view balance sheet report',
        'view account ledger report',
        'view account statement report',
        'view journal entry report',
        'view account balance report',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->reportPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        Role::where('name', 'employee')->first()?->givePermissionTo([
            'view monthly attendance report',
            'view task report',
        ]);

        Role::where('name', 'admin')->first()?->givePermissionTo($this->reportPermissions);
        Role::where('name', 'super admin')->first()?->givePermissionTo($this->reportPermissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->reportPermissions as $permission) {
            $permissionModel = Permission::where('name', $permission)->where('guard_name', 'web')->first();

            if ($permissionModel) {
                $permissionModel->roles()->detach();
                $permissionModel->delete();
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
