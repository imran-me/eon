<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User Role Setting (owner, 2026-11-01): one row per person — the pages and actions ticked for them on
 * the access map. Its own table; touches no existing table. Nothing reads it to grant access yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_role_settings')) {
            return;
        }
        Schema::create('user_role_settings', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->unique();
            $t->json('access');                        // {"travels.contract-flight.flights": "vce", …}
            $t->unsignedInteger('pages')->default(0);
            $t->unsignedInteger('modules')->default(0);
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_settings');
    }
};
