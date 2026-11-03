<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * eon_reviews — the human verdict for one person, for one month.
 *
 * The distinction this table exists to preserve: EON's score is an observation
 * and this is a JUDGEMENT. One is computed and can be recomputed; the other was
 * made by a named person on a date and must never change afterwards without
 * that being visible. They are stored apart for the same reason a thermometer
 * reading and a doctor's note are written on different lines.
 *
 * One review per person per month (unique on user_id + period). Re-saving the
 * same month updates it and re-stamps the reviewer, which is the behaviour the
 * oversight screen already has for ratings — an edit is a new judgement, not a
 * silent revision of the old one.
 *
 * The machine_* columns are copies taken at write time. They are not a cache:
 * they record what EON was saying when the human disagreed or agreed with it,
 * which is the only way to ever ask whether the engine is any good.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('eon_reviews')) {
            return;
        }

        Schema::create('eon_reviews', function (Blueprint $table) {
            $table->id();

            // No foreign keys — see the note in the snapshots migration. An
            // evaluation record outlives the account it describes.
            $table->unsignedBigInteger('user_id')->index();
            $table->string('period', 7)->index();

            // The authority's own rating, on the same 1-5 scale as the per-task
            // stars in Employee Task Oversight, so the two can be read together.
            $table->unsignedTinyInteger('overall_rating')->nullable();

            // commend | satisfactory | improve | escalate
            $table->string('verdict', 20)->nullable();

            $table->text('summary')->nullable();     // what the reviewer saw
            $table->text('actions')->nullable();     // what happens next

            // What EON was saying at the moment the review was written.
            $table->decimal('machine_score', 5, 1)->nullable();
            $table->string('machine_band', 20)->nullable();
            $table->string('machine_confidence', 10)->nullable();

            $table->unsignedBigInteger('snapshot_id')->nullable()->index();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'period'], 'eon_reviews_user_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eon_reviews');
    }
};
