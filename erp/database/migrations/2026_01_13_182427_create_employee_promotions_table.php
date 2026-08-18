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
        Schema::create('employee_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->foreignId('previous_designation_id')->constrained('designations');
            $table->foreignId('new_designation_id')->constrained('designations');

            $table->date('approved_at')->nullable();
            $table->date('effective_from')->nullable();

            $table->decimal('previous_salary', 15, 2)->nullable()->default(0);
            $table->decimal('new_salary', 15, 2)->nullable()->default(0);

            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_promotions');
    }
};
