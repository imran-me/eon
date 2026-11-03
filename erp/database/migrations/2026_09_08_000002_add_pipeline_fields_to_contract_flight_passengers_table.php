<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_flight_passengers', function (Blueprint $table) {
            $table->string('stage', 30)->default('agent')->after('passport_holder_id');
            $table->string('exception_flag', 20)->nullable()->after('stage');
            $table->dateTime('contract_confirmed_at')->nullable()->after('exception_flag');

            $table->boolean('has_bmet')->default(false)->after('contract_confirmed_at');
            $table->boolean('has_manpower_card')->default(false)->after('has_bmet');
            $table->decimal('manpower_card_fee', 12, 2)->nullable()->after('has_manpower_card');

            $table->foreignId('agent_id')->nullable()->after('manpower_card_fee')->constrained('users')->nullOnDelete();
            $table->decimal('agent_price', 12, 2)->nullable()->after('agent_id');
            $table->decimal('reissue_cost', 12, 2)->nullable()->after('agent_price');

            $table->string('contact_phone', 30)->nullable()->after('reissue_cost');
            $table->text('remarks')->nullable()->after('contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('contract_flight_passengers', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->dropColumn([
                'stage',
                'exception_flag',
                'contract_confirmed_at',
                'has_bmet',
                'has_manpower_card',
                'manpower_card_fee',
                'agent_id',
                'agent_price',
                'reissue_cost',
                'contact_phone',
                'remarks',
            ]);
        });
    }
};
