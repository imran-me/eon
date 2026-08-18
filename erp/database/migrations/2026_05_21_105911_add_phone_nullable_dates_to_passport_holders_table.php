<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passport_holders', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('nationality');
            $table->date('date_of_birth')->nullable()->change();
            $table->date('issue_date')->nullable()->change();
            $table->date('expiry_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('passport_holders', function (Blueprint $table) {
            $table->dropColumn('phone');
            $table->date('date_of_birth')->nullable(false)->change();
            $table->date('issue_date')->nullable(false)->change();
            $table->date('expiry_date')->nullable(false)->change();
        });
    }
};
