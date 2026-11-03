<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('vendor_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_type_id')->constrained('vendor_types')->cascadeOnDelete();
            $table->string('slug', 80);
            $table->string('name', 120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['vendor_type_id', 'slug']);
        });

        Schema::create('vendor_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->foreignId('vendor_type_id')->constrained('vendor_types')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('user_vendor_category', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vendor_category_id')->constrained('vendor_categories')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'vendor_category_id']);
        });

        $now = now();
        $types = [
            ['id' => 1, 'slug' => 'material_supplier', 'name' => 'Material Supplier', 'sort_order' => 10],
            ['id' => 2, 'slug' => 'labour_supplier', 'name' => 'Labour Supplier', 'sort_order' => 20],
            ['id' => 3, 'slug' => 'contractor', 'name' => 'Contractor', 'sort_order' => 30],
            ['id' => 4, 'slug' => 'service_provider', 'name' => 'Service Provider', 'sort_order' => 40],
        ];
        foreach ($types as $type) {
            DB::table('vendor_types')->insert($type + ['status' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        $typeIds = collect($types)->pluck('id', 'slug');
        $categories = [
            'material_supplier' => ['Bricks', 'Cement', 'Sand', 'Rod / Steel', 'Wood', 'Glass', 'Tiles', 'Sanitary', 'Electrical Materials', 'Paint', 'Hardware', 'Others'],
            'labour_supplier' => ['General Labour', 'Masonry', 'Electrical Labour', 'Plumbing Labour', 'Carpentry Labour', 'Painting Labour', 'Tiles Labour', 'Others'],
            'contractor' => ['Civil', 'Electrical', 'Glass', 'Carpentry', 'Paint', 'Tiles & Sanitary', 'Plumbing', 'Ceiling', 'Aluminium', 'Site Engineer / Consultant', 'Others'],
            'service_provider' => ['Transport', 'Equipment Rental', 'Design / Consultancy', 'Security', 'Cleaning', 'Maintenance', 'Others'],
        ];
        foreach ($categories as $typeSlug => $names) {
            foreach ($names as $index => $name) {
                DB::table('vendor_categories')->insert([
                    'vendor_type_id' => $typeIds[$typeSlug],
                    'slug' => str($name)->lower()->replace([' / ', ' & ', ' '], '_')->replace(['/', '&'], '_')->toString(),
                    'name' => $name,
                    'sort_order' => ($index + 1) * 10,
                    'status' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_vendor_category');
        Schema::dropIfExists('vendor_profiles');
        Schema::dropIfExists('vendor_categories');
        Schema::dropIfExists('vendor_types');
    }
};
