<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financing_capital_movements', function (Blueprint $table) {
            $table->time('transaction_time')->nullable()->after('date');
            $table->string('counterparty_method', 10)->nullable()->after('bank_id');
            $table->string('counterparty_bank', 150)->nullable()->after('counterparty_method');
            $table->string('counterparty_ref', 100)->nullable()->after('counterparty_bank');
        });
    }

    public function down(): void
    {
        Schema::table('financing_capital_movements', function (Blueprint $table) {
            $table->dropColumn([
                'transaction_time',
                'counterparty_method',
                'counterparty_bank',
                'counterparty_ref',
            ]);
        });
    }
};
