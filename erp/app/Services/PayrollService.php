<?php

namespace App\Services;

use App\Models\AdvanceSalary;
use App\Models\Attendance;
use App\Models\EmployeeSalary;
use App\Models\Leave;
use App\Models\LeaveEncashmentOpeningEntry;
use App\Models\Loan;
use App\Models\PaymentSchedule;
use App\Models\Payslip;
use App\Models\SalaryReconciliation;
use App\Models\User;
use App\Traits\PostsEmployeeLedger;
use App\Traits\PostsSalaryJournal;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Shared payroll business logic used by both the manual "Add New Employee
 * Salary" form (EmployeeSalaryController) and the automated monthly payroll
 * job — one code path so the two never drift apart.
 */
class PayrollService
{
    use PostsEmployeeLedger, PostsSalaryJournal;

    /**
     * Compute attendance-based deductions/additions for one employee for one
     * month. Same calculation EmployeeSalaryController::getAttendanceData()
     * exposes over AJAX for the manual form, extracted so the automated
     * monthly job can call it without an HTTP request.
     */
    public function calculateAttendanceDeductions(User $user, int $year, int $month): array
    {
        $user->loadMissing('shift', 'profile');

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
            'overtime_minutes' => 0,
        ];

        $dbAttendances = Attendance::with('shift', 'attendence_setting')
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('date');

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $period = CarbonPeriod::create($startDate, $endDate);

        $defaultShift = $user->shift ?? $dbAttendances->first()?->shift;

