<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Bank;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Loan;
use App\Models\LoanTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Records movements on staff loans — the money side and the ledger side together.
 *
 * Disbursement was already posting a journal from LoanController; repayment had
 * nowhere to go at all, so a loan could only ever be "reduced" by editing
 * remaining_amount by hand. Everything now goes through here so the transaction
 * row and its journal entry are always created in the same database transaction:
 * a repayment can't exist without its posting, and vice versa.
 */
class LoanLedgerService
{
    /**
     * The money going out — one row per loan, written the moment it is disbursed.
     *
     * The journal for a disbursement is posted by LoanController itself (it has
     * been for a while and other screens read it), so this only records the
     * movement and points at that posting. Idempotent: called again for the same
     * loan it corrects the existing row rather than adding a second disbursement,
     * which is what makes it safe to call from update() and from the backfill.
     */
    public function recordDisbursement(Loan $loan, ?int $journalEntryId = null): LoanTransaction
    {
        $method = $loan->bank_id ? 'bank' : 'cash';
        $accountId = $loan->bank_id ? Bank::find($loan->bank_id)?->account_id : null;

        $attributes = [
            'user_id'          => $loan->user_id,
            'method'           => $method,
            'amount'           => $loan->amount,
            'date'             => $loan->start_date,
            'account_id'       => $accountId,
            'bank_id'          => $loan->bank_id,
            'journal_entry_id' => $journalEntryId,
            'note'             => 'Staff loan disbursed',
        ];

        $existing = LoanTransaction::where('loan_id', $loan->id)->where('type', 'disburse')->first();

        if ($existing) {
            // A journal id is only overwritten when a new one is handed in — an
            // edit that doesn't repost must not blank the link to the posting.
            if ($journalEntryId === null) {
                unset($attributes['journal_entry_id']);
            }

            $existing->update($attributes);

            return $existing;
        }

        return LoanTransaction::create($attributes + [
            'loan_id'    => $loan->id,
            'type'       => 'disburse',
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Money coming back from the employee.
     *
     * @param  string  $method  salary | cash | bank
     */
    public function recordRepayment(
        Loan $loan,
        float $amount,
        string $method = 'cash',
        ?string $date = null,
        ?int $bankId = null,
        ?string $note = null,
        ?int $employeeSalaryId = null
    ): LoanTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Repayment amount must be greater than zero.');
        }

        // Never let a repayment take the balance below zero — an over-payment is
        // almost always a typo, and silently accepting it makes the register lie.
        //
        // A settled loan is included, not exempted: outstanding is derived from
        // the rows now, so nought means nought is owed. The guard used to run
        // only when outstanding was above zero, which let a cleared loan quietly
        // go on collecting and show as more-than-repaid in its own register.
        $outstanding = $loan->outstanding;

        if ($amount > $outstanding + 0.01) {
            throw new \InvalidArgumentException(
                $outstanding <= 0.01
                    ? 'This loan is settled in full — there is nothing left to collect on it.'
                    : 'Repayment (৳' . number_format($amount, 2) . ') is more than the ৳'
                        . number_format($outstanding, 2) . ' still outstanding on this loan.'
            );
        }

        $date = $date ?: now()->toDateString();

        return DB::transaction(function () use ($loan, $amount, $method, $date, $bankId, $note, $employeeSalaryId) {
            [$journal, $accountId] = $this->postRepaymentJournal($loan, $amount, $method, $date, $bankId, $note);

            $transaction = LoanTransaction::create([
                'loan_id'            => $loan->id,
                'user_id'            => $loan->user_id,
                'type'               => 'repay',
                'method'             => $method,
                'amount'             => $amount,
                'date'               => $date,
                'account_id'         => $accountId,
                'bank_id'            => $method === 'salary' ? null : $bankId,
                'employee_salary_id' => $employeeSalaryId,
                'journal_entry_id'   => $journal?->id,
                'note'               => $note,
                'created_by'         => Auth::id(),
            ]);

            $this->syncLoanBalance($loan);

            return $transaction;
        });
    }

    /**
     * The EMI a payslip withheld, recorded as a repayment on the loan.
     *
     * Payroll's rules differ from the manual form's in two ways, which is why it
     * doesn't call recordRepayment() directly:
     *  · an EMI larger than what is left collects only what is left, instead of
     *    throwing and taking the whole salary run down with it;
     *  · re-running the same payslip replaces its deduction rather than stacking
     *    a second one, so a corrected salary can never charge the employee twice.
     *
     * Returns null when there was nothing to collect.
     */
    public function recordSalaryDeduction(
        Loan $loan,
        float $amount,
        string $date,
        ?int $employeeSalaryId = null,
        ?string $note = null
    ): ?LoanTransaction {
        if ($employeeSalaryId) {
            $this->reverseForSalary($employeeSalaryId);
            $loan->refresh();
        }

        $collectable = min(round($amount, 2), $loan->outstanding);

        if ($collectable <= 0) {
            return null;
        }

        return $this->recordRepayment(
            $loan,
            $collectable,
            'salary',
            $date,
            null,
            $note,
            $employeeSalaryId
        );
    }

    /**
     * Undo whatever a payslip took off a loan — used when that salary row is
     * deleted or regenerated.
     *
     * Without this a deleted salary would leave its EMI standing in the loan
     * register as money the employee had repaid, against a payslip that no
     * longer exists.
     */
    public function reverseForSalary(int $employeeSalaryId): int
    {
        $rows = LoanTransaction::where('employee_salary_id', $employeeSalaryId)->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($rows) {
            $loanIds = $rows->pluck('loan_id')->unique();

            foreach ($rows as $row) {
                // A salary-deducted EMI carries no journal of its own (payroll's
                // own posting covers it), but a row that somehow has one must
                // take it with it rather than leave an orphan posting behind.
                if ($row->journal_entry_id) {
                    $journal = JournalEntry::find($row->journal_entry_id);
                    $journal?->items()->forceDelete();
                    $journal?->forceDelete();
                }

                $row->delete();
            }

            foreach (Loan::whereIn('id', $loanIds)->get() as $loan) {
                $this->syncLoanBalance($loan);
            }

            return $rows->count();
        });
    }

