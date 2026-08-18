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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('type', ['sale', 'purchase']);
            $table->enum('user_type', ['customer', 'supplier']);
            $table->unsignedBigInteger('account_id')->comment('payment_account');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('reference_no')->nullable();
            $table->string('payment_method')->comment('payment_method')->nullable();
            $table->decimal('old_balance', 16, 2)->default(0)->nullable();
            $table->decimal('debit', 16, 2)->default(0)->comment('out')->nullable();
            $table->decimal('credit', 16, 2)->default(0)->comment('in')->nullable();
            $table->decimal('balance', 16, 2)->default(0)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'user_type']);
            $table->index('account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};