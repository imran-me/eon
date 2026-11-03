@extends('layout.app')

@section('title', 'Company Ledger')

@section('main-content')
@php
    $role = Str::slug(Auth::user()->getRoleNames()->first());
    $fmt  = fn ($v) => '৳' . number_format(abs($v), 0);
    $sgn  = fn ($v) => ($v < 0 ? '−' : '') . '৳' . number_format(abs($v), 0);
    // Company Ledger accent — the dashboard card's teal.
    $accent = ['bg' => '#f0fdfa', 'fg' => '#0f766e', 'bar' => '#14b8a6', 'icon' => 'fa-building'];
    // Newest first, as every ledger here opens; the running figure was
    // computed oldest first so it still reads correctly row by row.
    $list = $rows->reverse()->values();
    $monthStart = now()->startOfMonth()->toDateString();
    $monthRows = $rows->filter(fn ($r) => (string) $r['date'] >= $monthStart);
@endphp

<style>
@media print {
    #sidebar, #mobileMenuButton, .header, .admin-footer, #commandPalette { display: none !important; }
    #mainContentWrapper { margin-left: 0 !important; }
    body, .min-h-screen { background: #fff !important; }
    main { padding: 0 !important; }
}
/* Title row and figures — the Agent / Vendor ledger's (ledger.blade.php),
   copied value for value so the three ledgers read alike. */
.ps-list-count { font-size: 11px; font-weight: 700; color: #4338ca; background: #eef2ff; border-radius: 20px; padding: 4px 10px; white-space: nowrap; }
.ps-kpis { display: flex; align-items: stretch; gap: 6px; flex-wrap: wrap; }
.ps-kpi { --ps-tint: #f8fafc; --ps-tint-bd: #eef2f7; --ps-tint-fg: #475569; --ps-val: #0f172a;
    display: flex; align-items: center; gap: 9px; min-width: 128px; padding: 4px 12px 4px 8px; border-radius: 10px;
    background: var(--ps-tint); border: 1px solid var(--ps-tint-bd); text-align: right; }
.ps-kpi > div { flex: 1; }
.ps-kpi-ic { flex: none; width: 26px; height: 26px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 11px; background: #fff; color: var(--ps-tint-fg); border: 1px solid var(--ps-tint-bd); }
.ps-kpi.is-type  { --ps-tint: var(--ps-soft, #f8fafc); --ps-tint-bd: #e8edf4; --ps-tint-fg: var(--ps-fg); --ps-val: var(--ps-fg); }
.ps-kpi.is-owed  { --ps-tint: #fef2f2; --ps-tint-bd: #fecaca; --ps-tint-fg: #b91c1c; --ps-val: #dc2626; }
.ps-kpi.is-adv   { --ps-tint: #ecfdf5; --ps-tint-bd: #a7f3d0; --ps-tint-fg: #047857; --ps-val: #059669; }
.ps-kpi.is-pay   { --ps-tint: #fffbeb; --ps-tint-bd: #fde68a; --ps-tint-fg: #b45309; --ps-val: #b45309; }
.ps-kpi-l { font-size: 9.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #94a3b8; white-space: nowrap; }
.ps-kpi-v { font-size: 15px; font-weight: 700; color: var(--ps-val); line-height: 1.2; margin-top: 1px; font-variant-numeric: tabular-nums; }
.ps-kpi-s { font-size: 10px; color: #94a3b8; margin-top: 1px; white-space: nowrap; }
.ps-panel-accent { position: relative; }
.ps-panel-accent::before { content: ""; position: absolute; left: 0; right: 0; top: 0; height: 3px; background: linear-gradient(90deg, var(--ps-bar), var(--ps-fg)); z-index: 1; }
.ps-title-ic { width: 26px; height: 26px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; color: #fff; background: linear-gradient(135deg, var(--ps-bar), var(--ps-fg)); }
.ps-roster-head { background: linear-gradient(135deg, #4338ca 0%, #5b4ce0 45%, #6d28d9 100%); color: #fff; flex-wrap: nowrap; min-width: 0; }
.ps-roster-head .ps-roster-title { color: #fff; white-space: nowrap; }
.ps-roster-head .ps-title-ic { background: rgba(255,255,255,.2); box-shadow: inset 0 0 0 1px rgba(255,255,255,.3); }
.ps-roster-head .ps-list-count { background: rgba(255,255,255,.18); color: #fff; }
.ps-roster-head .ps-kpis { flex-wrap: nowrap; min-width: 0; overflow: visible; padding: 2px; border-radius: 12px; background: rgba(25,17,112,.24); box-shadow: inset 0 2px 5px rgba(17,12,80,.28); }
#companyCard.ps-panel-accent::before { background: rgba(255,255,255,.22); }
.ps-roster-head .ps-kpi { --ps-tint: #fff; --ps-tint-bd: rgba(67,56,202,.12); flex: 0 0 auto; gap: 7px; padding: 2px 10px 2px 7px; border-radius: 9px; background: linear-gradient(145deg, #fff 0%, #f9faff 62%, #edf0f8 100%); box-shadow: 0 4px 7px rgba(15,10,82,.32), -1px -1px 2px rgba(255,255,255,.55), inset 0 2px 2px #fff, inset 0 -2px 3px rgba(88,91,132,.18); }
.ps-roster-head .ps-kpi-ic { width: 22px; height: 22px; border-radius: 7px; font-size: 10px; background: linear-gradient(145deg, #fff, #edf0f7); box-shadow: 1px 2px 3px rgba(51,51,99,.18), inset 1px 1px 1px #fff, inset -1px -1px 2px rgba(88,91,132,.14); }
.ps-roster-head .ps-kpi-l { font-size: 9px; line-height: 1.1; }
.ps-roster-head .ps-kpi-v { font-size: 13px; line-height: 1.15; }
.ps-roster-head .ps-kpi-s { font-size: 9px; line-height: 1.05; }
@media (max-width: 900px) { .ps-roster-head .ps-kpis { overflow-x: auto; overflow-y: hidden; scrollbar-width: none; } .ps-roster-head .ps-kpis::-webkit-scrollbar { display: none; } }
.ps-flow { display: inline-flex; align-items: center; gap: 3px; margin-left: 6px; padding: 1px 6px; border-radius: 999px; font-size: 9.5px; font-weight: 700; vertical-align: middle; }
.ps-bal { font-family: monospace; font-weight: 700; white-space: nowrap; }
.ps-date { white-space: nowrap; }
.ps-empty { padding: 30px 16px; text-align: center; font-size: 12.5px; color: #94a3b8; }
</style>

@if(!empty($cfLedger))
@include('contract-flights.partials.accounts-subtabs')
@else
@include('layout.party-tabs')
@endif
@include('party-statement.partials.bar-styles')
@include('party-statement.partials.feed-table-styles')

<div class="min-h-screen bg-gray-50">
    <div class="px-2.5 py-2 space-y-2">

        <div id="companyCard" class="ps-panel ps-panel-accent" style="--ps-bar:{{ $accent['bar'] }};--ps-fg:{{ $accent['fg'] }};--ps-soft:{{ $accent['bg'] }}">
            <div class="ps-roster-head flex items-center justify-between gap-3 px-5 py-2 border-b border-gray-100">
                <div class="flex items-center gap-3 flex-wrap">
                    <p class="ps-roster-title text-sm font-bold flex items-center gap-2">
                        <span class="ps-title-ic"><i class="fas {{ $accent['icon'] }}"></i></span> Company Ledger
                    </p>
                    <span class="ps-list-count">{{ $rows->count() }} {{ $rows->count() === 1 ? 'entry' : 'entries' }}</span>
                </div>
                <div class="ps-kpis">
                    <div class="ps-kpi {{ $totals['borne'] > 0 ? 'is-owed' : '' }}">
                        <span class="ps-kpi-ic"><i class="fas fa-arrow-trend-down"></i></span>
                        <div>
                            <div class="ps-kpi-l">Borne by company</div>
                            <div class="ps-kpi-v">{{ $fmt($totals['borne']) }}</div>
                            <div class="ps-kpi-s">penalties, fares, fees</div>
                        </div>
                    </div>
                    <div class="ps-kpi {{ $totals['recovered'] > 0 ? 'is-adv' : '' }}">
                        <span class="ps-kpi-ic"><i class="fas fa-arrow-trend-up"></i></span>
                        <div>
                            <div class="ps-kpi-l">Recovered</div>
                            <div class="ps-kpi-v">{{ $fmt($totals['recovered']) }}</div>
                            <div class="ps-kpi-s">billed back to agents</div>
                        </div>
                    </div>
                    <div class="ps-kpi {{ $totals['net'] > 0 ? 'is-pay' : '' }}">
                        <span class="ps-kpi-ic"><i class="fas fa-scale-balanced"></i></span>
                        <div>
                            <div class="ps-kpi-l">Net cost</div>
                            <div class="ps-kpi-v">{{ $sgn($totals['net']) }}</div>
                            <div class="ps-kpi-s">borne − recovered</div>
                        </div>
                    </div>
                    <div class="ps-kpi is-type">
                        <span class="ps-kpi-ic"><i class="far fa-calendar"></i></span>
                        <div>
                            <div class="ps-kpi-l">This month</div>
                            <div class="ps-kpi-v">{{ $sgn($monthRows->sum('borne') - $monthRows->sum('recovered')) }}</div>
                            <div class="ps-kpi-s">{{ $monthRows->count() }} {{ $monthRows->count() === 1 ? 'entry' : 'entries' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            @include('party-statement.partials.table-bar', [
                'prefix'      => 'cl',
                'placeholder' => 'Search flight, passenger, PNR, agent, head, reference or amount…',
                'segments'    => [['today', 'Today'], ['yesterday', 'Yesterday'], ['week', 'Last Week'], ['month', 'This Month'], ['all', 'All']],
                'dates'       => true,
                'print'       => true,
            ])

            @if(! $installed)
                <div class="ps-empty"><i class="fas fa-circle-info"></i> The Company Ledger heads are not set up on this server yet.</div>
            @elseif($list->isEmpty())
                <div class="ps-empty">Nothing borne or recovered yet.</div>
            @else
            <div style="overflow-x:auto">
                <table class="ps-recent">
                    <thead>
                        <tr>
                            <th class="ps-pick"><input type="checkbox" class="ps-pick-box ps-pick-all" title="Tick every row shown" aria-label="Select all shown"></th>
                            {{-- Entry and Details get the room; the three amounts stay narrow (owner, 2026-10-06) --}}
                            <th class="ps-date" style="width:8%">Date</th><th style="width:19%">Entry</th><th class="ps-details" style="width:23%">Details</th><th style="width:11%">Agent / party</th><th style="width:12%">Passenger</th>
                            <th style="text-align:right;width:8%">Borne</th><th style="text-align:right;width:8%">Recovered</th><th style="text-align:right;width:9%">Net cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($list as $r)
                            @php $isBorne = $r['side'] === 'borne'; @endphp
                            <tr data-date="{{ $r['date'] }}" data-pick-id="{{ $r['id'] }}"
                                data-hay="{{ strtolower(implode(' ', array_filter([$r['reference'], $r['details'], $r['head'], $r['party'], $r['passenger'] ?? null, $r['flight'] ?? null, $isBorne ? 'borne' : 'recovered', \Carbon\Carbon::parse($r['date'])->format('d/m/Y'), number_format(abs($r['borne'] ?: $r['recovered'])), number_format(abs($r['borne'] ?: $r['recovered']), 0, '', '')]))) }}">
                                <td class="ps-pick"><input type="checkbox" class="ps-pick-box" aria-label="Select this entry"></td>
                                <td class="ps-date">
                                    {{ \Carbon\Carbon::parse($r['date'])->format('d/m/Y') }}
                                    @if($r['time'])<div class="ps-sub" style="margin-top:2px;">{{ \Carbon\Carbon::parse($r['time'])->format('H:i:s') }}</div>@endif
                                </td>
                                <td>
                                    <div class="ps-nm">{{ $isBorne ? 'Borne' : 'Recovered' }}<span class="ps-flow" style="background:{{ $isBorne ? '#fee2e2' : '#dcfce7' }};color:{{ $isBorne ? '#b91c1c' : '#15803d' }};"><i class="fas fa-arrow-{{ $isBorne ? 'up' : 'down' }}"></i> {{ $isBorne ? 'Cost' : 'Income' }}</span>@if($r['reversal'])<span class="ps-flow" style="background:#f1f5f9;color:#475569;">Reversal</span>@endif</div>
                                    <div class="ps-sub">{{ $r['head'] }}</div>
                                    @if($r['reference'])<div class="ps-vno">{{ $r['reference'] }}</div>@endif
                                </td>
                                <td class="ps-details">
                                    @if($r['details'])<div class="ps-nm">{{ $r['details'] }}</div>@else<span class="ps-amt-none">—</span>@endif
                                </td>
                                <td>@if($r['party'])<div class="ps-nm">{{ $r['party'] }}</div>@else<span class="ps-amt-none">—</span>@endif</td>
                                {{-- Passenger (owner, 2026-10-06): whose change it was, flight or PNR under it --}}
                                <td>@if(! empty($r['passenger']))<div class="ps-nm">{{ $r['passenger'] }}</div>@if(! empty($r['flight']))<div class="ps-sub">{{ $r['flight'] }}</div>@endif @else<span class="ps-amt-none">—</span>@endif</td>
                                <td style="text-align:right;">@if($r['borne'])<span class="ps-amt-dr">{{ $sgn($r['borne']) }}</span>@else<span class="ps-amt-none">—</span>@endif</td>
                                <td style="text-align:right;">@if($r['recovered'])<span class="ps-amt-cr">{{ $sgn($r['recovered']) }}</span>@else<span class="ps-amt-none">—</span>@endif</td>
                                <td style="text-align:right;"><span class="ps-bal" style="color:{{ $r['balance'] > 0 ? '#dc2626' : '#1d4ed8' }};">{{ $sgn($r['balance']) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div id="clEmpty" class="ps-empty" style="display:none;">No entries match.</div>
            @endif
        </div>

        {{-- Profit lives below, not in the table above (owner, 2026-09-28). --}}
        @include('party-statement.partials.company-funds', ['funds' => $funds ?? ['ok' => false], 'owners' => $owners ?? collect()])

    </div>
</div>
@endsection

@section('js')
<script>
@include('party-statement.partials.bar-scripts')

// Search + range over the rows on the page — the dashboard feed's bar, over
// the whole Company Ledger (it is small; every row is already here).
(function initCompanyBar() {
    const rows = Array.from(document.querySelectorAll('#companyCard tbody tr[data-date]'));
    let q = '', from = '', to = '', range = 'all';
    const monthStart = () => { const d = new Date(); d.setDate(1); return psYmd(d); };

    const paint = () => {
        let shown = 0;
        rows.forEach(tr => {
            const d = tr.dataset.date;
            const ok = (!q || tr.dataset.hay.includes(q)) && (!from || d >= from) && (!to || d <= to);
            tr.style.display = ok ? '' : 'none';
            if (ok) shown++;
        });
        psPaintCount('cl', q !== '', shown, rows.length);
        const empty = document.getElementById('clEmpty');
        if (empty) empty.style.display = rows.length && !shown ? '' : 'none';
    };

    psInitBar('cl', {
        onSearch: term => { q = term; paint(); },
        onRange: (key, f, t) => { range = key; from = f; to = t; paint(); },
        ranges: {
            today:     () => [psYmd(new Date()), psYmd(new Date())],
            yesterday: () => [psDaysBack(1), psDaysBack(1)],
            week:      () => [psDaysBack(6), psYmd(new Date())],
            month:     () => [monthStart(), psYmd(new Date())],
            all:       () => ['', ''],
        },
        initial: 'all',
    });
    const names = { today: 'Today', yesterday: 'Yesterday', week: 'Last 7 days', month: 'This month', all: 'All entries' };
    psPrintSetup('cl', {
        host:       document.querySelector('#companyCard table'),
        dates:      true,
        noun:       'entries',
        title:      'Print company ledger',
        period:     () => ({ from, to }),
        shownLabel: () => range === 'custom' ? (from === to ? psDMY(from) : psDMY(from) + ' – ' + psDMY(to)) : (names[range] || 'All entries'),
        searching:  () => q !== '',
        rows:       () => rows.map(tr => [Number(tr.dataset.pickId), tr.dataset.date]),
        url: ({ from: f, to: t, ids }) => {
            const p = new URLSearchParams({ print: 1 });
            if (ids && ids.length) p.set('items', ids.join(','));
            else { if (f) p.set('from', f); if (t) p.set('to', t); }
            return @json(route('role.party-statement.company', ['role' => $role])) + '?' + p;
        },
    });
    paint();
})();
</script>
@endsection
