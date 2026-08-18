<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder_OLD extends Seeder
{
    public function run(): void
    {
        $accounts = [

            // ── ASSETS ───────────────────────────────────
            [
                'code'            => 'AST-001',
                'name'            => 'Accounts Receivable',
                'type'            => 'asset',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => 'AST-002',
                'name'            => 'Inventory / Stock',
                'type'            => 'asset',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => 'AST-003',
                'name'            => 'Prepaid Expense',
                'type'            => 'asset',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => 'AST-004',
                'name'            => 'Loan Receivable',
                'type'            => 'asset',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],

            // ── LIABILITIES ───────────────────────────────
            [
                'code'            => 'LIA-001',
                'name'            => 'Accounts Payable',
                'type'            => 'liability',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => 'LIA-002',
                'name'            => 'Salary Payable',
                'type'            => 'liability',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => 'LIA-003',
                'name'            => 'Loan Payable',
                'type'            => 'liability',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => 'LIA-004',
                'name'            => 'Tax Payable',
                'type'            => 'liability',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],

            // ── EQUITY ────────────────────────────────────
            [
                'code'            => 'EQT-001',
                'name'            => "Owner's Equity",
                'type'            => 'equity',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => 'EQT-002',
                'name'            => 'Retained Earnings',
                'type'            => 'equity',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],

            // ── INCOME ────────────────────────────────────
            [
                'code'            => 'INC-001',
                'name'            => 'Sales Revenue',
                'type'            => 'income',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => 'INC-002',
                'name'            => 'Ticket Sales Revenue',
                'type'            => 'income',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => 'INC-003',
                'name'            => 'Loan Interest Income',
                'type'            => 'income',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],

            // ── EXPENSES ──────────────────────────────────
            [
                'code'            => 'EXP-001',
                'name'            => 'Cost of Goods Sold',
                'type'            => 'expense',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => 'EXP-002',
                'name'            => 'Purchase Expense',
                'type'            => 'expense',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => '',
                'name'            => 'Ticket Purchase Cost',
                'type'            => 'expense',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => 'EXP-004',
                'name'            => 'Salary Expense',
                'type'            => 'expense',
                'parent_id'       => null,
                'opening_balance' => 0,
                'status'          => true,
            ],
            [
                'code'            => 'EXP-005',
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

        $this->command->info('✅ Chart of Accounts seeded successfully.');
    }
}
