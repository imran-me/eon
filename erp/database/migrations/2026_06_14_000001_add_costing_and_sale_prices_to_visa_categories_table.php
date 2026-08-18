<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visa_categories', function (Blueprint $table) {
            $table->decimal('costing_price', 15, 2)->default(0)->after('our_fee');
            $table->decimal('sale_price', 15, 2)->default(0)->after('costing_price');
        });
    }

    public function down(): void
    {
        Schema::table('visa_categories', function (Blueprint $table) {
            $table->dropColumn(['costing_price', 'sale_price']);
        });
    }
};
