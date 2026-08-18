<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_flight_passengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_flight_id')->constrained('contract_flights')->cascadeOnDelete();
            $table->foreignId('passport_holder_id')->constrained('passport_holders')->cascadeOnDelete();
            $table->json('document_statuses')->nullable();
            $table->timestamps();
            $table->unique(['contract_flight_id', 'passport_holder_id'], 'cf_passenger_flight_holder_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_flight_passengers');
    }
};
