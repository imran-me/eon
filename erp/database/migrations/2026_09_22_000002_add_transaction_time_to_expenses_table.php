<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->time('transaction_time')->nullable()->after('expense_date');
            $table->string('reference_user_name')->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', fn (Blueprint $table) => $table->dropColumn(['transaction_time', 'reference_user_name']));
    }
};
