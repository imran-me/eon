<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flight_price_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('airline_id')->constrained('airlines')->cascadeOnDelete();
            $table->foreignId('flight_category_id')->nullable()->constrained('flight_categories')->nullOnDelete();
            $table->foreignId('flight_category_type_id')->nullable()->constrained('flight_category_types')->nullOnDelete();
            $table->string('ticket_class', 50)->default('economy');
            $table->enum('handling_type', ['manpower_wise', 'immigration_wise'])->default('manpower_wise');
            $table->decimal('ticket_cost', 12, 2)->default(0);
            $table->decimal('manpower_cost', 12, 2)->default(0);
            $table->decimal('boarding_cost', 12, 2)->default(0);
            $table->decimal('immigration_cost', 12, 2)->default(0);
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flight_price_presets');
    }
};
