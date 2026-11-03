<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The airline that flies one sector of a ticket (owner, 2026-10-07): one ticket
// DAC-BAH-IST can be Qatar Airways DAC → BAH and Pegasus BAH → IST. Optional.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ticket_legs', 'airline_id')) {
            return;
        }
        Schema::table('ticket_legs', function (Blueprint $table) {
            $table->unsignedBigInteger('airline_id')->nullable()->after('to_location')->index();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('ticket_legs', 'airline_id')) {
            Schema::table('ticket_legs', function (Blueprint $table) {
                $table->dropIndex(['airline_id']);
                $table->dropColumn('airline_id');
            });
        }
    }
};
