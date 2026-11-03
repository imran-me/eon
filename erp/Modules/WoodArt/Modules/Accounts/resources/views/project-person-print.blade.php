<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Project Person Statement — {{ $person->name }}</title>
<style>
@page { size:A4 portrait; margin:12mm; }
* { box-sizing:border-box; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
body { margin:0; padding:24px; background:#eef2f7; color:#172033; font:12px Arial,sans-serif; }
.actions { max-width:794px; margin:0 auto 10px; text-align:right; }
.actions a,.actions button { border:0; border-radius:5px; padding:8px 16px; margin-left:6px; cursor:pointer; text-decoration:none; font-weight:700; }
.back { background:#64748b; color:#fff; }.print { background:#4f46e5;color:#fff; }
.sheet { max-width:794px; margin:auto; background:#fff; padding:34px; box-shadow:0 5px 20px #00000012; }
.header { display:flex; justify-content:space-between; align-items:center; gap:24px; }
.logo-wrap { width:210px; min-height:72px; display:flex; align-items:center; justify-content:flex-start; }
.logo { display:block; width:auto; max-width:190px; height:auto; max-height:90px; object-fit:contain; object-position:left center; }
.company { text-align:right; line-height:1.6; }.company strong { display:block;font-size:15px; }
.title { margin:18px 0; padding:9px; border-top:2px solid #3730a3; background:#f3f4ff; color:#3730a3; text-align:center; text-transform:uppercase; letter-spacing:1.5px; font-weight:800; }
.meta { display:flex; justify-content:space-between; gap:20px; margin-bottom:18px; }.meta h4 { margin:0 0 5px;color:#3730a3;text-transform:uppercase;font-size:10px;letter-spacing:.7px; }.meta p { margin:3px 0;line-height:1.45; }.meta table td { padding:2px 0 2px 16px; }.meta table td:first-child { color:#64748b; }
.summary { display:grid;grid-template-columns:repeat(3,1fr);border:1px solid #dfe5ee;border-radius:6px;overflow:hidden;margin-bottom:18px; }
.summary>div { padding:10px 12px;border-right:1px solid #dfe5ee; }.summary>div:last-child { border:0;background:#f8f8ff; }.sl { color:#64748b;text-transform:uppercase;font-size:9px;font-weight:700;letter-spacing:.5px; }.sv { margin-top:4px;font-size:15px;font-weight:800; }
table.ledger { width:100%;border-collapse:collapse;table-layout:fixed;font-size:10px; }.ledger th { background:#4f46e5;color:#fff;text-transform:uppercase;font-size:8px;letter-spacing:.4px;padding:7px 6px;text-align:left; }.ledger td { border-bottom:1px solid #e2e8f0;padding:7px 6px;vertical-align:top; }.ledger .num { text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums; }.muted { color:#64748b;font-size:9px;margin-top:2px; }.ref { color:#818cf8;font-family:monospace;font-size:8px; }.status { display:inline-block;border-radius:10px;background:#eef2ff;color:#4338ca;padding:2px 6px;font-size:8px;font-weight:700; }.reversed { opacity:.55; }.total td { background:#f8fafc;font-weight:800;border-top:2px solid #cbd5e1; }
.note { margin-top:12px;padding:9px 11px;background:#f8fafc;border-left:3px solid #6366f1;color:#475569;font-size:9px;line-height:1.5; }
.signatures { display:grid;grid-template-columns:repeat(3,1fr);gap:45px;margin-top:48px;text-align:center;color:#475569;font-size:9px; }.signatures div { border-top:1px solid #64748b;padding-top:6px; }
.footer { margin-top:20px;text-align:center;color:#94a3b8;font-size:8px; }
@media print { body{padding:0;background:#fff}.actions{display:none}.sheet{padding:0;box-shadow:none;max-width:none}.ledger tr{page-break-inside:avoid}.signatures{page-break-inside:avoid} }
</style>
</head>
<body>
<div class="actions"><a class="back" href="javascript:history.back()">← Back</a><button class="print" onclick="window.print()">Print / PDF</button></div>
<main class="sheet">
    <header class="header">
        <div class="logo-wrap">
            @php
                $storedLogo = $company?->logo ? ltrim((string) $company->logo, '/\\') : null;
                $logoSrc = $storedLogo && file_exists(public_path($storedLogo))
                    ? asset($storedLogo)
                    : asset('logo.png');
            @endphp
            <img class="logo" src="{{ $logoSrc }}" alt="{{ $company?->name ?? config('app.name') }}">
        </div>
        <div class="company"><strong>{{ $company?->name ?? config('app.name') }}</strong>@if($company?->address){{ $company->address }}<br>@endif{{ $company?->phone }}{{ $company?->phone && $company?->email ? ' | ' : '' }}{{ $company?->email }}</div>
    </header>

    <div class="title">Project Team Statement of Account</div>
    @php
        $money = fn($n) => number_format(abs((float)$n), 2);
        $balance = (float)$ledger['balance'];
        $balanceLabel = abs($balance) < .005 ? 'Settled' : ($balance > 0 ? 'Company owes employee' : 'Employee holds company funds');
        $rows = $ledger['rows'];
        $from = $rows->min('date'); $to = $rows->max('date');
    @endphp
    <section class="meta">
        <div><h4>Employee / Site Team</h4><p><strong>{{ $person->name }}</strong></p>@if($person->phone)<p>{{ $person->phone }}</p>@endif @if($person->email)<p>{{ $person->email }}</p>@endif</div>
        <table><tr><td>Project</td><td><strong>{{ $dept->name }}</strong></td></tr><tr><td>Period</td><td>{{ $from ? \Carbon\Carbon::parse($from)->format('d M Y') : '—' }} — {{ $to ? \Carbon\Carbon::parse($to)->format('d M Y') : '—' }}</td></tr><tr><td>Generated</td><td>{{ now()->format('d M Y') }}</td></tr><tr><td>Printed by</td><td>{{ auth()->user()->name }}</td></tr></table>
    </section>

    <section class="summary">
        <div><div class="sl">Funds provided (Dr)</div><div class="sv" style="color:#dc2626">৳{{ $money($ledger['provided']) }}</div></div>
        <div><div class="sl">Project expenses (Cr)</div><div class="sv" style="color:#16a34a">৳{{ $money($ledger['spent']) }}</div></div>
        <div><div class="sl">{{ $balanceLabel }}</div><div class="sv" style="color:#3730a3">৳{{ $money($balance) }} {{ abs($balance)<.005?'':($balance>0?'Cr':'Dr') }}</div></div>
    </section>

    <table class="ledger">
        <colgroup><col style="width:12%"><col style="width:38%"><col style="width:12%"><col style="width:12%"><col style="width:14%"><col style="width:12%"></colgroup>
        <thead><tr><th>Date</th><th>Particulars</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th><th>Books</th></tr></thead>
        <tbody>
        @foreach($rows as $row)
            <tr class="{{ $row->status === 'reversed' ? 'reversed' : '' }}">
                <td>{{ \Carbon\Carbon::parse($row->date)->format('d M y') }}</td>
                <td><strong>{{ $row->particulars }}</strong>@if($row->sub)<div class="muted">{{ $row->sub }}</div>@endif @if($row->ref)<div class="ref">{{ $row->ref }}</div>@endif</td>
                <td class="num">{{ $row->dr>0?$money($row->dr):'—' }}</td><td class="num">{{ $row->cr>0?$money($row->cr):'—' }}</td>
                <td class="num"><strong>{{ $money($row->balance) }} {{ abs($row->balance)<.005?'':($row->balance>0?'Cr':'Dr') }}</strong></td>
                <td><span class="status">{{ ucfirst($row->status) }}</span></td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr class="total"><td colspan="2" style="text-align:right">Closing · {{ $balanceLabel }}</td><td class="num">{{ $money($ledger['provided']) }}</td><td class="num">{{ $money($ledger['spent']) }}</td><td class="num">{{ $money($balance) }} {{ abs($balance)<.005?'':($balance>0?'Cr':'Dr') }}</td><td></td></tr></tfoot>
    </table>
    <div class="note"><strong>Reading the statement:</strong> Debit represents company funds provided to the employee. Credit represents project costs paid through the employee. A credit closing balance means the company owes the employee; a debit closing balance means the employee still holds company funds.</div>
    <div class="signatures"><div>Prepared by</div><div>Employee / Receiver</div><div>Authorized by</div></div>
    <div class="footer">Computer-generated project statement · {{ $company?->name }} · {{ $dept->name }}</div>
</main>
</body>
</html>
