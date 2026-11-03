<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* General Setup */
        body { 
            font-family: 'Helvetica', sans-serif; 
            color: #334155; 
            margin: 0; 
            padding: 10px; 
            font-size: 12px; 
        }
        
        .container { 
            /* width: 100%;  */
            margin: auto; 
            border: 1px solid #e2e8f0; 
            padding: 20px; 
            position: relative; 
            overflow: hidden; 
            background-color: #fff;
        }

        /* Watermark */
        .container::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 400px;
            height: 400px;
            /* Use Base64 or local path for Dompdf watermark */
            background: url({{$logoBase64}}) no-repeat center;
            background-size: contain;
            opacity: 0.05;
            transform: translate(-50%, -50%) rotate(-15deg);
            z-index: 0;
        }

        /* Header Layout: Logo Left, Details Center, Status Right */
        .header-table { 
            width: 100%; 
            border-bottom: 2px solid #0f172a; 
            padding-bottom: 15px; 
            margin-bottom: 20px; 
            border-collapse: collapse;
        }
        .brand-logo { width: 70px; height: auto; }
        .company-name { font-size: 20px; font-weight: bold; color: #0f172a; margin: 0; }
        .company-info { font-size: 10px; color: #64748b; line-height: 1.4; }
        .status-badge { 
            background: #fef3c7; 
            color: #b45309; 
            padding: 3px 10px; 
            border-radius: 12px; 
            font-weight: bold; 
            font-size: 10px; 
            text-transform: uppercase; 
        }

        /* Information Grid */
        .info-table { 
            width: 100%; 
            background: rgba(248, 250, 252, 0.9); 
            border: 1px solid #e2e8f0; 
            margin-bottom: 25px; 
            border-collapse: collapse; 
        }
        .info-table td { padding: 10px; border: 1px solid #e2e8f0; width: 33.3%; }
        .label { font-size: 9px; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 2px; }
        .value { font-weight: bold; color: #0f172a; }

        /* Earnings & Deductions Tables */
        .details-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .inner-table { width: 100%; border-collapse: collapse; }
        .inner-table th { text-align: left; border-bottom: 1px solid #0f172a; padding: 8px 0; font-size: 12px; }
        .inner-table td { padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
        .total-row td { font-weight: bold; background: #f8fafc; border: none; padding: 10px 5px; }

        /* Net Payable Box */
        .net-box { border: 2px solid #0f172a; padding: 15px; margin-top: 10px; }
        .net-title { font-weight: bold; font-size: 13px; }
        .amount-words { font-size: 10px; font-style: italic; color: #64748b; }
        .amount-value { font-size: 22px; font-weight: 800; color: #2563eb; text-align: right; }

        /* Footer */
        .footer { margin-top: 20px; width: 100%; }
        .note-text { font-size: 9px; color: #64748b; }
        .note-block { margin-top: 40px; }
        .timestamp-text { font-size: 9px; color: #64748b; margin-top: 10px; text-align: left; }
        .signature-row { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .signature-cell-left,
        .signature-cell-right { width: 50%; vertical-align: bottom; }
        .signature-cell-left { text-align: left; }
        .signature-cell-right { text-align: right; }
        .signature { 
            border-top: 1px solid #0f172a; 
            width: 180px; 
            text-align: center; 
            padding-top: 5px; 
            font-weight: bold; 
        }
        /* text-align on a table cell does not move a fixed-width block in
           DomPDF. Explicit auto margins anchor both signature blocks to their
           respective sides and keep each caption centred under its line. */
        .signature-cell-left .signature { margin-left: 0; margin-right: auto; }
        .signature-cell-right .signature { margin-left: auto; margin-right: 0; }

        /* Page 2 — monthly attendance & work summary. All `ms-` prefixed. */
        @include('employee-salaries.partials.monthly-summary-css')

        .ms-page-break {
            page-break-before: always;
        }

        /* Full-sheet frame. DomPDF ignores height on tables when the content is
           shorter, and height:100% resolves to nothing here, so the sheet gets
           an explicit height and the closing block is positioned against its
           bottom edge. .container is already position:relative, which is what
           the absolute footer anchors to. The height is A4's printable area
           less the page margins and this box's own padding — measured, not
           guessed: too tall and each frame pushes a blank page after itself. */
        .ms-sheet {
            height: 950px;
            position: relative;
        }

        .ms-fill-bot {
            position: absolute;
            left: 20px;
            right: 20px;
            bottom: 16px;
        }
    </style>
</head>
<body>

@php
    // Worked out before anything renders so page 1's footer can say "1 of 3".
    // Each extra page appears only when the snapshot carries it, so a salary
    // row issued before summary_snapshot existed still prints the single page
    // it always did rather than trailing blank ones.
    $msSnapshot = is_array($data->summary_snapshot ?? null) ? $data->summary_snapshot : null;
    $msHasHistory = ($msSnapshot['payroll_history']['available'] ?? false) === true;
    $msHasDays = ($msSnapshot['attendance_days']['available'] ?? false) === true;
    $msPageCount = 1 + ($msHasHistory ? 1 : 0) + ($msHasDays ? 1 : 0);
@endphp

{{-- Each page is a full-height frame: content rides at the top, the page's
     closing block (signatures here) sits on the bottom edge. Without it every
     page ended wherever the content happened to stop and left a third of the
     sheet blank underneath. --}}
<div class="container ms-sheet">
<div class="ms-fill-top">
    <table class="header-table">
        <tr>
            <td width="20%" align="left">
                <img src="{{$logoBase64}}" class="brand-logo">
            </td>
            <td width="60%" align="center">
                <h1 class="company-name">{{ $data->user->company->name ?? 'EPAL IT SOLUTIONS' }}</h1>
                <div class="company-info">
                    {{ $data->user->company->address ?? '' }}<br>
                    Phone: {{ $data->user->company->phone ?? '' }} | Email: {{ $data->user->company->email ?? '' }}
                </div>
            </td>
            <td width="20%" align="right" valign="top">
                @if($data->status === 'pending')
                <span class="status-badge">PENDING</span>
                @elseif($data->status === 'Paid')
                <span class="status-badge" style="background: #d1fae5; color: #065f46;">APPROVED</span>
                @endif
                <div style="margin-top: 8px; font-weight: bold;">Payslip #{{ $data->year }}-{{ $data->month }}-{{ $data->id }}</div>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td><span class="label">Employee Name</span><span class="value">{{ $data->user->name ?? '' }}</span></td>
            <td><span class="label">Designation</span><span class="value">{{ $data->user->profile->designation->name ?? '' }}</span></td>
            <td><span class="label">Employee ID</span><span class="value">{{ $data->user->employee_id ?? 'N/A' }}</span></td>
        </tr>
        <tr>
            <td><span class="label">Pay Period</span><span class="value">{{ $data->year }}/{{ $data->month }}</span></td>
            <td><span class="label">Payment Method</span><span class="value">{{ $data->payment_method ?? '' }}</span></td>
            <td><span class="label">Generate Date</span><span class="value">{{ $data->created_at->format('Y-m-d') }}</span></td>
        </tr>
    </table>

    @php
        // Every figure is rounded up to whole taka, and the totals are summed
        // from those rounded lines so the payslip adds up exactly as printed.
        $tpl = $data->user->salary_template;

        $earnings = [
            'Basic Salary'          => payslipRound($tpl->basic_salary ?? 0),
            'House Rent Allowance'  => payslipRound($tpl->house_rent ?? 0),
            'Medical Allowance'     => payslipRound($tpl->medical_allowance ?? 0),
            'Conveyance Allowance'  => payslipRound($tpl->conveyance_allowance ?? 0),
            ($data->bonus_label ?? 'Bonus') => payslipRound($data->bonus_amount ?? 0),
        ];
        if (!empty($data->overtime_salary)) {
            $earnings['Overtime'] = payslipRound($data->overtime_salary);
        }

        $deductions = [
            'Advance Salary' => payslipRound($data->advance_salary_deduction ?? 0),
            'Loan'           => payslipRound($data->loan_deduction ?? 0),
            'Absent'         => payslipRound($data->absent_deduction ?? 0),
            'Leave'          => payslipRound($data->leave_deduction ?? 0),
            'Late'           => payslipRound($data->late_deduction ?? 0),
            'Early leave'    => payslipRound($data->early_leave_deduction ?? 0),
        ];

        $storedGross      = payslipRound($data->gross_salary ?? 0);
        $storedDeductions = payslipRound($data->total_deductions ?? 0);
        if (array_sum(array_slice($earnings, 0, 4)) !== $storedGross) {
            $earnings = ['Gross Salary' => $storedGross, ($data->bonus_label ?? 'Bonus') => payslipRound($data->bonus_amount ?? 0)];
            if (!empty($data->overtime_salary)) {
                $earnings['Overtime'] = payslipRound($data->overtime_salary);
            }
        }
        $rawDeductionTotal = (float) ($data->advance_salary_deduction ?? 0)
            + (float) ($data->loan_deduction ?? 0) + (float) ($data->absent_deduction ?? 0)
            + (float) ($data->leave_deduction ?? 0) + (float) ($data->late_deduction ?? 0)
            + (float) ($data->early_leave_deduction ?? 0);
        if (abs($rawDeductionTotal - (float) ($data->total_deductions ?? 0)) <= .01) {
            $roundingDelta = $storedDeductions - array_sum($deductions);
            if ($roundingDelta !== 0) {
                foreach (array_reverse($deductions, true) as $label => $amount) {
                    if ($amount !== 0) { $deductions[$label] += $roundingDelta; break; }
                }
            }
        } else {
            $deductions = [$storedDeductions == 0 ? 'No deductions' : 'Recorded Deductions' => $storedDeductions];
        }

        $encashmentRows = $data->leaveEncashments;
        $encashment = payslipRound($encashmentRows->sum('total_amount'));
        $earnedDays = (float) $encashmentRows->sum('earned_days');
        $encashmentDays = (float) $encashmentRows->sum('encashable_days');
        $encashableValue = payslipRound($encashmentRows->sum('month_salary_amount'));
        $encashmentRefund = payslipRound($encashmentRows->sum('leave_deduction_refund'));
        $encashmentPeriod = $encashmentRows->isNotEmpty() ? $encashmentRows->min('period_start')->format('d M Y').' – '.$encashmentRows->max('period_end')->format('d M Y') : null;
        if ($encashment > 0) {
            $earnings['Leave Encashment (' . number_format($earnedDays, 1) . ' days)'] = $encashment;
        }
        $salaryGross      = $storedGross + payslipRound($data->bonus_amount ?? 0) + payslipRound($data->overtime_salary ?? 0);
        $gross_earning    = $salaryGross + $encashment;
        $total_deductions = $storedDeductions;
        $adjustment       = payslipRound($data->salary_adjustment ?? 0);
        $salaryNet        = payslipRound($data->net_salary ?? 0);
        $net_salary       = $salaryNet + $encashment;
    @endphp

    <table class="details-table">
        <tr>
            <td width="48%" valign="top">
                <table class="inner-table">
                    <tr><th colspan="2">EARNINGS</th></tr>
                    @foreach($earnings as $label => $amount)
                    <tr><td>{{ $label }}</td><td align="right">{{ number_format($amount) }}</td></tr>
                    @endforeach
                    <tr class="total-row"><td>{{ $encashment > 0 ? 'Total Earnings' : 'Gross Earnings' }}</td><td align="right">{{ number_format($gross_earning) }}</td></tr>
                </table>
            </td>
            <td width="4%"></td>
            <td width="48%" valign="top">
                <table class="inner-table">
                    <tr><th colspan="2">DEDUCTIONS</th></tr>
                    @foreach($deductions as $label => $amount)
                    <tr><td>{{ $label }}</td><td align="right">{{ number_format($amount) }}</td></tr>
                    @endforeach
                    <tr class="total-row"><td>Total Deductions</td><td align="right">{{ number_format($total_deductions) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @if($adjustment != 0)
    <div style="text-align: center; font-weight: bold; margin-bottom: 10px;">
        Salary Adjustment : {{ number_format($adjustment) }}
    </div>
    @endif

    @include('employee-salaries.partials.pay-breakdown', [
        'salary_gross' => $salaryGross, 'salary_net' => $salaryNet,
        'earned_days' => $earnedDays,
        'encashment_days' => $encashmentDays, 'encashable_value' => $encashableValue,
        'encashment_refund' => $encashmentRefund, 'encashment_period' => $encashmentPeriod,
    ])

    @if($encashment <= 0)
    <table class="net-box" width="100%">
        <tr>
            <td width="60%">
                <div class="net-title">NET PAYABLE AMOUNT</div>
                <div class="amount-words">Amount In Words: {{ amountToWords($net_salary) }}</div>
            </td>
            <td width="40%" class="amount-value">BDT {{ number_format($net_salary) }}</td>
        </tr>
    </table>
    @endif

    @php
        // Payment position supplied by the current preview snapshot.
        // Net is deliberately NOT restated here: the box above prints the net
        // recomputed from rounded template lines, while these come from the
        // ledger to the paisa, and two nets differing by rounding on one page
        // reads as an error. Paid/Due match the Payslips tab exactly.
        $msPay = $data->summary_snapshot['payment'] ?? null;
        if ($encashment > 0) {
            $encashmentPaid = (float) $data->leaveEncashments->sum(
                fn ($item) => \App\Models\PaymentSchedule::paidTotal($item->schedules)
            );
            $basePaid = is_array($msPay) ? (float) ($msPay['paid'] ?? 0) : 0;
            $combinedPaid = min((float) $net_salary, $basePaid + $encashmentPaid);
            $msPay = [
                'available' => true,
                'paid' => $combinedPaid,
                'due' => max(0, (float) $net_salary - $combinedPaid),
                'status' => $combinedPaid >= (float) $net_salary - .01 ? 'Paid' : ($combinedPaid > 0 ? 'Partial' : 'Pending'),
            ];
        }
        $msShowPay = is_array($msPay) && ($msPay['available'] ?? false) === true;
    @endphp

    @if ($msShowPay)
        <table class="ms-paid-line" width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td class="ms-paid-label">Current payment status</td>
                <td class="ms-paid-val" align="right">
                    Paid <strong><span class="ms-taka">BDT&nbsp;</span>{{ number_format($msPay['paid'], 2) }}</strong>
                    &nbsp;·&nbsp;
                    Still due <strong class="{{ $msPay['due'] > 0 ? 'ms-minus' : '' }}"><span class="ms-taka">BDT&nbsp;</span>{{ number_format($msPay['due'], 2) }}</strong>
                    &nbsp;·&nbsp;
                    {{ $msPay['status'] }}
                </td>
            </tr>
        </table>
    @endif

    <div class="note-text note-block">Note: {{ $data->notes ?? 'No note available' }}</div>

</div>
<div class="ms-fill-bot">
    <table class="footer">
        <tr>
            <td>
                <table class="signature-row">
                    <tr>
                        <td class="signature-cell-left">
                            <div class="signature">Employee Signature</div>
                        </td>
                        <td class="signature-cell-right">
                            <x-signature :company="$data->user->company ?? null" />
                            <div class="signature">Authorized Signatory</div>
                        </td>
                    </tr>
                </table>
                <div class="timestamp-text">
                    TIMESTAMP: {{ now()->format('Y-m-d H:i:s') }} &nbsp;·&nbsp; Page 1 of {{ $msPageCount }}
                </div>
            </td>
        </tr>
    </table>
</div>
</div>

@if ($msHasHistory)
    {{-- PAGE 2 — payroll history, frozen and dated. --}}
    <div class="ms-page-break"></div>
    <div class="container ms-sheet">
    <div class="ms-fill-top">
        @include('employee-salaries.partials.payroll-history', ['data' => $data])
    </div>
    <div class="ms-fill-bot">
        <div class="timestamp-text">Page 2 of {{ $msPageCount }}</div>
    </div>
    </div>
@endif

@if ($msHasDays)
    {{-- PAGE 3 — the whole month's attendance on one page. --}}
    <div class="ms-page-break"></div>
    <div class="container ms-sheet">
    <div class="ms-fill-top">
        @include('employee-salaries.partials.attendance-page', ['data' => $data])
    </div>
    <div class="ms-fill-bot">
        <div class="timestamp-text">Page {{ $msPageCount }} of {{ $msPageCount }}</div>
    </div>
    </div>
@endif

</body>
</html>
