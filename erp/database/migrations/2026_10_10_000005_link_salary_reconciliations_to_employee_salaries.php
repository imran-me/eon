<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_reconciliations', function (Blueprint $table) {
            $table->foreignId('employee_salary_id')->nullable()->after('company_id')
                ->constrained('employee_salaries')->nullOnDelete();
            $table->decimal('earned_days', 8, 2)->default(0)->after('period_end');
            $table->decimal('encashable_days', 8, 2)->default(0)->after('earned_days');
        });
    }

    public function down(): void
    {
        Schema::table('salary_reconciliations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_salary_id');
            $table->dropColumn(['earned_days', 'encashable_days']);
        });
    }
};
