<?php

namespace App\Http\Controllers;

use App\Exports\PayrollBookExport;
use App\Models\AdvanceSalary;
use App\Models\Company;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Services\AdvanceBookService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class AdvanceSalaryController implements HasMiddleware
{
    public function __construct(private AdvanceBookService $book)
    {
    }

    public static function middleware(): array
    {
        return [
            // Exports are a view, not a write: they hand back only what the same
            // user could already read, and scopedAdvances() applies the same locks.
            new Middleware('permission:view advance salary', only: ['index', 'show', 'schedule', 'paymentSlip', 'downloadPaymentSlip', 'exportExcel', 'exportPdf']),
            new Middleware('permission:create advance salary', only: ['create', 'store']),
            new Middleware('permission:edit advance salary', only: ['edit', 'update', 'schedulePay']),
            new Middleware('permission:delete advance salary', only: ['destroy']),
        ];
    }

    /**
     * The advance desk — one page, three views of the same book.
     *
     * Built exactly as the Loans tab is: the tiles say where the book stands,
     * "Employees with advances" folds it by person, the register keeps one row
     * per advance, and the trail is every movement that produced those figures.
     * All four read one scoped collection, so they cannot disagree.
     */
    public function index(Request $request)
    {
        $advances = $this->scopedAdvances($request);

        $summary = $this->book->summary($advances);
        $series = $this->book->series($advances);
        $byEmployee = $this->book->byEmployee($advances);
        $movements = $this->book->movements($advances);

        $employeeBook = $this->paginateCollection($byEmployee, 8, 'emp_page', $request);
        $register = $this->paginateCollection($advances, 10, 'reg_page', $request);
        $transactions = $this->paginateCollection($movements, 15, 'txn_page', $request);

        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();
        $userCompanyId = $authUser->company_id;

        // Same scoping applied to the employee picker used when creating a
        // new advance salary request: plain employees without "view all
        // advance salary" can only pick themselves; other roles without it
        // are locked to their own company.
        $employeeQuery = User::orderBy('name')->where('status', 'active')->role('employee');
        if ($authUser->hasRole('employee') && !$authUser->can('view all advance salary')) {
            $employeeQuery->where('id', $authUser->id);
        } elseif (!$authUser->can('view all advance salary') && !empty($userCompanyId)) {
            $employeeQuery->where('company_id', $userCompanyId);
        }
        $users = $employeeQuery->get();

        // The months that actually have an advance, for the filter.
        $periods = $advances->pluck('month')->filter()->unique()->sortDesc()->values();

        return view('advance-salaries.index', compact(
            'advances',
            'summary',
            'series',
            'employeeBook',
            'register',
            'transactions',
            'periods',
            'users'
        ));
    }

    /** One advance's whole life — the body of the detail modal. */
    public function show(Request $request, $role, $id)
    {
        $advance = AdvanceSalary::with([
            'user' => fn ($q) => $q->withTrashed()->with('company:id,name,short_name'),
            'recoveries',
        ])->findOrFail($id);

        $this->assertVisible($advance);

        return view('advance-salaries.detail', [
            'advance' => $advance,
            'book'    => $this->book,
        ])->render();
    }

    /** One of the three tables as a spreadsheet, exactly as filtered on screen. */
    public function exportExcel(Request $request)
    {
        [$sheet, $scopeLabel, $filterLabel] = $this->exportPayload($request);

        return Excel::download(
            new PayrollBookExport($sheet, $scopeLabel, $filterLabel),
            $sheet['filename'] . '-' . now()->format('Ymd-Hi') . '.xlsx'
        );
    }

    /** The same table as a printable report, in the shared payroll format. */
    public function exportPdf(Request $request)
    {
        [$sheet, $scopeLabel, $filterLabel, $advances] = $this->exportPayload($request);

        $summary = $this->book->summary($advances);

        return view('payroll.print-report', [
            'sheet'       => $sheet,
            'scopeLabel'  => $scopeLabel,
            'filterLabel' => $filterLabel,
            'company'     => $this->exportCompany($request),
            'meta'        => [
                'Advances in scope' => number_format($summary['advance_count']),
                'Still outstanding' => number_format($summary['open_count']),
            ],
            // Closing figure last: the strip fills its final card.
            'cards' => [
                ['label' => 'Advance Outstanding', 'value' => '৳' . number_format($summary['outstanding'], 2), 'tone' => '#dc2626',
                 'note' => $summary['holders'] ? $summary['holders'] . ' holding an advance' : 'nobody is holding one'],
                ['label' => 'Total Released', 'value' => '৳' . number_format($summary['released'], 2),
                 'note' => $summary['advance_count'] . ' advance(s), all time'],
                ['label' => 'Awaiting Release', 'value' => '৳' . number_format($summary['awaiting'], 2), 'tone' => '#b45309',
                 'note' => $summary['awaiting_count'] . ' approved, not yet paid out'],
                ['label' => 'Recovered', 'value' => '৳' . number_format($summary['recovered'], 2),
                 'note' => $summary['released'] > 0 ? $summary['recovered_pct'] . '% of everything released' : 'nothing released yet'],
            ],
            'note' => 'An advance is approved first and released later, and between those two the money is owed TO the '
                . 'employee rather than by them — which is why "Awaiting Release" is its own figure and never lands in '
                . 'Outstanding. Once released, recovery comes off a payslip, so the recovery rows are the payslips that '
                . 'withheld it. Outstanding is released less recovered, and "Recovered" is released less outstanding, '
                . 'which is why the four figures reconcile. A balance shown against a movement is that advance\'s '
                . 'position at that moment and is deliberately not summed.',
        ]);
    }

    /* ===================================================== scope & helpers */

    /**
     * The book the page is currently showing — one definition of "in scope",
     * shared by the screen and by every export, so a downloaded file can never
     * disagree with the screen it came from or reach further than it.
     */
    private function scopedAdvances(Request $request): Collection
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        if ($authUser->hasRole('employee') && !$authUser->hasPermissionTo('view all advance salary')) {
            $request->merge(['user_id' => Auth::id()]);
        }

        $query = AdvanceSalary::select('advance_salaries.*')
            ->join('users', 'users.id', '=', 'advance_salaries.user_id')
            ->with([
                'user' => fn ($q) => $q->withTrashed()->select('id', 'name', 'employee_id_no', 'company_id', 'status')->with('company:id,name,short_name'),
                'recoveries',
            ]);

        // Users without "view all advance salary" are locked to their own
        // company, same convention as Salary Manage / Salary Template / Loan.
        $userCompanyId = $authUser->company_id;
        if (!$authUser->can('view all advance salary') && !empty($userCompanyId)) {
            $query->where('users.company_id', $userCompanyId);
        } elseif ($request->filled('company_id')) {
            // Payroll toolbar company chip — only honoured for users who are
            // allowed to see every company in the first place.
            $query->where('users.company_id', (int) $request->company_id);
        }

        if ($request->filled('user_id')) {
            $query->where('advance_salaries.user_id', $request->user_id);
        }

        if ($request->filled('month')) {
            $query->where('advance_salaries.month', $request->month);
        }

        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('users.name', 'like', $term)
                    ->orWhere('users.employee_id_no', 'like', $term)
                    ->orWhere('advance_salaries.reason', 'like', $term);
            });
        }

        $advances = $query
            ->orderByDesc('advance_salaries.month')
            ->orderByDesc('advance_salaries.id')
            ->get();

        // State is derived from the money, not stored, so it filters after the
        // book is built — the `status` column only ever says Approved here, and
        // could not tell an unreleased advance from an unrecovered one.
        if ($request->filled('state')) {
            $advances = $advances->filter(fn (AdvanceSalary $a) => $a->state === $request->state)->values();
        }

        return $advances;
    }

    /** An advance outside the viewer's reach is not theirs to open. */
    private function assertVisible(AdvanceSalary $advance): void
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        if ($authUser->can('view all advance salary')) {
            return;
        }

        if ($authUser->hasRole('employee') && (int) $advance->user_id !== (int) $authUser->id) {
            abort(403);
        }

        if (!empty($authUser->company_id) && $advance->user?->company_id !== $authUser->company_id) {
            abort(403);
        }
    }

    /** What both exports need: the sheet plus the context lines. */
    private function exportPayload(Request $request): array
    {
        $table = $request->query('table', 'register');

        if (!in_array($table, AdvanceBookService::tables(), true)) {
            abort(404, 'There is no such advance table to export.');
        }

        $advances = $this->scopedAdvances($request);
        $withCompany = $advances->pluck('user.company_id')->filter()->unique()->count() > 1;

        return [
            $this->book->sheet($table, $advances, $withCompany),
            $this->scopeLabel($request, $advances),
            $this->filterLabel($request),
            $advances,
        ];
    }

    private function scopeLabel(Request $request, Collection $advances): string
    {
        if ($request->filled('company_id')) {
            return Company::find((int) $request->company_id)?->name ?: 'Company #' . $request->company_id;
        }

        $names = $advances->pluck('user.company')->filter()->unique('id')
            ->map(fn ($c) => $c->short_name ?: $c->name);

        if ($names->isEmpty()) {
            return 'All companies';
        }

        return $names->count() === 1 ? $names->first() : 'All companies — ' . $names->implode(', ');
    }

    /** The filters that were on, spelled out. */
    private function filterLabel(Request $request): string
    {
        $bits = [];

        if ($request->filled('user_id')) {
            $bits[] = 'Employee: ' . (User::withTrashed()->find($request->user_id)?->name ?: '#' . $request->user_id);
        }

        if ($request->filled('month')) {
            $bits[] = 'Month: ' . $this->book->monthLabel($request->month);
        }

        if ($request->filled('state')) {
            $bits[] = 'State: ' . $request->state . ' only';
        }

        if ($request->filled('search')) {
            $bits[] = 'Search: "' . $request->search . '"';
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
     * Page an already-built collection — outstanding and state are derived, so
     * neither can be paged in SQL without the tables and the tiles disagreeing.
     */
    private function paginateCollection(Collection $items, int $perPage, string $pageName, Request $request): LengthAwarePaginator
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

    public function create()
    {
        return view('advance-salaries.create-modal');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'amount'  => 'required',
            'month'   => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        try {
            $data = AdvanceSalary::create([
                'user_id'       => $request->user_id,
                'amount'        => $request->amount,
                'month'         => $request->month,
                'schedule_date' => $request->schedule_date ?: null,
                'reason'        => $request->reason,
                'status'        => $request->status,
            ]);

            if ($request->filled('schedule_date')) {
                $data->schedules()->create([
                    'type'           => 'pay',
                    'party_name'       => $data->user->name ?? 'Employee',
                    'party_type'     => 'employee',
                    'party_id'       => $request->user_id,
                    'amount'         => $request->amount,
                    'scheduled_date' => $request->schedule_date,
                    'status'         => 'pending',
                    'source_label'   => 'Advance Salary',
                    'created_by'     => Auth::id(),
                ]);
            }
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Data created successfully.', 'data' => $data]);
    }

    public function edit($id)
    {
        return view('advance-salaries.edit-modal', compact('id'));
    }

    public function update(Request $request, $id)
    {
        $id   = $request->id;
        $data = AdvanceSalary::findOrFail($id);

        $request->validate([
            'user_id' => 'required',
            'amount'  => 'required',
            'month'   => 'required',
        ]);

        // Captured before the update — approval is the moment money actually
        // leaves the company (a Pending advance is just a request, nothing
        // has moved yet), so the journal only posts on the transition INTO
        // Approved, never on a Pending row being saved again or an
        // already-Approved row being re-saved (which would double-post).
        $wasApproved = $data->status === 'Approved';

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $data, $wasApproved) {
            $data->update([
                'user_id' => $request->user_id,
                'amount'  => $request->amount,
                'month'   => $request->month,
                'reason'  => $request->reason,
                'status'  => $request->status,
            ]);

            if (!$wasApproved && $data->status === 'Approved') {
                // ── JOURNAL (auto) ────────────────────────────────────────
                // Mirrors LoanController::store()'s pattern: Dr the
                // receivable-style asset (Prepaid Expense — an advance
                // against salary not yet earned), Cr Salary Payable as the
                // fallback since advance_salaries has no bank_id column to
                // record which account the cash actually left from.
                $prepaidExpenseAccount = \App\Models\Account::where('code', config('accounts.prepaid_expense'))->firstOrFail();
                $salaryPayableAccount  = \App\Models\Account::where('code', config('accounts.salary_payable'))->firstOrFail();

                $journal = \App\Models\JournalEntry::create([
                    'company_id'  => $data->user->company_id ?? auth()->user()->company_id ?? 2,
                    'created_by'  => auth()->id(),
                    'date'        => now()->toDateString(),
                    'reference'   => 'ADV-' . str_pad($data->id, 5, '0', STR_PAD_LEFT),
                    'source'      => 'advance_salary',
                    'source_id'   => $data->id,
                    'description' => 'Advance salary approved — ' . ($data->user->name ?? 'Employee'),
                ]);

                \App\Models\JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $prepaidExpenseAccount->id,
                    'debit'            => $data->amount,
                    'credit'           => 0,
                    'note'             => 'Advance salary given to employee',
                    'party_type'       => 'employee',
                    'party_id'         => $data->user_id,
                ]);

                \App\Models\JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $salaryPayableAccount->id,
                    'debit'            => 0,
                    'credit'           => $data->amount,
                    'note'             => 'Advance salary disbursed',
                    'party_type'       => 'employee',
                    'party_id'         => $data->user_id,
                ]);
                // ── END JOURNAL ───────────────────────────────────────────
            }
        });

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Data updated successfully.', 'data' => $data]);
        }

        return redirect('/super-admin/airport')->with('success', 'Data updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        try {
            $item = AdvanceSalary::find($request->item_id);
            if ($item) {
                $item->delete();
            } else {
                return response()->json(['success' => false, 'message' => 'Data Info Not Found!']);
            }
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Data deleted successfully.']);
    }

    public function schedule($role, $id)
    {
        $advance = AdvanceSalary::with(['user', 'schedules'])->findOrFail($id);
        $this->authorizeAdvanceSalaryAccess($advance);

        return view('advance-salaries.schedule', compact('advance'));
    }

    public function schedulePay(Request $request, $role, $id)
    {
        $schedule = PaymentSchedule::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'paid_date' => 'required|date',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $schedule->update([
            'paid_amount' => $schedule->amount,
            'paid_date'   => $request->paid_date,
            'status'      => 'paid',
        ]);

        // advance salary payment_status update
        $advance = AdvanceSalary::find($schedule->schedulable_id);
        if ($advance) {
            $advance->update(['payment_status' => 'Paid', 'paid_at' => $request->paid_date]);
        }

        return response()->json(['success' => true, 'message' => 'Payment recorded successfully.']);
    }

    public function paymentSlip($role, $id)
    {
        $data = AdvanceSalary::with('user.company')->findOrFail($id);
        $this->authorizeAdvanceSalaryAccess($data);

        return view('advance-salaries.payment-slip', compact('data'));
    }

    public function downloadPaymentSlip($role, $id)
    {
        $data = AdvanceSalary::with('user.company')->findOrFail($id);
        $this->authorizeAdvanceSalaryAccess($data);

        $logoUrl  = asset($data->user->company->logo ?? 'images/site-setting/69401c60d0949.png');
        $logoData = @file_get_contents($logoUrl);

        if ($logoData === false) {
            $logoData = @file_get_contents('https://epal.com.bd/images/site-setting/69401c60d0949.png');
        }

        $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData ?: '');
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('advance-salaries.payment-slip-pdf', compact('data', 'logoBase64'));

        $employeeName = str_replace(' ', '_', $data->user->name ?? 'Employee');

        return $pdf->download("Advance_Salary_Slip_{$employeeName}_{$data->month}.pdf");
    }

    private function authorizeAdvanceSalaryAccess(AdvanceSalary $data): void
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        if ($authUser->hasRole('employee') && (int) $data->user_id !== (int) Auth::id()) {
            abort(403);
        }
    }
}
