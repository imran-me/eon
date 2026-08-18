<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class EmployeeRequestPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view employee request',
            'create employee request',
            'edit employee request',
            'delete employee request',
            'approve employee request',
            'view all employee request',
            'fulfill employee request',
            'disburse employee request',
            'view require assignment',
            'create require assignment',
            'verify require assignment',
            'escalate require assignment',
            // Phase 3
            'generate noc',
            'close employee request',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign all permissions to super-admin
        $superAdmin = Role::where('name', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permissions);
        }

        // Admin gets most permissions
        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $admin->givePermissionTo([
                'view employee request',
                'create employee request',
                'edit employee request',
                'delete employee request',
                'approve employee request',
                'view all employee request',
                'disburse employee request',
                'view require assignment',
                'create require assignment',
                'verify require assignment',
                'escalate require assignment',
                'generate noc',
                'close employee request',
            ]);
        }

        // HR gets approval + view all
        $hr = Role::where('name', 'hr')->orWhere('name', 'HR')->first();
        if ($hr) {
            $hr->givePermissionTo([
                'view employee request',
                'create employee request',
                'edit employee request',
                'approve employee request',
                'view all employee request',
                'view require assignment',
                'create require assignment',
                'verify require assignment',
                'escalate require assignment',
                'generate noc',
                'close employee request',
            ]);
        }

        // Finance role gets disburse
        $finance = Role::where('name', 'finance')->orWhere('name', 'Finance')->first();
        if ($finance) {
            $finance->givePermissionTo([
                'view employee request',
                'view all employee request',
                'disburse employee request',
                'view require assignment',
            ]);
        }

        // Employee gets own view + fulfill
        $employee = Role::where('name', 'employee')->first();
        if ($employee) {
            $employee->givePermissionTo([
                'view employee request',
                'create employee request',
                'fulfill employee request',
                'view require assignment',
            ]);
        }

        $this->command->info('Employee Request permissions seeded successfully.');
    }
}
