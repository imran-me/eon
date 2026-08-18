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
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'branch_id') && ! Schema::hasColumn('sales', 'company_id')) {
                $table->renameColumn('branch_id', 'company_id');
            }
        });

        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'branch_id') && ! Schema::hasColumn('purchases', 'company_id')) {
                $table->renameColumn('branch_id', 'company_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'company_id') && ! Schema::hasColumn('sales', 'branch_id')) {
                $table->renameColumn('company_id', 'branch_id');
            }
        });

        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'company_id') && ! Schema::hasColumn('purchases', 'branch_id')) {
                $table->renameColumn('company_id', 'branch_id');
            }
        });
    }
};
