<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('bank_charge_amount', 15, 2)->default(0)->after('bank_id');
            $table->foreignId('bank_charge_account_id')->nullable()->after('bank_charge_amount')
                ->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_charge_account_id');
            $table->dropColumn('bank_charge_amount');
        });
    }
};
