<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wa_clients — the homeowners, developers and corporates Wood Art builds for.
 *
 * ─── WHY THE FILENAME MATTERS ────────────────────────────────────────────
 * Same situation as wa_projects: this table already exists on the developer
 * machine, created by an earlier attempt that was rolled back — the code was
 * reverted, the database was not. The FILENAME is deliberately identical to
 * the one already recorded in the `migrations` table
 * (2026_07_27_000200_create_wa_clients_table), so Laravel matches it and skips
 * it where the table exists, while creating it on a server that has never had
 * it. Renaming this file would make it re-run and fail. Every column below is
 * a byte-for-byte match of the live schema, verified with SHOW CREATE TABLE.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Creates one `wa_`-prefixed table and touches nothing else. No shared table
 * appears in this file, and down() drops only this one. Rows are scoped by
 * `company_id` ('woodart').
 *
 * No seeder: Wood Art starts empty and real clients are entered by hand
 * (owner's instruction, 2026-08-11).
 *
 * NOTE ON THE JOIN: `wa_projects.client` stores this table's `name`, NOT its
 * id — the reference's own design, which is why (company_id, name) is indexed.
 * Renaming a client therefore does not follow through to its projects. That is
 * a known weakness inherited from the reference, recorded here so nobody
 * assumes a foreign key exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wa_clients')) {
            return;
        }

        Schema::create('wa_clients', function (Blueprint $table) {
            $table->id();
            $table->string('ext_id', 40);
            $table->string('company_id', 50)->default('woodart');
            $table->string('name', 160);
            $table->string('type', 40)->default('Homeowner');
            $table->string('contact', 160)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('area', 120)->nullable();
            $table->date('since')->nullable();
            $table->date('created_on')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'ext_id']);
            $table->index(['company_id', 'name']);   // the project/estimate join
            $table->index(['company_id', 'type']);   // the segment roll-up
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_clients');
    }
};
