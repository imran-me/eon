<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_schedules', function (Blueprint $table) {
            // Ad-hoc schedules have no linked polymorphic model
            $table->string('schedulable_type')->nullable()->change();
            $table->unsignedBigInteger('schedulable_id')->nullable()->change();

            // Ad-hoc schedules identify the party by name, not by FK
            $table->unsignedBigInteger('party_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->string('schedulable_type')->nullable(false)->change();
            $table->unsignedBigInteger('schedulable_id')->nullable(false)->change();
            $table->unsignedBigInteger('party_id')->nullable(false)->change();
        });
    }
};
