<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standard ticket detail on every ticket entry (owner, 2026-10-04: "more
 * standard, like TRAACS / GDS / BSP IATA"): the fare breakdown and
 * commission BSP bills on, the validating carrier, and the flight itself.
 *
 * Every column is nullable and informational — the ledger still posts the
 * ticket's `amount` (net cost) exactly as before, so nothing that already
 * reads these tables changes. Guarded per column, so it is safe to re-run.
 * ticket_bookings already has departure_at; it gets the rest.
 */
return new class extends Migration
{
    private function columns(Blueprint $table, string $tableName): void
    {
        $add = fn (string $col, callable $def) => Schema::hasColumn($tableName, $col) ? null : $def();

        $add('base_fare', fn () => $table->decimal('base_fare', 15, 2)->nullable());
        $add('tax_amount', fn () => $table->decimal('tax_amount', 15, 2)->nullable());
        $add('tax_breakdown', fn () => $table->string('tax_breakdown', 255)->nullable());     // "YQ 12000, BD 500, E5 300"
        $add('commission_pct', fn () => $table->decimal('commission_pct', 6, 3)->nullable());
        $add('commission_amount', fn () => $table->decimal('commission_amount', 15, 2)->nullable());
        $add('validating_carrier_id', fn () => $table->unsignedBigInteger('validating_carrier_id')->nullable());   // airlines.id
        $add('flight_no', fn () => $table->string('flight_no', 40)->nullable());
        $add('departure_at', fn () => $table->dateTime('departure_at')->nullable());
        $add('arrival_at', fn () => $table->dateTime('arrival_at')->nullable());
        $add('rbd', fn () => $table->string('rbd', 4)->nullable());                           // booking class letter
        $add('baggage', fn () => $table->string('baggage', 40)->nullable());
        // Round trip: the return flight (ticket_bookings already has return_at)
        $add('return_flight_no', fn () => $table->string('return_flight_no', 40)->nullable());
        $add('return_at', fn () => $table->dateTime('return_at')->nullable());
    }

    public function up(): void
    {
        foreach (['ticket_purchases', 'ticket_bookings'] as $t) {
            if (Schema::hasTable($t)) {
                Schema::table($t, fn (Blueprint $table) => $this->columns($table, $t));
                // Trip type gains multi-city (same NOT NULL / default as before).
                \Illuminate\Support\Facades\DB::statement("ALTER TABLE `$t` MODIFY `trip_type` ENUM('one-way','two-way','multi-city') NOT NULL DEFAULT 'one-way'");
            }
        }
    }

    public function down(): void
    {
        foreach (['ticket_purchases', 'ticket_bookings'] as $t) {
            if (Schema::hasTable($t)) {
                \Illuminate\Support\Facades\DB::table($t)->where('trip_type', 'multi-city')->update(['trip_type' => 'one-way']);
                \Illuminate\Support\Facades\DB::statement("ALTER TABLE `$t` MODIFY `trip_type` ENUM('one-way','two-way') NOT NULL DEFAULT 'one-way'");
            }
        }
        $cols = ['base_fare', 'tax_amount', 'tax_breakdown', 'commission_pct', 'commission_amount', 'validating_carrier_id', 'flight_no', 'arrival_at', 'rbd', 'baggage', 'return_flight_no'];
        if (Schema::hasTable('ticket_purchases')) {
            Schema::table('ticket_purchases', function (Blueprint $table) use ($cols) {
                $drop = array_values(array_filter([...$cols, 'departure_at', 'return_at'], fn ($c) => Schema::hasColumn('ticket_purchases', $c)));
                if ($drop) $table->dropColumn($drop);
            });
        }
        if (Schema::hasTable('ticket_bookings')) {
            Schema::table('ticket_bookings', function (Blueprint $table) use ($cols) {
                $drop = array_values(array_filter($cols, fn ($c) => Schema::hasColumn('ticket_bookings', $c)));
                if ($drop) $table->dropColumn($drop);
            });
        }
    }
};
