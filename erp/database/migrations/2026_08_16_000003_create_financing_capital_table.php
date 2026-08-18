<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner money in and out — the Capital half of the Capital & Financing desk.
 *
 * Separate from `financing_loans` on purpose. A loan has a principal, a rate, a
 * tenure and a schedule of instalments; capital has none of those. Forcing both
 * into one table would mean half the columns are always null and the register
 * would have to explain which ones apply.
 *
 * WHAT BELONGS HERE (the owner's cases):
 *   investment — money put INTO the company: working capital, or covering a
 *                negative balance.
 *   drawings   — money taken OUT as profit.
 *
 * WHAT DOES NOT: "taking money from the company as a loan". That is a loan —
 * it is expected back, and it has a schedule — so it belongs in the lent book
 * of `financing_loans` with the owner as the counterparty.
 *
 * ── SAME ACCOUNTING RULE AS THE LOAN BOOK ────────────────────────────────
 * This desk records the ARRANGEMENT and posts no journal entry. The money
 * itself is moved in Manage Banks, which already posts correctly — so a
 * capital injection is entered here for the record and as a bank deposit for
 * the books. Posting in both places would count the same taka twice, which is
 * the same reason the loan book stays off the ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financing_capital_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // 'investment' = money in, 'drawings' = money out. One column rather
            // than two tables: every other field is identical and the register
            // reads better as one list with a direction.
            $table->enum('kind', ['investment', 'drawings'])->index();

            // Who put it in or took it out. Free text — an investor is not
            // necessarily a user or a party record in this system.
            $table->string('person_name');
            $table->string('party_type', 40)->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->index(['party_type', 'party_id']);

            // Why. The owner's two investment cases read very differently on a
            // report — topping up working capital is not the same as covering an
            // overdrawn account — so the reason is a first-class field.
            $table->string('reason')->nullable()->index();

            $table->decimal('amount', 15, 2);
            $table->date('date')->index();

            $table->enum('method', ['cash', 'bank', 'adjustment'])->default('bank');
            // Recorded for this desk's own reporting. No journal is written for
            // it — the matching deposit or withdrawal is entered in Manage Banks.
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();

            $table->string('reference')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'kind', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financing_capital_movements');
    }
};
