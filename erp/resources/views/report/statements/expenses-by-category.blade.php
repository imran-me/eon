@extends('layout.app')

@section('meta-information')
    <title>Expenses by Category</title>
@endsection

@section('css')
    @include('report.partials.fr-styles')
@endsection

@section('main-content')
@include('report.partials.account-report-tabs')

<div class="fr-shell">
    @include('report.partials.fr-header')

    <div class="fr-card" style="margin-top:14px">
        @php $k = fn ($n) => number_format((float) $n, 2); $top = collect($ex['sections'])->flatMap(fn ($s) => $s['rows'])->sortByDesc('amount')->first(); @endphp
        <div class="fr-kpis">
            <div class="fr-kpi k-red"><div class="fr-k-label">Total expenses</div><div class="fr-k-value">{{ $k($ex['total']) }}</div><div class="fr-k-note">previous period {{ \Carbon\Carbon::parse($ex['prev_from'])->format('d M') }} – {{ \Carbon\Carbon::parse($ex['prev_to'])->format('d M Y') }}: {{ $k($ex['previous_total']) }}</div></div>
            <div class="fr-kpi"><div class="fr-k-label">Change vs previous period</div><div class="fr-k-value" style="color: {{ ($ex['change'] ?? 0) > 0 ? '#dc2626' : '#059669' }}">{{ $ex['change'] === null ? '—' : (($ex['change'] > 0 ? '▲ ' : '▼ ') . number_format(abs($ex['change']), 1) . '%') }}</div></div>
            <div class="fr-kpi k-blue"><div class="fr-k-label">Biggest category</div><div class="fr-k-value" style="font-size:16px">{{ $top['name'] ?? '—' }}</div><div class="fr-k-note">{{ $top ? $k($top['amount']) . ' · ' . number_format($top['share'], 1) . '%' : '' }}</div></div>
            <div class="fr-kpi k-amber"><div class="fr-k-label">Accounts with spend</div><div class="fr-k-value">{{ collect($ex['sections'])->sum(fn ($s) => count($s['rows'])) }}</div></div>
        </div>
        <div class="fr-body">
            @include('report.statements._exp')
        </div>
    </div>
</div>
@endsection
