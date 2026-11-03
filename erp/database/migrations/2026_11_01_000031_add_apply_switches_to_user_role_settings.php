<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User Role Setting → applying it (owner, 2026-11-01): a person switched on (`applied`) is checked by
 * the new system instead of his old role; everyone else stays on the old system. A master switch
 * (`user_role_setting_options.enabled`) turns the new system off for everybody at once.
 * Own tables only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_role_settings') && ! Schema::hasColumn('user_role_settings', 'applied')) {
            Schema::table('user_role_settings', function (Blueprint $t) {
                $t->boolean('applied')->default(false)->after('modules');
                $t->timestamp('applied_at')->nullable()->after('applied');
            });
        }
        if (! Schema::hasTable('user_role_setting_options')) {
            Schema::create('user_role_setting_options', function (Blueprint $t) {
                $t->string('key', 50)->primary();
                $t->string('value', 255)->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_setting_options');
        if (Schema::hasTable('user_role_settings') && Schema::hasColumn('user_role_settings', 'applied')) {
            Schema::table('user_role_settings', fn (Blueprint $t) => $t->dropColumn(['applied', 'applied_at']));
        }
    }
};
