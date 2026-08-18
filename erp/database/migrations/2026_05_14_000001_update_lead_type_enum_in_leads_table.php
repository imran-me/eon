<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->enum('lead_type', ['air_ticket', 'visa', 'software', 'interior', 'other'])
                  ->default('other')
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->enum('lead_type', ['air_ticket', 'visa', 'software', 'other'])
                  ->default('other')
                  ->change();
        });
    }
};
