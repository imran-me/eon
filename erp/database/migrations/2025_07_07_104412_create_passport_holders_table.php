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
        Schema::create('passport_holders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('passport_no')->unique();
            $table->string('nationality');
            $table->date('date_of_birth');
            $table->date('issue_date');
            $table->date('expiry_date');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('status')->default('active'); // Example status field
            $table->unsignedBigInteger('created_by')->nullable(); // To track who created the record
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('passport_holder_categories')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passport_holders');
    }
};
