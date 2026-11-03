<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-item time tracking for sub-work items (Start / Pause / Done).
 *
 * The columns go on `tasks` rather than a new table because a sub-work item IS
 * a task row (`parent_id`) — giving it its own table would mean a second place
 * to keep in sync on every create, copy and delete.
 *
 * Only three fields are needed; the fourth already exists:
 *   timer_accum_ms          banked milliseconds from every completed run
 *   timer_started_at        set while the clock is running, NULL when it is not
 *                           (this IS the "running" flag — one source of truth,
 *                           so a boolean can never disagree with a timestamp)
 *   timer_first_started_at  the left side of "3.59pm:24/08/2026 → …"
 *   completed_at            (already on the table) the right side, and the same
 *                           field the sub-work-item progress ring already counts
 *
 * Purely additive with defaults, so every existing row reads as "not started"
 * and nothing that runs today changes behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('timer_accum_ms')->default(0)->after('completed_at');
            $table->timestamp('timer_started_at')->nullable()->after('timer_accum_ms');
            $table->timestamp('timer_first_started_at')->nullable()->after('timer_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['timer_accum_ms', 'timer_started_at', 'timer_first_started_at']);
        });
    }
};
