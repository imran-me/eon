<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Party Statement</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; background: #fff; }
.page { padding: 36px 40px; }

/* Header */
.header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #1d4ed8; }
.company-name { font-size: 20px; font-weight: 700; color: #1d4ed8; }
.company-meta { font-size: 9px; color: #64748b; margin-top: 3px; line-height: 1.5; }
.doc-title { text-align: right; }
.doc-title h2 { font-size: 16px; font-weight: 700; color: #1e293b; }
.doc-title p { font-size: 9px; color: #64748b; margin-top: 2px; }

/* Party card */
.party-card { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; display: flex; justify-content: space-between; }
.party-name { font-size: 14px; font-weight: 700; color: #1e3a8a; }
.party-meta { font-size: 9px; color: #3b82f6; margin-top: 2px; }

/* Summary row */
.summary { display: flex; gap: 8px; margin-bottom: 16px; }
.sum-card { flex: 1; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; }
.sum-label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; font-weight: 600; }
.sum-value { font-size: 13px; font-weight: 700; color: #1e293b; margin-top: 2px; }
.sum-card.closing { background: #1d4ed8; border-color: #1d4ed8; }
.sum-card.closing .sum-label { color: #93c5fd; }
.sum-card.closing .sum-value { color: #fff; }

/* Table */
table { width: 100%; border-collapse: collapse; font-size: 10px; }
thead tr { background: #1d4ed8; color: #fff; }
thead th { padding: 7px 8px; text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: 0.05em; }
thead th.r { text-align: right; }
tbody tr { border-bottom: 1px solid #f1f5f9; }
tbody tr:nth-child(even) { background: #f8fafc; }
tbody td { padding: 6px 8px; }
tbody td.r { text-align: right; }
.opening-row td { font-style: italic; color: #64748b; }
.badge { display: inline-block; padding: 1px 6px; border-radius: 9px; font-size: 8px; font-weight: 600; }
.badge-payment { background: #dcfce7; color: #166534; }
.badge-invoice { background: #dbeafe; color: #1e40af; }
.badge-default { background: #f1f5f9; color: #475569; }
.debit  { color: #dc2626; font-weight: 600; }
.credit { color: #16a34a; font-weight: 600; }
.balance-dr { color: #dc2626; font-weight: 700; }
.balance-cr { color: #16a34a; font-weight: 700; }
.mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 9px; }

/* Footer */
tfoot tr { background: #1e3a8a; color: #fff; }
tfoot td { padding: 8px 8px; font-weight: 700; }
tfoot td.r { text-align: right; }

.print-meta { margin-top: 24px; padding-top: 10px; border-top: 1px solid #e2e8f0; font-size: 8px; color: #94a3b8; display: flex; justify-content: space-between; }
</style>
</head>
<body>
<div class="page">

    {{-- Header --}}
    <div class="header">
        <div>
            <div class="company-name">{{ $company->name ?? 'Company Name' }}</div>
            <div class="company-meta">
                {{ $company->address ?? '' }}<br>
                @if($company->phone) Tel: {{ $company->phone }} @endif
                @if($company->email) &nbsp;|&nbsp; {{ $company->email }} @endif
            </div>
        </div>
        <div class="doc-title">
            <h2>Party Statement</h2>
            <p>Bank-Style Ledger</p>
            <p style="margin-top:4px; color:#1d4ed8; font-weight:600;">Generated: {{ now()->format('d M Y') }}</p>
        </div>
    </div>

    {{-- Party Card --}}
    <div class="party-card">
        <div>
            <div class="party-name">{{ $party->name }}</div>
            <div class="party-meta">
                ID-{{ str_pad($party->id, 4, '0', STR_PAD_LEFT) }}
                @if($party->phone) &nbsp;·&nbsp; {{ $party->phone }} @endif
                @if($party->email) &nbsp;·&nbsp; {{ $party->email }} @endif
            </div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:9px; color:#3b82f6; font-weight:600;">
                {{ $party->getRoleNames()->map(fn($r) => strtoupper($r))->implode(' · ') }}
            </div>
        </div>
    </div>

    {{-- Summary --}}
    <div class="summary">
        <div class="sum-card">
            <div class="sum-label">Opening Balance</div>
            <div class="sum-value">৳{{ number_format($openingBalance, 0) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Total Debit (Dr)</div>
            <div class="sum-value" style="color:#dc2626;">৳{{ number_format($totalDebit, 0) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Total Credit (Cr)</div>
            <div class="sum-value" style="color:#16a34a;">৳{{ number_format($totalCredit, 0) }}</div>
        </div>
        <div class="sum-card closing">
            <div class="sum-label">Closing Balance</div>
            <div class="sum-value">৳{{ number_format(abs($closingBalance), 0) }} {{ $closingBalance > 0 ? 'Dr' : ($closingBalance < 0 ? 'Cr' : '') }}</div>
        </div>
    </div>

    {{-- Ledger Table --}}
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Voucher #</th>
                <th>Description</th>
                <th>Type</th>
                <th class="r">Debit (৳)</th>
                <th class="r">Credit (৳)</th>
                <th class="r">Balance (৳)</th>
            </tr>
        </thead>
        <tbody>
            @if($openingBalance != 0)
            <tr class="opening-row">
                <td></td>
                <td>—</td>
                <td>Opening Balance Brought Forward</td>
                <td></td>
                <td class="r">—</td>
                <td class="r">—</td>
                <td class="r mono">{{ number_format(abs($openingBalance), 2) }} Dr</td>
            </tr>
            @endif

            @forelse($transactions as $t)
            @php
                $badgeCls = match($t->type) {
                    'party_payment', 'bank_transfer' => 'badge-payment',
                    'party_invoice', 'ticket_sale', 'sale' => 'badge-invoice',
                    default => 'badge-default'
                };
                $badgeLabel = match($t->type) {
                    'party_payment'  => 'Payment',
                    'party_invoice'  => 'Invoice',
                    'bank_transfer'  => 'Transfer',
                    'ticket_sale'    => 'Ticket',
                    default => ucfirst(str_replace('_',' ',$t->type))
                };
                $bal = (float)$t->balance;
            @endphp
            <tr>
                <td>{{ \Carbon\Carbon::parse($t->payment_date)->format('d M y') }}</td>
                <td class="mono">{{ $t->reference_no ?? '—' }}</td>
                <td>{{ Str::limit($t->remarks, 55) }}</td>
                <td><span class="badge {{ $badgeCls }}">{{ $badgeLabel }}</span></td>
                <td class="r {{ $t->debit > 0 ? 'debit' : '' }}">{{ $t->debit > 0 ? number_format($t->debit, 2) : '—' }}</td>
                <td class="r {{ $t->credit > 0 ? 'credit' : '' }}">{{ $t->credit > 0 ? number_format($t->credit, 2) : '—' }}</td>
                <td class="r {{ $bal > 0 ? 'balance-dr' : ($bal < 0 ? 'balance-cr' : '') }}">
                    {{ number_format(abs($bal), 2) }} {{ $bal > 0 ? 'Dr' : ($bal < 0 ? 'Cr' : '') }}
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center; padding:20px; color:#94a3b8;">No transactions found</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4">Closing Balance — {{ now()->format('d M Y') }}</td>
                <td class="r">৳{{ number_format($totalDebit, 2) }}</td>
                <td class="r">৳{{ number_format($totalCredit, 2) }}</td>
                <td class="r">৳{{ number_format(abs($closingBalance), 2) }} {{ $closingBalance > 0 ? 'Dr' : ($closingBalance < 0 ? 'Cr' : '') }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="print-meta">
        <span>{{ $company->name ?? '' }} &nbsp;·&nbsp; {{ $company->address ?? '' }}</span>
        <span>Printed: {{ now()->format('d M Y H:i') }}</span>
    </div>

</div>
</body>
</html>
