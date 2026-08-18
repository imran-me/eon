<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_reissues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('original_sale_id')->nullable()->index();
            $table->unsignedBigInteger('original_purchase_id')->nullable();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->unsignedBigInteger('new_ticket_id')->nullable();
            $table->unsignedBigInteger('new_vendor_id')->nullable();
            $table->unsignedBigInteger('new_portal_id')->nullable();
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->string('original_invoice')->index();
            $table->string('new_ticket_no');
            $table->enum('new_trip_type', ['one-way', 'two-way'])->default('one-way');
            $table->date('reissue_date');
            $table->date('new_travel_date')->nullable();
            $table->date('new_purchase_date');
            $table->decimal('org_cost', 12, 2)->default(0);
            $table->decimal('penalty', 12, 2)->default(0);
            $table->decimal('fare_diff', 12, 2)->default(0);
            $table->decimal('new_cost', 12, 2)->default(0);
            $table->decimal('service_charge', 12, 2)->default(0);
            $table->decimal('new_sale_price', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('due_amount', 12, 2)->default(0);
            $table->enum('payment_status', ['due', 'partial', 'paid'])->default('due');
            $table->string('pay_method')->nullable();
            $table->enum('status', ['pending', 'confirm', 'cancelled'])->default('confirm');
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
        Schema::dropIfExists('ticket_reissues');
    }
};
