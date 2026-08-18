<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dismissing a notification from the live bulletin is not the same as reading
     * it: the row leaves the ticker but stays unread in the bell, so it needs a
     * timestamp of its own rather than reusing read_at.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->timestamp('bulletin_dismissed_at')->nullable()->after('read_at');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('bulletin_dismissed_at');
        });
    }
};
