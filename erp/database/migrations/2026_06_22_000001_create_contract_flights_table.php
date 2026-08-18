<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_flights', function (Blueprint $table) {
            $table->id();
            $table->string('flight_number')->unique();
            $table->foreignId('flight_category_id')->constrained('flight_categories')->cascadeOnDelete();
            $table->foreignId('airline_id')->constrained('airlines')->cascadeOnDelete();
            $table->string('airline_flight_no')->nullable();
            $table->string('route')->nullable();
            $table->dateTime('departure_at')->nullable();
            $table->unsignedInteger('total_seats')->default(0);
            $table->unsignedInteger('seats_sold')->default(0);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['open', 'boarding', 'departed', 'cancelled'])->default('open');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_flights');
    }
};
