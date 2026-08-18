<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visa_required_documents', function (Blueprint $table) {
            $table->id();
            $table->enum('visa_type', ['tourist', 'business', 'student', 'work', 'medical', 'other']);
            $table->string('document_name');
            $table->boolean('is_mandatory')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_required_documents');
    }
};
