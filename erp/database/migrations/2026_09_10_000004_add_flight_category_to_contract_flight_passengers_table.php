<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The category belongs to the traveller, not the departure.
 *
 * Cyprus Work Permit, Cyprus Student, Cyprus Family and Umrah are reasons a
 * particular person is flying — one agent's three passports routinely carry
 * three different ones on the same aircraft. Holding it only on
 * contract_flights forced the batch into one answer.
 *
 * Only the category is stored, not the type: a FlightCategory already owns its
 * flight_category_type_id, so a second column here could only ever drift from
 * it. Read the type through the category relation.
 *
 * contract_flights.flight_category_id stays as the batch default — it is what
 * the price preset is matched on (FlightPricePresetController::match()) and
 * what a new passenger card starts at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_flight_passengers', function (Blueprint $table) {
            $table->foreignId('flight_category_id')->nullable()->after('handling_type')
                ->constrained('flight_categories')->nullOnDelete();
        });

        // Inherit the batch's category so no existing passenger reads as blank.
        DB::statement('
            UPDATE contract_flight_passengers p
            JOIN contract_flights f ON f.id = p.contract_flight_id
            SET p.flight_category_id = f.flight_category_id
        ');
    }

    public function down(): void
    {
        Schema::table('contract_flight_passengers', function (Blueprint $table) {
            $table->dropForeign(['flight_category_id']);
            $table->dropColumn('flight_category_id');
        });
    }
};
