<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wa_spaces — the rooms a Wood Art project is divided into.
 *
 * ─── WHY THIS FILENAME ───────────────────────────────────────────────────
 * Identical to the name already recorded in the developer machine's
 * `migrations` table (batch 89), left behind by the rolled-back earlier
 * attempt: the rollback reverted the code but not the database, so the table
 * and its 11 rows survived while this file did not. Laravel matches migrations
 * by name, so:
 *   · dev machine, table already there → recorded as run, skipped, data intact
 *   · production, which has no wa_ scope tables → runs once and creates it
 * Renaming this file would make it re-run on the dev machine and fail.
 *
 * The column definitions are a byte-for-byte match of the live schema
 * (verified against SHOW CREATE TABLE), so the two can never drift.
 *
 * SHAPE NOTES:
 *  - `project` holds the project's ext_id STRING ('WAP-101'), not a foreign
 *    key — the same string join every other Wood Art table uses.
 *  - `sort` is the running order of rooms within a project, not a global rank.
 *  - `area` is square feet, unsigned; 0 means "not measured yet", which is why
 *    it is not nullable.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Creates one `wa_`-prefixed table with no foreign keys, so it cannot lock or
 * cascade into anything shared. Guarded with hasTable() so a re-run on a
 * partially-migrated server is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wa_spaces')) {
            return;
        }

        Schema::create('wa_spaces', function (Blueprint $table) {
            $table->id();
            $table->string('ext_id', 40);
            $table->string('company_id', 50)->default('woodart');
            $table->string('project', 40);                  // project EXT id
            $table->string('name', 120);
            $table->string('kind', 40)->default('Common');
            $table->unsignedInteger('area')->default(0);    // square feet
            $table->unsignedSmallInteger('sort')->default(1);
            $table->text('note')->nullable();
            $table->date('created_on')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'ext_id']);
            $table->index(['company_id', 'project']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_spaces');
    }
};
