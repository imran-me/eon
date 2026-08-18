<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Cash and mobile-banking accounts genuinely have no bank account
     * number — raw SQL is used here since doctrine/dbal (required by
     * Blueprint::change()) isn't installed in this project.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE banks MODIFY account_number VARCHAR(255) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE banks MODIFY account_number VARCHAR(255) NOT NULL');
    }
};
