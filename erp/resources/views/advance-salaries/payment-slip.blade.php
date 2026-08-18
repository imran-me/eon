@extends('layout.app')

@section('meta-information')
    <title>Advance Salary Slip | {{ $data->user->name ?? '' }}</title>
@endsection

@section('css')
<style>
    :root {
        --primary-indigo: #4f46e5;
        --slate-900: #0f172a;
        --slate-600: #475569;
        --slate-100: #f1f5f9;
        --emerald: #10b981;
    }

    .slip-wrapper {
        max-width: 850px;
        margin: 2rem auto;
        padding: 40px;
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
        position: relative;
        overflow: hidden;
    }

    /* Top Accent Line */
    .slip-wrapper::before {
        content: "";
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 5px;
        background: linear-gradient(90deg, var(--primary-indigo), #818cf8);
        z-index: 2;
    }

    /* Watermark */
    .slip-wrapper::after {
        content: "";
        position: absolute;
        top: 50%;
        left: 50%;
        width: 380px;
        height: 380px;
        background: url('{{ asset($data->user->company->logo ?? 'images/site-setting/69401c60d0949.png') }}') no-repeat center;
        background-size: contain;
        opacity: 0.05;
        transform: translate(-50%, -50%) rotate(-12deg);
        z-index: 0;
        pointer-events: none;
    }

    .slip-wrapper > * {
        position: relative;
        z-index: 1;
    }

    .slip-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-bottom: 30px;
    }

    .slip-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 40px;
    }

    .company-info .logo {
        height: 60px;
        margin-bottom: 12px;
    }

    .company-info h2 {
        font-size: 18px;
        font-weight: 800;
        color: var(--slate-900);
        margin: 0;
        text-transform: uppercase;
    }

    .voucher-title {
        text-align: right;
    }

    .voucher-title h1 {
        font-size: 24px;
        font-weight: 300;
        color: var(--slate-600);
        margin: 0;
        letter-spacing: 1px;
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

    /* Info Section */
    .info-section {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 40px;
        padding: 30px 0;
        border-top: 1px solid var(--slate-100);
    }

    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px dashed #e2e8f0;
    }

    .detail-label { color: var(--slate-600); font-size: 13px; }
    .detail-value { color: var(--slate-900); font-weight: 600; font-size: 14px; }

    /* Amount Box */
    .amount-highlight {
        background: var(--slate-900);
        border-radius: 12px;
        padding: 25px;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 20px;
    }

    .amount-highlight .label-group p { margin: 0; font-size: 12px; opacity: 0.7; }
    .amount-highlight .label-group h3 { margin: 5px 0 0; font-size: 18px; font-weight: 400; }
    
    .big-price {
        font-size: 32px;
        font-weight: 800;
        color: #fbbf24;
    }

    .reason-box {
        margin-top: 30px;
        background: var(--slate-100);
        padding: 20px;
        border-radius: 8px;
        font-size: 13px;
        line-height: 1.6;
    }

    .signature-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 100px;
        margin-top: 60px;
        padding-top: 20px;
    }

    .sig-line {
        border-top: 1px solid var(--slate-900);
        text-align: center;
        padding-top: 8px;
        font-size: 12px;
        font-weight: 700;
        color: var(--slate-900);
    }

    @media print {
        @page {
            size: A4;
            margin: 0;
        }

        html,
        body {
            width: 100% !important;
            height: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body * {
            visibility: hidden !important;
        }

        .slip-wrapper,
        .slip-wrapper * {
            visibility: visible !important;
        }

        .slip-wrapper {
            position: fixed;
            left: 0;
            top: 0;
            width: 100%;
            max-width: none !important;
            min-height: 100%;
            margin: 0 !important;
            padding: 18px;
            border-radius: 0;
            box-shadow: none;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            overflow: visible;
        }

        .slip-actions {
            display: none !important;
        }
    }
</style>
@endsection

@section('main-content')
@php
    $role = request()->route('role');
    $isPaid = ($data->status ?? '') === 'Approved';
@endphp

<div class="slip-wrapper">
    <div class="slip-actions">
        <a href="{{ route('role.advance-salaries.index', ['role' => $role]) }}" class="btn btn-light btn-sm shadow-sm">
            <i class="fas fa-chevron-left me-1"></i> Back
        </a>
        <button type="button" class="btn btn-dark btn-sm shadow-sm" onclick="window.print()">
            <i class="fas fa-print me-1"></i> Print Slip
        </button>
    </div>

    <div class="slip-header">
        <div class="company-info">
            <img src="{{ asset($data->user->company->logo ?? 'images/site-setting/69401c60d0949.png') }}" class="logo">
            <h2>{{ $data->user->company->name ?? 'EPAL IT SOLUTIONS' }}</h2>
        </div>
        <div class="voucher-title">
            <h1>ADVANCE VOUCHER</h1>
            <p style="margin:0; font-family: monospace; color: var(--slate-600);">REF: ADV-{{ str_pad($data->id, 5, '0', STR_PAD_LEFT) }}</p>
            <span class="status-pill {{ $isPaid ? 'status-paid' : 'status-unpaid' }}">
                {{ $data->status ?? 'Pending' }}
            </span>
        </div>
    </div>

    <div class="info-section">
        <div class="details-column">
            <div class="detail-row">
                <span class="detail-label">Employee Name</span>
                <span class="detail-value">{{ $data->user->name ?? 'N/A' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Employee ID</span>
                <span class="detail-value">#{{ $data->user->employee_id_no ?? 'N/A' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Designation</span>
                <span class="detail-value">{{ $data->user->profile->designation->name ?? 'N/A' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Disbursement Month</span>
                <span class="detail-value">{{ date('F, Y', strtotime($data->month)) }}</span>
            </div>
        </div>

        <div class="meta-column" style="background: var(--slate-100); padding: 20px; border-radius: 12px;">
            <div style="margin-bottom: 15px;">
                <label style="display:block; font-size:10px; color:var(--slate-600); text-transform: uppercase;">Payment Date</label>
                <span style="font-weight:700;">{{ $data->created_at ? date('d M Y', strtotime($data->created_at)) : 'TBD' }}</span>
            </div>
            <div>
                <label style="display:block; font-size:10px; color:var(--slate-600); text-transform: uppercase;">Generated By</label>
                <span style="font-weight:700;">System Generated</span>
            </div>
        </div>
    </div>

    <div class="amount-highlight">
        <div class="label-group">
            <p>TOTAL DISBURSED AMOUNT</p>
            <h3>Advance Salary Payment</h3>
        </div>
        <div class="big-price">
            <small style="font-size: 14px; vertical-align: middle;">BDT</small> 
            {{ number_format($data->amount ?? 0, 2) }}
        </div>
    </div>

    <div class="reason-box">
        <strong style="display: block; margin-bottom: 5px; color: var(--slate-900);">Reason for Request:</strong>
        {{ $data->reason ?: 'Standard salary advance request.' }}
    </div>

    <div class="signature-grid">
        <div class="sig-line">Employee Signature</div>
        <div class="sig-line">Authorized Approval</div>
    </div>

    <div style="margin-top: 50px; text-align: center; border-top: 1px solid var(--slate-100); padding-top: 15px;">
        <p style="font-size: 10px; color: #94a3b8; margin: 0;">
            This is an official document of <strong>{{ $data->user->company->name ?? 'EPAL IT SOLUTIONS' }}</strong>. 
            Printed on {{ now()->format('d M Y \a\t h:i A') }}
        </p>
    </div>
</div>
@endsection