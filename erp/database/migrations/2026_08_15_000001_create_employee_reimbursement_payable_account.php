<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The account an employee's own-pocket spending is owed against.
 *
 * Until now the chart could say "the company holds cash in someone's pocket"
 * (1015 Petty Cash Float, an asset) but had no way to say the opposite — "an
 * employee is holding a receipt the company has not paid for yet". That is a
 * LIABILITY, and without it a reimbursable expense has nowhere to credit:
 * crediting the float would drive it negative and be refused outright by
 * ExpenseController::assertFloatNotOverdrawn().
 *
 * Deliberately NOT 2113 Expense Payable — that sits under 2110 Accounts Payable,
 * the supplier side. Employee reimbursements filed there would age alongside
 * supplier invoices and make that report meaningless.
 *
 * company_id is NULL because every company posts to it. config/accounts.php
 * states the rule and the reason: reports filter ACCOUNTS by company but JOURNAL
 * ITEMS by entry, so a company-owned account posted to by another company shows
 * its debit and hides its credit, and that company's trial balance silently
 * stops balancing.
 *
 * Who is owed what is told apart by party_id on the journal item — the same
 * mechanism that separates one custodian's float from another's, not one account
 * per person.
 */
return new class extends Migration
{
    private const CODE   = '2240';
    private const PARENT = '2200';   // Employee Payables

    public function up(): void
    {
        // Idempotent: the account may already have been added by hand on a server
        // that ran ahead of this migration. Re-creating it would leave two rows
        // with the same code and config/accounts.php would resolve whichever came
        // first.
        if (DB::table('accounts')->where('code', self::CODE)->exists()) {
            return;
        }

        $parentId = DB::table('accounts')->where('code', self::PARENT)->value('id');

        // No parent means a chart this migration does not recognise. Stop rather
        // than attaching the account to the root, where a tree-walking report
        // would double count it against the liability total.
        if (!$parentId) {
            throw new RuntimeException(
                'Account ' . self::PARENT . ' (Employee Payables) is missing, so '
                . self::CODE . ' has no parent to sit under. Check the chart before rerunning.'
            );
        }

        DB::table('accounts')->insert([
            'code'            => self::CODE,
            'name'            => 'Employee Expense Reimbursement Payable',
            'type'            => 'liability',
            'parent_id'       => $parentId,
            'company_id'      => null,
            'opening_balance' => 0,
            'status'          => 1,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    public function down(): void
    {
        // Only if nothing was ever posted to it. An account carrying journal items
        // cannot be removed without orphaning them, and a reimbursement someone
        // was actually owed is not something a rollback should erase.
        $account = DB::table('accounts')->where('code', self::CODE)->first();

        if (!$account) {
            return;
        }

        if (DB::table('journal_items')->where('account_id', $account->id)->exists()) {
            throw new RuntimeException(
                'Account ' . self::CODE . ' has journal items posted to it and will not be removed. '
                . 'Reverse those entries first if this really must be rolled back.'
            );
        }

        DB::table('accounts')->where('id', $account->id)->delete();
    }
};
