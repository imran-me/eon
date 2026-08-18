<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->enum('source', ['machine', 'manual'])->default('machine')->after('status');
            $table->string('selfie')->nullable()->after('source');
            $table->timestamp('submitted_at')->nullable()->after('selfie');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['source', 'selfie', 'submitted_at']);
        });
    }
};
