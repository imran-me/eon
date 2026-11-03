<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One passenger can leave a flight and join another later (owner,
 * 2026-09-26): documents not ready, he will not travel on that date — take
 * him off, keep him under Cancelled, then put him on another flight as a
 * reissue or a reschedule, with a charge.
 *
 *  - contract_flight_id becomes nullable. A passenger with no flight is
 *    "off flight": he no longer counts on any batch's revenue, cost or
 *    headcount, and the flight's own builder can no longer see (or delete)
 *    him. The foreign key and its cascade stay exactly as they were.
 *  - ticket_purchases.cancel_note says why a ticket was cancelled from
 *    here: the old ticket stays in Ticket Orders, cancelled at 0, with the
 *    note (owner: "still shown in the ticket purchases, but with a amount
 *    zero, and a note as cancelled").
 *  - contract_flight_passenger_changes is the history of every such move
 *    and the home of its charge. One row per move, so a passenger who is
 *    moved twice carries two charges, each with its own bearer and its own
 *    Party Statement row (ContractFlightPassengerChange::LEDGER_TYPE).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_flight_passengers', function (Blueprint $table) {
            $table->unsignedBigInteger('contract_flight_id')->nullable()->change();
        });

        if (! Schema::hasTable('contract_flight_passenger_changes')) {
            Schema::create('contract_flight_passenger_changes', function (Blueprint $table) {
                // Foreign keys are named by hand: Laravel's generated name for
                // the passenger key is 69 characters, past MySQL's 64.
                $table->id();
                $table->unsignedBigInteger('contract_flight_passenger_id');
                // cancelled | reissue | reschedule | restored
                $table->string('kind', 20);
                $table->unsignedBigInteger('from_flight_id')->nullable();
                $table->unsignedBigInteger('to_flight_id')->nullable();
                $table->string('reason', 30)->nullable();
                $table->text('note')->nullable();
                // What the move costs, and who carries it: 'agent' posts it to
                // the agent's statement, 'company' records it and bills nobody.
                $table->decimal('charge', 12, 2)->nullable();
                $table->string('bearer', 10)->nullable();
                // The agent billed at the time — kept on the row, so a later
                // change of the passenger's agent never moves an old charge.
                $table->unsignedBigInteger('agent_id')->nullable();
                // The live PNRs he held before this move — on a reissue they
                // are cancelled (at 0, still linked); named here either way.
                $table->json('previous_ticket_purchase_ids')->nullable();
                // The flag the passenger carried before he was taken off, so
                // an Undo puts back exactly what was there.
                $table->string('previous_flag', 20)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['contract_flight_passenger_id', 'kind'], 'cfp_changes_passenger_kind_index');
                $table->foreign('contract_flight_passenger_id', 'cfp_changes_passenger_fk')
                    ->references('id')->on('contract_flight_passengers')->cascadeOnDelete();
                $table->foreign('from_flight_id', 'cfp_changes_from_flight_fk')
                    ->references('id')->on('contract_flights')->nullOnDelete();
                $table->foreign('to_flight_id', 'cfp_changes_to_flight_fk')
                    ->references('id')->on('contract_flights')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('ticket_purchases', 'cancel_note')) {
            Schema::table('ticket_purchases', function (Blueprint $table) {
                $table->string('cancel_note', 255)->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ticket_purchases', 'cancel_note')) {
            Schema::table('ticket_purchases', function (Blueprint $table) {
                $table->dropColumn('cancel_note');
            });
        }
        Schema::dropIfExists('contract_flight_passenger_changes');

        // Only restorable while nobody is off a flight — NOT NULL cannot be
        // put back over a passenger who has no flight.
        if (! DB::table('contract_flight_passengers')->whereNull('contract_flight_id')->exists()) {
            Schema::table('contract_flight_passengers', function (Blueprint $table) {
                $table->unsignedBigInteger('contract_flight_id')->nullable(false)->change();
            });
        }
    }
};
