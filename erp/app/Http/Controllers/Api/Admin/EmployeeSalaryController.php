<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdvanceSalary;
use App\Models\Attendance;
use App\Models\EmployeeSalary;
use App\Models\Payslip;
use App\Models\Payment;
use App\Models\SalaryTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Auth;

class EmployeeSalaryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if('employee' == Str::slug(Auth::user()->getRoleNames()->first())) {
            $request->merge(['user_id' => auth()->id()]);
        }
        $query = EmployeeSalary::select('employee_salaries.*')
            ->join('users', 'users.id', '=', 'employee_salaries.user_id')
            ->join('salary_templates', 'salary_templates.id', '=', 'employee_salaries.salary_template_id')
            // ->orderBy('users.name', 'asc')
            // ->orderBy('salary_templates.name', 'asc')
            ->where('users.status', 'active')
            ->orderBy('employee_salaries.id', 'desc');

        if ($request->has('user_id') && !empty($request->user_id)) {
            $query->where('employee_salaries.user_id', $request->user_id);
        }

        if ($request->has('salary_template_id') && !empty($request->salary_template_id)) {
            $query->where('employee_salaries.salary_template_id', $request->salary_template_id);
        }

        if ($request->filled('date')) {
            [$year, $month] = explode('-', $request->date);
            $query->where('employee_salaries.year', (int) $year)
                ->where('employee_salaries.month', str_pad($month, 2, '0', STR_PAD_LEFT));
        }

        if ($request->filled('status')) {
            $query->whereDate('employee_salaries.status', $request->status);
        }

        $datas = $query->paginate(20);
        // $users = User::orderBy('name')->where('is_super_admin', 0)->where('status', 'active')->role('employee')->get();
        // $templates = SalaryTemplate::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Data retrieved successfully.',
            'data' => $datas
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
            'loan_id' => 'nullable',
            'advance_salary_id' => 'nullable',
            'date' => 'required',
            'salary_generation_date' => 'required',
            'payment_method' => 'required',
            'status' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ]);
        }
        $employeeSalary = EmployeeSalary::where('user_id', $request->user_id)
            ->where('month', explode('-', $request->date)[1])
            ->where('year', explode('-', $request->date)[0])
            ->first();

        if ($employeeSalary) {
            return response()->json([
                'success' => false,
                'message' => 'Salary for this employee already exists for the selected month and year.'
            ]);
        }

        try {
            DB::transaction(function () use ($request) {
                [$year, $month] = explode('-', $request->date);
                $user = User::find($request->user_id);
                $empSalary = EmployeeSalary::create(
                    [
                        'user_id' => $request->user_id,
                        'salary_template_id' => $user->salary_template_id,
                        'loan_id' => $request->loan_id,
                        'advance_salary_id' => $request->advance_salary_id,
                        'month' => (string) $month,
                        'year' => (int) $year,
                        'bonus_label' => $request->bonus_label,
                        'bonus_amount' => $request->bonus_amount,
                        'gross_salary' => $request->gross_salary,
                        'loan_deduction' => $request->loan_deduction ?? 0,
                        'advance_salary_deduction' => $request->advance_salary_deduction ?? 0,
                        'early_leave_deduction' => $request->early_leave_deduction ?? 0,
                        'over_time' => $request->over_time ?? 0,
                        'over_time_days' => $request->over_time_days ?? 0,
                        'absent_deduction' => $request->absent_deduction ?? 0,
                        'leave_deduction' => $request->leave_deduction ?? 0,
                        'late_deduction' => $request->late_deduction ?? 0,
                        'salary_adjustment' => $request->salary_adjustment ?? 0,
                        'total_deductions' => $request->total_deductions ?? 0,
                        'net_salary' => $request->net_salary ?? 0,
                        'salary_generation_date' => $request->salary_generation_date,
                        'payment_method' => $request->payment_method,
                        'status' => $request->status,
                        'notes' => $request->notes ?? null
                    ]
                );


                $loan = \DB::table('loans')->where('id', $request->loan_id)->first();
                if ($loan) {
                    // Calculate the new balance
                    $newRemainingAmount = $loan->remaining_amount - $request->loan_deduction;

                    // Determine status: 'completed' if 0 or less, otherwise 'running'
                    $status = ($newRemainingAmount <= 0) ? 'completed' : 'running';

                    \DB::table('loans')->where('id', $request->loan_id)->update([
                        'status'           => $status,
                        'remaining_amount' => max(0, $newRemainingAmount) // Ensures balance doesn't go negative
                    ]);
                }

                $record = DB::table('advance_salaries')->where('id', $request->advance_salary_id)->first();

                if ($record && $record->status == 'Approved') {
                    DB::table('advance_salaries')->where('id', $request->advance_salary_id)->update([
                        'payment_status' => 'Paid',
                        'paid_at' => now(),
                    ]);
                }

                // $havePayment = Payment::where('user_id', $empSalary->user_id)
                //     ->where('employee_salary_id', $empSalary->id)
                //     ->where('payment_date', $empSalary->payment_date)
                //     ->where('payment_method', $request->payment_method)
                //     ->first();

                // if ($havePayment) {
                //     $havePayment->update([
                //         'amount' => $empSalary->net_salary,
                //         'transaction_no' => $request->transaction_no,
                //         'notes' => $request->notes
                //     ]);
                // } else {
                //     Payment::where('user_id', $empSalary->user_id)->where('employee_salary_id', $empSalary->id)->delete();
                //     Payment::create([
                //         'user_id' => $empSalary->user_id,
                //         'employee_salary_id' => $empSalary->id,
                //         'payment_date' => $empSalary->payment_date,
                //         'payment_method' => $request->payment_method,
                //         'amount' => $empSalary->net_salary,
                //         'transaction_no' => $request->transaction_no,
                //         'notes' => $request->notes
                //     ]);
                // }
            });
            return response()->json([
                'success' => true,
                'message' => 'Employee salary created successfully.'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }
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
        $empSalary = EmployeeSalary::findOrFail($id);
        if (empty($empSalary)) {
            return response()->json([
                'success' => false,
                'message' => 'Employee salary not found!'
            ]);
        }
        $validated = $request->validate([
            'user_id' => 'required',
            'salary_template_id' => 'required',
            'date' => 'required',
            'payment_date' => 'required',
            'payment_method' => 'required',
            'status' => 'required'
        ]);

        try {
            DB::transaction(function () use ($request, $empSalary) {
                [$year, $month] = explode('-', $request->date);
                $empSalary->update([
                    'user_id' => $request->user_id,
                    'salary_template_id' => $request->salary_template_id,
                    'month' => (string) $month,
                    'year' => (int) $year,
                    'gross_salary' => $request->gross_salary,
                    'total_deductions' => $request->total_deductions ?? 0,
                    'net_salary' => $request->net_salary ?? 0,
                    'payment_date' => $request->payment_date,
                    'status' => $request->status
                ]);

                // ── JOURNAL UPDATE (auto) ─────────────────────────────────
                $salaryExpenseAccount = \App\Models\Account::where('code', config('accounts.salary_expense'))->firstOrFail();
                $salaryPayableAccount = \App\Models\Account::where('code', config('accounts.salary_payable'))->firstOrFail();

                $journal = \App\Models\JournalEntry::where('source', 'salary')
                                                ->where('source_id', $empSalary->id)
                                                ->first();

                if ($journal) {
                    $journal->items()->delete();
                    $journal->update([
                        'date'        => $request->payment_date,
                        'description' => 'Salary (edited) — ' . ($empSalary->user->name ?? 'Employee'),
                    ]);
                } else {
                    $journal = \App\Models\JournalEntry::create([
                        'company_id'  => auth()->user()->company_id ?? 2,
                        'created_by'  => auth()->id(),
                        'date'        => $request->payment_date,
                        'reference'   => 'SAL-' . $empSalary->user_id . '-' . $empSalary->month . '-' . $empSalary->year,
                        'source'      => 'salary',
                        'source_id'   => $empSalary->id,
                        'description' => 'Salary (edited) — ' . ($empSalary->user->name ?? 'Employee'),
                    ]);
                }

                // Debit: Salary Expense — always, full net salary
                \App\Models\JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $salaryExpenseAccount->id,
                    'debit'            => $empSalary->net_salary,
                    'credit'           => 0,
                    'note'             => 'Net salary — ' . ($empSalary->user->name ?? 'Employee'),
                ]);

                // Credit: Bank (if paid) OR Salary Payable (if unpaid)
                if (in_array($request->status, ['paid', 'Paid'])) {
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
                        'credit'           => $empSalary->net_salary,
                        'note'             => 'Salary paid via ' . $request->payment_method,
                    ]);
                } else {
                    \App\Models\JournalItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id'       => $salaryPayableAccount->id,
                        'debit'            => 0,
                        'credit'           => $empSalary->net_salary,
                        'note'             => 'Salary payable — ' . ($empSalary->user->name ?? 'Employee'),
                    ]);
                }
                // ── END JOURNAL ───────────────────────────────────────────

                $havePayment = Payment::where('user_id', $empSalary->user_id)
                    ->where('employee_salary_id', $empSalary->id)
                    ->where('payment_date', $empSalary->payment_date)
                    ->where('payment_method', $request->payment_method)
                    ->first();

                if ($havePayment) {
                    $havePayment->update([
                        'amount' => $empSalary->net_salary,
                        'transaction_no' => $request->transaction_no,
                        'notes' => $request->notes
                    ]);
                } else {
                    Payment::where('user_id', $empSalary->user_id)->where('employee_salary_id', $empSalary->id)->delete();
                    Payment::create([
                        'user_id' => $empSalary->user_id,
                        'employee_salary_id' => $empSalary->id,
                        'payment_date' => $empSalary->payment_date,
                        'payment_method' => $request->payment_method,
                        'amount' => $empSalary->net_salary,
                        'transaction_no' => $request->transaction_no,
                        'notes' => $request->notes
                    ]);
                }
            });
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data updated successfully.'
        ]);
    }

    public function show($role, $id)
    {
        $data = EmployeeSalary::findOrFail($id);
        return response()->json([
            'success' => true,
            'message' => 'Employee salary retrieved successfully.',
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
            $item = EmployeeSalary::find($request->item_id);
            if ($item) {

                DB::transaction(function () use ($item) {

                    // ── JOURNAL CLEANUP ───────────────────────────────────
                    $journal = \App\Models\JournalEntry::where('source', 'salary')
                        ->where('source_id', $item->id)
                        ->first();
                    if ($journal) {
                        $journal->items()->forceDelete();
                        $journal->forceDelete();
                    }
                    // ── END JOURNAL ───────────────────────────────────────

                    // ── PAYMENT SCHEDULE CLEANUP ───────────────────────────
                    \App\Models\PaymentSchedule::where('schedulable_type', EmployeeSalary::class)
                        ->where('schedulable_id', $item->id)
                        ->delete();
                    // ── END PAYMENT SCHEDULE ───────────────────────────────

                    // ── EMPLOYEE LEDGER CLEANUP ─────────────────────────────
                    \App\Models\EmployeeLedger::where('source_type', EmployeeSalary::class)
                        ->where('source_id', $item->id)
                        ->forceDelete();
                    // ── END EMPLOYEE LEDGER ─────────────────────────────────

                    Payment::where('user_id', $item->user_id)
                        ->where('employee_salary_id', $item->id)
                        ->delete();

                    $item->delete();
                });
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee salary not found!'
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
            'message' => 'Employee salary deleted successfully.'
        ]);
    }

    public function getEmployeeSalary(Request $request)
    {
        try {
            $issuedSalaryIds = Payslip::where('user_id', $request->user_id)
                ->pluck('employee_salary_id')
                ->filter()
                ->toArray();

            $data = EmployeeSalary::where('user_id', $request->user_id)
                ->when(!empty($issuedSalaryIds), function ($q) use ($issuedSalaryIds) {
                    return $q->whereNotIn('id', $issuedSalaryIds);
                })
                ->orderBy('id', 'ASC')
                ->get();
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'data' => $data,
            'success' => true,
            'message' => 'Data Found Successfully.'
        ]);
    }
    public function getAttendanceData(Request $request)
    {
        $userId = $request->user_id;
        $dateInput = $request->month; // This is "2026-01"

        if (!$userId || !$dateInput) {
            return response()->json(['absent' => 0, 'late' => 0]);
        }

        // Parsing "2026-01"
        $timestamp = strtotime($dateInput);
        $selectedYear = date('Y', $timestamp);
        $selectedMonth = date('m', $timestamp);

        $userId = $request->input('user_id');

        $fullMonthData = collect();
        // Adjusted summary to match the logic where Late is a subset of Present
        $summary = [
            'present' => 0,
            'absent' => 0,
            'leave' => 0,
            'holiday' => 0,
            'late' => 0,
            'total_days' => 0,
            'late_minutes' => 0,
            'early_out' => 0,
            'early_minutes' => 0,
            'overtime_days' => 0,
            'overtime_minutes' => 0
        ];

        if ($userId) {
            $user = User::with('shift')->find($userId);

            $dbAttendances = Attendance::with('shift', 'attendence_setting')
                ->whereMonth('date', $selectedMonth)
                ->whereYear('date', $selectedYear)
                ->where('user_id', $userId)
                ->get()
                ->keyBy('date');

            $startDate = \Carbon\Carbon::create($selectedYear, $selectedMonth, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
            $period = \Carbon\CarbonPeriod::create($startDate, $endDate);

            // Fallback shift from user if no attendance records exist yet
            $defaultShift = $user->shift ?? $dbAttendances->first()?->shift;

            // Fetch Holidays that overlap with the selected month
            $holidays = \DB::table('holidays')
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate]);
                })->get();
            // Fetch Approved Leaves for this user
            $leaves = \DB::table('leaves')
                ->where('user_id', $userId)
                ->where('status', 'approved')
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate]);
                })->get();

            foreach ($period as $date) {
                $dateString = $date->format('Y-m-d');
                $dayName = strtolower($date->format('l'));

                if (isset($dbAttendances[$dateString])) {
                    $record = $dbAttendances[$dateString];

                    // --- CONSISTENT LATE CALCULATION ---
                    if ($record->check_in && $record->attendence_setting) {
                        $checkInTime = \Carbon\Carbon::parse($dateString . ' ' . $record->check_in);
                        $minutes = (int) $record->attendence_setting->time_after_checkin;
                        // dd($record->shift->start_time);
                        $lateThreshold = \Carbon\Carbon::parse($dateString . ' ' . $record->shift->start_time)
                            ->addMinutes($minutes);
                        // late min count
                        $lateMinutes = (int) abs($checkInTime->diffInMinutes($lateThreshold, false));
                        if ($checkInTime->gt($lateThreshold) && $lateMinutes > 0) {
                            $record->status = 'Late ' . $lateMinutes . ' mins';
                            $summary['late']++;    // Increment Late
                            $summary['present']++; // Also increment Present (as they are physically there)
                            $summary['late_minutes'] += $lateMinutes; // Accumulate late minutes
                        } else {
                            $record->status = 'Present';
                            $summary['present']++;
                        }
                        if ($record->check_out && $record->attendence_setting) {
                            $checkOutTime = \Carbon\Carbon::parse($dateString . ' ' . $record->check_out);

                            $minutes = (int) $record->attendence_setting->time_after_checkout;
                            // dd($record->shift->start_time);
                            $shiftEnd = \Carbon\Carbon::parse($dateString . ' ' . $record->shift->end_time);

                            // Thresholds (assuming $minutes is 10 for early and 60 for late)
                            $earlyThreshold = $shiftEnd->copy()->subMinutes(10); // 6:50 PM
                            $overtimeThreshold = $shiftEnd->copy()->addMinutes(60); // 8:00 PM

                            $earlyMinutes = 0;
                            $overtimeMinutes = 0;

                            if ($checkOutTime->lessThan($earlyThreshold)) {
                                // 1. LEFT TOO EARLY (Before 6:50)
                                $earlyMinutes = (int) abs($checkOutTime->diffInMinutes($earlyThreshold, false));
                            } elseif ($checkOutTime->greaterThanOrEqualTo($overtimeThreshold)) {
                                // 2. WORKED OVERTIME (8:00 or later)
                                // We calculate from 7:00 PM to give them the full hour + extra
                                $overtimeMinutes = (int) abs($checkOutTime->diffInMinutes($shiftEnd, false));
                            } else {
                                // 3. WITHIN GRACE PERIOD (6:50 to 7:59)
                                $earlyMinutes = 0;
                                $overtimeMinutes = 0;
                            }
                            if ($checkOutTime->lt($earlyThreshold) && $earlyMinutes > 0) {
                                $record->out_status = 'Early Out ' . $earlyMinutes . ' mins';
                                $summary['early_out']++;    // Increment Early Out
                                $summary['present']++; // Also increment Present (as they are physically there)
                                $summary['early_minutes'] += $earlyMinutes; // Accumulate early minutes
                            }
                            // Overtime handling days
                            if ($checkOutTime->gte($overtimeThreshold) && $overtimeMinutes > 0) {
                                $record->out_status = 'Overtime ' . $overtimeMinutes . ' mins';
                                $summary['overtime_minutes'] += $overtimeMinutes; // Accumulate overtime minutes
                                $summary['overtime_days'] = ($summary['overtime_days'] ?? 0) + 1; // Count days with overtime
                            }
                        } else {
                            $status = strtolower($record->status);
                            if (array_key_exists($status, $summary)) {
                                $summary[$status]++;
                            }
                        }
                    } else {
                        $status = strtolower($record->status);
                        if (array_key_exists($status, $summary)) {
                            $summary[$status]++;
                        }
                    }
                } elseif ($leaveRecord = $leaves->first(fn($l) => $dateString >= $l->start_date && $dateString <= $l->end_date)) {
                    $record = (object)[
                        'date' => $dateString,
                        'shift' => $defaultShift,
                        'check_in' => null,
                        'check_out' => null,
                        'status' => 'Leave',
                        'note' => $leaveRecord->leave_time
                            ? 'Approved Early Leave (from ' . \Carbon\Carbon::parse($leaveRecord->leave_time)->format('h:i A') . ')'
                            : 'Approved Leave'
                    ];
                    $summary['leave']++;
                } else {
                    $holidayRecord = $holidays->first(fn($h) => $dateString >= $h->start_date && $dateString <= $h->end_date);
                    $isWeekend = $defaultShift && in_array($dayName, $defaultShift->holidays ?? []);

                    if ($holidayRecord || $isWeekend) {
                        $record = (object)[
                            'date' => $dateString,
                            'shift' => $defaultShift,
                            'check_in' => null,
                            'check_out' => null,
                            'status' => 'Holiday',
                            'note' => $holidayRecord ? $holidayRecord->name : 'Weekend'
                        ];
                        $summary['holiday']++;
                    } else {
                        // 4. Default to Absent
                        $record = (object)[
                            'date' => $dateString,
                            'shift' => $defaultShift,
                            'check_in' => null,
                            'check_out' => null,
                            'status' => 'Absent',
                            'note' => 'No record'
                        ];
                        $summary['absent']++;
                    }
                }

                $fullMonthData->push($record);
            }

            $summary['total_days'] = $period->count();
            // $summary['holiday'] = $summary['total_days'] - ($summary['absent'] + $summary['leave'] + $summary['present']);
            $totalSalary = $user->profile->salary ?? 0;
            $dailySalary = round($totalSalary / $summary['total_days'], 2);
            $absent_deduction = $dailySalary * $summary['absent'];
            $leave_deduction = $dailySalary * $summary['leave'];
            $hourlySalary = round($dailySalary / 9, 2);
            $minuteSalary = $hourlySalary / 60;
            $lateDeductionThresholdMinutes = 120;
            $lateDeduction = $summary['late_minutes'] >= $lateDeductionThresholdMinutes
                ? round($summary['late_minutes'] * $minuteSalary, 2)
                : 0;
            $earlyOutDeduction = round($summary['early_minutes'] * $minuteSalary, 2);

            //loan deduction
            $loan = \DB::table('loans')
                ->where('user_id', $userId)
                ->where('status', 'running')
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->where('start_date', '<=', $endDate)
                        ->where('end_date', '>=', $startDate);
                })
                ->first();
            $advance_salary = AdvanceSalary::where('user_id', $userId)
                ->where('status', 'Approved')
                ->where(function ($query) use ($dateInput) {
                    $query->where('month', $dateInput);
                })
                ->first();
            $totalDeductions = $absent_deduction + $leave_deduction + $lateDeduction + $earlyOutDeduction + ($loan ? $loan->monthly_deduction : 0) + ($advance_salary ? $advance_salary->amount : 0);
            return response()->json([
                'summary' => $summary,
                'advance_salary_id' => $advance_salary ? $advance_salary->id : null,
                'loan_id' => $loan ? $loan->id : null,
                'daily_salary' => $dailySalary,
                'hourly_salary' => $hourlySalary,
                'absent_deduction' => $absent_deduction,
                'leave_deduction' => $leave_deduction,
                'late_deduction' => $lateDeduction,
                'early_out_deduction' => $earlyOutDeduction,
                'total_deductions' => round($totalDeductions, 2),
                'loan_deduction' => $loan ? $loan->monthly_deduction : 0,
                'advance_salary' => $advance_salary ? $advance_salary->amount : 0,
            ]);
        }
    }
    public function handleAction(Request $request, $role, $id, $action)
    {
        // Explicitly find the salary by ID
        $data = \App\Models\EmployeeSalary::findOrFail($id);


        if ($action === 'download') {
            $logoUrl = asset($data->user->company->logo) ?? asset('default-logo.png');
            $logoData = base64_encode(file_get_contents($logoUrl));
            $logoBase64 = 'data:image/png;base64,' . $logoData;
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('employee-salaries.pdf', compact('data', 'logoBase64'));
            return $pdf->download("{$data->user->name}_Payslip_{$data->month}_{$data->year}.pdf");
        }

        if ($action === 'email') {
            $data = EmployeeSalary::with('user')->findOrFail($id);

            // ১. একটি সিকিউর সাইনড লিঙ্ক তৈরি করুন (যা ৩০ দিন পর এক্সপায়ার হবে)
            $viewUrl = URL::temporarySignedRoute(
                'salary.view',
                now()->addDays(30),
                ['id' => $id]
            );

            // ২. ব্রেভো API এর মাধ্যমে ইমেল পাঠান

            $response = Http::withOptions([
                'verify' => false, // ⚠️ LOCAL ONLY
            ])->withHeaders([
                'api-key'      => config('services.brevo.key'),
                'Content-Type' => 'application/json',
            ])->post('https://api.brevo.com/v3/smtp/email', [
                'to' => [
                    [
                        'email' => $data->user->email,
                        'name'  => $data->user->name,
                    ],
                ],
                'subject' => 'আপনার বেতন বিবরণী (Payslip) - ' . $data->month . '/' . $data->year,
                'htmlContent' => '
            <div style="font-family: sans-serif; line-height: 1.6; color: #333;">
                <h2>প্রিয় ' . $data->user->name . ',</h2>
                <p>আপনার ' . $data->month . '/' . $data->year . ' মাসের বেতন বিবরণী এখন প্রস্তুত।</p>
                <p>নিচের লিঙ্কে ক্লিক করে আপনি আপনার পে-স্লিপটি দেখতে এবং ডাউনলোড করতে পারবেন:</p>
                <div style="text-align: center; margin: 30px 0; font-family: sans-serif;">
        <a href="' . $viewUrl . '" 
           style="background-color: #000000; 
                  color: #ffffff; 
                  display: inline-block; 
                  font-family: sans-serif; 
                  font-size: 16px; 
                  font-weight: bold; 
                  line-height: 45px; 
                  text-align: center; 
                  text-decoration: none; 
                  width: 200px; 
                  -webkit-text-size-adjust: none; 
                  border-radius: 5px;">
           পে-স্লিপ দেখুন
        </a>
        </div>
                <p>নিরাপত্তার স্বার্থে এই লিঙ্কটি আগামী ৩০ দিন পর্যন্ত কার্যকর থাকবে।</p>
                <hr style="border: none; border-top: 1px solid #eee;">
                <p style="font-size: 12px; color: #777;">এটি একটি সিস্টেম জেনারেটেড ইমেল, দয়া করে এখানে রিপ্লাই করবেন না।</p>
                <p>ধন্যবাদ,<br><strong>HR বিভাগ, Epal Group</strong></p>
            </div>',
                'sender' => [
                    'email' => 'no-reply@epaltravels.com', // আপনার ভেরিফাইড সেন্ডার ইমেল
                    'name'  => 'Epal Group',
                ],
            ]);

            if ($response->successful()) {
                return back()->with('success', 'ইমেল সফলভাবে পাঠানো হয়েছে।');
            } else {
                return back()->with('error', 'ইমেল পাঠাতে সমস্যা হয়েছে।');
            }
        }

        return back();
    }
    public function view(Request $request, $id)
    {
        if (!$request->hasValidSignature()) {
            abort(401);
        }

        $data = EmployeeSalary::with('user')->findOrFail($id);
        return view('employee-salaries.view', compact('data'));
    }

    public function download(Request $request, $id)
    {
        if (!$request->hasValidSignature()) {
            abort(401);
        }

        $data = EmployeeSalary::with('user.company', 'user.salary_template')->findOrFail($id);

        $logoUrl = asset($data->user->company->logo ?? 'images/site-setting/69401c60d0949.png');
        $logoData = @file_get_contents($logoUrl);

        if ($logoData === false) {
            $fallbackLogoUrl = 'https://epal.com.bd/images/site-setting/69401c60d0949.png';
            $logoData = @file_get_contents($fallbackLogoUrl);
        }

        $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData ?: '');
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('employee-salaries.pdf', compact('data', 'logoBase64'));

        return $pdf->download("{$data->user->name}_Payslip_{$data->month}_{$data->year}.pdf");
    }
}
