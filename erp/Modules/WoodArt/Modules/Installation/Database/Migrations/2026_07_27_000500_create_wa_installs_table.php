<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wa_installs — Wood Art's site visits: delivery, fitting, snagging, handover.
 *
 * Transcribed from the reference's own migration
 * (companies/woodart/modules/installation/backend/migrations/), keeping its
 * filename so a host that already ran it is matched rather than re-run.
 *
 * SHAPE NOTES a developer needs:
 *  - `ext_id` is the visit code ('INS-001') and the upsert key, unique PER
 *    COMPANY. `company_id` holds the slug 'woodart' — not a foreign key, so
 *    the module stays droppable and nothing points at a shared table.
 *  - `project` holds the project's ext_id STRING ('WAP-101'), not a foreign
 *    key, exactly as wa_production does. An install whose project has gone is
 *    KEPT and flagged, never hidden (reference decision I8).
 *  - **`snags` and `snag_list` coexist deliberately** (decision I1). A record
 *    may carry a plain OPEN count, or an itemised list of {text, done} — the
 *    reference's Projects snag modal migrates one into the other. Both are
 *    stored, and the count is always kept authoritative: on write, a supplied
 *    list RECOMPUTES the number, because the handover queue is ordered by that
 *    figure and a stale client count must not corrupt it (decision I2).
 *    `snag_list` is nullable JSON, so a record never itemised costs nothing.
 *  - NO money columns, and no billing anywhere in this module. Billing the
 *    handover belongs to Projects; a second posting path would double-bill
 *    every project (decision I4 — the reference calls it the most damaging
 *    thing this module could have done).
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Creates exactly one `wa_`-prefixed table with no foreign keys at all, so it
 * cannot lock, cascade into or otherwise touch a shared table. Guarded with
 * hasTable() so a re-run on a partially-migrated server is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wa_installs')) {
            return;
        }

        Schema::create('wa_installs', function (Blueprint $table) {
            $table->id();
            $table->string('ext_id', 40);
            $table->string('company_id', 50)->default('woodart');
            $table->string('project', 40)->nullable();     // project EXT id
            $table->string('site', 160);
            $table->string('team', 120)->nullable();
            $table->string('status', 30)->default('Scheduled');
            $table->date('date')->nullable();
            $table->unsignedInteger('snags')->default(0);  // OPEN snag count
            $table->json('snag_list')->nullable();         // itemised, when present
            $table->date('created_on')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'ext_id']);
            $table->index(['company_id', 'project']);
            $table->index(['company_id', 'team']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_installs');
    }
};
