<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Helvetica, sans-serif;
            color: #0f172a;
            margin: 0;
            padding: 0;
            font-size: 12px;
            background: #ffffff;
        }

        .slip-wrapper {
            max-width: 850px;
            margin: 0 auto;
            padding: 40px;
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            position: relative;
            overflow: hidden;
        }

        .slip-wrapper:before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: #4f46e5;
            z-index: 2;
        }

        .slip-wrapper:after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 380px;
            height: 380px;
            background: url('{{ $logoBase64 }}') no-repeat center;
            background-size: contain;
            opacity: 0.05;
            transform: translate(-50%, -50%) rotate(-12deg);
            z-index: 0;
        }

        .slip-wrapper > * {
            position: relative;
            z-index: 1;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
            border-bottom: 1px solid #f1f5f9;
        }

        .logo {
            width: 60px;
            height: auto;
            margin-bottom: 12px;
        }

        .company-name {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            text-transform: uppercase;
        }

        .voucher-title {
            font-size: 24px;
            font-weight: 300;
            color: #475569;
            margin: 0;
            letter-spacing: 1px;
            text-align: right;
        }

        .voucher-ref {
            margin: 0;
            font-family: monospace;
            color: #475569;
            font-size: 12px;
        }

        .status-pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            margin-top: 8px;
        }

        .status-paid { background: #dcfce7; color: #166534; }
        .status-unpaid { background: #fee2e2; color: #991b1b; }

        .info-layout {
            width: 100%;
            border-collapse: collapse;
            border-top: 1px solid #f1f5f9;
        }

        .details-col {
            width: 68%;
            padding: 30px 40px 0 0;
            vertical-align: top;
        }

        .meta-col {
            width: 32%;
            padding-top: 30px;
            vertical-align: top;
        }

        .detail-row {
            width: 100%;
            border-collapse: collapse;
        }

        .detail-row td {
            padding: 10px 0;
            border-bottom: 1px dashed #e2e8f0;
        }

        .detail-label {
            color: #475569;
            font-size: 13px;
        }

        .detail-value {
            color: #0f172a;
            font-weight: 600;
            font-size: 14px;
            text-align: right;
        }

        .meta-box {
            background: #f1f5f9;
            border-radius: 12px;
            padding: 20px;
        }

        .meta-label {
            display: block;
            font-size: 10px;
            color: #475569;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .meta-value {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
        }

        .amount-highlight {
            width: 100%;
            border-collapse: collapse;
            background: #0f172a;
            border-radius: 12px;
            margin-top: 20px;
        }

        .amount-highlight td {
            padding: 25px;
            color: #ffffff;
        }

        .amount-small {
            margin: 0;
            font-size: 12px;
            opacity: 0.7;
        }

        .amount-title {
            margin-top: 5px;
            font-size: 18px;
            font-weight: 400;
        }

        .amount-value {
            text-align: right;
            font-size: 32px;
            font-weight: 800;
            color: #fbbf24;
        }

        .reason-box {
            margin-top: 30px;
            background: #f1f5f9;
            padding: 20px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.6;
            color: #475569;
        }

        .reason-title {
            display: block;
            margin-bottom: 5px;
            color: #0f172a;
            font-weight: 700;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 60px;
        }

        .sig-line {
            border-top: 1px solid #0f172a;
            text-align: center;
            padding-top: 8px;
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
        }

        .footer-note {
            margin-top: 50px;
            text-align: center;
            border-top: 1px solid #f1f5f9;
            padding-top: 15px;
            font-size: 10px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
@php
    $paymentStatus = $data->status ?? 'Pending';
    $isPaid = $paymentStatus === 'Approved';
@endphp

<div class="slip-wrapper">
    <table class="header-table">
        <tr>
            <td width="45%" align="left" valign="middle">
                <img src="{{ $logoBase64 }}" class="logo" alt="Company Logo">
                <h2 class="company-name">{{ $data->user->company->name ?? 'EPAL IT SOLUTIONS' }}</h2>
            </td>
            <td width="55%" align="right" valign="middle">
                <h1 class="voucher-title">ADVANCE VOUCHER</h1>
                <p class="voucher-ref">REF: ADV-{{ str_pad($data->id, 5, '0', STR_PAD_LEFT) }}</p>
                <span class="status-pill {{ $isPaid ? 'status-paid' : 'status-unpaid' }}">{{ $paymentStatus }}</span>
            </td>
        </tr>
    </table>

    <table class="info-layout">
        <tr>
            <td class="details-col">
                <table class="detail-row">
                    <tr>
                        <td class="detail-label">Employee Name</td>
                        <td class="detail-value">{{ $data->user->name ?? 'N/A' }}</td>
                    </tr>
                </table>
                <table class="detail-row">
                    <tr>
                        <td class="detail-label">Employee ID</td>
                        <td class="detail-value">#{{ $data->user->employee_id_no ?? 'N/A' }}</td>
                    </tr>
                </table>
                <table class="detail-row">
                    <tr>
                        <td class="detail-label">Designation</td>
                        <td class="detail-value">{{ $data->user->profile->designation->name ?? 'N/A' }}</td>
                    </tr>
                </table>
                <table class="detail-row">
                    <tr>
                        <td class="detail-label">Disbursement Month</td>
                        <td class="detail-value">{{ date('F, Y', strtotime($data->month)) }}</td>
                    </tr>
                </table>
            </td>

            <td class="meta-col">
                <div class="meta-box">
                    <div style="margin-bottom: 15px;">
                        <label class="meta-label">Payment Date</label>
                        <span class="meta-value">{{ $data->created_at ? date('d M Y', strtotime($data->created_at)) : 'TBD' }}</span>
                    </div>
                    <div>
                        <label class="meta-label">Generated By</label>
                        <span class="meta-value">System Generated</span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table class="amount-highlight">
        <tr>
            <td width="65%">
                <p class="amount-small">TOTAL DISBURSED AMOUNT</p>
                <div class="amount-title">Advance Salary Payment</div>
            </td>
            <td width="35%" class="amount-value">
                <span style="font-size: 14px; vertical-align: middle; color: #ffffff;">BDT</span>
                {{ number_format($data->amount ?? 0, 2) }}
            </td>
        </tr>
    </table>

    <div class="reason-box">
        <strong class="reason-title">Reason for Request:</strong>
        {{ $data->reason ?: 'Standard salary advance request.' }}
    </div>

    <table class="signature-table">
        <tr>
            <td width="10%"></td>
            <td width="35%" class="sig-line">Employee Signature</td>
            <td width="10%"></td>
            <td width="35%" class="sig-line">Authorized Approval</td>
            <td width="10%"></td>
        </tr>
    </table>

    <div class="footer-note">
        This is an official document of <strong>{{ $data->user->company->name ?? 'EPAL IT SOLUTIONS' }}</strong>.
        Printed on {{ now()->format('d M Y \a\t h:i A') }}
    </div>
</div>

</body>
</html>