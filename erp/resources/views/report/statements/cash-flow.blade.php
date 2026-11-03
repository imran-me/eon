@extends('layout.app')

@section('meta-information')
    <title>Cash Flow Statement</title>
@endsection

@section('css')
    @include('report.partials.fr-styles')
@endsection

@section('main-content')
@include('report.partials.account-report-tabs')

<div class="fr-shell">
    @include('report.partials.fr-header')

    <div class="fr-card" style="margin-top:14px">
        @php $k = fn ($n) => number_format((float) $n, 2); @endphp
        <div class="fr-kpis">
            <div class="fr-kpi k-blue"><div class="fr-k-label">Beginning cash</div><div class="fr-k-value">{{ $k($cf['beginning']) }}</div></div>
            <div class="fr-kpi"><div class="fr-k-label">Operating</div><div class="fr-k-value">{{ $k($cf['operating']) }}</div><div class="fr-k-note">investing {{ $k($cf['investing']) }} · financing {{ $k($cf['financing']) }}</div></div>
            <div class="fr-kpi {{ $cf['net_change'] < 0 ? 'k-red' : '' }}"><div class="fr-k-label">Net change in cash</div><div class="fr-k-value" style="color: {{ $cf['net_change'] < 0 ? '#dc2626' : '#0f766e' }}">{{ $k($cf['net_change']) }}</div></div>
            <div class="fr-kpi k-amber"><div class="fr-k-label">Ending cash</div><div class="fr-k-value">{{ $k($cf['ending']) }}</div></div>
        </div>
        @if($cf['reconciles'])<div class="fr-ok"><i class="fas fa-circle-check"></i> Ending cash equals the cash and bank accounts on the balance sheet.</div>
        @else<div class="fr-warn"><i class="fas fa-triangle-exclamation"></i> Ending cash differs from the cash and bank accounts by <b>{{ $k(abs($cf['difference'])) }}</b> (accounts hold {{ $k($cf['actual_ending']) }}). A journal entry whose debits and credits do not match causes this — it is in the ledger, not the report.</div>@endif
        <div class="fr-body">
            @include('report.statements._cf')
        </div>
    </div>
</div>
@endsection
