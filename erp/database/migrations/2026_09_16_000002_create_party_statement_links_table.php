<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Short links to a party's ledger statement PDF — what the WhatsApp message
 * carries (erp.epal.com.bd/s/kX9pQ2Ab). The token is random and unguessable;
 * the row remembers which party and which date range it stands for. One row
 * per (party, range). Expiry was added by the 2026-09-21 migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_statement_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('user_id')->index();       // the party
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->unsignedBigInteger('created_by')->nullable(); // staff user who shared it
            $table->unsignedInteger('opens')->default(0);
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_statement_links');
    }
};
