<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Undo of a wrong cancellation (owner, 2026-10-26): the cancellation's lines are
 * REMOVED, not reversed — so every removed row is kept here verbatim, with who
 * removed it, when and why. Only this table is created; nothing else changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_flight_undo_logs')) {
            return;
        }
        Schema::create('contract_flight_undo_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_flight_passenger_id')->index();
            $table->string('change_ids', 190);
            $table->text('summary')->nullable();
            $table->string('reason', 1000)->nullable();
            $table->longText('removed');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_flight_undo_logs');
    }
};
