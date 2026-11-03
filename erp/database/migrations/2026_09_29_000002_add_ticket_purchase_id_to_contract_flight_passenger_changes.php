<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A ticket-level adjustment (owner, 2026-09-29): the SAME PNR taking a loss
 * on cancellation/refund, or an added cost on a later reissue, recorded
 * against that one ticket without moving the passenger between flights —
 * unlike the 'reissue'/'reschedule' kinds already on this table, which are
 * always paired with a flight move.
 *
 * ticket_purchase_id is nullable so every existing row (all flight-move
 * kinds, which have no single ticket) reads exactly as it always has —
 * only rows created by the new "Adjust ticket" action on a passenger's
 * Linked Tickets panel ever set it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_flight_passenger_changes', function (Blueprint $table) {
            $table->unsignedBigInteger('ticket_purchase_id')->nullable()->after('contract_flight_passenger_id');
            $table->foreign('ticket_purchase_id', 'cfp_changes_ticket_purchase_fk')
                ->references('id')->on('ticket_purchases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contract_flight_passenger_changes', function (Blueprint $table) {
            $table->dropForeign('cfp_changes_ticket_purchase_fk');
            $table->dropColumn('ticket_purchase_id');
        });
    }
};
