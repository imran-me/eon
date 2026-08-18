<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wa_projects + wa_estimates — the spine every other Wood Art module points at.
 *
 * ─── WHY THIS FILE LOOKS "ALREADY RUN" ───────────────────────────────────
 * These two tables ALREADY EXIST on the developer machine, created by an
 * earlier Wood Art attempt that was rolled back. The rollback reverted the
 * code but not the database, so the tables (and their demo rows) survived
 * while this file did not — leaving a schema that existed nowhere in version
 * control. This restores it.
 *
 * The FILENAME is deliberately identical to the one already recorded in the
 * `migrations` table (2026_07_27_000800_create_wa_projects_tables). Laravel
 * matches migrations by that name, so:
 *   · where the tables already exist  → recorded as run, skipped, no data touched
 *   · on a server that has never had them → runs once and creates them
 * Renaming this file would make it re-run and fail on the existing table.
 * The column definitions below are a byte-for-byte match of the live schema
 * (verified against SHOW CREATE TABLE), so the two can never drift.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * This migration CREATES two `wa_`-prefixed tables and touches nothing else.
 * It does not alter, rename, or add a column to any table another company
 * reads — no shared table appears anywhere in this file. down() drops only
 * these two. Every row is additionally scoped by `company_id` ('woodart').
 *
 * There is deliberately NO seeder: the owner's instruction (2026-08-11) is
 * that Wood Art's projects start empty so real jobs are entered by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_projects')) {
            Schema::create('wa_projects', function (Blueprint $table) {
                $table->id();
                $table->string('ext_id', 40);
                $table->string('company_id', 50)->default('woodart');
                $table->string('name', 200);
                $table->string('client', 160)->nullable();   // client NAME — see Clients
                $table->string('type', 40)->default('Residential');
                $table->unsignedInteger('area')->default(0); // sft
                $table->unsignedBigInteger('value')->default(0);
                $table->unsignedBigInteger('cost')->default(0);
                $table->string('stage', 40)->default('Design');
                $table->string('phase', 40)->nullable();
                $table->unsignedInteger('progress')->default(0);
                $table->string('designer', 160)->nullable();
                $table->date('start')->nullable();
                $table->date('deadline')->nullable();
                $table->boolean('billed')->default(false);
                $table->date('created_on')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'ext_id']);
                $table->index(['company_id', 'stage']);
                $table->index(['company_id', 'client']);
            });
        }

        if (! Schema::hasTable('wa_estimates')) {
            Schema::create('wa_estimates', function (Blueprint $table) {
                $table->id();
                $table->string('ext_id', 40);
                $table->string('company_id', 50)->default('woodart');
                $table->string('title', 200);
                $table->string('client', 160)->nullable();
                $table->string('project_ext', 40)->nullable(); // the project it quotes
                $table->string('status', 30)->default('Draft');
                $table->json('lines')->nullable();             // the BOQ — this IS the budget
                $table->date('valid_till')->nullable();
                $table->date('created_on')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'ext_id']);
                $table->index(['company_id', 'project_ext']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_estimates');
        Schema::dropIfExists('wa_projects');
    }
};
