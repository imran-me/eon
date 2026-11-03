<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cancel return (owner, 2026-10-14): a family passenger flies DAC → ECN, stays
 * on, and the unused return (ECN → DAC) is cancelled — on a round-trip ticket
 * only its return half, on a separate return ticket the whole ticket.
 *
 *   ticket_purchases
 *     family_return          a family ticket whose return may be cancelled
 *     return_cancel_by       the airline's window: cancel the return before this
 *     return_cancelled_at    when the return was cancelled
 *     return_refund_amount   what the airline gave back for the return half
 *                            (round trip; a separate ticket uses refund_amount)
 *     return_penalty         what the airline kept for that half — with the
 *                            refund, what comes off the live ticket's cost
 *     return_reminders       which deadline reminders went out {"3d": …, "1d": …, "0d": …}
 *
 *   contract_flight_passenger_changes
 *     price_revision         the agent's package lowered by a cancelled return —
 *                            credited to him as its own row, never edited in
 *
 * Additive only; every reader checks the columns exist, so a server that has
 * not run this yet simply shows no Cancel return.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('ticket_purchases', 'family_return')) $table->boolean('family_return')->default(false);
            if (! Schema::hasColumn('ticket_purchases', 'return_cancel_by')) $table->dateTime('return_cancel_by')->nullable();
            if (! Schema::hasColumn('ticket_purchases', 'return_cancelled_at')) $table->dateTime('return_cancelled_at')->nullable();
            if (! Schema::hasColumn('ticket_purchases', 'return_refund_amount')) $table->decimal('return_refund_amount', 15, 2)->nullable();
            if (! Schema::hasColumn('ticket_purchases', 'return_penalty')) $table->decimal('return_penalty', 15, 2)->nullable();
            if (! Schema::hasColumn('ticket_purchases', 'return_reminders')) $table->json('return_reminders')->nullable();
        });
        Schema::table('contract_flight_passenger_changes', function (Blueprint $table) {
            if (! Schema::hasColumn('contract_flight_passenger_changes', 'price_revision')) $table->decimal('price_revision', 15, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_purchases', function (Blueprint $table) {
            foreach (['family_return', 'return_cancel_by', 'return_cancelled_at', 'return_refund_amount', 'return_penalty', 'return_reminders'] as $c) {
                if (Schema::hasColumn('ticket_purchases', $c)) $table->dropColumn($c);
            }
        });
        Schema::table('contract_flight_passenger_changes', function (Blueprint $table) {
            if (Schema::hasColumn('contract_flight_passenger_changes', 'price_revision')) $table->dropColumn('price_revision');
        });
    }
};
