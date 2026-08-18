<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Journal Voucher — {{ $journal->reference ?? 'JV-'.$journal->id }}</title>
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

/* ── No-print toolbar ── */
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

/* ── Header ── */
.voucher-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 2px solid #1d4ed8;
    padding-bottom: 18px;
    margin-bottom: 22px;
}
.company-block { display: flex; align-items: center; gap: 14px; }
.company-logo  { width: 64px; height: 64px; object-fit: contain; border-radius: 6px; }
.company-logo-placeholder {
    width: 64px; height: 64px; border-radius: 6px;
    background: #1d4ed8; color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; font-weight: 700;
}
.company-name  { font-size: 20px; font-weight: 700; color: #1d4ed8; }
.company-sub   { font-size: 12px; color: #64748b; margin-top: 2px; }

.voucher-title-block { text-align: right; }
.voucher-title {
    font-size: 22px; font-weight: 800; letter-spacing: .5px;
    color: #1d4ed8; text-transform: uppercase;
}
.voucher-ref   { font-size: 14px; font-weight: 600; color: #475569; margin-top: 4px; }
.voucher-date  { font-size: 12px; color: #94a3b8; margin-top: 2px; }

/* ── Meta grid ── */
.meta-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 14px 18px;
    margin-bottom: 22px;
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
    margin-bottom: 18px;
}
.items-table thead tr {
    background: #1d4ed8;
    color: #fff;
}
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
.party-tag { font-size: 11px; color: #7c3aed; margin-top: 2px; }
.note-text { font-size: 11px; color: #64748b; margin-top: 2px; font-style: italic; }

/* ── Totals row ── */
.items-table tfoot tr { background: #eff6ff; border-top: 2px solid #1d4ed8; }
.items-table tfoot td { padding: 10px 12px; font-weight: 700; font-size: 13px; }
.items-table tfoot td.num { font-family: 'Courier New', monospace; font-size: 14px; }

/* ── Balance badge ── */
.balance-row {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 22px;
}
.balance-badge {
    padding: 6px 16px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 700;
}
.balanced   { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
.unbalanced { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }

/* ── Description ── */
.description-box {
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 12px 16px;
    margin-bottom: 28px;
    background: #f8fafc;
}
.description-box label { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: .6px; color: #94a3b8; margin-bottom: 4px; }
.description-box p { font-size: 13px; color: #374151; }

/* ── Signatures ── */
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

/* ── Footer ── */
.voucher-footer {
    text-align: center;
    margin-top: 24px;
    padding-top: 14px;
    border-top: 1px solid #e2e8f0;
    font-size: 11px;
    color: #94a3b8;
}

/* ── Print overrides ── */
@media print {
    body { background: #fff; padding: 0; }
    .page { box-shadow: none; border-radius: 0; max-width: 100%; padding: 0; }
    .toolbar { display: none !important; }
}
</style>
</head>
<body>
@php
    $totalDebit  = $journal->items->sum('debit');
    $totalCredit = $journal->items->sum('credit');
    $isBalanced  = abs($totalDebit - $totalCredit) < 0.01;
    $role        = \Illuminate\Support\Str::slug(auth()->user()->getRoleNames()->first() ?? 'admin');
@endphp

{{-- No-print toolbar --}}
<div class="toolbar no-print">
    <a href="{{ url()->previous() }}" class="btn btn-back">&#8592; Back</a>
    <button class="btn btn-print" onclick="window.print()">&#128438; Print / Save PDF</button>
</div>

<div class="page">

    {{-- Header --}}
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
                @if($company?->phone)
                <div class="company-sub">{{ $company->phone }}</div>
                @endif
                @if($company?->email)
                <div class="company-sub">{{ $company->email }}</div>
                @endif
            </div>
        </div>
        <div class="voucher-title-block">
            <div class="voucher-title">Voucher</div>
            <div class="voucher-ref">{{ $journal->reference ?? 'JV-'.str_pad($journal->id, 5, '0', STR_PAD_LEFT) }}</div>
            <div class="voucher-date">Date: {{ $journal->date->format('d M Y') }}</div>
        </div>
    </div>

    {{-- Meta --}}
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
                <th>Party</th>
                <th class="num">Debit</th>
                <th class="num">Credit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($journal->items as $i => $item)
            @php
                $partyName = null;
                if ($item->party_type && $item->party_id) {
                    if (in_array($item->party_type, ['customer', 'supplier'])) {
                        $partyName = $item->party?->name;
                    } else {
                        $partyName = $item->partyUser?->name;
                    }
                }
            @endphp
            <tr>
                <td style="color:#94a3b8;font-size:11px;">{{ $i + 1 }}</td>
                <td>
                    <span class="acct-code">[{{ $item->account?->code ?? '—' }}]</span>
                    <span class="acct-name"> {{ $item->account?->name ?? '—' }}</span>
                    @if($item->note)
                    <div class="note-text">{{ $item->note }}</div>
                    @endif
                </td>
                <td>
                    @if($partyName)
                    <div class="party-tag">
                        {{ ucfirst($item->party_type) }}: {{ $partyName }}
                    </div>
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
        <div class="balance-badge unbalanced">&#9888; Entry is NOT Balanced (Diff: {{ number_format(abs($totalDebit - $totalCredit), 2) }})</div>
        @endif
    </div>

    {{-- Description --}}
    @if($journal->description)
    <div class="description-box">
        <label>Description / Notes</label>
        <p>{{ $journal->description }}</p>
    </div>
    @endif

    {{-- Signature lines --}}
    <div class="sig-row">
        <div class="sig-box">
            <div class="sig-line"></div>
            <div class="sig-label">Prepared By</div>
        </div>
        <div class="sig-box">
            <div class="sig-line"></div>
            <div class="sig-label">Checked By</div>
        </div>
        <div class="sig-box">
            <div class="sig-line"></div>
            <div class="sig-label">Authorized By</div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="voucher-footer">
        Printed on {{ now()->format('d M Y, h:i A') }} &nbsp;|&nbsp; {{ $company?->name ?? '' }}
        @if($company?->website)
        &nbsp;|&nbsp; {{ $company->website }}
        @endif
    </div>

</div>
</body>
</html>
