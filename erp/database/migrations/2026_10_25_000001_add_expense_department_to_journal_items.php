<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_items', function (Blueprint $table) {
            $table->foreignId('expense_department_id')
                ->nullable()
                ->after('account_id')
                ->constrained('expense_departments')
                ->nullOnDelete();

            $table->index(['expense_department_id', 'account_id'], 'journal_items_department_account_idx');
        });
    }

    public function down(): void
    {
        Schema::table('journal_items', function (Blueprint $table) {
            $table->dropIndex('journal_items_department_account_idx');
            $table->dropConstrainedForeignId('expense_department_id');
        });
    }
};
