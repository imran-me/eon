<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether we handle a traveller's immigration is decided per person, not per
 * departure.
 *
 * One agent hands over three passports: one already holds a work permit and
 * needs no BMET service, one needs it, one takes our immigration support and
 * the others do not. Holding handling_type on contract_flights forced the
 * whole batch into a single answer, so a mixed group had to be split across
 * two flights that were really one.
 *
 * contract_flights.handling_type stays as the batch default: it is what the
 * flight price preset is matched on (FlightPricePresetController::match()
 * requires one) and what a new passenger card starts at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_flight_passengers', function (Blueprint $table) {
            $table->string('handling_type', 30)->default('manpower_wise')->after('ticket_class');
        });

        // Inherit the flight's answer so no existing passenger silently
        // becomes manpower-wise on a batch that was booked immigration-wise.
        DB::statement('
            UPDATE contract_flight_passengers p
            JOIN contract_flights f ON f.id = p.contract_flight_id
            SET p.handling_type = COALESCE(f.handling_type, "manpower_wise")
        ');
    }

    public function down(): void
    {
        Schema::table('contract_flight_passengers', function (Blueprint $table) {
            $table->dropColumn('handling_type');
        });
    }
};
