<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE banks MODIFY account_type ENUM('savings','current','fixed','credit_card') NOT NULL DEFAULT 'savings'");
        DB::statement("ALTER TABLE banks MODIFY type ENUM('bank','mobile_banking','digital_wallet','cash','credit_card') NOT NULL DEFAULT 'bank'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE banks MODIFY account_type ENUM('savings','current','fixed') NOT NULL DEFAULT 'savings'");
        DB::statement("ALTER TABLE banks MODIFY type ENUM('bank','mobile_banking','digital_wallet','cash') NOT NULL DEFAULT 'bank'");
    }
};
