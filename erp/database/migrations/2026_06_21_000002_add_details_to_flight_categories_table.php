<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flight_categories', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('name');
            $table->string('icon')->nullable()->after('code');
            $table->string('typical_routes')->nullable()->after('icon');
            $table->unsignedInteger('default_seats')->default(0)->after('typical_routes');
            $table->decimal('base_fare', 12, 2)->default(0)->after('default_seats');
            $table->enum('status', ['active', 'seasonal', 'inactive'])->default('active')->after('base_fare');
        });

        DB::table('flight_categories')->update(['status' => 'active']);

        Schema::table('flight_categories', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::create('flight_category_airline', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flight_category_id')->constrained('flight_categories')->cascadeOnDelete();
            $table->foreignId('airline_id')->constrained('airlines')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flight_category_airline');

        Schema::table('flight_categories', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            $table->dropColumn(['code', 'icon', 'typical_routes', 'default_seats', 'base_fare', 'status']);
        });
    }
};
