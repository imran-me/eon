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
        Schema::create('attendence_settings', function (Blueprint $table) {
            $table->id();
            $table->string('time_before_checkin')->nullable();
            $table->string('time_after_checkin')->nullable();
            $table->string('time_before_checkout')->nullable();
            $table->string('time_after_checkout')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendence_settings');
    }
};
