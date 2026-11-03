<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Capital & Drawings Statement</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
    @page { size: A4 portrait; margin: 12mm; }
    :root { --tc:#2563eb; --tbg:#eff6ff; --thd:#1d4ed8; --bdr:#e2e8f0; --txt:#1e293b; --tf:"Montserrat",sans-serif; --ff:"Inter",sans-serif; --nf:"Space Mono",monospace; }
    *,*::before,*::after{ box-sizing:border-box; margin:0; padding:0; -webkit-print-color-adjust:exact!important; print-color-adjust:exact!important; }
    body{ font-family:var(--ff); padding:28px; background:#f0f2f5; color:var(--txt); font-size:12px; }
    .card{ max-width:794px; margin:auto; background:#fff; padding:40px; border-radius:4px; box-shadow:0 4px 20px rgba(0,0,0,.06); }
    .hdr{ display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; }
    .co-info{ text-align:right; font-size:12px; line-height:1.7; }
    .co-info strong{ font-family:var(--tf); font-size:16px; display:block; }
    .logo-txt{ font-family:var(--tf); color:var(--tc); font-size:26px; font-weight:800; }
    .label-bar{ text-align:center; background:var(--tbg); color:var(--tc); text-transform:uppercase; letter-spacing:2px; font-weight:700; font-family:var(--tf); font-size:11px; padding:10px; border-top:2px solid var(--tc); margin-bottom:24px; }
    .meta-row{ display:flex; justify-content:space-between; align-items:flex-start; gap:24px; margin-bottom:20px; }
    .bill-to h4{ font-family:var(--tf); color:var(--tc); font-size:11px; text-transform:uppercase; letter-spacing:.05em; margin-bottom:5px; }
    .bill-to p{ font-size:13px; line-height:1.7; margin:1px 0; }
    .meta-tbl td{ padding:2px 0 2px 18px; font-weight:600; font-size:12px; }
    .meta-tbl .nf{ font-family:var(--nf); font-size:11px; }
    .summary-strip{ display:flex; border:1px solid var(--bdr); border-radius:6px; overflow:hidden; margin-bottom:20px; }
    .sum-card{ flex:1; padding:10px 14px; border-right:1px solid var(--bdr); }
    .sum-card:last-child{ border-right:none; background:var(--thd); }
    .sum-label{ font-size:9px; text-transform:uppercase; letter-spacing:.06em; color:#64748b; font-weight:700; margin-bottom:3px; }
    .sum-card:last-child .sum-label{ color:#bfdbfe; }
    .sum-value{ font-family:var(--nf); font-size:12px; font-weight:700; color:var(--txt); }
    .sum-card:last-child .sum-value{ color:#fff; font-size:13px; }
    .ledger{ width:100%; border-collapse:collapse; margin-bottom:22px; font-size:10.5px; }
    .ledger th{ background:var(--thd); color:#fff; padding:7px 8px; text-align:left; font-family:var(--tf); font-size:8.5px; text-transform:uppercase; letter-spacing:.04em; }
    .ledger th.r,.ledger td.r{ text-align:right; }
    .ledger td{ padding:7px 8px; border-bottom:1px solid var(--bdr); vertical-align:top; }
    .ledger td.mono{ font-family:var(--nf); font-size:10px; }
    .ledger tbody tr:nth-child(even) td{ background:#f8f9ff; }
    .badge{ display:inline-block; padding:1px 7px; border-radius:9px; font-size:8.5px; font-weight:700; }
    .in{ background:#dcfce7; color:#166534; }
    .out{ background:#fee2e2; color:#991b1b; }
    .pos{ color:#166534; font-weight:700; }
    .neg{ color:#dc2626; font-weight:700; }
    .sh{ background:var(--thd); color:#fff; padding:5px 12px; font-family:var(--tf); font-size:10px; font-weight:700; margin-bottom:8px; letter-spacing:.03em; }
    .sig{ margin-top:34px; display:flex; justify-content:flex-end; }
    .sig-c{ text-align:center; width:200px; }
    .sig-l{ border-top:1px solid #333; margin-top:6px; padding-top:4px; font-size:10px; font-weight:700; font-family:var(--tf); }
    @media print{ body{ background:#fff!important; padding:0!important; } .btn-row{ display:none!important; } .card{ box-shadow:none!important; padding:0!important; max-width:100%!important; } .label-bar{ padding:7px; margin-bottom:14px; } .ledger td{ padding:5px 7px; } .ledger th{ padding:6px 7px; } }
</style>
</head>
<body>
<div class="btn-row" style="max-width:794px;margin:0 auto 12px auto;display:flex;gap:8px;justify-content:flex-end;">
    <a href="javascript:history.back()" style="background:#6b7280;color:#fff;text-decoration:none;display:inline-block;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;border-radius:4px;">Back</a>
    <button onclick="window.print()" style="background:#2563eb;color:#fff;border:none;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">Print</button>
    <button onclick="window.print()" style="background:#fff;color:#2563eb;border:1px solid #2563eb;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">Download PDF</button>
</div>

<div class="card">
    <div class="hdr">
        <div>
            @if(optional($company)->logo)
                @php $logoSrc = file_exists(public_path($company->logo)) ? asset($company->logo) : asset('logo.png'); @endphp
                <img src="{{ $logoSrc }}" alt="" style="max-width:80px;">
            @else
                <div class="logo-txt">{{ optional($company)->name ?? config('app.name') }}</div>
            @endif
        </div>
        <div class="co-info">
            <strong>{{ optional($company)->name ?? config('app.name') }}</strong>
            @if(optional($company)->address){{ $company->address }}<br>@endif
            {{ optional($company)->phone }}{{ optional($company)->phone && optional($company)->email ? ' | ' : '' }}{{ optional($company)->email }}
        </div>
    </div>

    <div class="label-bar">Capital & Drawings Statement</div>

    <div class="meta-row">
        <div class="bill-to">
            <h4>Report Filters</h4>
            <p><strong>{{ $person ?: 'All Persons' }}</strong></p>
            <p>{{ $kind ? ucfirst($kind) : 'Investment and Drawings' }}</p>
            @if($search)<p>Search: {{ $search }}</p>@endif
        </div>
        <div>
            <table class="meta-tbl">
                <tr><td>Period</td><td class="nf">{{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d M Y') : 'All Time' }}{{ $dateTo ? ' to ' . \Carbon\Carbon::parse($dateTo)->format('d M Y') : '' }}</td></tr>
                <tr><td>Generated</td><td class="nf">{{ now()->format('d M Y') }}</td></tr>
                <tr><td>Printed By</td><td>{{ Auth::user()->name }}</td></tr>
            </table>
        </div>
    </div>

    <div class="summary-strip">
        <div class="sum-card"><div class="sum-label">Put In</div><div class="sum-value pos">&#2547;{{ number_format($capitalTotals['investment'], 2) }}</div></div>
        <div class="sum-card"><div class="sum-label">Taken Out</div><div class="sum-value neg">&#2547;{{ number_format($capitalTotals['drawings'], 2) }}</div></div>
        <div class="sum-card"><div class="sum-label">Entries</div><div class="sum-value">{{ number_format($capitalTotals['count']) }}</div></div>
        <div class="sum-card"><div class="sum-label">Net Balance</div><div class="sum-value">&#2547;{{ number_format($capitalTotals['net'], 2) }}</div></div>
    </div>

    @if($capitalPeople->count())
        <div class="sh">Personal Sheets</div>
        <table class="ledger">
            <thead><tr><th>Person</th><th class="r">Put In</th><th class="r">Taken Out</th><th class="r">Net Balance</th><th class="r">Entries</th></tr></thead>
            <tbody>
            @foreach($capitalPeople as $row)
                @php $pIn = (float) $row->investment_total; $pOut = (float) $row->drawings_total; $pNet = $pIn - $pOut; @endphp
                <tr>
                    <td>{{ $row->person_name }}</td>
                    <td class="r pos">&#2547;{{ number_format($pIn, 2) }}</td>
                    <td class="r neg">&#2547;{{ number_format($pOut, 2) }}</td>
                    <td class="r {{ $pNet < 0 ? 'neg' : 'pos' }}">&#2547;{{ number_format($pNet, 2) }}</td>
                    <td class="r">{{ number_format((int) $row->movement_count) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <div class="sh">Movement Register</div>
    <table class="ledger">
        <thead><tr><th>Date</th><th>Person</th><th>Direction</th><th>Method</th><th>Reason</th><th>Journal</th><th class="r">Amount</th></tr></thead>
        <tbody>
        @forelse($capital as $row)
            @php $posting = $capitalPosting->get($row->id); @endphp
            <tr>
                <td class="mono">{{ optional($row->date)->format('d M Y') }}</td>
                <td>{{ $row->person_name }}</td>
                <td><span class="badge {{ $row->kind === 'investment' ? 'in' : 'out' }}">{{ $row->kind === 'investment' ? 'Money In' : 'Money Out' }}</span></td>
                <td>{{ ucfirst($row->method) }}@if($row->bank)<br><small>{{ $row->bank->name }}</small>@endif</td>
                <td>{{ $row->reason ?: '-' }}</td>
                <td class="mono">{{ $posting?->reference ?: ($row->method === 'adjustment' ? 'Non-cash' : '-') }}</td>
                <td class="r {{ $row->kind === 'investment' ? 'pos' : 'neg' }}">{{ $row->kind === 'drawings' ? '-' : '' }}&#2547;{{ number_format((float) $row->amount, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" style="text-align:center;color:#64748b;">No capital movements found.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="sig"><div class="sig-c"><div class="sig-l">Authorized Signature</div></div></div>
</div>
</body>
</html>
