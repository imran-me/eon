{{-- Monthly Report body (snapshot, health score, key gaps, recommendations) — shared by the screen and the print page. Expects $mr. --}}
@php
    $fm = fn ($n) => number_format((float) $n, 0);
    $cls = fn ($s) => ['Good' => 'good', 'Moderate' => 'moderate', 'Worse' => 'worse'][$s] ?? 'nodata';
@endphp

<div class="fr-h2"><i class="fas fa-gauge-high"></i> Financial Snapshot
    <span style="font-weight:600; font-size:11.5px; color:#64748b">{{ \Carbon\Carbon::parse($mr['from'])->format('d M') }} – {{ \Carbon\Carbon::parse($mr['to'])->format('d M Y') }}
        vs {{ \Carbon\Carbon::parse($mr['prev_from'])->format('d M') }} – {{ \Carbon\Carbon::parse($mr['prev_to'])->format('d M Y') }}@if($mr['mtd']) · month to date, same number of days @endif</span></div>
<table class="fr-st">
    <thead><tr><th>KPI</th><th class="fr-r">Last Month</th><th class="fr-r">This Month</th><th class="fr-r">Change</th><th>Status</th></tr></thead>
    <tbody>
    @foreach($mr['kpis'] as $k)
        <tr class="fr-row"><td style="padding-left:14px; font-weight:700">{{ $k['label'] }}</td>
            <td class="fr-r" style="color:#64748b">{{ $fm($k['last']) }}</td>
            <td class="fr-r" style="font-weight:800">{{ $fm($k['this']) }}</td>
            <td class="fr-r">{!! $k['change'] === null ? '—' : '<span class="' . ($k['better'] ? 'fr-down' : 'fr-up') . '">' . ($k['change'] >= 0 ? '▲ ' : '▼ ') . number_format(abs($k['change']), 1) . '%</span>' !!}</td>
            <td>@if($k['better'] === null)<span class="fr-pill nodata">No comparison</span>@elseif($k['better'])<span class="fr-pill good">Better</span>@else<span class="fr-pill worse">Worse</span>@endif</td></tr>
    @endforeach
    </tbody>
</table>

<div class="fr-h2"><i class="fas fa-heart-pulse"></i> Financial Health Score</div>
<table class="fr-st">
    <thead><tr><th>Area</th><th>Status</th><th>Comments (why)</th></tr></thead>
    <tbody>
    @foreach($mr['health'] as $h)
        <tr class="fr-row"><td style="padding-left:14px; font-weight:700">{{ $h['area'] }}</td><td><span class="fr-pill {{ $cls($h['status']) }}">{{ $h['status'] }}</span></td><td style="color:#475569">{{ $h['comment'] }}</td></tr>
    @endforeach
    </tbody>
</table>

<div class="fr-h2"><i class="fas fa-triangle-exclamation"></i> Key Gaps Identified</div>
@foreach($mr['gaps'] as $i => $g)
    <div class="fr-gap"><b>{{ $i + 1 }}. {{ $g['title'] }}</b><ul>@foreach($g['points'] as $p)<li>{{ $p }}</li>@endforeach</ul></div>
@endforeach

<div class="fr-h2"><i class="fas fa-list-check"></i> Recommendations</div>
<ul class="fr-recs">@foreach($mr['recommendations'] as $r)<li>{{ $r }}</li>@endforeach</ul>
<div class="fr-verdict">{{ $mr['verdict'] }}</div>
<div style="margin-top:10px; font-size:11px; color:#94a3b8">Statuses and gaps come from fixed rules over the ledger (thresholds in config/account_reports.php) — each line says which figures set it.</div>