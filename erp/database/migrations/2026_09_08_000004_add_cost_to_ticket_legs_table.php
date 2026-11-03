<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_legs', function (Blueprint $table) {
            $table->decimal('cost', 12, 2)->nullable()->after('seat_number');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_legs', function (Blueprint $table) {
            $table->dropColumn('cost');
        });
    }
};
