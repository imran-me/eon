<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who has looked at which task, and when.
 *
 * The board's corner ribbon ("New" when an admin created the task, "Comment"
 * when an admin commented) has to clear per person: it disappears from MY board
 * once I open the task, and keeps showing for everyone who has not. That is a
 * per-user fact, so it cannot live on the task row.
 *
 * One row per (task, user) — rewritten on each open rather than appended to,
 * because only the latest look matters: the ribbon shows when the admin's
 * action is NEWER than this stamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_seen', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('seen_at');

            // The upsert on open depends on this pair being unique.
            $table->unique(['task_id', 'user_id']);
            // The board reads "everything this one person has seen" in one go.
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_seen');
    }
};
