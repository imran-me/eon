<?php

namespace App\Traits;

use App\Models\Account;
use App\Models\Bank;
use App\Models\JournalEntry;
use App\Models\JournalItem;

/**
 * Posts the double-entry chart-of-accounts journal for a salary-payable-side
 * record (debit Salary Expense for the full amount, credit either the paying
 * bank or Salary Payable). Mirrors PostsSaleJournal / PostsPurchaseJournal's
 * shape, but kept as its own trait rather than reusing PostsPurchaseJournal
 * directly — that trait hardcodes the payable side to accounts_payable
 * (2110, the vendor-payable account), which would misfile every unpaid
 * salary into the wrong liability account. Salary always needs its own
 * fixed accounts (salary_expense / salary_payable).
 *
 * Used both by real salary rows (source 'salary', tied to an EmployeeSalary
 * id) and by one-off employee-ledger events like bonuses (source
 * 'employee_ledger', tied to an EmployeeLedger id) — same accounting shape,
 * different source table, hence $source/$sourceId being caller-supplied
 * rather than hardcoded.
 */
trait PostsSalaryJournal
{
    /**
     * Create the journal entry for a newly created salary-payable-side record.
     */
    protected function createSalaryJournal(
        string $source,
        int $sourceId,
        ?int $companyId,
        $date,
        string $reference,
        string $description,
        float $amount,
        bool $isPaid,
        ?int $bankId,
        string $amountLabel = 'Net salary'
    ): JournalEntry {
        $journal = JournalEntry::create([
            'company_id' => $companyId,
            'created_by' => auth()->id(),
            'date' => $date,
            'reference' => $reference,
            'source' => $source,
            'source_id' => $sourceId,
            'description' => $description,
        ]);

        $this->syncSalaryJournalItems($journal, $reference, $amount, $isPaid, $bankId, $amountLabel);

        return $journal;
    }

    /**
     * Remove a salary-payable-side journal entry entirely (e.g. on delete).
     */
    protected function deleteSalaryJournal(string $source, int $sourceId): void
    {
        $journal = JournalEntry::where('source', $source)->where('source_id', $sourceId)->first();
        if ($journal) {
            $journal->items()->forceDelete();
            $journal->forceDelete();
        }
    }

    private function syncSalaryJournalItems(JournalEntry $journal, string $reference, float $amount, bool $isPaid, ?int $bankId, string $amountLabel): void
    {
        $salaryExpenseAccount = Account::where('code', config('accounts.salary_expense'))->firstOrFail();

        JournalItem::create([
            'journal_entry_id' => $journal->id,
            'account_id' => $salaryExpenseAccount->id,
            'debit' => $amount,
            'credit' => 0,
            'note' => $amountLabel . ' — ' . $reference,
        ]);

        if ($isPaid && $bankId) {
            $bank = Bank::find($bankId);
            if (!$bank || !$bank->account_id) {
                throw new \Exception('Bank is not linked to a chart-of-accounts account.');
            }
            JournalItem::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $bank->account_id,
                'debit' => 0,
                'credit' => $amount,
                'note' => $amountLabel . ' paid — ' . $reference,
            ]);
        } else {
            $salaryPayableAccount = Account::where('code', config('accounts.salary_payable'))->firstOrFail();
            JournalItem::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $salaryPayableAccount->id,
                'debit' => 0,
                'credit' => $amount,
                'note' => $amountLabel . ' payable — ' . $reference,
            ]);
        }
    }
}
