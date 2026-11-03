<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConstructionProjectDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $managers = collect(['Eman Hossain', 'Saib Ahmed', 'Md Mohsin', 'Hridoy', 'Rafiq'])->mapWithKeys(function (string $name, int $index) use ($now) {
            $username = 'construction.demo.manager.'.($index + 1);
            DB::table('users')->updateOrInsert(['username' => $username], ['name' => $name, 'company_id' => 3, 'status' => 'active', 'updated_at' => $now, 'created_at' => $now]);

            return [$name => DB::table('users')->where('username', $username)->value('id')];
        });
        $clients = collect(['Mr. Hossain', 'Mr. Saib Ahmed', 'ABC Ltd.', 'Mr. Hridoy', 'Skyline Ltd.', 'Next Enterprise', 'Mr. Rahman', 'EPAL Group', 'Green Valley Ltd.', 'Ocean View'])->mapWithKeys(function (string $name, int $index) use ($now) {
            $username = 'construction.demo.client.'.($index + 1);
            DB::table('users')->updateOrInsert(['username' => $username], ['name' => $name, 'status' => 'active', 'updated_at' => $now, 'created_at' => $now]);

            return [$name => DB::table('users')->where('username', $username)->value('id')];
        });

        $projects = [
            ['Munshi Villa Project', 'PRJ-001', 'Mr. Hossain', 'Eman Hossain', '2025-09-01', '2026-12-31', 71, 'in_progress', 'Residential construction', 7000000, 5000000],
            ['Munshi Villa - Phase 2', 'PRJ-002', 'Mr. Saib Ahmed', 'Saib Ahmed', '2025-08-15', '2026-11-30', 45, 'in_progress', 'Residential construction', 5500000, 2500000],
            ['Uttara Commercial', 'PRJ-003', 'ABC Ltd.', 'Md Mohsin', '2025-07-10', '2026-03-31', 64, 'in_progress', 'Commercial building', 12000000, 9000000],
            ['Banasri Duplex', 'PRJ-004', 'Mr. Hridoy', 'Hridoy', '2025-06-01', '2026-12-31', 35, 'on_hold', 'Residential construction', 8000000, 4000000],
            ['Dhanmondi Apartment', 'PRJ-005', 'Skyline Ltd.', 'Rafiq', '2025-05-20', '2026-04-30', 48, 'in_progress', 'Apartment building', 9000000, 6000000],
            ['Gazipur Warehouse', 'PRJ-006', 'Next Enterprise', 'Eman Hossain', '2025-04-01', '2025-12-31', 100, 'completed', 'Commercial building', 4500000, 4500000],
            ['Mirpur Residential', 'PRJ-007', 'Mr. Rahman', 'Md Mohsin', '2025-03-10', '2025-12-30', 68, 'in_progress', 'Residential construction', 6500000, 4000000],
            ['Chittagong Office', 'PRJ-008', 'EPAL Group', 'Eman Hossain', '2025-02-01', '2025-11-30', 85, 'in_progress', 'Commercial building', 15000000, 12000000],
            ['Sylhet Resort', 'PRJ-009', 'Green Valley Ltd.', 'Saib Ahmed', '2025-01-15', '2026-12-31', 40, 'on_hold', 'Resort construction', 20000000, 10000000],
            ["Cox's Bazar Villa", 'PRJ-010', 'Ocean View', 'Hridoy', '2025-01-01', '2025-12-31', 100, 'completed', 'Residential construction', 9500000, 9500000],
            ['Bashundhara Heights', 'PRJ-011', 'EPAL Group', 'Rafiq', '2025-10-01', '2027-06-30', 22, 'in_progress', 'Apartment building', 18000000, 3000000],
            ['Savar Industrial Shed', 'PRJ-012', 'Next Enterprise', 'Md Mohsin', '2025-11-01', '2027-03-31', 18, 'in_progress', 'Industrial construction', 11000000, 2000000],
        ];

        DB::transaction(function () use ($projects, $managers, $clients, $now) {
            foreach ($projects as $project) {
                [$name, $code, $client, $manager, $start, $end, $progress, $status, $type, $contract, $received] = $project;
                DB::table('expense_departments')->updateOrInsert(['company_id' => 3, 'name' => $name], ['description' => $type, 'status' => 1, 'updated_at' => $now, 'created_at' => $now]);
                $departmentId = DB::table('expense_departments')->where('company_id', 3)->where('name', $name)->value('id');
                DB::table('project_terms')->updateOrInsert(['expense_department_id' => $departmentId], ['project_code' => $code, 'client_party_id' => $clients[$client], 'manager_user_id' => $managers[$manager], 'start_date' => $start, 'end_date' => $end, 'progress_percent' => $progress, 'construction_status' => $status, 'project_type' => $type, 'contract_value' => $contract, 'updated_at' => $now, 'created_at' => $now]);
                $hasRealCollection = DB::table('project_statement')->where('expense_department_id', $departmentId)->where('kind', 'client_installment')->where(fn ($query) => $query->whereNull('ext_key')->orWhere('ext_key', '<>', 'construction-demo-received'))->exists();
                if ($hasRealCollection) {
                    DB::table('project_statement')->where('expense_department_id', $departmentId)->where('ext_key', 'construction-demo-received')->delete();
                } else {
                    DB::table('project_statement')->updateOrInsert(['expense_department_id' => $departmentId, 'ext_key' => 'construction-demo-received'], ['kind' => 'client_installment', 'party' => $client, 'party_id' => $clients[$client], 'label' => 'Demo opening collection', 'entry_date' => $start, 'amount' => $received, 'note' => 'Construction portfolio demo seed', 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        });
    }
}
