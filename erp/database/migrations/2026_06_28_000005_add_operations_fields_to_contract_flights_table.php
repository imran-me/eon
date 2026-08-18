<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_flights', function (Blueprint $table) {
            $table->string('ticket_class', 50)->default('economy')->after('handling_type');
            $table->dateTime('arrival_at')->nullable()->after('departure_at');
            $table->foreignId('boarding_officer_id')->nullable()->after('agent_id')->constrained('flight_officers')->nullOnDelete();
            $table->foreignId('immigration_officer_id')->nullable()->after('boarding_officer_id')->constrained('flight_officers')->nullOnDelete();
            $table->decimal('ticket_cost_per_pax', 12, 2)->default(0)->after('revenue');
            $table->decimal('manpower_cost_per_pax', 12, 2)->default(0)->after('ticket_cost_per_pax');
            $table->decimal('boarding_cost_per_pax', 12, 2)->default(0)->after('manpower_cost_per_pax');
            $table->decimal('immigration_cost_per_pax', 12, 2)->default(0)->after('boarding_cost_per_pax');
            $table->decimal('sale_price_per_pax', 12, 2)->default(0)->after('immigration_cost_per_pax');
        });
    }

    public function down(): void
    {
        Schema::table('contract_flights', function (Blueprint $table) {
            $table->dropForeign(['boarding_officer_id']);
            $table->dropForeign(['immigration_officer_id']);
            $table->dropColumn([
                'ticket_class',
                'arrival_at',
                'boarding_officer_id',
                'immigration_officer_id',
                'ticket_cost_per_pax',
                'manpower_cost_per_pax',
                'boarding_cost_per_pax',
                'immigration_cost_per_pax',
                'sale_price_per_pax',
            ]);
        });
    }
};
