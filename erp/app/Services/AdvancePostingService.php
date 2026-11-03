<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AdvanceSalary;
use App\Models\Bank;
use App\Models\EmployeeSalary;
use App\Models\JournalEntry;
use App\Traits\PostsBalancedJournal;
use App\Traits\PostsEmployeeLedger;
use App\Models\EmployeeLedger;
use Illuminate\Support\Collection;

/**
 * The money side of an advance salary — owner's instruction, 2026-09-27:
 * approving an advance must pick the bank it leaves from and the account it
 * goes to, so it reaches the bank ledger, the journal and every report built
 * on them.
 *
 * Where it sits: an advance is money the employee owes back out of future
 * salary, so it is a CURRENT ASSET — Salary Advance (1458), under Advances,
 * Deposits & Prepayments › Advances & Loans, beside Employee Loan (1457).
 *
 *   Approve (release)   Dr Salary Advance      Cr Bank / Cash
 *   Salary deducts it   Dr Salary Expense      Cr Salary Advance
 *
 * The recovery is its own entry (source advance_recovery, one per salary row)
 * rather than a line inside the salary journal: nine different places write
 * salary journals and every one of them posts the NET salary, so the withheld
 * part is exactly what is missing from Salary Expense. Posting it beside them
 * brings expense up to what was earned and clears the asset, without touching
 * any of the nine.
 *
 * The employee's own ledger (employee_ledger) is deliberately untouched: it
 * carries salary earned NET of the advance, so a separate advance row there
 * would count the same money twice.
 *
 * Advances approved before this existed have no release entry. They are left
 * exactly as they were — no retro-posting on re-save, and no recovery entry
 * for them either, because crediting an asset that was never debited would
 * drive it negative.
 */
class AdvancePostingService
{
    use PostsBalancedJournal, PostsEmployeeLedger;

    public const SOURCE_RELEASE  = 'advance_salary';
    public const SOURCE_RECOVERY = 'advance_recovery';
    public const SOURCE_REVERSAL = 'advance_salary_reversal';

