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
        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_id')->nullable()->after('payment_method');
            $table->foreign('bank_id')->references('id')->on('banks')->onDelete('set null');
        });
        Schema::table('purchases', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_id')->nullable()->after('payment_method');
            $table->foreign('bank_id')->references('id')->on('banks')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('bank_id');
        });
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('bank_id');
        });
    }
};
