<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('banks', 'account_id')) {
            Schema::table('banks', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->nullable();
                $table->foreign('account_id')->references('id')->on('accounts');
            });
        }
        if (!Schema::hasColumn('portals', 'account_id')) {
            Schema::table('portals', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->nullable();
                $table->foreign('account_id')->references('id')->on('accounts');
            });
        }
        if (!Schema::hasColumn('loans', 'bank_id')) {
            Schema::table('loans', function (Blueprint $table) {
                $table->unsignedBigInteger('bank_id')->nullable();
                $table->foreign('bank_id')->references('id')->on('accounts');
            });
        }                
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('banks', 'account_id')) {
            Schema::table('banks', function (Blueprint $table) {
                $table->dropForeign(['account_id']);
                $table->dropColumn('account_id');
            });
        }
        if (Schema::hasColumn('portals', 'account_id')) {
            Schema::table('portals', function (Blueprint $table) {
                $table->dropForeign(['account_id']);
                $table->dropColumn('account_id');
            });
        }
        if (Schema::hasColumn('loans', 'bank_id')) {
            Schema::table('loans', function (Blueprint $table) {
                $table->dropForeign(['bank_id']);
                $table->dropColumn('bank_id');
            });
        }
    }
};
