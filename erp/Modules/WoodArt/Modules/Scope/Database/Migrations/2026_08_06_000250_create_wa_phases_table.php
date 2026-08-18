<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wa_phases — the stages each space runs through.
 *
 * ─── WHY THIS FILENAME IS *NOT* ONE OF THE RECORDED ONES ─────────────────
 * On the dev machine this table was built in two steps by the rolled-back
 * attempt: created by `2026_07_28_000200_create_wa_cost_control_tables`
 * (alongside tables this module does not own) and then reshaped by
 * `2026_08_06_000200_reshape_wa_phases_for_spaces` to gain the `space` column.
 * Reproducing either name here would mean either re-creating tables that
 * belong elsewhere, or shipping a file called "reshape" that actually creates.
 *
 * So this is a NEW name with a hasTable() guard instead. That is safe on both
 * hosts precisely because of the guard:
 *   · dev machine, table already there → returns immediately, data untouched,
 *     and the name is simply recorded as run
 *   · production, which has no wa_phases → creates it in its FINAL shape,
 *     already including `space`, so there is nothing to reshape afterwards
 *
 * The columns are a byte-for-byte match of the live dev schema (verified
 * against SHOW CREATE TABLE), so a project cannot end up with two different
 * phase tables depending on which host it is on.
 *
 * SHAPE NOTES:
 *  - `project` and `space` hold EXT id STRINGS ('WAP-101', 'SPC-001'), never
 *    foreign keys. A phase whose space was removed is kept and flagged.
 *  - `owner_id` holds an employee CODE STRING, not a users.id. It is resolved
 *    for display against the shared user register at read time, read-only and
 *    fully guarded — an unresolvable code is shown as-is, never hidden.
 *  - `code` is the trade this phase belongs to ('Wood Work', 'Tiles Work'),
 *    matching the same vocabulary the estimates' bill of quantities uses.
 *  - No money columns: what a phase COSTS lives in wa_requirements.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Creates one `wa_`-prefixed table with no foreign keys. Nothing shared is
 * created, altered or referenced.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wa_phases')) {
            return;
        }

        Schema::create('wa_phases', function (Blueprint $table) {
            $table->id();
            $table->string('ext_id', 40)->default('');
            $table->string('company_id', 50)->default('woodart');
            $table->string('project', 40);                  // project EXT id
            $table->string('space', 40)->default('');       // space EXT id
            $table->string('name', 120);
            $table->string('code', 60)->nullable();         // the trade
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('status', 20)->default('Not started');
            $table->string('owner_id', 40)->nullable();     // employee CODE
            $table->date('start')->nullable();
            $table->date('finish')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'ext_id']);
            $table->index(['company_id', 'project']);
            $table->index(['company_id', 'space']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_phases');
    }
};
