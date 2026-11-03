<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_flight_passenger_service_costs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_flight_passenger_id');
            $table->string('cost_type', 30);
            $table->string('title')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('payment_status', 20)->default('unassigned');
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->date('incurred_on')->nullable();
            $table->date('payment_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('reference_no')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('contract_flight_passenger_id', 'cfp_service_cost_passenger_fk')
                ->references('id')->on('contract_flight_passengers')->cascadeOnDelete();
            $table->foreign('vendor_id', 'cfp_service_cost_vendor_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('bank_id', 'cfp_service_cost_bank_fk')
                ->references('id')->on('banks')->nullOnDelete();
            $table->foreign('created_by', 'cfp_service_cost_creator_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->unique(['contract_flight_passenger_id', 'cost_type'], 'cfp_service_cost_type_unique');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('contract_flight_passenger_service_costs');
    }
};
