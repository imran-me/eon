<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('sale_date');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('purchase_date');
        });

        Schema::table('ticket_sales', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('sale_date');
        });

        Schema::table('ticket_purchases', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('purchase_date');
        });
    }

    public function down(): void
    {
        Schema::table('sales', fn (Blueprint $t) => $t->dropColumn('due_date'));
        Schema::table('purchases', fn (Blueprint $t) => $t->dropColumn('due_date'));
        Schema::table('ticket_sales', fn (Blueprint $t) => $t->dropColumn('due_date'));
        Schema::table('ticket_purchases', fn (Blueprint $t) => $t->dropColumn('due_date'));
    }
};
