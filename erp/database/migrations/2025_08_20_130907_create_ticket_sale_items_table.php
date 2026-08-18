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
        Schema::create('ticket_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_sale_id')->constrained('ticket_sales')->onDelete('cascade');
            $table->foreignId('ticket_purchase_id')->constrained('ticket_purchases')->onDelete('cascade');
            $table->decimal('price', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_sale_items');
    }
};
