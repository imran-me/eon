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
        Schema::create('ticket_sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no')->unique();
            $table->unsignedBigInteger('ticket_id');
            $table->foreignId('agent_id')->constrained('users')->onDelete('cascade'); // user with Agent role
            $table->date('sale_date')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('currency', 5)->default('BDT');
            $table->string('status')->default('pending'); // pending, paid, cancelled
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_sales');
    }
};
