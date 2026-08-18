<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suggested posting accounts — the bridge between the loan book and the ledger.
 *
 * The desk still posts nothing: the owner's rule stands, and these columns
 * write no journal. What they do is remove the guesswork from the entry that
 * DOES hit the books.
 *
 * Money on a loan reaches the ledger through Manage Banks, where every deposit
 * and withdrawal asks for a contra account. Choosing the wrong one is how a
 * borrowing ends up as income, or a client instalment gets booked twice. So the
 * account is decided ONCE, when the loan is set up by someone who knows what it
 * is, and every screen afterwards simply tells the person recording the payment
 * which code to post against.
 *
 * Two accounts, because a repayment is two things:
 *   gl_account_id           the principal — a liability we are paying down, or a
 *                           receivable we are collecting.
 *   gl_interest_account_id  the interest — an expense on money we borrowed, or
 *                           income on money we lent.
 *
 * Both nullable: a loan set up before this existed, or one nobody has classified
 * yet, keeps working and simply shows no hint.
 *
 * nullOnDelete rather than cascade — losing an account from the chart must never
 * delete the loan that referenced it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financing_loans', function (Blueprint $table) {
            $table->foreignId('gl_account_id')->nullable()->after('security')
                  ->constrained('accounts')->nullOnDelete();
            $table->foreignId('gl_interest_account_id')->nullable()->after('gl_account_id')
                  ->constrained('accounts')->nullOnDelete();
        });

        Schema::table('financing_capital_movements', function (Blueprint $table) {
            // Owner money has no interest side, so one account is enough.
            $table->foreignId('gl_account_id')->nullable()->after('method')
                  ->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('financing_capital_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gl_account_id');
        });

        Schema::table('financing_loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gl_interest_account_id');
            $table->dropConstrainedForeignId('gl_account_id');
        });
    }
};
