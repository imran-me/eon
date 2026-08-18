<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if('employee' == Str::slug(Auth::user()->getRoleNames()->first())) {
            $request->merge(['user_id' => Auth::id()]);
        }
        $query = Loan::select('loans.*')
            ->join('users', 'users.id', '=', 'loans.user_id')
            ->orderBy('loans.id', 'desc');

        if ($request->has('user_id') && !empty($request->user_id)) {
            $query->where('loans.user_id', $request->user_id);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('loans.start_date', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('loans.end_date', $request->end_date);
        }

        $datas = $query->paginate(20);
        // $banks = Bank::orderBy('name')->where('status', 1)->get();
        // $users = User::orderBy('name')->where('status', 'active')->role('employee')->get();

        return response()->json([
            'success' => true,
            'message' => 'Loans retrieved successfully.',
            'data' => $datas,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'amount' => 'required',
            'start_date' => 'required'
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ]);            
        }

        try {
            $data = null;

            DB::transaction(function () use ($request, &$data) {
                $data = Loan::create([
                    'user_id' => $request->user_id,            
                    'bank_id' => $request->bank_id,            
                    'amount' => $request->amount,
                    'remaining_amount' => $request->remaining_amount ?? 0,
                    'monthly_deduction' => $request->monthly_deduction ?? 0,
                    'status' => $request->status ?? 'Running',
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date
                ]);

                // ── JOURNAL (auto) ────────────────────────────────────────
                $loanReceivableAccount = \App\Models\Account::where('code', config('accounts.loan_receivable'))->firstOrFail();

                $journal = \App\Models\JournalEntry::create([
                    'company_id'  => auth()->user()->company_id ?? 2,
                    'created_by'  => auth()->id(),
                    'date'        => $data->start_date,
                    'reference'   => 'LOAN-' . str_pad($data->id, 5, '0', STR_PAD_LEFT),
                    'source'      => 'loan',
                    'source_id'   => $data->id,
                    'description' => 'Loan issued — User #' . $data->user_id,
                ]);

                // Debit: Loan Receivable — employee owes us this amount
                \App\Models\JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $loanReceivableAccount->id,
                    'debit'            => $data->amount,
                    'credit'           => 0,
                    'note'             => 'Loan issued to employee',
                ]);

                // Credit: Bank — money went out (if bank_id provided)
                if ($request->bank_id) {
                    $bank = \App\Models\Bank::find($request->bank_id);
                    if (!$bank || !$bank->account_id) {
                        throw new \Exception('Bank is not linked to a chart-of-accounts account.');
                    }
                    \App\Models\JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id'       => $bank->account_id,
                        'debit'            => 0,
                        'credit'           => $data->amount,
                        'note'             => 'Loan disbursed from bank',
                    ]);
                } else {
                    // No bank selected — use cash/general payable as fallback
                    $salaryPayableAccount = \App\Models\Account::where('code', config('accounts.salary_payable'))->firstOrFail();
                    \App\Models\JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id'       => $salaryPayableAccount->id,
                        'debit'            => 0,
                        'credit'           => $data->amount,
                        'note'             => 'Loan issued — bank not specified',
                    ]);
                }
                // ── END JOURNAL ───────────────────────────────────────────

                // The movement itself, same as the web controller writes. A loan
                // created from the app has to reach the loan register with its
                // money going out, or it reads there as never having been paid.
                $ledger = app(\App\Services\LoanLedgerService::class);
                $ledger->recordDisbursement($data, $journal->id);
                $ledger->applyStatedRemaining($data, $request->remaining_amount);
                $ledger->syncLoanBalance($data);
            });
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Loan created successfully.',
            'data' => $data
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $data = Loan::findOrFail($id);
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Loan not found!'
            ]);
        }
        $validated = $request->validate([
            'user_id' => 'required',
            'amount' => 'required',
            'start_date' => 'required'
        ]);

        try {
            DB::transaction(function () use ($request, $data) {

                $data->update([
                    'user_id' => $request->user_id,
                    'bank_id' => $request->bank_id,
                    'amount' => $request->amount,
                    'remaining_amount' => $request->remaining_amount ?? 0,
                    'monthly_deduction' => $request->monthly_deduction ?? 0,
                    'status' => $request->status ?? 'Running',
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date
                ]);
                
                // ── JOURNAL UPDATE (auto) ─────────────────────────────────
                $loanReceivableAccount = \App\Models\Account::where('code', config('accounts.loan_receivable'))->firstOrFail();
                $journal = \App\Models\JournalEntry::where('source', 'loan')->where('source_id', $data->id)->first();

                if ($journal) {
                    $journal->items()->delete();
                    $journal->update([
                        'date'        => $data->start_date,
                        'description' => 'Loan (edited) — User #' . $data->user_id,
                    ]);
                } else {
                    $journal = \App\Models\JournalEntry::create([
                        'company_id'  => auth()->user()->company_id ?? 2,
                        'created_by'  => auth()->id(),
                        'date'        => $data->start_date,
                        'reference'   => 'LOAN-' . str_pad($data->id, 5, '0', STR_PAD_LEFT),
                        'source'      => 'loan',
                        'source_id'   => $data->id,
                        'description' => 'Loan (edited) — User #' . $data->user_id,
                    ]);
                }

                // Debit: Loan Receivable — updated loan amount
                \App\Models\JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $loanReceivableAccount->id,
                    'debit'            => $data->amount,
                    'credit'           => 0,
                    'note'             => 'Loan issued to employee',
                ]);

                // Credit: Bank or Salary Payable fallback
                if ($request->bank_id) {
                    $bank = \App\Models\Bank::find($request->bank_id);
                    if (!$bank || !$bank->account_id) {
                        throw new \Exception('Bank is not linked to a chart-of-accounts account.');
                    }
                    \App\Models\JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id'       => $bank->account_id,
                        'debit'            => 0,
                        'credit'           => $data->amount,
                        'note'             => 'Loan disbursed from bank',
                    ]);
                } else {
                    $salaryPayableAccount = \App\Models\Account::where('code', config('accounts.salary_payable'))->firstOrFail();
                    \App\Models\JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id'       => $salaryPayableAccount->id,
                        'debit'            => 0,
                        'credit'           => $data->amount,
                        'note'             => 'Loan issued — bank not specified',
                    ]);
                }
                // ── END JOURNAL ───────────────────────────────────────────

                // The movement itself, same as the web controller writes. A loan
                // created from the app has to reach the loan register with its
                // money going out, or it reads there as never having been paid.
                $ledger = app(\App\Services\LoanLedgerService::class);
                $ledger->recordDisbursement($data, $journal->id);
                $ledger->applyStatedRemaining($data, $request->remaining_amount);
                $ledger->syncLoanBalance($data);
            });
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong! '. $th->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Loan updated successfully.',
            'data' => $data
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $item = Loan::find($id);
            if ($item) {

                DB::transaction(function () use ($item) {

                    // ── JOURNAL CLEANUP ───────────────────────────────────
                    $journal = \App\Models\JournalEntry::where('source', 'loan')
                        ->where('source_id', $item->id)
                        ->first();
                    if ($journal) {
                        $journal->items()->forceDelete();
                        $journal->forceDelete();
                    }
                    // ── END JOURNAL ───────────────────────────────────────

                    $item->delete();
                });
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Loan not found!'
                ]);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Loan deleted successfully.'
        ]);
    }
}
