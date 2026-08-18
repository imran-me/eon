<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            $table->string('icon', 50)->nullable()->default('📢')->after('status');
            $table->string('card_color', 100)->nullable()->default('#f97316,#f59e0b')->after('icon');
            $table->string('text_color', 7)->nullable()->default('#ffffff')->after('card_color');
        });
    }

    public function down(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            $table->dropColumn(['icon', 'card_color', 'text_color']);
        });
    }
};
