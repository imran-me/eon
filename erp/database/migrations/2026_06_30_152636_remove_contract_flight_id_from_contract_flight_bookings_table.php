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
        Schema::table('contract_flight_bookings', function (Blueprint $table) {
            $table->dropForeign(['contract_flight_id']);
            $table->dropColumn('contract_flight_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_flight_bookings', function (Blueprint $table) {
            $table->foreignId('contract_flight_id')->constrained('contract_flights')->cascadeOnDelete();
        });
    }
};
