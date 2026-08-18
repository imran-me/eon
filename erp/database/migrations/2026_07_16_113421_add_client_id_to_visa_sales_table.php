<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visa_sales', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('bundle_label')->constrained('users')->nullOnDelete();
        });

        Schema::table('visa_sales', function (Blueprint $table) {
            $table->dropColumn(['client_name', 'client_phone', 'client_email']);
        });
    }

    public function down(): void
    {
        Schema::table('visa_sales', function (Blueprint $table) {
            $table->string('client_name')->nullable()->after('invoice_number');
            $table->string('client_phone')->nullable()->after('client_name');
            $table->string('client_email')->nullable()->after('client_phone');
        });

        Schema::table('visa_sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });
    }
};
