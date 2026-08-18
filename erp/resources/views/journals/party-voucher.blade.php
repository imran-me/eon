<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $voucherTitle }} — {{ $journal->reference ?? 'JV-'.$journal->id }}</title>
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
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-bottom: 18px;
}
.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 18px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    text-decoration: none;
}
.btn-print { background: #1d4ed8; color: #fff; }
.btn-back  { background: #e2e8f0; color: #374151; }
.btn:hover { opacity: .88; }

/* ── Top stripe (voucher type colour) ── */
.type-stripe {
    height: 6px;
    border-radius: 4px 4px 0 0;
    margin: -36px -40px 28px;
}
.stripe-customer { background: linear-gradient(90deg, #1d4ed8, #38bdf8); }
.stripe-supplier { background: linear-gradient(90deg, #15803d, #4ade80); }
.stripe-others   { background: linear-gradient(90deg, #7c3aed, #c084fc); }

/* ── Header ── */
.voucher-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 2px solid var(--accent);
    padding-bottom: 18px;
    margin-bottom: 20px;
}

.company-block { display: flex; align-items: center; gap: 14px; }
.company-logo  { width: 64px; height: 64px; object-fit: contain; border-radius: 6px; }
.company-logo-placeholder {
    width: 64px; height: 64px; border-radius: 6px;
    background: var(--accent); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; font-weight: 700;
}
.company-name { font-size: 20px; font-weight: 700; color: var(--accent); }
.company-sub  { font-size: 12px; color: #64748b; margin-top: 2px; }

.voucher-title-block { text-align: right; }
.voucher-title {
    font-size: 22px; font-weight: 800; letter-spacing: .5px;
    color: var(--accent); text-transform: uppercase;
}
.voucher-ref  { font-size: 14px; font-weight: 600; color: #475569; margin-top: 4px; }
.voucher-date { font-size: 12px; color: #94a3b8; margin-top: 2px; }

/* ── Party card ── */
.party-card {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border: 1.5px solid var(--accent-light);
    border-left: 4px solid var(--accent);
    border-radius: 6px;
    padding: 14px 18px;
    margin-bottom: 20px;
    background: var(--bg-light);
}
.party-label { font-size: 10px; text-transform: uppercase; letter-spacing: .7px; color: #94a3b8; margin-bottom: 4px; }
.party-name  { font-size: 18px; font-weight: 700; color: #1e293b; }
.party-info  { font-size: 12px; color: #475569; margin-top: 3px; }
.party-badge {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    color: #fff;
    background: var(--accent);
    align-self: flex-start;
    margin-top: 4px;
}

/* ── Meta grid ── */
.meta-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 14px 18px;
    margin-bottom: 20px;
}
.meta-item label { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: .6px; color: #94a3b8; margin-bottom: 3px; }
.meta-item span  { font-size: 13px; font-weight: 600; color: #1e293b; }
.source-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    background: #dbeafe;
    color: #1d4ed8;
}

/* ── Items table ── */
.items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 16px;
}
.items-table thead tr { background: var(--accent); color: #fff; }
.items-table thead th {
    padding: 9px 12px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .4px;
}
.items-table thead th.num { text-align: right; }

.items-table tbody tr { border-bottom: 1px solid #e2e8f0; }
.items-table tbody tr:nth-child(even) { background: #f8fafc; }
.items-table tbody td { padding: 9px 12px; font-size: 13px; vertical-align: top; }
.items-table tbody td.num { text-align: right; font-family: 'Courier New', monospace; font-weight: 600; }

.debit-val  { color: #1d4ed8; }
.credit-val { color: #15803d; }
.zero-val   { color: #cbd5e1; }

.acct-code { font-size: 11px; color: #94a3b8; }
.acct-name { font-weight: 600; }
.note-text { font-size: 11px; color: #64748b; margin-top: 2px; font-style: italic; }

/* ── Totals ── */
.items-table tfoot tr { background: #eff6ff; border-top: 2px solid var(--accent); }
.items-table tfoot td { padding: 10px 12px; font-weight: 700; font-size: 13px; }
.items-table tfoot td.num { font-family: 'Courier New', monospace; font-size: 14px; }

/* ── Amount in words ── */
.amount-words {
    border: 1px dashed var(--accent-light);
    border-radius: 6px;
    padding: 10px 16px;
    margin-bottom: 16px;
    background: var(--bg-light);
    font-size: 12px;
    color: #475569;
}
.amount-words strong { color: #1e293b; }

/* ── Balance badge ── */
.balance-row { display: flex; justify-content: flex-end; margin-bottom: 20px; }
.balance-badge { padding: 6px 16px; border-radius: 6px; font-size: 13px; font-weight: 700; }
.balanced   { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
.unbalanced { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }

/* ── Description ── */
.description-box {
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 12px 16px;
    margin-bottom: 24px;
    background: #f8fafc;
}
.description-box label { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: .6px; color: #94a3b8; margin-bottom: 4px; }
.description-box p { font-size: 13px; color: #374151; }

/* ── Signature ── */
.sig-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-top: 24px;
    border-top: 1px dashed #cbd5e1;
    padding-top: 24px;
}
.sig-box { text-align: center; }
.sig-line { border-top: 1px solid #94a3b8; margin: 40px 12px 6px; }
.sig-label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: .5px; }

/* ── Party acknowledgement box ── */
.ack-box {
    border: 1px solid var(--accent-light);
    border-radius: 6px;
    padding: 12px 16px;
    margin-top: 24px;
    background: var(--bg-light);
    display: flex;
    align-items: center;
    gap: 14px;
}
.ack-icon { font-size: 28px; }
.ack-text { font-size: 12px; color: #475569; flex: 1; }
.ack-text strong { color: #1e293b; display: block; margin-bottom: 2px; font-size: 13px; }

/* ── Footer ── */
.voucher-footer {
    text-align: center;
    margin-top: 22px;
    padding-top: 14px;
    border-top: 1px solid #e2e8f0;
    font-size: 11px;
    color: #94a3b8;
}

/* ── Print ── */
@media print {
    body { background: #fff; padding: 0; }
    .page { box-shadow: none; border-radius: 0; max-width: 100%; padding: 0; }
    .toolbar { display: none !important; }
    .type-stripe { margin: 0 0 28px; border-radius: 0; }
}
</style>
</head>
<body>
@php
    use Illuminate\Support\Str;

    $totalDebit  = $journal->items->sum('debit');
    $totalCredit = $journal->items->sum('credit');
    $isBalanced  = abs($totalDebit - $totalCredit) < 0.01;

    // Accent colours per party type
    $accentMap = [
        'customer' => ['#1d4ed8', '#bfdbfe', '#eff6ff'],
        'supplier' => ['#15803d', '#bbf7d0', '#f0fdf4'],
        'agent'    => ['#b45309', '#fde68a', '#fffbeb'],
        'vendor'   => ['#b45309', '#fde68a', '#fffbeb'],
    ];
    $defaults     = ['#7c3aed', '#e9d5ff', '#faf5ff'];
    [$accent, $accentLight, $bgLight] = $accentMap[$partyType] ?? $defaults;

    // Stripe class
    $stripeClass = match($partyType) {
        'customer' => 'stripe-customer',
        'supplier' => 'stripe-supplier',
        default    => 'stripe-others',
    };

    // Net amount to display (max of debit/credit — useful for single-party transactions)
    $netAmount = max($totalDebit, $totalCredit);
@endphp

<style>
    :root {
        --accent: {{ $accent }};
        --accent-light: {{ $accentLight }};
        --bg-light: {{ $bgLight }};
    }
</style>

{{-- No-print toolbar --}}
<div class="toolbar">
    <a href="{{ url()->previous() }}" class="btn btn-back">&#8592; Back</a>
    <button class="btn btn-print" onclick="window.print()">&#128438; Print / Save PDF</button>
</div>

<div class="page">

    {{-- Colour stripe --}}
    <div class="type-stripe {{ $stripeClass }}"></div>

    {{-- Company header --}}
    <div class="voucher-header">
        <div class="company-block">
            @if($company?->logo)
                <img src="{{ asset($company->logo) }}" alt="Logo" class="company-logo">
            @else
                <div class="company-logo-placeholder">{{ strtoupper(substr($company?->name ?? 'C', 0, 1)) }}</div>
            @endif
            <div>
                <div class="company-name">{{ $company?->name ?? 'Company Name' }}</div>
                <div class="company-sub">{{ $company?->address ?? '' }}</div>
                @if($company?->phone)<div class="company-sub">{{ $company->phone }}</div>@endif
                @if($company?->email)<div class="company-sub">{{ $company->email }}</div>@endif
            </div>
        </div>
        <div class="voucher-title-block">
            <div class="voucher-title">{{ $voucherTitle }}</div>
            <div class="voucher-ref">{{ $journal->reference ?? 'JV-'.str_pad($journal->id, 5, '0', STR_PAD_LEFT) }}</div>
            <div class="voucher-date">Date: {{ $journal->date->format('d M Y') }}</div>
        </div>
    </div>

    {{-- Party card --}}
    <div class="party-card">
        <div>
            <div class="party-label">{{ ucfirst($partyType) }}</div>
            <div class="party-name">{{ $partyName }}</div>
            @if($partyPhone)<div class="party-info">&#128222; {{ $partyPhone }}</div>@endif
            @if($partyEmail)<div class="party-info">&#9993; {{ $partyEmail }}</div>@endif
            @if($partyAddress)<div class="party-info">&#128205; {{ $partyAddress }}</div>@endif
        </div>
        <div class="party-badge">{{ ucfirst($partyType) }}</div>
    </div>

    {{-- Meta grid --}}
    <div class="meta-grid">
        <div class="meta-item">
            <label>Voucher No.</label>
            <span>{{ $journal->reference ?? 'JV-'.str_pad($journal->id, 5, '0', STR_PAD_LEFT) }}</span>
        </div>
        <div class="meta-item">
            <label>Date</label>
            <span>{{ $journal->date->format('d M Y') }}</span>
        </div>
        <div class="meta-item">
            <label>Source</label>
            <span class="source-badge">{{ ucfirst(str_replace('_', ' ', $journal->source)) }}</span>
        </div>
        <div class="meta-item">
            <label>Created By</label>
            <span>{{ $journal->createdBy?->name ?? '—' }}</span>
        </div>
        <div class="meta-item">
            <label>Created At</label>
            <span>{{ $journal->created_at->format('d M Y, h:i A') }}</span>
        </div>
        <div class="meta-item">
            <label>Status</label>
            <span style="color:{{ $isBalanced ? '#15803d' : '#dc2626' }};font-weight:700;">
                {{ $isBalanced ? 'Balanced' : 'Unbalanced' }}
            </span>
        </div>
    </div>

    {{-- Line items --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:30px">#</th>
                <th>Account</th>
                <th>Particulars</th>
                <th class="num">Debit</th>
                <th class="num">Credit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($journal->items as $i => $item)
            <tr>
                <td style="color:#94a3b8;font-size:11px;">{{ $i + 1 }}</td>
                <td>
                    <span class="acct-code">[{{ $item->account?->code ?? '—' }}]</span>
                    <span class="acct-name"> {{ $item->account?->name ?? '—' }}</span>
                </td>
                <td>
                    @if($item->note)
                        <span class="note-text">{{ $item->note }}</span>
                    @else
                        <span style="color:#cbd5e1;">—</span>
                    @endif
                </td>
                <td class="num">
                    @if($item->debit > 0)
                        <span class="debit-val">{{ number_format($item->debit, 2) }}</span>
                    @else
                        <span class="zero-val">—</span>
                    @endif
                </td>
                <td class="num">
                    @if($item->credit > 0)
                        <span class="credit-val">{{ number_format($item->credit, 2) }}</span>
                    @else
                        <span class="zero-val">—</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align:right;color:#475569;font-size:12px;padding-right:20px;">Total</td>
                <td class="num debit-val">{{ number_format($totalDebit, 2) }}</td>
                <td class="num credit-val">{{ number_format($totalCredit, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    {{-- Balance badge --}}
    <div class="balance-row">
        @if($isBalanced)
        <div class="balance-badge balanced">&#10003; Entry is Balanced</div>
        @else
        <div class="balance-badge unbalanced">&#9888; Unbalanced (Diff: {{ number_format(abs($totalDebit - $totalCredit), 2) }})</div>
        @endif
    </div>

    {{-- Description --}}
    @if($journal->description)
    <div class="description-box">
        <label>Description / Notes</label>
        <p>{{ $journal->description }}</p>
    </div>
    @endif

    {{-- Party acknowledgement --}}
    <div class="ack-box">
        <div class="ack-icon">
            @if($partyType === 'customer') &#128100;
            @elseif($partyType === 'supplier') &#127981;
            @elseif($partyType === 'agent') &#129309;
            @else &#128101;
            @endif
        </div>
        <div class="ack-text">
            <strong>{{ $partyName }}</strong>
            This voucher is issued to <strong>{{ $partyName }}</strong> as a record of the above transaction.
            Please retain for your reference.
        </div>
        <div style="text-align:right;min-width:160px;">
            <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px;">Net Amount</div>
            <div style="font-size:22px;font-weight:800;color:var(--accent);">{{ number_format($netAmount, 2) }}</div>
        </div>
    </div>

    {{-- Signatures --}}
    <div class="sig-row">
        <div class="sig-box">
            <div class="sig-line"></div>
            <div class="sig-label">Prepared By</div>
        </div>
        <div class="sig-box">
            <div class="sig-line"></div>
            <div class="sig-label">Authorized By</div>
        </div>
        <div class="sig-box">
            <div class="sig-line"></div>
            <div class="sig-label">{{ ucfirst($partyType) }}'s Signature</div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="voucher-footer">
        Printed on {{ now()->format('d M Y, h:i A') }} &nbsp;|&nbsp; {{ $company?->name ?? '' }}
        @if($company?->website) &nbsp;|&nbsp; {{ $company->website }} @endif
    </div>

</div>
</body>
</html>
