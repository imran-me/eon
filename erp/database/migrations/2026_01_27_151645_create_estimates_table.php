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
        Schema::create('estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->string('estimate_no')->unique();
            $table->date('estimate_date');
            $table->date('valid_until')->nullable();
            $table->enum('status', ['draft','pending', 'sent', 'approved', 'rejected'])->default('pending');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->longText('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['status', 'estimate_date', 'valid_until', 'deal_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estimates');
    }
};
