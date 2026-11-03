<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks an employee as being on a fixed salary: attendance never moves their
 * pay. Absent / leave / late / early-out deductions are all suppressed for
 * them, and they are excluded from the annual February leave-encashment
 * payout (the encashment exists to refund leave deductions they never take).
 *
 * Deliberately does NOT suppress loan or advance-salary instalments — those
 * recover money already handed to the employee, not attendance penalties —
 * and does not touch overtime, which stays gated on `overtime_eligible`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('fixed_salary')->default(false)->after('auto_payslip_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('fixed_salary');
        });
    }
};
