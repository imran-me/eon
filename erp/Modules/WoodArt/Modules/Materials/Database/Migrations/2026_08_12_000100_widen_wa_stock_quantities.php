<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wood Art buys and issues fractions: 12.5 kg adhesive, 2.5 litres lacquer,
 * 1.5 sheets of board (owner confirmed 2026-08-12). Widen the three stock
 * quantities to decimal(14,3).
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Touches only `wa_`-prefixed columns. No foreign key, trigger or view depends
 * on them, and neither column carries an index, so nothing is rebuilt beyond
 * the columns themselves. `wa_purchase_lines` belongs to Procurement but is
 * widened here on purpose: one file means one `--path` on the server, and one
 * server step is one fewer chance to run half a schema change.
 *
 * ─── EVERY STATEMENT IS GUARDED ──────────────────────────────────────────
 * `wa_movements` and `wa_purchase_lines` have no create-migration anywhere in
 * this repo — they exist on the dev box only as residue of a rolled-back
 * attempt, and may be absent on the target server.
 *
 * READ THIS IF YOU ARE THE PERSON WHO FINALLY WRITES THOSE TWO TABLES:
 * this migration is recorded in `migrations` even where it skipped a missing
 * table, so it will NEVER run again. Declare `decimal(14,3)` in your create
 * migration itself. Copying the dev residue (`wa_purchase_lines.qty`
 * decimal(14,2), `wa_movements.qty` int(11)) would leave that server
 * permanently narrower than dev, with nothing left to widen it.
 *
 * ─── `--pretend` CANNOT REVIEW THIS FILE ─────────────────────────────────
 * Under `--pretend` a pretended SELECT returns no rows, so `Schema::hasTable()`
 * returns FALSE, all three guards fail, and the output is three
 * information_schema queries and not one ALTER. That reads as "this migration
 * does nothing", which is the opposite of the truth. Prove the change with
 * SHOW CREATE TABLE afterwards, never with --pretend beforehand.
 *
 * ─── NOTES ON THE STATEMENTS ─────────────────────────────────────────────
 * Laravel 11+ `change()` DROPS any attribute not restated, so signedness,
 * nullability and DEFAULT are repeated on purpose on every line.
 * Blueprint has no `unsignedDecimal()` in Laravel 12 — use
 * `decimal()->unsigned()`.
 *
 * Lossless: signed int(11) spans 10 integer digits, decimal(14,3) holds 11.
 * Existing whole values become N.000 and every rendered figure is unchanged.
 *
 * down() is schema-reversible but NOT data-reversible: it destroys the 0.001
 * resolution and, under STRICT_TRANS_TABLES, errors (1265) on a row carrying a
 * real fraction rather than rounding it. Recover with a forward-fix migration,
 * not a rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wa_materials')) {
            Schema::table('wa_materials', function (Blueprint $t) {
                // int(11) NOT NULL DEFAULT 0 -> decimal(14,3) NOT NULL DEFAULT 0.
                // SIGNED on purpose: a mis-issue must show negative, not clamp.
                $t->decimal('stock', 14, 3)->default(0)->change();

                // int(10) unsigned -> decimal(14,3) unsigned. Moves with stock
                // because it is stock's comparand in Material::is_low.
                $t->decimal('reorder', 14, 3)->unsigned()->default(0)->change();
            });
        }

        if (Schema::hasTable('wa_movements')) {
            // int(11) NOT NULL, no default -> decimal(14,3) NOT NULL, no default.
            Schema::table('wa_movements', fn (Blueprint $t) => $t->decimal('qty', 14, 3)->change());
        }

        if (Schema::hasTable('wa_purchase_lines')) {
            // decimal(14,2) -> decimal(14,3): gains a digit of scale, gives up
            // one integer digit (max 99,999,999,999.999), which no plausible
            // quantity reaches. All three quantity columns then match.
            Schema::table('wa_purchase_lines', fn (Blueprint $t) => $t->decimal('qty', 14, 3)->default(0)->change());
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('wa_materials')) {
            Schema::table('wa_materials', function (Blueprint $t) {
                $t->integer('stock')->default(0)->change();              // signed, as it was
                $t->unsignedInteger('reorder')->default(0)->change();
            });
        }

        if (Schema::hasTable('wa_movements')) {
            Schema::table('wa_movements', fn (Blueprint $t) => $t->integer('qty')->change());
        }

        if (Schema::hasTable('wa_purchase_lines')) {
            Schema::table('wa_purchase_lines', fn (Blueprint $t) => $t->decimal('qty', 14, 2)->default(0)->change());
        }
    }
};
