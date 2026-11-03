@extends('layout.app')

@section('title', $meta['label'] . ' Ledger')

@section('main-content')
@php
    $role = Str::slug(Auth::user()->getRoleNames()->first());
    // The dashboard cards' accent for this type (dashboard.blade.php $accent).
    $accent = [
        'agent'  => ['bg' => '#fffbeb', 'fg' => '#b45309', 'bar' => '#f59e0b', 'icon' => 'fa-user-tie'],
        'client' => ['bg' => '#eff6ff', 'fg' => '#1d4ed8', 'bar' => '#3b82f6', 'icon' => 'fa-user'],
        'vendor' => ['bg' => '#f5f3ff', 'fg' => '#6d28d9', 'bar' => '#8b5cf6', 'icon' => 'fa-truck-field'],
    ][$type];
    $receivable = $meta['side'] === 'receivable';
    $owedLabel  = $receivable ? 'Receivable' : 'Payable';
    $fmt = fn ($v) => '৳' . number_format($v, 0);
@endphp

@if(empty($cfLedger) && empty($tkLedger) && empty($vaLedger))
<form method="GET" action="" class="mb-4 flex items-center justify-end gap-2 print-no-show">
    <label for="psCompany" class="text-xs font-semibold text-slate-500">Company</label>
    <select id="psCompany" name="company" onchange="this.form.submit()" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
        @if(Auth::user()->is_super_admin || Auth::user()->can('view all party statement'))<option value="">All Companies / Group</option>@endif
        @foreach($companies as $co)<option value="{{ $co->id }}" @selected((int)$companyId === (int)$co->id)>{{ $co->name }}</option>@endforeach
    </select>
    @if($companyId)
    <label for="psProject" class="text-xs font-semibold text-slate-500 ml-2">Project</label>
    <select id="psProject" name="project" onchange="this.form.submit()" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
        <option value="">All projects</option>
        @foreach($projects as $projectRow)<option value="{{ $projectRow->id }}" @selected((int)$projectId === (int)$projectRow->id)>{{ $projectRow->name }}</option>@endforeach
    </select>
    @endif
</form>
@endif

