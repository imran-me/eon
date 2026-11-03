<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A batch is opened the day the agent hands over passports — often before
 * anyone knows the category, route or flight number. Only the date is
 * required now; the batch category is a default that may arrive later.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('contract_flights', 'flight_category_id')) {
            DB::statement('ALTER TABLE contract_flights MODIFY flight_category_id BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        // Left nullable: rows saved without a category would block a NOT NULL revert.
    }
};
