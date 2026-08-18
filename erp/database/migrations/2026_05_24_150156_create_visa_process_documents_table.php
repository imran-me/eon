<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visa_process_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visa_process_id')->constrained('visa_processes')->cascadeOnDelete();
            $table->string('document_name');
            $table->boolean('is_mandatory')->default(true);
            $table->enum('status', ['pending', 'received', 'not_required'])->default('pending');
            $table->timestamp('received_at')->nullable();
            $table->string('notes')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['visa_process_id', 'document_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_process_documents');
    }
};
