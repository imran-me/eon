<?php

namespace App\Http\Controllers;

use App\Exports\PayrollBookExport;
use App\Models\Bank;
use App\Models\Company;
use App\Models\Loan;
use App\Models\LoanTransaction;
use App\Models\User;
use App\Services\LoanBookService;
use App\Services\LoanLedgerService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class LoanController implements HasMiddleware
{
    public function __construct(private LoanLedgerService $ledger)
    {
    }

    public static function middleware(): array
    {
        return [
            // Exports are a view, not a write: they can only ever hand back what
            // the same user could already read on the page, and scopedLoans()
            // applies the same company and employee locks.
            new Middleware('permission:view loan', only: ['index', 'show', 'exportExcel', 'exportPdf', 'statement']),
            new Middleware('permission:create loan', only: ['create', 'store', 'repay']),
            new Middleware('permission:edit loan', only: ['edit', 'update']),
            new Middleware('permission:delete loan', only: ['destroy']),
        ];
    }

    /**
     * The staff loan desk — one page, four views of the same book.
     *
     * The tiles say where the book stands, "Employees with loans" folds it by
     * person, the register keeps one row per loan (running and cleared alike,
     * because "how much of the ৳30,000 taken in May is left" is a question about
     * a loan, not a person), and the transaction trail is every movement that
     * produced those figures. All four are read off the same scoped collection —
     * they cannot disagree.
     */
    public function index(Request $request, LoanBookService $book)
    {
        $loans = $this->scopedLoans($request);

        $summary = $book->summary($loans);
        $series = $book->series($loans);
        $byEmployee = $book->byEmployee($loans);
        $balances = $book->runningBalances($loans);

        // ── THE TRAIL ──────────────────────────────────────────────────────
        // Newest first, and paginated on its own page key so turning a page here
        // doesn't reset the register above it.
        $transactions = LoanTransaction::whereIn('loan_id', $loans->pluck('id'))
            ->with(['user' => fn ($q) => $q->withTrashed()->select('id', 'name', 'company_id')->with('company:id,name,short_name'), 'bank:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'txn_page')
            ->withQueryString();

        $register = $this->paginateCollection($loans, 10, 'reg_page', $request);
        $employeeBook = $this->paginateCollection($byEmployee, 8, 'emp_page', $request);

        // Same company-lock applied to the employee and bank pickers used
        // when creating a new loan.
        $authUser = Auth::user();
        $userCompanyId = $authUser->company_id;

        $bankQuery = Bank::orderBy('name')->where('status', 1);
        if (!$authUser->can('view all bank') && !empty($userCompanyId)) {
            $bankQuery->where('company_id', $userCompanyId);
        }
        $banks = $bankQuery->get();

        $employeeQuery = User::orderBy('name')->where('status', 'active')->role('employee');
        if (!$authUser->can('view all loan') && !empty($userCompanyId)) {
            $employeeQuery->where('company_id', $userCompanyId);
        }
        $users = $employeeQuery->get();

        // Recording a repayment needs a loan that still owes something — the
        // picker in the repay modal offers exactly those, and nothing else.
        $openLoans = $loans->filter(fn (Loan $l) => ! $l->is_cleared)->values();

        return view('loans.index', compact(
            'loans',
            'summary',
            'series',
            'employeeBook',
            'register',
            'transactions',
            'balances',
            'openLoans',
            'users',
            'banks',
        ));
    }

    /**
     * One loan's whole life — rendered as the body of the detail modal.
     *
     * Returned as HTML rather than JSON because it is a document, not data: the
     * caller drops it straight into the modal shell.
     */
    public function show(Request $request, $role, $id)
    {
        $loan = Loan::with([
            'user' => fn ($q) => $q->withTrashed()->with('company:id,name,short_name'),
            'bank:id,name',
            'transactions.bank:id,name',
            'transactions.employeeSalary:id,month,year',
        ])->findOrFail($id);

        $this->assertVisible($loan);

        return view('loans.detail', compact('loan'))->render();
    }

    /**
     * Money coming back on a loan, recorded by hand — a cash or bank repayment,
     * or an early settlement. Salary EMI arrives on its own through payroll.
     */
    public function repay(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'loan_id' => 'required|exists:loans,id',
            'amount'  => 'required|numeric|min:0.01',
            'date'    => 'required|date',
            'method'  => 'required|in:cash,bank',
            'bank_id' => 'nullable|exists:banks,id',
            'note'    => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            $loan = Loan::with('transactions')->findOrFail($request->loan_id);
            $this->assertVisible($loan);

            $transaction = $this->ledger->recordRepayment(
                $loan,
                (float) $request->amount,
                $request->method,
                $request->date,
                $request->method === 'bank' ? $request->bank_id : null,
                $request->note,
            );
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }

        $loan->refresh()->load('transactions');

        return response()->json([
            'success' => true,
            'message' => 'Repayment recorded — ৳' . number_format((float) $transaction->amount, 2)
                . ' off this loan, ৳' . number_format($loan->outstanding, 2) . ' still due.',
        ]);
    }

    /**
     * One of the three tables as a spreadsheet, exactly as filtered on screen.
     *
     * ?table=employees|register|transactions
     */
    public function exportExcel(Request $request, LoanBookService $book)
    {
        [$sheet, $scopeLabel, $filterLabel] = $this->exportPayload($request, $book);

        return Excel::download(
            new PayrollBookExport($sheet, $scopeLabel, $filterLabel),
            $sheet['filename'] . '-' . now()->format('Ymd-Hi') . '.xlsx'
        );
    }

    /**
     * The same table as a printable report.
     *
     * A browser print page, exactly as the party statement and the salary
     * paid/due report deliver theirs — not a server-rendered PDF. The browser has
     * the report font family, renders ৳, honours flexbox and applies the @page
     * margin; DomPDF got all four of those wrong, and "Download PDF" on the page
     * is the browser's own print-to-PDF.
     */
    public function exportPdf(Request $request, LoanBookService $book)
    {
        [$sheet, $scopeLabel, $filterLabel, $loans] = $this->exportPayload($request, $book);

        $summary = $book->summary($loans);

        return view('payroll.print-report', [
            'sheet'       => $sheet,
            'scopeLabel'  => $scopeLabel,
            'filterLabel' => $filterLabel,
            'company'     => $this->exportCompany($request),
            'meta'        => [
                'Loans in scope' => number_format($summary['loan_count']),
                'Still running'  => number_format($summary['active_loans']),
            ],
            // Closing figure last: the strip fills its final card.
            'cards' => [
                ['label' => 'Loan Outstanding', 'value' => '৳' . number_format($summary['outstanding'], 2), 'tone' => '#dc2626',
                 'note' => $summary['borrowers'] ? $summary['borrowers'] . ' carrying a loan' : 'nobody is carrying a loan'],
                ['label' => 'Total Disbursed', 'value' => '৳' . number_format($summary['disbursed'], 2),
                 'note' => $summary['loan_count'] . ' loan(s), all time'],
                ['label' => 'Active Loans', 'value' => number_format($summary['active_loans']),
                 'note' => $summary['emi_total'] ? '৳' . number_format($summary['emi_total']) . '/mo EMI' : 'no schedule set'],
                ['label' => 'Repaid', 'value' => '৳' . number_format($summary['repaid'], 2),
                 'note' => $summary['disbursed'] > 0 ? $summary['repaid_pct'] . '% of everything lent' : 'nothing lent yet'],
            ],
            'note' => 'Figures are derived from the loan movement rows: outstanding is what was lent less what has '
                . 'come back, and "Repaid" is disbursed less outstanding — which is why the four figures above '
                . 'reconcile against one another. A balance shown against a transaction is that loan\'s position '
                . 'at that moment and is deliberately not summed.',
        ]);
    }

    /**
     * One loan's statement — what was taken, what has come back, and every
     * payment behind those two figures.
     */
    public function statement(Request $request, $role, $id)
    {
        $loan = Loan::with([
            'user' => fn ($q) => $q->withTrashed()->with('company:id,name,short_name'),
            'bank:id,name',
            'transactions.bank:id,name',
        ])->findOrFail($id);

        $this->assertVisible($loan);

        // The employee's own company heads the letter, not the viewer's — a
        // statement is issued by the concern that lent the money.
        return view('loans.print-statement', [
            'loan'    => $loan,
            'company' => $loan->user?->company ?: $this->exportCompany($request),
        ]);
    }

    /**
     * What both exports need: the requested sheet, plus the two lines of context
     * that make a downloaded file readable a month later — which companies it
     * covers and which filters were on when it was taken.
     *
     * Both formats go through here so a spreadsheet and a PDF pulled from the
     * same screen can never describe different books.
     */
    private function exportPayload(Request $request, LoanBookService $book): array
    {
        $table = $request->query('table', 'register');

        if (! in_array($table, LoanBookService::tables(), true)) {
            abort(404, 'There is no such loan table to export.');
        }

        $loans = $this->scopedLoans($request);
        $withCompany = $loans->pluck('user.company_id')->filter()->unique()->count() > 1;

        return [
            $book->sheet($table, $loans, $withCompany),
            $this->scopeLabel($request, $loans),
            $this->filterLabel($request),
            $loans,
        ];
    }

    /** Which company or companies the export covers. */
    private function scopeLabel(Request $request, Collection $loans): string
    {
        if ($request->filled('company_id')) {
            $company = Company::find((int) $request->company_id);

            return $company?->name ?: 'Company #' . $request->company_id;
        }

        $names = $loans->pluck('user.company')->filter()->unique('id')
            ->map(fn ($c) => $c->short_name ?: $c->name);

        if ($names->isEmpty()) {
            return 'All companies';
        }

        return $names->count() === 1 ? $names->first() : 'All companies — ' . $names->implode(', ');
    }

    /**
     * The filters that were on, spelled out. A file that says "every loan" when
     * it holds one company's running loans is worse than one with no caption.
     */
    private function filterLabel(Request $request): string
    {
        $bits = [];

        if ($request->filled('user_id')) {
            $bits[] = 'Employee: ' . (User::withTrashed()->find($request->user_id)?->name ?: '#' . $request->user_id);
        }

        if ($request->filled('status')) {
            $bits[] = 'Status: ' . ($request->status === 'cleared' ? 'cleared only' : 'running only');
        }

        if ($request->filled('search')) {
            $bits[] = 'Search: "' . $request->search . '"';
        }

        if ($request->filled('start_date') || $request->filled('end_date')) {
            $from = $request->filled('start_date') ? Carbon::parse($request->start_date . '-01')->format('M Y') : 'inception';
            $to = $request->filled('end_date') ? Carbon::parse($request->end_date . '-01')->format('M Y') : 'date';
            $bits[] = 'Taken ' . $from . ' – ' . $to;
        }

        return $bits ? implode(' · ', $bits) : 'No filters — the whole book';
    }

    /** The letterhead a printed document goes out under. */
    private function exportCompany(Request $request)
    {
        if ($request->filled('company_id')) {
            return Company::find((int) $request->company_id) ?: Company::first();
        }

        return Auth::user()->company ?: Company::first();
    }

    /**
     * The book the page is currently showing — one definition of "in scope",
     * shared by the screen and by every export.
     *
     * Kept as one method on purpose. An Export button that quietly runs its own
     * query is the classic way a spreadsheet ends up disagreeing with the screen
     * it was downloaded from, and the permission lock at the top of it is the
     * same reason: an export must never reach further than the page can.
     */
    private function scopedLoans(Request $request): Collection
    {
        $authUser = Auth::user();

        if ('employee' == Str::slug($authUser->getRoleNames()->first()) && !$authUser->hasPermissionTo('view all loan')) {
            $request->merge(['user_id' => $authUser->id]);
        }

        $canSeeAll = $authUser->can('view all loan');
        $userCompanyId = $authUser->company_id;

        // Joined rather than whereHas'd, and eager-loaded withTrashed: a resigned
        // employee's loan is still owed, and must not drop out of the book the
        // day their user row is soft-deleted.
        $query = Loan::select('loans.*')
            ->join('users', 'users.id', '=', 'loans.user_id')
            ->with([
                'user' => fn ($q) => $q->withTrashed()->select('id', 'name', 'employee_id_no', 'company_id', 'status')->with('company:id,name,short_name'),
                'transactions',
            ]);

        // Users without "view all loan" are locked to their own company,
        // same convention as Salary Manage / Salary Template.
        if (!$canSeeAll && !empty($userCompanyId)) {
            $query->where('users.company_id', $userCompanyId);
        } elseif ($request->filled('company_id')) {
            // Payroll toolbar company chip — only honoured for users who are
            // allowed to see every company in the first place.
            $query->where('users.company_id', (int) $request->company_id);
        }

        if ($request->filled('user_id')) {
            $query->where('loans.user_id', $request->user_id);
        }

        // Taken-on range. These were `whereDate` against a month input, which
        // could never match a full date — a filter that silently returned nothing.
        if ($request->filled('start_date')) {
            $query->whereDate('loans.start_date', '>=', $request->start_date . '-01');
        }

        if ($request->filled('end_date')) {
            $query->whereDate('loans.start_date', '<=', Carbon::parse($request->end_date . '-01')->endOfMonth());
        }

        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('users.name', 'like', $term)
                    ->orWhere('users.employee_id_no', 'like', $term);
            });
        }

        $loans = $query->orderByDesc('loans.start_date')->orderByDesc('loans.id')->get();

        // Status is derived, not stored, so it filters after the book is built —
        // an older loan whose `status` column was never updated still lands in
        // the right bucket.
        if ($request->filled('status')) {
            $wantCleared = $request->status === 'cleared';
            $loans = $loans->filter(fn (Loan $l) => $l->is_cleared === $wantCleared)->values();
        }

        return $loans;
    }

    /**
     * A loan outside the viewer's company is not theirs to open or collect on —
     * the same lock the index applies, enforced on the row itself.
     */
    private function assertVisible(Loan $loan): void
    {
        $authUser = Auth::user();

        if ($authUser->can('view all loan')) {
            return;
        }

        if ('employee' == Str::slug($authUser->getRoleNames()->first()) && $loan->user_id !== $authUser->id) {
            abort(403);
        }

        if (!empty($authUser->company_id) && $loan->user?->company_id !== $authUser->company_id) {
            abort(403);
        }
    }

    /**
     * Page an already-built collection.
     *
     * The book is assembled in memory (outstanding, "paid so far" and status are
     * all derived, so none of them can be paged in SQL), and each table gets its
     * own page key so turning one leaves the others where they were.
     */
    private function paginateCollection($items, int $perPage, string $pageName, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query($pageName, 1));

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => $pageName]
        );
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('loans.create-modal');
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
                'message' => $validator->errors()->first()
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

                // The movement itself. Without this row the loan exists but its
                // history starts blank, so the register and the transaction trail
                // would both open on a loan that was apparently never paid out.
                $this->ledger->recordDisbursement($data, $journal->id);
                $this->ledger->applyStatedRemaining($data, $request->remaining_amount);
                $this->ledger->syncLoanBalance($data);
            });
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data created successfully.',
            'data' => $data
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return view('loans.edit-modal', compact('id'));
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
        $id = $request->id;
        $data = Loan::findOrFail($id);
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!'
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

                // Amount, date or account may all have moved — the disbursement
                // row is corrected in place so the loan keeps exactly one.
                $this->ledger->recordDisbursement($data, $journal->id);
                $this->ledger->applyStatedRemaining($data, $request->remaining_amount);
                $this->ledger->syncLoanBalance($data);
            });
        } catch (\Throwable $th) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Something went wrong! '. $th->getMessage(),
                ]);
            }

            return redirect()->back()->with('success', 'Something went wrong! '. $th->getMessage());
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data updated successfully.',
                'data' => $data
            ]);
        }

        return redirect()->back()->with('success', 'Data updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        try {
            $item = Loan::find($request->item_id);
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
                    'message' => 'Data Info Not Found!'
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
            'message' => 'Data deleted successfully.'
        ]);
    }
}
