<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * eon_snapshots — the engine's reading of one person, frozen.
 *
 * WHY THIS TABLE EXISTS. EON computes live, which is right for a dashboard and
 * wrong for a review: the numbers behind a judgement written in August will not
 * reproduce in December, because the tasks will have moved, been reassigned or
 * been deleted. A review whose evidence has silently changed underneath it is
 * worse than no review at all — so the moment somebody writes one, the full
 * assessment is stored beside it exactly as it read that day.
 *
 * The payload is the whole Assessment::toSnapshot() document rather than a
 * column per metric, on purpose: EON's metrics will change as analyzers are
 * added, and a schema that had to migrate every time would either freeze the
 * engine or lose old readings. What is queryable — score, band, period — is
 * lifted into real columns; the rest is history, and history is read whole.
 *
 * Nothing outside EON reads this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('eon_snapshots')) {
            return;
        }

        Schema::create('eon_snapshots', function (Blueprint $table) {
            $table->id();

            // No foreign key, deliberately. Deleting a user must never be
            // blocked by, or silently erase, the evaluation history that was
            // written about them — that record outlives the account.
            $table->unsignedBigInteger('user_id')->index();

            // 'YYYY-MM' — the month the reading covers.
            $table->string('period', 7)->index();

            $table->decimal('score', 5, 1)->nullable();
            $table->string('band', 20)->nullable();
            $table->string('confidence', 10)->nullable();

            // The full Assessment document as it read at capture time.
            $table->json('payload')->nullable();

            $table->unsignedBigInteger('taken_by')->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eon_snapshots');
    }
};
