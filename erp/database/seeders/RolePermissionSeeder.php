<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Define the Modules
        $modules = [
            'branch',
            'users',
            'customers',
            'suppliers',
            'vendor',
            'customer',
            'product',
            'unit',
            'brand',
            'category',
            'subcategory',
            'passport holder',
            'ticket',
            'airline',
            'sales',
            'purchases',
            'expense',
            'department',
            'designation',
            'attendance',
            'salary template',
            'loan',
            'payslip',
            'project',
            'deal',
            'proposal',
            'support ticket',
            'report',
            'portal',
            'ticket purchase',
            'ticket sale',
            'flight category',
            'flight schedule',
            'contract flight',
            'contract flight sale',
            'contract file category',
            'contract file',
            'contract file sale',
            'company setting',
            'dashboard',
            'stock transfer',
            'stock movement',
            'return reference',
            'shift',
            'holiday',
            'leave',
            'leave type',
            'resignation',
            'advance salary',
            'commission',
            'lead source',
            'lead followup',
            'lead reminder',
            'lead manager',
            'lead project category',
            'lead project',
            'project category',
            'contract',
            'deal',
            'proposal',
            'estimate',
            'bank',
            'marketing',
            'geography',
            'role',
            'attendence setting',
            'workspace',
            'board',
            'column',
            'label',
            'task',
            'task project',
            'salary',
            'ticket department',
            'notice',
            'account',
            'visa',
            'journal',
            'ticket direct sale',
            'payment schedule',
            'employee request',
        ];

        // 2. Define the Actions
        $actions = ['view', 'create', 'edit', 'delete'];

        // 3. Create Permissions dynamically
        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$action} {$module}",
                    'guard_name' => 'web' // Explicitly setting guard is safer
                ]);
            }
        }

        // Add unique/singular permissions that don't fit the CRUD pattern
        $extraPermissions = [
            'view dashboard',
            'manage portal',
            'email marketing',
            'manage company setting',
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
            'approve payment schedule',
            'reject payment schedule',
            'verify require assignment',
            'escalate require assignment',
            'approve employee request',
            'disburse employee request',
            'generate noc',
            'fulfill employee request',
            'approve leave',
            'reject leave',
        ];

        foreach ($extraPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Add per-module "view all" permissions so roles can be granted the same full-menu visibility as superadmin.
        foreach ($modules as $module) {
            Permission::firstOrCreate([
                'name' => "view all {$module}",
                'guard_name' => 'web'
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | Role Assignment
    |--------------------------------------------------------------------------
    */

        $superAdmin = Role::firstOrCreate(['name' => 'super admin']);
        $admin      = Role::firstOrCreate(['name' => 'admin']);

        // Super Admin gets everything (including approve/reject payment schedule)
        $superAdmin->syncPermissions(Permission::all());

        $employee = Role::firstOrCreate(['name' => 'employee']);
        $employee->givePermissionTo([
            'view employee request',
            'create employee request',
            'fulfill employee request',
            'view monthly attendance report',
            'view task report',
        ]);

        // Admin gets specific CRUD permissions
        $admin->givePermissionTo([
            'view users',
            'create users',
            'edit users', // No delete
            'view airline',
            'create airline',
            'edit airline',
            'delete airline',
            'view product',
            'create product',
            'edit product',
            'delete product',
            'view dashboard',
            'view report',
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
            'view portal',
            'view payment schedule',
            'create payment schedule',
            'edit payment schedule',
        ]);
    }
}
