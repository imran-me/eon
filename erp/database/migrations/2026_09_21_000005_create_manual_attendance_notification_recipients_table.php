<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_attendance_notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'recipient_id'], 'manual_attendance_employee_recipient_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_attendance_notification_recipients');
    }
};
