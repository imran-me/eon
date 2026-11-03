<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_file_submissions', function (Blueprint $table) {
            $table->id();
            // One confirmation token per multi-applicant save, never a second invoice.
            $table->uuid('request_token')->unique();
            $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('payload_hash', 64);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('contract_files', function (Blueprint $table) {
            $table->foreignId('contract_file_submission_id')->nullable()
                ->constrained('contract_file_submissions')->restrictOnDelete();
        });

        Schema::create('contract_file_service_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_file_id')->constrained()->restrictOnDelete();
            // File-only service records: no contract-flight foreign keys.
            $table->string('cost_type', 40);
            $table->string('title', 160);
            $table->foreignId('vendor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('payment_status', 20)->default('due');
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->foreignId('bank_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('incurred_on')->nullable();
            $table->date('payment_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('reference_no', 160)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->index(['contract_file_id', 'cost_type']);
            $table->index(['vendor_id', 'due_date']);
        });

        Schema::create('contract_file_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_file_id')->constrained()->restrictOnDelete();
            $table->foreignId('passport_holder_id')->constrained()->restrictOnDelete();
            $table->string('document_type', 80);
            $table->string('title', 160);
            // Private disk paths only; access must be authorized by the download endpoint.
            $table->string('disk', 40)->default('local');
            $table->string('path', 500);
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64)->nullable();
            $table->string('status', 30)->default('received');
            // Reuse/version links preserve the original record and its passport ownership.
            $table->foreignId('linked_document_id')->nullable()
                ->constrained('contract_file_documents')->restrictOnDelete();
            $table->foreignId('supersedes_document_id')->nullable()
                ->constrained('contract_file_documents')->restrictOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['contract_file_id', 'status']);
            $table->index(['passport_holder_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_file_documents');
        Schema::dropIfExists('contract_file_service_costs');
        Schema::table('contract_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contract_file_submission_id');
        });
        Schema::dropIfExists('contract_file_submissions');
    }
};
