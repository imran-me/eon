<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_files', function (Blueprint $table) {
            $table->decimal('due_amount', 15, 2)->nullable()->default(0)->after('visa_rate');
            $table->date('payable_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contract_files', function (Blueprint $table) {
            $table->dropColumn(['due_amount', 'payable_date']);
        });
    }
};
