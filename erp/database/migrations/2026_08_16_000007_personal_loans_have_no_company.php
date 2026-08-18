<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A personal loan does not belong to a company.
 *
 * The first cut made company_id mandatory on every row, which forced the create
 * form to ask "which company?" even for the boss's own borrowing. That question
 * has no honest answer: a personal loan is not the group's liability, it does
 * not belong on any company's balance sheet, and attributing it to one is
 * exactly the confusion the taken_for flag exists to prevent.
 *
 * So company_id becomes nullable, and NULL means "not a company's debt".
 *
 * This also settles who may see it, and settles it correctly. The register
 * scopes rows by company for anyone without "view all financing"; a row with no
 * company therefore matches no company-locked user and is visible only to
 * someone holding the group-wide permission. The boss's personal borrowing does
 * not appear to a company accountant, which is the behaviour you would want
 * even if the accounting did not already demand it.
 *
 * `personal_of` names whose loan it is, because on a personal row the
 * counterparty column holds the LENDER, and without this there is nowhere to
 * record the borrower.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop the FK before altering the column: MySQL will not change a column
        // that a constraint still depends on.
        Schema::table('financing_loans', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });

        Schema::table('financing_loans', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->change();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();

            $table->string('personal_of')->nullable()->after('taken_for');
        });
    }

    public function down(): void
    {
        Schema::table('financing_loans', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('personal_of');
        });

        Schema::table('financing_loans', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });
    }
};
