<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One checkpoint per (bank, date): the real bank statement's closing balance,
 * as the owner reads it off the statement.
 *
 * WHY THIS EXISTS — the dashboard's balance is computed live from
 * journal_items, chronologically. When one day's transaction is missed and
 * entered late on a different day, every day between the two shows a system
 * balance that no longer matches the real bank statement, and nothing today
 * says WHICH day first went wrong — it surfaces weeks later as an
 * unexplained mismatch. This table lets the owner record the statement
 * figure for a day; the comparison against the live ledger balance
 * (Bank::ledgerBalanceForAccount($accountId, false, $date)) is done at read
 * time, never stored here, so fixing a mis-dated entry later makes the
 * mismatch disappear on its own with no recompute step.
 *
 * unique(bank_id, date) is deliberately a PLAIN two-column index with no
 * deleted_at in the key. MySQL never treats NULL as equal to NULL, so a
 * composite unique index that includes deleted_at gives no real protection
 * between two active rows (both have deleted_at = NULL). The plain index
 * gives genuine one-row-per-bank-per-day enforcement; the resave-same-day and
 * delete-then-recreate cases are handled in the controller via
 * withTrashed()->firstOrNew() + restore(), not by the index shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('actual_closing_balance', 15, 2);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['bank_id', 'date'], 'bank_reconciliations_one_per_bank_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
    }
};
