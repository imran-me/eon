<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * reissue_cost already tracked what a reissue actually cost, but never who
 * pays for it. Owner's rule (2026-09-23): when the reissue is our fault we
 * absorb it (reissue_cost alone, as before); when it is the passenger's/
 * agent's fault, the agent is billed for it separately from agent_price so
 * the original package price stays legible on its own. Null means "not
 * decided yet" — a reissue cost can be recorded before anyone has judged
 * whose fault it was.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_flight_passengers', function (Blueprint $table) {
            $table->string('reissue_bearer', 20)->nullable()->after('reissue_cost');
        });
    }

    public function down(): void
    {
        Schema::table('contract_flight_passengers', function (Blueprint $table) {
            $table->dropColumn('reissue_bearer');
        });
    }
};
