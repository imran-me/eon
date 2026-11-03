<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transfers', function (Blueprint $table) {
            $table->decimal('charge_amount', 15, 2)->default(0)->after('remarks');
            $table->foreignId('charge_account_id')->nullable()->after('charge_amount')->constrained('accounts')->nullOnDelete();
            $table->decimal('vat_amount', 15, 2)->default(0)->after('charge_account_id');
            $table->foreignId('vat_account_id')->nullable()->after('vat_amount')->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vat_account_id');
            $table->dropColumn('vat_amount');
            $table->dropConstrainedForeignId('charge_account_id');
            $table->dropColumn('charge_amount');
        });
    }
};
