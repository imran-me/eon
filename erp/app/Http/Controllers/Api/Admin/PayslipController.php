<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeSalary;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PayslipController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Payslip::select('payslips.*')
            ->join('users', 'users.id', '=', 'payslips.user_id')
            ->join('employee_salaries', 'employee_salaries.id', '=', 'payslips.employee_salary_id')
            ->orderBy('users.name', 'asc')
            ->orderBy('payslips.id', 'asc');

        $request_datas = null;
        if ($request->has('user_id') && !empty($request->user_id)) {
            $query->where('payslips.user_id', $request->user_id);

            $issuedSalaryIds = Payslip::where('user_id', $request->user_id)
                ->pluck('employee_salary_id')
                ->filter()
                ->toArray();

            $request_datas = EmployeeSalary::where('user_id', $request->user_id)
                ->when(!empty($issuedSalaryIds), function ($q) use ($issuedSalaryIds) {
                    return $q->whereNotIn('id', $issuedSalaryIds);
                })
                ->orderBy('id', 'ASC')
                ->get();
        }
        if ($request->has('employee_salary_id') && !empty($request->employee_salary_id)) {
            $query->where('payslips.employee_salary_id', $request->employee_salary_id);
        }
        if (!empty($request->issue_date)) {
            $query->whereDate('payslips.issue_date', $request->issue_date);
        }
        if (!empty($request->payslip_number)) {
            $query->whereDate('payslips.payslip_number', $request->payslip_number);
        }

        $datas = $query->paginate(30);
        // $users = User::orderBy('name')->where('status', 'active')->role('employee')->get();
        // $emp_salaries = EmployeeSalary::orderBy('id')->get();
        // $paymentMethods = \App\Models\Bank::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Payslips retrieved successfully.',
            'data' => $datas,
            'request_datas' => $request_datas,
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
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
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