    /**
     * Honour the "Remaining Amount" the disburse/edit form was filled in with.
     *
     * That field predates the transaction rows and used to BE the balance. Now
     * that the balance is derived, a figure typed there can only mean one thing:
     * this much of the loan came back before the system was recording payments.
     * So it is stored as opening_paid_amount — the one place a repayment with no
     * row behind it belongs — and the derived balance then agrees with what the
     * user typed instead of silently overwriting it.
     *
     * A blank field means "nothing repaid yet" for a new loan and is left alone
     * on an edit, so an untouched form never rewrites history.
     */
    public function applyStatedRemaining(Loan $loan, $statedRemaining): void
    {
        if ($statedRemaining === null || $statedRemaining === '') {
            return;
        }

        $recorded = (float) $loan->repayments()->sum('amount');
        $opening = round((float) $loan->amount - (float) $statedRemaining - $recorded, 2);

        $loan->update(['opening_paid_amount' => max(0, $opening)]);
    }

    /**
     * Keep loans.remaining_amount and status in step with the transaction rows.
     *
     * The column stays because plenty of existing screens read it directly; it is
     * now a cache of the rows rather than the source of truth.
     */
    public function syncLoanBalance(Loan $loan): void
    {
        $loan->load('transactions');

        // paid_amount already folds in opening_paid_amount, so an older loan's
        // pre-existing repayments survive the first recorded movement.
        $remaining = max(0, round((float) $loan->amount - $loan->paid_amount, 2));

        $loan->update([
            'remaining_amount' => $remaining,
            'status'           => $remaining <= 0.01 ? 'Completed' : 'Running',
        ]);
    }

    /**
     * Dr cash/bank (or salary payable) · Cr loan receivable — the employee owes
     * us less than they did.
     *
     * A salary-deducted EMI is posted by the payroll flow itself against the same
     * payslip, so recording it twice here would double-count; that method returns
     * without a journal and the row is kept purely as loan history.
     */
    private function postRepaymentJournal(
        Loan $loan,
        float $amount,
        string $method,
        string $date,
        ?int $bankId,
        ?string $note
    ): array {
        $loanReceivable = Account::where('code', config('accounts.loan_receivable'))->first();

        if (! $loanReceivable) {
            throw new \RuntimeException('Loan receivable account (' . config('accounts.loan_receivable') . ') is missing from the chart of accounts.');
        }

        if ($method === 'salary') {
            return [null, null];
        }

        $debitAccountId = null;

        if ($bankId) {
            $bank = Bank::find($bankId);
            if (! $bank || ! $bank->account_id) {
                throw new \RuntimeException('That account is not linked to the chart of accounts.');
            }
            $debitAccountId = $bank->account_id;
        } else {
            $cash = Account::where('code', config('accounts.office_cash'))->first();
            if (! $cash) {
                throw new \RuntimeException('Office cash account (' . config('accounts.office_cash') . ') is missing from the chart of accounts.');
            }
            $debitAccountId = $cash->id;
        }

        $journal = JournalEntry::create([
            'company_id'  => $loan->user->company_id ?? Auth::user()->company_id ?? null,
            'created_by'  => Auth::id(),
            'date'        => $date,
            'reference'   => 'LREP-' . str_pad($loan->id, 5, '0', STR_PAD_LEFT) . '-' . now()->format('ymdHis'),
            'source'      => 'loan_repayment',
            'source_id'   => $loan->id,
            'description' => $note ?: ('Loan repayment — ' . ($loan->user->name ?? 'employee #' . $loan->user_id)),
        ]);

        JournalItem::create([
            'journal_entry_id' => $journal->id,
            'account_id'       => $debitAccountId,
            'debit'            => $amount,
            'credit'           => 0,
            'note'             => 'Loan repayment received',
        ]);

        JournalItem::create([
            'journal_entry_id' => $journal->id,
            'account_id'       => $loanReceivable->id,
            'debit'            => 0,
            'credit'           => $amount,
            'note'             => 'Loan receivable reduced',
        ]);

        return [$journal, $debitAccountId];
    }
}
