<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The daily cost fund — how much a company MAY spend in cash on one day.
 *
 * WHAT THIS IS NOT — it is not money. Nothing here ever reaches the ledger, and
 * that is deliberate: a budget is a permission, not a transaction, so it has no
 * debit and no credit and no account to sit in. The cash itself is already fully
 * modelled — 1013 Office Cash for the drawer and 1015 Petty Cash Float for what a
 * custodian is holding, told apart by `journal_entries.company_id`. Adding a
 * second thing that also means "this company's cash" would give the same taka two
 * homes and the two would disagree the first time anyone looked.
 *
 * So this table answers one question and only one: on a given day, what was this
 * company's spending ceiling? What was actually spent is read from `expenses`,
 * where it already lives.
 *
 * WHY EFFECTIVE-DATED RATHER THAN A ROW PER DAY — a ceiling is a standing policy
 * ("Travels gets 2,000 a day"), not a daily chore. A row per company per day would
 * mean 12 rows every morning forever, and a day nobody filled in would read as a
 * fund of zero rather than "unchanged". Instead each row says "from this date, the
 * figure is X", and the fund on any date is the row in force on it. Changing the
 * policy writes one new row and closes the old one, so last month's report still
 * shows last month's ceiling instead of today's.
 *
 * THE ONE INVARIANT — rows for a company never overlap. DailyFundService::save()
 * is the only writer and maintains it by closing the row it supersedes. Because
 * of that, "the fund on date D" is a single row, not a max() over candidates.
 *
 * RESETTING — nothing resets. The ceiling stands until superseded; it is the
 * SPEND that is measured per day, so every morning starts at zero used without
 * anything having to happen. Cash does not reset either, which is exactly why the
 * two are kept apart: yesterday's unspent taka is still in the custodian's pocket,
 * but yesterday's unspent allowance is gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_daily_funds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // The ceiling itself. Zero is a real answer — "this company may spend
            // nothing in cash" — which is why no fund at all is the absence of a
            // row rather than a 0 stored here.
            $table->decimal('amount', 15, 2)->default(0);

            // The window this figure is in force for. effective_to NULL means
            // "still in force", which is the normal state; it is filled in only
            // when a later row supersedes this one.
            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            // The lookup this table exists for: one company, one date.
            $table->index(['company_id', 'effective_from', 'effective_to'], 'company_daily_funds_in_force');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_daily_funds');
    }
};
