<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $super_admin = Role::firstOrCreate(['name' => 'super admin']);
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $accountant = Role::firstOrCreate(['name' => 'accountant']);
        $agent = Role::firstOrCreate(['name' => 'agent']);
        $vendor = Role::firstOrCreate(['name' => 'vendor']);
        $customer = Role::firstOrCreate(['name' => 'customer']);
        $employee = Role::firstOrCreate(['name' => 'employee']);

        $superAdminUser = User::where('email', 'superadmin@epal.com')->first();
        if ($superAdminUser && !$superAdminUser->hasRole('super admin')) {
            $superAdminUser->assignRole('super admin');
        }

        $adminUser = User::where('email', 'admin@epal.com')->first();
        if ($adminUser  && !$adminUser->hasRole('admin')) {
            $adminUser->assignRole('admin');
        }

    }
}
