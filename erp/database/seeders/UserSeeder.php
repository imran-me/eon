<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'superadmin@epal.com'],
            [
                'name' => 'Super Admin',
                'username' => 'superadmin',
                'password' => Hash::make('123456'),
                'is_super_admin' => true,
            ]
        );
        
        User::updateOrCreate(
            ['email' => 'admin@epal.com'],
            [
                'name' => 'Admin',
                'username' => 'admin',
                'password' => Hash::make('123456'),
                'is_super_admin' => false,
            ]
        );        
    }
}
