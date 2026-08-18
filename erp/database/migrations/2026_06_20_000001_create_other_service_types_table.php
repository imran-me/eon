<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('other_service_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->string('bg_color')->nullable();
            $table->decimal('default_fee', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('other_service_types')->insert([
            ['name' => 'VFS Appointment',        'icon' => 'fa-passport',        'color' => '#dc2626', 'bg_color' => '#fee2e2', 'default_fee' => null, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Bank Statement',          'icon' => 'fa-file-invoice',     'color' => '#1d4ed8', 'bg_color' => '#dbeafe', 'default_fee' => null, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Travel Insurance',        'icon' => 'fa-shield-halved',    'color' => '#be123c', 'bg_color' => '#ffe4e6', 'default_fee' => null, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Hotel Booking',           'icon' => 'fa-building',         'color' => '#0e7490', 'bg_color' => '#cffafe', 'default_fee' => null, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Translation & Attestation', 'icon' => 'fa-stamp',          'color' => '#7c3aed', 'bg_color' => '#ede9fe', 'default_fee' => null, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Document Support',        'icon' => 'fa-file-lines',       'color' => '#4338ca', 'bg_color' => '#e0e7ff', 'default_fee' => null, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('other_service_types');
    }
};
