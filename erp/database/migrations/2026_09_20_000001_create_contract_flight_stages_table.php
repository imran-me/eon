<?php

use App\Models\ContractFlightStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_flight_stages', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('label', 80);
            $table->string('icon', 60)->default('fa-circle');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_conditional')->default(false);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        // Seed the pipeline as it shipped so existing passengers' stage keys
        // all resolve on the first render after the migration.
        $now = now();
        DB::table('contract_flight_stages')->insert(array_map(fn ($row) => [
            'key' => $row['key'], 'label' => $row['label'], 'icon' => $row['icon'],
            'sort_order' => $row['sort_order'], 'is_conditional' => $row['is_conditional'],
            'is_system' => $row['is_system'], 'created_at' => $now, 'updated_at' => $now,
        ], ContractFlightStage::defaultRows()));
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_flight_stages');
    }
};
