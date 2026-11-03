<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_sales', function (Blueprint $table) {
            $table->foreignId('payment_instruction_bank_id')
                ->nullable()
                ->after('bank_id')
                ->constrained('banks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_instruction_bank_id');
        });
    }
};
