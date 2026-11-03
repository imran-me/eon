<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $meta['plural'] }} — Balances — {{ optional($company)->name ?? config('app.name') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
    {{-- The roster sheet: one line per party with the figures the ledger
         page's table shows. Same family as print-feed / banks/print-recent. --}}
    @page { size: A4 portrait; margin: 12mm; }

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

    .card { max-width: 794px; margin: auto; background: #fff; padding: 40px; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,.06); }

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

    .ledger { width: 100%; table-layout: fixed; border-collapse: collapse; margin-bottom: 20px; font-size: 11px; }
    .ledger th {
        background: var(--thd); color: #fff; padding: 8px 6px; text-align: left; white-space: nowrap; overflow: hidden;
        font-family: var(--tf); font-size: 9px; text-transform: uppercase; letter-spacing: .04em;
    }
    .ledger th.r { text-align: right; }
    .ledger td { padding: 7px 8px; border-bottom: 1px solid var(--bdr); vertical-align: top; overflow-wrap: anywhere; }
    .ledger td.r    { text-align: right; white-space: nowrap; }
    .ledger td.mono { font-family: var(--nf); font-size: 10px; overflow-wrap: normal; }
    .ledger tbody tr:nth-child(even) td { background: #f8f9ff; }
    .ledger .sub { display: block; font-size: 9.5px; color: #94a3b8; margin-top: 2px; }
    .ledger tfoot tr { background: var(--thd); color: #fff; }
    .ledger tfoot td { padding: 8px 10px; font-weight: 700; border-bottom: none; font-family: var(--tf); font-size: 10px; }
    .ledger tfoot td.r { text-align: right; font-family: var(--nf); font-size: 9px; white-space: nowrap; }

    .code { font-family: var(--nf); font-size: 9.5px; color: var(--tc); font-weight: 700; }
    .flow { display: inline-block; padding: 1px 6px; border-radius: 9px; font-size: 8.5px; font-weight: 700; margin-left: 4px; vertical-align: middle; }
    .flow-stale { background: #fef3c7; color: #92400e; }

    .owed       { color: #dc2626; font-weight: 600; }
    .owed-pay   { color: #b45309; font-weight: 600; }
    .advance    { color: #16a34a; font-weight: 600; }
    .balance-dr { color: #1d4ed8; font-weight: 700; }
    .balance-cr { color: #dc2626; font-weight: 700; }
    .none       { color: #cbd5e1; }

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
    $receivable = $meta['side'] === 'receivable';
    $owedLabel  = $receivable ? 'Receivable' : 'Payable';
    $lower      = strtolower($meta['plural']);
    $showText   = match ($show) {
        'selected' => $selection . ' selected ' . ($selection === 1 ? strtolower($meta['label']) : $lower),
        'owing'    => $lower . ' with ' . strtolower($owedLabel) . ' > 0',
        'stale'    => $lower . ' needing follow-up (no movement in ' . $staleDays . ' days)',
        'advance'  => $lower . ' in advance',
        default    => 'all ' . $lower,
    };
    $totalNet = (float) $parties->sum('net');
@endphp

<div class="btn-row" style="max-width:794px;margin:0 auto 12px auto;display:flex;gap:8px;justify-content:flex-end;">
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

    <div class="label-bar">{{ $meta['plural'] }} — {{ $selection ? 'Selected ' . $meta['plural'] : 'Balances' }}</div>

    <div class="meta-row">
        <div class="scope">
            <h4>Scope</h4>
            <p><strong>{{ ucfirst($showText) }}</strong></p>
            <div class="accts">{{ $summary['owing'] }} of {{ $summary['count'] }} owe · {{ $summary['active'] }} with a ledger · {{ $summary['stale'] }} need follow-up</div>
        </div>
        <div>
            <table class="meta-tbl">
                <tr><td>{{ $meta['plural'] }}</td><td class="nf">{{ $parties->count() }}</td></tr>
                <tr><td>Balances as of</td><td class="nf">{{ now()->format('d M Y') }}</td></tr>
                <tr><td>Generated</td><td class="nf">{{ now()->format('d M Y H:i') }}</td></tr>
                <tr><td>Printed By</td><td>{{ Auth::user()->name }}</td></tr>
            </table>
        </div>
    </div>

    <div class="summary-strip">
        <div class="sum-card">
            <div class="sum-label">Total {{ $owedLabel }}</div>
            <div class="sum-value" style="color:{{ $receivable ? '#dc2626' : '#b45309' }};">৳{{ number_format($summary['owed'], 2) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Total Advance</div>
            <div class="sum-value" style="color:#16a34a;">৳{{ number_format($summary['advance'], 2) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Needs follow-up</div>
            <div class="sum-value">{{ $summary['stale'] }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Net position</div>
            <div class="sum-value">৳{{ number_format(abs($totalNet), 2) }} {{ $totalNet < 0 ? 'Cr' : 'Dr' }}</div>
        </div>
    </div>

    <table class="ledger">
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th style="width:22%">{{ $meta['label'] }}</th>
                <th style="width:14%">Type</th>
                <th style="width:18%">Contact</th>
                <th class="r" style="width:8%">Entries</th>
                <th style="width:14%">Last movement</th>
                <th class="r" style="width:19%">Balance (৳)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($parties as $p)
            <tr>
                <td class="mono">{{ $loop->iteration }}</td>
                <td>
                    <strong>{{ $p['name'] }}</strong>
                    <span class="sub">{{ $meta['label'] }} <span class="code">{{ $p['code'] }}</span></span>
                </td>
                <td>
                    @php $svc = $p['services'] ?? []; @endphp
                    @if(count($svc) > 1)<strong>Multiple</strong><span class="sub">{{ implode(', ', $svc) }}</span>
                    @elseif($svc)<strong>{{ $svc[0] }}</strong>
                    @else<span class="none">—</span>@endif
                </td>
                <td>
                    @if($p['contact_person'])<strong>{{ $p['contact_person'] }}</strong>@endif
                    <span class="{{ $p['contact_person'] ? 'sub' : '' }}">{{ $p['phone'] ?: ($p['email'] ?: '—') }}</span>
                </td>
                <td class="r mono">{{ $p['entries'] ?: '—' }}</td>
                <td style="white-space:nowrap;">
                    {{ $p['last_at'] ? \Carbon\Carbon::parse($p['last_at'])->format('d M y') : '—' }}
                    @if($p['stale'])<span class="flow flow-stale">stale</span>@endif
                </td>
                <td class="r">@if($p['entries'])<span class="{{ $p['net'] < 0 ? 'balance-cr' : 'balance-dr' }}">{{ number_format(abs($p['net']), 2) }} {{ $p['net'] < 0 ? 'Cr' : 'Dr' }}</span>@else<span class="none">—</span>@endif</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align:center;padding:20px;color:#94a3b8;">No {{ $lower }} for this selection.</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6">Total — {{ $showText }}</td>
                <td class="r">৳{{ number_format(abs($totalNet), 2) }} {{ $totalNet < 0 ? 'Cr' : 'Dr' }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="note">
        This sheet was generated on {{ now()->format('d M Y \a\t H:i') }} and lists {{ $showText }} with their whole-ledger balances as of today.
        Balance is each party's whole-ledger position: Dr = {{ $receivable ? 'they owe us (receivable)' : 'outstanding' }}, Cr = paid in advance.
        A party is marked stale when money is outstanding and nothing has moved in {{ $staleDays }} days.
    </div>

    <div class="sig">
        <div class="sig-c">
            <x-signature :company="$company" />
            <div class="sig-l">Authorized Signatory</div>
            <div style="font-size:10px;margin-top:3px;">For, {{ optional($company)->name ?? config('app.name') }}</div>
        </div>
    </div>

</div>

<script>window.onload = function () { window.print(); };</script>
</body>
</html>
