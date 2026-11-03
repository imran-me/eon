<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advance_salaries', function (Blueprint $table) {
            $table->foreignId('employee_request_id')->nullable()->unique()->after('id')
                ->constrained('employee_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('advance_salaries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_request_id');
        });
    }
};
