<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_terms', function (Blueprint $table) {
            $table->string('project_code', 40)->nullable()->after('expense_department_id');
            $table->foreignId('manager_user_id')->nullable()->after('client_party_id')->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable()->after('manager_user_id');
            $table->date('end_date')->nullable()->after('start_date');
            $table->unsignedTinyInteger('progress_percent')->nullable()->after('end_date');
            $table->string('construction_status', 30)->nullable()->after('progress_percent');
            $table->string('project_type', 80)->nullable()->after('construction_status');
            $table->unique('project_code');
        });
    }

    public function down(): void
    {
        Schema::table('project_terms', function (Blueprint $table) {
            $table->dropUnique(['project_code']);
            $table->dropConstrainedForeignId('manager_user_id');
            $table->dropColumn(['project_code', 'start_date', 'end_date', 'progress_percent', 'construction_status', 'project_type']);
        });
    }
};
