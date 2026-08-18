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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('companies')->cascadeOnDelete()->comment('Workspace id associated with the company');
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('column_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('position');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('priority', ['low', 'medium', 'high'])->nullable();
            $table->string('label')->nullable();
            $table->string('label_color')->nullable();
            $table->timestamp('start_date')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->nullable()->comment('User assigned to the task');
            $table->softDeletes();
            $table->timestamps();
            $table->index(['workspace_id', 'project_id', 'board_id'], 'tasks_workspace_idx');
            $table->index(['assigned_to', 'priority'], 'tasks_assigned_idx');
            $table->index('title', 'tasks_title_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
