<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('require_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('employee_requests')->onDelete('cascade');
            $table->foreignId('assigned_to')->constrained('users')->onDelete('cascade');
            $table->foreignId('assigned_by')->constrained('users')->onDelete('cascade');
            $table->date('due_date');
            $table->timestamp('reminder_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->boolean('escalated')->default(false);
            $table->text('fulfillment_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('require_assignments');
    }
};
