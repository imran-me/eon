<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_reissues', function (Blueprint $table) {
            // Who bears the re-issue charge — mirrors ContractFlightPassenger::REISSUE_BEARERS.
            // Defaults to 'agent' so existing rows (always billed to the agent before this
            // column existed) read exactly as they always have.
            $table->string('bearer', 20)->default('agent')->after('agent_id');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_reissues', function (Blueprint $table) {
            $table->dropColumn('bearer');
        });
    }
};
