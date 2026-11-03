<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cabin class on a ticket purchase.
 *
 * Deliberately nullable with NO default: every row bought before today was
 * recorded without anyone being asked for a class, and stamping them all
 * 'economy' would put a figure on the screen that nobody ever chose. They
 * read "—" (not recorded) instead; rows created from now on always carry a
 * class because the form requires one.
 *
 * The vocabulary is the app's existing one — economy / business / first, as
 * used by contract_flights, contract_flight_passengers, flight_price_presets
 * and lead_air_tickets — so the word means the same thing on every screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_purchases', function (Blueprint $table) {
            $table->string('ticket_class', 50)->nullable()->after('trip_type');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_purchases', function (Blueprint $table) {
            $table->dropColumn('ticket_class');
        });
    }
};
