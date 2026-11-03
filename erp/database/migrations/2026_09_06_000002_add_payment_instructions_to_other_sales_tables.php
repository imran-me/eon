<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['contract_flight_bookings', 'contract_file_sales', 'visa_sales'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('payment_instruction_bank_id')
                    ->nullable()
                    ->after('bank_id')
                    ->constrained('banks')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['contract_flight_bookings', 'contract_file_sales', 'visa_sales'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('payment_instruction_bank_id');
            });
        }
    }
};
