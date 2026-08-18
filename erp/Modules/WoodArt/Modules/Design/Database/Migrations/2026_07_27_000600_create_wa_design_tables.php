<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wa_drawings + wa_revisions — the architecture & 3D phase's own records.
 *
 * ─── WHY THIS FILENAME ───────────────────────────────────────────────────
 * Identical to the name already recorded on the dev machine (batch 89), left
 * by the rolled-back earlier attempt: the rollback reverted the code but not
 * the database, so the tables and their rows survived while this file did not.
 * Laravel matches migrations by name, so it is skipped there and runs once on
 * production, which has neither table. Renaming it would make it re-run on the
 * dev machine and fail. The columns are a byte-for-byte match of the live
 * schema (verified against SHOW CREATE TABLE).
 *
 * ─── TWO TABLES, ONE LIFECYCLE ───────────────────────────────────────────
 *   wa_drawings   the deliverable, carrying its CURRENT revision and status
 *   wa_revisions  the trail — one row per revision letter, per action
 *
 * The trail is a SEPARATE table, not a JSON column on the drawing, and that is
 * deliberate. A revision is an AUDIT record: who issued it, what the client
 * said, when it was approved. Site & Install embeds its snag list because a
 * snag is a checklist item; a revision is evidence, and evidence gets its own
 * row that can be queried, counted and never silently rewritten.
 *
 * SHAPE NOTES:
 *  - `project` holds the project's ext_id STRING, never a foreign key. A
 *    drawing whose project was removed is kept and flagged.
 *  - `revisions.drawing` likewise holds a drawing ext_id STRING.
 *  - `rev` is a single letter, A upward. The number of revisions is derived
 *    from it (A = none), so the letter and the count can never disagree.
 *  - `designer` and `by` are NAMES, not employee ids — no foreign key into
 *    any shared table.
 *  - No money columns anywhere: a drawing costs nothing here.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Creates two `wa_`-prefixed tables with no foreign keys at all. Each create
 * is guarded independently, so a host that somehow has one but not the other
 * is repaired rather than broken.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_drawings')) {
            Schema::create('wa_drawings', function (Blueprint $table) {
                $table->id();
                $table->string('ext_id', 40);
                $table->string('company_id', 50)->default('woodart');
                $table->string('title', 200);
                $table->string('kind', 40)->default('Plan');
                $table->string('project', 40)->nullable();     // project EXT id
                $table->string('designer', 160)->nullable();   // a NAME
                $table->string('rev', 4)->default('A');
                $table->string('status', 30)->default('Draft');
                $table->date('issued')->nullable();
                $table->date('approved')->nullable();
                $table->date('created_on')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'ext_id']);
                $table->index(['company_id', 'project']);
                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'designer']);
            });
        }

        if (! Schema::hasTable('wa_revisions')) {
            Schema::create('wa_revisions', function (Blueprint $table) {
                $table->id();
                $table->string('ext_id', 40);
                $table->string('company_id', 50)->default('woodart');
                $table->string('drawing', 40);                 // drawing EXT id
                $table->string('rev', 4)->default('A');
                $table->string('action', 30)->default('Drafted');
                $table->string('by', 160)->nullable();         // a NAME
                $table->string('note', 500)->nullable();
                $table->date('date')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'ext_id']);
                $table->index(['company_id', 'drawing']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_revisions');
        Schema::dropIfExists('wa_drawings');
    }
};
