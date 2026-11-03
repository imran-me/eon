<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Name Ticket No. (owner, 2026-09-30): the airline's per-passenger ticket
 * number, entered beside the PNR. One PNR covers a whole group; each name on
 * it has its own ticket number. Optional everywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['ticket_purchases' => 'ticket_no', 'ticket_bookings' => 'pnr'] as $table => $after) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'name_ticket_no')) {
                Schema::table($table, function (Blueprint $t) use ($after) {
                    $t->string('name_ticket_no', 100)->nullable()->after($after);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['ticket_purchases', 'ticket_bookings'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'name_ticket_no')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('name_ticket_no'));
            }
        }
    }
};
