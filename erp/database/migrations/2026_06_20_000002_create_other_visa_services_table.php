<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('other_visa_services', function (Blueprint $table) {
            $table->id();
            $table->string('service_code')->unique();
            $table->foreignId('passport_holder_id')->constrained('passport_holders')->cascadeOnDelete();
            $table->foreignId('visa_process_id')->nullable()->constrained('visa_processes')->nullOnDelete();
            $table->foreignId('other_service_type_id')->constrained('other_service_types')->cascadeOnDelete();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('assigned_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('fee', 12, 2)->default(0);
            $table->date('deadline')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'done', 'overdue', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->boolean('is_billable')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('other_visa_services');
    }
};
