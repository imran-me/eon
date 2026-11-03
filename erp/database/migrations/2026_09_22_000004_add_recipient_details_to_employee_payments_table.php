<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employee_payments', 'recipient_bank')) {
            Schema::table('employee_payments', fn (Blueprint $table) => $table->string('recipient_bank', 150)->nullable()->after('payment_method'));
        }
        if (! Schema::hasColumn('employee_payments', 'recipient_account')) {
            Schema::table('employee_payments', fn (Blueprint $table) => $table->string('recipient_account', 100)->nullable()->after('recipient_bank'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employee_payments', 'recipient_account')) {
            Schema::table('employee_payments', fn (Blueprint $table) => $table->dropColumn('recipient_account'));
        }
        if (Schema::hasColumn('employee_payments', 'recipient_bank')) {
            Schema::table('employee_payments', fn (Blueprint $table) => $table->dropColumn('recipient_bank'));
        }
    }
};
