<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wa_production — Wood Art's fabrication jobs (the workshop floor).
 *
 * Transcribed from the reference's own migration
 * (companies/woodart/modules/production/backend/migrations/), keeping its
 * filename so a host that already ran it is matched rather than re-run.
 *
 * SHAPE NOTES a developer needs:
 *  - `ext_id` is the job code ('JOB-001') and the upsert key, unique PER
 *    COMPANY.
 *  - `company_id` holds the slug 'woodart'. NOT a foreign key on purpose —
 *    the module must stay droppable, and nothing here may point at a shared
 *    table.
 *  - `project` holds the PROJECT'S ext_id STRING ('WAP-101'), not a foreign
 *    key — the same string-join every other Wood Art table uses. It is indexed
 *    because the register resolves a project name for every row. A job whose
 *    project no longer exists is KEPT and flagged, never hidden (reference
 *    decision W3): losing real shop-floor history because a parent vanished is
 *    worse than showing the problem.
 *  - `assigned_to` is a person's NAME, not an employee id, for the same reason
 *    — the Wood Art module must not hold a foreign key into the shared
 *    employee tables.
 *  - NO money columns. A job costs nothing here (reference decision W6);
 *    labour and material cost belong to the project, and duplicating them
 *    would give the ERP two answers to one question.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Creates exactly one `wa_`-prefixed table that no other company reads or
 * writes. It has no foreign keys at all, so it cannot lock, cascade into, or
 * otherwise touch a shared table. Guarded with hasTable() so a re-run on a
 * partially-migrated server is a no-op rather than a failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wa_production')) {
            return;
        }

        Schema::create('wa_production', function (Blueprint $table) {
            $table->id();
            $table->string('ext_id', 40);
            $table->string('company_id', 50)->default('woodart');
            $table->string('job', 160);
            $table->string('project', 40)->nullable();       // project EXT id
            $table->string('station', 60)->default('CNC');
            $table->string('assigned_to', 160)->nullable();  // person NAME
            $table->string('status', 30)->default('Queued');
            $table->date('due')->nullable();
            $table->date('created_on')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'ext_id']);
            $table->index(['company_id', 'project']);
            $table->index(['company_id', 'station']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_production');
    }
};
