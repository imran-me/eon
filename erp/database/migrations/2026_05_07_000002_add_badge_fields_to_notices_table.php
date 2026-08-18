<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            $table->string('badge_icon', 60)->nullable()->default('fas fa-bell')->after('text_color');
            $table->string('badge_label', 40)->nullable()->default('REMINDER')->after('badge_icon');
        });
    }

    public function down(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            $table->dropColumn(['badge_icon', 'badge_label']);
        });
    }
};
