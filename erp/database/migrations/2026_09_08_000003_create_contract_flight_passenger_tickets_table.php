<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_flight_passenger_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_flight_passenger_id');
            $table->foreignId('ticket_purchase_id');
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('contract_flight_passenger_id', 'cf_passenger_ticket_passenger_fk')
                ->references('id')->on('contract_flight_passengers')->cascadeOnDelete();
            $table->foreign('ticket_purchase_id', 'cf_passenger_ticket_purchase_fk')
                ->references('id')->on('ticket_purchases')->cascadeOnDelete();

            $table->unique(['contract_flight_passenger_id', 'ticket_purchase_id'], 'cf_passenger_ticket_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_flight_passenger_tickets');
    }
};
