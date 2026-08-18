<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Petty cash — the money that has left the cash box but has not been spent yet.
 *
 * WHAT THIS FIXES — someone is handed ৳2,000 for the evening snacks. Booking that
 * as an expense the moment it leaves the drawer is wrong twice over: it is not an
 * expense until the snacks are bought, and the ৳500 that comes back has nowhere to
 * go, so it quietly stops existing. Nobody can answer "how much is in Karim's
 * pocket right now?" because nothing was ever recording it.
 *
 * THE MODEL — three separate events, which is what the imprest system is:
 *
 *     issue     Dr Petty Cash Float (party: custodian)   Cr Office Cash / Bank
 *     spend     Dr Tea & Snacks                          Cr Petty Cash Float (party: custodian)
 *     return    Dr Office Cash / Bank                    Cr Petty Cash Float (party: custodian)
 *
 * Issuing touches no expense account at all — the money is still the company's,
 * it has only moved from the drawer to a pocket. What the custodian is holding is
 * therefore always Dr − Cr on the float account for that person, and that figure
 * is derived from the ledger rather than stored, so it cannot drift away from the
 * accounts the way a cached column would.
 *
 * ONE ACCOUNT, NOT ONE PER PERSON — journal_items already carries party_type and
 * party_id, and AdvanceSalaryController already writes 'employee' there. Fifty
 * custodians would otherwise mean fifty near-identical asset accounts and a chart
 * nobody can read.
 *
 * WHY EXPENSES ONLY GET A COLUMN — the spend is an ordinary expense in every way
 * except which account the money came out of, so it stays in `expenses` with the
 * whole classification picker behind it. `petty_cash_float_id` records that it was
 * settled from a pocket rather than the drawer, and ExpenseController's
 * settlementAccountId() does the rest.
 */
return new class extends Migration
{
    private const FLOAT_ACCOUNT_CODE = '1165';

    public function up(): void
    {
        $this->ensureFloatAccount();

        Schema::create('petty_cash_floats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // The person holding the money. Not nullable: an unheld float is just
            // cash in the drawer, and the drawer already has an account.
            $table->foreignId('custodian_id')->constrained('users')->cascadeOnDelete();

            // Which asset account this float sits in. Defaulted from config rather
            // than hardcoded so a company running a separate site fund can point
            // its float somewhere else without a schema change.
            $table->foreignId('account_id')->constrained('accounts');

            // The imprest amount — what the custodian is topped back up TO, not
            // what they were last handed. Reimbursing to a fixed figure is what
            // keeps the drawer closed six days a week.
            $table->decimal('float_limit', 15, 2)->default(0);

            $table->string('note')->nullable();
            $table->boolean('status')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            // One live float per person per company. Two would split their balance
            // across rows while the ledger kept one figure, and the two would
            // disagree the first time anyone looked.
            $table->unique(['company_id', 'custodian_id', 'deleted_at'], 'petty_cash_floats_one_per_custodian');
        });

        Schema::create('petty_cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_float_id')->constrained()->cascadeOnDelete();

            // issue — drawer to pocket. return — pocket back to drawer.
            // Spending is NOT here; it lives in `expenses`, which is the only place
            // that knows what was bought. Recording it in both would give two
            // answers to one question.
            $table->enum('type', ['issue', 'return']);

            $table->decimal('amount', 15, 2);
            $table->date('date');

            // Where the money came from on an issue, or went back to on a return.
            // Null means the office cash account, matching how an expense with no
            // bank is read.
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();

            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('attachment')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['petty_cash_float_id', 'date']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('petty_cash_float_id')->nullable()->after('bank_id')
                ->constrained()->nullOnDelete();
        });
    }

    /**
     * Add the float account under 1160 Advances & Loans if it is not there yet.
     *
     * Written as an upsert rather than a plain insert because the chart is edited
     * by hand and somebody may well have added 1165 before this runs.
     */
    private function ensureFloatAccount(): void
    {
        if (DB::table('accounts')->where('code', self::FLOAT_ACCOUNT_CODE)->exists()) {
            return;
        }

        DB::table('accounts')->insert([
            'code'            => self::FLOAT_ACCOUNT_CODE,
            'name'            => 'Petty Cash Float',
            'type'            => 'asset',
            'parent_id'       => DB::table('accounts')->where('code', '1160')->value('id'),
            'company_id'      => null,   // shared, like the rest of the chart
            'opening_balance' => 0,
            'status'          => 1,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['petty_cash_float_id']);
            $table->dropColumn('petty_cash_float_id');
        });

        Schema::dropIfExists('petty_cash_transactions');
        Schema::dropIfExists('petty_cash_floats');

        // Account 1165 is left in the chart. Dropping an account that journal
        // entries may still reference would strand them, and an unused account
        // costs nothing.
    }
};
