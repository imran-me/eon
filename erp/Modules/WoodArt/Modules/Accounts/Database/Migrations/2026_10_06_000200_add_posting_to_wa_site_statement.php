<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wa_site_statement — link each statement row to the journal that posts it.
 *
 * The first migration made the statement a record the owner keeps on the side.
 * This one lets a row be POSTED to the real books (client bill, installment,
 * supplier due, supplier payment) and remembers which journal did it, so the
 * project sheet can say "in the books" / "not yet" per row, and a posting can be
 * reversed by an opposite journal (never deleted).
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Adds nullable columns to a `wa_` table and nothing else. Every existing row
 * stays exactly as it was: no journal id = not posted = the statement-only
 * meaning it already had. Guarded per column so a re-run is harmless. Run with
 * --path, never a bare `migrate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_site_statement')) {
            return;
        }

        Schema::table('wa_site_statement', function (Blueprint $table) {
            if (! Schema::hasColumn('wa_site_statement', 'journal_entry_id')) {
                $table->unsignedBigInteger('journal_entry_id')->nullable()->after('ext_key')->index();
            }
            if (! Schema::hasColumn('wa_site_statement', 'reversal_journal_entry_id')) {
                $table->unsignedBigInteger('reversal_journal_entry_id')->nullable()->after('journal_entry_id');
            }
            if (! Schema::hasColumn('wa_site_statement', 'posted_at')) {
                $table->timestamp('posted_at')->nullable()->after('reversal_journal_entry_id');
            }
            if (! Schema::hasColumn('wa_site_statement', 'bank_id')) {
                $table->unsignedBigInteger('bank_id')->nullable()->after('posted_at');          // the Wood Art bank used
            }
            if (! Schema::hasColumn('wa_site_statement', 'counter')) {
                $table->string('counter', 16)->nullable()->after('bank_id');                    // bank | intercompany
            }
            if (! Schema::hasColumn('wa_site_statement', 'cost_sub_category_id')) {
                $table->unsignedBigInteger('cost_sub_category_id')->nullable()->after('counter'); // supplier due: which cost head
            }
            if (! Schema::hasColumn('wa_site_statement', 'payable_kind')) {
                $table->string('payable_kind', 12)->nullable()->after('cost_sub_category_id');  // supplier | contractor
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('wa_site_statement')) {
            return;
        }

        Schema::table('wa_site_statement', function (Blueprint $table) {
            foreach (['payable_kind', 'cost_sub_category_id', 'counter', 'bank_id', 'posted_at', 'reversal_journal_entry_id', 'journal_entry_id'] as $col) {
                if (Schema::hasColumn('wa_site_statement', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
