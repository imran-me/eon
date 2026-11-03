<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contract_file_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_file_id')->constrained()->restrictOnDelete();
            $table->uuid('request_token')->unique();
            $table->string('payload_hash', 64);
            $table->string('type', 30);
            $table->foreignId('service_cost_id')->nullable()->constrained('contract_file_service_costs')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('users')->restrictOnDelete();
            $table->string('bearer', 20);
            $table->decimal('client_refund', 14, 2)->default(0);
            $table->decimal('vendor_refund', 14, 2)->default(0);
            $table->decimal('penalty', 14, 2)->default(0);
            $table->decimal('extra_cost', 14, 2)->default(0);
            $table->decimal('client_delta', 14, 2)->default(0);
            $table->date('occurred_on');
            $table->json('snapshot');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['contract_file_id', 'type']);
        });
    }
    public function down(): void { Schema::dropIfExists('contract_file_changes'); }
};
