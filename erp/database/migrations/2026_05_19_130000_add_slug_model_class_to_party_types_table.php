<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('party_types', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->string('model_class')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('party_types', function (Blueprint $table) {
            $table->dropColumn(['slug', 'model_class']);
        });
    }
};
