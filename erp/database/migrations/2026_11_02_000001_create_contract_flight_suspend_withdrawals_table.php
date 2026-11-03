<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suspend withdraw (owner, 2026-11-02): an airline / portal suspended a ticket and another vendor was
 * hired to lift it. One row per withdrawal — its own register, not a contract_flight_passenger_changes kind.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_flight_suspend_withdrawals')) {
            return;
        }
        Schema::create('contract_flight_suspend_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_flight_passenger_id')->index('cfsw_passenger_idx');
            $table->unsignedBigInteger('contract_flight_id')->nullable();
            $table->unsignedBigInteger('ticket_purchase_id')->nullable();
            $table->unsignedBigInteger('vendor_id');                 // who lifted the suspension
            $table->decimal('cost', 14, 2)->default(0);
            $table->string('bearer', 20);                            // company | agent
            $table->decimal('our_charge', 14, 2)->default(0);
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->decimal('agent_total', 14, 2)->default(0);       // what his ONE line carries
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_flight_suspend_withdrawals');
    }
};
