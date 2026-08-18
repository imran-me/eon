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
        Schema::create('return_refs', function (Blueprint $table) {
            $table->id();
            $table->string('return_no')->unique();
            $table->string('return_type'); // purchase or sale
            $table->unsignedBigInteger('reference_id')->nullable(); // purchase_id or sale_id
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->date('return_date');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_refs');
    }
};
