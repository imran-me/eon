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
        Schema::create('project_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_department_id')->constrained()->onDelete('cascade');
            $table->string('label');
            $table->enum('type', ['text', 'number', 'date', 'select', 'textarea'])->default('text');
            $table->text('options')->nullable(); // comma-separated for select type
            $table->boolean('required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_field_definitions');
    }
};
