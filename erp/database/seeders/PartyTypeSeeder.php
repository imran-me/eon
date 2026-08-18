<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\PartyType;
use Illuminate\Database\Seeder;

class PartyTypeSeeder extends Seeder
{
    // Default party types seeded for every company
    private array $defaults = [
        ['name' => 'Customer', 'slug' => 'customer', 'model_class' => \App\Models\Customer::class],
        ['name' => 'Supplier', 'slug' => 'supplier', 'model_class' => \App\Models\Supplier::class],
        ['name' => 'Vendor',   'slug' => 'vendor',   'model_class' => \App\Models\Supplier::class],
        ['name' => 'Others',   'slug' => 'others',   'model_class' => null],
    ];

    public function run(): void
    {
        $companies = Company::pluck('id');

        foreach ($companies as $companyId) {
            foreach ($this->defaults as $type) {
                PartyType::firstOrCreate(
                    ['company_id' => $companyId, 'slug' => $type['slug']],
                    ['name' => $type['name'], 'model_class' => $type['model_class']]
                );
            }
        }
    }
}
