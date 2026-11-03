<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('from_airport_id')->nullable()->change();
            $table->unsignedBigInteger('to_airport_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('tickets')->whereNull('from_airport_id')->orWhereNull('to_airport_id')->exists()) {
            throw new RuntimeException('Complete missing route airports before rolling back this migration.');
        }
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('from_airport_id')->nullable(false)->change();
            $table->unsignedBigInteger('to_airport_id')->nullable(false)->change();
        });
    }
};
