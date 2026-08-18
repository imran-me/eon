<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Bank;
use App\Models\ExpenseReimbursement;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What the company owes its staff for money they spent themselves, and paying it.
 *
 * The mirror of PettyCashService. That one tracks company cash sitting in a
 * pocket (an asset); this tracks a pocket the company has emptied and has not
 * refilled (a liability). Both separate one person from another by party_id on
 * the journal item rather than by an account each, and both read every balance
 * back from the ledger instead of storing one.
 */
class EmployeeReimbursementService
{
    /**
     * The account that carries what is owed to everyone.
     *
     * Thrown rather than defaulted, unlike PettyCashService::cashPotAccount().
     * A missing cash pot has a sane fallback — office cash is still cash. A
     * missing payable has none: posting a claim anywhere else would either
     * pretend company money moved or bury a debt in an unrelated account.
     */
    public function payableAccount(): Account
    {
        $code = config('accounts.employee_reimbursement_payable');

        $account = Account::where('code', $code)->where('status', 1)->first();

        if (!$account) {
            throw new \RuntimeException(
                'The employee reimbursement account (' . ($code ?: 'not configured') . ') is missing from '
                . 'the chart of accounts, so staff claims cannot be recorded or paid. '
                . 'Run php artisan accounts:check.'
            );
        }

        return $account;
    }

    /**
     * What one person is still owed, in one company's books.
     *
     * Credit minus debit: approving a claim credits this account, paying it back
     * debits it, so the difference is what is left. The soft-delete checks are
     * explicit because this is a raw join and no global scope reaches it — the
     * same trap PettyCashService::balancesFor() documents, where a reversed entry
     * would otherwise still count.
     */
    public function owedTo(int $userId, ?int $companyId = null): float
    {
        return (float) $this->ledgerQuery()
            ->where('ji.party_id', $userId)
            ->when($companyId, fn ($q) => $q->where('je.company_id', $companyId))
            ->selectRaw('COALESCE(SUM(ji.credit) - SUM(ji.debit), 0) as owed')
            ->value('owed');
    }

    /**
     * Everyone still owed something, one row per person per company.
     *
     * Per COMPANY as well as per person, because a claim filed under one
     * company's books has to be settled out of that company's cash. Rolling them
     * into a single figure would invite paying EPAL IT's debt from Travels' till.
     */
    public function outstanding(?int $companyId = null): Collection
    {
        return $this->ledgerQuery()
            ->join('users as u', 'u.id', '=', 'ji.party_id')
            ->join('companies as c', 'c.id', '=', 'je.company_id')
            ->when($companyId, fn ($q) => $q->where('je.company_id', $companyId))
            ->groupBy('ji.party_id', 'u.name', 'je.company_id', 'c.name')
            ->havingRaw('COALESCE(SUM(ji.credit) - SUM(ji.debit), 0) > 0.001')
            ->orderByDesc('owed')
            ->get([
                'ji.party_id as user_id',
                'u.name as user_name',
                'je.company_id',
                'c.name as company_name',
                DB::raw('COALESCE(SUM(ji.credit) - SUM(ji.debit), 0) as owed'),
            ]);
    }

    /**
     * Pay someone back.
     *
     * Two refusals, both about money that is not there:
     *
     *   - more than they are owed, which would leave the company owing MINUS
     *     something and read as staff owing the company;
     *   - more than the paying account holds, the same rule that stops a petty
     *     cash float being issued out of an empty drawer.
     */
    public function pay(array $data): ExpenseReimbursement
    {
        $amount    = round((float) $data['amount'], 2);
        $userId    = (int) $data['user_id'];
        $companyId = (int) $data['company_id'];

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be more than zero.');
        }

        $owed = $this->owedTo($userId, $companyId);

        if ($amount > $owed + 0.001) {
            throw new \InvalidArgumentException(
                'Only ' . number_format($owed, 2) . ' is owed here, so '
                . number_format($amount, 2) . ' cannot be paid back. '
                . 'Approve the claim first if a receipt is still waiting.'
            );
        }

        $counterAccountId = $this->counterAccountId($data['bank_id'] ?? null, $companyId);
        $available        = $this->accountBalance($counterAccountId);

        if ($amount > $available + 0.001) {
            $source = Account::find($counterAccountId)?->name ?? 'That account';

            throw new \InvalidArgumentException(
                $source . ' holds ' . number_format($available, 2) . ', so '
                . number_format($amount, 2) . ' cannot be paid out of it. '
                . 'Record the money going into it first, or pay from somewhere else.'
            );
        }

