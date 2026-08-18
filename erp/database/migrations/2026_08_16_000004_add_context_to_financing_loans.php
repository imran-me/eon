<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Context columns for the loan book — who a loan is really for, and what it
 * was raised against.
 *
 * The first cut recorded the counterparty and the terms but not the answer to
 * two questions the owner asks of every row:
 *
 *   BORROWED — "is this the company's debt or the boss's personal one?"
 *              Both are tracked here, but they are not the same liability and a
 *              register that cannot separate them is misleading. `taken_for`
 *              makes it explicit and `department_id` narrows a company loan to
 *              the department that carries it.
 *
 *   LENT     — "which sale is this instalment plan against?" When a client buys
 *              a visa or a flight, pays part, and takes the balance over months,
 *              the financed amount is only half the story: `down_payment` keeps
 *              what they already paid, so the register can show the real deal
 *              value rather than just the part still owed.
 *
 * `linked_type` / `linked_id` point at the originating sale where there is one,
 * with `linked_label` holding a readable reference. The label is stored rather
 * than resolved because those records live in other modules, and a register
 * must not break when one of them is archived.
 *
 * Every column is nullable or defaulted, so existing rows and the create form
 * keep working untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financing_loans', function (Blueprint $table) {
            // Only meaningful on the borrowed book. Null on a lent loan.
            $table->enum('taken_for', ['company', 'personal'])->nullable()->after('direction')->index();

            $table->foreignId('department_id')->nullable()->after('company_id')
                  ->constrained('departments')->nullOnDelete();

            // What the customer paid before the balance was financed. Principal
            // stays the FINANCED amount, so no figure double counts.
            $table->decimal('down_payment', 15, 2)->default(0)->after('principal');

            $table->string('linked_type', 60)->nullable()->after('account_no');
            $table->unsignedBigInteger('linked_id')->nullable()->after('linked_type');
            $table->string('linked_label')->nullable()->after('linked_id');
            $table->index(['linked_type', 'linked_id']);
        });
    }

    public function down(): void
    {
        Schema::table('financing_loans', function (Blueprint $table) {
            $table->dropIndex(['linked_type', 'linked_id']);
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['taken_for', 'down_payment', 'linked_type', 'linked_id', 'linked_label']);
        });
    }
};
