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
            $table->string('payment_method')->nullable()->after('paid_amount');
            $table->foreignId('bank_id')->nullable()->after('payment_method')->constrained('banks')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_flight_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_id');
            $table->dropColumn('payment_method');
        });
    }
};
