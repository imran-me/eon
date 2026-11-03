<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employee_ledger', 'transaction_time')) {
            Schema::table('employee_ledger', function (Blueprint $table) {
                $table->time('transaction_time')->nullable()->after('entry_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employee_ledger', 'transaction_time')) {
            Schema::table('employee_ledger', function (Blueprint $table) {
                $table->dropColumn('transaction_time');
            });
        }
    }
};
