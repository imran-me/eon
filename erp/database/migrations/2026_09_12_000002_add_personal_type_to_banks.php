<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE banks MODIFY account_type ENUM('savings','current','fixed','credit_card','personal') NOT NULL DEFAULT 'savings'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE banks MODIFY account_type ENUM('savings','current','fixed','credit_card') NOT NULL DEFAULT 'savings'");
    }
};
