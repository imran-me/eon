<?php

namespace Modules\WoodArt\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * woodart:seed-cost-heads — the cost heads (and site projects) a company's
 * spreadsheets already use, created once in the ERP's Expense desk.
 *
 *     php artisan woodart:seed-cost-heads --company=3 --dry-run   # list what it would add
 *     php artisan woodart:seed-cost-heads --company=3             # add it
 *
 * --company  6 = Wood Art Interiors (default, as before) · 3 = Epal Constructions.
 *
 * Safe to run again: anything that already exists by name is skipped, nothing is
 * ever changed or removed. It writes rows owned by ONE company only —
 * expense_departments (a project) and expense_sub_categories (a cost head, under
 * that company's "Project Cost" category). No other company is touched.
 *
 * Each head posts to an account chosen BY CODE — the company's own account of that
 * code when it has one, else the shared one — so Wood Art lands on its Interiors
 * accounts (5420/5430/5410/5440) and Constructions on the shared construction
 * ones (5340 Project Material · 5350 Project Specific · 5310 Subcontractor Civil).
 *
 * (Munshi Villa is a CONSTRUCTION project. An earlier version of this command put
 * it under Wood Art; it is created for company 3 only now.)
 */
class SeedCostHeads extends Command
{
    protected $signature = 'woodart:seed-cost-heads {--company=6 : 6 = Wood Art Interiors, 3 = Epal Constructions} {--dry-run : only list what would be created}';

    protected $description = 'Create a company\'s site projects and cost heads in the Expense desk';

    /** company => the "Project Cost" expense category the heads sit under */
    private const CATEGORY = [6 => 91, 3 => 92];

    /** company => projects (expense departments) to make sure exist */
    private const PROJECTS = [
        6 => [],
        3 => ['Munshi Villa Project'],
    ];

    /** The heads the owner's sheets use: name => account code */
    private const HEADS = [
        6 => [
            // materials
            'Bricks & Breaking' => '5420', 'Cement' => '5420', 'Rod' => '5420', 'Sand (Bali)' => '5420',
            'Hardware' => '5420', 'Sanitary' => '5420', 'Electrical Materials' => '5420', 'Wood' => '5420',
            'Board' => '5420', 'Edging' => '5420', 'Molding & Shutter Bit' => '5420', 'HPL / Laminate' => '5420',
            'Glass & Tinted Glass' => '5420', 'Metal & Aluminium' => '5420', 'Paint Materials' => '5420', 'Tiles' => '5420',
            // labour
            'Labour' => '5430', 'Soil Excavation & Fill' => '5430', 'CNC Cutting' => '5430', 'HPL Pasting & Cutting' => '5430',
            // contractors
            'Contractor' => '5410', 'Carpenter Contractor' => '5410', 'Paint Contractor' => '5410', 'Furniture Work' => '5410',
            // site running costs
            'Transport & Visit' => '5440', '3D Design' => '5440', 'Extra / Others' => '5440',
        ],
        3 => [
            // materials -> 5340 Project Material Cost
            'Bricks & Breaking' => '5340', 'Cement' => '5340', 'Rod' => '5340', 'Sand (Bali)' => '5340',
            'Hardware' => '5340', 'Sanitary' => '5340', 'Electrical Materials' => '5340',
            // labour, earthwork, site running costs -> 5350 Project Specific Expense
            'Labour' => '5350', 'Soil Excavation & Fill' => '5350', 'Transport & Visit' => '5350', 'Extra / Others' => '5350',
            // contractors -> 5310 Subcontractor - Civil
            'Contractor' => '5310',
        ],
    ];

    public function handle(): int
    {
        $company = (int) $this->option('company');
        $dry     = (bool) $this->option('dry-run');

        if (! isset(self::CATEGORY[$company])) {
            $this->error("Company {$company} has no cost-head plan. Use 6 (Wood Art Interiors) or 3 (Epal Constructions).");

            return self::FAILURE;
        }
        $category = self::CATEGORY[$company];

        if (! DB::table('expense_categories')->where('id', $category)->where('company_id', $company)->exists()) {
            $this->error("Category {$category} (the Project Cost category of company {$company}) was not found — nothing created.");

            return self::FAILURE;
        }

        $made = 0;

        foreach (self::PROJECTS[$company] as $name) {
            $exists = DB::table('expense_departments')
                ->where('company_id', $company)->where('name', $name)->whereNull('deleted_at')->exists();

            $this->line(($exists ? '  exists   ' : ($dry ? '  would add ' : '  adding   ')) . 'project  ' . $name);

            if (! $exists && ! $dry) {
                DB::table('expense_departments')->insert([
                    'user_id' => 1, 'company_id' => $company, 'name' => $name,
                    'status' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $made++;
            }
        }

        foreach (self::HEADS[$company] as $name => $code) {
            $accountId = $this->accountId($code, $company);
            if (! $accountId) {
                $this->error("Account {$code} (for \"{$name}\") is missing from the chart of accounts — nothing more created.");

                return self::FAILURE;
            }

            $exists = DB::table('expense_sub_categories')
                ->where('expense_category_id', $category)->where('company_id', $company)
                ->where('name', $name)->whereNull('deleted_at')->exists();

            $this->line(($exists ? '  exists   ' : ($dry ? '  would add ' : '  adding   ')) . 'head     ' . $name . "  → {$code}");

            if (! $exists && ! $dry) {
                DB::table('expense_sub_categories')->insert([
                    'user_id' => 1, 'company_id' => $company, 'expense_category_id' => $category,
                    'account_id' => $accountId, 'name' => $name, 'status' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $made++;
            }
        }

        $this->info($dry ? 'Dry run — nothing written.' : "Done — {$made} created.");

        return self::SUCCESS;
    }

    /** The company's own account of this code when it has one, else the shared one. */
    private function accountId(string $code, int $company): ?int
    {
        $id = DB::table('accounts')->where('code', $code)->where('status', 1)->whereNull('deleted_at')
            ->where(fn ($q) => $q->where('company_id', $company)->orWhereNull('company_id'))
            ->orderByRaw('company_id IS NULL')->value('id');

        return $id ? (int) $id : null;
    }
}
