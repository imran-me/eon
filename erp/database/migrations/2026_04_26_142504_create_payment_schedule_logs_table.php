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
        Schema::create('payment_schedule_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_schedule_id')->constrained('payment_schedules')->cascadeOnDelete();
            $table->enum('action', ['rescheduled', 'approved', 'rejected', 'paid', 'cancelled']);
            $table->date('old_date')->nullable();
            $table->date('new_date')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('done_by')->constrained('users');
            $table->timestamps();

            $table->index('payment_schedule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_schedule_logs');
    }
};
