<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wa_requirements — what each phase of each space needs: materials, and work
 * put out to contract.
 *
 * ─── WHY THIS FILENAME ───────────────────────────────────────────────────
 * Identical to the name already recorded on the dev machine (batch 89), whose
 * 214 rows survived the rollback that removed the code. Same reasoning as
 * wa_spaces: matched and skipped there, run once on production. The columns
 * are a byte-for-byte match of the live schema.
 *
 * SHAPE NOTES:
 *  - `project`, `space`, `phase` hold EXT id STRINGS, never foreign keys.
 *  - `material_id` is a material EXT id ('MAT-001') or NULL. NULL is normal:
 *    a `contract` line is bought work, not something in the store.
 *  - `unit_cost` / `unit_sale` are integer taka, per unit — the same pair the
 *    estimates' bill of quantities carries, so a requirement can be compared
 *    against what was quoted.
 *  - `kind` is material|contract; `status` is Planned|Ordered|Issued.
 *
 * NOTHING HERE POSTS TO A LEDGER. A requirement states what a phase needs and
 * what it was costed at. Turning that into money is Procurement's purchase
 * order and Projects' billing — this table must never become a second source
 * of truth for spend.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Creates one `wa_`-prefixed table with no foreign keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wa_requirements')) {
            return;
        }

        Schema::create('wa_requirements', function (Blueprint $table) {
            $table->id();
            $table->string('ext_id', 40);
            $table->string('company_id', 50)->default('woodart');
            $table->string('project', 40);                  // project EXT id
            $table->string('space', 40);                    // space EXT id
            $table->string('phase', 40);                    // phase EXT id
            $table->string('kind', 20)->default('material');
            $table->string('code', 60)->nullable();         // the trade
            $table->string('item', 200);
            $table->string('material_id', 40)->nullable();  // material EXT id
            $table->decimal('qty', 14, 2)->default(0);
            $table->string('unit', 30)->nullable();
            $table->unsignedBigInteger('unit_cost')->default(0);
            $table->unsignedBigInteger('unit_sale')->default(0);
            $table->string('status', 20)->default('Planned');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'ext_id']);
            $table->index(['company_id', 'phase']);
            $table->index(['company_id', 'project', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_requirements');
    }
};
