<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The last two facts the loan entries need, and then the desk can post.
 *
 * ── 1. financing_loans.disbursement_bank_id ──────────────────────────────
 * Which account the money landed in. The entry for a received loan has three
 * legs — the cash that arrived, the fee the lender kept, and the full debt —
 * and the first of those cannot be written without knowing the account.
 *
 * Nullable, because it is meaningless on the two books that never post: a loan
 * in a person's own name never touched a company account on the way in, and a
 * client instalment plan was never a disbursement at all.
 *
 * ── 2. financing_transactions.tds_amount ─────────────────────────────────
 * Tax withheld from an interest payment and held for the NBR. Only arises when
 * the interest is paid to a PERSON — a director who lent the company money —
 * never to a bank.
 *
 * It is withheld FROM the payment rather than added to it, which is the opposite
 * of how fee_part behaves, so the two must not be conflated:
 *
 *     amount     = principal_part + interest_part + fee_part
 *     bank credit = amount − tds_amount
 *
 * So 50,000 of interest with 5,000 withheld is amount 50,000, tds 5,000, and
 * 45,000 leaving the bank — and the entry still balances because the withheld
 * 5,000 lands in 2280 instead of the bank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financing_loans', function (Blueprint $table) {
            // nullOnDelete, not cascade: closing a bank must never delete the loan
            // that was drawn into it.
            $table->foreignId('disbursement_bank_id')->nullable()->after('processing_fee')
                  ->constrained('banks')->nullOnDelete();
        });

        Schema::table('financing_transactions', function (Blueprint $table) {
            $table->decimal('tds_amount', 15, 2)->default(0)->after('fee_part');
        });
    }

    public function down(): void
    {
        Schema::table('financing_transactions', function (Blueprint $table) {
            $table->dropColumn('tds_amount');
        });

        Schema::table('financing_loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disbursement_bank_id');
        });
    }
};
