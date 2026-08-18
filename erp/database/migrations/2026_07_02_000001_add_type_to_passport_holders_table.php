<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passport_holders', function (Blueprint $table) {
            $table->string('type', 30)->default('general')->after('category_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('passport_holders', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
