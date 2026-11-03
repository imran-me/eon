<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account Ledger — [{{ $account->code }}] {{ $account->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
    @page { size:A4 portrait; margin:12mm; }
    :root { --tc:#3730a3; --tbg:#f5f7ff; --thd:#4f46e5; --bdr:#e2e8f0; --txt:#1e293b; --tf:"Montserrat",sans-serif; --ff:"Inter",sans-serif; --nf:"Space Mono",monospace; }
    *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; -webkit-print-color-adjust:exact!important; print-color-adjust:exact!important; }
    body { font-family:var(--ff); padding:28px; background:#f0f2f5; color:var(--txt); font-size:12px; }
    .actions { max-width:794px; margin:0 auto 12px; display:flex; justify-content:flex-end; gap:8px; }
    .actions a,.actions button { border-radius:4px; padding:8px 18px; font:600 12px var(--tf); cursor:pointer; text-decoration:none; }
    .back { border:0; background:#64748b; color:#fff; }
    .print { border:0; background:#4f46e5; color:#fff; }
    .pdf { border:1px solid #4f46e5; background:#fff; color:#4f46e5; }
    .sheet { max-width:794px; margin:auto; background:#fff; padding:40px; border-radius:4px; box-shadow:0 4px 20px rgba(0,0,0,.06); }
    .header { display:flex; justify-content:space-between; align-items:center; gap:28px; }
    .logo { max-width:190px; max-height:105px; object-fit:contain; display:block; }
    .logo-text { font:800 25px var(--tf); color:var(--tc); }
    .company { text-align:right; line-height:1.65; }
    .company strong { display:block; font:700 16px var(--tf); }
    .title { margin:16px 0 22px; padding:9px; border-top:2px solid var(--tc); background:var(--tbg); color:var(--tc); text-align:center; text-transform:uppercase; letter-spacing:2px; font:700 11px var(--tf); }
    .meta { display:flex; justify-content:space-between; align-items:flex-start; gap:28px; margin-bottom:20px; }
    .account h4 { margin-bottom:6px; color:var(--tc); text-transform:uppercase; letter-spacing:.05em; font:700 10px var(--tf); }
    .account strong { display:block; font:700 14px var(--tf); margin-bottom:4px; }
    .account p { line-height:1.65; }
    .code,.number { font-family:var(--nf); }
    .type { display:inline-block; margin-top:6px; padding:2px 8px; border-radius:10px; background:#e0e7ff; color:#3730a3; font-size:9px; font-weight:700; text-transform:capitalize; }
    .meta-table td { padding:2px 0 2px 18px; font-size:11px; font-weight:600; }
    .meta-table td:last-child { font-family:var(--nf); font-size:10px; }
    .summary { display:flex; border:1px solid var(--bdr); border-radius:6px; overflow:hidden; margin-bottom:20px; }
    .summary-item { flex:1; min-width:0; padding:10px 12px; border-right:1px solid var(--bdr); }
    .summary-item:last-child { border-right:0; background:var(--thd); }
    .summary-label { color:#94a3b8; text-transform:uppercase; letter-spacing:.05em; font-size:8px; font-weight:700; margin-bottom:4px; }
    .summary-value { font:700 10px var(--nf); white-space:nowrap; }
    .summary-item:last-child .summary-label { color:#c7d2fe; }
    .summary-item:last-child .summary-value { color:#fff; font-size:11px; }
    table.ledger { width:100%; table-layout:fixed; border-collapse:collapse; font-size:9px; }
    .ledger thead { display:table-header-group; }
    .ledger th { padding:7px 5px; background:var(--thd); color:#fff; text-align:left; text-transform:uppercase; letter-spacing:.03em; font:700 8px var(--tf); }
    .ledger th.right,.ledger td.right { text-align:right; }
    .ledger td { padding:6px 5px; border-bottom:1px solid var(--bdr); vertical-align:top; overflow-wrap:anywhere; }
    .ledger tbody tr:nth-child(even) td { background:#f8f9ff; }
    .ledger tr { break-inside:avoid; }
    .ledger .opening td { color:#64748b; font-style:italic; background:#eff6ff!important; }
    .ledger .ref { font:400 8px var(--nf); }
    .ledger .source { display:inline-block; padding:1px 5px; border-radius:8px; background:#f1f5f9; color:#475569; font-size:7px; font-weight:700; text-transform:capitalize; }
    .ledger .note { display:block; margin-top:2px; color:#94a3b8; font-size:7.5px; }
    .ledger .debit { color:#dc2626; font:700 8.5px var(--nf); white-space:nowrap; }
    .ledger .credit { color:#16a34a; font:700 8.5px var(--nf); white-space:nowrap; }
    .ledger .balance { color:#3730a3; font:700 8.5px var(--nf); white-space:nowrap; }
    .ledger tfoot td { padding:8px 5px; background:#4f46e5; color:#fff; font-weight:700; border:0; }
    .ledger tfoot td.right { font-family:var(--nf); white-space:nowrap; }
    .empty { padding:38px; text-align:center; color:#94a3b8; border:1px dashed var(--bdr); }
    .bottom { display:grid; grid-template-columns:1.2fr .8fr; gap:30px; margin-top:20px; }
    .note-box { color:#64748b; font-size:10px; line-height:1.7; }
    .amounts { width:100%; border-collapse:collapse; }
    .amounts td { padding:5px 0; border-bottom:1px solid #eee; font-size:10px; }
    .amounts td:last-child { text-align:right; font:700 9px var(--nf); }
    .amounts .grand td { color:var(--tc); border-bottom:2px solid var(--tc); font-weight:800; }
    .signature { display:flex; justify-content:flex-end; margin-top:42px; }
    .signature div { width:190px; padding-top:5px; border-top:1px solid #334155; text-align:center; font:700 9px var(--tf); }
    @media print {
        body { padding:0; background:#fff; }
        .actions { display:none!important; }
        .sheet { max-width:100%; padding:0; box-shadow:none; }
        .title { margin-bottom:14px; padding:7px; }
        .summary { margin-bottom:14px; }
        .signature { margin-top:28px; }
    }
</style>
</head>
<body>
@php
    $dateFrom = request('date_from');
    $dateTo = request('date_to');
    $searched = request()->filled('search');
    $balanceLabel = $searched ? 'Filtered Closing' : 'Closing Balance';
    $logoSrc = optional($company)->logo
        ? (file_exists(public_path($company->logo)) ? asset($company->logo) : asset('logo.png'))
        : null;
@endphp

<div class="actions">
    <a class="back" href="javascript:history.back()">← Back</a>
    <button class="print" type="button" onclick="window.print()">Print</button>
    <button class="pdf" type="button" onclick="window.print()">Download PDF</button>
</div>

<main class="sheet">
    <header class="header">
        <div>
            @if($logoSrc)<img class="logo" src="{{ $logoSrc }}" alt="{{ optional($company)->name }}">@else<div class="logo-text">{{ optional($company)->name ?? config('app.name') }}</div>@endif
        </div>
        <div class="company">
            <strong>{{ optional($company)->name ?? config('app.name') }}</strong>
            @if(optional($company)->address){{ $company->address }}<br>@endif
            {{ optional($company)->phone }}{{ optional($company)->phone && optional($company)->email ? ' | ' : '' }}{{ optional($company)->email }}
        </div>
    </header>

    <div class="title">Account Ledger Statement</div>

    <section class="meta">
        <div class="account">
            <h4>Account Details</h4>
            <strong>[{{ $account->code }}] {{ $account->name }}</strong>
            @if($account->parent)<p>Parent: [{{ $account->parent->code }}] {{ $account->parent->name }}</p>@endif
            <span class="type">{{ $account->type }}</span>
        </div>
        <table class="meta-table">
            <tr><td>Period</td><td>{{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d M Y') : 'Beginning' }} → {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d M Y') : 'Today' }}</td></tr>
            <tr><td>Scope</td><td>{{ $viewAllCompanies ? 'All companies' : (optional($company)->name ?? 'Company') }}</td></tr>
            @if($searched)<tr><td>Search</td><td>{{ request('search') }}</td></tr>@endif
            <tr><td>Generated</td><td>{{ now()->format('d M Y') }}</td></tr>
            <tr><td>Printed By</td><td>{{ Auth::user()->name }}</td></tr>
        </table>
    </section>

    <section class="summary">
        <div class="summary-item"><div class="summary-label">Opening Balance</div><div class="summary-value">৳{{ number_format(abs($openingBalance),2) }} {{ $openingBalance < 0 ? 'Cr' : 'Dr' }}</div></div>
        <div class="summary-item"><div class="summary-label">Total Debit</div><div class="summary-value" style="color:#dc2626">৳{{ number_format($totalDebit,2) }}</div></div>
        <div class="summary-item"><div class="summary-label">Total Credit</div><div class="summary-value" style="color:#16a34a">৳{{ number_format($totalCredit,2) }}</div></div>
        @if(in_array($account->type,['income','expense']))<div class="summary-item"><div class="summary-label">P&amp;L Movement</div><div class="summary-value">৳{{ number_format($periodMovement,2) }}</div></div>@endif
        <div class="summary-item"><div class="summary-label">{{ $balanceLabel }}</div><div class="summary-value">৳{{ number_format(abs($closingBalance),2) }} {{ $closingBalance < 0 ? 'Cr' : 'Dr' }}</div></div>
    </section>

    @if($rows->isEmpty())
        <div class="empty">No transactions found for this statement.</div>
    @else
    <table class="ledger">
        <colgroup><col style="width:10%"><col style="width:13%"><col style="width:12%"><col style="width:29%"><col style="width:12%"><col style="width:12%"><col style="width:12%"></colgroup>
        <thead><tr><th>Date</th><th>Reference</th><th>Source</th><th>Description / Note</th><th class="right">Debit (৳)</th><th class="right">Credit (৳)</th><th class="right">Balance (৳)</th></tr></thead>
        <tbody>
            <tr class="opening"><td>{{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d M y') : 'B/F' }}</td><td>—</td><td>Opening</td><td>Opening Balance Brought Forward</td><td class="right">—</td><td class="right">—</td><td class="right number">{{ number_format(abs($openingBalance),2) }} {{ $openingBalance < 0 ? 'Cr' : 'Dr' }}</td></tr>
            @foreach($rows as $row)
            <tr>
                <td>{{ \Carbon\Carbon::parse($row['date'])->format('d M y') }}</td>
                <td class="ref">{{ $row['reference'] ?: '—' }}</td>
                <td><span class="source">{{ str_replace('_',' ',$row['source']) }}</span></td>
                <td>{{ $row['description'] ?: '—' }}@if($row['note'])<span class="note">{{ $row['note'] }}</span>@endif</td>
                <td class="right debit">{{ $row['debit'] > 0 ? number_format($row['debit'],2) : '—' }}</td>
                <td class="right credit">{{ $row['credit'] > 0 ? number_format($row['credit'],2) : '—' }}</td>
                <td class="right balance">{{ number_format(abs($row['balance']),2) }} {{ $row['balance'] < 0 ? 'Cr' : 'Dr' }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="4">Closing Balance · {{ number_format($transactionCount) }} transactions</td><td class="right">{{ number_format($totalDebit,2) }}</td><td class="right">{{ number_format($totalCredit,2) }}</td><td class="right">{{ number_format(abs($closingBalance),2) }} {{ $closingBalance < 0 ? 'Cr' : 'Dr' }}</td></tr></tfoot>
    </table>
    @endif

    <section class="bottom">
        <div class="note-box"><strong>Statement note</strong><br>This report is generated from posted journal entries. Debit and credit totals should be read together with the selected period, company scope and any search filter shown above.</div>
        <table class="amounts"><tr><td>Opening</td><td>৳{{ number_format(abs($openingBalance),2) }}</td></tr><tr><td>Total Debit</td><td>৳{{ number_format($totalDebit,2) }}</td></tr><tr><td>Total Credit</td><td>৳{{ number_format($totalCredit,2) }}</td></tr><tr class="grand"><td>{{ $balanceLabel }}</td><td>৳{{ number_format(abs($closingBalance),2) }} {{ $closingBalance < 0 ? 'Cr' : 'Dr' }}</td></tr></table>
    </section>
    <div class="signature"><div>Authorised Signature</div></div>
</main>
</body>
</html>
