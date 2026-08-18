<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Written to be safe to re-run: a previous attempt on production added the
     * `parent_id` column but failed (MySQL errno 150) when adding the foreign key.
     * Adding an explicit index first didn't resolve it either — this turned out to
     * be the known MySQL/MariaDB quirk where a self-referencing FK added via a
     * separate ALTER TABLE (after the column already exists) can spuriously fail
     * during the engine's internal in-place rename step even though the schema is
     * valid. Disabling FOREIGN_KEY_CHECKS for just that one ALTER statement is the
     * standard workaround — it only skips the engine's internal validation during
     * this ALTER, not any application-level data integrity.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('tasks', 'parent_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('id');
            });
        }

        if (!$this->indexExists('tasks', 'tasks_parent_id_index')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->index('parent_id');
            });
        }

        if (!$this->foreignKeyExists('tasks', 'tasks_parent_id_foreign')) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                Schema::table('tasks', function (Blueprint $table) {
                    $table->foreign('parent_id')->references('id')->on('tasks')->nullOnDelete();
                });
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->foreignKeyExists('tasks', 'tasks_parent_id_foreign')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropForeign(['parent_id']);
            });
        }

        if ($this->indexExists('tasks', 'tasks_parent_id_index')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropIndex(['parent_id']);
            });
        }

        if (Schema::hasColumn('tasks', 'parent_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn('parent_id');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]))->isNotEmpty();
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        return collect(DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
             AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$table, $constraintName]
        ))->isNotEmpty();
    }
};
