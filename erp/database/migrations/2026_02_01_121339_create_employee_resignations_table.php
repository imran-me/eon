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
        Schema::create('employee_resignations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();

            $table->date('resign_date');
            $table->date('last_working_day')->nullable();

            $table->enum('resign_type', ['voluntary', 'terminated', 'abscond'])
                ->default('voluntary');

            $table->string('reason')->nullable()->comment('Purpose: Why employee resigned');
            $table->integer('notice_period_days')->default(0);

            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending');

            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->text('exit_note')->nullable()->comment('Purpose: HR’s official closing comments on the resignation');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_resignations');
    }
};
