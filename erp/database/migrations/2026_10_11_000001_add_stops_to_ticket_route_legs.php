<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Several via airports on one route (owner, 2026-10-11): DAC → DOH → ESB → ECN, where each
// stop is either a TRANSIT inside a ticket or the place a NEW ticket starts. A route leg is
// still one ticket (from → to, own airline / seller / price); `stops` lists the transit
// airports inside it, in order — DAC → ESB with stops [DOH] = "DAC → DOH → ESB" on one ticket.
// Nullable: every existing leg reads as having no transit stop.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticket_route_legs') || Schema::hasColumn('ticket_route_legs', 'stops')) {
            return;
        }
        Schema::table('ticket_route_legs', function (Blueprint $table) {
            $table->json('stops')->nullable()->after('to_airport_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('ticket_route_legs') && Schema::hasColumn('ticket_route_legs', 'stops')) {
            Schema::table('ticket_route_legs', function (Blueprint $table) {
                $table->dropColumn('stops');
            });
        }
    }
};
