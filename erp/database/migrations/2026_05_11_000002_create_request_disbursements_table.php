<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('employee_requests')->cascadeOnDelete();
            $table->enum('payment_method', ['cash', 'bank_transfer', 'cheque', 'payroll_deduction'])->default('bank_transfer');
            $table->decimal('disbursed_amount', 15, 2);
            $table->foreignId('disbursed_by')->constrained('users');
            $table->timestamp('disbursed_at');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->string('attachment')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_disbursements');
    }
};
