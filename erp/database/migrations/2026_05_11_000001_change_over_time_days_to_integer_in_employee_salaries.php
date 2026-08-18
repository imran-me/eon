<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Change over_time_days from date to integer, keeping the column name
        if (Schema::hasColumn('employee_salaries', 'over_time_days')) {
            DB::statement('ALTER TABLE employee_salaries MODIFY over_time_days INT NOT NULL DEFAULT 0');
        }

        // Fix any environment where the column was incorrectly renamed to over_time_days_int
        if (Schema::hasColumn('employee_salaries', 'over_time_days_int') && !Schema::hasColumn('employee_salaries', 'over_time_days')) {
            DB::statement('ALTER TABLE employee_salaries CHANGE over_time_days_int over_time_days INT NOT NULL DEFAULT 0');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employee_salaries', 'over_time_days')) {
            DB::statement('ALTER TABLE employee_salaries MODIFY over_time_days DATE NULL DEFAULT NULL');
        }
    }
};
