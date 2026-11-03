<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The passenger already carried manpower, boarding, reissue and manpower-card
 * costs of its own, but not immigration — which existed only as the flight's
 * immigration_cost_per_pax estimate. On an immigration-wise batch that left a
 * real per-head cost with nowhere to go, so total_travel_cost (and every
 * profit figure built on it) understated what the passenger actually cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_flight_passengers', function (Blueprint $table) {
            $table->decimal('immigration_cost', 12, 2)->nullable()->after('boarding_cost');
        });
    }

    public function down(): void
    {
        Schema::table('contract_flight_passengers', function (Blueprint $table) {
            $table->dropColumn('immigration_cost');
        });
    }
};
