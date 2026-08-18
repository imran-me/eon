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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('title')->comment('ex- Dhaka to Malaysia');
            $table->foreignId('vendor_id')->nullable()->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('portal_id')->nullable()->references('id')->on('portals')->cascadeOnDelete();
            $table->foreignId('from_airport_id')->references('id')->on('airports')->cascadeOnDelete();
            $table->foreignId('to_airport_id')->references('id')->on('airports')->cascadeOnDelete();
            $table->decimal('price',10,2)->default(0);
            $table->integer('qty')->default(0);
            $table->integer('total_sale_qty')->default(0);
            $table->decimal('total_sale_amount')->default(0);
            $table->tinyInteger('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