        return DB::transaction(function () use ($data, $amount, $userId, $companyId, $counterAccountId) {
            $payment = ExpenseReimbursement::create([
                'user_id'    => $userId,
                'company_id' => $companyId,
                'amount'     => $amount,
                'bank_id'    => $data['bank_id'] ?? null,
                'paid_on'    => $data['paid_on'],
                'note'       => $data['note'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $name = $payment->user->name ?? 'staff member';

            $journal = JournalEntry::create([
                'company_id'  => $companyId,
                'created_by'  => auth()->id(),
                'date'        => $data['paid_on'],
                'reference'   => 'REIMB-' . str_pad($payment->id, 5, '0', STR_PAD_LEFT),
                'source'      => 'expense_reimbursement',
                'source_id'   => $payment->id,
                'description' => 'Reimbursed ' . $name . ' for out-of-pocket expenses',
            ]);

            // Debit the payable, and CARRY THE PARTY. Without it the total on the
            // account falls but this person's own balance does not, so they stay
            // owed money that has already been handed to them.
            JournalItem::create([
                'journal_entry_id' => $journal->id,
                'account_id'       => $this->payableAccount()->id,
                'debit'            => $amount,
                'credit'           => 0,
                'note'             => 'Reimbursement paid to ' . $name,
                'party_type'       => 'employee',
                'party_id'         => $userId,
            ]);

            JournalItem::create([
                'journal_entry_id' => $journal->id,
                'account_id'       => $counterAccountId,
                'debit'            => 0,
                'credit'           => $amount,
                'note'             => 'Reimbursement paid to ' . $name,
            ]);

            $payment->update(['journal_entry_id' => $journal->id]);

            return $payment;
        });
    }

    /**
     * Undo a payment by writing the opposite entry, never by deleting the first.
     *
     * The same shape ExpenseController::reverse() uses, and for the same reason:
     * a deleted posting leaves a closed month quietly different from what was
     * reported at the time, while a reversal leaves both facts on the record.
     */
    public function reverse(ExpenseReimbursement $payment): void
    {
        $journal = $payment->journalEntry;

        if (!$journal) {
            throw new \RuntimeException('That payment has no posting to reverse.');
        }

        if (JournalEntry::where('reversed_journal_entry_id', $journal->id)->exists()) {
            throw new \RuntimeException('That payment has already been reversed.');
        }

        DB::transaction(function () use ($payment, $journal) {
            $reversal = JournalEntry::create([
                'company_id'                => $journal->company_id,
                'created_by'                => auth()->id(),
                'date'                      => now()->toDateString(),
                'reference'                 => 'REV-' . ($journal->reference ?: $journal->id),
                'source'                    => 'expense_reimbursement',
                'source_id'                 => $payment->id,
                'description'               => 'Reversal of: ' . $journal->description,
                'reversed_journal_entry_id' => $journal->id,
            ]);

            foreach ($journal->items as $item) {
                JournalItem::create([
                    'journal_entry_id' => $reversal->id,
                    'account_id'       => $item->account_id,
                    'debit'            => $item->credit,
                    'credit'           => $item->debit,
                    'note'             => 'Reversal — ' . ($item->note ?: $journal->description),
                    'party_type'       => $item->party_type,
                    'party_id'         => $item->party_id,
                ]);
            }

            // The payment row goes, its postings stay. Both entries remain on the
            // ledger and the person's balance returns to what it was.
            $payment->delete();
        });
    }

    /**
     * Where the money leaves from: a named bank, or the cash pot.
     *
     * Resolved through PettyCashService so a reimbursement paid in cash credits
     * exactly the account a cash expense would — the two cannot disagree about
     * where the company's cash lives.
     */
    private function counterAccountId(?int $bankId, int $companyId): int
    {
        if (!$bankId) {
            return app(PettyCashService::class)->cashPotAccount()->id;
        }

        $bank = Bank::find($bankId);

        if (!$bank || !$bank->account_id) {
            throw new \InvalidArgumentException('That bank is not linked to a chart-of-accounts account.');
        }

        if ((int) $bank->company_id !== $companyId) {
            throw new \InvalidArgumentException(
                'That bank belongs to a different company than the claim being settled.'
            );
        }

        return (int) $bank->account_id;
    }

    /** Postings on the payable account, with both soft-delete checks applied. */
    private function ledgerQuery()
    {
        return DB::table('journal_items as ji')
            ->join('journal_entries as je', 'je.id', '=', 'ji.journal_entry_id')
            ->where('ji.account_id', $this->payableAccount()->id)
            ->where('ji.party_type', 'employee')
            ->whereNull('ji.deleted_at')
            ->whereNull('je.deleted_at');
    }

    private function accountBalance(int $accountId): float
    {
        return (float) DB::table('journal_items as ji')
            ->join('journal_entries as je', 'je.id', '=', 'ji.journal_entry_id')
            ->where('ji.account_id', $accountId)
            ->whereNull('ji.deleted_at')
            ->whereNull('je.deleted_at')
            ->selectRaw('COALESCE(SUM(ji.debit) - SUM(ji.credit), 0) as balance')
            ->value('balance');
    }
}
