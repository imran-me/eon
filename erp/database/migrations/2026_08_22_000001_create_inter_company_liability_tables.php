<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inter-company liabilities — the Liability book on the Capital & Financing desk.
 *
 * WHAT THIS IS FOR
 * One company's expense is sometimes paid from another company's bank account:
 * an Interiors site cost settled from a Travels account. The expense belongs to
 * Interiors and is booked there; the money left Travels. Somebody has to
 * remember that Interiors owes Travels for it, and until now nothing did.
 *
 * A row here is that memory. It is raised when the expense is APPROVED — not
 * when it is filed — because filing an expense is a claim and approving it is
 * what commits the money. A claim that is rejected must not leave a debt behind
 * on the paying company's book. See ExpenseController::approve().
 *
 * ── THE ACCOUNTING RULE (owner, 2026-08-22) ───────────────────────────────
 * This book posts NO journal entries and moves NO balance. It is a record of
 * who owes whom, kept alongside the ledger, not inside it. That is the owner's
 * explicit instruction: "it [shouldn't] effect anywhere anything, just a new
 * record or tracking."
 *
 * That is why there is no journal_entry_id below, and it is deliberate.
 * Precedent: App\Services\DailyFundService keeps a whole desk with no debit,
 * no credit and no account, and the lent book on this same desk posts nothing
 * for reasons documented on create_financing_tables.
 *
 * ── WHAT THIS DOES NOT FIX, STATED PLAINLY ────────────────────────────────
 * The underlying ledger stays as it is today. When Interiors' expense is
 * approved against a Travels bank, postJournal() writes:
 *
 *     Dr  Interiors expense account       45,000
 *     Cr  Travels bank account            45,000   (entry stamped company = Interiors)
 *
 * Travels' cash falls inside an entry belonging to Interiors, so Travels' trial
 * balance loses 45,000 with nothing to offset it — the exact failure
 * config/accounts.php:27-30 warns about for shared posting accounts. A row in
 * this table records the debt but does not repair that, because it writes
 * nothing to the ledger.
 *
 * Closing it properly needs an inter-company pair in config/accounts.php
 * (a "Due from Sister Company" asset and a "Due to Sister Company" liability)
 * and a third leg on the expense posting. These tables are shaped so that is
 * one posting service over these same rows later, not a rebuild — same escape
 * hatch create_financing_tables left itself, and the same one it later used.
 *
 * ── SAFE WHILE BROKEN ─────────────────────────────────────────────────────
 * Everything that reads these tables must Schema::hasTable()-guard itself.
 * The window between "code deployed" and "migration run" is otherwise a 500 on
 * the expense desk for all twelve companies — the shape of the 2026-08-10
 * outage. App\Services\DailyFundService is the pattern to copy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inter_company_liabilities', function (Blueprint $table) {
            $table->id();

            // Who owes, and who is owed. Named for the direction rather than
            // "company_a/company_b" so a query cannot read the wrong way round:
            // the DEBTOR is the company whose expense it was, the CREDITOR is
            // the company whose bank account the money actually left.
            $table->foreignId('debtor_company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('creditor_company_id')->constrained('companies')->cascadeOnDelete();

            // The expense that raised it. Nullable so a liability can later be
            // entered by hand for something that never was an expense row, and
            // nullOnDelete so deleting an expense cannot take the debt with it —
            // the money still left the other company's account.
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();

            // Which account paid. Kept for the register's own reporting; no
            // journal is written against it — see the accounting rule above.
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();

            $table->decimal('amount', 15, 2);
            $table->decimal('settled_amount', 15, 2)->default(0);

            $table->date('incurred_on')->index();

            // Denormalised from the expense on purpose. The register has to stay
            // readable years later, after the expense row has been deleted or
            // its title edited; a debt that can no longer say what it was for is
            // not a record of anything.
            $table->string('title')->nullable();
            $table->string('reference')->nullable();

            $table->enum('status', ['active', 'settled', 'written_off', 'cancelled'])
                  ->default('active')->index();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // One liability per expense. A reversed-then-re-approved expense
            // reuses its row (the service updateOrCreates on this key), so the
            // constraint makes a genuine double-raise fail loudly instead of
            // quietly showing the same debt twice.
            $table->unique('expense_id');

            // The two tables are always read one pair and one status at a time.
            //
            // Named explicitly, and not for neatness: MySQL caps an identifier at
            // 64 characters and the name Laravel derives here —
            // inter_company_liabilities_debtor_company_id_creditor_company_id_status_index
            // — is 76, so the migration fails outright on the three-column index.
            // Long table names plus long column names reach that ceiling fast;
            // every composite index and foreign key below is named for the same
            // reason.
            $table->index(['debtor_company_id', 'creditor_company_id', 'status'], 'icl_pair_status_idx');
            $table->index(['creditor_company_id', 'status'], 'icl_creditor_status_idx');
        });

        Schema::create('inter_company_liability_transactions', function (Blueprint $table) {
            $table->id();

            // Explicit FK name for the 64-character reason above: the derived
            // one (…_inter_company_liability_id_foreign) is 71.
            $table->unsignedBigInteger('inter_company_liability_id');
            $table->foreign('inter_company_liability_id', 'iclt_liability_fk')
                  ->references('id')->on('inter_company_liabilities')
                  ->cascadeOnDelete();

            // 'raise' is the debt coming into existence, written once when the
            // expense is approved. Everything after it reduces the balance.
            // Kept as movements rather than a settled_amount alone so the
            // Liability Transaction table can show WHEN each part was paid.
            $table->enum('type', ['raise', 'settlement', 'write_off', 'adjustment'])->index();

            $table->date('date')->index();
            $table->decimal('amount', 15, 2);

            // Optional proof that real money moved. A settlement between two
            // companies is a bank transfer, and that desk already records and
            // posts one — linking to it beats describing it a second time here.
            // Null is a perfectly valid settlement: an owner writing off a small
            // balance between his own companies is still a settlement.
            $table->foreignId('bank_transfer_id')->nullable()
                  ->constrained('bank_transfers')->nullOnDelete();

            $table->string('memo')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Derived name would be 74 characters. Same ceiling.
            $table->index(['inter_company_liability_id', 'date'], 'iclt_liability_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inter_company_liability_transactions');
        Schema::dropIfExists('inter_company_liabilities');
    }
};
