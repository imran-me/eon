@extends('layout.app')

@section('meta-information')
    <title>Balance Sheet</title>
@endsection

@section('css')
    @include('report.partials.fr-styles')
@endsection

@section('main-content')
@include('report.partials.account-report-tabs')

<div class="fr-shell">
    @include('report.partials.fr-header', ['frClassicUrl' => route('role.report.balance-sheet', ['role' => $frRole, 'classic' => 1] + request()->only(['as_of']))])

    <div class="fr-card" style="margin-top:14px">
        @php $k = fn ($n) => number_format((float) $n, 2); @endphp
        <div class="fr-kpis">
            <div class="fr-kpi"><div class="fr-k-label">Total assets</div><div class="fr-k-value">{{ $k($bs['total_assets']) }}</div></div>
            <div class="fr-kpi k-red"><div class="fr-k-label">Total liabilities</div><div class="fr-k-value">{{ $k($bs['total_liabilities']) }}</div></div>
            <div class="fr-kpi k-blue"><div class="fr-k-label">Total equity</div><div class="fr-k-value">{{ $k($bs['total_equity']) }}</div></div>
            <div class="fr-kpi k-amber"><div class="fr-k-label">Current year earnings</div><div class="fr-k-value">{{ $k($bs['current_year_earnings']) }}</div><div class="fr-k-note">since {{ \Carbon\Carbon::parse($bs['fy_start'])->format('d M Y') }}</div></div>
        </div>
        @if($bs['balanced'])<div class="fr-ok"><i class="fas fa-circle-check"></i> Assets equal liabilities plus equity.</div>
        @else<div class="fr-warn"><i class="fas fa-triangle-exclamation"></i> Assets and liabilities + equity differ by <b>{{ $k(abs($bs['difference'])) }}</b>. A journal entry whose debits and credits do not match causes this — it is in the ledger, not the report.</div>@endif
        <div class="fr-body">
            @include('report.statements._bs')
        </div>
    </div>
</div>
@endsection
