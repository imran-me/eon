<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_flights', function (Blueprint $table) {
            $table->foreignId('ticket_id')->nullable()->after('flight_category_id')
                ->constrained('tickets')->nullOnDelete();
        });

        Schema::table('contract_flights', function (Blueprint $table) {
            $table->dropForeign(['airline_id']);
            $table->dropColumn(['airline_id', 'route']);
        });
    }

    public function down(): void
    {
        Schema::table('contract_flights', function (Blueprint $table) {
            $table->foreignId('airline_id')->nullable()->after('flight_category_id')->constrained('airlines')->cascadeOnDelete();
            $table->string('route')->nullable()->after('airline_id');
        });

        Schema::table('contract_flights', function (Blueprint $table) {
            $table->dropForeign(['ticket_id']);
            $table->dropColumn('ticket_id');
        });
    }
};
