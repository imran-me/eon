<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visa_sale_items', function (Blueprint $table) {
            $table->foreignId('other_visa_service_id')->nullable()->after('visa_process_id')
                ->constrained('other_visa_services')->cascadeOnDelete();
        });

        Schema::table('visa_sale_items', function (Blueprint $table) {
            $table->foreignId('visa_process_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('visa_sale_items', function (Blueprint $table) {
            $table->dropForeign(['other_visa_service_id']);
            $table->dropColumn('other_visa_service_id');
            $table->unsignedBigInteger('visa_process_id')->nullable(false)->change();
        });
    }
};
