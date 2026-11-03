<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Company Ledger — {{ optional($company)->name ?? config('app.name') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
    {{-- The bank feed's sheet (banks/print-recent), read from the party's
         side: the bank column becomes the party, the beneficiary column
         becomes "paid via", Company Balance becomes the party's balance
         after the row. Landscape for the same reason — seven columns. --}}
    @page { size: A4 landscape; margin: 10mm; }

    :root {
        --tc:  #4338ca;
        --tbg: #eef2ff;
        --thd: #4f46e5;
        --bdr: #e2e8f0;
        --txt: #1e293b;
        --tf:  "Montserrat", sans-serif;
        --ff:  "Inter", sans-serif;
        --nf:  "Space Mono", monospace;
    }

    *, *::before, *::after {
        box-sizing: border-box; margin: 0; padding: 0;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    body { font-family: var(--ff); padding: 28px; background: #f0f2f5; color: var(--txt); font-size: 12px; }

    .card { max-width: 1100px; margin: auto; background: #fff; padding: 36px 40px; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,.06); }

    .hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .co-info { text-align: right; font-size: 12px; line-height: 1.7; }
    .co-info strong { font-family: var(--tf); font-size: 16px; display: block; }
    .logo-txt { font-family: var(--tf); color: var(--tc); font-size: 26px; font-weight: 800; }

    .label-bar {
        text-align: center; background: var(--tbg); color: var(--tc);
        text-transform: uppercase; letter-spacing: 2px; font-weight: 700;
        font-family: var(--tf); font-size: 11px; padding: 10px;
        border-top: 2px solid var(--tc); margin-bottom: 20px;
    }

    .meta-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-bottom: 18px; }
    .scope h4 { font-family: var(--tf); color: var(--tc); font-size: 11px; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 5px; }
    .scope p { font-size: 13px; line-height: 1.7; margin: 1px 0; }
    .scope .accts { font-size: 11px; color: #64748b; line-height: 1.6; max-width: 520px; }
    .meta-tbl td { padding: 2px 0 2px 18px; font-weight: 600; font-size: 12px; white-space: nowrap; }
    .meta-tbl .nf { font-family: var(--nf); font-size: 11px; }

    .summary-strip { display: flex; border: 1px solid var(--bdr); border-radius: 6px; overflow: hidden; margin-bottom: 18px; }
    .sum-card { flex: 1; padding: 10px 14px; border-right: 1px solid var(--bdr); }
    .sum-card:last-child { border-right: none; background: var(--thd); }
    .sum-label { font-size: 9px; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; font-weight: 600; margin-bottom: 3px; }
    .sum-card:last-child .sum-label { color: #c7d2fe; }
    .sum-value { font-family: var(--nf); font-size: 12px; font-weight: 700; color: var(--txt); }
    .sum-card:last-child .sum-value { color: #fff; font-size: 13px; }

    .ledger { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 11px; }
    .ledger th {
        background: var(--thd); color: #fff; padding: 8px 10px; text-align: left;
        font-family: var(--tf); font-size: 9px; text-transform: uppercase; letter-spacing: .04em;
    }
    .ledger th.r { text-align: right; }
    .ledger td { padding: 7px 10px; border-bottom: 1px solid var(--bdr); vertical-align: top; }
    .ledger td.r    { text-align: right; white-space: nowrap; }
    .ledger td.mono { font-family: var(--nf); font-size: 10px; }
    .ledger tbody tr:nth-child(even) td { background: #f8f9ff; }
    .ledger .sub { display: block; font-size: 9.5px; color: #94a3b8; margin-top: 2px; }
    .ledger tfoot tr { background: var(--thd); color: #fff; }
    .ledger tfoot td { padding: 8px 10px; font-weight: 700; border-bottom: none; font-family: var(--tf); font-size: 10px; }
    .ledger tfoot td.r { text-align: right; font-family: var(--nf); }

    .flow { display: inline-block; padding: 1px 6px; border-radius: 9px; font-size: 8.5px; font-weight: 700; margin-left: 4px; vertical-align: middle; }
    .flow-up   { background: #fee2e2; color: #b91c1c; }
    .flow-down { background: #dcfce7; color: #15803d; }
    .flow-cash   { background: #dcfce7; color: #15803d; }
    .flow-bank   { background: #dbeafe; color: #1e40af; }
    .flow-cheque { background: #fef3c7; color: #92400e; }
    .flow-card   { background: #ede9fe; color: #5b21b6; }
    .flow-mobile { background: #ffe4e6; color: #be123c; }
    .code { font-family: var(--nf); font-size: 9.5px; color: var(--tc); font-weight: 700; }

    {{-- Debit raises what a party owes, credit settles it — the party
         statement's colours, not the bank feed's. --}}
    .debit      { color: #dc2626; font-weight: 600; }
    .credit     { color: #16a34a; font-weight: 600; }
    .balance-dr { color: #1d4ed8; font-weight: 700; }
    .balance-cr { color: #dc2626; font-weight: 700; }

    .note { font-size: 11px; color: #64748b; line-height: 1.7; }

    .sig { margin-top: 30px; display: flex; justify-content: flex-end; }
    .sig-c { text-align: center; width: 200px; }
    .sig-l { border-top: 1px solid #333; margin-top: 6px; padding-top: 4px; font-size: 10px; font-weight: 700; font-family: var(--tf); }

    @media print {
        body       { background: #fff !important; padding: 0 !important; }
        .btn-row   { display: none !important; }
        .card      { box-shadow: none !important; padding: 0 !important; max-width: 100% !important; }
        .label-bar { padding: 7px; margin-bottom: 12px; }
        .ledger    { font-size: 10px; }
        .ledger td { padding: 5px 8px; }
        .ledger th { padding: 6px 8px; }
        .ledger tr { page-break-inside: avoid; }
        .sig       { margin-top: 20px; }
    }
</style>
</head>
<body>
@php
    $periodText = $selection
        ? $selection . ' selected entr' . ($selection === 1 ? 'y' : 'ies')
        : ($dateFrom && $dateTo && $dateFrom === $dateTo
            ? \Carbon\Carbon::parse($dateFrom)->format('d M Y')
            : ($dateFrom || $dateTo
                ? ($dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d M Y') : 'Start') . ' → ' . ($dateTo ? \Carbon\Carbon::parse($dateTo)->format('d M Y') : now()->format('d M Y'))
                : 'All Time'));
    $m = fn ($v) => ($v < 0 ? '−' : '') . number_format(abs($v), 2);
@endphp
<div class="btn-row" style="max-width:1100px;margin:0 auto 12px auto;display:flex;gap:8px;justify-content:flex-end;">
    <a href="javascript:void(0)" onclick="if(history.length>1){history.back();}else{window.close();setTimeout(function(){location.href=document.referrer||'/';},300);}return false;" style="background:#6b7280;color:#fff;text-decoration:none;display:inline-block;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;border-radius:4px;">← Back</a>
    <button onclick="window.print()" style="background:#4f46e5;color:#fff;border:none;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">🖨️ Print</button>
    <button onclick="window.print()" style="background:#fff;color:#4f46e5;border:1px solid #4f46e5;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">📄 Download PDF</button>
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
    <div class="label-bar">Company Ledger — {{ $selection ? 'Selected Entries' : 'Period Report' }}</div>
    <div class="meta-row">
        <div class="scope">
            <h4>Scope</h4>
            <p><strong>Costs the company bore, and what it recovered</strong></p>
            <div class="accts">Contract flight penalties, fare differences and change fees</div>
        </div>
        <div>
            <table class="meta-tbl">
                <tr><td>Showing</td><td class="nf">{{ $periodText }}</td></tr>
                <tr><td>Entries</td><td class="nf">{{ $rows->count() }}</td></tr>
                <tr><td>Generated</td><td class="nf">{{ now()->format('d M Y H:i') }}</td></tr>
                <tr><td>Printed By</td><td>{{ Auth::user()->name }}</td></tr>
            </table>
        </div>
    </div>
    <div class="summary-strip">
        <div class="sum-card"><div class="sum-label">Borne by company</div><div class="sum-value" style="color:#dc2626;">৳{{ $m($totals['borne']) }}</div></div>
        <div class="sum-card"><div class="sum-label">Recovered</div><div class="sum-value" style="color:#16a34a;">৳{{ $m($totals['recovered']) }}</div></div>
        <div class="sum-card"><div class="sum-label">Entries on this sheet</div><div class="sum-value">{{ $rows->count() }}</div></div>
        <div class="sum-card"><div class="sum-label">Net cost</div><div class="sum-value">৳{{ $m($totals['net']) }}</div></div>
    </div>
    <table class="ledger">
        <thead>
            <tr>
                <th width="9%">Date</th>
                <th width="22%">Entry</th>
                <th width="27%">Details</th>
                <th width="12%">Agent / party</th>
                <th class="r" width="10%">Borne (৳)</th>
                <th class="r" width="10%">Recovered (৳)</th>
                <th class="r" width="10%">Net cost (৳)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                <tr>
                    <td class="mono">{{ \Carbon\Carbon::parse($r['date'])->format('d/m/Y') }}</td>
                    <td>{{ $r['side'] === 'borne' ? 'Borne' : 'Recovered' }}<span class="flow {{ $r['side'] === 'borne' ? 'flow-up' : 'flow-down' }}">{{ $r['side'] === 'borne' ? 'Cost' : 'Income' }}</span>@if($r['reversal'])<span class="flow" style="background:#f1f5f9;color:#475569;">Reversal</span>@endif
                        <span class="sub">{{ $r['head'] }}</span>@if($r['reference'])<span class="sub code">{{ $r['reference'] }}</span>@endif</td>
                    <td>{{ $r['details'] ?: '—' }}</td>
                    <td>{{ $r['party'] ?: '—' }}</td>
                    <td class="r">@if($r['borne'])<span class="debit">{{ $m($r['borne']) }}</span>@else — @endif</td>
                    <td class="r">@if($r['recovered'])<span class="credit">{{ $m($r['recovered']) }}</span>@else — @endif</td>
                    <td class="r mono">{{ $m($r['balance']) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:18px;">No entries.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4">Totals</td>
                <td class="r">{{ $m($totals['borne']) }}</td>
                <td class="r">{{ $m($totals['recovered']) }}</td>
                <td class="r">{{ $m($totals['net']) }}</td>
            </tr>
        </tfoot>
    </table>
    <div class="sig"><div class="sig-c"><div class="sig-l">Authorised Signature</div></div></div>
</div>
</body>
</html>
