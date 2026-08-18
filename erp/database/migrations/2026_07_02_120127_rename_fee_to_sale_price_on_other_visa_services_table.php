<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add sale_price after cost_price, copy data from fee, then drop fee
        Schema::table('other_visa_services', function (Blueprint $table) {
            $table->decimal('sale_price', 12, 2)->default(0)->after('cost_price');
        });

        DB::statement('UPDATE other_visa_services SET sale_price = fee');

        Schema::table('other_visa_services', function (Blueprint $table) {
            $table->dropColumn('fee');
        });
    }

    public function down(): void
    {
        Schema::table('other_visa_services', function (Blueprint $table) {
            $table->decimal('fee', 12, 2)->default(0)->after('assigned_officer_id');
        });

        DB::statement('UPDATE other_visa_services SET fee = sale_price');

        Schema::table('other_visa_services', function (Blueprint $table) {
            $table->dropColumn('sale_price');
        });
    }
};
