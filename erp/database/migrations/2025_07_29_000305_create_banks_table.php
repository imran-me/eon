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
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('branch_name')->nullable();
            $table->string('account_name');
            $table->enum('account_type', ['savings', 'current', 'fixed'])->default('savings');
            $table->enum('bank_type', ['national', 'international'])->default('national');
            $table->enum('type', ['bank', 'mobile_banking', 'digital_wallet','cash'])->default('bank');
            $table->string('routing_number')->nullable();
            $table->string('account_number')->unique();
            $table->string('iban')->nullable();
            $table->string('swift_code')->nullable();
            $table->string('currency', 10);
            $table->text('address')->nullable();
            $table->boolean('status')->default(true);
            $table->decimal('balance', 15, 2)->default(0.00);
            $table->date('last_transaction_date')->nullable();
            $table->decimal('last_transaction_amount', 15, 2)->nullable();
            $table->enum('last_transaction_type', ['credit', 'debit'])->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
