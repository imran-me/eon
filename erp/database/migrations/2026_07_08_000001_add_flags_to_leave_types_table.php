<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->boolean('requires_time')->default(false)->after('max_leaves_count');
            $table->boolean('exempts_early_out_deduction')->default(false)->after('requires_time');
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn(['requires_time', 'exempts_early_out_deduction']);
        });
    }
};
