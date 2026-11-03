@extends('layout.app')

@section('meta-information')
    <title>Profit and Loss</title>
@endsection

@section('css')
    @include('report.partials.fr-styles')
@endsection

@section('main-content')
@include('report.partials.account-report-tabs')

<div class="fr-shell">
    @include('report.partials.fr-header', ['frClassicUrl' => route('role.report.profit-loss', ['role' => $frRole, 'classic' => 1] + request()->only(['date_from', 'date_to']))])

    <div class="fr-card" style="margin-top:14px">
        @php $k = fn ($n) => number_format((float) $n, 2); $inc = $pl['sections']['operating_income']['total']; @endphp
        <div class="fr-kpis">
            <div class="fr-kpi"><div class="fr-k-label">Operating income</div><div class="fr-k-value">{{ $k($inc) }}</div><div class="fr-k-note">after discounts &amp; adjustments</div></div>
            <div class="fr-kpi k-blue"><div class="fr-k-label">Gross profit</div><div class="fr-k-value">{{ $k($pl['gross_profit']) }}</div><div class="fr-k-note">{{ $pl['gross_margin'] !== null ? number_format($pl['gross_margin'], 1) . '% margin' : '—' }}</div></div>
            <div class="fr-kpi k-amber"><div class="fr-k-label">Operating profit</div><div class="fr-k-value">{{ $k($pl['operating_profit']) }}</div><div class="fr-k-note">gross profit − operating expense</div></div>
            <div class="fr-kpi {{ $pl['net_profit'] < 0 ? 'k-red' : '' }}"><div class="fr-k-label">Net profit / loss</div><div class="fr-k-value" style="color: {{ $pl['net_profit'] < 0 ? '#dc2626' : '#0f766e' }}">{{ $k($pl['net_profit']) }}</div><div class="fr-k-note">{{ $pl['net_margin'] !== null ? number_format($pl['net_margin'], 1) . '% of operating income' : '—' }}</div></div>
        </div>
        <div class="fr-body">
            @include('report.statements._pl')
        </div>
    </div>
</div>
@endsection
