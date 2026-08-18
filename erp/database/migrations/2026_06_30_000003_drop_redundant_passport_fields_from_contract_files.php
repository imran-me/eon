<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_files', function (Blueprint $table) {
            $table->dropColumn([
                'applicant_name',
                'phone',
                'passport_no',
                'date_of_birth',
                'address',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('contract_files', function (Blueprint $table) {
            $table->string('applicant_name')->nullable()->after('passport_holder_id');
            $table->string('phone')->nullable()->after('applicant_name');
            $table->string('passport_no')->nullable()->after('phone');
            $table->date('date_of_birth')->nullable()->after('passport_no');
            $table->text('address')->nullable()->after('date_of_birth');
        });
    }
};
