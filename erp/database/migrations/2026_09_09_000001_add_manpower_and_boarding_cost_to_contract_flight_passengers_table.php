<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_flight_passengers', function (Blueprint $table) {
            $table->decimal('manpower_cost', 12, 2)->nullable()->after('agent_price');
            $table->decimal('boarding_cost', 12, 2)->nullable()->after('manpower_cost');
        });
    }

    public function down(): void
    {
        Schema::table('contract_flight_passengers', function (Blueprint $table) {
            $table->dropColumn(['manpower_cost', 'boarding_cost']);
        });
    }
};
