<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket routes are soft-deleted (owner, 2026-09-21): a route that already
 * has purchases, sales, bookings or flights is renamed "… (Deleted)" and
 * hidden from every dropdown, while the history that points at it still
 * resolves (the belongsTo relations read withTrashed()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->softDeletes()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
