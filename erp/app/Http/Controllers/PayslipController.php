<?php

namespace App\Http\Controllers;

use App\Exports\PayrollBookExport;
use App\Models\Company;
use App\Models\EmployeeSalary;
use App\Models\Payslip;
use App\Models\User;
use App\Services\PayslipBookService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Maatwebsite\Excel\Facades\Excel;

class PayslipController implements HasMiddleware
{
    public function __construct(private PayslipBookService $book)
    {
    }

    public static function middleware(): array
    {
        return [
            // Exports are a view, not a write: they hand back only what the same
            // user could already read, and scopedSlips() applies the same locks.
            new Middleware('permission:view payslip', only: ['index', 'exportExcel', 'exportPdf', 'statement']),
            new Middleware('permission:create payslip', only: ['create', 'store']),
            new Middleware('permission:edit payslip', only: ['edit', 'update']),
            new Middleware('permission:delete payslip', only: ['destroy']),
        ];
    }

    /**
     * The payslip desk — what has been issued, for whom, and what is still owed.
     *
     * Built the same way the Loans tab is: the cards, the register and every
     * export read one scoped collection, so they cannot disagree with each other.
     */
    public function index(Request $request)
    {
        $slips = $this->scopedSlips($request);

        $summary = $this->book->summary($slips);
        $register = $this->paginateCollection($slips, 15, 'page', $request);

        // Same self-only lock applied to the "generate new payslip" employee
        // picker, so a non-privileged user can't issue a payslip to (or
        // browse the name of) another employee.
        $employeeQuery = User::orderBy('name')->where('status', 'active')->role('employee');
        if (!auth()->user()->can('view all payslip')) {
            $employeeQuery->where('id', auth()->id());
        }
        $users = $employeeQuery->get();

        // The statement picker offers the months that actually have a payslip —
        // a month with nothing issued has no statement to open.
        $periods = $this->availablePeriods($request);

        // The salaries still without a payslip, for the "add new" form. Only
        // loaded when an employee is chosen, as before.
        $request_datas = null;
        if ($request->filled('user_id')) {
            $issuedSalaryIds = Payslip::where('user_id', $request->user_id)
                ->pluck('employee_salary_id')
                ->filter()
                ->toArray();

            $request_datas = EmployeeSalary::where('user_id', $request->user_id)
                ->when(!empty($issuedSalaryIds), fn ($q) => $q->whereNotIn('id', $issuedSalaryIds))
                ->orderBy('id', 'ASC')
                ->get();
        }

        $emp_salaries = EmployeeSalary::orderBy('id')->get();
        $paymentMethods = \App\Models\Bank::orderBy('name')->get();

        return view('payslips.index', compact(
            'slips',
            'summary',
            'register',
            'periods',
            'request_datas',
            'users',
            'emp_salaries',
            'paymentMethods'
        ));
    }

    /** The register as a spreadsheet, exactly as filtered on screen. */
    public function exportExcel(Request $request)
    {
        [$sheet, $scopeLabel, $filterLabel] = $this->exportPayload($request);

        return Excel::download(
            new PayrollBookExport($sheet, $scopeLabel, $filterLabel),
            $sheet['filename'] . '-' . now()->format('Ymd-Hi') . '.xlsx'
        );
    }

    /**
     * The register as a printable report — a browser print page in the same
     * format as the party statement and the loan reports, not a rendered PDF.
     */
    public function exportPdf(Request $request)
    {
        [$sheet, $scopeLabel, $filterLabel, $slips] = $this->exportPayload($request);

        $summary = $this->book->summary($slips);

        return view('payroll.print-report', [
            'sheet'       => $sheet,
            'scopeLabel'  => $scopeLabel,
            'filterLabel' => $filterLabel,
            'company'     => $this->exportCompany($request),
            'meta'        => [
                'Payslips'  => number_format($summary['count']),
                'Employees' => number_format($summary['employees']),
            ],
            // Closing figure last: the strip fills its final card.
            'cards' => [
                ['label' => 'Gross', 'value' => '৳' . number_format($summary['gross'], 2),
                 'note' => $summary['count'] . ' payslip(s)'],
                ['label' => 'Additions', 'value' => '৳' . number_format($summary['additions'], 2), 'tone' => '#16a34a',
                 'note' => 'overtime, bonus, adjustments'],
                ['label' => 'Deductions', 'value' => '৳' . number_format($summary['deductions'], 2), 'tone' => '#dc2626',
                 'note' => 'loans, advances, absence'],
                ['label' => 'Net Payable', 'value' => '৳' . number_format($summary['net'], 2),
                 'note' => $this->book->statusSplit($summary['by_status'])],
            ],
            'note' => 'Each row is one payslip and carries that month\'s own figures, not a running balance, which is '
                . 'why every money column sums. Gross plus Additions less Deductions is the Net — overtime, bonus '
                . 'and salary adjustments are why gross alone does not reach it. "Still Due" is the net less '
                . 'whatever has been paid against it, so Net less Still Due is exactly what has gone out. A payslip '
                . 'reads as paid, part-paid or accrued from its payment schedule rather than from a status column.',
        ]);
    }

