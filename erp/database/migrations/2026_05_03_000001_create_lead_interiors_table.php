<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_interiors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->onDelete('cascade');
            $table->string('property_type')->default('flat'); // flat, house, office, shop, restaurant
            $table->decimal('area_sqft', 10, 2)->nullable();
            $table->string('style_preference')->nullable(); // modern, classic, minimalist, contemporary
            $table->json('room_details')->nullable(); // [{name:'bedroom', count:3, note:'...'}, ...]
            $table->decimal('budget_min', 14, 2)->nullable();
            $table->decimal('budget_max', 14, 2)->nullable();
            $table->date('site_visit_date')->nullable();
            $table->boolean('site_visit_done')->default(false);
            $table->string('measurement_file')->nullable(); // uploaded floor plan
            $table->text('design_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_interiors');
    }
};
