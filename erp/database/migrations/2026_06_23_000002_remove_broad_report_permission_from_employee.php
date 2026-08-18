<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $employee = Role::where('name', 'employee')->first();

        if ($employee && $employee->hasPermissionTo('view report')) {
            $employee->revokePermissionTo('view report');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::where('name', 'employee')->first()?->givePermissionTo('view report');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
