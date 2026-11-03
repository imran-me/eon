<?php

use App\Services\ContractFlightMessenger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per scenario: who gets told, on which channels, with which
        // wording. Seeded with every scenario the messenger knows; the
        // Messages page edits them in place.
        Schema::create('contract_flight_message_rules', function (Blueprint $table) {
            $table->id();
            $table->string('scenario', 40)->unique();
            $table->boolean('auto')->default(false);
            $table->boolean('to_passenger')->default(true);
            $table->boolean('to_agent')->default(true);
            $table->boolean('via_sms')->default(true);
            $table->boolean('via_email')->default(true);
            $table->text('passenger_template')->nullable();
            $table->text('agent_template')->nullable();
            $table->timestamps();
        });

        // Every message that left (or tried to), whoever it went to.
        Schema::create('contract_flight_messages', function (Blueprint $table) {
            $table->id();
            $table->string('scenario', 40)->index();
            $table->string('trigger', 10)->default('manual');   // manual | auto
            $table->unsignedBigInteger('contract_flight_id')->nullable()->index();
            $table->unsignedBigInteger('contract_flight_passenger_id')->nullable()->index();
            $table->string('recipient_type', 12);                // passenger | agent
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('channel', 10);                       // sms | email | whatsapp
            $table->string('to')->nullable();                    // phone or address
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('status', 10)->default('sent');       // sent | failed | skipped | prepared
            $table->text('error')->nullable();
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('contract_flight_message_rules')->insert(array_map(fn ($key, $s) => [
            'scenario' => $key, 'auto' => $s['auto'], 'to_passenger' => $s['to_passenger'], 'to_agent' => $s['to_agent'],
            'via_sms' => true, 'via_email' => true,
            'passenger_template' => $s['passenger'], 'agent_template' => $s['agent'],
            'created_at' => $now, 'updated_at' => $now,
        ], array_keys(ContractFlightMessenger::SCENARIOS), ContractFlightMessenger::SCENARIOS));
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_flight_messages');
        Schema::dropIfExists('contract_flight_message_rules');
    }
};
