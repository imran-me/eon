<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_todo_checklists', function (Blueprint $table) {
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium')->after('title');
            $table->date('start_date')->nullable()->after('priority');
            $table->date('end_date')->nullable()->after('start_date');
        });
    }

    public function down(): void
    {
        Schema::table('office_todo_checklists', function (Blueprint $table) {
            $table->dropColumn(['priority', 'start_date', 'end_date']);
        });
    }
};
