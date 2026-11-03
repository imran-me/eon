<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who was at fault for a flight change, and the two costs it can bring
 * (owner, 2026-09-28):
 *
 *   fault            agent | company — who bears the airline's penalty
 *   airline_penalty  what the airline kept when his ticket was cancelled
 *   fare_difference  how much more the new ticket costs than the old one
 *   fare_bearer      agent | company — who bears that difference
 *
 * Company-borne amounts land on the Company Ledger (5171 / 5172); agent-borne
 * ones are billed on the agent's statement and shown there as recovered
 * (4181 / 4182). Nullable: every change recorded before this has none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_flight_passenger_changes', function (Blueprint $table) {
            if (! Schema::hasColumn('contract_flight_passenger_changes', 'fault')) {
                $table->string('fault', 20)->nullable()->after('bearer');
            }
            if (! Schema::hasColumn('contract_flight_passenger_changes', 'airline_penalty')) {
                $table->decimal('airline_penalty', 15, 2)->nullable()->after('fault');
            }
            if (! Schema::hasColumn('contract_flight_passenger_changes', 'fare_difference')) {
                $table->decimal('fare_difference', 15, 2)->nullable()->after('airline_penalty');
            }
            if (! Schema::hasColumn('contract_flight_passenger_changes', 'fare_bearer')) {
                $table->string('fare_bearer', 20)->nullable()->after('fare_difference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contract_flight_passenger_changes', function (Blueprint $table) {
            foreach (['fare_bearer', 'fare_difference', 'airline_penalty', 'fault'] as $col) {
                if (Schema::hasColumn('contract_flight_passenger_changes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
