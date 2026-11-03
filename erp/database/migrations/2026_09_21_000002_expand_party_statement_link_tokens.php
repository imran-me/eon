<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('party_statement_links', function (Blueprint $table) {
            $table->string('token', 64)->change();
        });
    }

    public function down(): void
    {
        Schema::table('party_statement_links', function (Blueprint $table) {
            $table->string('token', 16)->change();
        });
    }
};
