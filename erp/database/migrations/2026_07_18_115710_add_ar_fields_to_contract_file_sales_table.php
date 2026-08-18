<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_file_sales', function (Blueprint $table) {
            $table->decimal('due_amount', 15, 2)->nullable()->default(0)->after('paid_amount');
            $table->date('receivable_date')->nullable()->after('sale_date');
        });
    }

    public function down(): void
    {
        Schema::table('contract_file_sales', function (Blueprint $table) {
            $table->dropColumn(['due_amount', 'receivable_date']);
        });
    }
};
