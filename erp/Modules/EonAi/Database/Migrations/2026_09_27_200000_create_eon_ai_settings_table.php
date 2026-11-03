<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EON AI's own settings — one row. Touches no shared table.
 * The shared key is stored encrypted with the app key (Crypt), never plain.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('eon_ai_settings')) {
            return;
        }

        Schema::create('eon_ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('server_url', 255)->nullable();
            $table->text('secret')->nullable();                 // Crypt::encryptString
            $table->timestamp('key_created_at')->nullable();
            $table->boolean('enabled')->default(false);         // may EON connect at all
            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('last_test_ok')->nullable();
            $table->string('last_test_message', 500)->nullable();
            $table->unsignedInteger('last_test_ms')->nullable();
            $table->string('eon_version', 60)->nullable();      // what EON reported
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eon_ai_settings');
    }
};
