<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The three things the expense entry flow needs and the schema did not have.
 *
 * 1. expenses.department_id — an expense could say which company and which
 *    category it belonged to, but never which department spent it, so
 *    "what does this department cost us" could only ever be answered from
 *    salaries. Nullable: every expense already on file predates the field and
 *    guessing a department for them would be inventing history.
 *
 * 2. expenses.other_note — the escape hatch. When the right department or
 *    category genuinely is not on the list, the spender types what it was
 *    instead of inventing a category nobody will use twice. One column rather
 *    than one per level: only one level is ever "Others" on a given expense, and
 *    three nullable columns to say one thing is three chances to disagree.
 *
 * 3. expense_categories.account_id — which chart-of-accounts expense account a
 *    category posts to. The form has been asking every user to pick a GL code by
 *    hand; a category knows its own account, so the account should follow the
 *    category and the picker can go. Nullable, and the posting falls back to the
 *    general expense account when a category has not been mapped yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('company_id')
                ->constrained('departments')->nullOnDelete();

            $table->string('other_note')->nullable()->after('expense_sub_category_id');

            $table->index(['company_id', 'department_id']);
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('company_id')
                ->constrained('accounts')->nullOnDelete();
        });

        // Map every category that has never been mapped to whatever account the
        // expenses already booked against it used. That is the mapping the books
        // have actually been keeping, so reading it back is more truthful than
        // dropping everything onto the general expense account.
        $observed = DB::table('expenses')
            ->select('expense_category_id', 'account_id', DB::raw('COUNT(*) as n'))
            ->whereNotNull('expense_category_id')
            ->whereNotNull('account_id')
            ->groupBy('expense_category_id', 'account_id')
            ->orderByDesc('n')
            ->get();

        $seen = [];

        foreach ($observed as $row) {
            // orderByDesc('n') means the first account seen for a category is the
            // one it has been used with most; later, rarer pairings are ignored.
            if (isset($seen[$row->expense_category_id])) {
                continue;
            }

            $seen[$row->expense_category_id] = true;

            DB::table('expense_categories')
                ->where('id', $row->expense_category_id)
                ->update(['account_id' => $row->account_id]);
        }
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'department_id']);
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('other_note');
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });
    }
};
