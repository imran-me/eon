<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_todo_checklists', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('office_todo_id')
                ->constrained('office_todo_checklists')
                ->cascadeOnDelete();

            $table->enum('status', ['pending', 'in_progress', 'completed'])
                ->default('pending')
                ->after('priority');
        });

        // is_checked stays the canonical "completed" flag — the mobile API still
        // writes it directly — so seed status from it rather than the other way round.
        DB::table('office_todo_checklists')
            ->where('is_checked', true)
            ->update(['status' => 'completed']);
    }

    public function down(): void
    {
        Schema::table('office_todo_checklists', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'status']);
        });
    }
};
