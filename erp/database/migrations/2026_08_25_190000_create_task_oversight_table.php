<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A super admin's verdict on one task: locked, flagged, rated.
 *
 * Deliberately its own table rather than columns on `tasks`.
 *
 * `tasks` is the busiest table in the ERP — every company's board, drawer,
 * report and timer reads it, and it already carries 6,000 rows and twenty-odd
 * columns. Bolting nine oversight columns onto it would mean an ALTER on a hot
 * shared table for a feature only the super admin can use, and every future
 * `SELECT *` on the board would drag the extra bytes along for cards that will
 * never be flagged. Here the rows exist only for tasks somebody actually acted
 * on, and the board joins them in one query.
 *
 * One row per task — `task_id` is unique and the write path upserts on it.
 * Everything is nullable and defaults to "no verdict", so a task with no row
 * behaves exactly as it did before this table existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_oversight', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');

            // The lock. One switch, blocking edit AND delete for anyone who is
            // not a super admin — the employee sees it and cannot lift it.
            $table->boolean('is_restricted')->default(false);
            $table->string('restriction_note', 500)->nullable();
            $table->unsignedBigInteger('restricted_by')->nullable();
            $table->timestamp('restricted_at')->nullable();

            // The flag. Attention, not permission: it changes nothing about
            // what the employee may do, it just makes the card impossible
            // to miss.
            $table->boolean('red_flag')->default(false);
            $table->string('red_flag_note', 500)->nullable();
            $table->unsignedBigInteger('red_flagged_by')->nullable();
            $table->timestamp('red_flagged_at')->nullable();

            // The rating: 1-5 stars and an optional remark.
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('rating_note')->nullable();
            $table->unsignedBigInteger('rated_by')->nullable();
            $table->timestamp('rated_at')->nullable();

            $table->timestamps();

            // The upsert from the drawer depends on this being unique.
            $table->unique('task_id');
            // "Show me everything flagged / everything locked" across a board.
            $table->index('red_flag');
            $table->index('is_restricted');
            // Per-employee scorecards read every rating a person's tasks hold.
            $table->index('rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_oversight');
    }
};
