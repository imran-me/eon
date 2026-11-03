@extends('layout.app')
@section('meta-information')<title>Visa Dashboard</title>@endsection
@section('css')
@include('party-statement.partials.bar-styles')
<style>
    /* Visa Dashboard — the Flight Dashboard's banner and card, vd- prefixed so
       nothing here reaches another page. Values copied from
       contract-flights/dashboard (.fd-banner / .fd-stats / .fd-card). */
    .vd-banner { background: linear-gradient(135deg, #4f46e5 0%, #6366f1 55%, #7c3aed 100%); border-radius: 16px; padding: .55rem .9rem; color: #fff;
        display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: .75rem; position: relative; overflow: hidden; }
    .vd-banner::before { content: ''; position: absolute; inset: 0; pointer-events: none;
        background: radial-gradient(120% 150% at 88% -25%, rgba(255,255,255,.20), transparent 58%), radial-gradient(90% 130% at -5% 125%, rgba(255,255,255,.12), transparent 55%); }
    .vd-banner > * { position: relative; z-index: 1; }
    .vd-stats { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.16);
        border-radius: 12px; padding: 7px 14px; box-shadow: inset 0 1px 0 rgba(255,255,255,.12); }
    .vd-stat + .vd-stat { border-left: 1px solid rgba(255,255,255,.22); padding-left: 12px; }
    .vd-stat .val { font-size: 1.05rem; font-weight: 700; line-height: 1.25; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .vd-stat .lbl { font-size: .58rem; opacity: .78; text-transform: uppercase; letter-spacing: .07em; white-space: nowrap; }
    .vd-stat.warn .val { color: #fde68a; }
    .vd-actions { display: flex; align-items: center; gap: 8px; }
    .vd-count { background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.22); border-radius: 9px; padding: 5px 10px; font-size: .75rem; font-weight: 700; }
    .vd-add { display: inline-flex; align-items: center; gap: 6px; background: #fff; color: #4338ca; border-radius: 9px; padding: 6px 12px; font-size: .78rem; font-weight: 700; text-decoration: none; }
    .vd-add:hover { background: #eef2ff; color: #3730a3; text-decoration: none; }

    .vd-filters { display: flex; align-items: flex-end; gap: 8px; flex-wrap: nowrap; overflow-x: auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 12px; margin-bottom: .75rem; }
    .vd-field { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
    .vd-field label { font-size: 11px; font-weight: 600; color: #475569; }
    .vd-field input, .vd-field select { height: 32px; border: 1px solid #dce2eb; border-radius: 8px; background: #fff; font-size: 12px; color: #334155; padding: 0 9px; box-sizing: border-box; }
    .vd-field input:focus, .vd-field select:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.12); }
    .vd-search { position: relative; flex: 1 1 170px; min-width: 150px; }
    #vdCountry { width: 130px; } #vdAgent { width: 150px; }
    .vd-chip { padding: 0 9px; }
    .vd-search i { position: absolute; left: 10px; bottom: 10px; color: #94a3b8; font-size: 11px; }
    .vd-search input { width: 100%; padding-left: 28px; }
    .vd-chips { display: inline-flex; gap: 4px; flex-wrap: nowrap; }
    .vd-filters > .vd-field { flex: 0 0 auto; }
    .vd-filters > .vd-search { flex: 1 1 150px; }
    .vd-chip { height: 32px; padding: 0 11px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; color: #475569; font-size: 11.5px; font-weight: 600; cursor: pointer; white-space: nowrap; }
    .vd-chip:hover { background: #f8fafc; }
    .vd-chip.is-on { background: #4f46e5; border-color: #4f46e5; color: #fff; }
    .vd-chip .n { margin-left: 4px; opacity: .7; }

    /* ── Card wall ── */
    #vdGrid { display: grid; grid-template-columns: repeat(auto-fill, minmax(196px, 1fr)); gap: .5rem; margin-bottom: 1rem; }
    .vd-card { --tint-rgb: 100, 116, 139; --tint: #64748b; position: relative; display: block; overflow: hidden; border: 1px solid rgba(15,23,42,.20); border-radius: 14px;
        background: radial-gradient(135% 95% at 100% 0%, rgba(var(--tint-rgb), .10), transparent 56%), #fff;
        padding: 9px 11px 0 13px; box-shadow: 0 1px 2px rgba(7,12,30,.06), 0 3px 10px -5px rgba(7,12,30,.10); color: inherit; text-decoration: none;
        transition: transform .18s, box-shadow .18s; }
    .vd-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px -8px rgba(7,12,30,.25); text-decoration: none; color: inherit; }
    .vd-card.is-late { border-color: rgba(220,38,38,.45); background: radial-gradient(135% 95% at 100% 0%, rgba(220,38,38,.12), transparent 56%), #fff; }
    .vd-head { display: flex; gap: 9px; align-items: flex-start; }
    .vd-flag { flex: 0 0 44px; height: 44px; border-radius: 11px; background: #fff; box-shadow: 0 1px 2px rgba(15,23,42,.08), 0 4px 10px -4px rgba(15,23,42,.18);
        display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .vd-flag img { width: 32px; height: auto; border-radius: 3px; }
    .vd-flag i { color: #94a3b8; }
    .vd-id { min-width: 0; flex: 1; }
    .vd-title { font-size: 13px; font-weight: 700; color: #0f172a; line-height: 1.25; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .vd-sub { font-size: 11px; color: #64748b; line-height: 1.4; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .vd-sub .ref { color: #4f46e5; font-weight: 700; }
    .vd-sub .st { font-weight: 700; color: var(--tint); }
    .vd-who { font-size: 11px; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 1px; }
    .vd-who i { color: #94a3b8; font-size: 9.5px; width: 11px; }
    .vd-date { display: flex; align-items: baseline; justify-content: space-between; gap: 6px; margin: 9px 0 9px; }
    .vd-date .d { font-size: 20px; font-weight: 700; color: #111827; line-height: 1.2; white-space: nowrap; }
    .vd-date .d small { font-size: 9.5px; font-weight: 700; color: #8b95a5; letter-spacing: .04em; margin-left: 4px; text-transform: uppercase; }
    .vd-card.is-late .vd-date .d { color: #dc2626; }
    .vd-pill { flex: 0 0 auto; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 999px; background: #f1f5f9; color: #475569; white-space: nowrap; }
    .vd-pill.late { background: #fee2e2; color: #b91c1c; }
    .vd-pill.soon { background: #fef3c7; color: #b45309; }
    .vd-pill.ok { background: #dcfce7; color: #15803d; }
    .vd-strip { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); margin: 0 -11px 0 -13px; border-top: 1px solid #eef1f6; background: rgba(248,250,252,.7); }
    .vd-strip .cell { padding: 6px 4px 8px; text-align: center; min-width: 0; }
    .vd-strip .cell + .cell { border-left: 1px solid #eef1f6; }
    .vd-strip .v { font-size: 12.5px; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .vd-strip .l { font-size: 9px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .06em; }
    .vd-strip .v.due { color: #b45309; } .vd-strip .v.none { color: #94a3b8; font-weight: 600; font-size: 11px; }
    .vd-empty { grid-column: 1 / -1; padding: 30px; text-align: center; color: #94a3b8; background: #fff; border: 1px dashed #e2e8f0; border-radius: 14px; }

    /* ── Finished table ── */
    .vd-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
    .vd-panel-h { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: linear-gradient(90deg, #4f46e5 0%, #6d28d9 100%); }
    .vd-panel-h h3 { margin: 0; font-size: 13.5px; font-weight: 800; color: #fff; }
    .vd-panel-h small { color: #c7d2fe; font-size: 11.5px; }
    .vd-table { width: 100%; border-collapse: collapse; font-size: .8rem; }
    .vd-table th { background: #eef2ff; color: #4338ca; padding: 8px 10px; text-align: left; font-size: .6rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; border-bottom: 1px solid #c7d2fe; }
    .vd-table td { padding: 7px 10px; border-bottom: 1px solid #eef1f6; color: #334155; vertical-align: top; }
    .vd-table tbody tr:hover td { background: #f8faff; }
    .vd-table td small { display: block; color: #94a3b8; font-size: .7rem; }
    .vd-table .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .vd-table .b { font-weight: 600; color: #1e293b; }
    .vd-table a { color: #4f46e5; font-weight: 600; text-decoration: none; }
    .vd-table .good { color: #047857; font-weight: 600; } .vd-table .bad { color: #dc2626; font-weight: 600; } .vd-table .due { color: #b45309; font-weight: 600; }
    .vd-st { font-weight: 700; }
    @media print { .vd-banner, .vd-filters, #vdGrid, .pb-bar, #sidebar, header, .ex-toolbar { display: none !important; } .vd-panel { border: 0; } }
</style>
@endsection
@section('main-content')
@php
    $m = fn ($v) => '৳' . number_format((float) $v, 0);
    $short = fn ($v) => $v >= 100000 ? '৳' . number_format($v / 100000, 1) . 'L' : ($v >= 1000 ? '৳' . number_format($v / 1000, 1) . 'K' : '৳' . number_format($v, 0));
    $safeRoute = function ($name, $params = []) use ($role) {
        try { return \Illuminate\Support\Facades\Route::has($name) ? route($name, ['role' => $role] + $params) : null; } catch (\Throwable) { return null; }
    };
    $boardUrl = $safeRoute('role.visa.index');
    $saleUrl = fn ($id) => $safeRoute('role.visa-sales.show', ['visaSale' => $id]);
    $partyUrl = $safeRoute('role.party-statement.index');
    $flag = fn ($cc) => $cc ? asset('flags/' . $cc . '.png') : null;
    $chips = ['all' => 'All'] + collect($statuses)->except(\App\Http\Controllers\Dashboard\VisaDashboardController::FINAL)->map(fn ($s) => ['In Embassy' => 'Embassy', 'In Progress' => 'Progress', 'Documents' => 'Docs'][$s[0]] ?? $s[0])->all();
@endphp

@include('layout.visa-processing-tabs')

<div class="vd-banner">
    <div class="vd-stats">
        <div class="vd-stat"><div class="val">{{ $kpis['open'] }}</div><div class="lbl">Open</div></div>
        <div class="vd-stat"><div class="val">{{ $kpis['in_embassy'] }}</div><div class="lbl">In embassy</div></div>
        <div class="vd-stat {{ $kpis['overdue'] ? 'warn' : '' }}" title="Open applications past their expected decision date"><div class="val">{{ $kpis['overdue'] }}</div><div class="lbl">Overdue</div></div>
        <div class="vd-stat"><div class="val">{{ $kpis['approved'] }}</div><div class="lbl">Approved this month</div></div>
        <div class="vd-stat"><div class="val">{{ $kpis['rejected'] }}</div><div class="lbl">Rejected</div></div>
        <div class="vd-stat" title="Unpaid on Manage Sales invoices"><div class="val">{{ $short($kpis['agent_due']) }}</div><div class="lbl">Agent due</div></div>
        <div class="vd-stat" title="Application costs not yet paid to the vendor"><div class="val">{{ $short($kpis['vendor_due']) }}</div><div class="lbl">Vendor due</div></div>
    </div>
    <div class="vd-actions">
        <span class="vd-count" id="vdCount">{{ $open->count() }} of {{ $open->count() }} open</span>
        @if($boardUrl)<a class="vd-add" href="{{ $boardUrl }}?open=create"><i class="fas fa-plus"></i> New Application</a>@endif
    </div>
</div>

<div class="vd-filters">
    <div class="vd-field vd-search"><label for="vdSearch">Search</label><i class="fas fa-search"></i><input type="search" id="vdSearch" placeholder="Applicant, passport, VISA no, agent…" autocomplete="off"></div>
    <div class="vd-field"><label for="vdCountry">Country</label>
        <select id="vdCountry"><option value="">All countries</option>@foreach($countries as $cc => $name)<option value="{{ strtolower($cc) }}">{{ $name }}</option>@endforeach</select></div>
    <div class="vd-field"><label for="vdAgent">Agent</label>
        <select id="vdAgent"><option value="">All agents</option><option value="__none">Not invoiced yet</option>@foreach($agents as $a)<option value="{{ $a }}">{{ $a }}</option>@endforeach</select></div>
    <div class="vd-field"><label>Status</label>
        <div class="vd-chips" id="vdStatus">@foreach($chips as $k => $l)<button type="button" class="vd-chip {{ $k === 'all' ? 'is-on' : '' }}" data-v="{{ $k }}">{{ $l }}<span class="n">{{ $k === 'all' ? $open->count() : $open->where('status', $k)->count() }}</span></button>@endforeach</div></div>
    <div class="vd-field"><label>Expected</label>
        <div class="vd-chips" id="vdWhen">@foreach(['all' => 'Any', 'overdue' => 'Overdue', 'week' => 'This week'] as $k => $l)<button type="button" class="vd-chip {{ $k === 'all' ? 'is-on' : '' }}" data-v="{{ $k }}">{{ $l }}</button>@endforeach</div></div>
</div>

<div id="vdGrid">
    @forelse($open as $a)
        @php
            [$sl, $sc, $srgb] = $statuses[$a['status']] ?? [ucfirst($a['status']), '#64748b', '100, 116, 139'];
            $exp = $a['expected'] ? \Illuminate\Support\Carbon::parse($a['expected']) : null;
            $pill = $a['to_go'] === null ? ['No date', ''] : ($a['to_go'] < 0 ? [abs($a['to_go']) . 'd overdue', 'late'] : ($a['to_go'] === 0 ? ['today', 'soon'] : ($a['to_go'] <= 3 ? 'in ' . $a['to_go'] . 'd' : 'in ' . $a['to_go'] . 'd')));
            if (! is_array($pill)) $pill = [$pill, $a['to_go'] <= 3 ? 'soon' : 'ok'];
        @endphp
        <a class="vd-card {{ $a['overdue'] ? 'is-late' : '' }}" style="--tint:{{ $sc }};--tint-rgb:{{ $srgb }}"
           href="{{ $boardUrl ? $boardUrl . '?passport_holder_id=' . $a['holder_id'] : '#' }}"
           data-search="{{ strtolower(implode(' ', [$a['ref'], $a['applicant'], $a['passport_no'], $a['country'], $a['category'], $a['agent'], $a['invoice'], $a['officer']])) }}"
           data-country="{{ $a['country_code'] }}" data-agent="{{ $a['agent'] ?? '__none' }}" data-status="{{ $a['status'] }}"
           data-expected="{{ $a['expected'] }}" data-togo="{{ $a['to_go'] ?? '' }}"
           title="{{ $a['ref'] }} · {{ $a['applicant'] }} · {{ $a['country'] }} — open the board">
            <div class="vd-head">
                <span class="vd-flag">@if($f = $flag($a['country_code']))<img src="{{ $f }}" alt="{{ $a['country'] }}" loading="lazy" onerror="this.replaceWith(Object.assign(document.createElement('i'),{className:'fas fa-passport'}))">@else<i class="fas fa-passport"></i>@endif</span>
                <div class="vd-id">
                    <div class="vd-title" title="{{ $a['country'] }} · {{ $a['category'] }}">{{ $a['country'] ?: 'Country not set' }} · {{ $a['category'] }}</div>
                    <div class="vd-sub"><span class="ref">{{ $a['ref'] }}</span> · <span class="st">{{ $sl }}</span>@if($a['stage']) · {{ $a['stage'] }}@endif</div>
                    <div class="vd-who" title="{{ $a['applicant'] }} {{ $a['passport_no'] }}"><i class="fas fa-user"></i> {{ $a['applicant'] ?: '—' }}</div>
                    <div class="vd-who" title="{{ $a['agent'] ? 'Billed to ' . $a['agent'] . ' on ' . $a['invoice'] : 'Not on any invoice yet' }}"><i class="fas fa-user-tie"></i> {{ $a['agent'] ?: 'Not invoiced' }}</div>
                </div>
            </div>
            <div class="vd-date" title="{{ $exp ? 'Expected decision' : 'No processing time on the category and no travel date' }}">
                <span class="d">{{ $exp ? $exp->format('j M') : '—' }}@if($exp && $exp->year != now()->year)<small>{{ $exp->year }}</small>@endif<small>expected</small></span>
                <span class="vd-pill {{ $pill[1] }}">{{ $pill[0] }}</span>
            </div>
            <div class="vd-strip">
                <div class="cell" title="Days since the application was opened"><div class="v">{{ $a['in_process'] }}d</div><div class="l">In process</div></div>
                <div class="cell" title="{{ $a['billed'] ? 'Invoiced on ' . $a['invoice'] : 'Sale price on the application — not invoiced yet' }}"><div class="v">{{ $short($a['charge']) }}</div><div class="l">Charge</div></div>
                <div class="cell" title="{{ $a['billed'] ? 'Unpaid on ' . $a['invoice'] . ' (its share)' : 'Not invoiced yet' }}">
                    @if($a['billed'])<div class="v {{ $a['due'] > 0 ? 'due' : '' }}">{{ $a['due'] > 0 ? $short($a['due']) : 'Paid' }}</div>@else<div class="v none">Not billed</div>@endif
                    <div class="l">Due</div></div>
            </div>
        </a>
    @empty
        <div class="vd-empty"><i class="fas fa-passport"></i> No open applications.</div>
    @endforelse
    <div class="vd-empty" id="vdNone" hidden>No open application matches these filters.</div>
</div>

<section class="vd-panel">
    <div class="vd-panel-h"><h3>Finished applications</h3><small>Approved or rejected · newest first</small></div>
    @include('party-statement.partials.table-bar', [
        'prefix' => 'vf', 'placeholder' => 'Search applicant, passport, application, country or agent…',
        'segments' => [['all', 'All'], ['approved', 'Approved'], ['rejected', 'Rejected']],
        'dates' => true, 'print' => true,
    ])
    <div style="overflow-x:auto"><table class="vd-table" id="vfTable">
        <thead><tr><th>Decided</th><th>Application</th><th>Applicant</th><th>Visa</th><th>Agent</th><th class="num">Charge</th><th class="num">Cost</th><th class="num">Profit</th><th class="num">Due</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($done as $a)
            @php [$sl, $sc] = $statuses[$a['status']] ?? [ucfirst($a['status']), '#64748b']; @endphp
            <tr data-search="{{ strtolower(implode(' ', [$a['ref'], $a['applicant'], $a['passport_no'], $a['country'], $a['category'], $a['agent'], $a['invoice']])) }}" data-status="{{ $a['status'] }}" data-date="{{ $a['decided'] }}">
                <td>{{ \Illuminate\Support\Carbon::parse($a['decided'])->format('d M Y') }}<small>{{ $a['in_process'] }} days</small></td>
                <td class="b">@if($boardUrl)<a href="{{ $boardUrl }}?passport_holder_id={{ $a['holder_id'] }}">{{ $a['ref'] }}</a>@else{{ $a['ref'] }}@endif<small>{{ \Illuminate\Support\Carbon::parse($a['opened'])->format('d M Y') }}</small></td>
                <td class="b">{{ $a['applicant'] ?: '—' }}<small>{{ $a['passport_no'] }}</small></td>
                <td>{{ $a['country'] ?: '—' }}<small>{{ $a['category'] }}</small></td>
                <td>@if($a['agent']){{ $a['agent'] }}<small>@if($u = $saleUrl($a['sale_id']))<a href="{{ $u }}">{{ $a['invoice'] }}</a>@else{{ $a['invoice'] }}@endif</small>@else<span style="color:#94a3b8">Not invoiced</span>@endif</td>
                <td class="num">{{ $m($a['charge']) }}</td>
                <td class="num">{{ $m($a['cost']) }}</td>
                <td class="num {{ $a['profit'] < 0 ? 'bad' : 'good' }}">{{ $m($a['profit']) }}</td>
                <td class="num">@if(! $a['billed'])<span style="color:#94a3b8">—</span>@elseif($a['due'] > 0)<span class="due">{{ $m($a['due']) }}</span>@else<span class="good">Paid</span>@endif</td>
                <td><span class="vd-st" style="color:{{ $sc }}">{{ $sl }}</span></td>
            </tr>
        @empty
            <tr><td colspan="10" style="text-align:center;color:#94a3b8;padding:24px">No finished applications yet.</td></tr>
        @endforelse
        <tr id="vfNone" hidden><td colspan="10" style="text-align:center;color:#94a3b8;padding:24px">No finished application matches.</td></tr>
        </tbody>
    </table></div>
</section>
@endsection
@section('script')
<script>
@include('party-statement.partials.bar-scripts')
// ── Card wall filters ──
(function () {
    const cards = [...document.querySelectorAll('#vdGrid .vd-card')];
    const st = { q: '', country: '', agent: '', status: 'all', when: 'all', from: '', to: '' };
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const iso = d => d.toISOString().slice(0, 10);
    const weekEnd = iso(new Date(today.getTime() + (6 - ((today.getDay() + 6) % 7)) * 86400000));
    function apply() {
        let n = 0;
        cards.forEach(c => {
            const d = c.dataset, togo = d.togo === '' ? null : +d.togo;
            let ok = (!st.q || d.search.includes(st.q)) && (!st.country || d.country === st.country) && (!st.agent || d.agent === st.agent)
                && (st.status === 'all' || d.status === st.status);
            if (ok && st.when === 'overdue') ok = togo !== null && togo < 0;
            if (ok && st.when === 'today') ok = togo === 0;
            if (ok && st.when === 'week') ok = togo !== null && togo >= 0 && d.expected <= weekEnd;
            if (ok && st.from) ok = d.expected && d.expected >= st.from;
            if (ok && st.to) ok = d.expected && d.expected <= st.to;
            c.hidden = !ok; if (ok) n++;
        });
        document.getElementById('vdNone').hidden = n > 0 || cards.length === 0;
        document.getElementById('vdCount').textContent = n + ' of ' + cards.length + ' open';
    }
    document.getElementById('vdSearch').addEventListener('input', e => { st.q = e.target.value.trim().toLowerCase(); apply(); });
    document.getElementById('vdCountry').addEventListener('change', e => { st.country = e.target.value; apply(); });
    document.getElementById('vdAgent').addEventListener('change', e => { st.agent = e.target.value; apply(); });
    [['vdStatus', 'status'], ['vdWhen', 'when']].forEach(([id, key]) => {
        const box = document.getElementById(id);
        box.querySelectorAll('.vd-chip').forEach(b => b.addEventListener('click', () => {
            st[key] = b.dataset.v; box.querySelectorAll('.vd-chip').forEach(x => x.classList.toggle('is-on', x === b)); apply();
        }));
    });
})();

// ── Finished table: the standard bar (search · segment · dates · print) ──
(function () {
    const rows = [...document.querySelectorAll('#vfTable tbody tr[data-status]')];
    const st = { q: '', seg: 'all', from: '', to: '' };
    function apply() {
        let n = 0;
        rows.forEach(r => {
            const d = r.dataset;
            const ok = (!st.q || d.search.includes(st.q)) && (st.seg === 'all' || d.status === st.seg)
                && (!st.from || d.date >= st.from) && (!st.to || d.date <= st.to);
            r.hidden = !ok; if (ok) n++;
        });
        document.getElementById('vfNone').hidden = n > 0 || rows.length === 0;
    }
    const s = document.getElementById('vfSearch'), c = document.getElementById('vfClear');
    s.addEventListener('input', () => { st.q = s.value.trim().toLowerCase(); c.style.display = st.q ? 'block' : 'none'; apply(); });
    c.addEventListener('click', () => { s.value = ''; st.q = ''; c.style.display = 'none'; apply(); });
    const seg = document.getElementById('vfSeg');
    const paint = () => seg.querySelectorAll('button').forEach(b => b.classList.toggle('is-on', b.dataset.range === st.seg));
    seg.querySelectorAll('button').forEach(b => b.addEventListener('click', () => { st.seg = b.dataset.range; paint(); apply(); }));
    paint();
    const ft = document.getElementById('vfFromText'), tt = document.getElementById('vfToText');
    [['From', ft], ['To', tt]].forEach(([k, txt]) => document.getElementById('vf' + k).addEventListener('change', e => { txt.value = psDMY(e.target.value); psMarkBad(txt, false); }));
    document.getElementById('vfApply').addEventListener('click', () => {
        const f = psParseDate(ft.value), t = psParseDate(tt.value);
        psMarkBad(ft, f === null); psMarkBad(tt, t === null);
        if (f === null || t === null) return;
        [st.from, st.to] = f && t && f > t ? [t, f] : [f, t]; apply();
    });
    document.getElementById('vfPrint')?.addEventListener('click', e => { e.preventDefault(); window.print(); });
})();
</script>
@endsection
