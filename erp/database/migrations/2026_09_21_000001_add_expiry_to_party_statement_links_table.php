<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('party_statement_links', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->index()->after('last_opened_at');
        });

        // Existing shared links receive the same 30-day lifetime from deployment.
        DB::table('party_statement_links')->whereNull('expires_at')->update(['expires_at' => now()->addDays(30)]);
    }

    public function down(): void
    {
        Schema::table('party_statement_links', fn (Blueprint $table) => $table->dropColumn('expires_at'));
    }
};
