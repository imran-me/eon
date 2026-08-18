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
        if (!Schema::hasColumn('accounts', 'company_id')) {
            Schema::table('accounts', function (Blueprint $table) {
                // Nullable: NULL means the account is shared/global across all
                // companies (the existing convention for expense/income/equity
                // categories); a set value means the account belongs to that
                // one company (used for bank sub-accounts).
                $table->unsignedBigInteger('company_id')->nullable()->after('parent_id');
                $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('accounts', 'company_id')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }
    }
};
