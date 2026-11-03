<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('expense_department_id')->nullable()->after('company_id')
                ->constrained('expense_departments')->nullOnDelete();
            $table->index(['company_id', 'expense_department_id', 'user_id'], 'transactions_company_project_party_idx');
        });

        Schema::create('project_party', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_department_id')->constrained('expense_departments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('party_type', 16);
            $table->timestamps();

            $table->unique(['expense_department_id', 'user_id', 'party_type'], 'project_party_unique');
            $table->index(['user_id', 'party_type']);
        });

        // Existing statements prove project usage. Seed only assignments;
        // financial rows and balances remain untouched.
        if (Schema::hasTable('project_statement')) {
            DB::table('project_statement')->whereNotNull('party_id')->whereNull('deleted_at')
                ->select('expense_department_id', 'party_id')->distinct()->orderBy('expense_department_id')
                ->each(function ($row) {
                    DB::table('project_party')->updateOrInsert([
                        'expense_department_id' => $row->expense_department_id,
                        'user_id' => $row->party_id,
                        'party_type' => 'vendor',
                    ], ['created_at' => now(), 'updated_at' => now()]);
                });

            DB::table('project_statement')->whereNotNull('party_id')->orderBy('id')
                ->each(function ($row) {
                    DB::table('transactions')->where('invoice_id', $row->id)
                        ->whereIn('type', ['project_statement', 'project_statement_reversal'])
                        ->update(['expense_department_id' => $row->expense_department_id]);
                });
        }
        if (Schema::hasTable('project_terms')) {
            DB::table('project_terms')->whereNotNull('client_party_id')
                ->select('expense_department_id', 'client_party_id')->distinct()->orderBy('expense_department_id')
                ->each(function ($row) {
                    DB::table('project_party')->updateOrInsert([
                        'expense_department_id' => $row->expense_department_id,
                        'user_id' => $row->client_party_id,
                        'party_type' => 'client',
                    ], ['created_at' => now(), 'updated_at' => now()]);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_party');
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['expense_department_id']);
            $table->dropIndex('transactions_company_project_party_idx');
            $table->dropColumn('expense_department_id');
        });
    }
};
