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
        Schema::table('advance_salaries', function (Blueprint $table) {
            $table->date('schedule_date')->nullable()->after('month');
        });
    }

    public function down(): void
    {
        Schema::table('advance_salaries', function (Blueprint $table) {
            $table->dropColumn('schedule_date');
        });
    }
};
