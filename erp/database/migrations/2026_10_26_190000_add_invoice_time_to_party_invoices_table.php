<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('party_invoices', function (Blueprint $table) {
            $table->time('invoice_time')->nullable()->after('invoice_date');
        });
    }

    public function down(): void
    {
        Schema::table('party_invoices', function (Blueprint $table) {
            $table->dropColumn('invoice_time');
        });
    }
};
