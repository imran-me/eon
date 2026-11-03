<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_tracker_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_tracker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Why this person was pulled in. Kept on the pivot rather than in the
            // history table so it stays readable next to the name it explains.
            $table->text('note')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['operation_tracker_id', 'user_id'], 'operation_tracker_assignee_unique');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_tracker_assignees');
    }
};
