<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visa_sales', function (Blueprint $table) {
            $table->date('receivable_date')->nullable()->after('voucher_date');
        });

        Schema::table('visa_sales', function (Blueprint $table) {
            $table->dropColumn('payment_terms');
        });
    }

    public function down(): void
    {
        Schema::table('visa_sales', function (Blueprint $table) {
            $table->string('payment_terms')->default('full_today')->after('voucher_date');
        });

        Schema::table('visa_sales', function (Blueprint $table) {
            $table->dropColumn('receivable_date');
        });
    }
};
