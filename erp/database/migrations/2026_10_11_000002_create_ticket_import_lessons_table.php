<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket PDF import — what staff taught it. One row = "on a ticket, the
 * value printed next to / under THIS label is THAT field". Only the label
 * text is kept, never a passenger's data. See App\Services\TicketImport\Lessons.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket_import_lessons')) {
            return;
        }
        Schema::create('ticket_import_lessons', function (Blueprint $table) {
            $table->id();
            $table->string('field', 20);          // pnr | eticket | name | passport | flight | from | to | date
            $table->string('anchor', 80);         // the label, upper-cased: "RESERVATION PNR"
            $table->string('position', 8);        // same = after the label on its line; below = the cell under it
            $table->unsignedInteger('hits')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->unique(['field', 'anchor', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_import_lessons');
    }
};
