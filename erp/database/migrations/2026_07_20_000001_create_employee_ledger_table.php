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
        Schema::create('employee_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('type');
            $table->nullableMorphs('source');
            $table->date('entry_date');
            $table->string('reference')->nullable();
            $table->decimal('old_balance', 16, 2)->default(0);
            $table->decimal('debit', 16, 2)->default(0)->comment('increases what the company owes the employee');
            $table->decimal('credit', 16, 2)->default(0)->comment('decreases what the company owes the employee (paid out / recovered)');
            $table->decimal('balance', 16, 2)->default(0)->comment('running balance after this row');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'entry_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_ledger');
    }
};
