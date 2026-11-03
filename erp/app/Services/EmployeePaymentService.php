<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Bank;
use App\Models\EmployeePayment;
use App\Models\EmployeePaymentAllocation;
use App\Models\EmployeeSalary;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\User;
use App\Traits\PostsBalancedJournal;
use App\Traits\PostsEmployeeLedger;
use Illuminate\Support\Facades\DB;

class EmployeePaymentService
{
    use PostsBalancedJournal, PostsEmployeeLedger;

    public function pay(User $user, array $data): EmployeePayment
    {
        $payment = DB::transaction(function () use ($user, $data) {
            $amount = round((float) $data['amount'], 2);
            $bankCharge = round((float) ($data['bank_charge_amount'] ?? 0), 2);
            // Pay Due supplies the operator's mandatory custom time. Legacy
            // schedule settlement also enters this shared service; retain a
            // safe clock value there until that older modal supplies one.
            $transactionTime = $data['transaction_time'] ?? now()->format('H:i');
            $bank = Bank::findOrFail($data['bank_id']);
            if (! $bank->account_id) {
                throw new \RuntimeException('Bank is not linked to a chart-of-accounts account.');
            }
            $chargeAccount = null;
            if ($bankCharge > 0) {
                $chargeAccount = Account::active()->whereKey($data['bank_charge_account_id'] ?? null)
                    ->where('type', 'expense')
                    ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', auth()->user()->company_id ?? $user->company_id))
                    ->whereDoesntHave('children')->first();
                if (! $chargeAccount) {
                    throw new \RuntimeException('Select an active expense account for the salary bank charge.');
                }
            }
            Bank::assertCanCover((int) $bank->account_id, $amount + $bankCharge, $bank->name);

            // A caller's own reference (e.g. the Pay Due Amount form's
            // pre-filled, editable "Reference No. / TRNX ID") wins when given;
            // otherwise this remains the guaranteed-unique fallback so every
            // payment still has one.
            $reference = trim((string) ($data['reference'] ?? '')) ?: 'EMPPAY-'.$user->id.'-'.now()->format('YmdHisv');
            $payment = EmployeePayment::create([
                'user_id' => $user->id, 'company_id' => auth()->user()->company_id ?? $user->company_id,
                'bank_id' => $bank->id, 'payment_date' => $data['payment_date'],
                'transaction_time' => $transactionTime,
                'payment_method' => $data['payment_method'] ?? null, 'reference' => $reference,
                'recipient_bank' => $data['recipient_bank'] ?? null,
                'recipient_account' => $data['recipient_account'] ?? null,
                'amount' => $amount, 'bank_charge_amount' => $bankCharge,
                'bank_charge_account_id' => $chargeAccount?->id,
                'note' => $data['note'] ?? null, 'status' => 'posted',
                'created_by' => auth()->id(),
            ]);

            $remaining = $amount;
            $paidSub = EmployeePaymentAllocation::postedTotalsSubquery();
            $salaries = EmployeeSalary::query()
                ->leftJoinSub($paidSub, 'allocation_paid', fn ($j) => $j->on('allocation_paid.schedulable_id', '=', 'employee_salaries.id'))
                ->where('employee_salaries.user_id', $user->id)
                ->when(! empty($data['employee_salary_id']), fn ($q) => $q->where('employee_salaries.id', $data['employee_salary_id']))
                ->whereRaw('employee_salaries.net_salary > COALESCE(allocation_paid.paid_total, 0)')
                ->orderBy('employee_salaries.year')->orderBy('employee_salaries.month')->orderBy('employee_salaries.id')
                ->lockForUpdate()->get(['employee_salaries.*', DB::raw('COALESCE(allocation_paid.paid_total, 0) allocated_paid')]);

            if (! empty($data['employee_salary_id']) && $salaries->isEmpty()) {
                throw new \RuntimeException('Selected payslip is already paid or does not belong to this employee.');
            }
            foreach ($salaries as $salary) {
                if ($remaining <= .01) {
                    break;
                }
                $due = round((float) $salary->net_salary - (float) $salary->allocated_paid, 2);
                $allocated = min($remaining, $due);
                EmployeePaymentAllocation::create(['employee_payment_id' => $payment->id, 'employee_salary_id' => $salary->id, 'amount' => $allocated]);
                $salary->update(['status' => $allocated >= $due - .01 ? 'Paid' : 'Pending']);
                $remaining = round($remaining - $allocated, 2);
            }
            if (! empty($data['employee_salary_id']) && $remaining > .01) {
                throw new \RuntimeException('Amount exceeds the selected payslip due.');
            }

            $payable = Account::where('code', config('accounts.salary_payable'))->firstOrFail();
            $journal = $this->postBalancedJournal('employee_payment', $payment->id, $payment->company_id ?? 2,
                $data['payment_date'], $reference, 'Employee payment - '.$user->name,
                $payable->id, $bank->account_id, $amount, 'Salary payable settled',
                'Employee paid via '.($data['payment_method'] ?? 'N/A'), 'employee', $user->id,
                $transactionTime, $user->name, $user->id,
                match ($data['payment_method'] ?? null) {
                    'cash' => 'cash', 'cheque' => 'cheque',
                    'bank_transfer', 'mobile_banking', 'card' => 'bank',
                    default => null,
                },
                $data['recipient_bank'] ?? null,
                $data['recipient_account'] ?? null);
            if ($bankCharge > 0) {
                $bankItem = $journal->items()->where('account_id', $bank->account_id)->firstOrFail();
                $bankItem->update(['credit' => round($amount + $bankCharge, 2)]);
                JournalItem::create([
                    'journal_entry_id' => $journal->id, 'account_id' => $chargeAccount->id,
                    'debit' => $bankCharge, 'credit' => 0, 'note' => 'Bank charge — salary payment',
                    'party_type' => 'employee', 'party_id' => $user->id,
                ]);
            }
            $this->postEmployeeLedgerRow($user->id, ['type' => 'employee_payment', 'entry_date' => $data['payment_date'],
                'reference' => $reference, 'debit' => 0, 'credit' => $amount, 'note' => $data['note'] ?? null], $payment);

            return $payment->load('allocations');
        });

        // Delivery happens only after the accounting transaction commits.
        // A gateway outage must never roll back (or invite a duplicate of) a
        // valid salary payment.
        // When this service is called from Payment Schedule it sits inside an
        // outer transaction. afterCommit waits for that outermost commit; on a
        // direct Pay Due payment it runs immediately after this transaction.
        DB::afterCommit(function () use ($payment) {
            try {
                app(EmployeePaymentNotifier::class)->send($payment->loadMissing(['employee', 'bank']));
            } catch (\Throwable $e) {
                \Log::warning('Employee payment notification pipeline failed.', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        return $payment;
    }

    public function reverse(EmployeePayment $payment, array $data): void
    {
        DB::transaction(function () use ($payment, $data) {
            $payment = EmployeePayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status === 'reversed') {
                throw new \RuntimeException('This payment is already reversed.');
            }
            $payment->load(['allocations.salary', 'employee', 'bank']);
            if (! $payment->bank?->account_id) {
                throw new \RuntimeException('Payment bank is not linked to an account.');
            }
            $original = JournalEntry::with('items')->where('source', 'employee_payment')
                ->where('source_id', $payment->id)->whereNull('reversed_journal_entry_id')->lockForUpdate()->firstOrFail();
            if ($original->reversedBy()->exists()) {
                throw new \RuntimeException('This payment journal is already reversed.');
            }
            $reversal = JournalEntry::create([
                'company_id' => $original->company_id, 'created_by' => auth()->id(),
                'date' => $data['reversal_date'], 'entry_time' => $original->entry_time,
                'reference' => 'REV-'.$payment->reference, 'source' => 'employee_payment_reversal',
                'source_id' => $payment->id, 'description' => 'Employee payment reversal — '.$data['reason'],
                'beneficiary' => $original->beneficiary, 'beneficiary_party_id' => $original->beneficiary_party_id,
                'beneficiary_method' => $original->beneficiary_method, 'beneficiary_bank' => $original->beneficiary_bank,
                'beneficiary_ref' => $original->beneficiary_ref, 'reversed_journal_entry_id' => $original->id,
            ]);
            foreach ($original->items as $item) {
                JournalItem::create([
                    'journal_entry_id' => $reversal->id, 'account_id' => $item->account_id,
                    'debit' => $item->credit, 'credit' => $item->debit,
                    'note' => 'Reversal — '.$item->note, 'party_type' => $item->party_type, 'party_id' => $item->party_id,
                ]);
            }
            $this->postEmployeeLedgerRow($payment->user_id, ['type' => 'employee_payment_reversal',
                'entry_date' => $data['reversal_date'], 'reference' => 'REV-'.$payment->reference,
                'debit' => $payment->amount, 'credit' => 0, 'note' => $data['reason']], $payment);
            $payment->update(['status' => 'reversed', 'reversed_by' => auth()->id(), 'reversed_at' => now(), 'reversal_reason' => $data['reason']]);
            foreach ($payment->allocations->pluck('salary')->filter()->unique('id') as $salary) {
                $salary->update(['status' => EmployeePaymentAllocation::paidForSalary($salary->id) >= (float) $salary->net_salary - .01 ? 'Paid' : 'Pending']);
            }
        });
    }
}
