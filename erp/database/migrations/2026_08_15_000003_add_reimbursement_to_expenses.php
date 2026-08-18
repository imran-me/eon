<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who the company owes for this expense, when it owes anyone.
 *
 * Set only when someone paid out of their OWN money on the company's behalf. It
 * is the fourth settlement source, and the only one that creates a debt:
 *
 *   petty_cash_float_id  → Cr the float          (company cash already in a pocket)
 *   bank_id              → Cr that bank          (company money in an account)
 *   neither              → Cr the cash pot       (company money in the drawer)
 *   reimburse_to_user_id → Cr Employee Payable   (THEIR money — we owe them)
 *
 * A separate column rather than a new `payment_mode` value on purpose:
 * payment_mode answers "how was it paid" (cash, bank transfer, mobile banking)
 * and this answers "whose money was it". Two different questions, and seven
 * screens already read that enum.
 *
 * Nullable, so every expense that exists keeps meaning exactly what it meant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('reimburse_to_user_id')
                ->nullable()
                ->after('petty_cash_float_id')
                ->constrained('users')
                // Deliberately not cascading. A claim outlives the staff record it
                // came from — money owed to someone who has left is still owed,
                // and the ledger entry naming them stays either way.
                ->nullOnDelete();

            // "What is still owed, and to whom" is the query this feature exists
            // to answer; it runs on every reimbursement screen.
            $table->index('reimburse_to_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['reimburse_to_user_id']);
            $table->dropIndex(['reimburse_to_user_id']);
            $table->dropColumn('reimburse_to_user_id');
        });
    }
};
