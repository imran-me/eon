<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_flight_bookings', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('agent_id')->constrained('users')->nullOnDelete();
        });

        // Every existing booking's agent_id becomes its client_id — agent and
        // customer are now the same unified "party" concept for this sale type.
        DB::statement('UPDATE contract_flight_bookings SET client_id = agent_id WHERE agent_id IS NOT NULL');

        Schema::table('contract_flight_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_id');
            $table->dropColumn(['client_name', 'client_phone']);
        });
    }

    public function down(): void
    {
        Schema::table('contract_flight_bookings', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->after('booking_number')->constrained('users')->nullOnDelete();
            $table->string('client_name')->nullable()->after('booking_number');
            $table->string('client_phone')->nullable()->after('client_name');
        });

        DB::statement('UPDATE contract_flight_bookings SET agent_id = client_id WHERE client_id IS NOT NULL');

        Schema::table('contract_flight_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });
    }
};
