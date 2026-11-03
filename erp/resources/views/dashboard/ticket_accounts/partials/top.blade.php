{{-- Ticket Accounts' KPI strip and switcher. The same block sits on the page
     and on the Agent / Vendor Ledger tabs, so the switcher never moves. On a
     ledger tab ($tkLedger) every tab is a link; on the page the other tabs
     switch in place. --}}
@php
    $role = $role ?? request()->route('role');
    $m = fn ($v) => ((float) $v < 0 ? '−' : '') . '৳' . number_format(abs((float) $v), 0);
    $pct = fn ($v) => $v === null ? '—' : number_format($v, 1) . '%';
    $d = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '—';
    $safeRoute = function ($name, $params = []) use ($role) {
        try { return \Illuminate\Support\Facades\Route::has($name) ? route($name, ['role' => $role] + $params) : null; } catch (\Throwable) { return null; }
    };
    $partyUrl = $safeRoute('role.party-statement.index');
    $editUrl = fn ($id) => $safeRoute('role.ticket-direct-sale.edit', ['ticket_direct_sale' => $id]);
    $saleUrl = fn ($id) => $safeRoute('role.ticket-sales.show', ['ticket_sale' => $id]);
    $useLabel = ['cf' => ['Contract flight', ''], 'sale' => ['Sold', 'ok'], 'idle' => ['Idle', 'warn'], 'cancelled' => ['Cancelled', 'bad']];
@endphp
@php
    $tkOn = $tkLedger ?? null;
    $tkBase = $safeRoute('role.ticketing.accounts');
    $tkTabs = [
        ['pl', 'fa-scale-balanced', 'Profit & Loss', null],
        ['agents', 'fa-user-tie', 'Agent Ledger', null],
        ['vendors', 'fa-truck-field', 'Vendor Ledger', null],
        ['refunds', 'fa-rotate-left', 'Refunds & Cancellations', $cancelled->count() + $refunds->count() + $reissues->count() + ($changes ?? collect())->count()],
        ['tx', 'fa-right-left', 'Transactions', $transactions->count()],
        ['journal', 'fa-book', 'Journal History', $journal->count()],
    ];
@endphp
{{-- The current month (owner, 2026-10-15); earlier months are under Profit & Loss → Past months. --}}
<div class="cfa-kpis" title="{{ $kpi['label'] }} — earlier months are under Profit & Loss → Past months">
    <div class="cfa-kpi in"><div class="l">Sales revenue</div><div class="v">{{ $m($kpi['revenue']) }}</div><div class="s"><b class="cfa-kpi-mo">{{ $kpi['label'] }}</b> · {{ $kpi['invoices'] }} invoices</div></div>
    <div class="cfa-kpi out"><div class="l">Ticket cost</div><div class="v">{{ $m($kpi['cost']) }}</div><div class="s">{{ $kpi['tickets'] }} tickets{{ $kpi['penalty'] > 0 ? ' · penalties ' . $m($kpi['penalty']) : '' }}</div></div>
    <div class="cfa-kpi {{ $kpi['sold_profit'] < 0 ? 'bad' : 'good' }}"><div class="l">Profit on sold tickets</div><div class="v">{{ $m($kpi['sold_profit']) }}</div><div class="s">Margin {{ $pct($kpi['margin']) }}</div></div>
    <div class="cfa-kpi" title="Billed inside the contract flight package — their revenue is on Flight Accounts"><div class="l">Contract flight tickets</div><div class="v">{{ $m($kpi['cf_cost']) }}</div><div class="s">{{ $kpi['cf_count'] }} tickets{{ $kpi['idle_count'] ? ' · ' . $kpi['idle_count'] . ' idle ' . $m($kpi['idle_cost']) : '' }}</div></div>
    <div class="cfa-kpi due"><div class="l">Supplier due</div><div class="v">{{ $m($kpi['supplier_due']) }}</div><div class="s">Paid {{ $m($kpi['supplier_paid']) }}</div></div>
    <div class="cfa-kpi due"><div class="l">Agent due</div><div class="v">{{ $m($kpi['agent_due']) }}</div><div class="s">Received {{ $m($kpi['agent_received']) }}</div></div>
</div>

<div class="cfa-tabs" role="tablist">
    @foreach($tkTabs as [$k, $i, $l, $n])
        @php $isLedger = in_array($k, ['agents', 'vendors'], true); @endphp
        @if($isLedger || $tkOn)
            @php $u = $isLedger ? $safeRoute('role.ticketing.accounts.ledger', ['ledger' => $k]) : ($tkBase ? $tkBase . '#' . $k : null); @endphp
            @if($u)<a class="cfa-tab {{ $tkOn === $k ? 'is-on' : '' }}" href="{{ $u }}"><span class="ic"><i class="fas {{ $i }}"></i></span><span class="t">{{ $l }}</span>@if($n !== null) <span class="n">{{ $n }}</span>@endif</a>@endif
        @else
            <button type="button" class="cfa-tab {{ $k === 'pl' ? 'is-on' : '' }}" data-tab="{{ $k }}" role="tab"><span class="ic"><i class="fas {{ $i }}"></i></span><span class="t">{{ $l }}</span>@if($n !== null) <span class="n">{{ $n }}</span>@endif</button>
        @endif
    @endforeach
    @if($u = $safeRoute('role.ticketing.accounts.workbook'))
        {{-- the Ticketing workbook: built from the database at this moment --}}
        <a class="cfa-tab cfa-tab-xl" href="{{ $u }}" data-no-prefetch target="_blank" rel="noopener" title="Every ticket, change, seller and agent as an Excel workbook — always up to this moment"><span class="ic"><i class="fas fa-file-excel"></i></span><span class="t">Excel</span></a>
    @endif
</div>
