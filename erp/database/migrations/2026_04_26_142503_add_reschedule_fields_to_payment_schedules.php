<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->date('original_scheduled_date')->nullable()->after('scheduled_date');
            $table->unsignedTinyInteger('reschedule_count')->default(0)->after('original_scheduled_date');
            $table->string('reschedule_reason')->nullable()->after('reschedule_count');
        });
    }

    public function down(): void
    {
        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->dropColumn(['original_scheduled_date', 'reschedule_count', 'reschedule_reason']);
        });
    }
};
