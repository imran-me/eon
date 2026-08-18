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
        Schema::create('visa_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visa_process_id')->constrained()->onDelete('cascade');
            $table->string('file_path');
            $table->string('file_name'); // ফাইলের অরিজিনাল নাম সেভ রাখার জন্য
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visa_attachments');
    }
};
