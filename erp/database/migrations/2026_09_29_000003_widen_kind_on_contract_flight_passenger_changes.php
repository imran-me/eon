<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `kind` was sized for the original 4 values (longest 'reschedule', 10
 * chars) plus 'flagged'; the 2026-09-29 ticket-adjustment kinds run longer —
 * 'ticket_reissue_addition' is 23 chars against a 20-char column, so saving
 * one truncated with SQLSTATE[22001]. Widened generously so the next kind
 * added doesn't hit the same wall.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_flight_passenger_changes', function (Blueprint $table) {
            $table->string('kind', 40)->change();
        });
    }

    public function down(): void
    {
        Schema::table('contract_flight_passenger_changes', function (Blueprint $table) {
            $table->string('kind', 20)->change();
        });
    }
};
