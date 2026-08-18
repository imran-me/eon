<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The three facts a loan entry cannot be written without.
 *
 * ── THIS MIGRATION POSTS NOTHING ──────────────────────────────────────────
 * The owner's rule (create_financing_tables, 2026-07-15) stands untouched: this
 * desk writes no journal entries. What these columns do is record facts that
 * are impossible to recover later — and that any correct entry, whether typed
 * by hand in the Journal screen today or posted by a service if the owner ever
 * approves one, cannot be written without.
 *
 * They are here because all three are UNKNOWABLE after the fact. A month later
 * nobody can tell you whether the boss paid February's instalment himself or
 * whether it left the company's account, and getting that wrong in either
 * direction is a real error in the books.
 *
 * ── 1. financing_loans.processing_fee ────────────────────────────────────
 * A bank sanctions 1,000,000, deducts a 15,000 arrangement fee, and credits
 * 985,000. Three different numbers, and the desk could previously store only
 * one of them.
 *
 * `principal` stays the SANCTIONED amount, because that is what must be repaid
 * and therefore what the liability is worth. The fee is a cost incurred on day
 * one, not a reduction of the debt — netting it off would show a 985,000 loan
 * that the bank will still want 1,000,000 back on, and would hide the fee from
 * the P&L entirely.
 *
 * ── 2. financing_transactions.paid_by ────────────────────────────────────
 * Only meaningful on a loan in a person's own name (`taken_for = personal`),
 * and there it decides whether anything is recorded at all:
 *
 *   personal — he paid his own instalment from his own pocket. The company's
 *              money never moved and the company owes nothing, so there is
 *              nothing to record. This is the normal case.
 *   company  — the instalment left a company account. Company money is gone,
 *              so it must be recorded — as money taken out for personal use,
 *              never as a company expense and never split into interest.
 *
 * Nullable with NO default, deliberately. A default of 'company' would let an
 * unanswered form invent a withdrawal that never happened, and a fabricated
 * bank movement is worse than a missing one: the missing one is caught by the
 * next bank reconciliation, the invented one makes the books disagree with the
 * statement while looking complete. Unanswered therefore means "not stated",
 * and nothing may be posted from it.
 *
 * On a company loan the question does not arise — the company pays, always —
 * so the column simply stays null there.
 *
 * ── 3. financing_transactions.fee_part ───────────────────────────────────
 * Settle a loan early and the bank adds a closure charge: 250,000 of principal
 * plus a 5,000 charge leaves the account as one 255,000 payment. Without this
 * the whole 255,000 would reduce the loan, and 2510 would end up 5,000 short of
 * zero — which reads exactly like a mis-split instalment and sends whoever
 * reconciles it hunting through the schedule for an error that is not there.
 *
 * Taken off the top: amount − fee_part is what gets split into principal and
 * interest, so the three parts always sum back to the amount paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financing_loans', function (Blueprint $table) {
            $table->decimal('processing_fee', 15, 2)->default(0)->after('down_payment');
        });

        Schema::table('financing_transactions', function (Blueprint $table) {
            $table->decimal('fee_part', 15, 2)->default(0)->after('interest_part');

            // No default — see the note above on why "unanswered" must stay
            // distinguishable from "the company paid it".
            $table->enum('paid_by', ['company', 'personal'])->nullable()->after('method');
        });
    }

    public function down(): void
    {
        Schema::table('financing_transactions', function (Blueprint $table) {
            $table->dropColumn(['paid_by', 'fee_part']);
        });

        Schema::table('financing_loans', function (Blueprint $table) {
            $table->dropColumn('processing_fee');
        });
    }
};
