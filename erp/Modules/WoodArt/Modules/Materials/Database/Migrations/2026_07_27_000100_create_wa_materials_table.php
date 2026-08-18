<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wa_materials — board, laminate, hardware, finishes and fabric.
 *
 * ─── WHY THE FILENAME MATTERS ────────────────────────────────────────────
 * Same as wa_projects and wa_clients: the table already exists on the
 * developer machine from an earlier rolled-back attempt (code reverted, tables
 * not). The FILENAME matches the one already recorded in the `migrations`
 * table, so Laravel skips it where the table exists and creates it where it
 * does not. Renaming this file would make it re-run and fail. Columns are a
 * byte-for-byte match of the live schema, verified with SHOW CREATE TABLE.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Creates one `wa_`-prefixed table, touches nothing else, and down() drops
 * only this one. Rows are scoped by `company_id` ('woodart').
 *
 * ─── ON `stock` ──────────────────────────────────────────────────────────
 * `stock` is a STORED column and is deliberately signed, so a mis-issue can
 * show a negative rather than silently clamping to zero and hiding the error.
 *
 * The reference's rule is that stock is written together with a movement row
 * in one transaction, and a reconcile() proves the two agree — "a balance that
 * cannot be explained by its ledger is a balance nobody should trust". The
 * movements ledger (`wa_movements`) is NOT built yet, so for now stock is set
 * directly on the material. When movements land, direct editing of this column
 * must be replaced by an Adjustment movement; the register's form carries the
 * same warning so it is not forgotten.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wa_materials')) {
            return;
        }

        Schema::create('wa_materials', function (Blueprint $table) {
            $table->id();
            $table->string('ext_id', 40);
            $table->string('company_id', 50)->default('woodart');
            $table->string('name', 160);
            $table->string('category', 60)->default('Board');
            $table->string('unit', 20)->default('pcs');
            $table->integer('stock')->default(0);            // signed on purpose
            $table->unsignedInteger('reorder')->default(0);
            $table->unsignedBigInteger('unit_cost')->default(0);
            $table->string('supplier', 160)->nullable();      // vendor NAME, not an id
            $table->date('created_on')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'ext_id']);
            $table->index(['company_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_materials');
    }
};
