<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Collapse the per-company copies of each expense category into ONE shared row.
 *
 * WHAT WAS WRONG — the same nine categories existed once per company: four rows
 * named "Communication", four named "Food & Beverage", and so on. With no company
 * chosen the picker listed all of them, and they read as duplicates because
 * nothing on screen told them apart.
 *
 * The deeper problem was that the split bought nothing. Of 29 category rows only
 * 9 names were distinct; all 31 sub-categories hung off EPAL GROUP's copies, and
 * 27 of 29 rows had no account mapped. Companies 1, 2 and 6 held empty shells.
 * Making the split real would have meant replicating every sub-category and every
 * account mapping per company, then keeping four copies in step forever.
 *
 * WHAT IS TRUE INSTEAD — a category is vocabulary, and the group shares it. The
 * chart of accounts already works this way: 322 of 337 accounts carry no company.
 * What actually varies per company is the EXPENSE, and `expenses.company_id`
 * already records that — expense #28 is booked to company 1 while its category
 * row belonged to company 2, which is the clearest sign the category was never
 * the thing carrying the company.
 *
 * So `company_id` on a category now means:
 *     NULL  — shared, offered to every company
 *     set   — specific to that one company
 *
 * Nothing is thrown away. For each name the richest row survives — most
 * sub-categories, then a mapped account, then oldest — and inherits any account
 * or description the thinner copies had. Sub-categories, expenses and budgets are
 * repointed at the survivor before the copies go.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('expense_categories')
                ->get()
                ->groupBy(fn ($c) => mb_strtolower(trim($c->name)))
                ->each(fn ($rows) => $this->mergeGroup($rows));
        });
    }

    /**
     * Fold one name's copies into a single shared row.
     */
    private function mergeGroup($rows): void
    {
        $ranked  = $this->rank($rows);
        $keep    = array_shift($ranked);
        $account = $keep->account_id;
        $note    = $keep->description;
        $status  = (int) $keep->status;

        foreach ($ranked as $drop) {
            // The thinner copies may still hold the one mapped account or the
            // only description anybody wrote, so take those before dropping them.
            $account = $account ?: $drop->account_id;
            $note    = $note ?: $drop->description;
            $status  = max($status, (int) $drop->status);

            $this->moveSubCategories($drop->id, $keep->id);

            DB::table('expenses')->where('expense_category_id', $drop->id)
                ->update(['expense_category_id' => $keep->id]);

            DB::table('expense_budgets')->where('expense_category_id', $drop->id)
                ->update(['expense_category_id' => $keep->id]);

            DB::table('expense_categories')->where('id', $drop->id)->delete();
        }

        DB::table('expense_categories')->where('id', $keep->id)->update([
            'company_id'  => null,
            'account_id'  => $account,
            'description' => $note,
            'status'      => $status,
            'updated_at'  => now(),
        ]);

        // A sub-category follows its parent: if the category is shared, so is it.
        DB::table('expense_sub_categories')
            ->where('expense_category_id', $keep->id)
            ->update(['company_id' => null]);
    }

    /**
     * Richest copy first — the one carrying the taxonomy is the one worth keeping.
     */
    private function rank($rows): array
    {
        $subCounts = DB::table('expense_sub_categories')
            ->whereIn('expense_category_id', $rows->pluck('id'))
            ->selectRaw('expense_category_id, COUNT(*) as n')
            ->groupBy('expense_category_id')
            ->pluck('n', 'expense_category_id');

        $list = $rows->values()->all();

        usort($list, fn ($a, $b) => [
            (int) ($subCounts[$b->id] ?? 0), $b->account_id ? 1 : 0, -$b->id,
        ] <=> [
            (int) ($subCounts[$a->id] ?? 0), $a->account_id ? 1 : 0, -$a->id,
        ]);

        return $list;
    }

    /**
     * Repoint a dropped category's sub-categories at the survivor.
     *
     * Where both copies had a sub-category of the same name, the survivor's wins
     * and anything booked against the twin is moved onto it — merging must not
     * leave two identical sub-categories under one parent, which is the same
     * duplicate this migration exists to remove.
     */
    private function moveSubCategories(int $from, int $to): void
    {
        foreach (DB::table('expense_sub_categories')->where('expense_category_id', $from)->get() as $sub) {
            $twin = DB::table('expense_sub_categories')
                ->where('expense_category_id', $to)
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($sub->name))])
                ->first();

            if ($twin) {
                DB::table('expenses')->where('expense_sub_category_id', $sub->id)
                    ->update(['expense_sub_category_id' => $twin->id]);

                DB::table('expense_sub_categories')->where('id', $sub->id)->delete();

                continue;
            }

            DB::table('expense_sub_categories')->where('id', $sub->id)
                ->update(['expense_category_id' => $to]);
        }
    }

    /**
     * Deliberately not reversible.
     *
     * Re-splitting one shared category back into a copy per company would have to
     * invent which company each sub-category and each booked expense belonged to,
     * and those copies never held that answer — it is why the merge happened. A
     * rollback that guesses is worse than one that declines.
     */
    public function down(): void
    {
        // No-op.
    }
};
