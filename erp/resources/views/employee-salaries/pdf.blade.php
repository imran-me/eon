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
        .signature-row { width: 100%; border-collapse: collapse; }
        .signature-cell-left { text-align: left; }
        .signature-cell-right { text-align: right; }
        .signature { 
            border-top: 1px solid #0f172a; 
            width: 180px; 
            text-align: center; 
            padding-top: 5px; 
            font-weight: bold; 
        }
    </style>
</head>
<body>

<div class="container">
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

        $gross_earning    = array_sum($earnings);
        $total_deductions = array_sum($deductions);
        $adjustment       = payslipRound($data->salary_adjustment ?? 0);
        $net_salary       = $gross_earning - $total_deductions + $adjustment;
    @endphp

    <table class="details-table">
        <tr>
            <td width="48%" valign="top">
                <table class="inner-table">
                    <tr><th colspan="2">EARNINGS</th></tr>
                    @foreach($earnings as $label => $amount)
                    <tr><td>{{ $label }}</td><td align="right">{{ number_format($amount) }}</td></tr>
                    @endforeach
                    <tr class="total-row"><td>Gross Earnings</td><td align="right">{{ number_format($gross_earning) }}</td></tr>
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

    <div style="text-align: center; font-weight: bold; margin-bottom: 10px;">
        Salary Adjustment : {{ number_format($adjustment) }}
    </div>

    <table class="net-box" width="100%">
        <tr>
            <td width="60%">
                <div class="net-title">NET PAYABLE AMOUNT</div>
                <div class="amount-words">Amount In Words: {{ amountToWords($net_salary) }}</div>
            </td>
            <td width="40%" class="amount-value">BDT {{ number_format($net_salary) }}</td>
        </tr>
    </table>

    <div class="note-text note-block">Note: {{ $data->notes ?? 'No note available' }}</div>

    <table class="footer">
        <tr>
            <td>
                <table class="signature-row">
                    <tr>
                        <td class="signature-cell-left">
                            <div class="signature">Employee Signature</div>
                        </td>
                        <td class="signature-cell-right">
                            <div class="signature">Authorized Signatory</div>
                        </td>
                    </tr>
                </table>
                <div class="timestamp-text">
                    TIMESTAMP: {{ now()->format('Y-m-d H:i:s') }}
                </div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>