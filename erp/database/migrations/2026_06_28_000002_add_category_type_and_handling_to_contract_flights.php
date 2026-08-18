<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_flights', function (Blueprint $table) {
            $table->foreignId('flight_category_type_id')->nullable()->after('flight_category_id')
                ->constrained('flight_category_types')->nullOnDelete();
            $table->enum('handling_type', ['manpower_wise', 'immigration_wise'])
                ->default('manpower_wise')->after('flight_category_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('contract_flights', function (Blueprint $table) {
            $table->dropForeign(['flight_category_type_id']);
            $table->dropColumn(['flight_category_type_id', 'handling_type']);
        });
    }
};
