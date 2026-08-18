<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();

            // polymorphic: Sale | Purchase | TicketSale | TicketPurchase
            $table->string('schedulable_type');
            $table->unsignedBigInteger('schedulable_id');

            $table->string('type');               // 'receive' (AR) | 'pay' (AP)
            $table->string('party_type');         // 'customer' | 'vendor' | 'agent'
            $table->unsignedBigInteger('party_id');

            $table->decimal('amount', 15, 2);
            $table->date('scheduled_date');

            $table->string('status')->default('pending'); // pending | paid | overdue | cancelled
            $table->date('paid_date')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->text('note')->nullable();

            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->index(['schedulable_type', 'schedulable_id']);
            $table->index('scheduled_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_schedules');
    }
};
