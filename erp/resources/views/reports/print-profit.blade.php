@php
    $allModules = [
        'ticket' => ['label'=>'Ticket', 'icon'=>'🎫', 'grp'=>'#7c3aed', 'fbg'=>'#ede9fe', 'ftx'=>'#4c1d95'],
        'visa'   => ['label'=>'Visa',   'icon'=>'📋', 'grp'=>'#2563eb', 'fbg'=>'#dbeafe', 'ftx'=>'#1e3a8a'],
        'flight' => ['label'=>'Flight', 'icon'=>'✈',  'grp'=>'#0284c7', 'fbg'=>'#e0f2fe', 'ftx'=>'#0c4a6e'],
        'file'   => ['label'=>'File',   'icon'=>'📁', 'grp'=>'#d97706', 'fbg'=>'#fef3c7', 'ftx'=>'#78350f'],
    ];
    $modules      = array_filter($allModules, fn($k) => in_array($k, $selectedTypes), ARRAY_FILTER_USE_KEY);
    $modCount     = count($modules);
    $showTotal    = $modCount > 1;
    $landscape    = $modCount >= 3;
    $fmt  = fn($n) => '৳' . number_format((float)$n, 0,   '.', ',');
    $fmtD = fn($n) => '৳' . number_format((float)$n, 2,   '.', ',');
    $pct  = fn($n) => number_format((float)$n, 1) . '%';
    $a    = $annual;
    $moduleTitle  = implode(', ', array_map(fn($m) => $m['label'], $modules));
    $pageSize     = $landscape ? 'A4 landscape' : 'A4 portrait';
    $cardWidth    = $landscape ? '1100px' : '780px';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Monthly Profit Sheet — {{ $year }} — {{ optional($company)->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">

{{-- Static CSS ─────────────────────────────────────────────────────────────── --}}
<style>
    :root {
        --green:  #059669;
        --glt:    #f0fdf4;
        --border: #e2e8f0;
        --text:   #1e293b;
        --muted:  #94a3b8;
        --tf: "Montserrat", sans-serif;
        --ff: "Inter", sans-serif;
        --nf: "Space Mono", monospace;
    }

    *, *::before, *::after {
        box-sizing: border-box; margin: 0; padding: 0;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }

    body { font-family:var(--ff); padding:28px; background:#f0f2f5; color:var(--text); font-size:11px; }

    .card { margin:auto; background:#fff; padding:36px 40px; border-radius:4px; box-shadow:0 4px 20px rgba(0,0,0,.07); }

    /* Header */
    .hdr { display:flex; justify-content:space-between; align-items:center; margin-bottom:22px; }
    .co-info { text-align:right; font-size:11px; line-height:1.7; }
    .co-info strong { font-family:var(--tf); font-size:15px; display:block; }
    .logo-txt { font-family:var(--tf); color:var(--green); font-size:24px; font-weight:800; }

    /* Label bar */
    .label-bar {
        text-align:center; background:var(--glt); color:var(--green);
        text-transform:uppercase; letter-spacing:2px; font-weight:700;
        font-family:var(--tf); font-size:11px; padding:10px;
        border-top:2px solid var(--green); margin-bottom:20px;
    }

    /* Meta */
    .meta-row { display:flex; justify-content:space-between; align-items:flex-start; gap:20px; margin-bottom:18px; }
    .meta-left p { font-size:11px; line-height:1.7; margin:1px 0; }
    .meta-right td { padding:2px 0 2px 16px; font-weight:600; font-size:10px; }
    .meta-right .nf { font-family:var(--nf); font-size:9px; }

    /* Summary strip */
    .sum-strip { display:flex; border:1px solid var(--border); border-radius:6px; overflow:hidden; margin-bottom:18px; }
    .sum-card { flex:1; padding:9px 12px; border-right:1px solid var(--border); }
    .sum-card.last { border-right:none; background:var(--green); }
    .sum-lbl { font-size:7px; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); font-weight:700; margin-bottom:2px; }
    .sum-card.last .sum-lbl { color:#a7f3d0; }
    .sum-val { font-family:var(--nf); font-size:12px; font-weight:700; }
    .sum-card.last .sum-val { color:#fff; }

    /* Module cards */
    .mod-strip { display:flex; gap:10px; margin-bottom:18px; }
    .mod-card { flex:1; border:1px solid var(--border); border-radius:6px; padding:9px 11px; }
    .mod-title { font-family:var(--tf); font-size:9px; font-weight:700; margin-bottom:7px; }
    .mod-row { display:flex; justify-content:space-between; font-size:9px; margin-bottom:2px; }
    .mod-row .lbl { color:var(--muted); }
    .mod-row .val { font-family:var(--nf); font-size:8px; font-weight:600; }
    .mod-div { border-top:1px solid var(--border); margin:4px 0; }

    /* Table */
    .pt { width:100%; border-collapse:collapse; margin-bottom:22px; table-layout:fixed; }
    .pt .gh th { padding:6px 5px; color:#fff; font-family:var(--tf); font-size:8px; text-transform:uppercase; letter-spacing:.04em; }
    .pt .sh th { padding:4px 4px; background:#f8fafc; color:#64748b; font-size:7px; text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid var(--border); font-weight:600; }
    .pt th.ra, .pt td.ra { text-align:right; }
    .pt tbody tr { border-bottom:1px solid #f1f5f9; }
    .pt tbody tr:nth-child(even) td { background:#fafbff; }
    .pt tbody tr.empty td { opacity:.35; }
    .pt tbody td { padding:5px 4px; color:#374151; vertical-align:middle; }
    .pt tbody td.mn { font-family:var(--nf); font-size:8px; }
    .pt tbody td.mc { font-family:var(--tf); font-size:9px; font-weight:700; color:var(--text); white-space:nowrap; background:#fff !important; }
    .pg { color:#059669; font-weight:700; }
    .pr { color:#dc2626; font-weight:700; }
    .mu { color:#cbd5e1; }
    .cost { color:#dc2626; }
    .pt tfoot td { padding:7px 4px; font-weight:700; font-size:9px; border-top:2px solid var(--text); }
    .tf-month { background:var(--text); color:#fff; font-family:var(--tf); }
    .tf-total { background:#d1fae5; color:#064e3b; }

    /* Bottom */
    .bot { display:grid; grid-template-columns:1.4fr 0.6fr; gap:28px; }
    .sh2 { background:var(--green); color:#fff; padding:4px 10px; font-size:9px; font-weight:600; font-family:var(--tf); margin-bottom:7px; letter-spacing:.03em; }
    .nb  { font-size:10px; line-height:1.7; color:#475569; }
    .tt  { width:100%; border-collapse:collapse; }
    .tt td { padding:5px 0; border-bottom:1px solid #eee; font-size:10px; }
    .tt td.n { font-family:var(--nf); font-size:9px; text-align:right; }
    .tt tr.grand td { font-weight:700; color:var(--green); font-size:12px; border-bottom:2px solid var(--green); }

    /* Signature */
    .sig { margin-top:32px; display:flex; justify-content:flex-end; }
    .sig-c { text-align:center; width:180px; }
    .sig-l { border-top:1px solid #333; margin-top:6px; padding-top:4px; font-size:9px; font-weight:700; font-family:var(--tf); }

    /* Print */
    @media print {
        body       { background:#fff !important; padding:0 !important; }
        .btn-row   { display:none !important; }
        .card      { box-shadow:none !important; padding:0 !important; max-width:100% !important; }
        .label-bar { padding:6px; margin-bottom:12px; }
        .sum-strip { margin-bottom:12px; }
        .mod-strip { margin-bottom:12px; }
        .pt        { margin-bottom:14px; }
        .bot       { gap:18px; }
        .sig       { margin-top:20px; }
    }
</style>

{{-- Dynamic CSS (page size, card width, per-module colors) ─────────────────── --}}
<style>
<?php
    // Page + card — no Blade expressions inside CSS so the IDE linter is happy
    $ps = $landscape ? 'A4 landscape' : 'A4 portrait';
    $cw = $landscape ? '1100px' : '780px';
    echo "@page { size: {$ps}; margin: 12mm; }\n";
    echo ".card  { max-width: {$cw}; }\n";
    echo ".btn-row { max-width: {$cw}; }\n";
    // Per-module colors
    $mw = $landscape ? '7%' : '10%';
    echo ".col-month { width: {$mw}; }\n";
    foreach ($modules as $key => $mod) {
        echo ".gh-{$key} { background: {$mod['grp']}; }\n";
        echo ".ft-{$key} { background: {$mod['fbg']}; color: {$mod['ftx']}; }\n";
        echo ".mt-{$key} { color: {$mod['grp']}; }\n";
    }
?>
</style>
</head>
<body>

{{-- Action buttons ───────────────────────────────────────────────────────── --}}
<div class="btn-row" style="margin:0 auto 12px auto; display:flex; gap:8px; justify-content:flex-end;">
    <a href="javascript:history.back()" style="background:#6b7280;color:#fff;text-decoration:none;display:inline-block;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;border-radius:4px;">← Back</a>
    <button onclick="window.print()" style="background:#059669;color:#fff;border:none;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">🖨️ Print</button>
    <button onclick="window.print()" style="background:#fff;color:#059669;border:1px solid #059669;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">📄 Download PDF</button>
</div>

<div class="card">

    {{-- Header ── --}}
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

    {{-- Label bar ── --}}
    <div class="label-bar">Monthly Profit Sheet &mdash; {{ $year }} &mdash; {{ $moduleTitle }}</div>

    {{-- Meta ── --}}
    <div class="meta-row">
        <div class="meta-left">
            <p><strong>Period:</strong> January {{ $year }} &mdash; December {{ $year }}</p>
            <p><strong>Modules:</strong> {{ $moduleTitle }}</p>
            <p><strong>Generated:</strong> {{ now()->format('d M Y \a\t H:i') }}</p>
        </div>
        <div>
            <table class="meta-right">
                <tr><td>Total Revenue</td><td class="nf">{{ $fmtD($a['total']['revenue']) }}</td></tr>
                <tr><td>Total Cost</td><td class="nf">{{ $fmtD($a['total']['cost']) }}</td></tr>
                <tr><td style="color:#059669;font-weight:700;">Net Profit</td>
                    <td class="nf" style="color:#059669;">{{ $fmtD($a['total']['profit']) }}</td></tr>
                <tr><td>Profit Margin</td><td class="nf">{{ $pct($a['total']['margin']) }}</td></tr>
            </table>
        </div>
    </div>

    {{-- Summary strip ── --}}
    <div class="sum-strip">
        <div class="sum-card"><div class="sum-lbl">Total Revenue</div><div class="sum-val">{{ $fmt($a['total']['revenue']) }}</div></div>
        <div class="sum-card"><div class="sum-lbl">Total Cost</div><div class="sum-val cost">{{ $fmt($a['total']['cost']) }}</div></div>
        <div class="sum-card"><div class="sum-lbl">Net Profit</div><div class="sum-val pg">{{ $fmt($a['total']['profit']) }}</div></div>
        <div class="sum-card last"><div class="sum-lbl">Profit Margin</div><div class="sum-val">{{ $pct($a['total']['margin']) }}</div></div>
    </div>

    {{-- Module summary cards ── --}}
    <div class="mod-strip">
        @foreach($modules as $key => $mod)
        <div class="mod-card">
            <div class="mod-title mt-{{ $key }}">{{ $mod['icon'] }} {{ $mod['label'] }}</div>
            <div class="mod-row"><span class="lbl">Revenue</span><span class="val">{{ $fmt($a[$key]['revenue']) }}</span></div>
            <div class="mod-row"><span class="lbl">Cost</span><span class="val cost">{{ $fmt($a[$key]['cost']) }}</span></div>
            <div class="mod-div"></div>
            <div class="mod-row">
                <span class="lbl" style="font-weight:600;">Profit</span>
                <span class="val {{ $a[$key]['profit'] >= 0 ? 'pg' : 'pr' }}">{{ $fmt($a[$key]['profit']) }}</span>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Monthly table ── --}}
    <table class="pt">
        <thead>
            <tr class="gh">
                <th class="tf-month col-month" rowspan="2">Month</th>
                @foreach($modules as $key => $mod)
                <th colspan="3" class="gh-{{ $key }}" style="text-align:center;">{{ $mod['icon'] }} {{ $mod['label'] }}</th>
                @endforeach
                @if($showTotal)
                <th colspan="3" style="background:#059669;text-align:center;">📊 Total</th>
                @endif
            </tr>
            <tr class="sh">
                @foreach($modules as $_)
                <th class="ra">Revenue</th><th class="ra">Cost</th><th class="ra">Profit</th>
                @endforeach
                @if($showTotal)
                <th class="ra">Revenue</th><th class="ra">Cost</th><th class="ra">Profit</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($months as $row)
            @php $hasData = $row['total']['revenue'] > 0 || $row['total']['cost'] > 0; @endphp
            <tr class="{{ !$hasData ? 'empty' : '' }}">
                <td class="mc">{{ $row['month_short'] }}</td>
                @foreach($modules as $key => $mod)
                @php
                    $d   = $row[$key];
                    $has = $d['revenue'] > 0 || $d['cost'] > 0;
                    $pc  = (float)$d['profit'];
                @endphp
                <td class="ra mn">{!! $has ? $fmt($d['revenue']) : '<span class="mu">—</span>' !!}</td>
                <td class="ra mn {{ $has ? 'cost' : '' }}">{!! $has ? $fmt($d['cost']) : '<span class="mu">—</span>' !!}</td>
                <td class="ra mn {{ $has ? ($pc >= 0 ? 'pg' : 'pr') : 'mu' }}">{!! $has ? $fmt($pc) : '—' !!}</td>
                @endforeach
                @if($showTotal)
                @php $t = $row['total']; $hasT = $t['revenue'] > 0 || $t['cost'] > 0; $tp = (float)$t['profit']; @endphp
                <td class="ra mn">{!! $hasT ? $fmt($t['revenue']) : '<span class="mu">—</span>' !!}</td>
                <td class="ra mn {{ $hasT ? 'cost' : '' }}">{!! $hasT ? $fmt($t['cost']) : '<span class="mu">—</span>' !!}</td>
                <td class="ra mn {{ $hasT ? ($tp >= 0 ? 'pg' : 'pr') : 'mu' }}">{!! $hasT ? $fmt($tp) : '—' !!}</td>
                @endif
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td class="tf-month">TOTAL</td>
                @foreach($modules as $key => $mod)
                @php $p = (float)$a[$key]['profit']; @endphp
                <td class="ra mn ft-{{ $key }}">{{ $fmt($a[$key]['revenue']) }}</td>
                <td class="ra mn ft-{{ $key }} cost">{{ $fmt($a[$key]['cost']) }}</td>
                <td class="ra mn ft-{{ $key }} {{ $p >= 0 ? 'pg' : 'pr' }}">{{ $fmt($p) }}</td>
                @endforeach
                @if($showTotal)
                @php $tp = (float)$a['total']['profit']; @endphp
                <td class="ra mn tf-total">{{ $fmt($a['total']['revenue']) }}</td>
                <td class="ra mn tf-total cost">{{ $fmt($a['total']['cost']) }}</td>
                <td class="ra mn tf-total {{ $tp >= 0 ? 'pg' : 'pr' }}">{{ $fmt($tp) }}</td>
                @endif
            </tr>
        </tfoot>
    </table>

    {{-- Bottom ── --}}
    <div class="bot">
        <div>
            <div class="sh2">Annual Net Profit In Words:</div>
            <div class="nb" style="margin-bottom:14px;">
                {{ ucfirst((new \NumberToWords\NumberToWords())->getNumberTransformer('en')->toWords((int) abs($annual['total']['profit']))) }} Taka
                {{ $annual['total']['profit'] >= 0 ? '(Net Profit)' : '(Net Loss)' }}
            </div>
            <div class="sh2">Note:</div>
            <div class="nb">
                This profit sheet covers <strong>{{ $moduleTitle }}</strong> transactions for <strong>{{ $year }}</strong>.
                Profit = Revenue &minus; Cost. Generated {{ now()->format('d M Y \a\t H:i') }}.
            </div>
        </div>
        <div>
            <div class="sh2">Annual Summary:</div>
            <table class="tt">
                <tr><td>Total Revenue</td><td class="n">{{ $fmtD($a['total']['revenue']) }}</td></tr>
                <tr><td>Total Cost</td><td class="n cost">{{ $fmtD($a['total']['cost']) }}</td></tr>
                <tr class="grand">
                    <td>Net Profit</td>
                    <td class="n {{ $a['total']['profit'] < 0 ? 'pr' : '' }}">{{ $fmtD($a['total']['profit']) }}</td>
                </tr>
                <tr><td>Profit Margin</td><td class="n">{{ $pct($a['total']['margin']) }}</td></tr>
            </table>
        </div>
    </div>

    {{-- Signature ── --}}
    <div class="sig">
        <div class="sig-c">
            <div class="sig-l">Authorized Signatory</div>
            <div style="font-size:9px;margin-top:3px;">For, {{ optional($company)->name ?? config('app.name') }}</div>
        </div>
    </div>

</div>

<script>window.onload = function () { window.print(); };</script>
</body>
</html>
