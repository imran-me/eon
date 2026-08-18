<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wa_purchases.project — which job an order was raised for.
 *
 * Separate from the create migration because that is the reference's own
 * history, and the recorded migration names must still match for both to be
 * skipped where the columns already exist.
 *
 * NULLABLE ON PURPOSE. A null project means general stock rather than "unknown"
 * — the reference is explicit that a purchase with no project is buying for the
 * store, and cost control must not count it against any job. Do not backfill it
 * with a guess.
 *
 * Like every other Wood Art reference, it holds the project's ext_id STRING
 * ("WAP-101"), not a foreign key.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Alters one `wa_`-prefixed table and nothing else. Both up() and down() are
 * guarded so a partially-migrated server cannot fail on a re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_purchases') || Schema::hasColumn('wa_purchases', 'project')) {
            return;
        }

        Schema::table('wa_purchases', function (Blueprint $table) {
            $table->string('project', 40)->nullable()->after('supplier');
            $table->index(['company_id', 'project']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('wa_purchases') || ! Schema::hasColumn('wa_purchases', 'project')) {
            return;
        }

        Schema::table('wa_purchases', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'project']);
            $table->dropColumn('project');
        });
    }
};
