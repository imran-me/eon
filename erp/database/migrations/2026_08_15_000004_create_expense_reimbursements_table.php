<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One payment back to a member of staff for money they spent themselves.
 *
 * The other half of a reimbursable expense. The expense records that the company
 * owes; this records that it paid, and the two meet on account 2240:
 *
 *   claim approved   Dr expense           Cr 2240 (party = them)
 *   paid back        Dr 2240 (party)      Cr cash pot or bank
 *
 * A row of its own rather than a flag on the expense, because the two do not line
 * up one-to-one: one payment can settle four receipts, and a part payment leaves
 * the rest still owed. What is outstanding is always read from the ledger — Cr
 * minus Dr on 2240 for that person — never from a stored total, for the same
 * reason PettyCashFloat stores no balance: a stored figure is one more thing that
 * can disagree with the ledger, and the ledger would still be right.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_reimbursements', function (Blueprint $table) {
            $table->id();

            // Who was paid.
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();

            // Whose books. A claim filed under one company must be settled out of
            // that company's cash — paying it from another's would move money
            // between two sets of books with no transfer recorded, which is the
            // error config/accounts.php exists to warn about.
            $table->foreignId('company_id')->constrained('companies');

            $table->decimal('amount', 15, 2);

            // Null means cash — out of the pot config/accounts.php names, the same
            // one a cash expense credits. Named bank means it left that account.
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();

            $table->date('paid_on');
            $table->string('note')->nullable();

            // The posting this payment wrote, so the row can point at its own
            // entry instead of the ledger being searched for it.
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            // "What has this person been paid" is the second query every
            // reimbursement screen runs, after "what are they still owed".
            $table->index(['user_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_reimbursements');
    }
};
