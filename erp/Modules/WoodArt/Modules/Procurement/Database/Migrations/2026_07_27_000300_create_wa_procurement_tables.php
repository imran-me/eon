<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wa_vendors + wa_purchases — who Wood Art buys from, and what it has ordered.
 *
 * ─── WHY THE FILENAME MATTERS ────────────────────────────────────────────
 * As with the other Wood Art tables: these already exist on the developer
 * machine from a rolled-back attempt (code reverted, database not). The
 * filename matches the recorded migration so Laravel skips it where the tables
 * exist and creates them where they do not. Do not rename it.
 *
 * A SECOND migration in this folder (2026_07_28_000300) adds `project` to
 * wa_purchases. Both are needed for a server to reach the current schema; the
 * split is the reference's own history and is preserved so the recorded names
 * still match.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Creates two `wa_`-prefixed tables and touches nothing else. down() drops
 * only these two. Rows are scoped by `company_id` ('woodart').
 *
 * ─── ON THE JOINS ────────────────────────────────────────────────────────
 * `wa_purchases.supplier` stores the vendor's NAME, not an id — hence the
 * index on (company_id, supplier). Same weakness as projects→clients: renaming
 * a vendor detaches its orders. Inherited from the reference and recorded here
 * so nobody assumes a foreign key exists.
 *
 * `amount` is the order total in integer taka. The reference notes it is a
 * cache of the order's line values; per-line detail lives in
 * wa_purchase_lines, which is not built here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_vendors')) {
            Schema::create('wa_vendors', function (Blueprint $table) {
                $table->id();
                $table->string('ext_id', 40);
                $table->string('company_id', 50)->default('woodart');
                $table->string('name', 160);
                $table->string('category', 60)->default('General');
                $table->string('contact', 160)->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('email', 160)->nullable();
                $table->string('area', 120)->nullable();
                $table->string('terms', 40)->nullable();
                $table->date('since')->nullable();
                $table->date('created_on')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'ext_id']);
                $table->index(['company_id', 'name']);       // the order→vendor join
                $table->index(['company_id', 'category']);   // the spend roll-up
            });
        }

        if (! Schema::hasTable('wa_purchases')) {
            Schema::create('wa_purchases', function (Blueprint $table) {
                $table->id();
                $table->string('ext_id', 40);
                $table->string('company_id', 50)->default('woodart');
                $table->string('supplier', 160);             // vendor NAME — see note
                $table->unsignedInteger('items')->default(0);
                $table->unsignedBigInteger('amount')->default(0);
                $table->string('status', 30)->default('Ordered');
                $table->date('date')->nullable();
                $table->date('created_on')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'ext_id']);
                $table->index(['company_id', 'supplier']);
                $table->index(['company_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_purchases');
        Schema::dropIfExists('wa_vendors');
    }
};