    /** Cancel an erroneous posting, never delete the original accounting trail. */
    public function reverse(AdvanceSalary $advance, string $date, string $reason): JournalEntry
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($advance, $date, $reason) {
            $advance = AdvanceSalary::whereKey($advance->id)->lockForUpdate()->firstOrFail();
            if (trim($reason) === '') {
                throw new \RuntimeException('A reversal reason is required.');
            }
            $journal = $this->releaseJournal($advance);
            if (!$journal || !$advance->is_released) {
                throw new \RuntimeException('This advance has no active released payment to reverse.');
            }
            if (JournalEntry::where('reversed_journal_entry_id', $journal->id)->exists()) {
                throw new \RuntimeException('This advance has already been reversed.');
            }
            if ($date < $journal->date->toDateString() || $date > now()->toDateString()) {
                throw new \RuntimeException('Reversal date must be between the payment date and today.');
            }
            if ($advance->recovered_amount > 0 || $advance->recoveries()->exists()) {
                throw new \RuntimeException('Payroll has already deducted this advance. Correct the payroll deduction before reversing it.');
            }
            $items = $journal->items()->get();
            if ($items->isEmpty() || abs($items->sum('debit') - $items->sum('credit')) > 0.001) {
                throw new \RuntimeException('The original journal is empty or unbalanced; accountant review is required.');
            }
            $reversal = JournalEntry::create([
                'company_id' => $journal->company_id,
                'created_by' => auth()->id(),
                'date' => $date,
                'reference' => 'REV-ADV-' . $advance->id . '-JE-' . $journal->id,
                'source' => self::SOURCE_REVERSAL,
                'source_id' => $advance->id,
                'reversed_journal_entry_id' => $journal->id,
                'description' => 'Reverse advance ' . $journal->reference . ': ' . trim($reason),
                'beneficiary' => $journal->beneficiary,
                'beneficiary_party_id' => $journal->beneficiary_party_id,
                'beneficiary_method' => $journal->beneficiary_method,
                'beneficiary_bank' => $journal->beneficiary_bank,
                'beneficiary_ref' => $journal->beneficiary_ref,
            ]);
            foreach ($items as $item) {
                $reversal->items()->create([
                    'account_id' => $item->account_id,
                    'debit' => $item->credit,
                    'credit' => $item->debit,
                    'party_type' => $item->party_type,
                    'party_id' => $item->party_id,
                    'note' => 'Advance reversal: ' . trim($reason),
                ]);
            }
            $advance->forceFill(['status' => 'Rejected', 'payment_status' => 'Unpaid'])->save();
            return $reversal;
        });
    }

    /** Code of the account an advance is debited to by default. */
    public static function defaultAccountCode(): string
    {
        return (string) config('accounts.salary_advance', '1458');
    }

    /**
     * Accounts an advance may be debited to: the asset accounts under
     * Advances, Deposits & Prepayments (1450), leaves only, so a wrong pick
     * is still an asset and never an expense, a bank or a group heading.
     */
    public function contraAccounts(): Collection
    {
        $assets = Account::where('type', 'asset')->where('status', 1)
            ->orderBy('code')->get(['id', 'code', 'name', 'parent_id']);
        $root = $assets->firstWhere('code', (string) config('accounts.advances_group', '1450'));
        if (! $root) {
            return $assets->where('code', self::defaultAccountCode())->values();
        }

        $byParent = $assets->groupBy('parent_id');
        $out = collect();
        $walk = function ($id) use (&$walk, $byParent, &$out) {
            foreach ($byParent->get($id, collect()) as $child) {
                $out->push($child);
                $walk($child->id);
            }
        };
        $walk($root->id);

        // Leaves only: a group (1455, 1470) is a heading, not a place to post.
        return $out->reject(fn ($a) => $byParent->has($a->id))->sortBy('code')->values();
    }

    /** Banks and cash cards the money can leave from. */
    public function banks(): Collection
    {
        return Bank::where('status', 1)->whereNotNull('account_id')->orderBy('name')->get();
    }

    public function releaseJournal(AdvanceSalary $advance): ?JournalEntry
    {
        return JournalEntry::where('source', self::SOURCE_RELEASE)
            ->where('source_id', $advance->id)
            ->first();
    }

    /**
     * Pay the advance out: Dr the chosen advance account, Cr the bank. Marks it
     * released on the same date. Call inside the caller's DB transaction.
     */
    public function release(
        AdvanceSalary $advance,
        int $bankId,
        int $contraAccountId,
        string $date,
        ?string $time = null,
        ?string $paymentMethod = null,
        ?string $recipientBank = null,
        ?string $recipientReference = null,
        ?string $reference = null
    ): JournalEntry
    {
        if ($this->releaseJournal($advance)) {
            throw new \RuntimeException('This advance has already been paid out.');
        }

        $bank = Bank::find($bankId);
        if (! $bank || ! $bank->account_id) {
            throw new \RuntimeException('Pick the bank or cash account the advance was paid from.');
        }

        $contra = $this->contraAccounts()->firstWhere('id', $contraAccountId);
        if (! $contra) {
            throw new \RuntimeException('Pick the account the advance is recorded in (Salary Advance).');
        }
        if ((int) $contra->id === (int) $bank->account_id) {
            throw new \RuntimeException('The advance account and the bank cannot be the same account.');
        }

        $amount = round((float) $advance->amount, 2);
        if ($amount <= 0) {
            throw new \RuntimeException('The advance amount must be more than zero.');
        }

        // The money is leaving this bank now, so it has to be there.
        Bank::assertCanCover((int) $bank->account_id, $amount, $bank->name);

        $advance->loadMissing('user');
        $name = $advance->user->name ?? 'Employee';
        if ($bank->company_id && $advance->user?->company_id
            && (int) $bank->company_id !== (int) $advance->user->company_id) {
            throw new \RuntimeException('Choose a company account that belongs to the employee\'s company.');
        }

        $journalReference = trim((string) $reference) ?: 'ADV-' . str_pad((string) $advance->id, 5, '0', STR_PAD_LEFT);

        $journal = $this->postBalancedJournal(
            self::SOURCE_RELEASE,
            $advance->id,
            $advance->user->company_id ?? auth()->user()?->company_id ?? 2,
            $date,
            $journalReference,
            'Advance salary paid — ' . $name,
            (int) $contra->id,
            (int) $bank->account_id,
            $amount,
            'Advance salary given to ' . $name,
            'Advance salary paid from ' . $bank->name,
            'employee',
            (int) $advance->user_id,
            $time ? substr($time, 0, 5) . ':00' : null,
            $name,
            (int) $advance->user_id,
            match ($paymentMethod) {
                'cash' => 'cash',
                'cheque' => 'cheque',
                'bank_transfer', 'mobile_banking', 'card' => 'bank',
                default => null,
            },
            $recipientBank,
            $recipientReference,
        );

        $advance->forceFill(['payment_status' => 'Paid', 'paid_at' => $date])->save();

        // The future payslip records salary net of this advance. A second
        // credit here would deduct it twice, so this is an audit/display row
        // with no effect on the employee's salary-due running balance.
        $this->syncEmployeeLedgerRow($advance, 'advance_salary', (int) $advance->user_id, [
            'entry_date' => $date,
            'reference'  => $journalReference,
            'debit'      => 0,
            'credit'     => 0,
            'note'       => 'Advance salary paid from ' . $bank->name . ' (informational; recovered through payroll)',
        ]);

        return $journal;
    }

    /**
     * Take the payment back out of the books (advance set back to Pending,
     * rejected, or deleted). Refused once any salary has recovered it — that
     * recovery is already in the books against this payment.
     */
    public function undoRelease(AdvanceSalary $advance): void
    {
        if ($advance->is_reversed) {
            throw new \RuntimeException('This advance was reversed. Its audit records cannot be deleted or rewritten.');
        }
        $journal = $this->releaseJournal($advance);
        if (! $journal) {
            return;
        }

        if ($advance->recovered_amount > 0) {
            throw new \RuntimeException('A salary has already deducted this advance, so its payment cannot be reversed. Delete or correct that salary first.');
        }

        $journal->items()->forceDelete();
        $journal->forceDelete();

        EmployeeLedger::where('source_type', $advance->getMorphClass())
            ->where('source_id', $advance->id)
            ->where('type', 'advance_salary')
            ->forceDelete();

        $advance->forceFill(['payment_status' => 'Unpaid', 'paid_at' => null])->save();
    }

    /**
     * Keep the salary row's recovery entry equal to what it withheld. Safe to
     * call any number of times: it replaces, never stacks.
     */
    public function syncRecovery(EmployeeSalary $salary): void
    {
        $this->removeRecovery((int) $salary->id);

        $amount = round((float) $salary->advance_salary_deduction, 2);
        if ($amount <= 0 || ! $salary->advance_salary_id) {
            return;
        }

        $advance = AdvanceSalary::find($salary->advance_salary_id);
        if ($advance?->is_reversed) {
            throw new \RuntimeException('This advance was reversed and must not be deducted from salary.');
        }
        $release = $advance ? $this->releaseJournal($advance) : null;
        if (! $release) {
            return;   // paid out before posting existed — see the class note
        }

        $advanceAccountId = (int) $release->items()->where('debit', '>', 0)->value('account_id');
        $expense = Account::where('code', config('accounts.salary_expense'))->firstOrFail();
        $salary->loadMissing('user');
        $name = $salary->user->name ?? 'Employee';
        $period = sprintf('%04d-%02d', (int) $salary->year, (int) $salary->month);

        $this->postBalancedJournal(
            self::SOURCE_RECOVERY,
            (int) $salary->id,
            $salary->user->company_id ?? auth()->user()?->company_id ?? 2,
            $salary->salary_generation_date ?? now()->toDateString(),
            'ADVREC-' . $salary->id,
            'Advance recovered from ' . $period . ' salary — ' . $name,
            (int) $expense->id,
            $advanceAccountId,
            $amount,
            'Salary settled by advance — ' . $name,
            'Advance recovered from salary — ' . $name,
            'employee',
            (int) $salary->user_id,
        );
    }

    public function removeRecovery(int $salaryId): void
    {
        $journal = JournalEntry::where('source', self::SOURCE_RECOVERY)->where('source_id', $salaryId)->first();
        if ($journal) {
            $journal->items()->forceDelete();
            $journal->forceDelete();
        }
    }

    // ── Paid from ────────────────────────────────────────────────────────

    /**
     * Which bank each advance was paid from, read off its release entry —
     * [advance_id => ['bank' => name, 'ref' => ADV-000xx]]. One query for any
     * number of advances. Advances with no release entry are simply absent.
     */
    public function paidFrom(iterable $advanceIds): array
    {
        $ids = collect($advanceIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return \Illuminate\Support\Facades\DB::table('journal_entries as j')
            ->join('journal_items as i', fn ($q) => $q->on('i.journal_entry_id', '=', 'j.id')->where('i.credit', '>', 0))
            ->leftJoin('banks as b', 'b.account_id', '=', 'i.account_id')
            ->leftJoin('accounts as a', 'a.id', '=', 'i.account_id')
            ->where('j.source', self::SOURCE_RELEASE)
            ->whereIn('j.source_id', $ids)
            ->whereNull('j.deleted_at')
            ->whereNull('i.deleted_at')
            ->get(['j.source_id', 'j.reference', 'j.date', 'j.entry_time', 'j.beneficiary_method', 'j.beneficiary_bank', 'j.beneficiary_ref', 'b.name as bank', 'a.name as account'])
            ->mapWithKeys(fn ($r) => [(int) $r->source_id => [
                'bank' => $r->bank ?: $r->account,
                'ref' => $r->reference,
                'date' => $r->date,
                'time' => $r->entry_time,
                'method' => $r->beneficiary_method,
                'recipient_bank' => $r->beneficiary_bank,
                'recipient_ref' => $r->beneficiary_ref,
            ]])
            ->all();
    }
}
