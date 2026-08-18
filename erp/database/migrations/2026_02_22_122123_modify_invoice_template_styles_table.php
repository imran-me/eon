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
        Schema::table('invoice_template_styles', function (Blueprint $table) {            
            $table->dropColumn('font_family');
            $table->string('title_font')->default('twelve');
            $table->string('text_font')->default('one');
            $table->string('number_font')->default('fifteen');

            $table->dropColumn('primary_color');
            $table->dropColumn('secondary_color');
            $table->string('title_color')->default('#111827'); 
            $table->string('title_bg')->default('#1E293B'); 
            $table->string('tabler_header_bg')->default('#1b75cf'); 
            $table->string('text_color')->default('#334155');                                      
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_template_styles', function (Blueprint $table) {            
            $table->string('font_family')->default('Inter');
            $table->string('primary_color')->default('#000000');
            $table->string('secondary_color')->nullable();
            $table->dropColumn('title_color');
            $table->dropColumn('title_bg');
            $table->dropColumn('tabler_header_bg');
            $table->dropColumn('text_color');
            $table->dropColumn('title_font');
            $table->dropColumn('text_font');
            $table->dropColumn('number_font');
        });
    }
};
