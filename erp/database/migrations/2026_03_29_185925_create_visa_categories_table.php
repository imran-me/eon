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
        Schema::create('visa_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Tourist, Student, Work, etc.
            $table->decimal('base_fee', 10, 2)->default(0); // ডিফল্ট ফি (ঐচ্ছিক)
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes(); // সফট ডিলিট ফিচার
            $table->timestamps();
            $table->index('name'); // নামের উপর ইনডেক্স, সার্চের জন্য সাহায্য করবে
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visa_categories');
    }
};
