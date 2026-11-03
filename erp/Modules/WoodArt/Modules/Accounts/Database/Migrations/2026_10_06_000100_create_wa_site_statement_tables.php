<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wood Art site statements — what a project's CLIENT has paid and what its
 * SUPPLIERS are still owed.
 *
 * STATEMENT ONLY, NOT BOOKS. Nothing here posts a journal. Site COSTS live in
 * the ERP's Expense desk (company 6) like every other expense; these two tables
 * hold the figures the owner keeps on the side — the contract value, the
 * client's installments, and the supplier balances noted at a date — so the
 * overview and due sheets can show them next to the posted cost. When the
 * accountant has settled which bank/account each one belongs to, they are
 * posted properly and the row is left behind as the record of what was said.
 *
 * A project is an Expense Department of company 6 (expense_departments.id); the
 * code checks that, there is deliberately no foreign key into a shared table.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Creates two `wa_`-prefixed tables and touches nothing else. Guarded, so a
 * partly-migrated server can re-run it. Run with --path (see the deploy note),
 * never a bare `migrate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_site_terms')) {
            Schema::create('wa_site_terms', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('expense_department_id')->unique();
                $table->decimal('contract_value', 14, 2)->nullable();   // what the client agreed to pay in total
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('wa_site_statement')) {
            Schema::create('wa_site_statement', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('expense_department_id')->index();
                // client_installment = money the client paid us
                // supplier_bill      = what a supplier/contractor is owed
                // supplier_payment   = what was paid against that (reduces the due)
                $table->string('kind', 24);
                $table->string('party', 160)->nullable();                // supplier / contractor name
                $table->string('label', 160)->nullable();               // "1st payment", "Sand due"…
                $table->date('entry_date')->nullable();
                $table->decimal('amount', 14, 2);
                $table->text('note')->nullable();
                $table->string('ext_key', 60)->nullable();              // makes a seeded row safe to re-run
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['expense_department_id', 'kind']);
                $table->unique(['expense_department_id', 'ext_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_site_statement');
        Schema::dropIfExists('wa_site_terms');
    }
};
