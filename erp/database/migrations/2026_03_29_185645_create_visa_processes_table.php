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
        Schema::create('visa_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passport_holder_id')->constrained()->onDelete('cascade'); // পাসপোর্টের সাথে লিংক
            $table->foreignId('country_id')->constrained()->onDelete('cascade'); // দেশের সাথে লিংক
            $table->unsignedBigInteger('visa_category_id'); // ভিসা ক্যাটাগরি আইডি
            $table->string('status')->default('pending'); // প্রক্রিয়ার অবস্থা (pending, approved, rejected)
            $table->text('remarks')->nullable(); // বিশেষ কোনো নোটের জন্য
            $table->softDeletes(); // সফট ডিলিট ফিচার
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visa_processes');
    }
};
