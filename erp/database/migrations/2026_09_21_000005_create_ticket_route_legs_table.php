<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A ticket route knows its legs (owner, 2026-09-21). DAC → ECN may really be
 * DAC → IST then IST → ECN: the route carries a VIA airport, and each leg
 * has its own airline, vendor/portal and price and is bought as its own
 * ticket / PNR. One row per leg, in order. A direct route has no rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_route_legs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->foreignId('airline_id')->nullable()->constrained('airlines')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('portal_id')->nullable()->constrained('portals')->nullOnDelete();
            $table->foreignId('from_airport_id')->nullable()->constrained('airports')->nullOnDelete();
            $table->foreignId('to_airport_id')->nullable()->constrained('airports')->nullOnDelete();
            $table->decimal('cost', 12, 2)->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_route_legs');
    }
};
