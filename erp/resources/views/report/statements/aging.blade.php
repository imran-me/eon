@extends('layout.app')

@section('meta-information')
    <title>{{ $frTitle }}</title>
@endsection

@section('css')
    @include('report.partials.fr-styles')
@endsection

@section('main-content')
@include('report.partials.account-report-tabs')

<div class="fr-shell">
    @include('report.partials.fr-header')

    <div class="fr-card" style="margin-top:14px">
        @php $k = fn ($n) => number_format((float) $n, 2); $isAr = $frSide === 'receivable'; $t = $ag['totals']; @endphp
        <div class="fr-kpis">
            <div class="fr-kpi {{ $isAr ? '' : 'k-red' }}"><div class="fr-k-label">{{ $isAr ? 'Total receivable' : 'Total payable' }}</div><div class="fr-k-value">{{ $k($t['total']) }}</div><div class="fr-k-note">{{ count($ag['rows']) }} {{ $isAr ? 'customers / agents' : 'vendors' }} with a balance</div></div>
            <div class="fr-kpi k-amber"><div class="fr-k-label">Past due</div><div class="fr-k-value">{{ $k($ag['overdue']) }}</div><div class="fr-k-note">{{ $t['total'] > 0 ? number_format($ag['overdue'] / $t['total'] * 100, 1) . '% of the total' : '—' }}</div></div>
            <div class="fr-kpi k-red"><div class="fr-k-label">Over 45 days</div><div class="fr-k-value">{{ $k($t['over']) }}</div><div class="fr-k-note">{{ $t['total'] > 0 ? number_format($t['over'] / $t['total'] * 100, 1) . '% of the total' : '—' }}</div></div>
            <div class="fr-kpi k-blue"><div class="fr-k-label">{{ $isAr ? 'Advances received' : 'Advances paid' }}</div><div class="fr-k-value">{{ $k($ag['advance_total']) }}</div><div class="fr-k-note">credit balances, shown separately</div></div>
        </div>
        <div class="fr-body">
            @include('report.statements._aging')
        </div>
        <div class="fr-foot">Ties to the Party Statement: each party's total is his ledger balance. Payments settle the oldest entry first; an entry is aged from its due date where it has one, otherwise from the date it was posted.</div>
    </div>
</div>
@endsection
