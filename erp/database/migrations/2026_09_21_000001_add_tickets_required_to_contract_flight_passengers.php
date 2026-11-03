<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A DAC → ECN passenger travels on two tickets (DAC-IST, then IST-ECN),
 * each issued and confirmed on its own. "Ticketed" has to mean every leg
 * is covered, not "any confirmed PNR" — so each passenger says how many
 * tickets their route takes. Defaults to 1, the direct-flight case.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_flight_passengers', function (Blueprint $table) {
            $table->unsignedTinyInteger('tickets_required')->default(1)->after('ticket_class');
        });
    }

    public function down(): void
    {
        Schema::table('contract_flight_passengers', function (Blueprint $table) {
            $table->dropColumn('tickets_required');
        });
    }
};
