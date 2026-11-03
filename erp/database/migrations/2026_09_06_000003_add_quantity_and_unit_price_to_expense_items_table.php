<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 2)->default(1)->after('description');
            $table->decimal('unit_price', 15, 2)->nullable()->after('quantity');
        });

        // Existing line amounts were totals for a single item. Preserve every
        // total by treating those rows as quantity 1 at the same unit price.
        DB::table('expense_items')->update([
            'quantity' => 1,
            'unit_price' => DB::raw('amount'),
        ]);
    }

    public function down(): void
    {
        Schema::table('expense_items', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'unit_price']);
        });
    }
};