        $holidays = DB::table('holidays')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
            })->get();

        $leaves = DB::table('leaves')
            ->leftJoin('leave_types', 'leave_types.id', '=', 'leaves.leave_type_id')
            ->where('leaves.user_id', $user->id)
            ->where('leaves.status', 'approved')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('leaves.start_date', [$startDate, $endDate])
                    ->orWhereBetween('leaves.end_date', [$startDate, $endDate]);
            })
            ->select('leaves.*', 'leave_types.exempts_early_out_deduction')
            ->get();

        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $dayName = strtolower($date->format('l'));

            if (isset($dbAttendances[$dateString])) {
                $record = $dbAttendances[$dateString];

                if ($record->check_in && $record->attendence_setting) {
                    $checkInTime = Carbon::parse($dateString . ' ' . $record->check_in);
                    $minutes = (int) $record->attendence_setting->time_after_checkin;
                    $lateThreshold = Carbon::parse($dateString . ' ' . $record->shift->start_time)->addMinutes($minutes);
                    $lateMinutes = (int) abs($checkInTime->diffInMinutes($lateThreshold, false));

                    if ($checkInTime->gt($lateThreshold) && $lateMinutes > 0) {
                        $summary['late']++;
                        $summary['present']++;
                        $summary['late_minutes'] += $lateMinutes;
                    } else {
                        $summary['present']++;
                    }

                    if ($record->check_out && $record->attendence_setting) {
                        $checkOutTime = Carbon::parse($dateString . ' ' . $record->check_out);
                        $minutes = (int) $record->attendence_setting->time_after_checkout;
                        $shiftEnd = Carbon::parse($dateString . ' ' . $record->shift->end_time);

                        $earlyThreshold = $shiftEnd->copy()->subMinutes(10);
                        $overtimeThreshold = $shiftEnd->copy()->addMinutes(60);

                        $earlyMinutes = 0;
                        $overtimeMinutes = 0;

                        if ($checkOutTime->lessThan($earlyThreshold)) {
                            $earlyMinutes = (int) abs($checkOutTime->diffInMinutes($earlyThreshold, false));
                        } elseif ($checkOutTime->greaterThanOrEqualTo($overtimeThreshold)) {
                            $overtimeMinutes = (int) abs($checkOutTime->diffInMinutes($shiftEnd, false));
                        }

                        if ($checkOutTime->lt($earlyThreshold) && $earlyMinutes > 0) {
                            $approvedEarlyLeave = $this->findApprovedEarlyLeaveForDate($leaves, $dateString);
                            if ($approvedEarlyLeave) {
                                $summary['present']++;
                            } else {
                                $summary['early_out']++;
                                $summary['present']++;
                                $summary['early_minutes'] += $earlyMinutes;
                            }
                        }

                        if ($checkOutTime->gte($overtimeThreshold) && $overtimeMinutes > 0) {
                            $summary['overtime_minutes'] += $overtimeMinutes;
                            $summary['overtime_days'] = ($summary['overtime_days'] ?? 0) + 1;
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
            } elseif ($leaves->first(fn($l) => $dateString >= $l->start_date && $dateString <= $l->end_date)) {
                $summary['leave']++;
            } else {
                $holidayRecord = $holidays->first(fn($h) => $dateString >= $h->start_date && $dateString <= $h->end_date);
                $isWeekend = $defaultShift && in_array($dayName, $defaultShift->holidays ?? []);

                if ($holidayRecord || $isWeekend) {
                    $summary['holiday']++;
                } else {
                    $summary['absent']++;
                }
            }
        }

        $summary['total_days'] = $period->count();
        $totalSalary = (float) ($user->profile->salary ?? 0);
        $dailySalary = round($totalSalary / $summary['total_days'], 2);
        $absentDeduction = $dailySalary * $summary['absent'];
        $leaveDeduction = $dailySalary * $summary['leave'];
        $hourlySalary = round($dailySalary / 9, 2);
        $minuteSalary = $hourlySalary / 60;
        $lateDeductionThresholdMinutes = 120;
        $lateDeduction = $summary['late_minutes'] >= $lateDeductionThresholdMinutes
            ? round($summary['late_minutes'] * $minuteSalary, 2)
            : 0;
        $earlyOutDeduction = round($summary['early_minutes'] * $minuteSalary, 2);

        $loan = DB::table('loans')
            ->where('user_id', $user->id)
            ->where('status', 'running')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where('start_date', '<=', $endDate)->where('end_date', '>=', $startDate);
            })
            ->first();

        $advanceSalary = AdvanceSalary::where('user_id', $user->id)
            ->where('status', 'Approved')
            ->where('month', sprintf('%04d-%02d', $year, $month))
            ->first();

        $overtimeSalary = $user->overtime_eligible
            ? round($summary['overtime_minutes'] * $minuteSalary, 2)
            : 0;

        $totalDeductions = $absentDeduction + $leaveDeduction + $lateDeduction + $earlyOutDeduction
            + ($loan ? $loan->monthly_deduction : 0)
            + ($advanceSalary ? $advanceSalary->amount : 0);

        return [
            'overtime_eligible' => (bool) $user->overtime_eligible,
            'summary' => $summary,
            'advance_salary_id' => $advanceSalary ? $advanceSalary->id : null,
            'loan_id' => $loan ? $loan->id : null,
            'gross_salary' => $totalSalary,
            'daily_salary' => $dailySalary,
            'hourly_salary' => $hourlySalary,
            'absent_deduction' => $absentDeduction,
            'leave_deduction' => $leaveDeduction,
            'late_deduction' => $lateDeduction,
            'early_out_deduction' => $earlyOutDeduction,
            'overtime_salary' => $overtimeSalary,
            'total_deductions' => round($totalDeductions, 2),
            'loan_deduction' => $loan ? $loan->monthly_deduction : 0,
            'advance_salary' => $advanceSalary ? $advanceSalary->amount : 0,
        ];
    }

    /**
     * Create an EmployeeSalary row plus its journal entry, payment schedule,
     * and employee-ledger row — the same side effects
     * EmployeeSalaryController::store() performs, extracted so both the
     * manual form and the automated monthly job share one code path.
     *
     * Expected $data keys mirror what EmployeeSalaryController::store()
     * previously read straight off the request: user_id, loan_id,
     * advance_salary_id, month, year, bonus_label, bonus_amount,
     * gross_salary, loan_deduction, advance_salary_deduction,
     * early_leave_deduction, over_time, over_time_days, overtime_salary,
     * absent_deduction, leave_deduction, late_deduction, salary_adjustment,
     * total_deductions, net_salary, salary_generation_date, scheduled_date,
     * payment_method, status, notes, bank_id.
     */
    public function createEmployeeSalaryRecord(array $data): EmployeeSalary
    {
        return DB::transaction(function () use ($data) {
            $user = User::findOrFail($data['user_id']);

            $empSalary = EmployeeSalary::create([
                'user_id' => $data['user_id'],
                'salary_template_id' => $user->salary_template_id,
                'loan_id' => $data['loan_id'] ?? null,
                'advance_salary_id' => $data['advance_salary_id'] ?? null,
                'month' => (string) $data['month'],
                'year' => (int) $data['year'],
                'bonus_label' => $data['bonus_label'] ?? null,
                'bonus_amount' => $data['bonus_amount'] ?? 0,
                'gross_salary' => $data['gross_salary'],
                'loan_deduction' => $data['loan_deduction'] ?? 0,
                'advance_salary_deduction' => $data['advance_salary_deduction'] ?? 0,
                'early_leave_deduction' => $data['early_leave_deduction'] ?? 0,
                'over_time' => $data['over_time'] ?? 0,
                'over_time_days' => $data['over_time_days'] ?? 0,
                'overtime_salary' => $user->overtime_eligible ? ($data['overtime_salary'] ?? 0) : 0,
                'absent_deduction' => $data['absent_deduction'] ?? 0,
                'leave_deduction' => $data['leave_deduction'] ?? 0,
                'late_deduction' => $data['late_deduction'] ?? 0,
                'salary_adjustment' => $data['salary_adjustment'] ?? 0,
                'total_deductions' => $data['total_deductions'] ?? 0,
                'net_salary' => $data['net_salary'] ?? 0,
                'salary_generation_date' => $data['salary_generation_date'],
                'scheduled_date' => $data['scheduled_date'],
                'payment_method' => $data['payment_method'] ?? null,
                'status' => $data['status'] ?? 'Pending',
                'notes' => $data['notes'] ?? null,
            ]);

            // ── EMPLOYEE LEDGER (auto) ─────────────────────────────────
            $this->postEmployeeLedgerRow($empSalary->user_id, [
                'type' => 'salary_earned',
                'entry_date' => $empSalary->salary_generation_date ?? now()->toDateString(),
                'reference' => Carbon::createFromDate($empSalary->year, (int) $empSalary->month, 1)->format('F Y') . ' salary (net of deductions)',
                'debit' => $empSalary->net_salary,
                'credit' => 0,
            ], $empSalary);

            // ── LOAN EMI ───────────────────────────────────────────────
            // The deduction used to be applied by decrementing loans.remaining_amount
            // in place, which left no trace of when it happened or which payslip
            // took it. It is now a repayment row on the loan, and the balance falls
            // out of the rows — same figure, but the loan register and the
            // transaction trail can finally show where it came from.
            $loanDeduction = (float) ($data['loan_deduction'] ?? 0);
            $loan = Loan::find($data['loan_id'] ?? null);

            if ($loan && $loanDeduction > 0) {
                $period = Carbon::createFromDate($empSalary->year, (int) $empSalary->month, 1);

                app(LoanLedgerService::class)->recordSalaryDeduction(
                    $loan,
                    $loanDeduction,
                    $empSalary->salary_generation_date ?? now()->toDateString(),
                    $empSalary->id,
                    'EMI deducted from ' . $period->format('F Y') . ' salary'
                );
            }
            // ── END LOAN EMI ───────────────────────────────────────────

            $advanceRecord = DB::table('advance_salaries')->where('id', $data['advance_salary_id'] ?? null)->first();
            if ($advanceRecord && $advanceRecord->status == 'Approved') {
                DB::table('advance_salaries')->where('id', $data['advance_salary_id'])->update([
                    'payment_status' => 'Paid',
                    'paid_at' => now(),
                ]);
            }

            // ── JOURNAL (auto) ────────────────────────────────────────
            $this->createSalaryJournal(
                'salary',
                $empSalary->id,
                $user->company_id ?? auth()->user()?->company_id ?? 2,
                $empSalary->salary_generation_date,
                'SAL-' . $empSalary->user_id . '-' . $empSalary->month . '-' . $empSalary->year,
                'Salary — ' . ($empSalary->user->name ?? 'Employee'),
                $empSalary->net_salary,
                in_array($empSalary->status, ['paid', 'Paid']),
                $data['bank_id'] ?? null,
            );
            // ── END JOURNAL ───────────────────────────────────────────

            if (!in_array($empSalary->status, ['paid', 'Paid']) && $empSalary->net_salary > 0) {
                PaymentSchedule::updateOrCreate(
                    [
                        'schedulable_type' => EmployeeSalary::class,
                        'schedulable_id' => $empSalary->id,
                        'company_id' => $user->company_id ?? auth()->user()?->company_id ?? 2,
                        'status' => 'pending',
                    ],
                    [
                        'type' => 'pay',
                        'party_type' => 'employee',
                        'party_id' => $empSalary->user_id,
                        'party_name' => $empSalary->user?->name,
                        'source_label' => 'Salary - ' . $empSalary->month . '/' . $empSalary->year,
                        'amount' => $empSalary->net_salary,
                        'scheduled_date' => $empSalary->scheduled_date,
                        'note' => $empSalary->notes,
                        'created_by' => auth()->id(),
                    ]
                );
            }

            return $empSalary;
        });
    }

    /**
     * Auto-generate a payslip PDF for an EmployeeSalary and record it in the
     * payslips table — no journal entry here, since createEmployeeSalaryRecord()
     * already posted the salary's journal entry; posting another would
     * double-book the expense.
     *
     * @return array{payslip: Payslip, pdf_content: string}
     */
    public function issuePayslip(EmployeeSalary $empSalary): array
    {
        $empSalary->loadMissing('user.company', 'user.profile.designation', 'user.salary_template');

        $logoUrl = asset($empSalary->user->company->logo ?? 'images/site-setting/69401c60d0949.png');
        $logoData = @file_get_contents($logoUrl);
        if ($logoData === false) {
            $logoData = @file_get_contents('https://epal.com.bd/images/site-setting/69401c60d0949.png');
        }
        $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData ?: '');

        $pdfContent = Pdf::loadView('employee-salaries.pdf', ['data' => $empSalary, 'logoBase64' => $logoBase64])->output();

        $uploadPath = 'image/payslip/';
        if (!file_exists(public_path($uploadPath))) {
            mkdir(public_path($uploadPath), 0777, true);
        }
        $fileName = uniqid() . '.pdf';
        file_put_contents(public_path($uploadPath . $fileName), $pdfContent);

        $payslipNumber = sprintf('PS-%04d%02d-%04d', $empSalary->year, (int) $empSalary->month, $empSalary->user_id);

        $payslip = Payslip::updateOrCreate(
            ['employee_salary_id' => $empSalary->id],
            [
                'user_id' => $empSalary->user_id,
                'payslip_number' => $payslipNumber,
                'issue_date' => now()->toDateString(),
                'pdf_path' => $uploadPath . $fileName,
            ]
        );

        return ['payslip' => $payslip, 'pdf_content' => $pdfContent];
    }

    /**
     * Payslip-generated notifications — in-app + push, email, SMS. Shared by
     * both the automated monthly payroll job and the manual "Add New"
     * salary form (EmployeeSalaryController::store()), so a manually
     * created salary record notifies the employee exactly like an
     * automated one. Each channel is independently try/caught: none of
     * them should ever fail the salary-creation request itself.
     */
    public function sendPayslipNotifications(EmployeeSalary $empSalary, ?Payslip $payslip = null): void
    {
        $empSalary->loadMissing('user');
        $user = $empSalary->user;
        if (!$user) {
            return;
        }

        try {
            NotificationService::notifyPayslipGenerated($empSalary, $payslip);
        } catch (\Throwable $e) {
            Log::warning('Payslip in-app/push notification failed.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        if ($user->email) {
            try {
                $viewUrl = URL::temporarySignedRoute(
                    'salary.view',
                    now()->addDays(30),
                    ['id' => $empSalary->id]
                );

                $subject = 'আপনার বেতন বিবরণী (Payslip) - ' . $empSalary->month . '/' . $empSalary->year;
                $htmlContent = view('emails.payslip-notice', ['user' => $user, 'empSalary' => $empSalary, 'viewUrl' => $viewUrl])->render();

                $response = sendBrevoMail($user->email, $user->name, $subject, $htmlContent);

                if (!$response->successful()) {
                    Log::warning('Brevo payslip email API call failed.', [
                        'user_id' => $user->id,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Payslip email failed to send.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        if (!empty($user->phone)) {
            try {
                $monthLabel = Carbon::createFromDate((int) $empSalary->year, (int) $empSalary->month, 1)->format('F Y');
                $smsMessage = "Dear {$user->name}, your {$monthLabel} salary has been generated. Please check your email.";

                // Loan/advance recovered via this payslip's deductions —
                // each line skipped when that deduction wasn't applicable.
                $loanTaken = (float) ($empSalary->loan_deduction ?? 0);
                $advanceTaken = (float) ($empSalary->advance_salary_deduction ?? 0);
                if ($loanTaken > 0 || $advanceTaken > 0) {
                    $parts = [];
                    if ($loanTaken > 0) {
                        $parts[] = 'Loan Taken: ' . number_format($loanTaken, 0);
                    }
                    if ($advanceTaken > 0) {
                        $parts[] = 'Advance Taken: ' . number_format($advanceTaken, 0);
                    }
                    $parts[] = 'Total Outstanding: ' . number_format($loanTaken + $advanceTaken, 0);
                    $smsMessage .= ' ' . implode(' ', $parts);
                }

                sendSms($user->phone, $smsMessage);
            } catch (\Throwable $e) {
                Log::warning('Payslip SMS failed to send.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Every employee shares the same fixed leave-encashment year — Feb 1 to
     * Jan 31 — rather than their personal join-month anniversary. Anyone who
     * joined before this date had their leave/salary handled manually
     * pre-launch, so there's no reliable per-month leave_deduction to refund
     * for that time; their eligibility clock is reset to start here instead
     * of their real joining_date (see encashmentEffectiveStart()).
     */
    // Public so the payroll reports can state the same liability the annual
    // reconciliation will settle, rather than a second definition of it.
    public const ENCASHMENT_CUTOVER_DATE = '2026-02-01';

    /**
     * Checks whether ($year, $month) is a February — the one month per year
     * this fires for anyone — and, if $user has completed a full year of
     * (effective) service by then and that service year hasn't already been
     * reconciled, posts the annual leave-encashment payout: 1 month's
     * current gross salary plus a refund of everything deducted from their
     * payslips for approved leave since their last payout (never for
     * unauthorized absence). Posts an employee-ledger row, an accounting
     * journal (same salary_expense / salary_payable accounts as a normal
     * salary row), and a PaymentSchedule so the payout is queued for payment
     * like real salary rather than left as an unscheduled liability.
     *
     * No-ops every month except February, and no-ops if this service year
     * was already reconciled — safe to call unconditionally on every
     * monthly payroll run for every employee. salary_reconciliations'
     * unique(user_id, service_year_number) backs this up at the DB level in
     * case of a race between retries.
     */
    public function maybeProcessAnniversaryReconciliation(User $user, int $year, int $month): ?SalaryReconciliation
    {
        if ($month !== 2) {
            return null;
        }

        $user->loadMissing('profile');
        $joiningDate = $user->profile?->joining_date;
        if (!$joiningDate) {
            return null;
        }

        $effectiveStart = $this->encashmentEffectiveStart(Carbon::parse($joiningDate));
        $processingDate = Carbon::create($year, 2, 1);

        // Must have completed a full year of effective service by this Feb.
        if ($effectiveStart->copy()->addYear()->gt($processingDate)) {
            return null;
        }

        // Idempotency check on anniversary_date (this Feb cycle), not
        // service_year_number — service_year_number is derived *from* the
        // previous row below, so checking idempotency against it instead
        // would be self-referential: a re-run would compute the next
        // number up and create a second, overlapping row for the same
        // cycle rather than detecting it was already processed.
        if (SalaryReconciliation::where('user_id', $user->id)->where('anniversary_date', $processingDate->toDateString())->exists()) {
            return null;
        }

        $previous = SalaryReconciliation::where('user_id', $user->id)
            ->orderByDesc('service_year_number')
            ->first();

        $serviceYearNumber = $previous ? ((int) $previous->service_year_number + 1) : 1;

        $periodStart = $previous
            ? Carbon::parse($previous->period_end)->addDay()->startOfDay()
            : $effectiveStart->copy();
        $periodEnd = Carbon::create($year, 1, 1)->endOfMonth(); // Jan 31 of $year — the cycle that just closed

        // Fold in the one-time manual opening entry, but only on this
        // employee's very first reconciliation ever ($previous null) — once
        // a first payout exists, the opening entry has already been paid
        // out and must never be added again. If it has an as_of_date, the
        // real leave_deduction window must start strictly after it —
        // otherwise employee_salaries rows for months it already covers
        // would be summed a second time on top of the manual amount.
        $openingEntry = $previous ? null : LeaveEncashmentOpeningEntry::where('user_id', $user->id)->first();

        if ($openingEntry && $openingEntry->as_of_date) {
            $afterOpeningEntry = $openingEntry->as_of_date->copy()->addDay()->startOfDay();
            if ($afterOpeningEntry->gt($periodStart)) {
                $periodStart = $afterOpeningEntry;
            }
        }

        $leaveRefund = $this->sumLeaveDeductionForWindow($user->id, $periodStart, $periodEnd);

        if ($openingEntry) {
            $leaveRefund = round($leaveRefund + (float) $openingEntry->amount, 2);
        }

        $monthSalaryAmount = (float) ($user->profile->salary ?? 0);
        $totalAmount = round($monthSalaryAmount + $leaveRefund, 2);

        return DB::transaction(function () use ($user, $serviceYearNumber, $processingDate, $periodStart, $periodEnd, $monthSalaryAmount, $leaveRefund, $totalAmount) {
            $companyId = $user->company_id ?? 2;

            $reconciliation = SalaryReconciliation::create([
                'user_id' => $user->id,
                'company_id' => $companyId,
                'service_year_number' => $serviceYearNumber,
                'anniversary_date' => $processingDate->toDateString(),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'month_salary_amount' => $monthSalaryAmount,
                'leave_deduction_refund' => $leaveRefund,
                'total_amount' => $totalAmount,
                'status' => 'Pending',
                'notes' => 'Auto-generated anniversary reconciliation (service year ' . $serviceYearNumber . ')',
            ]);

            $this->postEmployeeLedgerRow($user->id, [
                'type' => 'salary_reconciliation',
                'entry_date' => now()->toDateString(),
                'reference' => 'Service year ' . $serviceYearNumber . ' anniversary payout',
                'debit' => $totalAmount,
                'credit' => 0,
            ], $reconciliation);

            $this->createSalaryJournal(
                'salary_reconciliation',
                $reconciliation->id,
                $companyId,
                now()->toDateString(),
                'RECON-' . $user->id . '-Y' . $serviceYearNumber,
                'Annual leave encashment — ' . ($user->name ?? 'Employee') . ' (Year ' . $serviceYearNumber . ')',
                $totalAmount,
                false,
                null,
                'Leave encashment'
            );

            PaymentSchedule::create([
                'schedulable_type' => SalaryReconciliation::class,
                'schedulable_id' => $reconciliation->id,
                'company_id' => $companyId,
                'type' => 'pay',
                'party_type' => 'employee',
                'party_id' => $user->id,
                'party_name' => $user->name,
                'source_label' => 'Leave Encashment — Year ' . $serviceYearNumber,
                'amount' => $totalAmount,
                'scheduled_date' => now()->addDays(5)->toDateString(),
                'status' => 'pending',
                'note' => 'Auto-generated anniversary reconciliation',
                'created_by' => auth()->id(),
            ]);

            return $reconciliation;
        });
    }

    /**
     * Read-only projection of what's accrued toward $user's next February
     * payout, as of $asOf — used to display a running "due so far" figure
     * (employee profile header tile + monthly breakdown) without creating
     * any records. Shares the same effective-start and window logic as
     * maybeProcessAnniversaryReconciliation() so the live display and the
     * real payout can never disagree about which months are being summed.
     *
     * Returns null if the employee has no joining_date on file.
     */
    public function projectPendingReconciliation(User $user, Carbon $asOf): ?array
    {
        $user->loadMissing('profile');
        $joiningDate = $user->profile?->joining_date;
        if (!$joiningDate) {
            return null;
        }

        $effectiveStart = $this->encashmentEffectiveStart(Carbon::parse($joiningDate));
        $lastReconciliation = SalaryReconciliation::where('user_id', $user->id)
            ->orderByDesc('service_year_number')
            ->first();

        $serviceYearInProgress = $lastReconciliation ? ((int) $lastReconciliation->service_year_number + 1) : 1;
        $periodStart = $lastReconciliation
            ? Carbon::parse($lastReconciliation->period_end)->addDay()->startOfDay()
            : $effectiveStart->copy();

        // End of the last fully-completed month as of $asOf — never the
        // current, still-in-progress month. Monthly payroll only ever gets
        // generated for a month once it's over (the scheduled job runs on
        // the 1st, for the previous month), so counting approved-leave days
        // from the current month here would show a "days" figure running
        // ahead of the "amount" figure, which only reflects months that
        // actually have a generated employee_salaries row.
        $periodEnd = $asOf->copy()->subMonthNoOverflow()->endOfMonth();

        // Same rule as maybeProcessAnniversaryReconciliation(): the manual
        // opening entry only counts toward the employee's first-ever cycle.
        // If it has an as_of_date, the live leave-day/leave_deduction window
        // must start strictly after it — otherwise real Leave/EmployeeSalary
        // rows for dates the opening entry already covers would be counted
        // a second time on top of the manual days/amount.
        $openingEntry = $lastReconciliation
            ? null
            : LeaveEncashmentOpeningEntry::where('user_id', $user->id)->first();

        if ($openingEntry && $openingEntry->as_of_date) {
            $afterOpeningEntry = $openingEntry->as_of_date->copy()->addDay()->startOfDay();
            if ($afterOpeningEntry->gt($periodStart)) {
                $periodStart = $afterOpeningEntry;
            }
        }

        // Next February this employee is (or will be) eligible to be paid in
        // — the first Feb 1st on/after both "today" and their 1-year
        // eligibility floor.
        $eligibleFrom = $effectiveStart->copy()->addYear();
        $nextPayoutDate = Carbon::create($asOf->year, 2, 1);
        while ($nextPayoutDate->lte($asOf) || $nextPayoutDate->lt($eligibleFrom)) {
            $nextPayoutDate->addYear();
        }

        $monthlyRows = EmployeeSalary::where('user_id', $user->id)
            ->whereRaw('(year * 100 + CAST(month AS UNSIGNED)) BETWEEN ? AND ?', [
                $periodStart->year * 100 + $periodStart->month,
                $periodEnd->year * 100 + $periodEnd->month,
            ])
            ->orderBy('year')->orderBy('month')
            ->get(['year', 'month', 'leave_deduction', 'absent_deduction']);

        $accruedLeaveDeduction = (float) $monthlyRows->sum('leave_deduction');

        $leaveDaysTaken = Leave::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $periodEnd->toDateString())
            ->whereDate('end_date', '>=', $periodStart->toDateString())
            ->get()
            ->sum(function ($leave) use ($periodStart, $periodEnd) {
                $start = Carbon::parse($leave->start_date)->max($periodStart);
                $end = Carbon::parse($leave->end_date)->min($periodEnd);
                return $end->greaterThanOrEqualTo($start) ? $start->diffInDays($end) + 1 : 0;
            });

        if ($openingEntry) {
            $accruedLeaveDeduction = round($accruedLeaveDeduction + (float) $openingEntry->amount, 2);
            $leaveDaysTaken += (float) $openingEntry->days;
        }

        $currentGrossSalary = (float) ($user->profile->salary ?? 0);

        return [
            'service_year_in_progress' => $serviceYearInProgress,
            'next_anniversary_date' => $nextPayoutDate,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'leave_days_taken' => $leaveDaysTaken,
            'accrued_leave_deduction' => $accruedLeaveDeduction,
            'current_gross_salary' => $currentGrossSalary,
            'projected_total_payout' => round($accruedLeaveDeduction + $currentGrossSalary, 2),
            'monthly_breakdown' => $monthlyRows,
            'opening_entry' => $openingEntry,
        ];
    }

    /**
     * The date $user's leave-encashment eligibility clock actually starts
     * counting from: their real joining_date, unless that predates the
     * ENCASHMENT_CUTOVER_DATE, in which case it's reset to the cutover.
     * Employees who joined earlier had leave handled manually before
     * launch, so there's no reliable monthly leave_deduction on file to
     * refund for that time — resetting to the cutover means nobody is owed
     * (or shorted) a refund for a period the system never tracked.
     */
    public function encashmentEffectiveStart(Carbon $joiningDate): Carbon
    {
        $cutover = Carbon::parse(self::ENCASHMENT_CUTOVER_DATE);

        return $joiningDate->lt($cutover) ? $cutover->copy() : $joiningDate->copy();
    }

    private function sumLeaveDeductionForWindow(int $userId, Carbon $periodStart, Carbon $periodEnd): float
    {
        return (float) EmployeeSalary::where('user_id', $userId)
            ->whereRaw('(year * 100 + CAST(month AS UNSIGNED)) BETWEEN ? AND ?', [
                $periodStart->year * 100 + $periodStart->month,
                $periodEnd->year * 100 + $periodEnd->month,
            ])
            ->sum('leave_deduction');
    }

    private function findApprovedEarlyLeaveForDate($leaves, string $dateString)
    {
        foreach ($leaves as $leave) {
            if (!$leave->exempts_early_out_deduction) {
                continue;
            }
            if ($dateString >= $leave->start_date && $dateString <= $leave->end_date) {
                return $leave;
            }
        }

        return null;
    }
}
