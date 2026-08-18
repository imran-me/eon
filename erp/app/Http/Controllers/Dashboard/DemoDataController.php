<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DemoDataController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view company setting', only: ['index']),
            new Middleware('permission:delete company setting', only: ['destroy']),
        ];
    }

    /**
     * Sections and the DB tables to wipe per section.
     * Order within each section matters: child tables first to avoid FK violations.
     */
    private function sections(): array
    {
        return [
            'air_ticketing' => [
                'label'       => 'Air Ticketing',
                'sub'         => 'Ticket Manage & Manage Sales',
                'icon'        => 'fas fa-plane',
                'color'       => 'blue',
                'tables'      => [
                    'ticket_reissues',
                    'ticket_refunds',
                    'ticket_sale_items',
                    'ticket_legs',
                    'ticket_sales',
                    'ticket_purchases',
                    'tickets',
                ],
                'count_table' => 'ticket_sales',
            ],
            'visa_application' => [
                'label'       => 'Visa Processing',
                'sub'         => 'Application Board',
                'icon'        => 'fas fa-passport',
                'color'       => 'indigo',
                'tables'      => [
                    'visa_process_documents',
                    'visa_attachments',
                    'visa_processes',
                ],
                'count_table' => 'visa_processes',
            ],
            'visa_category' => [
                'label'       => 'Visa Processing',
                'sub'         => 'Visa Category',
                'icon'        => 'fas fa-tags',
                'color'       => 'violet',
                'tables'      => ['visa_categories'],
                'count_table' => 'visa_categories',
            ],
            'products_inventory' => [
                'label'       => 'Business Operations',
                'sub'         => 'Product & Inventory',
                'icon'        => 'fas fa-boxes',
                'color'       => 'emerald',
                'tables'      => [
                    'stock_transfers',
                    'stock_movements',
                    'stocks',
                    'purchase_items',
                    'purchases',
                    'sub_categories',
                    'products',
                    'brands',
                    'units',
                    'categories',
                ],
                'count_table' => 'products',
            ],
            'sales' => [
                'label'       => 'Business Operations',
                'sub'         => 'Sales',
                'icon'        => 'fas fa-receipt',
                'color'       => 'teal',
                'tables'      => [
                    'return_items',
                    'return_refs',
                    'sale_items',
                    'sales_records',
                    'sales',
                ],
                'count_table' => 'sales',
            ],
            'crm_leads' => [
                'label'       => 'CRM – Leads',
                'sub'         => 'Lead Manager, Followup & Reminder',
                'icon'        => 'fas fa-user-tie',
                'color'       => 'orange',
                'tables'      => [
                    'lead_status_histories',
                    'lead_reminders',
                    'lead_followups',
                    'leads',
                ],
                'count_table' => 'leads',
            ],
            'crm_deals' => [
                'label'       => 'CRM – Deals',
                'sub'         => 'Deal Manager & Estimate',
                'icon'        => 'fas fa-handshake',
                'color'       => 'amber',
                'tables'      => [
                    'proposals',
                    'estimates',
                    'deals',
                ],
                'count_table' => 'deals',
            ],
            'lead_projects' => [
                'label'       => 'Projects',
                'sub'         => 'Lead Projects (type: lead_project)',
                'icon'        => 'fas fa-project-diagram',
                'color'       => 'cyan',
                'tables'      => [],          // handled with custom logic
                'count_table' => null,
            ],
            'journal' => [
                'label'       => 'Accounts',
                'sub'         => 'Manage Journal',
                'icon'        => 'fas fa-book',
                'color'       => 'rose',
                'tables'      => [
                    'journal_items',
                    'journal_entries',
                ],
                'count_table' => 'journal_entries',
            ],
            'expenses' => [
                'label'       => 'Accounts – Expense',
                'sub'         => 'All Expenses',
                'note'        => 'Records, line items, ledger postings, the staff reimbursements that paid them back, and attachment files',
                'icon'        => 'fas fa-file-invoice-dollar',
                'color'       => 'pink',
                'tables'      => [],          // handled with custom logic
                'count_table' => null,
                'scoped'      => true,
            ],
            'expense_budgets' => [
                'label'       => 'Accounts – Expense',
                'sub'         => 'Budget Setup',
                'note'        => 'Budget rows only — clearing expenses leaves these alone',
                'icon'        => 'fas fa-bullseye',
                'color'       => 'stone',
                'tables'      => [],          // handled with custom logic
                'count_table' => null,
                'scoped'      => true,
            ],
            'daily_fund' => [
                'label'       => 'Accounts – Expense',
                'sub'         => 'Daily Cost Fund',
                'note'        => 'The per-company ceilings ONLY — every company goes back to "No daily fund set". The "spent today" figures are not stored here, they are read from the expenses, so clear All Expenses to zero those',
                'icon'        => 'fas fa-gauge-high',
                'color'       => 'amber',
                'tables'      => [],          // handled with custom logic
                'count_table' => null,
                'scoped'      => true,
            ],
            'petty_cash' => [
                'label'       => 'Accounts – Petty Cash',
                'sub'         => 'Petty Cash in Hand',
                'note'        => 'Issues, returns, their ledger postings and any expense settled from a pocket — the custodian floats themselves are kept, and every balance falls to zero',
                'icon'        => 'fas fa-wallet',
                'color'       => 'green',
                'tables'      => [],          // handled with custom logic
                'count_table' => null,
                'scoped'      => true,
            ],
            'payment_schedules' => [
                'label'       => 'Accounts',
                'sub'         => 'Payment Schedules',
                'icon'        => 'fas fa-calendar-check',
                'color'       => 'purple',
                'tables'      => [
                    'payment_schedule_logs',
                    'payment_schedules',
                ],
                'count_table' => 'payment_schedules',
            ],
            'party_statement' => [
                'label'       => 'Accounts',
                'sub'         => 'Party Statement (manual payments & invoices)',
                'icon'        => 'fas fa-file-invoice',
                'color'       => 'sky',
                'tables'      => [],          // handled with custom logic
                'count_table' => null,
            ],
            'portal_accounts' => [
                'label'       => 'Accounts',
                'sub'         => 'Portal Balances (resets balance & clears ledger — portals themselves are kept)',
                'icon'        => 'fas fa-plug',
                'color'       => 'fuchsia',
                'tables'      => [],          // handled with custom logic
                'count_table' => 'portal_balances',
            ],
            'office_todo' => [
                'label'       => 'Office',
                'sub'         => 'Todo & Notices',
                'icon'        => 'fas fa-tasks',
                'color'       => 'lime',
                'tables'      => [
                    'office_todo_checklists',
                    'office_todo_assignees',
                    'office_todos',
                    'notices',
                ],
                'count_table' => 'office_todos',
            ],
        ];
    }

    public function index(Request $request)
    {
        $companyId = $this->resolveCompanyId($request->input('company_id'));
        $sections  = $this->sections();

        foreach ($sections as $key => &$section) {
            if ($key === 'lead_projects') {
                $section['count'] = DB::table('projects')->where('type', 'lead_project')->count();
            } elseif ($key === 'party_statement') {
                $section['count'] = DB::table('transactions')->whereIn('type', ['party_payment', 'party_invoice', 'opening_balance'])->count();
            } elseif ($key === 'expenses') {
                $section['count'] = $this->expenseScopeQuery($companyId)->count();
            } elseif ($key === 'expense_budgets') {
                $section['count'] = $this->budgetScopeQuery($companyId)->count();
            } elseif ($key === 'daily_fund') {
                $section['count'] = $this->dailyFundScopeQuery($companyId)?->count() ?? 0;
            } elseif ($key === 'petty_cash') {
                // Counts the movements, not the floats — the floats survive, so
                // showing them here would promise a deletion that will not happen.
                $section['count'] = DB::table('petty_cash_transactions')
                    ->whereIn('petty_cash_float_id', $this->pettyCashScopeQuery($companyId)->select('id'))
                    ->count();
            } elseif ($section['count_table']) {
                $section['count'] = DB::table($section['count_table'])->count();
            } else {
                $section['count'] = 0;
            }
        }
        unset($section);

        $companies = \App\Models\Company::orderBy('name')->get(['id', 'name', 'short_name']);

        return view('demo-data.index', compact('sections', 'companies', 'companyId'));
    }

    /**
     * The company the expense sections are limited to, or null for "all".
     *
     * Anything that is not a real company id degrades to null rather than
     * erroring — a stale bookmark should show every company, not a 500.
     */
    private function resolveCompanyId($value): ?int
    {
        // is_scalar first: index() reads this straight off the query string,
        // where `?company_id[]=1` arrives as an array and casting one to string
        // is a fatal. A crafted URL should show every company, not a 500.
        if (!is_scalar($value) || $value === '' || !ctype_digit((string) $value)) {
            return null;
        }

        return DB::table('companies')->where('id', (int) $value)->exists() ? (int) $value : null;
    }

    /**
     * Expense rows in scope.
     *
     * Query builder rather than the Expense model on purpose: expenses soft
     * delete, and a demo wipe has to take the trashed rows with it instead of
     * leaving them behind, invisible but still in the table.
     */
    private function expenseScopeQuery(?int $companyId)
    {
        $query = DB::table('expenses');

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    /** Budget rows in scope. Soft-deleting, so same reasoning as above. */
    private function budgetScopeQuery(?int $companyId)
    {
        $query = DB::table('expense_budgets');

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    /**
     * Daily cost fund rows in scope, or null when the table is not there yet.
     *
     * Null rather than an empty query because a deploy puts the code on the
     * server before anyone runs the migration, and this page must not be the one
     * that 500s in that window. DailyFundService guards itself the same way and
     * for the same reason — see its tableReady(). No table means no ceilings to
     * count and none to clear, which is the honest answer, not an error.
     *
     * Query builder rather than the model, like the expense and budget scopes:
     * CompanyDailyFund soft deletes, and a demo wipe that leaves the rows in the
     * table — invisible but still counted by the next `whereNull(deleted_at)` —
     * has not cleared anything.
     */
    private function dailyFundScopeQuery(?int $companyId)
    {
        if (!Schema::hasTable('company_daily_funds')) {
            return null;
        }

        $query = DB::table('company_daily_funds');

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    /**
     * Petty cash floats in scope — including the soft-deleted ones.
     *
     * There are retired floats in the data still carrying a live ledger balance,
     * so skipping them would leave money "in a pocket" nobody can open.
     */
    private function pettyCashScopeQuery(?int $companyId)
    {
        $query = DB::table('petty_cash_floats');

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'sections'   => 'required|array|min:1',
            'sections.*' => 'string|in:' . implode(',', array_keys($this->sections())),
            'company_id' => 'nullable|integer|exists:companies,id',
        ]);

        $selected    = $request->input('sections');
        $allSections = $this->sections();
        $companyId   = $this->resolveCompanyId($request->input('company_id'));
        $companyName = $companyId
            ? DB::table('companies')->where('id', $companyId)->value('name')
            : null;

        $cleared     = [];
        $attachments = [];

        DB::transaction(function () use ($selected, $allSections, $companyId, $companyName, &$cleared, &$attachments) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($selected as $key) {
                $section = $allSections[$key];

                if ($key === 'lead_projects') {
                    $this->clearLeadProjects();
                } elseif ($key === 'party_statement') {
                    $this->clearPartyStatement();
                } elseif ($key === 'portal_accounts') {
                    $this->clearPortalAccounts();
                } elseif ($key === 'expenses') {
                    $attachments = array_merge($attachments, $this->clearExpenses($companyId));
                } elseif ($key === 'expense_budgets') {
                    $this->budgetScopeQuery($companyId)->delete();
                } elseif ($key === 'daily_fund') {
                    $this->clearDailyFund($companyId);
                } elseif ($key === 'petty_cash') {
                    // Merged, not assigned: selecting both this and All Expenses
                    // must not let one section's attachment list overwrite the
                    // other's and leave those files orphaned on disk.
                    $attachments = array_merge($attachments, $this->clearPettyCash($companyId));
                } else {
                    foreach ($section['tables'] as $table) {
                        DB::table($table)->delete();
                    }
                }

                // Only the expense sections honour the company filter, so only
                // they may claim to have been limited by it.
                $cleared[] = $section['label'] . ' – ' . $section['sub']
                    . ($companyName && !empty($section['scoped']) ? ' (' . $companyName . ' only)' : '');
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        });

        // Files go last, and only once the rows are really gone. Unlinking
        // inside the transaction would leave the attachments deleted even when
        // the delete rolled back — the rows would come back and their receipts
        // would not.
        $filesRemoved = $this->removeAttachmentFiles($attachments);

        $message = 'Cleared: ' . implode(', ', $cleared);
        if ($filesRemoved > 0) {
            $message .= '. Removed ' . $filesRemoved . ' attachment file' . ($filesRemoved === 1 ? '' : 's') . '.';
        }

        return redirect()->route('role.demo-data.index', array_filter([
            'role'       => request()->route('role'),
            'company_id' => $companyId,
        ]))->with('success', $message);
    }

    /**
     * Clear expenses and everything that filing one created.
     *
     * An expense is not just its row. Approving one writes a two-sided posting
     * to `journal_entries` with source='expense'
     * (ExpenseController::postJournal), and reversing it writes a second entry
     * under that same source. Deleting the expenses alone — which is what this
     * section used to do — leaves the trial balance still carrying every taka
     * of the spend, and because a custodian's petty cash balance is derived
     * from those very journal rows (PettyCashService::balanceOf), it also
     * leaves every float showing money as spent with no expense behind it.
     * Clearing the postings is what puts the floats back.
     *
     * Returns the attachment paths belonging to the deleted rows so the caller
     * can unlink them after the transaction commits.
     */
    private function clearExpenses(?int $companyId): array
    {
        $attachments = $this->deleteExpenseRows($this->expenseScopeQuery($companyId)->pluck('id'));

        // The claims side goes with them, and it is not optional.
        //
        // An expense marked "paid by staff" CREDITS 2240 Employee Expense
        // Reimbursement Payable (ExpenseController::settlementAccountId), and the
        // payment that settles it DEBITS the same account
        // (EmployeeReimbursementService::pay). They are two halves of one story.
        // Clearing the expenses alone — which is what this section used to do —
        // left the debits with nothing to cancel them: on live data that put 2240
        // at -1,700, i.e. the company reading as OWED money BY the staff member it
        // had just paid. The same shape of wrong the petty cash section documents,
        // arrived at from the other end.
        $this->clearReimbursements($companyId);

        // Clearing every company means nothing expense-shaped has an owner left,
        // so sweep the rows whose parent expense had already been hard-deleted
        // out from under them. There are real ones: expense_items still holds
        // line items pointing at expense ids that no longer exist, and without
        // this they survive a "clear everything" untouched.
        //
        // Skipped when a company was named — a row this filter cannot attribute
        // to a company might belong to one of the other eleven, and a scoped
        // clear has no business guessing.
        if ($companyId === null) {
            DB::table('expense_items')
                ->whereNotIn('expense_id', DB::table('expenses')->select('id'))
                ->delete();

            $orphanIds = DB::table('journal_entries')->where('source', 'expense')->pluck('id');

            foreach ($orphanIds->chunk(500) as $chunk) {
                DB::table('journal_items')->whereIn('journal_entry_id', $chunk->all())->delete();
                DB::table('journal_entries')->whereIn('id', $chunk->all())->delete();
            }
        }

        return $attachments;
    }

    /**
     * Clear what was paid back to staff — the "Recently Paid Back" list.
     *
     * Each payment is a row in `expense_reimbursements` plus a posting filed under
     * source='expense_reimbursement', so both go, exactly the way an expense and
     * its posting do.
     *
     * Query builder rather than the model, for the usual reason: the model soft
     * deletes, and reverse() already leaves trashed rows behind — a wipe that
     * skips them clears the list on screen while the ledger keeps every taka.
     *
     * A REVERSED payment is the awkward case and is handled by the same filter:
     * reverse() deletes the payment row but keeps BOTH postings, and files the
     * reversal under the same source with the same source_id, so one `whereIn`
     * catches the pair. What it cannot catch is a pair whose payment row was hard
     * deleted long ago — those are swept below, but only on an unscoped clear,
     * since a row with no owner may belong to a company that was not named.
     */
    private function clearReimbursements(?int $companyId): void
    {
        if (!Schema::hasTable('expense_reimbursements')) {
            return;
        }

        $query = DB::table('expense_reimbursements');

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        foreach ($query->pluck('id')->chunk(500) as $chunk) {
            $this->deleteJournalEntries(
                DB::table('journal_entries')
                    ->where('source', 'expense_reimbursement')
                    ->whereIn('source_id', $chunk->all())
                    ->pluck('id')
            );

            DB::table('expense_reimbursements')->whereIn('id', $chunk->all())->delete();
        }

        if ($companyId === null) {
            $this->deleteJournalEntries(
                DB::table('journal_entries')->where('source', 'expense_reimbursement')->pluck('id')
            );
        }
    }

    /** Delete journal entries and their items together, in chunks. */
    private function deleteJournalEntries(\Illuminate\Support\Collection $journalIds): void
    {
        foreach ($journalIds->chunk(500) as $chunk) {
            DB::table('journal_items')->whereIn('journal_entry_id', $chunk->all())->delete();
            DB::table('journal_entries')->whereIn('id', $chunk->all())->delete();
        }
    }

    /**
     * Delete a given set of expenses and everything hanging off them.
     *
     * Shared by the expenses section and the petty cash one. A petty-cash-settled
     * expense has to come out exactly the way an ordinary one does — pulling it
     * apart in two places is how the two would quietly start disagreeing about
     * whether the journal goes with it.
     *
     * Returns the attachment paths of the rows it removed.
     */
    private function deleteExpenseRows(\Illuminate\Support\Collection $expenseIds): array
    {
        $attachments = [];

        foreach ($expenseIds->chunk(500) as $chunk) {
            $ids = $chunk->all();

            $attachments = array_merge($attachments, DB::table('expenses')
                ->whereIn('id', $ids)
                ->whereNotNull('attachment')
                ->where('attachment', '<>', '')
                ->pluck('attachment')
                ->all());

            // The original posting and its reversal together — reverse() files
            // the reversal under source='expense' as well, so one filter
            // catches the pair and neither half is left stranded.
            $journalIds = DB::table('journal_entries')
                ->where('source', 'expense')
                ->whereIn('source_id', $ids)
                ->pluck('id');

            if ($journalIds->isNotEmpty()) {
                DB::table('journal_items')->whereIn('journal_entry_id', $journalIds)->delete();
                DB::table('journal_entries')->whereIn('id', $journalIds)->delete();
            }

            DB::table('expense_items')->whereIn('expense_id', $ids)->delete();
            DB::table('expenses')->whereIn('id', $ids)->delete();
        }

        return $attachments;
    }

    /**
     * Clear the spending ceilings, and only those.
     *
     * The simplest section on this page, and deliberately so: a daily cost fund
     * is a PERMISSION, not a transaction. It has no debit, no credit and no
     * account — DailyFundService writes no journal entry at all — so unlike the
     * expense and petty cash sections there is nothing to unwind alongside it.
     * Deleting the rows is the whole job.
     *
     * What each day was actually SPENT is untouched, because it was never stored
     * here: it is read from `expenses` every time. So clearing this removes the
     * ceilings and leaves the history of the spending intact — every company
     * simply reads "No daily fund set" again, exactly as it did before one was
     * configured.
     */
    private function clearDailyFund(?int $companyId): void
    {
        $this->dailyFundScopeQuery($companyId)?->delete();
    }

    /**
     * Clear what the custodians were handed, without clearing the custodians.
     *
     * The float ROWS are kept on purpose, the same call `portal_accounts` makes:
     * a float is (company, custodian, account, imprest limit) — that is setup
     * somebody configured, not demo data. What gets cleared is the money: the
     * issues, the returns and their postings.
     *
     * Nothing needs resetting afterwards. A float's balance is derived from the
     * ledger rather than stored (PettyCashService::balanceOf is Dr − Cr on the
     * float account for that custodian), so once the postings are gone every
     * pocket reads zero on its own. That is precisely why there is no balance
     * column here to zero out the way portals needed.
     *
     * The spend leg has to go with it. An expense settled from a pocket CREDITS
     * the float account (ExpenseController::settlementAccountId), so deleting the
     * issues while leaving those expenses would leave credits with no matching
     * debits and every affected custodian reading as holding LESS than nothing —
     * the mirror image of the bug this whole section exists to fix.
     */
    private function clearPettyCash(?int $companyId): array
    {
        $floatIds = $this->pettyCashScopeQuery($companyId)->pluck('id');

        // Expenses paid out of those pockets, removed the same way the expenses
        // section removes any other.
        $attachments = $this->deleteExpenseRows(
            DB::table('expenses')->whereIn('petty_cash_float_id', $floatIds)->pluck('id')
        );

        $transactionIds = DB::table('petty_cash_transactions')
            ->whereIn('petty_cash_float_id', $floatIds)
            ->pluck('id');

        foreach ($transactionIds->chunk(500) as $chunk) {
            $journalIds = DB::table('journal_entries')
                ->where('source', 'petty_cash')
                ->whereIn('source_id', $chunk->all())
                ->pluck('id');

            if ($journalIds->isNotEmpty()) {
                DB::table('journal_items')->whereIn('journal_entry_id', $journalIds)->delete();
                DB::table('journal_entries')->whereIn('id', $journalIds)->delete();
            }
        }

        DB::table('petty_cash_transactions')->whereIn('petty_cash_float_id', $floatIds)->delete();

        // Same reasoning as the expense sweep: with no company named, nothing
        // petty-cash-shaped has an owner left worth preserving, so take the rows
        // whose float or transaction had already been hard-deleted out from under
        // them. Skipped when scoped — an unattributable row may be another
        // company's.
        if ($companyId === null) {
            DB::table('petty_cash_transactions')
                ->whereNotIn('petty_cash_float_id', DB::table('petty_cash_floats')->select('id'))
                ->delete();

            $orphanIds = DB::table('journal_entries')->where('source', 'petty_cash')->pluck('id');

            foreach ($orphanIds->chunk(500) as $chunk) {
                DB::table('journal_items')->whereIn('journal_entry_id', $chunk->all())->delete();
                DB::table('journal_entries')->whereIn('id', $chunk->all())->delete();
            }
        }

        return $attachments;
    }

    /**
     * Unlink expense attachments, and nothing else.
     *
     * The paths come out of the database, so they are treated as untrusted:
     * a file is removed only when it resolves to a plain file sitting directly
     * inside the one directory the expense uploader writes to
     * (`public/image/expense/`). A row carrying `../../.env` in its attachment
     * column resolves outside that directory and is skipped.
     */
    private function removeAttachmentFiles(array $paths): int
    {
        if (empty($paths)) {
            return 0;
        }

        $baseDir = realpath(public_path('image/expense'));

        if ($baseDir === false) {
            return 0;
        }

        $removed = 0;

        foreach (array_unique($paths) as $path) {
            $full = realpath(public_path($path));

            if ($full === false || !is_file($full) || dirname($full) !== $baseDir) {
                continue;
            }

            if (@unlink($full)) {
                $removed++;
            }
        }

        return $removed;
    }

    private function clearPartyStatement(): void
    {
        // Only manually-created party-statement entries — ticket_sale/visa_sale/etc.
        // ledger rows are owned by their own modules and cleared with those sections.
        DB::table('transactions')->whereIn('type', ['party_payment', 'party_invoice', 'opening_balance'])->delete();
        DB::table('party_invoices')->delete();
    }

    private function clearPortalAccounts(): void
    {
        // Portals are configured vendor accounts (name, API credentials,
        // vendor link) — that's setup, not demo data, so the records are
        // kept. Only their balance and transaction ledger get wiped.
        //
        // Portal top-up/opening-balance/opening-usage entries are posted with
        // source = 'portal_balance' — only remove those, not the whole journal
        // (mirrors clearPartyStatement()'s scoped-by-type approach).
        $journalIds = DB::table('journal_entries')->where('source', 'portal_balance')->pluck('id');

        if ($journalIds->isNotEmpty()) {
            DB::table('journal_items')->whereIn('journal_entry_id', $journalIds)->delete();
            DB::table('journal_entries')->whereIn('id', $journalIds)->delete();
        }

        DB::table('portal_balances')->delete();
        DB::table('portals')->update(['balance' => 0]);
    }

    private function clearLeadProjects(): void
    {
        $projectIds = DB::table('projects')->where('type', 'lead_project')->pluck('id');

        if ($projectIds->isEmpty()) {
            return;
        }

        // Get board IDs for these projects
        $boardIds = DB::table('boards')->whereIn('project_id', $projectIds)->pluck('id');

        // Get column IDs
        $columnIds = DB::table('columns')->whereIn('board_id', $boardIds)->pluck('id');

        // Get task IDs in these columns
        $taskIds = DB::table('tasks')->whereIn('column_id', $columnIds)->pluck('id');

        if ($taskIds->isNotEmpty()) {
            DB::table('task_links')->whereIn('task_id', $taskIds)->orWhereIn('linked_task_id', $taskIds)->delete();
            DB::table('label_task')->whereIn('task_id', $taskIds)->delete();
            DB::table('task_user')->whereIn('task_id', $taskIds)->delete();
            DB::table('task_attachments')->whereIn('task_id', $taskIds)->delete();
            DB::table('task_activity_logs')->whereIn('task_id', $taskIds)->delete();
            DB::table('task_comments')->whereIn('task_id', $taskIds)->delete();
            DB::table('tasks')->whereIn('id', $taskIds)->delete();
        }

        if ($columnIds->isNotEmpty()) {
            DB::table('columns')->whereIn('id', $columnIds)->delete();
        }

        if ($boardIds->isNotEmpty()) {
            DB::table('board_columns')->whereIn('board_id', $boardIds)->delete();
            DB::table('boards')->whereIn('id', $boardIds)->delete();
        }

        DB::table('projects')->whereIn('id', $projectIds)->delete();
    }
}
