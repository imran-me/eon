<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_department_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('generation_day')->default(1);
            $table->date('start_month');
            $table->date('end_month')->nullable();
            $table->enum('payment_mode', ['cash', 'mobile_banking', 'bank_transfer', 'digital_wallet', 'other'])->default('cash');
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['is_active', 'generation_day', 'start_month'], 'recurring_expenses_due_idx');
        });

        Schema::create('recurring_expense_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('expense_sub_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('recurring_expense_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_expense_id')->constrained()->cascadeOnDelete();
            $table->date('period');
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->timestamp('generated_at');
            $table->unique(['recurring_expense_id', 'period'], 'recurring_expense_period_unique');
            $table->unique('expense_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_expense_generations');
        Schema::dropIfExists('recurring_expense_lines');
        Schema::dropIfExists('recurring_expenses');
    }
};