    /**
     * One payslip as a statement — the document handed to the employee.
     *
     * Reached both from the register's print action and from the Employee/Month
     * picker at the top of the desk.
     */
    public function statement(Request $request, $role, $id)
    {
        $slip = Payslip::with([
            'user' => fn ($q) => $q->withTrashed()->with('company:id,name,short_name'),
            'employee_salary.schedules',
            'employee_salary.bank:id,name',
        ])->findOrFail($id);

        $this->assertVisible($slip);

        return view('payslips.print-statement', [
            'slip'    => $slip,
            'salary'  => $slip->employee_salary,
            'company' => $slip->user?->company ?: $this->exportCompany($request),
            'book'    => $this->book,
        ]);
    }

    /**
     * The Employee + Month picker's target: whichever payslip that person holds
     * for that month.
     */
    public function statementLookup(Request $request, $role)
    {
        $slip = $this->scopedSlips($request)
            ->first(fn (Payslip $s) => (int) $s->user_id === (int) $request->user_id
                && $this->book->period($s) === $request->period);

        if (! $slip) {
            return back()->with('error', 'No payslip has been issued to that employee for that month.');
        }

        return redirect()->route('role.payslips.statement', ['role' => $role, 'payslip' => $slip->id]);
    }

    /* ===================================================== scope & helpers */

