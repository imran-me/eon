<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Travel dates carry an optional time (owner, 2026-09-30). DATE → DATETIME;
 * every existing value becomes that day at 00:00:00, which the date+time
 * picker shows as the date alone, so nothing already entered changes.
 * Operational dates only — accounting dates stay DATE by the owner's choice.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'ticket_legs'     => 'travel_date',
        'ticket_reissues' => 'new_travel_date',
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $t->dateTime($column)->nullable()->change());
            }
        }
    }

    public function down(): void
    {
        // Drops any time entered since — the day itself survives.
        foreach (self::COLUMNS as $table => $column) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $t->date($column)->nullable()->change());
            }
        }
    }
};
