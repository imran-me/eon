<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>
    {{ $schedule->type === 'receive' ? 'Receivable Invoice' : 'Payment Voucher' }}
    — {{ 'SCHED-'.str_pad($schedule->id, 5, '0', STR_PAD_LEFT) }}
</title>
<style>
@page { size: A4 portrait; margin: 15mm; }
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 13px;
    color: #1e293b;
    background: #f1f5f9;
    padding: 24px;
}
.page {
    background: #fff;
    max-width: 780px;
    margin: 0 auto;
    padding: 36px 40px 32px;
    border-radius: 8px;
    box-shadow: 0 2px 16px rgba(0,0,0,.10);
}

/* ── Toolbar ── */
.toolbar {
    display: flex; gap: 10px; justify-content: flex-end; margin-bottom: 18px;
}
.btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 18px; border-radius: 6px; font-size: 13px; font-weight: 600;
    cursor: pointer; border: none; text-decoration: none;
}
.btn-print { background: #1d4ed8; color: #fff; }
.btn-back  { background: #e2e8f0; color: #374151; }
.btn:hover { opacity: .88; }

/* ── Header ── */
.voucher-header {
    display: flex; justify-content: space-between; align-items: flex-start;
    padding-bottom: 18px; margin-bottom: 22px;
}
.receive-header { border-bottom: 2px solid #2563eb; }
.pay-header     { border-bottom: 2px solid #d97706; }

.company-block { display: flex; align-items: center; gap: 14px; }
.company-logo  { width: 64px; height: 64px; object-fit: contain; border-radius: 6px; }
.company-logo-placeholder {
    width: 64px; height: 64px; border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; font-weight: 700; color: #fff;
}
.receive-placeholder { background: #2563eb; }
.pay-placeholder     { background: #d97706; }
.company-name { font-size: 20px; font-weight: 700; }
.receive-name { color: #2563eb; }
.pay-name     { color: #d97706; }
.company-sub  { font-size: 12px; color: #64748b; margin-top: 2px; }

.voucher-title-block { text-align: right; }
.voucher-title {
    font-size: 22px; font-weight: 800; letter-spacing: .5px; text-transform: uppercase;
}
.receive-title { color: #2563eb; }
.pay-title     { color: #d97706; }
.voucher-ref  { font-size: 14px; font-weight: 600; color: #475569; margin-top: 4px; }
.voucher-date { font-size: 12px; color: #94a3b8; margin-top: 2px; }

/* ── Party / Meta grid ── */
.info-grid {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 8px; margin-bottom: 18px;
}
.info-box {
    border: 1px solid #e2e8f0; border-radius: 6px;
    padding: 9px 11px; background: #f8fafc;
    border-top: 2px solid #e2e8f0;
}
.receive-box { border-top-color: #93c5fd; }
.pay-box     { border-top-color: #fcd34d; }
.info-box label {
    display: block; font-size: 9px; text-transform: uppercase;
    letter-spacing: .7px; color: #94a3b8; margin-bottom: 4px; font-weight: 700;
}
.info-box .val    { font-size: 12.5px; font-weight: 700; color: #1e293b; line-height: 1.3; }
.info-box .sub    { font-size: 10.5px; color: #64748b; margin-top: 2px; }
.info-box .mono   { font-family: 'Courier New', monospace; }

/* ── Amount banner ── */
.amount-banner {
    border-radius: 8px; padding: 16px 20px; margin-bottom: 22px;
    display: flex; justify-content: space-between; align-items: center;
}
.receive-banner { background: #eff6ff; border: 1.5px solid #bfdbfe; }
.pay-banner     { background: #fffbeb; border: 1.5px solid #fde68a; }
.amount-label   { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: #64748b; }
.amount-val     { font-size: 28px; font-weight: 800; font-family: 'Courier New', monospace; }
.receive-val    { color: #2563eb; }
.pay-val        { color: #d97706; }
.amount-words   { font-size: 11px; color: #64748b; margin-top: 4px; font-style: italic; }

/* ── Status badge ── */
.status-badge {
    display: inline-block; padding: 4px 14px; border-radius: 999px;
    font-size: 12px; font-weight: 700;
}
.s-pending   { background: #fef3c7; color: #92400e; }
.s-paid      { background: #d1fae5; color: #065f46; }
.s-overdue   { background: #fee2e2; color: #991b1b; }
.s-approved  { background: #dbeafe; color: #1e40af; }
.s-cancelled { background: #f3f4f6; color: #6b7280; }
.s-rejected  { background: #fce7f3; color: #9d174d; }

/* ── Detail table ── */
.detail-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
.detail-table thead tr { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
.detail-table thead th {
    padding: 10px 14px; text-align: left; font-size: 10px;
    font-weight: 800; text-transform: uppercase; letter-spacing: .7px; color: #64748b;
}
.detail-table thead th:last-child { text-align: left; }
.detail-table tbody td {
    padding: 9px 14px; border-bottom: 1px solid #f1f5f9; font-size: 12.5px; vertical-align: middle;
}
.detail-table tbody tr:last-child td { border-bottom: none; }
.detail-table tbody tr:nth-child(even) { background: #fafbfc; }
.detail-table tbody tr:hover { background: #f1f5f9; }
.detail-table .td-label {
    font-weight: 600; color: #64748b; width: 34%;
    font-size: 11.5px; text-transform: capitalize; letter-spacing: .2px;
}
.detail-table .td-val   { color: #1e293b; font-weight: 500; }
.detail-table .tr-amount td { background: #fffbeb !important; }
.detail-table .tr-amount .td-label { color: #92400e; font-weight: 700; }
.detail-table.receive-table .tr-amount td { background: #eff6ff !important; }
.detail-table.receive-table .tr-amount .td-label { color: #1e40af; font-weight: 700; }

/* ── Payment info block ── */
.payment-block {
    border: 1.5px solid #86efac; border-radius: 6px; padding: 14px 16px; margin-bottom: 20px; background: #f0fdf4;
}
.payment-block h4 { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #15803d; margin-bottom: 10px; }
.payment-row { display: flex; gap: 24px; flex-wrap: wrap; }
.payment-item label { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; margin-bottom: 3px; }
.payment-item span  { font-size: 13px; font-weight: 600; color: #1e293b; }

/* ── Note block ── */
.note-block {
    border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; background: #f8fafc;
}
.note-block label { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: .6px; color: #94a3b8; margin-bottom: 4px; font-weight: 600; }
.note-block p { font-size: 13px; color: #374151; }

/* ── Reschedule log ── */
.log-block { margin-bottom: 22px; }
.log-block h4 { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; margin-bottom: 8px; }
.log-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.log-table thead tr { background: #f1f5f9; }
.log-table thead th { padding: 6px 10px; text-align: left; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: #64748b; }
.log-table tbody td { padding: 6px 10px; border-bottom: 1px solid #f1f5f9; color: #475569; }

/* ── Signatures ── */
.sig-row {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;
    margin-top: 28px; border-top: 1px dashed #cbd5e1; padding-top: 24px;
}
.sig-box { text-align: center; }
.sig-line { border-top: 1px solid #94a3b8; margin: 40px 12px 6px; }
.sig-label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: .5px; }

/* ── Footer ── */
.voucher-footer {
    text-align: center; margin-top: 22px; padding-top: 12px;
    border-top: 1px solid #e2e8f0; font-size: 11px; color: #94a3b8;
}

/* ── Print ── */
@media print {
    body { background: #fff; padding: 0; }
    .page { box-shadow: none; border-radius: 0; max-width: 100%; padding: 0; }
    .toolbar { display: none !important; }
}
</style>
</head>
<body>
@php
    $isReceive  = $schedule->type === 'receive';
    $typeClass  = $isReceive ? 'receive' : 'pay';
    $voucherNo  = 'SCHED-' . str_pad($schedule->id, 5, '0', STR_PAD_LEFT);
    $role       = \Illuminate\Support\Str::slug(auth()->user()->getRoleNames()->first() ?? 'admin');

    /* Party name */
    $partyName = $schedule->party_name;
    if (!$partyName && $schedule->schedulable) {
        $s = $schedule->schedulable;
        $partyName = match($schedule->party_type) {
            'customer' => $s->customer?->name,
            'vendor'   => $s->supplier?->name,
            default    => null,
        };
    }
    $partyName ??= '—';

    /* Reference number */
    $ref = $schedule->source_label;
    if (!$ref && $schedule->schedulable) {
        $s   = $schedule->schedulable;
        $ref = $s->invoice_no ?? $s->purchase_no ?? $s->ticket_no ?? ('ID#'.$s->id);
    }

    $statusMap = [
        'pending'   => 's-pending',
        'paid'      => 's-paid',
        'overdue'   => 's-overdue',
        'approved'  => 's-approved',
        'cancelled' => 's-cancelled',
        'rejected'  => 's-rejected',
    ];
    $statusClass = $statusMap[$schedule->status] ?? 's-pending';
@endphp

{{-- Toolbar --}}
<div class="toolbar">
    <a href="{{ url()->previous() }}" class="btn btn-back">&#8592; Back</a>
    <button class="btn btn-print" onclick="window.print()">&#128438; Print / Save PDF</button>
</div>

<div class="page">

    {{-- Header --}}
                <div class="voucher-title {{ $typeClass }}-title" style="text-align: center;color:rgb(4, 115, 241);margin-bottom: 28px;">
                {{ $isReceive ? 'Receivable Invoice' : 'Payment Voucher' }}
            </div>
    <div class="voucher-header {{ $typeClass }}-header">
        <div class="company-block">
            @if($company?->logo && file_exists(public_path($company->logo)))
                <img src="{{ asset($company->logo) }}" alt="Logo" class="company-logo">
            @else
                <div class="company-logo-placeholder {{ $typeClass }}-placeholder">
                    {{ strtoupper(substr($company?->name ?? 'E', 0, 1)) }}
                </div>
            @endif
            <div>
                <div class="company-name {{ $typeClass }}-name">{{ $company?->name ?? 'Company Name' }}</div>
                @if($company?->address)<div class="company-sub">{{ $company->address }}</div>@endif
                @if($company?->phone)<div class="company-sub">{{ $company->phone }}</div>@endif
                @if($company?->email)<div class="company-sub">{{ $company->email }}</div>@endif
            </div>
        </div>
        <div class="voucher-title-block">

            <div class="voucher-ref">{{ $voucherNo }}</div>
            <div class="voucher-date">Date: {{ $schedule->scheduled_date->format('d M Y') }}</div>
            <div style="margin-top:8px;">
                <span class="status-badge {{ $statusClass }}">{{ ucfirst($schedule->status) }}</span>
            </div>
        </div>
    </div>

    {{-- Amount Banner --}}
    <div class="amount-banner {{ $typeClass }}-banner">
        <div>
            <div class="amount-label">{{ $isReceive ? 'Amount Receivable' : 'Amount Payable' }}</div>
            <div class="amount-val {{ $typeClass }}-val">৳ {{ number_format($schedule->amount, 2) }}</div>
            <div class="amount-words">
                {{ ucfirst((new \NumberToWords\NumberToWords())->getNumberTransformer('en')->toWords((int) $schedule->amount)) }} Taka only
            </div>
        </div>
        <div style="text-align:right;">
            <div class="amount-label">Type</div>
            <div style="font-size:16px;font-weight:700;color:{{ $isReceive ? '#2563eb' : '#d97706' }};">
                {{ $isReceive ? 'Receivable' : 'Payable' }}
            </div>
        </div>
    </div>

    {{-- Party / Reference Grid --}}
    <div class="info-grid">
        <div class="info-box {{ $typeClass }}-box">
            <label>{{ $isReceive ? 'Bill From' : 'Pay To' }}</label>
            <div class="val">{{ $partyName }}</div>
            <div class="sub" style="text-transform:capitalize;">{{ $schedule->party_type }}</div>
        </div>
        <div class="info-box {{ $typeClass }}-box">
            <label>Reference</label>
            <div class="val mono">{{ $ref ?? '—' }}</div>
            @if($schedule->schedulable_type)
                <div class="sub">{{ class_basename($schedule->schedulable_type) }}</div>
            @endif
        </div>
        <div class="info-box {{ $typeClass }}-box">
            <label>Scheduled Date</label>
            <div class="val">{{ $schedule->scheduled_date->format('d M Y') }}</div>
            @if($schedule->original_scheduled_date && $schedule->original_scheduled_date->ne($schedule->scheduled_date))
                <div class="sub">Orig: {{ $schedule->original_scheduled_date->format('d M Y') }} &times;{{ $schedule->reschedule_count }}</div>
            @endif
        </div>
        <div class="info-box {{ $typeClass }}-box">
            <label>Voucher No.</label>
            <div class="val mono">{{ $voucherNo }}</div>
            <div class="sub">{{ $schedule->created_at->format('d M Y, h:i A') }}</div>
        </div>
    </div>

    {{-- Details Table --}}
    <table class="detail-table {{ $typeClass }}-table">
        <thead>
            <tr>
                <th style="width:34%;">Description</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="td-label">Voucher No.</td>
                <td class="td-val" style="font-family:'Courier New',monospace;font-weight:700;letter-spacing:.5px;">{{ $voucherNo }}</td>
            </tr>
            <tr>
                <td class="td-label">{{ $isReceive ? 'Bill From' : 'Pay To' }}</td>
                <td class="td-val">
                    <span style="font-weight:700;">{{ $partyName }}</span>
                    <span style="font-size:11px;color:#94a3b8;margin-left:6px;">({{ ucfirst($schedule->party_type) }})</span>
                </td>
            </tr>
            <tr>
                <td class="td-label">Reference No.</td>
                <td class="td-val" style="font-family:'Courier New',monospace;">{{ $ref ?? '—' }}</td>
            </tr>
            <tr>
                <td class="td-label">Type</td>
                <td class="td-val">
                    <span style="display:inline-flex;align-items:center;gap:5px;font-weight:600;color:{{ $isReceive ? '#1d4ed8' : '#b45309' }};">
                        {!! $isReceive ? '&#8595;' : '&#8593;' !!} {{ $isReceive ? 'Receivable — Collect from party' : 'Payable — Pay to party' }}
                    </span>
                </td>
            </tr>
            <tr>
                <td class="td-label">Scheduled Date</td>
                <td class="td-val">{{ $schedule->scheduled_date->format('d M Y') }}
                    @if($schedule->original_scheduled_date && $schedule->original_scheduled_date->ne($schedule->scheduled_date))
                        <span style="font-size:11px;color:#94a3b8;margin-left:6px;">
                            (Original: {{ $schedule->original_scheduled_date->format('d M Y') }})
                        </span>
                    @endif
                </td>
            </tr>
            <tr class="tr-amount">
                <td class="td-label" style="font-size:12.5px;">Total Amount</td>
                <td class="td-val" style="font-family:'Courier New',monospace;font-weight:800;font-size:18px;letter-spacing:-.5px;
                    color:{{ $isReceive ? '#1d4ed8' : '#b45309' }};">
                    ৳ {{ number_format($schedule->amount, 2) }}
                </td>
            </tr>
            <tr>
                <td class="td-label">Status</td>
                <td class="td-val">
                    <span class="status-badge {{ $statusClass }}">{{ ucfirst($schedule->status) }}</span>
                    @if($schedule->status === 'approved' && $schedule->approvedBy)
                        <span style="font-size:11px;color:#059669;margin-left:10px;">
                            &#10003; {{ $schedule->approvedBy->name }}
                            {{ $schedule->approved_at ? ' · '.$schedule->approved_at->format('d M Y') : '' }}
                        </span>
                    @endif
                </td>
            </tr>
            @if($schedule->approval_note)
            <tr>
                <td class="td-label">Approval Note</td>
                <td class="td-val" style="color:#475569;font-style:italic;">{{ $schedule->approval_note }}</td>
            </tr>
            @endif
            <tr>
                <td class="td-label">Created By</td>
                <td class="td-val" style="font-weight:600;">{{ $schedule->createdBy?->name ?? '—' }}
                    <span style="font-size:11px;color:#94a3b8;font-weight:400;">&nbsp;&bull;&nbsp; {{ $schedule->created_at->format('d M Y, h:i A') }}</span>
                </td>
            </tr>
        </tbody>
    </table>

    {{-- Payment Info (if paid) --}}
    @if($schedule->status === 'paid' && $schedule->paid_date)
    <div class="payment-block">
        <h4>&#10003; Payment Received / Made</h4>
        <div class="payment-row">
            <div class="payment-item">
                <label>Paid Date</label>
                <span>{{ $schedule->paid_date->format('d M Y') }}</span>
            </div>
            @php $lastPaidLog = $schedule->logs->where('action','paid')->first(); @endphp
            @if($lastPaidLog?->reason)
            <div class="payment-item">
                <label>Payment Method / Bank</label>
                <span>{{ $lastPaidLog->reason }}</span>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Note --}}
    @if($schedule->note)
    <div class="note-block">
        <label>Note / Remarks</label>
        <p>{{ $schedule->note }}</p>
    </div>
    @endif

    @if($schedule->reschedule_reason)
    <div class="note-block" style="border-color:#c7d2fe;background:#eef2ff;">
        <label style="color:#6d28d9;">Last Reschedule Reason</label>
        <p>{{ $schedule->reschedule_reason }}</p>
    </div>
    @endif

    {{-- Reschedule / Activity Log --}}
    @if($schedule->logs->isNotEmpty())
    <div class="log-block">
        <h4>Activity Log</h4>
        <table class="log-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Action</th>
                    <th>Old Date</th>
                    <th>New Date</th>
                    <th>Reason</th>
                    <th>Done By</th>
                    <th>At</th>
                </tr>
            </thead>
            <tbody>
                @foreach($schedule->logs as $i => $log)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td style="font-weight:600;text-transform:capitalize;">{{ $log->action }}</td>
                    <td>{{ $log->old_date ? \Carbon\Carbon::parse($log->old_date)->format('d M Y') : '—' }}</td>
                    <td>{{ $log->new_date ? \Carbon\Carbon::parse($log->new_date)->format('d M Y') : '—' }}</td>
                    <td>{{ $log->reason ?? '—' }}</td>
                    <td>{{ $log->doneBy?->name ?? '—' }}</td>
                    <td>{{ $log->created_at?->format('d M Y, h:i A') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Signature Lines --}}
    <div class="sig-row">
        <div class="sig-box">
            <div class="sig-line"></div>
            <div class="sig-label">Prepared By</div>
        </div>
        <div class="sig-box">
            <div class="sig-line"></div>
            <div class="sig-label">{{ $isReceive ? 'Received By' : 'Checked By' }}</div>
        </div>
        <div class="sig-box">
            <div class="sig-line"></div>
            <div class="sig-label">Authorized By</div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="voucher-footer">
        Printed on {{ now()->format('d M Y, h:i A') }} &nbsp;|&nbsp; {{ $company?->name ?? '' }}
        @if($company?->website)&nbsp;|&nbsp; {{ $company->website }}@endif
    </div>

</div>

<script>
    window.onload = function () { window.print(); };
</script>
</body>
</html>
