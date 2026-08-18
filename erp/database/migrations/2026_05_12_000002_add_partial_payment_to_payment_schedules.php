<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->decimal('paid_amount', 15, 2)->nullable()->after('amount');
        });

        DB::statement("ALTER TABLE payment_schedule_logs MODIFY COLUMN action ENUM('rescheduled','approved','rejected','paid','cancelled','partial_paid')");
    }

    public function down(): void
    {
        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });

        DB::statement("ALTER TABLE payment_schedule_logs MODIFY COLUMN action ENUM('rescheduled','approved','rejected','paid','cancelled')");
    }
};