    /**
     * The register the page is currently showing — one definition of "in scope",
     * shared by the screen and by every export, so a downloaded file can never
     * disagree with the screen it came from or reach further than it.
     */
    private function scopedSlips(Request $request): Collection
    {
        $query = Payslip::select('payslips.*')
            ->join('users', 'users.id', '=', 'payslips.user_id')
            ->join('employee_salaries', 'employee_salaries.id', '=', 'payslips.employee_salary_id')
            ->with([
                'user' => fn ($q) => $q->withTrashed()->select('id', 'name', 'employee_id_no', 'company_id')->with('company:id,name,short_name'),
                'employee_salary.schedules',
                'employee_salary.bank:id,name',
            ]);

        // Users without "view all payslip" can only see their own payslips.
        if (!auth()->user()->can('view all payslip')) {
            $query->where('payslips.user_id', auth()->id());
        } elseif ($request->filled('company_id')) {
            // Payroll toolbar company chip — only honoured for users who are
            // allowed to see every payslip in the first place.
            $query->where('users.company_id', (int) $request->company_id);
        }

        if ($request->filled('user_id')) {
            $query->where('payslips.user_id', $request->user_id);
        }

        if ($request->filled('period')) {
            [$year, $month] = array_pad(explode('-', $request->period), 2, null);
            $query->where('employee_salaries.year', (int) $year)
                ->whereRaw('CAST(employee_salaries.month AS UNSIGNED) = ?', [(int) $month]);
        }

        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('users.name', 'like', $term)
                    ->orWhere('users.employee_id_no', 'like', $term)
                    ->orWhere('payslips.payslip_number', 'like', $term);
            });
        }

        $slips = $query
            ->orderByDesc('employee_salaries.year')
            ->orderByRaw('CAST(employee_salaries.month AS UNSIGNED) DESC')
            ->orderBy('users.name')
            ->get();

        // Status is derived from the payment schedules, not stored, so it filters
        // after the register is built — the same way Salary Manage decides
        // whether a salary is paid, partial or still accrued.
        if ($request->filled('status')) {
            $slips = $slips->filter(fn (Payslip $s) => $this->book->status($s) === $request->status)->values();
        }

        return $slips;
    }

    /** Months that actually have a payslip, newest first, for the picker. */
    private function availablePeriods(Request $request): Collection
    {
        $query = Payslip::join('users', 'users.id', '=', 'payslips.user_id')
            ->join('employee_salaries', 'employee_salaries.id', '=', 'payslips.employee_salary_id');

        if (!auth()->user()->can('view all payslip')) {
            $query->where('payslips.user_id', auth()->id());
        } elseif ($request->filled('company_id')) {
            $query->where('users.company_id', (int) $request->company_id);
        }

        return $query
            ->selectRaw('employee_salaries.year as y, CAST(employee_salaries.month AS UNSIGNED) as m')
            ->distinct()
            ->orderByDesc('y')
            ->orderByDesc('m')
            ->get()
            ->map(fn ($r) => sprintf('%04d-%02d', (int) $r->y, (int) $r->m));
    }

    /** What both exports need: the sheet plus the context lines that make a file readable later. */
    private function exportPayload(Request $request): array
    {
        $slips = $this->scopedSlips($request);
        $withCompany = $slips->pluck('user.company_id')->filter()->unique()->count() > 1;

        return [
            $this->book->sheet($slips, $withCompany),
            $this->scopeLabel($request, $slips),
            $this->filterLabel($request),
            $slips,
        ];
    }

    private function scopeLabel(Request $request, Collection $slips): string
    {
        if ($request->filled('company_id')) {
            return Company::find((int) $request->company_id)?->name ?: 'Company #' . $request->company_id;
        }

        $names = $slips->pluck('user.company')->filter()->unique('id')
            ->map(fn ($c) => $c->short_name ?: $c->name);

        if ($names->isEmpty()) {
            return 'All companies';
        }

        return $names->count() === 1 ? $names->first() : 'All companies — ' . $names->implode(', ');
    }

    /**
     * The filters that were on, spelled out. A file that says "every payslip"
     * when it holds one month's is worse than one with no caption.
     */
    private function filterLabel(Request $request): string
    {
        $bits = [];

        if ($request->filled('user_id')) {
            $bits[] = 'Employee: ' . (User::withTrashed()->find($request->user_id)?->name ?: '#' . $request->user_id);
        }

        if ($request->filled('period')) {
            $bits[] = 'Month: ' . $this->book->periodLabel($request->period);
        }

        if ($request->filled('status')) {
            $bits[] = 'Status: ' . $request->status . ' only';
        }

        if ($request->filled('search')) {
            $bits[] = 'Search: "' . $request->search . '"';
        }

        return $bits ? implode(' · ', $bits) : 'No filters — every payslip';
    }

    /** The letterhead a printed document goes out under. */
    private function exportCompany(Request $request)
    {
        if ($request->filled('company_id')) {
            return Company::find((int) $request->company_id) ?: Company::first();
        }

        return auth()->user()->company ?: Company::first();
    }

    /** A payslip outside the viewer's reach is not theirs to open. */
    private function assertVisible(Payslip $slip): void
    {
        $authUser = auth()->user();

        if ($authUser->can('view all payslip')) {
            return;
        }

        if ((int) $slip->user_id !== (int) $authUser->id) {
            abort(403);
        }
    }

    /**
     * Page an already-built collection — status is derived, so it cannot be
     * paged in SQL without the register and the cards disagreeing.
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


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('payslips.create-modal');
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
            'employee_salary_id' => 'required',
            'issue_date' => 'required',
            'payslip_number' => 'required',
            'pdf_path' => 'required',
            'bank_id' => 'exists:banks,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        try {
            $data = Payslip::updateOrCreate([
                'user_id' => $request->user_id,
                'employee_salary_id' => $request->employee_salary_id,
                'payslip_number' => $request->payslip_number,
                'issue_date' => $request->issue_date
            ]);

            $pdf_path = $request->file('pdf_path');
            if ($pdf_path) {
                $pdf_path_name = uniqid() . '.' . strtolower($pdf_path->getClientOriginalExtension());
                $upload_path = 'image/payslip/';
                if (!file_exists(public_path($upload_path))) {
                    mkdir(public_path($upload_path), 0777, true);
                }
                $success = $pdf_path->move(public_path($upload_path), $pdf_path_name);
                if ($success) {
                    if (!empty($data->pdf_path) && file_exists(public_path($data->pdf_path))) {
                        unlink(public_path($data->pdf_path));
                    }
                    $data->pdf_path = $upload_path . $pdf_path_name;
                }
            }
            $data->save();

            $empSalary = EmployeeSalary::find($request->employee_salary_id);
            $month = $empSalary->month;
            $year = $empSalary->year;
            $user = User::find($request->user_id);

            // ── JOURNAL (auto) ────────────────────────────────────────
                $salaryExpenseAccount = \App\Models\Account::where('code', config('accounts.salary_expense'))->firstOrFail();
                
                $salaryPayableAccount = \App\Models\Account::where('code', config('accounts.salary_payable'))->firstOrFail();

                $journal = \App\Models\JournalEntry::create([
                    'company_id'  => auth()->user()->company_id ?? 2,
                    'created_by'  => auth()->id(),
                    'date'        => $request->issue_date,
                    'reference'   => 'SAL-' . $request->user_id . '-' . $month . '-' . $year,
                    'source'      => 'salary',
                    'source_id'   => $empSalary->id,
                    'description' => 'Salary — ' . ($user->name ?? 'Employee') . ' (' . $month . '/' . $year . ')',
                ]);

                // Debit: Salary Expense — always, full net salary
                \App\Models\JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $salaryExpenseAccount->id,
                    'debit'            => $empSalary->net_salary ?? 0,
                    'credit'           => 0,
                    'note'             => 'Net salary — ' . ($user->name ?? 'Employee'),
                ]);

                // Credit: Bank (if paid) OR Salary Payable (if unpaid)
                if (in_array($request->status, ['paid', 'Paid'])) {
                    // Must have a bank linked for paid salary
                    if (!$request->bank_id) {
                        throw new \Exception('Bank account is required when salary status is paid.');
                    }
                    $bank = \App\Models\Bank::find($request->bank_id);
                    if (!$bank || !$bank->account_id) {
                        throw new \Exception('Bank is not linked to a chart-of-accounts account.');
                    }
                    \App\Models\JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id'       => $bank->account_id,
                        'debit'            => 0,
                        'credit'           => $empSalary->net_salary ?? 0,
                        'note'             => 'Salary paid via ' . $bank->name . ' — ' . ($user->name ?? 'Employee')    ,
                    ]);
                } else {
                    // Unpaid — goes to salary payable liability
                    \App\Models\JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id'       => $salaryPayableAccount->id,
                        'debit'            => 0,
                        'credit'           => $empSalary->net_salary ?? 0,
                        'note'             => 'Salary payable — ' . ($user->name ?? 'Employee'),
                    ]);
                }
                // ── END JOURNAL ───────────────────────────────────────────

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
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
        return view('payslips.edit-modal', compact('id'));
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
        $data = Payslip::findOrFail($id);
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!'
            ]);
        }
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'employee_salary_id' => 'required',
            'issue_date' => 'required',
            'payslip_number' => 'required',
            'pdf_path' => 'nullable'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);
        }
        try {
            // Handle pdf_path upload
            if ($request->hasFile('pdf_path')) {
                $upload_path = 'image/payslip/';
                if (!file_exists(public_path($upload_path))) {
                    mkdir(public_path($upload_path), 0777, true);
                }

                // Delete old pdf_path
                if (!empty($company->pdf_path) && file_exists(public_path($company->pdf_path))) {
                    unlink(public_path($company->pdf_path));
                }

                $file = $request->file('pdf_path');
                $filename = uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path($upload_path), $filename);
                $data->pdf_path = $upload_path . $filename;
            }

            $data->update([
                'user_id' => $request->user_id,
                'employee_salary_id' => $request->employee_salary_id,
                'payslip_number' => $request->payslip_number,
                'issue_date' => $request->issue_date
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data updated successfully.',
            'data' => $data
        ]);
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
            $item = Payslip::find($request->item_id);
            if ($item) {
                $item->delete();
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
