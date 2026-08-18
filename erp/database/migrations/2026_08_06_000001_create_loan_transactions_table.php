<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every movement on a staff loan, as its own row.
 *
 * Until now a loan carried only `remaining_amount` — a running figure with no
 * history behind it, so "৳20,000 has been repaid" was recorded while when, how
 * and into which account were not. This table is that missing history: one row
 * per disbursement and per repayment, which is what the loan register, the
 * transaction list and the salary-vs-cash split are all read from.
 *
 * `remaining_amount` on loans stays as-is and is not backfilled — outstanding
 * for existing loans keeps coming from that column, while everything recorded
 * from here on is reconstructable from these rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            // Denormalised so the per-employee views don't have to join loans
            // just to group by person.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->enum('type', ['disburse', 'repay'])->index();
            // How the money moved. 'salary' is an EMI withheld from a payslip —
            // no cash changes hands at that moment, which is why it has to be
            // told apart from a cash/bank repayment in the register.
            $table->enum('method', ['salary', 'cash', 'bank'])->default('cash');

            $table->decimal('amount', 15, 2);
            $table->date('date')->index();

            // Ledger account the money left from / arrived into.
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();

            // Set when the row was created by a payslip's EMI deduction, so the
            // same deduction can never be counted twice.
            $table->foreignId('employee_salary_id')->nullable()->constrained('employee_salaries')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['loan_id', 'date']);
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_transactions');
    }
};
