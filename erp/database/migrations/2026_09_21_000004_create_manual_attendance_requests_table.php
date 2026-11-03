<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_attendance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('action', ['check_in', 'check_out']);
            $table->date('requested_date');
            $table->time('requested_time');
            $table->string('selfie')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->date('approved_date')->nullable();
            $table->time('approved_time')->nullable();
            $table->foreignId('attendance_log_id')->nullable()->constrained('attendance_logs')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'requested_date', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_attendance_requests');
    }
};
