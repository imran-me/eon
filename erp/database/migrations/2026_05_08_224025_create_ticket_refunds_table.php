<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('ticket_sale_id')->nullable()->index();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('portal_id')->nullable();
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->string('original_invoice')->index();
            $table->string('refund_ref_no')->nullable();
            $table->date('refund_date');
            $table->decimal('org_cost', 12, 2)->default(0);
            $table->decimal('airline_refund', 12, 2)->default(0);
            $table->decimal('penalty', 12, 2)->default(0);
            $table->decimal('service_charge', 12, 2)->default(0);
            $table->decimal('org_sale', 12, 2)->default(0);
            $table->decimal('net_refund', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('due_amount', 12, 2)->default(0);
            $table->enum('payment_status', ['due', 'partial', 'paid'])->default('due');
            $table->string('pay_method')->nullable();
            $table->enum('status', ['confirm', 'processing', 'pending_airline', 'completed'])->default('confirm');
            $table->string('currency', 5)->default('BDT');
            $table->string('attachment')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_refunds');
    }
};
