<?php

namespace Modules\WoodArt\Console;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\WoodArt\Modules\Accounts\Support\ExpensePoster;
use Modules\WoodArt\Modules\Accounts\Support\ProjectContext;

/**
 * woodart:undo-munshi-wrong-company — takes Munshi Villa back OUT of Wood Art
 * Interiors (company 6), where an earlier version of woodart:seed-munshi-villa
 * wrongly put it. Munshi Villa is a construction project (company 3).
 *
 *     php artisan woodart:undo-munshi-wrong-company --dry-run
 *     php artisan woodart:undo-munshi-wrong-company
 *
 * HOW IT UNDOES, in the owner's own terms ("nothing is deleted, reversed by
 * addition"):
 *   · an APPROVED expense is REVERSED through the Expense desk's own reverse():
 *     an opposite journal is added and the original stays, so the ledger nets to
 *     zero and the history shows both;
 *   · every one of those expenses is then SOFT-deleted (hidden, row kept) so the
 *     project sheet and the lists stop showing it;
 *   · the wrong statement rows are soft-deleted and the contract value cleared;
 *   · the wrong "Munshi Villa Project" department is soft-deleted, but ONLY when
 *     no live expense of company 6 still uses it.
 *
 * It touches only company-6 rows whose reference starts "WA-MV-" and that
 * department. On a server where only the cost-head seeder ran (no expenses, no
 * journals) it simply removes the stray department. Safe to run again.
 *
 * The 27 cost heads of company 6 (Rod, Cement …) are left: they are harmless
 * and Wood Art may use them.
 */
class UndoMunshiWrongCompany extends Command
{
    protected $signature = 'woodart:undo-munshi-wrong-company {--dry-run : do everything, then roll it back}';

    protected $description = 'Remove the Munshi Villa entries wrongly filed under Wood Art Interiors (reversal, not deletion)';

    private const WRONG_COMPANY = 6;
    private const PROJECT = 'Munshi Villa Project';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $admin = User::role('super admin')->orderBy('id')->first();
        if (! $admin) {
            $this->error('No user with the "super admin" role was found — it is needed to reverse the expenses.');

            return self::FAILURE;
        }
        Auth::login($admin);
        ProjectContext::force(self::WRONG_COMPANY);

        $this->info(($dry ? '[DRY RUN — everything below is rolled back] ' : '') . 'Taking Munshi Villa out of Wood Art Interiors (company ' . self::WRONG_COMPANY . ')');

        DB::beginTransaction();
        try {
            $dept = DB::table('expense_departments')
                ->where('company_id', self::WRONG_COMPANY)->where('name', self::PROJECT)->whereNull('deleted_at')->value('id');

            $ids = DB::table('expenses')->where('company_id', self::WRONG_COMPANY)
                ->where('reference', 'like', 'WA-MV-%')->whereNull('deleted_at')->orderBy('id')->pluck('id');

            $reversed = 0;
            $hidden = 0;
            foreach ($ids as $id) {
                $e = Expense::find($id);
                if ($e->approval_status === Expense::APPROVED) {
                    ExpensePoster::reverse((int) $id, now()->toDateString());
                    $reversed++;
                }
                Expense::whereKey($id)->first()?->delete();   // soft delete: the row stays
                $hidden++;
            }

            $stmt = 0;
            if ($dept && Schema::hasTable('project_statement')) {
                $stmt = DB::table('project_statement')->where('expense_department_id', $dept)->whereNull('deleted_at')
                    ->update(['deleted_at' => now(), 'updated_at' => now()]);
                if (Schema::hasTable('project_terms')) {
                    DB::table('project_terms')->where('expense_department_id', $dept)
                        ->update(['contract_value' => null, 'note' => 'cleared: Munshi Villa belongs to Epal Constructions', 'updated_at' => now()]);
                }
            }

            $deptGone = false;
            if ($dept) {
                $live = DB::table('expenses')->where('expense_department_id', $dept)->whereNull('deleted_at')->count();
                if ($live === 0) {
                    DB::table('expense_departments')->where('id', $dept)->update(['deleted_at' => now(), 'updated_at' => now()]);
                    $deptGone = true;
                } else {
                    $this->warn("The department is still used by {$live} other expense(s), so it was left.");
                }
            }

            // proof: what the reversal left on the ledger for those expenses is zero
            $net = 0.0;
            if ($ids->isNotEmpty()) {
                $net = (float) DB::table('journal_items as ji')->join('journal_entries as je', 'je.id', '=', 'ji.journal_entry_id')
                    ->where('je.source', 'expense')->whereIn('je.source_id', $ids)
                    ->selectRaw('COALESCE(SUM(ji.debit) - SUM(ji.credit), 0) n')->value('n');
            }

            $this->line('');
            $this->line("  expenses found            : {$ids->count()}");
            $this->line("  reversed (opposite journal): {$reversed}");
            $this->line("  hidden (soft-deleted)      : {$hidden}");
            $this->line("  statement rows hidden      : {$stmt}");
            $this->line('  wrong department removed   : ' . ($deptGone ? 'yes' : ($dept ? 'no (still in use)' : 'none found')));
            $this->line('  ledger effect of those entries now: ' . number_format($net, 2) . '  (must be 0.00)');

            if (abs($net) > 0.004) {
                throw new \RuntimeException('The ledger does not net to zero after the reversal — rolled back.');
            }

            $dry ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Nothing was changed. ' . $e->getMessage());
            ProjectContext::force(null);

            return self::FAILURE;
        }

        ProjectContext::force(null);
        $this->info($dry ? 'Dry run finished — nothing was changed.' : 'Done.');

        return self::SUCCESS;
    }
}
