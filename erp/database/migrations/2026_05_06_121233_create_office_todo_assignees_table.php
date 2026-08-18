<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_todo_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_todo_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['office_todo_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_todo_assignees');
    }
};
