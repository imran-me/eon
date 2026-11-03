<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cabin class belongs to the passenger, not to the batch.
 *
 * It sat on contract_flights, which said every man on a departure flew the
 * same class — but a contract batch routinely carries one person in business
 * and the rest in economy, and the class is a property of the ticket bought
 * for that person.
 *
 * contract_flights.ticket_class is deliberately NOT dropped: it still holds
 * the batch's default class, which is what the flight price preset is looked
 * up by (FlightPricePresetController::match() requires one) and what a new
 * passenger card starts at. Dropping it would break rate lookup and throw
 * away the class recorded on every flight created before today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_flight_passengers', function (Blueprint $table) {
            $table->string('ticket_class', 50)->default('economy')->after('passport_holder_id');
        });

        // Existing passengers inherit the class their flight was carrying, so
        // no historical row reads as an unexplained "economy" it never chose.
        DB::statement('
            UPDATE contract_flight_passengers p
            JOIN contract_flights f ON f.id = p.contract_flight_id
            SET p.ticket_class = COALESCE(f.ticket_class, "economy")
        ');
    }

    public function down(): void
    {
        Schema::table('contract_flight_passengers', function (Blueprint $table) {
            $table->dropColumn('ticket_class');
        });
    }
};
