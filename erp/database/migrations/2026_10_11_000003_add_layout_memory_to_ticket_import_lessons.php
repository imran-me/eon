<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket PDF import — layout memory and trust (see App\Services\TicketImport\Lessons).
 *
 *  - ticket_import_layouts: one row per ticket design seen, recognised by the
 *    set of labels it prints (labels only — never anyone's data);
 *  - ticket_import_lessons.layout_id: a lesson learned on that design
 *    (0 = applies to every design);
 *  - ticket_import_lessons.misses: times the rule's value was corrected by
 *    staff — a rule wrong more often than right stops being used.
 *
 * Touches only the import's own two tables. Without it, the import still
 * reads and learns labels exactly as before (Lessons::smart()).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticket_import_layouts')) {
            Schema::create('ticket_import_layouts', function (Blueprint $table) {
                $table->id();
                $table->string('signature', 32)->index();       // md5 of the label set when first seen
                $table->text('labels');                         // JSON: the labels the design prints
                $table->string('name', 80)->nullable();         // the layout reader / airline, when known
                $table->unsignedInteger('uses')->default(1);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }
        if (Schema::hasTable('ticket_import_lessons') && ! Schema::hasColumn('ticket_import_lessons', 'misses')) {
            Schema::table('ticket_import_lessons', function (Blueprint $table) {
                $table->unsignedBigInteger('layout_id')->default(0)->after('id');
                $table->unsignedInteger('misses')->default(0)->after('hits');
            });
            Schema::table('ticket_import_lessons', function (Blueprint $table) {
                $table->dropUnique(['field', 'anchor', 'position']);
                $table->unique(['layout_id', 'field', 'anchor', 'position']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ticket_import_lessons') && Schema::hasColumn('ticket_import_lessons', 'misses')) {
            // a lesson per layout cannot fold back into one per label: keep the every-layout ones
            DB::table('ticket_import_lessons')->where('layout_id', '!=', 0)->delete();
            Schema::table('ticket_import_lessons', function (Blueprint $table) {
                $table->dropUnique(['layout_id', 'field', 'anchor', 'position']);
                $table->unique(['field', 'anchor', 'position']);
            });
            Schema::table('ticket_import_lessons', function (Blueprint $table) {
                $table->dropColumn(['layout_id', 'misses']);
            });
        }
        Schema::dropIfExists('ticket_import_layouts');
    }
};
