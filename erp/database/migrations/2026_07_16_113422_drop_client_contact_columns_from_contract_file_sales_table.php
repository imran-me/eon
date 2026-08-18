<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_file_sales', function (Blueprint $table) {
            $table->dropColumn(['client_name', 'client_phone']);
        });
    }

    public function down(): void
    {
        Schema::table('contract_file_sales', function (Blueprint $table) {
            $table->string('client_name')->nullable()->after('client_id');
            $table->string('client_phone')->nullable()->after('client_name');
        });
    }
};
