<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendors and clients of a company's projects — the "who" behind a due or a payment.
 *
 * Until now a supplier was only a typed name on a statement row, so "Haji" and
 * "Hazi Enterprise" were two suppliers, a supplier's purchases could not be added up,
 * and a client had no record at all. This is the register that fixes that:
 *
 *   project_parties        one row per vendor / client, OF ONE COMPANY (never shared
 *                          across companies — Epal Constructions' suppliers are not
 *                          Travels' vendors)
 *   project_statement.party_id    the party a statement row belongs to
 *   project_terms.client_party_id the project's client
 *   project_expense_party  which vendor an Expense-desk cost entry was paid to — pure
 *                          metadata beside the expense; the expense itself is untouched
 *
 * ─── WHY NOT THE ERP'S OWN SUPPLIER / VENDOR TABLES ──────────────────────────
 * `suppliers` has no company column and `users` vendors feed Travels' Party Statement:
 * a Constructions supplier put there would show up on other companies' screens. A
 * private register keeps each company's parties its own, and journals are not tagged
 * with an unknown party type (the shared journal screens would resolve it to the
 * wrong person).
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Two new tables and nullable columns on two `project_` tables; nothing shared.
 * Guarded so a re-run is harmless. Run with --path, never a bare `migrate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_parties')) {
            Schema::create('project_parties', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('type', 10);                       // vendor | client
                $table->string('name', 160);
                $table->string('phone', 40)->nullable();
                $table->string('email', 160)->nullable();
                $table->string('address', 255)->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'type', 'name']);
            });
        }

        if (Schema::hasTable('project_statement') && ! Schema::hasColumn('project_statement', 'party_id')) {
            Schema::table('project_statement', function (Blueprint $table) {
                $table->unsignedBigInteger('party_id')->nullable()->after('party')->index();
            });
        }

        if (Schema::hasTable('project_terms') && ! Schema::hasColumn('project_terms', 'client_party_id')) {
            Schema::table('project_terms', function (Blueprint $table) {
                $table->unsignedBigInteger('client_party_id')->nullable()->after('contract_value');
            });
        }

        if (! Schema::hasTable('project_expense_party')) {
            Schema::create('project_expense_party', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('expense_id')->unique();   // one vendor per cost entry
                $table->unsignedBigInteger('party_id')->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_expense_party');

        if (Schema::hasTable('project_terms') && Schema::hasColumn('project_terms', 'client_party_id')) {
            Schema::table('project_terms', fn (Blueprint $t) => $t->dropColumn('client_party_id'));
        }
        if (Schema::hasTable('project_statement') && Schema::hasColumn('project_statement', 'party_id')) {
            Schema::table('project_statement', fn (Blueprint $t) => $t->dropColumn('party_id'));
        }

        Schema::dropIfExists('project_parties');
    }
};
