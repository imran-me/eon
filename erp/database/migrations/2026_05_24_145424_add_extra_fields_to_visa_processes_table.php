<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visa_processes', function (Blueprint $table) {
            $table->string('application_id')->nullable()->unique()->after('id');
            $table->string('visa_type')->nullable()->after('visa_category_id');
            $table->string('duration')->nullable()->after('visa_type');
            $table->date('travel_date')->nullable()->after('duration');
            $table->decimal('embassy_fee', 10, 2)->default(0)->after('travel_date');
            $table->decimal('vfs_fee', 10, 2)->default(0)->after('embassy_fee');
            $table->decimal('our_service_fee', 10, 2)->default(0)->after('vfs_fee');
            $table->decimal('advance_received', 10, 2)->default(0)->after('our_service_fee');
            $table->unsignedBigInteger('assigned_officer_id')->nullable()->after('advance_received');

            $table->foreign('assigned_officer_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('visa_processes', function (Blueprint $table) {
            $table->dropForeign(['assigned_officer_id']);
            $table->dropColumn([
                'application_id', 'visa_type', 'duration', 'travel_date',
                'embassy_fee', 'vfs_fee', 'our_service_fee', 'advance_received', 'assigned_officer_id',
            ]);
        });
    }
};
