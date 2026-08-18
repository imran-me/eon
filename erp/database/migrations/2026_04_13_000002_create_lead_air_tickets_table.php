<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_air_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('route_from', 10)->nullable();   // e.g. DAC
            $table->string('route_to', 10)->nullable();     // e.g. DXB
            $table->date('travel_date')->nullable();
            $table->unsignedSmallInteger('pax_count')->default(1);
            $table->enum('ticket_class', ['economy', 'business', 'first'])->default('economy');
            $table->string('preferred_airline')->nullable();
            $table->decimal('quoted_price', 12, 2)->nullable();
            $table->enum('payment_status', ['pending', 'partial', 'paid'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_air_tickets');
    }
};
