<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\LeaveEncashmentOpeningEntry;
use App\Models\User;
use App\Traits\PostsEmployeeLedger;
use App\Traits\PostsSalaryJournal;
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
            new Middleware('permission:create salary', only: ['storeBonus', 'storeOpeningBalance', 'storeLeaveEncashmentOpening']),
        ];
    }

    /**
     * Record a one-off bonus directly on the employee's ledger (not tied to a
     * monthly salary sheet row), with the matching journal entry.
     */
    public function storeBonus(Request $request, string $role, User $user)
    {
        $validator = Validator::make($request->all(), [
            'amount'     => 'required|numeric|min:0.01',
            'entry_date' => 'required|date',
            'reference'  => 'nullable|string|max:255',
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
                    'type'       => 'bonus',
                    'entry_date' => $request->entry_date,
                    'reference'  => $request->reference ?: 'Bonus',
                    'debit'      => $request->amount,
                    'credit'     => 0,
                ]);

                $this->createSalaryJournal(
                    'employee_ledger',
                    $ledgerRow->id,
                    $user->company_id ?? auth()->user()->company_id ?? 2,
                    $request->entry_date,
                    'BONUS-' . $ledgerRow->id,
                    'Bonus — ' . $user->name,
                    (float) $request->amount,
                    false,
                    null,
                    'Bonus'
                );
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
            'amount'     => 'required|numeric|min:0.01',
            'entry_date' => 'required|date',
            'reference'  => 'nullable|string|max:255',
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
                    'type'       => 'opening_balance',
                    'entry_date' => $request->entry_date,
                    'reference'  => $request->reference ?: 'Opening balance',
                    'debit'      => $request->amount,
                    'credit'     => 0,
                ]);

                $openingBalanceEquityAccount = Account::where('code', config('accounts.opening_balance_equity'))->firstOrFail();
                $salaryPayableAccount = Account::where('code', config('accounts.salary_payable'))->firstOrFail();
                $companyId = $user->company_id ?? auth()->user()->company_id ?? 2;

                $journal = JournalEntry::create([
                    'company_id'  => $companyId,
                    'created_by'  => auth()->id(),
                    'date'        => $request->entry_date,
                    'reference'   => 'OPBAL-' . $ledgerRow->id,
                    'source'      => 'employee_ledger',
                    'source_id'   => $ledgerRow->id,
                    'description' => 'Opening balance — ' . $user->name,
                ]);

                JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $openingBalanceEquityAccount->id,
                    'debit'            => $request->amount,
                    'credit'           => 0,
                    'note'             => 'Opening balance — ' . $user->name,
                ]);

                JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $salaryPayableAccount->id,
                    'debit'            => 0,
                    'credit'           => $request->amount,
                    'note'             => 'Opening balance payable — ' . $user->name,
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
            'days'       => 'required|numeric|min:0',
            'amount'     => 'required|numeric|min:0',
            'as_of_date' => 'required|date',
            'notes'      => 'nullable|string|max:255',
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
                'days'       => $request->days,
                'amount'     => $request->amount,
                'as_of_date' => $request->as_of_date,
                'notes'      => $request->notes,
                'created_by' => auth()->id(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Leave encashment opening entry saved successfully.',
        ]);
    }
}
