<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket orders per TICKET, not per route (owner, 2026-09-21).
 *
 * One entry screen; each ticket of a passenger (a via route = two tickets)
 * carries its own status — Purchased writes a ticket_purchases row and hits
 * the ledger; Draft / Pending / Booked write a ticket_bookings row and do not.
 * Nothing is mandatory at entry, so a booking may exist before its route,
 * seller, quote or deadline are known. Sibling tickets of one entry share an
 * order_group and know their leg_sequence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->foreignId('ticket_id')->nullable()->change();
            $table->dateTime('booking_at')->nullable()->change();
            $table->dateTime('expires_at')->nullable()->change();
            $table->string('order_group', 40)->nullable()->index()->after('booking_reference');
            $table->unsignedTinyInteger('leg_sequence')->nullable()->after('order_group');
            $table->foreignId('from_airport_id')->nullable()->after('leg_sequence')->constrained('airports')->nullOnDelete();
            $table->foreignId('to_airport_id')->nullable()->after('from_airport_id')->constrained('airports')->nullOnDelete();
        });
        DB::statement("ALTER TABLE ticket_bookings MODIFY status ENUM('draft','pending','booked','confirmed','expired','cancelled') NOT NULL DEFAULT 'booked'");

        Schema::table('ticket_purchases', function (Blueprint $table) {
            $table->string('order_group', 40)->nullable()->index()->after('ticket_no');
            $table->unsignedTinyInteger('leg_sequence')->nullable()->after('order_group');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_purchases', function (Blueprint $table) {
            $table->dropColumn(['order_group', 'leg_sequence']);
        });
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('from_airport_id');
            $table->dropConstrainedForeignId('to_airport_id');
            $table->dropColumn(['order_group', 'leg_sequence']);
        });
        DB::statement("ALTER TABLE ticket_bookings MODIFY status ENUM('booked','confirmed','expired','cancelled') NOT NULL DEFAULT 'booked'");
    }
};
