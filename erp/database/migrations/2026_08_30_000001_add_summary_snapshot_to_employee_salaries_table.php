<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frozen copy of the month's attendance + task figures as they stood when the
 * payslip was issued.
 *
 * The payslip PDF is never stored as a file — EmployeeSalaryController::download(),
 * ::handleAction('download') and the mobile InvoiceReportController::download()
 * all re-render `employee-salaries.pdf` from `employee_salaries` on every
 * request. So anything shown on the payslip that is computed live would change
 * underneath the employee: attendance rows get corrected and leaves get
 * approved retroactively, and the payslip mailed in August would quote
 * different numbers when re-downloaded in November, with no way to tell which
 * copy was right.
 *
 * Writing the summary here at issue time makes the payslip reproducible: every
 * renderer reads this snapshot and none of them recompute.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->json('summary_snapshot')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->dropColumn('summary_snapshot');
        });
    }
};
