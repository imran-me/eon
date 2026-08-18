<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a loan had already been repaid before loan_transactions existed.
 *
 * Without this, the first recorded repayment on an older loan wipes out its
 * history: paid-to-date switches from "amount − remaining_amount" to "sum of
 * rows", so a ৳30,000 loan sitting at ৳10,000 outstanding jumps to ৳26,000
 * outstanding the moment ৳4,000 comes in.
 *
 * Storing the already-repaid figure once keeps those balances intact while
 * every new movement is still driven by transaction rows — no invented
 * transactions with guessed dates and methods.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('opening_paid_amount', 15, 2)->default(0)->after('remaining_amount');
        });

        // Existing loans: whatever the running figure implies was already repaid.
        // New loans start at 0 and are driven entirely by their rows.
        DB::statement('UPDATE loans SET opening_paid_amount = GREATEST(COALESCE(amount, 0) - COALESCE(remaining_amount, 0), 0)');
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('opening_paid_amount');
        });
    }
};
