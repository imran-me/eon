<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['ticket_purchases', 'ticket_bookings'];

    private const COLUMNS = [
        'free_baggage_kg', 'extra_baggage_kg', 'extra_baggage_cost',
        'extra_baggage_agent_charge', 'extra_baggage_bearer', 'baggage_conditions',
    ];

    public function up(): void
    {
        // Leave historical free-text baggage and all existing postings intact.
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['free_baggage_kg', 'extra_baggage_kg'] as $column) {
                    if (! Schema::hasColumn($tableName, $column)) {
                        $table->decimal($column, 10, 2)->nullable();
                    }
                }

                foreach (['extra_baggage_cost', 'extra_baggage_agent_charge'] as $column) {
                    if (! Schema::hasColumn($tableName, $column)) {
                        $table->decimal($column, 15, 2)->nullable();
                    }
                }

                if (! Schema::hasColumn($tableName, 'extra_baggage_bearer')) {
                    // customer = the ticket's buyer; company = we bear the cost.
                    $table->string('extra_baggage_bearer', 16)->nullable();
                }

                if (! Schema::hasColumn($tableName, 'baggage_conditions')) {
                    $table->text('baggage_conditions')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $columns = array_values(array_filter(
                self::COLUMNS,
                fn (string $column) => Schema::hasColumn($tableName, $column)
            ));

            if ($columns !== []) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($columns));
            }
        }
    }
};
