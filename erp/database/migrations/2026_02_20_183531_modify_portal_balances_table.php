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
        Schema::table('portal_balances', function (Blueprint $table) {                        
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('payment_method')->comment('payment_method')->nullable();
        });        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('portal_balances', function (Blueprint $table) {
            $table->dropColumn('invoice_id')->nullable();
            $table->dropColumn('payment_method')->nullable();
        });
    }
};