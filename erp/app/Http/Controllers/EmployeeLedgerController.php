<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Bank;
use App\Models\EmployeeLedger;
use App\Models\EmployeePayment;
use App\Models\EmployeeSalary;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\LeaveEncashmentOpeningEntry;
use App\Models\User;
use App\Services\PayrollService;
use App\Traits\PostsEmployeeLedger;
use App\Traits\PostsSalaryJournal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmployeeLedgerController implements HasMiddleware
{
    use PostsEmployeeLedger, PostsSalaryJournal;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:create salary', only: ['storeBonus', 'storeOpeningBalance', 'storeLeaveEncashmentOpening', 'storeExitLeaveEncashment']),
            new Middleware('permission:edit payment schedule', only: ['updatePaymentDateTime']),
        ];
    }

    /**
     * Correct an employee-ledger row's chronology without changing money.
     * Where the row has one unambiguous accounting/source document, its date
     * and journal timestamp move with the ledger row.
     */
    public function updatePaymentDateTime(Request $request, string $role, User $user, EmployeeLedger $entry)
    {
        abort_unless((int) $entry->user_id === (int) $user->id, 404);

        $data = $request->validate([
            'entry_date' => 'required|date|before_or_equal:today',
            'transaction_time' => 'required|date_format:H:i',
            'reason' => 'required|string|min:5|max:500',
        ]);

        try {
            DB::transaction(function () use ($data, $entry) {
                $entry = EmployeeLedger::whereKey($entry->id)->lockForUpdate()->firstOrFail();
                $source = $entry->source;

                $oldDate = $entry->entry_date->format('Y-m-d');
                $oldTime = substr((string) ($entry->transaction_time ?: $entry->display_time ?: 'not recorded'), 0, 8);
                $audit = sprintf(
                    'Date/time corrected from %s %s to %s %s. Reason: %s',
                    $oldDate,
                    $oldTime,
                    $data['entry_date'],
                    $data['transaction_time'],
                    trim($data['reason'])
                );

                DB::table('employee_ledger')->where('id', $entry->id)->update([
                    'entry_date' => $data['entry_date'],
                    'transaction_time' => $data['transaction_time'].':00',
                    'note' => trim(implode("\n", array_filter([$entry->note, $audit]))),
                    'updated_at' => now(),
                ]);

                if ($source instanceof EmployeePayment && $entry->type === 'employee_payment') {
                    DB::table('employee_payments')->where('id', $source->id)->update([
                        'payment_date' => $data['entry_date'],
                        'transaction_time' => $data['transaction_time'].':00',
                        'updated_at' => now(),
                    ]);
                }
                if ($source instanceof EmployeeSalary && $entry->type === 'salary_earned') {
                    DB::table('employee_salaries')->where('id', $source->id)->update([
                        'salary_generation_date' => $data['entry_date'],
                        'updated_at' => now(),
                    ]);
                }

                $journal = $this->journalForLedgerEntry($entry);
                if ($journal) {
                    $journal->update([
                        'date' => $data['entry_date'],
                        'entry_time' => $data['transaction_time'].':00',
                    ]);
                }
            });
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Transaction date and time corrected. Amounts and balances were unchanged.',
        ]);
    }

    private function journalForLedgerEntry(EmployeeLedger $entry): ?JournalEntry
    {
        $sourceId = (int) ($entry->source_id ?: $entry->id);
        $journalSource = match ($entry->type) {
            'employee_payment' => 'employee_payment',
            'employee_payment_reversal' => 'employee_payment_reversal',
            'salary_earned' => 'salary',
            'salary_reconciliation' => 'salary_reconciliation',
            'advance_salary' => 'advance_salary',
            'advance_salary_reversal' => 'advance_salary_reversal',
            'loan' => 'loan',
            'opening_balance', 'bonus', 'bonus_paid' => 'employee_ledger',
            default => null,
        };

        if (! $journalSource) {
            return null;
        }

        return JournalEntry::where('source', $journalSource)
            ->where('source_id', $sourceId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Record a one-off bonus directly on the employee's ledger (not tied to a
     * monthly salary sheet row), with the matching journal entry.
     */
    public function storeBonus(Request $request, string $role, User $user)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'entry_date' => 'required|date',
            'transaction_time' => 'required|date_format:H:i',
            'payment_method' => 'required|in:cash,bank_transfer,mobile_banking,cheque,card',
            'bank_id' => 'required|exists:banks,id',
            'reference' => 'nullable|string|max:191',
            'reason' => 'nullable|string|max:255',
            'recipient_bank' => 'nullable|string|max:150',
            'recipient_account' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            DB::transaction(function () use ($request, $user) {
                $bank = Bank::whereKey($request->bank_id)->whereNotNull('account_id')->firstOrFail();
                if ($bank->company_id && $user->company_id
                    && (int) $bank->company_id !== (int) $user->company_id) {
                    throw new \RuntimeException('Choose an account owned by the employee company, or a Group / Shared account.');
                }
                Bank::assertCanCover((int) $bank->account_id, (float) $request->amount, $bank->name);
                $journalReference = trim((string) $request->reference)
                    ?: 'BONUS-'.now()->format('YmdHis').random_int(10, 99);
                $reason = trim((string) $request->reason) ?: 'Bonus';

                $ledgerRow = $this->postEmployeeLedgerRow($user->id, [
                    'type' => 'bonus',
                    'entry_date' => $request->entry_date,
                    'reference' => $reason,
                    'debit' => $request->amount,
                    'credit' => 0,
                ]);

                $journal = $this->createSalaryJournal(
                    'employee_ledger',
                    $ledgerRow->id,
                    $user->company_id ?? auth()->user()->company_id ?? 2,
                    $request->entry_date,
                    $journalReference,
                    'Bonus — '.$user->name,
                    (float) $request->amount,
                    true,
                    $bank->id,
                    'Bonus'
                );

                $this->postEmployeeLedgerRow($user->id, [
                    'type' => 'bonus_paid',
                    'entry_date' => $request->entry_date,
                    'reference' => $journalReference,
                    'debit' => 0,
                    'credit' => $request->amount,
                    'note' => 'Bonus paid from '.$bank->name,
                ], $ledgerRow);

                $journal->update([
                    'entry_time' => $request->transaction_time.':00',
                    'beneficiary' => $user->name,
                    'beneficiary_party_id' => $user->id,
                    'beneficiary_method' => in_array($request->payment_method, ['cash', 'cheque']) ? $request->payment_method : 'bank',
                    'beneficiary_bank' => $request->recipient_bank,
                    'beneficiary_ref' => $request->recipient_account,
                    'description' => $reason.' paid — '.$user->name,
                ]);
                $journal->items()->update([
                    'party_type' => 'employee',
                    'party_id' => $user->id,
                ]);
            });
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bonus recorded successfully.',
        ]);
    }

    /**
     * Manually records a one-time "opening balance" on the employee's
     * ledger — for money genuinely owed from before proper ledger/journal
     * tracking existed for this employee (e.g. a salary month that predates
     * this accounting system, or was created through a path that never
     * posted to the ledger). Not tied to any specific EmployeeSalary row or
     * PaymentSchedule — "Pay Due Amount" already settles any ledger balance
     * beyond real pending schedules via its generic remainder branch, so
     * this needs no schedule of its own to eventually be paid down.
     *
     * Deliberately does NOT use PostsSalaryJournal::createSalaryJournal() —
     * that trait always debits salary_expense, which would double-book the
     * expense for something that already happened before this system
     * existed. Instead debits the dedicated opening_balance_equity account
     * (config('accounts.opening_balance_equity')) — the standard
     * accounting pattern for bringing a new ledger in sync with reality
     * without re-recognizing an expense on the current period's P&L.
     */
    public function storeOpeningBalance(Request $request, string $role, User $user)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'entry_date' => 'required|date',
            'reference' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            DB::transaction(function () use ($request, $user) {
                $ledgerRow = $this->postEmployeeLedgerRow($user->id, [
                    'type' => 'opening_balance',
                    'entry_date' => $request->entry_date,
                    'reference' => $request->reference ?: 'Opening balance',
                    'debit' => $request->amount,
                    'credit' => 0,
                ]);

                $openingBalanceEquityAccount = Account::where('code', config('accounts.opening_balance_equity'))->firstOrFail();
                $salaryPayableAccount = Account::where('code', config('accounts.salary_payable'))->firstOrFail();
                $companyId = $user->company_id ?? auth()->user()->company_id ?? 2;

                $journal = JournalEntry::create([
                    'company_id' => $companyId,
                    'created_by' => auth()->id(),
                    'date' => $request->entry_date,
                    'reference' => 'OPBAL-'.$ledgerRow->id,
                    'source' => 'employee_ledger',
                    'source_id' => $ledgerRow->id,
                    'description' => 'Opening balance — '.$user->name,
                ]);

                JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $openingBalanceEquityAccount->id,
                    'debit' => $request->amount,
                    'credit' => 0,
                    'note' => 'Opening balance — '.$user->name,
                ]);

                JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $salaryPayableAccount->id,
                    'debit' => 0,
                    'credit' => $request->amount,
                    'note' => 'Opening balance payable — '.$user->name,
                ]);
            });
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Opening balance recorded successfully.',
        ]);
    }

    /**
     * Sets (or edits) $user's one-time leave-encashment opening entry —
     * days + amount credited toward their first payout, for service time
     * that predates clean per-month leave_deduction tracking. Unlike
     * storeOpeningBalance() above, this does NOT touch the ledger or post a
     * journal entry immediately — it's only an input to
     * PayrollService::projectPendingReconciliation() /
     * maybeProcessAnniversaryReconciliation(), which fold it into the
     * accrual (and post the real ledger/journal entries) once the actual
     * payout happens. One row per employee — upserted, not appended, since
     * this is meant to be set once (with room to correct a mistake).
     */
    public function storeLeaveEncashmentOpening(Request $request, string $role, User $user)
    {
        $validator = Validator::make($request->all(), [
            'days' => 'required|numeric|min:0',
            'amount' => 'required|numeric|min:0',
            'as_of_date' => 'required|date',
            'notes' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        LeaveEncashmentOpeningEntry::updateOrCreate(
            ['user_id' => $user->id],
            [
                'days' => $request->days,
                'amount' => $request->amount,
                'as_of_date' => $request->as_of_date,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Leave encashment opening entry saved successfully.',
        ]);
    }

    public function storeExitLeaveEncashment(Request $request, string $role, User $user, PayrollService $payroll)
    {
        $actor = $request->user();
        abort_unless($actor && ($actor->is_super_admin || $actor->hasRole('super admin')), 403);

        $validator = Validator::make($request->all(), ['payout_date' => 'required|date']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }
        try {
            $payroll->processLeaveEncashmentPayout($user, Carbon::parse($request->payout_date));
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Leave encashment posted to the journal and payment schedule.']);
    }
}
