<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Round-trip routes (owner, 2026-10-18: "I need an option to make round trip route — DAC-ECN-DAC, in one
 * ticket or multiple tickets"). tickets.trip_type: NULL / 'one-way' = as every route so far; 'two-way' =
 * a round trip. From / To stay origin → destination. Return on the same ticket(s) = no extra legs (each
 * ticket of the route is a round-trip ticket); a separate return = return legs (destination → origin)
 * after the outbound ones in ticket_route_legs. Read behind Ticket::roundTripAvailable().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tickets') && ! Schema::hasColumn('tickets', 'trip_type')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->string('trip_type', 12)->nullable()->after('to_airport_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tickets') && Schema::hasColumn('tickets', 'trip_type')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropColumn('trip_type');
            });
        }
    }
};
