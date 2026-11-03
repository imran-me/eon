<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for correcting a journal entry's date — old date, new date,
 * who, when, and why.
 *
 * This is the one journal-entry edit in the app that gets a history trail.
 * updateRemarks() (BankControllerV2) overwrites the description/note in
 * place with no record of the previous text, because a wrong word carries no
 * financial weight. A wrong date is different: it decides which day a
 * transaction counts against reconciliation, so silently moving it would
 * leave no trace of what the ledger used to say. Append-only — no soft
 * deletes, because nothing in the app ever updates or deletes a row here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entry_date_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->date('old_date');
            $table->date('new_date');
            $table->text('reason')->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_date_edits');
    }
};
