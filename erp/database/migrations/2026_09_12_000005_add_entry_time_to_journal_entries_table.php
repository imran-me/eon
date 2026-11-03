<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional time-of-day for a journal entry, used only to order same-day rows
 * on the bank ledger the way the real bank statement orders them.
 *
 * journal_entries.date is a DATE column on purpose — the accounting date, not
 * a timestamp — so several transactions posted on the same day are otherwise
 * ordered by insertion (id), which rarely matches the order they actually
 * happened in on the real statement. Nullable and off by default: an entry
 * with no time keeps sorting at the end of its day exactly as before: this
 * column is opt-in per entry, set only when someone wants that entry
 * positioned precisely against its neighbours.
 *
 * Never affects any balance. A day's closing balance is a sum, and a sum
 * does not care what order its terms are added in — this only changes which
 * row a given running-balance figure is printed against within the day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->time('entry_time')->nullable()->after('date');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn('entry_time');
        });
    }
};
