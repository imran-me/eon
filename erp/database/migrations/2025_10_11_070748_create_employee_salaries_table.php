<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('salary_template_id')->constrained('salary_templates')->onDelete('cascade');
            $table->unsignedBigInteger('loan_id')->nullable();
            $table->unsignedBigInteger('advance_salary_id')->nullable();
            $table->string('month');
            $table->integer('year');
            $table->string('bonus_label')->nullable();
            $table->decimal('bonus_amount', 15, 2)->default(0);
            $table->decimal('gross_salary', 15, 2)->default(0);
            $table->decimal('loan_deduction', 15, 2)->default(0);
            $table->decimal('advance_salary_deduction', 15, 2)->default(0);
            $table->decimal('absent_deduction', 15, 2)->default(0);
            $table->decimal('late_deduction', 15, 2)->default(0);
            $table->decimal('early_leave_deduction', 15, 2)->default(0);
            $table->decimal('salary_adjustment', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2)->default(0);
            $table->date('over_time_days')->nullable();
            $table->decimal('over_time', 15, 2)->default(0);
            $table->date('salary_generation_date')->nullable()->comment('salary generation date');
            $table->string('payment_method')->nullable();
            $table->enum('status', ['Pending', 'Paid'])->default('Pending');
            $table->text('notes')->nullable();
            $table->index(['user_id', 'month', 'year']);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_salaries');
    }
};
