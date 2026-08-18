<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Point every expense category AND sub-category at the right ledger account.
 *
 * WHAT WAS WRONG — eight of the nine categories had no account at all, so every
 * expense fell back to 6000 OPERATINGS EXPENSE and the whole taxonomy collapsed
 * into one line on the P&L. The ninth, Food & Beverage, was mapped to 6603
 * Accounting Service: the daily snacks were being booked as accountancy fees.
 *
 * WHY SUB-CATEGORIES NEED THEIR OWN — the chart is finer than the category level
 * and already says so. Communication covers 6309 Internet & Broadband and 6310
 * Telephone & Mobile, which are different lines; Food & Beverage has a leaf per
 * sub-category under 8100. With the account held only on the category, a phone
 * bill and a broadband bill posted to the same place and the detail the chart was
 * built to carry was thrown away at the last step. So the account now resolves
 * sub-category first, then category, then the general fallback — see
 * ExpenseClassificationService::accountFor().
 *
 * MATCHED BY NAME AND CODE, NOT ID, so this lands the same way on live as it does
 * here. Names are compared with letters and digits only, which is what lets
 * "Fuel — Vehicle" match whether it was typed with an em dash or a hyphen.
 *
 * Overwriting the existing mappings is deliberate: eight were empty and the ninth
 * was wrong, so there is no considered choice here to preserve.
 */
return new class extends Migration
{
    /**
     * Category → ledger account, and each sub-category → its own finer account.
     *
     * A sub-category left out has no account in the chart yet — Postage & Courier,
     * Newspaper & Magazine, Software Subscription. Those fall back to the category
     * account, which is exactly what the fallback is for, and they are worth
     * adding to the chart later rather than forcing into a line they do not belong.
     */
    private const MAP = [
        'Communication' => ['account' => '6310', 'subs' => [
            'Internet Bill'       => '6309',
            'Mobile & Phone Bill' => '6310',
        ]],

        'Food & Beverage' => ['account' => '8100', 'subs' => [
            'Tea & Snacks'                 => '8101',
            'Staff Lunch'                  => '8112',
            'Drinking Water'               => '8113',
            'Meeting & Event Refreshment'  => '8114',
            'Office Iftar / Festival Food' => '8115',
        ]],

        'Marketing & Promotion' => ['account' => '8400', 'subs' => [
            'Online Advertising (FB/Google)' => '8411',
            'Print & Banner Promotion'       => '8412',
            'Gift & Client Entertainment'    => '8413',
            'Business Card & Brochure'       => '8414',
        ]],

        'Miscellaneous' => ['account' => '6000', 'subs' => []],

        'Office Operations' => ['account' => '8200', 'subs' => [
            'Stationery & Supplies'    => '8211',
            'Office Cleaning Supplies' => '8212',
            'Printing & Photocopy'     => '8213',
            'Washroom Supplies'        => '8214',
            'Office Decoration'        => '8215',
        ]],

        // Equipment Maintenance goes to Other Maintenance rather than 6906, which
        // is Machinery Repair (Production/Workshop) — office equipment is not that.
        'Repair & Maintenance' => ['account' => '6900', 'subs' => [
            'Office Repair'          => '6901',
            'Furniture Maintenance'  => '6903',
            'IT Hardware Maintenance' => '6904',
            'Equipment Maintenance'  => '6909',
        ]],

        'Salary & Allowance' => ['account' => '6101', 'subs' => []],

        'Transport & Conveyance' => ['account' => '8300', 'subs' => [
            'Local Conveyance (Rickshaw/CNG)' => '8311',
            'Fuel — Vehicle'                  => '8312',
            'Vehicle Repair & Maintenance'    => '8313',
            'Business Travel & Tour'          => '8314',
            'Parking & Toll'                  => '8315',
        ]],

        // Electricity is the category fallback simply because it is the bill that
        // turns up most; each sub-category names its own account anyway.
        'Utility Expenses' => ['account' => '6306', 'subs' => [
            'Electricity Bill'        => '6306',
            'Water & Sewerage Bill'   => '6307',
            'Gas Bill'                => '6308',
        ]],
    ];

    public function up(): void
    {
        if (!Schema::hasColumn('expense_sub_categories', 'account_id')) {
            Schema::table('expense_sub_categories', function (Blueprint $table) {
                $table->foreignId('account_id')->nullable()->after('expense_category_id')
                    ->constrained('accounts')->nullOnDelete();
            });
        }

        DB::transaction(function () {
            $accounts = DB::table('accounts')->pluck('id', 'code');

            foreach (self::MAP as $categoryName => $spec) {
                $categoryIds = $this->findByName('expense_categories', $categoryName);

                if ($categoryIds->isEmpty()) {
                    continue;
                }

                if ($account = $accounts[$spec['account']] ?? null) {
                    DB::table('expense_categories')->whereIn('id', $categoryIds)
                        ->update(['account_id' => $account, 'updated_at' => now()]);
                }

                foreach ($spec['subs'] as $subName => $code) {
                    if (!$account = $accounts[$code] ?? null) {
                        continue;
                    }

                    $subIds = $this->findByName('expense_sub_categories', $subName, $categoryIds);

                    if ($subIds->isNotEmpty()) {
                        DB::table('expense_sub_categories')->whereIn('id', $subIds)
                            ->update(['account_id' => $account, 'updated_at' => now()]);
                    }
                }
            }
        });
    }

    /**
     * Rows whose name matches once punctuation and spacing are set aside.
     *
     * Compared on letters and digits alone so an em dash, a hyphen or a stray
     * double space cannot decide whether a mapping lands.
     */
    private function findByName(string $table, string $name, $withinCategories = null)
    {
        $wanted = $this->normalise($name);

        return DB::table($table)
            ->when($withinCategories, fn ($q) => $q->whereIn('expense_category_id', $withinCategories))
            ->get(['id', 'name'])
            ->filter(fn ($row) => $this->normalise($row->name) === $wanted)
            ->pluck('id');
    }

    private function normalise(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower($value));
    }

    public function down(): void
    {
        Schema::table('expense_sub_categories', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
        });

        // The category mappings are left in place. Restoring them would mean
        // putting back eight empty columns and one wrong account, which is not
        // worth preserving and would silently re-break the snacks posting.
    }
};
