<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_flight_booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_flight_booking_id')->constrained('contract_flight_bookings')->cascadeOnDelete();
            $table->foreignId('contract_flight_id')->constrained('contract_flights')->cascadeOnDelete();
            $table->unsignedInteger('seats')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['contract_flight_booking_id', 'contract_flight_id'],'cf_booking_item_flight_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_flight_booking_items');
    }
};
