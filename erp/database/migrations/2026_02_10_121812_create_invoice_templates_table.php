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
        Schema::create('invoice_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Sales A4, Ticket Receipt, Salary Slip
            $table->string('type'); // sale, purchase, ticket_sale, salary, payment
            $table->string('paper_size')->default('A4'); // A4, A5, Thermal
            $table->string('orientation')->default('portrait'); // portrait/landscape
            $table->boolean('is_default')->default(false);            
            $table->json('layout_config')->nullable(); // design config in JSON
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_templates');
    }
};
