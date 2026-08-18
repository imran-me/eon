<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_todo_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_todo_id')->constrained('office_todos')->onDelete('cascade');
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_checked')->default(false);
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_todo_checklists');
    }
};
