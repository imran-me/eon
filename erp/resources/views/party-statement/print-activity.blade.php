<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Party Activity Report</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
@page{size:A4 landscape;margin:10mm}:root{--tc:#3730a3;--tbg:#f5f7ff;--thd:#4f46e5;--thd2:#564fc8;--line:#e2e8f0;--text:#1e293b;--tfg:#837de4;--tf:"Montserrat",sans-serif;--ff:"Inter",sans-serif;--nf:"Space Mono",monospace}*{box-sizing:border-box;margin:0;padding:0;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}body{padding:28px;background:#f0f2f5;color:var(--text);font-family:var(--ff);font-size:11px}.tools{display:flex;justify-content:flex-end;gap:8px;max-width:1100px;margin:0 auto 12px}.tools button,.tools a{padding:7px 18px;border:0;border-radius:4px;color:#fff;font-family:var(--tf);font-size:11px;text-decoration:none;cursor:pointer}.tools a{background:#6b7280}.tools button{background:var(--thd)}.sheet{max-width:1100px;margin:auto;padding:36px 40px;background:#fff;border-radius:4px;box-shadow:0 4px 20px rgba(0,0,0,.06)}.head{display:flex;justify-content:space-between;align-items:center}.logo{width:150px;max-height:90px;object-fit:contain;object-position:left center}.brand{color:var(--tc);font-family:var(--tf);font-size:26px;font-weight:800}.company{text-align:right;font-size:11px;line-height:1.7}.company b{display:block;font-family:var(--tf);font-size:16px}.title{margin:0 0 20px;padding:10px;border-top:2px solid var(--tc);background:var(--tbg);color:var(--tc);font-family:var(--tf);font-size:11px;font-weight:700;letter-spacing:2px;text-align:center;text-transform:uppercase}.meta{display:flex;justify-content:space-between;align-items:flex-start;gap:24px;margin-bottom:18px}.meta h4{margin-bottom:5px;color:var(--tc);font-family:var(--tf);font-size:10px;letter-spacing:.05em;text-transform:uppercase}.meta p{margin:1px 0;font-size:12px;line-height:1.6}.meta-table td{padding:2px 0 2px 18px;font-size:11px;font-weight:600}.meta-table td:last-child{font-family:var(--nf);font-size:10px;text-align:right}.cards{display:flex;margin-bottom:18px;border:1px solid var(--line);border-radius:6px;overflow:hidden}.card{flex:1;padding:10px 12px;border-right:1px solid var(--line)}.card:last-child{border:0;background:linear-gradient(135deg,#d97706,#b45309);color:#fff}.card span{display:block;margin-bottom:3px;color:#94a3b8;font-size:8px;font-weight:600;letter-spacing:.06em;text-transform:uppercase}.card:last-child span{color:rgba(255,255,255,.75)}.card b{font-family:var(--nf);font-size:11px}.red{color:#dc2626}.green{color:#16a34a}.blue{color:#1d4ed8}.card:last-child .blue{color:#fff;font-size:13px}.ledger{width:100%;margin-bottom:20px;border-collapse:collapse;table-layout:fixed;font-size:10px}.ledger th{padding:8px 9px;background:var(--thd2);color:#fff;font-family:var(--tf);font-size:8px;letter-spacing:.04em;text-align:left;text-transform:uppercase}.ledger td{padding:6px 9px;border-bottom:1px solid var(--line);vertical-align:middle}.ledger tbody tr:nth-child(even) td{background:#f8f9ff}.ledger td small{display:block;margin-top:2px;color:#94a3b8;font-family:var(--nf);font-size:7.5px}.num{text-align:right!important;white-space:nowrap}.ledger tfoot td{padding:10px 9px;border:0;background:var(--tfg);color:#fff;font-family:var(--tf);font-weight:700}.ledger tfoot .red,.ledger tfoot .green,.ledger tfoot .blue{color:#fff}.signatures{display:grid;grid-template-columns:repeat(3,1fr);gap:55px;margin-top:34px;text-align:center}.signatures div{padding-top:5px;border-top:1px solid #333;font-family:var(--tf);font-size:9px;font-weight:700}.foot{margin-top:12px;color:#64748b;font-size:8px;line-height:1.6}@media print{body{padding:0;background:#fff}.tools{display:none}.sheet{max-width:none;padding:0;box-shadow:none}.title{padding:7px;margin-bottom:14px}.ledger td{padding:5px 8px}.ledger th{padding:6px 8px}.ledger tr{page-break-inside:avoid}.signatures{margin-top:24px}}
</style>
</head>
<body>
@php
    $money = fn ($value) => '৳'.number_format(abs((float) $value), 2);
    $scopeCompany = optional($selectedCompany)->name ?? 'All Companies';
    $scopeParty = $partyType === 'all' ? 'All Party Types' : ($partyType === 'client' ? 'Customers' : ucfirst($partyType).'s');
    $printCompany = $selectedCompany ?? \App\Models\Company::find(2) ?? \App\Models\Company::first();
@endphp
<div class="tools"><a href="javascript:void(0)" onclick="if(history.length>1){history.back();}else{window.close();setTimeout(function(){location.href=document.referrer||'/';},300);}return false;">← Back</a><button type="button" onclick="window.print()">Print / Save PDF</button></div>
<main class="sheet">
    <header class="head"><div>@if(optional($printCompany)->logo)<img class="logo" src="{{ asset($printCompany->logo) }}" alt="{{ $printCompany->name }}">@else<div class="brand">{{ optional($printCompany)->name ?? config('app.name', 'Epal ERP') }}</div>@endif</div><div class="company"><b>{{ optional($printCompany)->name ?? config('app.name') }}</b>@if(optional($printCompany)->address){{ $printCompany->address }}<br>@endif{{ optional($printCompany)->phone }}{{ optional($printCompany)->phone && optional($printCompany)->email ? ' | ' : '' }}{{ optional($printCompany)->email }}</div></header>
    <div class="title">Party Activity Report</div>
    <section class="meta"><div><h4>Report Scope</h4><p><strong>{{ $scopeCompany }}</strong></p><p>{{ $scopeParty }}</p></div><table class="meta-table"><tr><td>Period</td><td>{{ $periodLabel }}</td></tr><tr><td>Generated</td><td>{{ now()->format('d M Y') }}</td></tr><tr><td>Printed By</td><td>{{ auth()->user()->name }}</td></tr></table></section>
    <section class="cards">
        <div class="card"><span>Active parties</span><b>{{ number_format($summary['parties']) }}</b></div>
        <div class="card"><span>Transactions</span><b>{{ number_format($summary['transactions']) }}</b></div>
        <div class="card"><span>Charged / Cost</span><b class="red">{{ $money($summary['debit']) }}</b></div>
        <div class="card"><span>Paid / Received</span><b class="green">{{ $money($summary['credit']) }}</b></div>
        <div class="card"><span>Period net</span><b>{{ $money($summary['net']) }} {{ $summary['net'] > 0 ? 'Dr' : ($summary['net'] < 0 ? 'Cr' : '') }}</b></div>
        <div class="card"><span>Current outstanding</span><b class="blue">{{ $money($summary['current']) }} {{ $summary['current'] > 0 ? 'Dr' : ($summary['current'] < 0 ? 'Cr' : '') }}</b></div>
    </section>
    <table class="ledger">
        <thead><tr><th>Party</th><th>Type</th><th class="num">Entries</th><th class="num">Charged / Cost</th><th class="num">Paid / Received</th><th class="num">Period Net</th><th class="num">Current Balance</th><th>Last Activity</th></tr></thead>
        <tbody>@forelse($rows as $row)<tr><td><b>{{ $row['name'] }}</b><small>{{ $row['code'] }}{{ $row['phone'] ? ' · '.$row['phone'] : '' }}</small></td><td>{{ $row['type'] === 'client' ? 'Customer' : ucfirst($row['type']) }}</td><td class="num">{{ $row['count'] }}</td><td class="num red">{{ $money($row['debit']) }}</td><td class="num green">{{ $money($row['credit']) }}</td><td class="num">{{ $money($row['net']) }} {{ $row['net'] > 0 ? 'Dr' : ($row['net'] < 0 ? 'Cr' : '') }}</td><td class="num blue">{{ $money($row['current']) }} {{ $row['current'] > 0 ? 'Dr' : ($row['current'] < 0 ? 'Cr' : '') }}</td><td>{{ $row['last_at'] ? \Carbon\Carbon::parse($row['last_at'])->format('d M Y') : '—' }}</td></tr>@empty<tr><td colspan="8" style="padding:25px;text-align:center">No transactions found.</td></tr>@endforelse</tbody>
        <tfoot><tr><td colspan="2">Report total</td><td class="num">{{ number_format($summary['transactions']) }}</td><td class="num red">{{ $money($summary['debit']) }}</td><td class="num green">{{ $money($summary['credit']) }}</td><td class="num">{{ $money($summary['net']) }}</td><td class="num blue">{{ $money($summary['current']) }}</td><td></td></tr></tfoot>
    </table>
    <div class="signatures"><div>Prepared By</div><div>Checked By</div><div>Approved By</div></div>
    <div class="foot">Period movement covers only the selected dates. Current balance is the complete scoped party-ledger balance and may include earlier transactions.</div>
</main>
</body>
</html>
