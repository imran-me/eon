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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('contract_type_id')->nullable();
            $table->string('contract_no')->unique();
            $table->date('contract_date');
            $table->date('valid_until')->nullable();
            $table->decimal('contract_value', 10, 2)->default(0);
            $table->enum('status', ['draft', 'signed', 'expired'])->default('draft');
            $table->longText('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['status', 'contract_date', 'valid_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
