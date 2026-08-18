<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_recoveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('employee_requests')->cascadeOnDelete();
            $table->unsignedBigInteger('payslip_id')->nullable();
            $table->unsignedInteger('installment_no');
            $table->decimal('deducted_amount', 15, 2);
            $table->decimal('remaining_balance', 15, 2)->default(0);
            $table->date('deducted_at');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_recoveries');
    }
};