<style>
@media print {
    #sidebar, #mobileMenuButton, .header, .admin-footer, #commandPalette { display: none !important; }
    #mainContentWrapper { margin-left: 0 !important; }
    body, .min-h-screen { background: #fff !important; }
    main { padding: 0 !important; }
    #searchFilterBar, #placeholderArea, #ledgerActionBtns, #ledgerMeta, #partyListCard, .print-no-show { display: none !important; }
}

/* The party table, drawn as Manage Bank's Recent Transactions table
   (.txn-table in banks/dashboard.blade.php — same heading tint, padding and
   hairlines; copied, not shared, so one desk cannot move the other). */
.ps-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 16px rgba(0,0,0,0.06); }
.ps-list-wrap { max-height: 420px; overflow: auto; }
.ps-list { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.83rem; }
.ps-list thead th {
    position: sticky; top: 0; z-index: 1;
    background: #eef2ff; color: #4338ca; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.04em; font-size: 0.7rem; padding: 13px 12px; white-space: nowrap;
    border-bottom: 1px solid #c7d2fe; text-align: left;
}
.ps-list thead th[data-sort] { cursor: pointer; user-select: none; transition: background .15s, color .15s; }
.ps-list thead th[data-sort]:hover { background: #e0e7ff; color: #3730a3; }
.ps-list th .ps-sort { margin-left: 5px; opacity: 0; font-size: 0.62rem; transition: opacity .15s; }
.ps-list th[data-sort]:hover .ps-sort { opacity: .45; }
.ps-list th.is-sorted { color: #4f46e5; }
.ps-list th.is-sorted .ps-sort { opacity: 1; color: #4f46e5; }
.ps-list tbody td { padding: 11px 12px; border-bottom: 1px solid #b8c4d4; color: #334155; vertical-align: middle; }
.ps-list tbody tr:last-child td { border-bottom: none; }
.ps-list tbody tr { cursor: pointer; }
.ps-list tbody tr:hover td { background: #f8fafc; }
.ps-list tbody tr.is-open td { background: #eef2ff; }
.ps-list tbody tr.is-open td:first-child { box-shadow: inset 3px 0 0 #4f46e5; }
.ps-list tbody tr.is-hidden { display: none; }
.ps-list .ps-nm { font-weight: 600; color: #1e293b; }
.ps-list .ps-sub { font-size: 0.7rem; color: #94a3b8; margin-top: 1px; }
.ps-list .ps-id { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; letter-spacing: 0.02em; color: #4f46e5; font-weight: 700; }
.ps-list .ps-bal { font-family: monospace; font-weight: 700; white-space: nowrap; }
.ps-list .ps-none { color: #cbd5e1; }
.ps-list .ps-amt-dr { color: #dc2626; font-weight: 600; }
.ps-list .ps-amt-pay { color: #b45309; font-weight: 600; }
.ps-list .ps-amt-cr { color: #16a34a; font-weight: 600; }
/* The statement table itself is .ps-recent — partials/feed-table-styles. */

.ps-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 11px; font-weight: 700; color: #4f46e5; letter-spacing: .02em;
    background: #eef2ff; border-radius: 6px; padding: 2px 7px; white-space: nowrap;
}
.ps-avatar {
    width: 32px; height: 32px; border-radius: 9px; flex: none;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 800; color: #fff;
    background: linear-gradient(135deg, #3b82f6, #4f46e5);
}
.ps-stale {
    display: inline-flex; align-items: center; gap: 4px; padding: 1px 7px; border-radius: 20px;
    font-size: 9.5px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase;
    background: #fef3c7; color: #b45309;
}
.ps-seg { display: inline-flex; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #fff; }
.ps-seg button { border: 0; border-left: 1px solid #e2e8f0; background: none; padding: 0 11px; height: 32px; font-size: 11.5px; font-weight: 600; color: #64748b; cursor: pointer; white-space: nowrap; }
.ps-seg button:first-child { border-left: 0; }
.ps-seg button:hover { background: #f1f5f9; color: #334155; }
.ps-seg button.is-on { background: #eef2ff; color: #4338ca; font-weight: 700; }
.ps-list-count { font-size: 11px; font-weight: 700; color: #4338ca; background: #eef2ff; border-radius: 20px; padding: 4px 10px; white-space: nowrap; }
/* The title row's figures — the header band's four tiles, drawn on white. */
.ps-kpis { display: flex; align-items: stretch; gap: 6px; flex-wrap: wrap; }
/* Same height as the plain tiles were (4px padding, three lines); the icon
   and the tint add meaning, not room. --ps-tint / --ps-tint-fg come from the
   is-* modifier; neutral otherwise. */
.ps-kpi { --ps-tint: #f8fafc; --ps-tint-bd: #eef2f7; --ps-tint-fg: #475569; --ps-val: #0f172a;
    display: flex; align-items: center; gap: 9px; min-width: 128px; padding: 4px 12px 4px 8px; border-radius: 10px;
    background: var(--ps-tint); border: 1px solid var(--ps-tint-bd); text-align: right; }
.ps-kpi > div { flex: 1; }
.ps-kpi-ic { flex: none; width: 26px; height: 26px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 11px; background: #fff; color: var(--ps-tint-fg); border: 1px solid var(--ps-tint-bd); }
.ps-kpi.is-type  { --ps-tint: var(--ps-soft, #f8fafc); --ps-tint-bd: #e8edf4; --ps-tint-fg: var(--ps-fg); --ps-val: var(--ps-fg); }
.ps-kpi.is-owed  { --ps-tint: #fef2f2; --ps-tint-bd: #fecaca; --ps-tint-fg: #b91c1c; --ps-val: #dc2626; }
.ps-kpi.is-pay   { --ps-tint: #fffbeb; --ps-tint-bd: #fde68a; --ps-tint-fg: #b45309; --ps-val: #b45309; }
.ps-kpi.is-adv   { --ps-tint: #ecfdf5; --ps-tint-bd: #a7f3d0; --ps-tint-fg: #047857; --ps-val: #059669; }
.ps-kpi.is-stale { --ps-tint: #fffbeb; --ps-tint-bd: #fde68a; --ps-tint-fg: #b45309; --ps-val: #b45309; }
.ps-kpi-l { font-size: 9.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #94a3b8; white-space: nowrap; }
.ps-kpi-v { font-size: 15px; font-weight: 700; color: var(--ps-val); line-height: 1.2; margin-top: 1px; font-variant-numeric: tabular-nums; }
.ps-kpi-s { font-size: 10px; color: #94a3b8; margin-top: 1px; white-space: nowrap; }
/* The type's accent: a stripe along the panel's top and the title's chip —
   the dashboard cards' colours, so the tab and the card agree. */
.ps-panel-accent { position: relative; }
.ps-panel-accent::before { content: ""; position: absolute; left: 0; right: 0; top: 0; height: 3px; background: linear-gradient(90deg, var(--ps-bar), var(--ps-fg)); z-index: 1; }
.ps-title-ic { width: 26px; height: 26px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; color: #fff; background: linear-gradient(135deg, var(--ps-bar), var(--ps-fg)); }
.ps-roster-head { background: linear-gradient(135deg, #4338ca 0%, #5b4ce0 45%, #6d28d9 100%); color: #fff; flex-wrap: nowrap; min-width: 0; }
.ps-roster-head .ps-roster-title { color: #fff; white-space: nowrap; }
.ps-roster-head .ps-title-ic { background: rgba(255,255,255,.2); box-shadow: inset 0 0 0 1px rgba(255,255,255,.3); }
.ps-roster-head .ps-list-count { background: rgba(255,255,255,.18); color: #fff; }
.ps-roster-head .ps-kpis { flex-wrap: nowrap; min-width: 0; overflow: visible; padding: 2px; border-radius: 12px; background: rgba(25,17,112,.24); box-shadow: inset 0 2px 5px rgba(17,12,80,.28); }
.ps-roster-head .ps-kpis::-webkit-scrollbar { display: none; }
#partyListCard.ps-panel-accent::before { background: rgba(255,255,255,.22); }
.ps-roster-head .ps-kpi { --ps-tint: #fff; --ps-tint-bd: rgba(67,56,202,.12); flex: 0 0 auto; gap: 7px; padding: 2px 10px 2px 7px; border-radius: 9px; background: linear-gradient(145deg, #fff 0%, #f9faff 62%, #edf0f8 100%); box-shadow: 0 4px 7px rgba(15,10,82,.32), -1px -1px 2px rgba(255,255,255,.55), inset 0 2px 2px #fff, inset 0 -2px 3px rgba(88,91,132,.18); }
.ps-roster-head .ps-kpi-ic { width: 22px; height: 22px; border-radius: 7px; font-size: 10px; background: linear-gradient(145deg, #fff, #edf0f7); box-shadow: 1px 2px 3px rgba(51,51,99,.18), inset 1px 1px 1px #fff, inset -1px -1px 2px rgba(88,91,132,.14); }
.ps-roster-head .ps-kpi-l { font-size: 9px; line-height: 1.1; }
.ps-roster-head .ps-kpi-v { font-size: 13px; line-height: 1.15; }
.ps-roster-head .ps-kpi-s { font-size: 9px; line-height: 1.05; }
@media (max-width: 900px) { .ps-roster-head .ps-kpis { overflow-x: auto; overflow-y: hidden; scrollbar-width: none; } }
</style>

@if(!empty($cfLedger))
@include('contract-flights.partials.accounts-subtabs')
@elseif(!empty($tkLedger))
@include('dashboard.ticket_accounts.partials.subtabs')
@elseif(!empty($vaLedger))
@include('dashboard.visa_accounts.partials.subtabs')
@else
@include('layout.party-tabs')
@endif
@include('party-statement.partials.bar-styles')
@include('party-statement.partials.feed-table-styles')

<div class="min-h-screen bg-gray-50">

    <div class="px-2.5 py-2 space-y-2">

        {{-- ══ PARTY TABLE ══ --}}
        <div id="partyListCard" class="ps-panel ps-panel-accent" style="--ps-bar:{{ $accent['bar'] }};--ps-fg:{{ $accent['fg'] }};--ps-soft:{{ $accent['bg'] }}">
            <div class="ps-roster-head flex items-center justify-between gap-3 px-5 py-2 border-b border-gray-100">
                <div class="flex items-center gap-3 flex-wrap">
                    <p class="ps-roster-title text-sm font-bold flex items-center gap-2">
                        <span class="ps-title-ic"><i class="fas {{ $accent['icon'] }}"></i></span> {{ $meta['plural'] }}
                    </p>
                    <span class="ps-list-count" id="psListCount">{{ $summary['count'] }} {{ strtolower($meta['plural']) }}</span>
                </div>
                {{-- The figures the header band used to carry (owner, 2026-09-15:
                     "merge these two and make one") — the same four, on the
                     title row's right. --}}
                <div class="ps-kpis">
                    <div class="ps-kpi is-type">
                        <span class="ps-kpi-ic"><i class="fas {{ $accent['icon'] }}"></i></span>
                        <div>
                            <div class="ps-kpi-l">{{ $meta['plural'] }}</div>
                            <div class="ps-kpi-v">{{ $summary['count'] }}</div>
                            <div class="ps-kpi-s">{{ $summary['active'] }} with a ledger</div>
                        </div>
                    </div>
                    <div class="ps-kpi {{ $summary['owed'] > 0 ? ($receivable ? 'is-owed' : 'is-pay') : '' }}">
                        <span class="ps-kpi-ic"><i class="fas {{ $receivable ? 'fa-hand-holding-dollar' : 'fa-file-invoice-dollar' }}"></i></span>
                        <div>
                            <div class="ps-kpi-l">Total {{ $owedLabel }}</div>
                            <div class="ps-kpi-v">{{ $fmt($summary['owed']) }}</div>
                            <div class="ps-kpi-s">{{ $summary['owing'] }} {{ strtolower($meta['plural']) }} owing</div>
                        </div>
                    </div>
                    <div class="ps-kpi {{ $summary['advance'] > 0 ? 'is-adv' : '' }}">
                        <span class="ps-kpi-ic"><i class="fas fa-arrow-trend-up"></i></span>
                        <div>
                            <div class="ps-kpi-l">Advance</div>
                            <div class="ps-kpi-v">{{ $fmt($summary['advance']) }}</div>
                            <div class="ps-kpi-s">{{ $receivable ? 'paid ahead by them' : 'paid ahead by us' }}</div>
                        </div>
                    </div>
                    <div class="ps-kpi {{ $summary['stale'] > 0 ? 'is-stale' : '' }}">
                        <span class="ps-kpi-ic"><i class="fas fa-clock"></i></span>
                        <div>
                            <div class="ps-kpi-l">Needs follow-up</div>
                            <div class="ps-kpi-v">{{ $summary['stale'] }}</div>
                            <div class="ps-kpi-s">no movement in 60 days</div>
                        </div>
                    </div>
                </div>
            </div>
            {{-- The bank feed's bar. The segment picks which rows: all, only
                 those owing, only the stale ones, only those in advance. --}}
            @include('party-statement.partials.table-bar', [
                'prefix'      => 'pl',
                'placeholder' => 'Search ' . strtolower($meta['plural']) . ' by name, code, phone or contact…',
                'segments'    => [['all', 'All'], ['owing', $owedLabel . ' > 0'], ['stale', 'Needs follow-up'], ['advance', 'Advance']],
                'dates'       => false,
                'print'       => true,
                'typeFilter'  => \App\Http\Controllers\Dashboard\PartyStatementController::TYPE_FILTER,
            ])

            <div class="ps-list-wrap">
                <table class="w-full text-sm ps-list" id="partyList">
                    <thead>
                        <tr>
                            <th class="ps-pick"><input type="checkbox" class="ps-pick-box ps-pick-all" title="Tick every row shown" aria-label="Select all shown"></th>
                            <th data-sort="name">{{ $meta['label'] }}<i class="fas fa-sort ps-sort"></i></th>
                            <th>Contact</th>
                            <th>Type</th>
                            <th style="text-align:right;" data-sort="entries">Entries<i class="fas fa-sort ps-sort"></i></th>
                            <th data-sort="last">Last movement<i class="fas fa-sort ps-sort"></i></th>
                            <th style="text-align:right;" data-sort="owed">{{ $owedLabel }}<i class="fas fa-sort ps-sort"></i></th>
                            <th style="text-align:right;" data-sort="advance">Advance<i class="fas fa-sort ps-sort"></i></th>
                            <th style="text-align:right;" data-sort="net">Balance<i class="fas fa-sort ps-sort"></i></th>
                            <th style="width:36px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($parties as $p)
                        <tr data-party-id="{{ $p['id'] }}" data-pick-id="{{ $p['id'] }}"
                            data-name="{{ strtolower($p['name']) }}"
                            data-code="{{ $p['code'] }}"
                            data-entries="{{ $p['entries'] }}"
                            data-last="{{ $p['last_at'] ?? '' }}"
                            data-owed="{{ $p['owed'] }}"
                            data-advance="{{ $p['advance'] }}"
                            data-net="{{ $p['net'] }}"
                            data-stale="{{ $p['stale'] ? 1 : 0 }}"
                            data-kinds="{{ implode(' ', $p['kinds'] ?? []) }}"
                            data-hay="{{ strtolower($p['name'] . ' ' . $p['code'] . ' ' . ($p['phone'] ?? '') . ' ' . ($p['contact_person'] ?? '')) }}"
                            class="border-b border-gray-50"
                            onclick="openParty({{ $p['id'] }})">
                            <td class="ps-pick"><input type="checkbox" class="ps-pick-box" aria-label="Select this {{ strtolower($meta['label']) }}"></td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="min-w-0">
                                        <div class="ps-nm truncate">{{ $p['name'] }}</div>
                                        <div class="ps-sub">{{ $meta['label'] }} ID: <span class="ps-id">{{ $p['code'] }}</span></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($p['contact_person'])<div class="ps-nm">{{ $p['contact_person'] }}</div>@endif
                                <div class="{{ $p['contact_person'] ? 'ps-sub' : '' }}">{{ $p['phone'] ?: ($p['email'] ?: '—') }}</div>
                            </td>
                            <td>
                                @php
                                    $svc = $p['services'] ?? [];
                                    $kinds = $p['kinds'] ?? [];
                                    $tf = \App\Http\Controllers\Dashboard\PartyStatementController::TYPE_FILTER;
                                    [$tl, $ti, $tc] = $tf[count($kinds) > 1 ? 'multiple' : ($kinds[0] ?? 'none')] ?? $tf['others'];
                                @endphp
                                @if($svc)
                                <div class="ps-nm" style="white-space:nowrap;"><i class="fas {{ $ti }}" style="color:{{ $tc }};width:14px;margin-right:4px;"></i>{{ count($svc) > 1 ? 'Multiple' : $svc[0] }}</div>
                                @if(count($svc) > 1)<div class="ps-sub">{{ implode(', ', $svc) }}</div>@endif
                                @else<span class="ps-none">—</span>@endif
                            </td>
                            <td style="text-align:right;">{{ $p['entries'] ?: '—' }}</td>
                            <td style="white-space:nowrap;">
                                {{ $p['last_at'] ? \Carbon\Carbon::parse($p['last_at'])->format('d/m/Y') : '—' }}
                                @if($p['stale'])<span class="ps-stale ml-1"><i class="fas fa-clock"></i> stale</span>@endif
                            </td>
                            <td style="text-align:right;">@if($p['owed'] > 0)<span class="{{ $receivable ? 'ps-amt-dr' : 'ps-amt-pay' }}">{{ $fmt($p['owed']) }}</span>@else<span class="ps-none">—</span>@endif</td>
                            <td style="text-align:right;">@if($p['advance'] > 0)<span class="ps-amt-cr">{{ $fmt($p['advance']) }}</span>@else<span class="ps-none">—</span>@endif</td>
                            {{-- The signed balance, Dr/Cr, as the bank feed's Company
                                 Balance column: Dr is outstanding, Cr an advance. --}}
                            <td style="text-align:right;">@if($p['entries'])<span class="ps-bal" style="color:{{ $p['net'] < 0 ? '#dc2626' : '#1d4ed8' }};">{{ $fmt(abs($p['net'])) }} <small>{{ $p['net'] < 0 ? 'Cr' : 'Dr' }}</small></span>@else<span class="ps-none">—</span>@endif</td>
                            <td style="text-align:center;color:#cbd5e1;"><i class="fas fa-chevron-right text-xs"></i></td>
                        </tr>
                        @empty
                        <tr><td colspan="9" style="text-align:center;color:#94a3b8;padding:1.5rem;">No {{ strtolower($meta['plural']) }} on file.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div id="psListEmpty" class="hidden px-5 py-8 text-center text-sm text-gray-400">No {{ strtolower($meta['plural']) }} match.</div>
        </div>

        @include('party-statement.partials.statement')

    </div>
</div>

@if(!empty($cfLedger))
@include('contract-flights.partials.accounts-due')
@elseif(!empty($tkLedger))
@include('dashboard.ticket_accounts.partials.due')
@elseif(!empty($vaLedger))
@include('dashboard.visa_accounts.partials.due')
@endif
@include('party-statement.partials.modals')
@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script>
@include('party-statement.partials.bar-scripts')
@include('party-statement.partials.scripts')

// ── The party table: filter, segment, sort ─────────────────────────────────────
// All client-side over the rows already rendered — the roster is dozens of
// rows, not thousands, and the balances were computed once on the server.
(function () {
    const table = document.getElementById('partyList');
    if (!table) return;
    const rows  = Array.from(table.querySelectorAll('tbody tr[data-party-id]'));
    const count = document.getElementById('psListCount');
    const empty = document.getElementById('psListEmpty');
    const plural = @json(strtolower($meta['plural']));
    let show = 'all', q = '', kind = '';
    let sort = { key: 'owed', dir: 'desc' };

    const passes = tr => {
        if (q && !tr.dataset.hay.includes(q)) return false;
        if (kind) {
            const ks = tr.dataset.kinds ? tr.dataset.kinds.split(' ') : [];
            if (kind === 'multiple' ? ks.length < 2 : kind === 'none' ? ks.length : !ks.includes(kind)) return false;
        }
        if (show === 'owing')   return Number(tr.dataset.owed) > 0;
        if (show === 'stale')   return tr.dataset.stale === '1';
        if (show === 'advance') return Number(tr.dataset.advance) > 0;
        return true;
    };
    const value = (tr, key) => {
        if (key === 'name' || key === 'code' || key === 'last') return tr.dataset[key] || '';
        return Number(tr.dataset[key]) || 0;
    };
    const paint = () => {
        let shown = 0;
        rows.forEach(tr => { const ok = passes(tr); tr.classList.toggle('is-hidden', !ok); if (ok) shown++; });
        count.textContent = (shown === rows.length ? shown : shown + ' of ' + rows.length) + ' ' + plural;
        psPaintCount('pl', q !== '', shown, rows.length);
        empty.classList.toggle('hidden', shown > 0);

        const dir = sort.dir === 'asc' ? 1 : -1;
        const sorted = rows.slice().sort((a, b) => {
            const av = value(a, sort.key), bv = value(b, sort.key);
            if (av < bv) return -dir;
            if (av > bv) return dir;
            return a.dataset.name < b.dataset.name ? -1 : 1;   // stable, by name
        });
        const tbody = table.querySelector('tbody');
        sorted.forEach(tr => tbody.appendChild(tr));

        table.querySelectorAll('th[data-sort]').forEach(th => {
            const on = th.dataset.sort === sort.key;
            th.classList.toggle('is-sorted', on);
            const i = th.querySelector('.ps-sort');
            i.className = 'fas ps-sort ' + (on ? (sort.dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort');
        });
    };

    psInitBar('pl', {
        onSearch: term => { q = term; paint(); },
        onRange:  key  => { show = key; paint(); },
        initial: 'all',
    });
    // Type dropdown in the search box
    const kindBox = document.getElementById('plKind');
    if (kindBox) {
        const btn = kindBox.querySelector('.pb-kind-btn');
        btn.addEventListener('click', e => { e.stopPropagation(); kindBox.classList.toggle('is-open'); });
        document.addEventListener('click', e => { if (!kindBox.contains(e.target)) kindBox.classList.remove('is-open'); });
        kindBox.querySelectorAll('.pb-kind-menu button').forEach(b => b.addEventListener('click', () => {
            kind = b.dataset.kind;
            btn.firstElementChild.className = 'fas ' + b.dataset.icon;
            btn.firstElementChild.style.color = b.dataset.color;
            btn.children[1].textContent = b.textContent.trim();
            kindBox.querySelectorAll('.pb-kind-menu button').forEach(x => x.classList.toggle('is-sel', x === b));
            kindBox.classList.toggle('is-on', !!kind);
            kindBox.classList.remove('is-open');
            paint();
        }));
    }
    // Print — the chooser without the day/range choices (a roster has no
    // period): the ticked parties, or what the table shows (the segment,
    // or the search's rows).
    const segNames = { all: 'all ' + plural, owing: plural + ' owing', stale: plural + ' needing follow-up', advance: plural + ' in advance' };
    psPrintSetup('pl', {
        host:       table,
        dates:      false,
        noun:       plural,
        title:      'Print ' + plural,
        period:     () => ({ show }),
        shownLabel: () => segNames[show] || segNames.all,
        searching:  () => q !== '',
        url: ({ ids, show: seg }) => {
            const p = new URLSearchParams();
            p.set('type', @json($type));
            @if(! empty($deptScope)) p.set('dept', @json($deptScope)); @endif
            if (ids && ids.length) p.set('ids', ids.join(','));
            else { p.set('show', seg || 'all'); if (kind) p.set('kind', kind); }
            return @json(route('role.party-statement.parties-print', ['role' => $role, 'company' => request('company')])) + (@json(request('company') ? '&' : '?')) + p;
        },
    });
    table.querySelectorAll('th[data-sort]').forEach(th => th.addEventListener('click', () => {
        const key = th.dataset.sort;
        if (sort.key === key) sort.dir = sort.dir === 'asc' ? 'desc' : 'asc';
        else sort = { key, dir: (key === 'name' || key === 'code') ? 'asc' : 'desc' };
        paint();
    }));

    paint();
})();
</script>
@endsection
