<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Some installations received these tables before this migration was
        // added to their migration history. Adopt those existing tables rather
        // than failing the entire deployment with "table already exists".
        if (! Schema::hasTable('employee_payments')) {
            Schema::create('employee_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                // New payments always supply a bank. Historical backfills may not
                // have enough surviving evidence to identify the original account,
                // so keep it null instead of inventing misleading audit data.
                $table->foreignId('bank_id')->nullable()->constrained('banks')->restrictOnDelete();
                $table->date('payment_date');
                $table->string('payment_method')->nullable();
                $table->string('reference')->unique();
                $table->decimal('amount', 15, 2);
                $table->text('note')->nullable();
                $table->string('status')->default('posted'); // posted | reversed
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reversed_at')->nullable();
                $table->text('reversal_reason')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'payment_date']);
            });
        }

        if (! Schema::hasTable('employee_payment_allocations')) {
            Schema::create('employee_payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_payment_id')->constrained('employee_payments')->restrictOnDelete();
                $table->foreignId('employee_salary_id')->constrained('employee_salaries')->restrictOnDelete();
                $table->decimal('amount', 15, 2);
                $table->timestamps();
                $table->unique(['employee_payment_id', 'employee_salary_id'], 'employee_payment_salary_unique');
                $table->index('employee_salary_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_payment_allocations');
        Schema::dropIfExists('employee_payments');
    }
};
