<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->time('transaction_time')->nullable()->after('payment_date');
        });

        Schema::create('transaction_date_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->date('old_date')->nullable();
            $table->time('old_time')->nullable();
            $table->date('new_date');
            $table->time('new_time');
            $table->string('note', 500);
            $table->foreignId('edited_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_date_edits');

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('transaction_time');
        });
    }
};
