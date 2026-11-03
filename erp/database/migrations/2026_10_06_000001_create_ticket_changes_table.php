<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket Orders → Change (owner, 2026-10-06): re-issue, reschedule, refund
 * and void on ONE ticket, from Ticketing. This table is the register of
 * those changes — what was done, the figures, who bore it. The money itself
 * is in the party ledgers and journals (source / invoice_id = this row's id);
 * a change on a contract-flight ticket is posted by the contract flight's
 * own code and only recorded here, so nothing is ever posted twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket_changes')) {
            return;
        }
        Schema::create('ticket_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('ticket_purchase_id')->index();
            $table->string('kind', 20);                     // reissue | reschedule | refund | void
            $table->string('source', 20)->default('ticketing'); // ticketing | contract_flight
            $table->unsignedBigInteger('contract_flight_passenger_id')->nullable()->index();
            $table->unsignedBigInteger('contract_flight_passenger_change_id')->nullable();
            $table->dateTime('old_travel_at')->nullable();
            $table->dateTime('new_travel_at')->nullable();
            $table->decimal('penalty', 14, 2)->default(0);          // airline re-issue / reschedule penalty, or what the airline kept on a refund
            $table->decimal('fare_difference', 14, 2)->default(0);
            $table->decimal('service_fee', 14, 2)->default(0);      // our own fee, billed to the customer
            $table->decimal('refund_amount', 14, 2)->nullable();    // what the airline gives back
            $table->string('bearer', 20)->nullable();               // customer | company
            $table->unsignedBigInteger('client_id')->nullable();    // who the ticket was sold to
            $table->decimal('cost_added', 14, 2)->default(0);       // owed to the ticket's seller (Dr 5110)
            $table->decimal('charged', 14, 2)->default(0);          // billed to the customer (Dr AR / Cr 4110)
            $table->decimal('sale_reversed', 14, 2)->default(0);    // agent price taken back on a refund / void
            $table->string('token', 64)->nullable()->unique();      // one click = one change
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_changes');
    }
};
