<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ticket_sales', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('agent_id')->constrained('users')->nullOnDelete();
        });

        // Every existing sale's agent_id becomes its client_id — agent and
        // customer are now the same unified "party" concept for this sale type,
        // matching contract_flight_bookings/contract_file_sales/visa_sales.
        DB::statement('UPDATE ticket_sales SET client_id = agent_id WHERE agent_id IS NOT NULL');

        Schema::table('ticket_sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_sales', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        DB::statement('UPDATE ticket_sales SET agent_id = client_id WHERE client_id IS NOT NULL');

        Schema::table('ticket_sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });
    }
};
