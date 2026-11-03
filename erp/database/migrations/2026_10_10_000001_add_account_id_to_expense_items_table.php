<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_items', function (Blueprint $table) {
            // NULL preserves legacy lines: they use the expense's existing head.
            // Restrict deletion so a selected accounting head cannot disappear.
            $table->foreignId('account_id')->nullable()->after('expense_id')
                ->constrained('accounts')->restrictOnDelete();
            $table->foreignId('expense_category_id')->nullable()->after('account_id')
                ->constrained('expense_categories')->restrictOnDelete();
            $table->foreignId('expense_sub_category_id')->nullable()->after('expense_category_id')
                ->constrained('expense_sub_categories')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expense_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_sub_category_id');
            $table->dropConstrainedForeignId('expense_category_id');
            $table->dropConstrainedForeignId('account_id');
        });
    }
};
