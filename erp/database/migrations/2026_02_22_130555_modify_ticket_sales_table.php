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
        Schema::table('ticket_sales', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('sale_date');
            $table->unsignedBigInteger('bank_id')->nullable()->after('sale_date');
            $table->decimal('paid_amount', 10, 2)->default(0.00)->after('sale_date');
            $table->decimal('due_amount', 10, 2)->default(0.00)->after('sale_date');
            $table->enum('payment_status', ['due', 'paid', 'partial'])->default('due')->after('sale_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_sales', function (Blueprint $table) {
            $table->dropColumn('company_id');
            $table->dropColumn('bank_id');
            $table->dropColumn('paid_amount');
            $table->dropColumn('due_amount');
            $table->dropColumn('payment_status');
        });   
    }
};
