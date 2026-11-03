<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers where a work item was standing before ✓ marked it done.
 *
 * The ✓ button now moves the item into the board's Done column, so the tick and
 * the state dropdown always agree. Un-ticking has to put it back where it came
 * from — "Review" must not silently become "In Progress" — and that origin has
 * nowhere else to live.
 *
 * Nullable with no default and no foreign key: an item ticked before this column
 * existed, or one whose old column has since been deleted, simply falls back to
 * the board's first open column instead of erroring.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('pre_complete_column_id')->nullable()->after('column_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('pre_complete_column_id');
        });
    }
};
