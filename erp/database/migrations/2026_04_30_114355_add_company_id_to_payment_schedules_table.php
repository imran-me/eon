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
            $table->unsignedBigInteger('company_id')->nullable()->after('id');
            $table->index('company_id');
        });

        // Backfill company_id from the related Sale or Purchase
        DB::update(
            "UPDATE payment_schedules ps JOIN sales s ON ps.schedulable_type = ? AND ps.schedulable_id = s.id SET ps.company_id = s.company_id",
            [\App\Models\Sale::class]
        );

        DB::update(
            "UPDATE payment_schedules ps JOIN purchases p ON ps.schedulable_type = ? AND ps.schedulable_id = p.id SET ps.company_id = p.company_id",
            [\App\Models\Purchase::class]
        );
    }

    public function down(): void
    {
        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
