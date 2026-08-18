<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capital & Financing — the loan book.
 *
 * Three books on one desk, per the owner's brief carried over from the
 * reference build (platform/kit/loans.js):
 *
 *   1. LENT OUT   — money the group lends: a personal or business loan, a
 *                   goodwill loan, or a service someone takes now and pays for
 *                   monthly (a visa on instalments is treated as a loan here).
 *   2. EMPLOYEE   — staff loans are NOT stored here. They already live in
 *                   `loans` + `loan_transactions` under Payroll and recover as
 *                   payslip EMI. This desk mirrors that book read-only so the
 *                   whole portfolio reads in one place. Nothing here moves them.
 *   3. BORROWED   — what the group owes: bank loan, car EMI, equipment or
 *                   office loan, working capital.
 *
 * ── THE ACCOUNTING RULE (owner, 2026-07-15) ──────────────────────────────
 * These books are kept SEPARATE from the main accounts. Lending out and
 * borrowing post NO journal entries and move NO bank balance — this desk
 * carries its own numbers. That is why there is no journal_entry_id anywhere
 * below, and it is deliberate, not an omission.
 *
 * It is also the correct treatment, not merely a preference: when a customer
 * takes a visa and pays monthly, the SALE has already put that money on the
 * books as a receivable. Booking it a second time as a loan asset would count
 * the same taka twice.
 *
 * The one exception stays where it is — EMPLOYEE loans are payroll's, and
 * their disbursement and EMI recovery do hit the ledger exactly as they always
 * have, through the existing `loans` tables this migration does not touch.
 *
 * If the group ever wants these on the books, that is one posting service over
 * these same tables; nothing here needs restructuring.
 *
 * ── WHY NEW TABLES AND NOT `loans` ───────────────────────────────────────
 * `loans` is the staff-loan desk: every row is hard-wired to a `users` row and
 * every journal it writes debits the employee-loan account. It has no
 * direction, no counterparty and no interest. Adding those columns would
 * change a table payroll posts to on a live twelve-company install, so these
 * are separate tables and the employee book is read where it already lives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financing_loans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Which book the row belongs to. 'lent' = they owe us, 'borrowed' =
            // we owe them. One table because every field below is common to
            // both and the screens differ only in wording.
            $table->enum('direction', ['lent', 'borrowed'])->index();

            // Free text rather than an enum: the owner's list (Car, Flat,
            // Properties, Equipment, Working Capital, Service on Instalments)
            // is not closed, and a new kind must not need a migration.
            $table->string('kind')->nullable()->index();

            // The other side. counterparty_name always carries a readable name,
            // including for a lender that is not a record in this system (a
            // bank, a relative). party_type/party_id additionally point at a
            // real row when there is one — same convention journal_items uses.
            $table->string('counterparty_name');
            $table->string('party_type', 40)->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->index(['party_type', 'party_id']);

            // The lender's own reference for the facility, e.g. CBL-TL-77120.
            $table->string('account_no')->nullable();

            $table->decimal('principal', 15, 2);
            $table->decimal('interest_rate', 6, 3)->default(0);      // annual %
            // 'reducing' charges interest on the outstanding balance, 'flat' on
            // the original principal for the whole term. They give very
            // different instalments, so the schedule builder must be told which.
            $table->enum('interest_method', ['none', 'flat', 'reducing'])->default('none');

            $table->unsignedSmallInteger('tenure_months')->nullable();
            // The computed EMI, stored so the register does not recompute it per
            // row. The schedule remains the source of truth for what is owed.
            $table->decimal('instalment_amount', 15, 2)->nullable();

            $table->date('start_date')->index();

            $table->text('purpose')->nullable();
            $table->text('security')->nullable();                     // collateral
            $table->text('notes')->nullable();

            $table->enum('status', ['active', 'closed', 'written_off', 'cancelled'])
                  ->default('active')->index();

            $table->timestamps();
            $table->softDeletes();

            // The register is always read one company and one book at a time.
            $table->index(['company_id', 'direction', 'status']);
        });

        Schema::create('financing_schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('financing_loan_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('instalment_no');
            $table->date('due_date')->index();

            // Split so a repayment can be reported as principal recovered vs
            // interest earned/paid without recomputing the amortisation.
            $table->decimal('principal_component', 15, 2)->default(0);
            $table->decimal('interest_component', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);

            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->date('paid_date')->nullable();

            $table->enum('status', ['due', 'partial', 'paid', 'waived'])->default('due')->index();

            $table->timestamps();

            // One row per instalment number per loan — makes a double-generated
            // schedule fail loudly instead of silently doubling what is owed.
            $table->unique(['financing_loan_id', 'instalment_no']);
        });

        Schema::create('financing_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('financing_loan_id')->constrained()->cascadeOnDelete();
            // Which instalment the money was applied to. Null for a disbursement
            // or an ad-hoc payment that is not against a scheduled row.
            $table->foreignId('financing_schedule_id')->nullable()
                  ->constrained('financing_schedules')->nullOnDelete();

            // 'receipt' is money coming in on a lent loan; 'repayment' is money
            // going out on a borrowed one. Kept apart from direction so a
            // refund or an adjustment on either book still reads correctly.
            $table->enum('type', ['disburse', 'receipt', 'repayment', 'write_off', 'adjustment'])->index();

            $table->date('date')->index();
            $table->decimal('amount', 15, 2);
            $table->decimal('principal_part', 15, 2)->default(0);
            $table->decimal('interest_part', 15, 2)->default(0);

            $table->enum('method', ['cash', 'bank', 'adjustment'])->default('cash');
            // Which bank the money moved through, for the desk's own reporting.
            // No journal is written for it — see the accounting rule above.
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();

            $table->string('memo')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['financing_loan_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financing_transactions');
        Schema::dropIfExists('financing_schedules');
        Schema::dropIfExists('financing_loans');
    }
};
