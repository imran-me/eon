<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_items', function (Blueprint $table) {
            // party_type: 'customer' | 'supplier' | 'agent' | 'vendor'
            $table->string('party_type', 20)->nullable()->after('note');
            // party_id references customers.id / suppliers.id / users.id depending on party_type
            $table->unsignedBigInteger('party_id')->nullable()->after('party_type');
            $table->index(['party_type', 'party_id'], 'ji_party_index');
        });
    }

    public function down(): void
    {
        Schema::table('journal_items', function (Blueprint $table) {
            $table->dropIndex('ji_party_index');
            $table->dropColumn(['party_type', 'party_id']);
        });
    }
};
