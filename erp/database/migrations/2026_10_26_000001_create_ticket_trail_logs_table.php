<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket Trail (owner, 2026-10-26): every correction made to a ticket from its
 * Trail — the ticket, its seller payments, its sale and its changes as they
 * stood, as they became, and a full copy of every ledger row, journal entry and
 * wallet row the correction replaced ("wrong entry get vanished and the edited
 * take their places" — the copy is what keeps that auditable). Nothing reads
 * it to post: it is the history the Trail shows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket_trail_logs')) {
            return;
        }
        Schema::create('ticket_trail_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('ticket_purchase_id')->index();
            $table->string('action', 30)->default('correction');
            $table->text('reason')->nullable();
            $table->text('summary')->nullable();       // one line per thing changed, as shown on confirm
            $table->longText('before')->nullable();    // json: the ticket's editable state before
            $table->longText('after')->nullable();     // json: and after
            $table->longText('replaced')->nullable();  // json: every removed row, verbatim
            $table->longText('added')->nullable();     // json: ids of the rows posted in their place
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_trail_logs');
    }
};
