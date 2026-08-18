<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visa_processes', function (Blueprint $table) {
            $table->decimal('costing_price', 15, 2)->default(0)->after('our_service_fee');
            $table->decimal('sale_price', 15, 2)->default(0)->after('costing_price');
            $table->date('payable_date')->nullable()->after('advance_received');
            $table->date('receivable_date')->nullable()->after('payable_date');
            $table->string('payment_status')->default('pending')->after('receivable_date');
        });
    }

    public function down(): void
    {
        Schema::table('visa_processes', function (Blueprint $table) {
            $table->dropColumn(['costing_price', 'sale_price', 'payable_date', 'receivable_date', 'payment_status']);
        });
    }
};
