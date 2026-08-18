@extends('layout.app')

@section('main-content')
    <style>
        :root {
            --primary: #0f172a;
            --accent: #2563eb;
            --success: #059669;
            --border: #e2e8f0;
            --text-main: #334155;
            --text-light: #64748b;
        }

        .salary-wrapper {
            background: #f8fafc;
            padding: 40px 15px;
            font-family: 'Inter', -apple-system, sans-serif;
        }

        .salary-slip {
            max-width: 850px;
            margin: auto;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 4px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 40px;
            position: relative;
            overflow: hidden;
        }

        /* Professional Watermark */
        .salary-slip::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 400px;
            height: 400px;
            background: url({{ asset($data->user->company->logo ?? 'https://epal.com.bd/images/site-setting/69401c60d0949.png') }}) no-repeat center;
            background-size: contain;
            opacity: 0.05;
            transform: translate(-50%, -50%) rotate(-15deg);
            z-index: 0;
            pointer-events: none;
        }

        .slip-header,
        .info-grid,
        .table-flex,
        .adjustment-row,
        .net-payment-section,
        .verification {
            position: relative;
            z-index: 1;
        }

        /* Header Section */
        /* --- Header Alignment Fix --- */
        .slip-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            /* Vertical centering */
            border-bottom: 2px solid var(--primary);
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .header-left {
            flex: 0 0 100px;
            text-align: left;
        }

        .header-center {
            flex: 1;
            text-align: center;
        }

        .header-right {
            flex: 0 0 180px;
            text-align: right;
        }

        .brand-content {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .brand-logo {
            width: 80px;
            height: auto;
        }

        .brand h1 {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .brand p {
            font-size: 12px;
            color: var(--text-light);
            margin: 4px 0;
        }

        .badge {
            background: #dcfce7;
            color: var(--success);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            background: rgba(241, 245, 249, 0.9);
            padding: 20px;
            border-radius: 8px;
        }

        .info-item label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-light);
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .info-item span {
            font-size: 14px;
            font-weight: 600;
            color: var(--primary);
        }

        /* Financial Tables */
        .table-flex {
            display: flex;
            gap: 40px;
        }

        .table-container {
            flex: 1;
        }

        .table-container h2 {
            font-size: 14px;
            font-weight: 700;
            color: var(--primary);
            border-bottom: 1px solid var(--primary);
            padding-bottom: 8px;
            margin-bottom: 15px;
        }

        .data-table {
            width: 100%;
            font-size: 13px;
        }

        .data-table tr td {
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .data-table tr td:last-child {
            text-align: right;
            font-family: 'Courier New', Courier, monospace;
            font-weight: 600;
        }

        .total-row {
            background: rgba(248, 250, 252, 0.8);
        }

        .total-row td {
            font-weight: 700 !important;
            color: var(--primary);
            border-bottom: none !important;
        }

        /* --- New Adjustment Row Styling --- */
        .adjustment-row {
            text-align: center;
            margin: 15px 0;
            font-size: 13px;
            font-weight: 600;
            color: var(--primary);
        }

        /* Net Section */
        /* Net Section Fix */
        .net-payment-section {
            position: relative;
            z-index: 1;
            margin-top: 15px;
            padding: 20px 25px;
            border: 2px solid var(--primary);
            background: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            /* Remove fixed heights to allow expansion */
            height: auto;
            min-height: 90px;
            gap: 20px;
        }

        .net-label {
            flex: 1;
            /* This lets the label take all space except what the amount needs */
            font-size: 15px;
            font-weight: 700;
            color: var(--primary);
        }

        .amount-words {
            display: block;
            font-size: 13px;
            font-style: italic;
            color: var(--text-light);
            margin-top: 5px;
            line-height: 1.4;
            /* Prevents text from bunching up */
        }

        .net-amount {
            font-size: 26px;
            font-weight: 800;
            color: #2563eb;
            /* Blue color as per your reference */
            white-space: nowrap;
            /* Keeps 'BDT' and the number on one line */
        }

        .amount-words {
            font-size: 13px;
            font-style: italic;
            color: var(--text-light);
            margin-top: 8px;
            display: block;
            font-weight: 500;
            text-transform: capitalize;
        }

        /* Verification Footer */
        .verification {
            margin-top: 85px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 14px;
        }

        .signature-group {
            display: flex;
            gap: 20px;
            align-items: flex-end;
            justify-content: space-between;
            width: 100%;
        }

        .security-hash {
            font-family: 'Courier New', monospace;
            font-size: 10px;
            color: var(--text-light);
            max-width: 100%;
            text-align: left;
        }

        .sig-area {
            text-align: center;
            border-top: 1px solid var(--primary);
            padding-top: 8px;
            width: 180px;
            font-size: 12px;
            font-weight: 600;
        }


        /* Ensure buttons don't show on paper */
        @media print {
/* This is the key for showing watermarks/backgrounds in Print */
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }
            /* 1. Hide everything on the page */
            body * {
                visibility: hidden;
                margin: 0;
                padding: 0;
            }

            .salary-slip::before {
        opacity: 0.08 !important; /* Slightly darker for better visibility on paper */
        display: block !important;
    }

            /* 2. Show only the salary-slip and its children */
            .salary-slip,
            .salary-slip * {
                visibility: visible;
            }

            /* 3. Position the salary-slip at the top-left of the printed page */
            .salary-slip {
                position: absolute;
                left: 0;
                top: 0;
                width: 100% !important;
                margin: 0 !important;
                padding: 20px !important;
                /* Adjust padding for paper margins */
                border: 1px solid #eee !important;
                box-shadow: none !important;
                /* Keep footer/signatures at the bottom on A4 print */
                min-height: calc(297mm - 2cm) !important;
                display: flex !important;
                flex-direction: column !important;
            }

            .verification {
                margin-top: auto !important;
                width: 100% !important;
            }

            /* 4. Explicitly hide the button container so it doesn't leave a gap */
            .no-print {
                display: none !important;
            }

            /* 5. Hide the wrapper background/padding */
            .salary-wrapper {
                background: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            /* Optional: Remove header/footer (URL/Date) added by browsers */
            @page {
                margin: 1cm;
            }
        }
    </style>
    @php
        $roleSlug = Str::slug(Auth::user()->getRoleNames()->first());
    @endphp

    <div class="no-print relative z-50 flex items-center justify-end gap-3 mx-auto my-5" style="max-width: 850px;">
        <button onclick="window.print()"
            class="inline-block px-6 py-2.5 bg-slate-500 hover:bg-slate-600 text-white font-semibold text-sm rounded-md shadow-sm transition duration-150 ease-in-out cursor-pointer border-none">
            Print
        </button>

        <a href="{{ route('role.employee-salaries.action', ['role' => $roleSlug, 'id' => $data->id, 'action' => 'download']) }}"
            class="inline-block px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm rounded-md shadow-sm transition duration-150 ease-in-out no-underline">
            Download PDF
        </a>

        <a href="{{ route('role.employee-salaries.action', ['role' => $roleSlug, 'id' => $data->id, 'action' => 'email']) }}"
            class="inline-block px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm rounded-md shadow-sm transition duration-150 ease-in-out no-underline">
            Send Email
        </a>
    </div>
    <div class="salary-wrapper">
        <div class="salary-slip">

            <div class="slip-header">
                <div class="header-left">
                    <img src="{{ asset($data->user->company->logo ?? 'https://epal.com.bd/images/site-setting/69401c60d0949.png') }}"
                        alt="Logo" class="brand-logo">
                </div>

                <div class="header-center">
                    <div class="brand">
                        <h1>{{ $data->user->company->name ?? 'Epal Group' }}</h1>
                        <p>{{ $data->user->company->address ?? '' }}</p>
                        <p>Phone: {{ $data->user->company->phone ?? '' }} | Email: {{ $data->user->company->email ?? '' }}
                        </p>
                    </div>
                </div>

                <div class="header-right">
                    <div class="slip-status">
                        @if ($data->status == 'Pending')
                            <span class="badge" style="background: #fef3c7; color: #b45309;">Pending</span>
                        @elseif($data->status == 'Paid')
                            <span class="badge">Paid</span>
                        @endif
                        <p style="margin-top: 10px; font-weight: 700; color: var(--text-main);">Payslip
                            #{{ $data->year }}-{{ $data->month }}-{{ $data->id }}</p>
                    </div>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <label>Employee Name</label>
                    <span>{{ $data->user->name ?? '' }}</span>
                </div>
                <div class="info-item">
                    <label>Designation</label>
                    <span>{{ $data->user->profile->designation->name ?? '' }}</span>
                </div>
                <div class="info-item">
                    <label>Employee ID</label>
                    <span>{{ $data->user->employee_id_no ?? 'N/A' }}</span>
                </div>
                <div class="info-item">
                    <label>Pay Period</label>
                    <span> {{ $data->year }}/{{ $data->month }}</span>
                </div>
                <div class="info-item">
                    <label>Payment Method</label>
                    <span>{{ $data->payment_method ?? '' }}</span>
                </div>
                <div class="info-item">
                    <label>Generate Date</label>
                    <span>{{ $data->salary_generation_date ?? '' }}</span>
                </div>
            </div>

            @php
                // Whole taka, always rounded up; totals summed from the rounded
                // lines so the printed column adds up exactly.
                $tpl = $data->user->salary_template;

                $earnings = [
                    'Basic Salary'         => payslipRound($tpl->basic_salary ?? 0),
                    'House Rent Allowance' => payslipRound($tpl->house_rent ?? 0),
                    'Medical Allowance'    => payslipRound($tpl->medical_allowance ?? 0),
                    'Conveyance Allowance' => payslipRound($tpl->conveyance_allowance ?? 0),
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

            <div class="table-flex">
                <div class="table-container">
                    <h2>EARNINGS</h2>
                    <table class="data-table">
                        @foreach($earnings as $label => $amount)
                        <tr>
                            <td>{{ $label }}</td>
                            <td>{{ number_format($amount) }}</td>
                        </tr>
                        @endforeach
                        <tr class="total-row">
                            <td>Gross Earnings</td>
                            <td>{{ number_format($gross_earning) }}</td>
                        </tr>
                    </table>
                </div>

                <div class="table-container">
                    <h2>DEDUCTIONS</h2>
                    <table class="data-table">
                        @foreach($deductions as $label => $amount)
                        <tr>
                            <td>{{ $label }}</td>
                            <td>{{ number_format($amount) }}</td>
                        </tr>
                        @endforeach
                        <tr class="total-row">
                            <td>Total Deductions</td>
                            <td>{{ number_format($total_deductions) }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="adjustment-row" style="text-align: center; margin: 10px 0; font-weight: 600;">
                Salary Adjustment : {{ number_format($adjustment) }}
            </div>

            <div class="net-payment-section">
                <div class="net-label">
                    NET PAYABLE AMOUNT
                    <span class="amount-words">
                        Amount in words: {{ amountToWords($net_salary) }}
                    </span>
                </div>
                <div class="net-amount">
                    BDT {{ number_format($net_salary) }}
                </div>
            </div>
            <div class="security-hash" style="margin-top: 40px;">
                Note : {{ $data->notes ?? 'No note available' }}
            </div>
            <div class="verification">
                <div class="signature-group">
                    <div class="sig-area">
                        Employee Signature
                    </div>
                    <div class="sig-area">
                        Authorized Signatory
                    </div>
                </div>
                <div class="security-hash">
                    TIMESTAMP: {{ now()->format('Y-m-d H:i:s') }}
                </div>
            </div>

        </div>
    </div>
@endsection
