<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_balances', function (Blueprint $table) {
            $table->date('date')->nullable()->after('portal_id');
            $table->string('reference')->nullable()->after('date');
            $table->unsignedBigInteger('journal_entry_id')->nullable()->after('reference');
            $table->unsignedBigInteger('source_account_id')->nullable()->after('journal_entry_id');
        });
    }

    public function down(): void
    {
        Schema::table('portal_balances', function (Blueprint $table) {
            $table->dropColumn(['date', 'reference', 'journal_entry_id', 'source_account_id']);
        });
    }
};
