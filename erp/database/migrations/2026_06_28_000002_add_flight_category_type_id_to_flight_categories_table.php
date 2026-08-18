<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flight_categories', function (Blueprint $table) {
            $table->foreignId('flight_category_type_id')->nullable()->after('name')
                ->constrained('flight_category_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('flight_categories', function (Blueprint $table) {
            $table->dropForeign(['flight_category_type_id']);
            $table->dropColumn('flight_category_type_id');
        });
    }
};
