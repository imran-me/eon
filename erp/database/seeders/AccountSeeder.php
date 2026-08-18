<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [

            // ── ASSETS ───────────────────────────────────
            [
                'code'            => '1130',
                'name'            => 'Accounts Receivable',
                'type'            => 'asset',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => '1140',
                'name'            => 'Inventory / Stock',
                'type'            => 'asset',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => '1150',
                'name'            => 'Prepaid Expense',
                'type'            => 'asset',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => '1310',
                'name'            => 'Loan Receivable',
                'type'            => 'asset',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => 'AST-005',
                'name'            => 'Cash',
                'type'            => 'asset',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],

            // ── LIABILITIES ───────────────────────────────
            [
                'code'            => '2110',
                'name'            => 'Accounts Payable',
                'type'            => 'liability',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => '2130',
                'name'            => 'Salary Payable',
                'type'            => 'liability',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => '2120',
                'name'            => 'Loan Payable',
                'type'            => 'liability',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => '2140',
                'name'            => 'Tax Payable',
                'type'            => 'liability',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],

            // ── EQUITY ────────────────────────────────────
            [
                'code'            => '3110',
                'name'            => "Owner's Equity",
                'type'            => 'equity',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => '3120',
                'name' => 'Retained Earnings',
                'type'            => 'equity',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],

            // ── INCOME ────────────────────────────────────
            [
                'code'            => '4100',
                'name'            => 'Sales Revenue',
                'type'            => 'income',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => '4120',
                'name'            => 'Ticket Sales Revenue',
                'type'            => 'income',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => '4140',
                'name'            => 'Flight Sales Revenue',
                'type'            => 'income',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => '4150',
                'name'            => 'File Sales Revenue',
                'type'            => 'income',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => '4160',
                'name'            => 'Visa Sales Revenue',
                'type'            => 'income',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => '4210',
                'name'            => 'Loan Interest Income',
                'type'            => 'income',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],

            // ── EXPENSES ──────────────────────────────────
            [
                'code'            => '5000',
                'name'            => 'Cost of Goods Sold',
                'type'            => 'expense',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => '5100',
                'name'            => 'Purchase Expense',
                'type'            => 'expense',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => 'EXP-003',
                'name'            => 'Ticket Purchase Cost',
                'type'            => 'expense',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => '6110',
                'name'            => 'Salary Expense',
                'type'            => 'expense',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => '6000',
                'name'            => 'General Expense',
                'type'            => 'expense',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => 'EXP-006',
                'name'            => 'Loan Interest Expense',
                'type'            => 'expense',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
        ];

        foreach ($accounts as $account) {
            Account::updateOrCreate(
                ['code' => $account['code']],
                $account
            );
        }

        $this->command->info('✅ Chart of Accounts seeded and synchronized successfully.');
    }
}