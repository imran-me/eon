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
        if (Schema::hasColumn('users', 'device_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('device_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'device_id')) {            
            Schema::table('users', function (Blueprint $table) {
                $table->string('device_id')->nullable()->change();
            });
        }
    }
};
