{{-- Expenses by category table — shared by the screen and the print page. Expects $ex, $frFrom, $frTo, $frLedgerScope, $frRole, $frPrinting. --}}
@php
    $fm = fn ($n) => number_format((float) $n, 2);
    $canLedger = ! ($frPrinting ?? false) && $frLedgerScope && auth()->user()->can('view account ledger report');
    $ledgerUrl = fn (int $id) => $canLedger
        ? route('role.report.account-ledger', ['role' => $frRole, 'account_id' => $id, 'date_from' => $frFrom, 'date_to' => $frTo, 'scope' => $frLedgerScope, 'from_report' => 'profit-loss'])
        : null;
    $chg = function ($c) {
        if ($c === null) { return '—'; }
        $cls = $c > 0.05 ? 'fr-up' : ($c < -0.05 ? 'fr-down' : '');
        return '<span class="' . $cls . '">' . ($c > 0 ? '▲ ' : ($c < 0 ? '▼ ' : '')) . number_format(abs($c), 1) . '%</span>';
    };
@endphp
<table class="fr-st">
    <thead><tr><th>Category (expense account)</th><th class="fr-r">Amount</th><th class="fr-r">Previous period</th><th class="fr-r">Change</th><th style="width:140px">Share of total</th></tr></thead>
    <tbody>
    @forelse($ex['sections'] as $s)
        <tr class="fr-sec"><td colspan="5">{{ $s['label'] }}</td></tr>
        @foreach($s['rows'] as $r)
        @php $u = $ledgerUrl($r['id']); @endphp
        <tr class="fr-row">
            <td><span class="fr-code">{{ $r['code'] }}</span>@if($u)<a class="fr-link" href="{{ $u }}">{{ $r['name'] }}</a>@else{{ $r['name'] }}@endif</td>
            <td class="fr-r">{{ $fm($r['amount']) }}</td>
            <td class="fr-r" style="color:#64748b">{{ $fm($r['previous']) }}</td>
            <td class="fr-r">{!! $chg($r['change']) !!}</td>
            <td><div class="fr-pct"><i style="width: {{ max(0, min(100, $r['share'])) }}%"></i></div><span style="font-size:11px; color:#64748b">{{ number_format($r['share'], 1) }}%</span></td>
        </tr>
        @endforeach
        <tr class="fr-sub"><td>Total for {{ $s['label'] }}</td><td class="fr-r">{{ $fm($s['total']) }}</td><td class="fr-r" style="color:#64748b">{{ $fm($s['previous']) }}</td><td class="fr-r">{!! $chg($s['previous'] != 0.0 ? ($s['total'] - $s['previous']) / abs($s['previous']) * 100 : null) !!}</td><td></td></tr>
    @empty
        <tr><td colspan="5" class="fr-empty">No expenses in this period.</td></tr>
    @endforelse
    @if(count($ex['sections']))
        <tr class="fr-grand"><td>TOTAL</td><td class="fr-r">{{ $fm($ex['total']) }}</td><td class="fr-r">{{ $fm($ex['previous_total']) }}</td><td class="fr-r">{!! $ex['change'] === null ? '—' : (($ex['change'] > 0 ? '▲ ' : '▼ ') . number_format(abs($ex['change']), 1) . '%') !!}</td><td></td></tr>
    @endif
    </tbody>
</table>
