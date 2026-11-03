<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Twin of journal_entries.entry_time (2026_09_12_000005) — the custom time
 * picked on the transfer form. Stored on the transfer itself (not only on
 * the journal entry) because a transfer can be saved as 'pending' and only
 * get its JournalEntry later, when it's marked completed; without a column
 * here that later post would have no time to carry over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transfers', function (Blueprint $table) {
            $table->time('entry_time')->nullable()->after('payment_date');
        });
    }

    public function down(): void
    {
        Schema::table('bank_transfers', function (Blueprint $table) {
            $table->dropColumn('entry_time');
        });
    }
};
