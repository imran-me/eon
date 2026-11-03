{{-- Visa Accounts' KPI strip and switcher — on the page and on the Agent /
     Vendor Ledger tabs, so the switcher never moves. On a ledger tab
     ($vaLedger) every tab is a link; on the page the others switch in place. --}}
@php
    $role = $role ?? request()->route('role');
    $m = fn ($v) => ((float) $v < 0 ? '−' : '') . '৳' . number_format(abs((float) $v), 0);
    $pct = fn ($v) => $v === null ? '—' : number_format($v, 1) . '%';
    $safeRoute = function ($name, $params = []) use ($role) {
        try { return \Illuminate\Support\Facades\Route::has($name) ? route($name, ['role' => $role] + $params) : null; } catch (\Throwable) { return null; }
    };
    $vaOn = $vaLedger ?? null;
    $vaBase = $safeRoute('role.visa-accounts.index');
    $vaTabs = [
        ['pl', 'fa-scale-balanced', 'Profit & Loss', $overview['invoices']],
        ['agents', 'fa-user-tie', 'Agent Ledger', null],
        ['vendors', 'fa-truck-field', 'Vendor Ledger', null],
        ['tx', 'fa-right-left', 'Transactions', $transactions->count()],
        ['journal', 'fa-book', 'Journal History', $journal->count()],
    ];
@endphp
{{-- The current month (owner, 2026-10-15); earlier months are under Profit & Loss → Past months. --}}
<div class="cfa-kpis" title="{{ $kpi['label'] }} — earlier months are under Profit & Loss → Past months">
    <div class="cfa-kpi in"><div class="l">Sales revenue</div><div class="v">{{ $m($kpi['revenue']) }}</div><div class="s"><b class="cfa-kpi-mo">{{ $kpi['label'] }}</b> · {{ $kpi['invoices'] }} invoices · {{ $kpi['items'] }} items</div></div>
    <div class="cfa-kpi out"><div class="l">Cost</div><div class="v">{{ $m($kpi['cost']) }}</div><div class="s">Applications &amp; services invoiced</div></div>
    <div class="cfa-kpi {{ $kpi['profit'] < 0 ? 'bad' : 'good' }}"><div class="l">Gross profit</div><div class="v">{{ $m($kpi['profit']) }}</div><div class="s">Margin {{ $pct($kpi['margin']) }}</div></div>
    <div class="cfa-kpi" title="Applications and services opened this month and on no invoice yet — work done, not charged"><div class="l">Not invoiced</div><div class="v">{{ $m($kpi['unbilled_price']) }}</div><div class="s">{{ $kpi['unbilled_count'] }} items · cost {{ $m($kpi['unbilled_cost']) }}</div></div>
    <div class="cfa-kpi due"><div class="l">Vendor due</div><div class="v">{{ $m($kpi['supplier_due']) }}</div><div class="s">Paid {{ $m($kpi['supplier_paid']) }}</div></div>
    <div class="cfa-kpi due"><div class="l">Agent due</div><div class="v">{{ $m($kpi['agent_due']) }}</div><div class="s">Received {{ $m($kpi['agent_received']) }}</div></div>
</div>

<div class="cfa-tabs" role="tablist">
    @foreach($vaTabs as [$k, $i, $l, $n])
        @php $isLedger = in_array($k, ['agents', 'vendors'], true); @endphp
        @if($isLedger || $vaOn)
            @php $u = $isLedger ? $safeRoute('role.visa-accounts.ledger', ['ledger' => $k]) : ($vaBase ? $vaBase . '#' . $k : null); @endphp
            @if($u)<a class="cfa-tab {{ $vaOn === $k ? 'is-on' : '' }}" href="{{ $u }}"><span class="ic"><i class="fas {{ $i }}"></i></span><span class="t">{{ $l }}</span>@if($n !== null) <span class="n">{{ $n }}</span>@endif</a>@endif
        @else
            <button type="button" class="cfa-tab {{ $k === 'pl' ? 'is-on' : '' }}" data-tab="{{ $k }}" role="tab"><span class="ic"><i class="fas {{ $i }}"></i></span><span class="t">{{ $l }}</span>@if($n !== null) <span class="n">{{ $n }}</span>@endif</button>
        @endif
    @endforeach
</div>
