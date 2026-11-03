<?php

use App\Models\ContractFlightStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The ops sheet tracks "Call Done / Pending" per passenger — the
 * confirmation phone call before the flight. Slot it into the live
 * pipeline right after Ready for Travel on installs already seeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contract_flight_stages')) {
            return;
        }
        if (DB::table('contract_flight_stages')->where('key', 'confirmation_call')->exists()) {
            return;
        }

        $after = DB::table('contract_flight_stages')->where('key', 'ready_for_travel')->value('sort_order');
        $order = $after === null
            ? (int) DB::table('contract_flight_stages')->max('sort_order') + 1
            : (int) $after + 1;

        DB::table('contract_flight_stages')->where('sort_order', '>=', $order)->increment('sort_order');
        DB::table('contract_flight_stages')->insert([
            'key' => 'confirmation_call', 'label' => 'Confirmation Call', 'icon' => 'fa-phone-volume',
            'sort_order' => $order, 'is_conditional' => false, 'is_system' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('contract_flight_stages')) {
            DB::table('contract_flight_stages')->where('key', 'confirmation_call')->delete();
        }
    }
};
