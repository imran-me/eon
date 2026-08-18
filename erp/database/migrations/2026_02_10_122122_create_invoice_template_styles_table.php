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
        Schema::create('invoice_template_styles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_template_id')->constrained()->cascadeOnDelete();
            $table->string('font_family')->default('Inter');
            $table->string('primary_color')->default('#000000');
            $table->string('secondary_color')->nullable();
            $table->boolean('show_border')->default(true);
            $table->boolean('striped_table')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_template_styles');
    }
};
