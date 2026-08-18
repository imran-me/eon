<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visa_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('country_id')->nullable()->after('name');
            $table->string('visa_type')->nullable()->after('country_id');
            $table->decimal('embassy_fee', 10, 2)->default(0)->after('visa_type');
            $table->decimal('vfs_fee', 10, 2)->default(0)->after('embassy_fee');
            $table->decimal('our_fee', 10, 2)->default(0)->after('vfs_fee');
            $table->string('avg_processing_days')->nullable()->after('our_fee');

            $table->foreign('country_id')->references('id')->on('countries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('visa_categories', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropColumn(['country_id', 'visa_type', 'embassy_fee', 'vfs_fee', 'our_fee', 'avg_processing_days']);
        });
    }
};
