{{-- Flight Accounts' header, filters, KPI strip and tab row. The same block
     sits on the accounts page and on the Agent / Vendor / Company Ledger tabs,
     so the switcher never moves. On a ledger tab ($cfLedger) every tab is a
     link; on the accounts page P&L / Transactions / Journal are in-page. --}}
@php
    $cfaOnLedger = $cfLedger ?? null;
    $cfaQuery = array_filter(request()->only(['from', 'to', 'agent_id']), fn ($v) => $v !== null && $v !== '');
    $m = fn ($v) => ((float) $v < 0 ? '−' : '') . '৳' . number_format(abs((float) $v), 0);
    $pct = fn ($v) => $v === null ? '—' : number_format($v, 1) . '%';
    $rangeLabel = ($from || $to) ? (($from ? $from->format('d M Y') : 'Start') . ' – ' . ($to ? $to->format('d M Y') : 'Today')) : 'All time';
@endphp
    {{-- Headline P&L — the current month (owner, 2026-10-15); earlier months
         are under Profit & Loss → Past months. --}}
    <div class="cfa-kpis" title="{{ $kpi['label'] }} — earlier months are under Profit & Loss → Past months">
        <div class="cfa-kpi in"><div class="l">Revenue (agent charges)</div><div class="v">{{ $m($kpi['revenue']) }}</div><div class="s"><b class="cfa-kpi-mo">{{ $kpi['label'] }}</b> · {{ $kpi['passengers'] }} passengers · {{ $kpi['flights'] }} flights{{ $kpi['voided'] ? ' · ' . $kpi['voided'] . ' voided' : '' }}</div></div>
        <div class="cfa-kpi out"><div class="l">Total cost</div><div class="v">{{ $m($kpi['cost']) }}</div><div class="s">Tickets {{ $m($kpi['ticket_cost']) }} · Services {{ $m($kpi['service_cost']) }}{{ $kpi['change_cost'] > 0 ? ' · Changes ' . $m($kpi['change_cost']) : '' }}</div></div>
        <div class="cfa-kpi {{ $kpi['profit'] < 0 ? 'bad' : 'good' }}"><div class="l">Gross profit</div><div class="v">{{ $m($kpi['profit']) }}</div><div class="s">Margin {{ $pct($kpi['margin']) }}{{ $kpi['reissue_cost'] > 0 ? ' · Reissue ' . $m($kpi['reissue_cost']) . ' aside' : '' }}</div></div>
        <div class="cfa-kpi good"><div class="l">Received from agents (ledger)</div><div class="v">{{ $m($kpi['agent_received']) }}</div><div class="s">{{ $kpi['revenue'] > 0 ? number_format($kpi['agent_received'] / $kpi['revenue'] * 100, 0) . '% of revenue' : '—' }}</div></div>
        <div class="cfa-kpi due"><div class="l">Agent due</div><div class="v">{{ $m($kpi['agent_due']) }}</div><div class="s">{{ $kpi['due_passengers'] }} passengers · {{ $kpi['due_agents'] }} agents</div></div>
        <div class="cfa-kpi due"><div class="l">Vendor due</div><div class="v">{{ $m($kpi['vendor_due']) }}</div><div class="s">{{ $kpi['vendor_due_items'] }} items · {{ $kpi['vendor_due_vendors'] }} vendors</div></div>
    </div>

    <div class="cfa-tabs" role="tablist">
        @foreach([['pl', 'fa-scale-balanced', 'Profit & Loss', $flights->count()], ['agents', 'fa-user-tie', 'Agent Ledger', null], ['vendors', 'fa-truck-field', 'Vendor Ledger', null], ['company', 'fa-building', 'Company Ledger', null], ['tx', 'fa-right-left', 'Transactions', $transactions->count()], ['journal', 'fa-book', 'Journal History', $journal->count()]] as [$cfaTk, $cfaTi, $cfaTl, $cfaTn])
            @php $cfaIsLedger = in_array($cfaTk, ['agents', 'vendors', 'company'], true); @endphp
            @if($cfaIsLedger || $cfaOnLedger)
                <a class="cfa-tab {{ $cfaOnLedger === $cfaTk ? 'is-on' : '' }}" href="{{ $cfaIsLedger ? route('role.contract-flights.accounts.ledger', ['role' => $role, 'ledger' => $cfaTk] + $cfaQuery) : route('role.contract-flights.accounts', ['role' => $role] + $cfaQuery) . '#' . $cfaTk }}"><span class="ic"><i class="fas {{ $cfaTi }}"></i></span><span class="t">{{ $cfaTl }}</span>@if($cfaTn !== null) <span class="n">{{ $cfaTn }}</span>@endif</a>
            @else
                <button type="button" class="cfa-tab {{ $cfaTk === 'pl' ? 'is-on' : '' }}" data-tab="{{ $cfaTk }}" role="tab"><span class="ic"><i class="fas {{ $cfaTi }}"></i></span><span class="t">{{ $cfaTl }}</span>@if($cfaTn !== null) <span class="n">{{ $cfaTn }}</span>@endif</button>
            @endif
        @endforeach
    </div>
