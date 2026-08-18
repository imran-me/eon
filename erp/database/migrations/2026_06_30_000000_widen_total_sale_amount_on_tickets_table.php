<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tickets MODIFY total_sale_amount DECIMAL(14,2) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tickets MODIFY total_sale_amount DECIMAL(8,2) NOT NULL DEFAULT 0');
    }
};
