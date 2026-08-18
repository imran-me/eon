<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visa_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visa_sale_id')->constrained('visa_sales')->cascadeOnDelete();
            $table->foreignId('visa_process_id')->constrained('visa_processes')->cascadeOnDelete();
            $table->decimal('sale_price', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_sale_items');
    }
};
