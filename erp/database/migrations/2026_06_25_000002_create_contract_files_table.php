<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_files', function (Blueprint $table) {
            $table->id();
            $table->string('file_number')->unique();
            $table->string('applicant_name');
            $table->string('phone');
            $table->string('passport_no');
            $table->date('date_of_birth')->nullable();
            $table->text('address')->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('contract_file_category_id')->nullable()->constrained('contract_file_categories')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('visa_rate', 12, 2)->default(0);
            $table->date('submit_date');
            $table->date('expected_out_date')->nullable();
            $table->enum('status', ['doc_collection', 'submitted_to_vendor', 'under_process', 'approved', 'delivered', 'rejected'])->default('doc_collection');
            $table->json('required_documents')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_files');
    }
};
