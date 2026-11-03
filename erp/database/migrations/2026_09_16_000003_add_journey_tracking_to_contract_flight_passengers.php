<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_flight_passengers', function (Blueprint $table) {
            $table->string('stage', 30)->default('passenger_received')->change();
        });
        if (! Schema::hasColumn('contract_flight_passengers', 'agent_paid_amount')) {
            Schema::table('contract_flight_passengers', function (Blueprint $table) {
                $table->decimal('agent_paid_amount', 12, 2)->default(0)->after('agent_price');
            });
        }
        if (! Schema::hasColumn('contract_flight_passengers', 'agent_paid_at')) {
            Schema::table('contract_flight_passengers', function (Blueprint $table) {
                $table->dateTime('agent_paid_at')->nullable()->after('agent_paid_amount');
            });
        }

        Schema::create('contract_flight_passenger_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_flight_passenger_id');
            $table->string('type', 30);
            $table->string('code', 40)->nullable();
            $table->string('label', 120);
            $table->text('note')->nullable();
            $table->dateTime('occurred_at');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->foreign('contract_flight_passenger_id', 'cfp_events_passenger_fk')
                ->references('id')->on('contract_flight_passengers')->cascadeOnDelete();
            $table->foreign('created_by', 'cfp_events_creator_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->index(['contract_flight_passenger_id', 'occurred_at'], 'cfp_events_passenger_occurred_idx');
        });

        $map = [
            'agent' => 'passenger_received', 'manpower_bmet' => 'documents_verified',
            'document_prep' => 'documents_verified', 'ticketing' => 'ticket_issued',
            'airport' => 'airport_processing', 'departure' => 'departed',
            'entry' => 'landed', 'track' => 'arrival_confirmed',
            'crm' => 'arrival_confirmed', 'schedule' => 'case_closed',
            'refund_reissue' => 'ticket_issued', 'contract_track' => 'case_closed',
        ];
        foreach ($map as $old => $new) {
            DB::table('contract_flight_passengers')->where('stage', $old)->update(['stage' => $new]);
        }
        DB::table('contract_flight_passengers')->where('exception_flag', 'cancelled')->update(['exception_flag' => 'flight_cancelled']);

        $labels = [
            'passenger_received' => 'Passenger Received', 'information_entered' => 'Information Entered',
            'documents_received' => 'Documents Received', 'documents_verified' => 'Documents Verified',
            'service_defined' => 'Service / Category Defined', 'route_confirmed' => 'Route Confirmed',
            'ticket_issued' => 'Ticket Issued', 'ready_for_travel' => 'Ready for Travel',
            'preflight_coordination' => 'Pre-flight Coordination', 'airport_processing' => 'Airport Processing',
            'immigration' => 'Immigration', 'boarding' => 'Boarding', 'departed' => 'Departed',
            'transit' => 'Transit Handling', 'landed' => 'Landed',
            'arrival_confirmed' => 'Arrival Confirmed', 'case_closed' => 'Case Closed',
        ];
        DB::table('contract_flight_passengers')->orderBy('id')->chunkById(500, function ($passengers) use ($labels) {
            $now = now();
            DB::table('contract_flight_passenger_events')->insert($passengers->map(fn ($passenger) => [
                'contract_flight_passenger_id' => $passenger->id,
                'type' => 'stage', 'code' => $passenger->stage,
                'label' => $labels[$passenger->stage] ?? ucfirst(str_replace('_', ' ', $passenger->stage)),
                'note' => 'Opening stage when journey history was enabled',
                'occurred_at' => $now, 'created_by' => null, 'created_at' => $now, 'updated_at' => $now,
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_flight_passenger_events');
        Schema::table('contract_flight_passengers', function (Blueprint $table) {
            $table->string('stage', 30)->default('agent')->change();
            $table->dropColumn(['agent_paid_amount', 'agent_paid_at']);
        });
    }
};
