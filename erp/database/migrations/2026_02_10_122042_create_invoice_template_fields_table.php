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
        Schema::create('invoice_template_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_template_id')->constrained()->cascadeOnDelete();
            $table->string('label');       // Passport No, Flight Date, Basic Salary
            $table->string('key');         // passport_no, flight_date
            $table->string('type');        // text, number, date, currency
            $table->string('section');     // header, body, footer
            $table->integer('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_template_fields');
    }
};
