@extends('layout.app')

@section('meta-information')
<title>Operations Status Tracker</title>
@endsection

@section('css')
<style>
    .ost-shell { --ost-blue:#2563eb; --ost-line:#e2e8f0; --ost-ink:#0f172a; --ost-muted:#64748b; }
    .ost-page-header { display:flex; align-items:center; justify-content:space-between; gap:18px; padding:20px; overflow:hidden; color:#fff; border:1px solid #1e3a8a; border-radius:14px; background:linear-gradient(105deg,#0f172a 0%,#172554 54%,#1e40af 100%); box-shadow:0 2px 5px rgba(15,23,42,.14); }
    .ost-page-header-copy { display:flex; align-items:center; gap:12px; min-width:0; }
    .ost-page-header-actions { display:flex; align-items:center; gap:8px; flex:0 0 auto; }
    .ost-live-pill { display:inline-flex; align-items:center; gap:7px; min-height:34px; padding:0 12px; color:#d1fae5; background:rgba(5,150,105,.28); border:1px solid rgba(110,231,183,.5); border-radius:999px; font-size:10px; font-weight:800; white-space:nowrap; }
    .ost-live-dot { width:8px; height:8px; flex:0 0 auto; border-radius:50%; background:#34d399; box-shadow:0 0 0 3px rgba(52,211,153,.18); }
    .ost-refresh { width:34px; height:34px; display:grid; flex:0 0 34px; place-items:center; color:#fff; background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.3); border-radius:9px; transition:.15s ease; }
    .ost-refresh:hover { background:rgba(255,255,255,.27); transform:rotate(12deg); }
    .ost-card { background:#fff; border:1px solid var(--ost-line); border-radius:14px; box-shadow:0 1px 3px rgba(15,23,42,.04); }
    .ost-kpis { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:12px; }
    .ost-kpi { display:block; padding:15px 16px; min-width:0; }
    /* Three of the five cards are a filter you can press. */
    a.ost-kpi { transition:.15s; }
    a.ost-kpi:hover { border-color:#93c5fd; box-shadow:0 3px 10px rgba(37,99,235,.10); transform:translateY(-1px); }
    .ost-kpi-label { display:flex; align-items:center; justify-content:space-between; gap:8px; color:#64748b; font-size:10px; font-weight:800; letter-spacing:.035em; }
    .ost-kpi-icon { width:28px; height:28px; border-radius:8px; display:grid; place-items:center; background:#f1f5f9; }
    .ost-kpi-value { margin:9px 0 4px; font-size:25px; line-height:1; font-weight:900; letter-spacing:-.03em; color:#0f172a; }
    .ost-kpi-note { color:#94a3b8; font-size:10px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .ost-danger { border-color:#fecaca; } .ost-danger .ost-kpi-value { color:#dc2626; } .ost-danger .ost-kpi-icon { color:#dc2626; background:#fef2f2; }
    .ost-warning { border-color:#fde68a; } .ost-warning .ost-kpi-value { color:#b45309; } .ost-warning .ost-kpi-icon { color:#b45309; background:#fffbeb; }
    .ost-tabs { display:flex; gap:4px; padding:3px; background:#f1f5f9; border-radius:10px; }
    .ost-tab { padding:8px 12px; border-radius:8px; color:#64748b; font-size:11px; font-weight:800; white-space:nowrap; }
    .ost-tab.active { color:#1d4ed8; background:#fff; box-shadow:0 1px 3px rgba(15,23,42,.14); }
    .ost-control { height:36px; border:1px solid #dbe3ee; border-radius:9px; background:#fff; color:#334155; padding:0 10px; font-size:11px; outline:none; }
    .ost-control:focus { border-color:#93c5fd; box-shadow:0 0 0 3px #dbeafe; }
    /* ── Filter bar ───────────────────────────────────────────────────────── */
    .ost-bar { display:flex; align-items:center; gap:9px; flex-wrap:wrap; padding:10px 13px; background:#f8fafc; border-bottom:1px solid var(--ost-line); }
    .ost-bar-2 { background:#fff; }
    .ost-bar-spacer { flex:1 1 auto; }
    .ost-search { position:relative; flex:1 1 200px; min-width:150px; }
    .ost-search > i { position:absolute; left:11px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:12px; pointer-events:none; }
    .ost-search input { width:100%; height:34px; padding:0 30px; border:1px solid #e2e8f0; border-radius:8px; background:#fff; font-size:12px; color:#1e293b; outline:none; transition:border-color .15s, box-shadow .15s; }
    .ost-search input::placeholder { color:#a3adbb; }
    .ost-search input:focus { border-color:#93c5fd; box-shadow:0 0 0 3px #dbeafe; }
    .ost-clear { position:absolute; right:6px; top:50%; transform:translateY(-50%); display:none; padding:4px; border:0; background:none; color:#cbd5e1; font-size:12px; line-height:1; }
    .ost-clear:hover { color:#64748b; }
    .ost-search.has-q .ost-clear { display:block; }
    /* One border around the group, hairlines between — five buttons, one control.
       flex:0 0 auto so it is never sliced by overflow when the bar gets tight. */
    .ost-seg { display:flex; flex:0 0 auto; overflow:hidden; border:1px solid #e2e8f0; border-radius:8px; background:#fff; }
    .ost-seg button { height:34px; padding:0 8px; border:0; border-left:1px solid #e2e8f0; background:none; color:#64748b; font-size:11px; font-weight:650; white-space:nowrap; transition:.15s; }
    .ost-seg button:first-child { border-left:0; }
    .ost-seg button:hover { color:#334155; background:#f1f5f9; }
    .ost-seg button.is-on { color:#1d4ed8; background:#eff6ff; font-weight:800; }
    .ost-dates { display:flex; flex:0 0 auto; align-items:center; gap:6px; }
    .ost-dates span { color:#94a3b8; font-size:11px; }
    .ost-date { position:relative; display:inline-flex; align-items:center; height:34px; padding-right:5px; border:1px solid #e2e8f0; border-radius:8px; background:#fff; transition:border-color .15s, background .15s; }
    .ost-date:focus-within { border-color:#93c5fd; }
    .ost-date-text { width:76px; height:32px; padding:0 2px 0 9px; border:0; background:none; outline:none; color:#334155; font-size:11.5px; }
    /* A date that will not parse holds still and says so, rather than being
       swallowed and leaving the table on its old range. */
    .ost-date.is-bad { border-color:#fca5a5; background:#fef2f2; }
    .ost-date-text.is-bad { color:#b91c1c; }
    .ost-date-native { width:22px; height:22px; flex:0 0 auto; margin:0; padding:0; border:0; background:none; color:transparent; overflow:hidden; cursor:pointer; }
    /* Everything but the calendar button is taken out of the input, so what is
       left IS the button: 22px of live picker indicator. */
    .ost-date-native::-webkit-datetime-edit,
    .ost-date-native::-webkit-inner-spin-button,
    .ost-date-native::-webkit-clear-button { display:none; }
    .ost-date-native::-webkit-calendar-picker-indicator { width:22px; height:22px; margin:0; padding:0; opacity:.5; cursor:pointer; transition:opacity .15s; }
    .ost-date:hover .ost-date-native::-webkit-calendar-picker-indicator,
    .ost-date-native:focus::-webkit-calendar-picker-indicator { opacity:.9; }
    .ost-dates.is-open .ost-date { border-color:#bfdbfe; background:#f5f9ff; }
    .ost-go { height:34px; padding:0 11px; border:0; border-radius:8px; background:#2563eb; color:#fff; font-size:11px; font-weight:800; }
    .ost-go:hover { background:#1d4ed8; }
    .ost-print { height:34px; padding:0 10px; display:inline-flex; align-items:center; gap:6px; border:1px solid #e2e8f0; border-radius:8px; background:#fff; color:#475569; font-size:11px; font-weight:800; }
    .ost-print:hover { color:#1d4ed8; border-color:#93c5fd; }
    .ost-chips { display:flex; flex-wrap:wrap; gap:5px; }
    .ost-chip { padding:5px 10px; border:1px solid #e2e8f0; border-radius:999px; color:#64748b; background:#fff; font-size:10px; font-weight:750; white-space:nowrap; transition:.15s; }
    .ost-chip:hover { color:#334155; border-color:#cbd5e1; background:#f8fafc; }
    .ost-chip.is-on { color:#fff; border-color:#1d4ed8; background:#2563eb; }
    .ost-reset { height:32px; padding:0 11px; display:inline-flex; align-items:center; gap:6px; border:1px solid #fecaca; border-radius:8px; color:#b91c1c; background:#fef2f2; font-size:10.5px; font-weight:800; }
    .ost-reset:hover { color:#fff; background:#dc2626; border-color:#dc2626; }
    .ost-result { color:#64748b; font-size:10.5px; font-weight:700; white-space:nowrap; }

    .ost-table-wrap { overflow-x:auto; }
    .ost-table { width:100%; min-width:1060px; table-layout:fixed; border-collapse:collapse; }
    .ost-table th { padding:10px 9px; text-align:left; color:#64748b; background:#f8fafc; border-bottom:1px solid var(--ost-line); font-size:9px; font-weight:850; letter-spacing:.06em; text-transform:uppercase; }
    .ost-table td { padding:11px 9px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
    .ost-table tbody tr:last-child td { border-bottom:0; } .ost-table tbody tr:hover { background:#f8fbff; }
    /* Soft black — present enough to read at a glance, quiet enough not to
       compete with the reference next to it. */
    .ost-type { color:#526174; font-size:11.5px; font-weight:700; text-transform:capitalize; letter-spacing:.01em; }
    .ost-type-icon { width:31px; height:31px; border-radius:9px; display:grid; place-items:center; font-size:11px; }
    .ost-type-icon.visa { color:#6d28d9; background:#ede9fe; } .ost-type-icon.file { color:#0e7490; background:#ecfeff; } .ost-type-icon.ticket { color:#1d4ed8; background:#eff6ff; } .ost-type-icon.contract_flight { color:#b45309; background:#fef3c7; }
    .ost-ref { color:#0f172a; font-size:12px; font-weight:900; } .ost-sub { display:block; margin-top:2px; color:#94a3b8; font-size:9px; }
    /* The reference is the way to the record it stands for. */
    .ost-ref-link { color:#1d4ed8; text-decoration:none; }
    .ost-ref-link:hover { text-decoration:underline; }
    .ost-ref-link i { margin-left:4px; font-size:8px; opacity:.55; }
    .ost-assignee { display:flex; align-items:center; gap:7px; white-space:nowrap; color:#334155; font-size:11px; }
    .ost-avatar { width:27px; height:27px; border-radius:50%; display:grid; place-items:center; color:#4338ca; background:#e0e7ff; font-size:8px; font-weight:900; }
    .ost-stack { display:inline-flex; align-items:center; vertical-align:middle; }
    .ost-people-btn { display:inline-flex; align-items:center; gap:5px; margin-top:6px; padding:3px 7px 3px 4px; border:1px dashed #cbd5e1; border-radius:999px; color:#64748b; background:#fff; font-size:9px; font-weight:800; transition:.15s; }
    .ost-people-btn:hover { color:#1d4ed8; border-color:#93c5fd; border-style:solid; background:#eff6ff; }
    .ost-people-btn i { font-size:9px; }
    .ost-stack .ost-avatar { width:22px; height:22px; margin-right:-6px; font-size:7px; color:#0f766e; background:#ccfbf1; border:2px solid #fff; }
    .ost-stack-more { margin-left:10px; color:#94a3b8; font-size:9px; font-weight:800; }
    .ost-pill { display:inline-flex; align-items:center; gap:4px; padding:4px 7px; border:1px solid; border-radius:999px; font-size:9px; font-weight:850; white-space:nowrap; }
    .ost-pill.red { color:#b91c1c; background:#fef2f2; border-color:#fecaca; } .ost-pill.amber { color:#92400e; background:#fffbeb; border-color:#fde68a; } .ost-pill.green { color:#166534; background:#f0fdf4; border-color:#bbf7d0; } .ost-pill.gray { color:#475569; background:#f8fafc; border-color:#e2e8f0; } .ost-pill.blue { color:#1d4ed8; background:#eff6ff; border-color:#bfdbfe; }
    .ost-action { width:31px; height:31px; border:1px solid #dbe3ee; border-radius:8px; display:grid; place-items:center; color:#475569; background:#fff; transition:.15s; }
    .ost-action:hover { color:#fff; border-color:#2563eb; background:#2563eb; }
    .ost-progress { width:110px; height:4px; margin-top:6px; overflow:hidden; border-radius:99px; background:#e2e8f0; }
    .ost-progress span { display:block; height:100%; border-radius:99px; background:#3b82f6; }
    .ost-note-cell { max-width:none; text-align:left; }
    .ost-note-btn { display:block; width:100%; text-align:left; }
    .ost-note-body { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; color:#475569; font-size:10px; line-height:1.45; }
    .ost-note-empty { color:#cbd5e1; font-size:10px; font-style:italic; }
    .ost-note-count { display:inline-flex; align-items:center; gap:4px; margin-top:4px; color:#1d4ed8; font-size:9px; font-weight:850; }
    .ost-board { display:grid; grid-template-columns:repeat(6,minmax(210px,1fr)); gap:11px; padding:13px; overflow-x:auto; background:#f8fafc; }
    .ost-column { min-width:210px; padding:9px; border:1px solid #e2e8f0; border-radius:11px; background:#f8fafc; }
    .ost-column-head { display:flex; justify-content:space-between; padding:2px 2px 9px; color:#334155; font-size:10px; font-weight:900; letter-spacing:.05em; text-transform:uppercase; }
    .ost-count { min-width:21px; height:21px; padding:0 6px; display:grid; place-items:center; border-radius:999px; color:#475569; background:#e2e8f0; }
    .ost-board-card { display:block; width:100%; margin-bottom:8px; padding:10px; text-align:left; border:1px solid #e2e8f0; border-radius:10px; background:#fff; box-shadow:0 1px 2px rgba(15,23,42,.05); }
    .ost-board-card:last-child { margin-bottom:0; } .ost-board-card:hover { border-color:#93c5fd; }
    .ost-modal-backdrop { background:rgba(15,23,42,.56); backdrop-filter:blur(2px); }
    /* One height for every tab. Tall enough for the Update form on a laptop,
       capped so it never outgrows a short viewport. */
    .ost-modal-box { height:min(92vh, 700px); }
    @media(max-height:620px) { .ost-modal-box { height:94vh; } }
    .ost-form-label { display:block; margin-bottom:5px; color:#475569; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; }
    .ost-form-input { width:100%; min-height:40px; padding:9px 11px; border:1px solid #dbe3ee; border-radius:9px; color:#1e293b; background:#fff; font-size:12px; outline:none; }
    .ost-form-input:focus { border-color:#60a5fa; box-shadow:0 0 0 3px #dbeafe; }
    .ost-timeline { margin-left:6px; padding-left:17px; border-left:2px solid #e2e8f0; }
    .ost-event { position:relative; padding-bottom:14px; } .ost-event:before { content:""; position:absolute; left:-23px; top:4px; width:10px; height:10px; border:3px solid #3b82f6; border-radius:50%; background:#fff; }
    /* Modal panel switcher */
    .ost-panel-tabs { display:flex; gap:4px; padding:3px; background:#f1f5f9; border-radius:10px; }
    .ost-panel-tab { flex:1; padding:7px 10px; border-radius:8px; color:#64748b; font-size:11px; font-weight:800; text-align:center; }
    .ost-panel-tab.active { color:#1d4ed8; background:#fff; box-shadow:0 1px 3px rgba(15,23,42,.14); }
    .ost-person { display:flex; align-items:flex-start; gap:9px; padding:9px 10px; border:1px solid #e2e8f0; border-radius:10px; background:#fff; }
    .ost-person + .ost-person { margin-top:7px; }
    .ost-person-remove { flex:0 0 auto; width:26px; height:26px; display:grid; place-items:center; border:1px solid #fecaca; border-radius:7px; color:#dc2626; background:#fef2f2; }
    .ost-person-remove:hover { color:#fff; background:#dc2626; }
    .ost-picker { max-height:190px; overflow-y:auto; padding:6px; border:1px solid #dbe3ee; border-radius:9px; background:#fff; }
    .ost-picker label { display:flex; align-items:center; gap:8px; padding:6px 7px; border-radius:7px; color:#334155; font-size:11px; cursor:pointer; }
    .ost-picker label:hover { background:#f1f5f9; }
    .ost-note { padding:10px 11px; border:1px solid #e2e8f0; border-radius:10px; background:#f8fafc; }
    .ost-note + .ost-note { margin-top:8px; }
    @media(max-width:1200px) { .ost-kpis { grid-template-columns:repeat(3,minmax(0,1fr)); } }
    @media(max-width:700px) { .ost-page-header { align-items:flex-start; flex-direction:column; } .ost-page-header-actions { width:100%; justify-content:flex-end; } .ost-kpis { grid-template-columns:repeat(2,minmax(0,1fr)); } .ost-kpi:last-child { grid-column:1/-1; } }
</style>
@endsection

@section('main-content')
@php
    $role = request()->route('role');
    $statusMeta = [
        'new' => ['New / Collection', 'blue', 18],
        'in_progress' => ['In Progress', 'blue', 50],
        'waiting' => ['Waiting', 'amber', 65],
        'ready' => ['Ready', 'green', 88],
        'completed' => ['Completed', 'green', 100],
        'rejected' => ['Rejected / Cancelled', 'red', 100],
    ];
    $withQuery = fn (array $replace) => route('role.operation-status-tracker.index', array_merge(['role' => $role], request()->except('page'), $replace));
    $initials = fn (?string $name) => collect(explode(' ', (string) $name))
        ->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('') ?: '?';
@endphp

<div class="ost-shell space-y-4">
    <header class="ost-page-header">
        <div class="ost-page-header-copy">
            <span class="grid h-11 w-11 flex-shrink-0 place-items-center rounded-xl bg-white/15"><i class="fas fa-chart-line"></i></span>
            <div><h1 class="text-lg font-black tracking-tight">Operations Status Tracker</h1><p class="mt-1 text-[11px] text-blue-100">Visa, file, ticket and contract-flight progress — exceptions, assignment and next actions in one place.</p></div>
        </div>
        <div class="ost-page-header-actions">
            <span class="ost-live-pill"><span class="ost-live-dot"></span>Live database view</span>
            <a href="{{ $withQuery([]) }}" class="ost-refresh" title="Refresh" aria-label="Refresh operations"><i class="fas fa-rotate-right text-xs"></i></a>
        </div>
    </header>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800"><i class="fas fa-circle-check mr-2"></i>{{ session('success') }}</div>
    @endif
    @if(isset($errors) && $errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-800"><strong>Update was not saved:</strong> {{ $errors->first() }}</div>
    @endif

    <section class="ost-kpis" aria-label="Operations summary">
        <article class="ost-card ost-kpi"><div class="ost-kpi-label"><span>ACTIVE OPERATIONS</span><span class="ost-kpi-icon"><i class="fas fa-layer-group"></i></span></div><div class="ost-kpi-value">{{ number_format($stats['active']) }}</div><div class="ost-kpi-note">Current work across all selected services</div></article>
        <a href="{{ $withQuery(['flag' => 'attention', 'view' => 'all', 'page' => null]) }}" class="ost-card ost-kpi ost-danger" title="Show only these"><div class="ost-kpi-label"><span>NEEDS ATTENTION</span><span class="ost-kpi-icon"><i class="fas fa-triangle-exclamation"></i></span></div><div class="ost-kpi-value">{{ number_format($stats['attention']) }}</div><div class="ost-kpi-note">{{ $stats['overdue'] }} overdue · {{ $stats['blocked'] }} blocked · {{ $stats['stale'] }} stale</div></a>
        <a href="{{ $withQuery(['flag' => 'due_soon', 'view' => 'all', 'page' => null]) }}" class="ost-card ost-kpi ost-warning" title="Show only these"><div class="ost-kpi-label"><span>DUE IN 48 HOURS</span><span class="ost-kpi-icon"><i class="fas fa-clock"></i></span></div><div class="ost-kpi-value">{{ number_format($stats['due_soon']) }}</div><div class="ost-kpi-note">Upcoming operational deadlines</div></a>
        <a href="{{ $withQuery(['flag' => 'unassigned', 'view' => 'all', 'page' => null]) }}" class="ost-card ost-kpi" title="Show only these"><div class="ost-kpi-label"><span>UNASSIGNED</span><span class="ost-kpi-icon"><i class="fas fa-user-plus"></i></span></div><div class="ost-kpi-value">{{ number_format($stats['unassigned']) }}</div><div class="ost-kpi-note">Active records with nobody assigned</div></a>
        <article class="ost-card ost-kpi"><div class="ost-kpi-label"><span>COMPLETED TODAY</span><span class="ost-kpi-icon"><i class="fas fa-check"></i></span></div><div class="ost-kpi-value">{{ number_format($stats['completed_today']) }}</div><div class="ost-kpi-note">Based on native module status updates</div></article>
    </section>

    <section class="ost-card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-3 lg:flex-row lg:items-center lg:justify-between">
            <nav class="ost-tabs" aria-label="Tracker views">
                <a class="ost-tab {{ $view === 'attention' ? 'active' : '' }}" href="{{ $withQuery(['view' => 'attention', 'page' => null]) }}">Needs Attention ({{ $stats['attention'] }})</a>
                <a class="ost-tab {{ $view === 'all' ? 'active' : '' }}" href="{{ $withQuery(['view' => 'all', 'page' => null]) }}">All Operations</a>
                <a class="ost-tab {{ $view === 'board' ? 'active' : '' }}" href="{{ $withQuery(['view' => 'board', 'page' => null]) }}">Board View</a>
            </nav>
        </div>

        {{-- Filter bar. Everything here is server-side on purpose: the table
             paginates, so filtering in the browser would only ever filter the
             25 rows already on screen and quietly lie about the totals. --}}
        <form id="ostFilters" method="GET" action="{{ route('role.operation-status-tracker.index', ['role' => $role]) }}">
            <input type="hidden" name="view" value="{{ $view }}">
            <input type="hidden" name="flag" value="{{ $filters['flag'] }}">
            {{-- Carries the active preset when Apply is pressed. A preset button
                 submits its own value after this, and PHP takes the later one. --}}
            <input type="hidden" name="range" id="ostRange" value="{{ $filters['range'] }}">

            <div class="ost-bar">
                <div class="ost-search {{ $search ? 'has-q' : '' }}">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" name="search" id="ostSearch" value="{{ $filters['search'] }}" autocomplete="off"
                           placeholder="Search reference, client, country, stage, assignee…">
                    <button type="button" class="ost-clear" id="ostSearchClear" title="Clear search"><i class="fas fa-xmark"></i></button>
                </div>

                <div class="ost-seg" id="ostRangeSeg">
                    @foreach($rangeOptions as $key => $label)
                        <button type="submit" name="range" value="{{ $key }}"
                                class="{{ $filters['range'] === $key && ! $filters['from'] && ! $filters['to'] ? 'is-on' : '' }}">{{ $label }}</button>
                    @endforeach
                </div>

                {{-- Which date the range means. A row has both a deadline and a
                     last-update, and filtering the wrong one looks identical. --}}
                <select class="ost-control" name="date_field" title="Which date the range applies to">
                    @foreach($dateFieldOptions as $key => $label)
                        <option value="{{ $key }}" @selected($filters['date_field'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>

                {{-- Typed half and picker half inside one border. <input type="date">
                     prints its value in the BROWSER's locale — mm/dd/yyyy on an en-US
                     Chrome — and no attribute on the page can change that. So the half
                     you read and type is a text box, and the real date input sits beside
                     it with its printed value hidden and only its calendar button showing.
                     That button is Chrome's own picker indicator, so it opens the native
                     calendar with no library and no showPicker() call. The name stays on
                     the date input, so the server still receives a plain Y-m-d. --}}
                <div class="ost-dates {{ $filters['from'] || $filters['to'] ? 'is-open' : '' }}">
                    <span class="ost-date">
                        <input type="text" class="ost-date-text" data-for="ostFrom" placeholder="dd/mm/yyyy"
                               inputmode="numeric" maxlength="10" autocomplete="off" aria-label="From date">
                        <input type="date" class="ost-date-native" id="ostFrom" name="from"
                               value="{{ $filters['from'] }}" aria-label="Pick the from date" title="Pick a date">
                    </span>
                    <span>to</span>
                    <span class="ost-date">
                        <input type="text" class="ost-date-text" data-for="ostTo" placeholder="dd/mm/yyyy"
                               inputmode="numeric" maxlength="10" autocomplete="off" aria-label="To date">
                        <input type="date" class="ost-date-native" id="ostTo" name="to"
                               value="{{ $filters['to'] }}" aria-label="Pick the to date" title="Pick a date">
                    </span>
                </div>

                <button type="submit" class="ost-go"><i class="fas fa-filter"></i> Apply</button>
                @if($invoiceFlightId)
                    <a class="ost-print" id="ostPrint" target="_blank"
                       href="{{ route('role.contract-flights.invoice', ['role' => $role, 'contractFlight' => $invoiceFlightId]) }}"
                       title="Open this flight's invoice"><i class="fas fa-file-invoice"></i> Print Invoice</a>
                @else
                    <a class="ost-print" id="ostPrint" target="_blank"
                       href="{{ route('role.operation-status-tracker.print', array_merge(['role' => $role], request()->except('page'))) }}"
                       title="Open a print-friendly sheet of the rows below"><i class="fas fa-print"></i> Print</a>
                @endif
            </div>

            <div class="ost-bar ost-bar-2">
                <div class="ost-chips">
                    @foreach($flagOptions as $key => $label)
                        <a class="ost-chip {{ $filters['flag'] === $key ? 'is-on' : '' }}"
                           href="{{ $withQuery(['flag' => $key, 'page' => null]) }}">{{ $label }}</a>
                    @endforeach
                </div>

                <span class="ost-bar-spacer"></span>

                <select class="ost-control" name="type" title="Service type">
                    <option value="all">All types</option>
                    @foreach(['visa' => 'Visa', 'file' => 'File', 'ticket' => 'Ticket', 'contract_flight' => 'Contract Flight'] as $key => $label)
                        <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <select class="ost-control" name="status" title="Stage">
                    <option value="all">All stages</option>
                    @foreach($statusMeta as $key => [$label, , ])
                        <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <select class="ost-control" name="priority" title="Priority">
                    <option value="all">All priorities</option>
                    @foreach(['urgent','high','normal','low'] as $key)
                        <option value="{{ $key }}" @selected($filters['priority'] === $key)>{{ ucfirst($key) }}</option>
                    @endforeach
                </select>
                <select class="ost-control" name="assignee" title="Assignee, or anyone asked to look">
                    <option value="">All assignees</option>
                    @foreach($assignees as $person)
                        <option value="{{ $person->id }}" @selected($assignee === $person->id)>{{ $person->name }}</option>
                    @endforeach
                </select>

                @if($filtered)
                    <a class="ost-reset" href="{{ route('role.operation-status-tracker.index', ['role' => $role, 'view' => $view]) }}" title="Clear every filter"><i class="fas fa-rotate-left"></i> Reset</a>
                    <span class="ost-result">{{ number_format($datas->total()) }} match{{ $datas->total() === 1 ? '' : 'es' }}</span>
                @endif
            </div>
        </form>

        @if($view === 'board')
            <div class="ost-board">
                @foreach($statusMeta as $statusKey => [$statusLabel, $tone, $progress])
                    @php
                        $columnItems = $board->get($statusKey, collect());
                    @endphp
                    <div class="ost-column">
                        <div class="ost-column-head"><span>{{ $statusLabel }}</span><span class="ost-count">{{ $columnItems->count() }}</span></div>
                        @forelse($columnItems as $item)
                            @can('edit operation tracker')
                            <button type="button" class="ost-board-card ost-open" data-key="{{ $item['type'] }}:{{ $item['id'] }}">
                            @else
                            <a href="{{ $item['source_url'] }}" class="ost-board-card">
                            @endcan
                                <div class="flex items-start justify-between gap-2"><strong class="text-[11px] text-slate-800">{{ $item['reference'] }}</strong>@if($item['is_overdue'])<span class="ost-pill red">Overdue</span>@elseif($item['is_blocked'])<span class="ost-pill amber">Blocked</span>@endif</div>
                                <p class="my-2 text-[10px] text-slate-500">{{ $item['client'] }} · {{ $item['stage'] }}</p>
                                <div class="flex justify-between text-[9px] text-slate-400"><span class="capitalize">{{ str_replace('_', ' ', $item['type']) }} · {{ $item['context'] }}</span><span>{{ $item['assignee_name'] ?: 'Unassigned' }}@if(count($item['collaborators'])) +{{ count($item['collaborators']) }}@endif</span></div>
                            @can('edit operation tracker')</button>@else</a>@endcan
                        @empty
                            <div class="rounded-lg border border-dashed border-slate-200 px-3 py-7 text-center text-[10px] text-slate-400">No operations</div>
                        @endforelse
                    </div>
                @endforeach
            </div>
        @else
            @if($view === 'attention')
                <div class="flex justify-between border-b border-red-100 bg-red-50/60 px-4 py-3 text-[10px] text-red-800"><strong><i class="fas fa-triangle-exclamation mr-1"></i>Exceptions first — sorted by urgency</strong><span>Refreshed {{ now()->format('h:i A') }}</span></div>
            @endif
            <div class="ost-table-wrap">
                <table class="ost-table">
                    {{-- Fixed widths so all eight columns land inside a 1366 laptop
                         with the sidebar open, rather than pushing Notes off-screen. --}}
                    <colgroup>
                        <col style="width:62px"><col style="width:195px"><col style="width:150px"><col style="width:173px">
                        <col style="width:135px"><col style="width:145px"><col style="width:150px"><col style="width:50px">
                    </colgroup>
                    <thead><tr><th>Type</th><th>Reference / Client</th><th>Current stage</th><th>Assignee</th><th>Deadline / Age</th><th>Next action</th><th>Notes</th><th></th></tr></thead>
                    <tbody>
                    @forelse($datas as $item)
                        @php
                            [$statusLabel, $tone, $progress] = $statusMeta[$item['normalized_status']];
                        @endphp
                        <tr>
                            <td><span class="ost-type">{{ str_replace('_', ' ', $item['type']) }}</span></td>
                            <td>
                                {{-- Straight to the record on its own module screen. --}}
                                <a class="ost-ref ost-ref-link" href="{{ $item['source_url'] }}" title="Open {{ $item['reference'] }} in its own module">{{ $item['reference'] }}<i class="fas fa-arrow-up-right-from-square"></i></a>
                                <span class="ost-sub">{{ $item['client'] }}{{ $item['context'] ? ' · '.$item['context'] : '' }}</span>
                            </td>
                            <td><div class="text-[11px] font-bold text-slate-700">{{ $item['stage'] }}</div><div class="ost-progress"><span style="width:{{ $progress }}%;{{ $tone === 'amber' ? 'background:#f59e0b' : ($tone === 'green' ? 'background:#22c55e' : ($tone === 'red' ? 'background:#ef4444' : '')) }}"></span></div><span class="ost-sub">{{ $statusLabel }}</span></td>
                            <td>
                                @if($item['assignee_name'])
                                    <div class="ost-assignee"><span class="ost-avatar">{{ $initials($item['assignee_name']) }}</span><span>{{ $item['assignee_name'] }}<span class="ost-sub">{{ ucfirst($item['priority']) }} priority</span></span></div>
                                @else
                                    <span class="ost-pill red"><i class="fas fa-user-plus"></i>Unassigned</span>
                                @endif
                                {{-- The way in to assigning more people. Without a
                                     control here the picker is invisible: it lives
                                     three clicks deep behind the row's arrow. --}}
                                @can('edit operation tracker')
                                    <button type="button" class="ost-open ost-people-btn" data-key="{{ $item['type'] }}:{{ $item['id'] }}" data-open-panel="people"
                                            title="{{ count($item['collaborators']) ? 'Also looking: '.collect($item['collaborators'])->pluck('name')->implode(', ') : 'Ask people to look into this' }}">
                                        @if(count($item['collaborators']))
                                            <span class="ost-stack">
                                                @foreach(array_slice($item['collaborators'], 0, 3) as $person)
                                                    <span class="ost-avatar">{{ $initials($person['name']) }}</span>
                                                @endforeach
                                            </span>
                                            <span class="ost-stack-more">{{ count($item['collaborators']) > 3 ? '+'.(count($item['collaborators']) - 3).' more' : 'also looking' }}</span>
                                        @else
                                            <i class="fas fa-user-plus"></i>Add people
                                        @endif
                                    </button>
                                @elseif(count($item['collaborators']))
                                    <div class="ost-stack" title="Also looking: {{ collect($item['collaborators'])->pluck('name')->implode(', ') }}">
                                        @foreach(array_slice($item['collaborators'], 0, 3) as $person)
                                            <span class="ost-avatar">{{ $initials($person['name']) }}</span>
                                        @endforeach
                                        <span class="ost-stack-more">{{ count($item['collaborators']) > 3 ? '+'.(count($item['collaborators']) - 3).' more' : 'also looking' }}</span>
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($item['is_overdue'])<span class="ost-pill red"><i class="fas fa-circle-exclamation"></i>Overdue {{ $item['due_at']->diffForHumans(null, true) }}</span>
                                @elseif($item['is_due_soon'])<span class="ost-pill amber"><i class="fas fa-clock"></i>Due {{ $item['due_at']->diffForHumans() }}</span>
                                @elseif($item['due_at'])<span class="ost-pill gray"><i class="fas fa-calendar"></i>{{ $item['due_at']->format('d M Y') }}</span>
                                @else<span class="ost-pill gray">No deadline</span>@endif
                                <span class="ost-sub">Last update {{ $item['last_activity_at']?->diffForHumans() ?: 'unknown' }}</span>
                            </td>
                            <td><strong class="block truncate text-[11px] text-slate-700">{{ $item['next_action'] ?: 'Next action not set' }}</strong>@if($item['blocked_reason'])<span class="ost-sub truncate text-red-500">Blocked: {{ $item['blocked_reason'] }}</span>@elseif($item['is_stale'])<span class="ost-sub text-amber-600">No update for more than 3 days</span>@endif</td>
                            <td class="ost-note-cell">
                                @can('edit operation tracker')
                                    <button type="button" class="ost-open ost-note-btn" data-key="{{ $item['type'] }}:{{ $item['id'] }}" data-open-panel="notes" title="Open notes">
                                @endcan
                                    @if($item['latest_note'])
                                        <span class="ost-note-body">{{ $item['latest_note'] }}</span>
                                        <span class="ost-note-count"><i class="fas fa-note-sticky"></i>{{ $item['note_count'] }} note{{ $item['note_count'] === 1 ? '' : 's' }}</span>
                                    @else
                                        <span class="ost-note-empty">No notes yet</span>
                                        @can('edit operation tracker')<span class="ost-note-count"><i class="fas fa-plus"></i>Add a note</span>@endcan
                                    @endif
                                @can('edit operation tracker')
                                    </button>
                                @endcan
                            </td>
                            <td>@can('edit operation tracker')<button type="button" class="ost-action ost-open" data-key="{{ $item['type'] }}:{{ $item['id'] }}" title="Open details"><i class="fas fa-arrow-right"></i></button>@else<a class="ost-action" href="{{ $item['source_url'] }}" title="Open source record"><i class="fas fa-arrow-right"></i></a>@endcan</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-14 text-center"><i class="fas fa-circle-check mb-2 block text-2xl text-emerald-400"></i><strong class="text-sm text-slate-600">No matching operations</strong><span class="ost-sub">There is nothing requiring attention under the selected filters.</span></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($datas->hasPages())<div class="border-t border-slate-100 p-3">{{ $datas->links() }}</div>@endif
        @endif
    </section>
</div>

@can('edit operation tracker')
<div id="ostModal" class="ost-modal-backdrop fixed inset-0 z-[9999] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="ostModalTitle">
    {{-- Fixed frame. The three panels differ a lot in height (Update is long,
         Notes is short); without a set height the whole dialog jumped and
         re-centred on every tab switch. The box stays put and the body scrolls. --}}
    <div class="ost-modal-box flex w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl" onclick="event.stopPropagation()">
        <header class="flex flex-shrink-0 items-center gap-3 border-b border-slate-200 bg-white px-5 py-4">
            <span id="ostModalTypeIcon" class="ost-type-icon visa">V</span>
            <div class="min-w-0">
                <h2 id="ostModalTitle" class="text-sm font-black text-slate-800">Operation</h2>
                <p id="ostModalSubtitle" class="text-[10px] text-slate-400"></p>
            </div>
            <a id="ostHeaderSource" class="ml-auto hidden rounded-lg border border-slate-200 px-3 py-1.5 text-[10px] font-bold text-slate-600 hover:border-blue-300 hover:text-blue-600 sm:inline-block" target="_blank">Open record <i class="fas fa-arrow-up-right-from-square ml-1"></i></a>
            <button type="button" id="ostModalClose" class="ml-2 grid h-8 w-8 flex-shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-500"><i class="fas fa-times"></i></button>
        </header>

        <div class="grid min-h-0 flex-1 gap-5 overflow-y-auto p-5 lg:grid-cols-[1fr_310px]">
            <div class="min-w-0 space-y-4">
                <nav class="ost-panel-tabs" role="tablist">
                    <button type="button" class="ost-panel-tab active" data-panel-tab="update" role="tab">Update</button>
                    <button type="button" class="ost-panel-tab" data-panel-tab="people" role="tab">People <span id="ostPeopleCount" class="text-slate-400"></span></button>
                    <button type="button" class="ost-panel-tab" data-panel-tab="notes" role="tab">Notes <span id="ostNotesCount" class="text-slate-400"></span></button>
                </nav>

                {{-- Panel 1 — the tracking metadata this screen owns. --}}
                <form id="ostUpdateForm" method="POST" class="ost-panel grid grid-cols-1 gap-4 sm:grid-cols-2" data-panel="update">
                    @csrf @method('PATCH')
                    <div><label class="ost-form-label" for="ostStatus">Native status <span class="normal-case text-slate-400">(source-controlled)</span></label><select class="ost-form-input bg-slate-50" id="ostStatus" disabled></select></div>
                    <div id="ostStageWrap"><label class="ost-form-label" for="ostStage">Visa stage <span class="normal-case text-slate-400">(source-controlled)</span></label><select class="ost-form-input bg-slate-50" id="ostStage" disabled>@foreach(\App\Models\VisaProcess::$stages as $stage)<option value="{{ $stage }}">{{ $stage }}</option>@endforeach</select></div>
                    <div><label class="ost-form-label" for="ostAssignee">Assignee</label><select class="ost-form-input" id="ostAssignee" name="assigned_to"><option value="">Unassigned</option>@foreach($assignees as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></div>
                    <div><label class="ost-form-label" for="ostPriority">Priority</label><select class="ost-form-input" id="ostPriority" name="priority">@foreach(['low','normal','high','urgent'] as $priority)<option value="{{ $priority }}">{{ ucfirst($priority) }}</option>@endforeach</select></div>
                    <div><label class="ost-form-label" for="ostDue">Operational deadline</label><input class="ost-form-input" id="ostDue" type="datetime-local" name="due_at"></div>
                    <div><label class="ost-form-label" for="ostNextAction">Next action</label><input class="ost-form-input" id="ostNextAction" name="next_action" maxlength="1000" placeholder="What must happen next?"></div>
                    <div class="sm:col-span-2"><label class="ost-form-label" for="ostBlocker">Blocked reason <span class="normal-case text-slate-400">(leave empty when not blocked)</span></label><textarea class="ost-form-input" id="ostBlocker" name="blocked_reason" rows="2" maxlength="1000" placeholder="What is preventing progress?"></textarea></div>
                    <div class="sm:col-span-2"><label class="ost-form-label" for="ostNote">Update note *</label><textarea class="ost-form-input" id="ostNote" name="update_note" rows="3" minlength="3" maxlength="1500" required placeholder="Briefly record what changed and why"></textarea></div>
                    <div class="flex flex-wrap gap-2 sm:col-span-2"><button class="rounded-lg bg-blue-600 px-4 py-2.5 text-[11px] font-bold text-white" type="submit"><i class="fas fa-save mr-1"></i>Save update</button><a id="ostSourceLink" class="rounded-lg border border-slate-200 px-4 py-2.5 text-[11px] font-bold text-slate-600" target="_blank">Open source record <i class="fas fa-arrow-up-right-from-square ml-1"></i></a></div>
                </form>

                {{-- Panel 2 — extra people looped in. The assignee above still answers
                     for the record; these are people asked to look, each notified. --}}
                <div class="ost-panel hidden space-y-4" data-panel="people">
                    <div>
                        <h3 class="mb-2 text-[10px] font-black uppercase tracking-wider text-slate-500">On this record</h3>
                        <div id="ostPeopleList"></div>
                    </div>
                    <form id="ostAssignForm" method="POST" class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                        @csrf
                        <div>
                            <label class="ost-form-label" for="ostPersonFilter">Ask more people to look</label>
                            <input class="ost-form-input mb-2" id="ostPersonFilter" type="search" placeholder="Filter by name…" autocomplete="off">
                            {{-- Current employees only. `$assignees` (used by the assignee
                                 dropdown and the filter) additionally carries people
                                 already on a record who have since left; nobody
                                 should be newly assigned to one of those. --}}
                            <div class="ost-picker" id="ostPersonPicker">
                                @forelse($staff as $person)
                                    <label data-name="{{ mb_strtolower($person->name) }}">
                                        <input type="checkbox" name="users[]" value="{{ $person->id }}">
                                        <span>{{ $person->name }}</span>
                                    </label>
                                @empty
                                    <p class="px-2 py-3 text-center text-[10px] text-slate-400">No active employees found for this company.</p>
                                @endforelse
                            </div>
                        </div>
                        <div>
                            <label class="ost-form-label" for="ostAssignNote">Why (sent with the notification)</label>
                            <textarea class="ost-form-input" id="ostAssignNote" name="note" rows="2" maxlength="1000" placeholder="e.g. Embassy asked for a re-submission — please review the documents"></textarea>
                        </div>
                        <button class="rounded-lg bg-blue-600 px-4 py-2.5 text-[11px] font-bold text-white" type="submit"><i class="fas fa-user-plus mr-1"></i>Add &amp; notify</button>
                    </form>
                </div>

                {{-- Panel 3 — the note thread. Posting notifies the assignee and
                     everyone on the record. --}}
                <div class="ost-panel hidden space-y-4" data-panel="notes">
                    <form id="ostNoteForm" method="POST" class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                        @csrf
                        <div>
                            <label class="ost-form-label" for="ostNoteBody">Add a note</label>
                            <textarea class="ost-form-input" id="ostNoteBody" name="body" rows="3" minlength="2" maxlength="2000" required placeholder="What should everyone on this record know?"></textarea>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <button class="rounded-lg bg-blue-600 px-4 py-2.5 text-[11px] font-bold text-white" type="submit"><i class="fas fa-paper-plane mr-1"></i>Post &amp; notify</button>
                            <span id="ostNoteAudience" class="text-[10px] text-slate-400"></span>
                        </div>
                    </form>
                    <div id="ostNoteList"></div>
                </div>
            </div>

            <aside class="border-t border-slate-200 pt-4 lg:border-l lg:border-t-0 lg:pl-5 lg:pt-0">
                <h3 class="mb-4 text-[10px] font-black tracking-wider text-slate-500">ACTIVITY TIMELINE</h3>
                <div id="ostTimeline" class="ost-timeline"></div>
            </aside>
        </div>
    </div>
</div>
@endcan
@endsection

@section('js')
{{-- Filter bar behaviour. Outside the edit-permission block: a read-only user
     still filters and prints. --}}
<script>
(() => {
    const form = document.getElementById('ostFilters');
    if (!form) return;

    const search = document.getElementById('ostSearch');
    const wrap = search.closest('.ost-search');
    const clear = document.getElementById('ostSearchClear');

    const toggleClear = () => wrap.classList.toggle('has-q', search.value.trim() !== '');
    search.addEventListener('input', toggleClear);
    clear.addEventListener('click', () => { search.value = ''; toggleClear(); form.submit(); });
    toggleClear();

    // Keep the dd/mm/yyyy box and the real date input saying the same thing.
    // The native input is what submits, so the text box only ever writes to it.
    const pad = n => String(n).padStart(2, '0');
    form.querySelectorAll('.ost-date-text').forEach(text => {
        const native = document.getElementById(text.dataset.for);
        const box = text.closest('.ost-date');

        const fromNative = () => {
            const [y, m, d] = (native.value || '').split('-');
            text.value = y ? `${d}/${m}/${y}` : '';
            box.classList.remove('is-bad'); text.classList.remove('is-bad');
        };
        fromNative();
        native.addEventListener('change', fromNative);

        text.addEventListener('input', () => {
            const raw = text.value.trim();
            if (raw === '') { native.value = ''; box.classList.remove('is-bad'); text.classList.remove('is-bad'); return; }
            const m = raw.match(/^(\d{1,2})\D(\d{1,2})\D(\d{4})$/);
            const d = m ? new Date(+m[3], +m[2] - 1, +m[1]) : null;
            // Re-reading the parts catches 31/02, which Date happily rolls over.
            const ok = d && d.getFullYear() === +m[3] && d.getMonth() === +m[2] - 1 && d.getDate() === +m[1];
            native.value = ok ? `${m[3]}-${pad(m[2])}-${pad(m[1])}` : '';
            box.classList.toggle('is-bad', !ok);
            text.classList.toggle('is-bad', !ok);
        });
    });

    // A preset and a typed range are two answers to the same question. Picking a
    // preset drops whatever was typed, so the bar can never show both at once.
    document.querySelectorAll('#ostRangeSeg button').forEach(button => {
        button.addEventListener('click', () => {
            document.getElementById('ostRange').value = button.value;
            form.querySelectorAll('.ost-date-native').forEach(input => input.value = '');
            form.querySelectorAll('.ost-date-text').forEach(input => input.value = '');
        });
    });

    // Both #ostPrint variants are <a> links now (real invoice, or the
    // standalone print sheet) — they navigate on their own, nothing to wire up.
})();
</script>
@can('edit operation tracker')
@php
    $modalItems = $board->flatten(1)->mapWithKeys(function ($item) {
        return [$item['type'].':'.$item['id'] => [
            'type' => $item['type'], 'id' => $item['id'], 'reference' => $item['reference'], 'client' => $item['client'], 'context' => $item['context'],
            'source_status' => $item['source_status'], 'source_stage' => $item['source_stage'], 'status_options' => $item['status_options'],
            'assignee_id' => $item['assignee_id'], 'assignee_name' => $item['assignee_name'], 'priority' => $item['priority'], 'due_at' => $item['due_at']?->format('Y-m-d\TH:i'),
            'next_action' => $item['next_action'], 'blocked_reason' => $item['blocked_reason'], 'source_url' => $item['source_url'],
            'collaborators' => $item['collaborators'],
            'notes' => $item['notes']->map(fn($note) => ['body' => $note->note, 'by' => $note->changedBy?->name ?: 'System', 'at' => $note->created_at?->format('d M Y, h:i A')])->values(),
            'histories' => $item['histories']->map(fn($history) => ['event' => str_replace('_',' ',$history->event_type), 'from' => $history->from_status, 'to' => $history->to_status, 'note' => $history->note, 'by' => $history->changedBy?->name ?: 'System', 'at' => $history->created_at?->format('d M Y, h:i A')])->values(),
        ]];
    });
@endphp
<script>
(() => {
    const items = @json($modalItems);
    const modal = document.getElementById('ostModal');
    const form = document.getElementById('ostUpdateForm');
    const assignForm = document.getElementById('ostAssignForm');
    const noteForm = document.getElementById('ostNoteForm');
    const baseUrl = @json(url('/'.$role.'/operation-status-tracker'));
    const csrf = @json(csrf_token());
    const openKey = @json($openKey);
    const fields = {
        status: document.getElementById('ostStatus'), stage: document.getElementById('ostStage'), assignee: document.getElementById('ostAssignee'),
        priority: document.getElementById('ostPriority'), due: document.getElementById('ostDue'), next: document.getElementById('ostNextAction'), blocker: document.getElementById('ostBlocker')
    };
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    const initials = name => String(name || '?').trim().split(/\s+/).slice(0, 2).map(part => part.charAt(0).toUpperCase()).join('') || '?';

    let current = null;

    // Scoped to the modal: the table's note buttons carry `data-open-panel`,
    // deliberately a different attribute, so they are never swept up here.
    function showPanel(name) {
        modal.querySelectorAll('[data-panel]').forEach(panel => panel.classList.toggle('hidden', panel.dataset.panel !== name));
        modal.querySelectorAll('[data-panel-tab]').forEach(tab => tab.classList.toggle('active', tab.dataset.panelTab === name));
    }

    function renderPeople(item) {
        const list = document.getElementById('ostPeopleList');
        const assignee = item.assignee_name
            ? `<div class="ost-person"><span class="ost-avatar">${escapeHtml(initials(item.assignee_name))}</span>
                 <div class="min-w-0 flex-1"><strong class="block text-[11px] text-slate-700">${escapeHtml(item.assignee_name)}</strong>
                 <span class="text-[9px] text-slate-400">Assignee — set on the Update tab</span></div></div>`
            : `<div class="ost-person"><span class="ost-pill red"><i class="fas fa-user-plus"></i>Unassigned</span>
                 <span class="text-[10px] text-slate-400">Set an assignee on the Update tab.</span></div>`;

        const extras = (item.collaborators || []).map(person => `
            <div class="ost-person">
                <span class="ost-avatar" style="color:#0f766e;background:#ccfbf1">${escapeHtml(initials(person.name))}</span>
                <div class="min-w-0 flex-1">
                    <strong class="block text-[11px] text-slate-700">${escapeHtml(person.name)}</strong>
                    <span class="block text-[9px] text-slate-400">${person.note ? escapeHtml(person.note) : 'Asked to look into this'}</span>
                </div>
                <form method="POST" action="${baseUrl}/${encodeURIComponent(item.type)}/${item.id}/assignees/${person.id}"
                      onsubmit="return confirm('Remove ${escapeHtml(person.name).replace(/'/g, "\\'")} from this record?')">
                    <input type="hidden" name="_token" value="${csrf}">
                    <input type="hidden" name="_method" value="DELETE">
                    <button class="ost-person-remove" type="submit" title="Remove"><i class="fas fa-user-minus text-[10px]"></i></button>
                </form>
            </div>`).join('');

        list.innerHTML = assignee + extras;

        // Nobody should be offered to themselves twice.
        const already = new Set([item.assignee_id, ...(item.collaborators || []).map(person => person.id)].filter(Boolean).map(Number));
        document.querySelectorAll('#ostPersonPicker label').forEach(label => {
            const box = label.querySelector('input');
            box.checked = false;
            label.hidden = already.has(Number(box.value));
        });
        document.getElementById('ostPersonFilter').value = '';

        const total = (item.assignee_id ? 1 : 0) + (item.collaborators || []).length;
        document.getElementById('ostPeopleCount').textContent = total ? `(${total})` : '';
        document.getElementById('ostNoteAudience').textContent = total
            ? `${total} person(s) will be notified.`
            : 'Nobody is on this record yet — no one will be notified.';
    }

    function renderNotes(item) {
        const list = document.getElementById('ostNoteList');
        list.innerHTML = (item.notes || []).length
            ? item.notes.map(note => `
                <div class="ost-note">
                    <p class="text-[11px] leading-relaxed text-slate-700">${escapeHtml(note.body)}</p>
                    <span class="mt-1.5 block text-[9px] text-slate-400">${escapeHtml(note.by)} · ${escapeHtml(note.at)}</span>
                </div>`).join('')
            : '<div class="rounded-lg border border-dashed border-slate-200 p-5 text-center text-[10px] text-slate-400">No notes on this record yet.</div>';
        document.getElementById('ostNotesCount').textContent = (item.notes || []).length ? `(${item.notes.length})` : '';
        document.getElementById('ostNoteBody').value = '';
    }

    function open(key, panel) {
        const item = items[key]; if (!item) return;
        current = item;
        document.getElementById('ostModalTitle').textContent = item.reference;
        document.getElementById('ostModalSubtitle').textContent = [item.client, item.context].filter(Boolean).join(' · ');
        const icon = document.getElementById('ostModalTypeIcon'); icon.className = 'ost-type-icon ' + item.type; icon.textContent = item.type.charAt(0).toUpperCase();
        fields.status.innerHTML = Object.entries(item.status_options).map(([value,label]) => `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`).join('');
        fields.status.value = item.source_status; fields.stage.value = item.source_stage || ''; fields.assignee.value = item.assignee_id || '';
        fields.priority.value = item.priority || 'normal'; fields.due.value = item.due_at || ''; fields.next.value = item.next_action || ''; fields.blocker.value = item.blocked_reason || '';
        document.getElementById('ostNote').value = '';
        document.getElementById('ostStageWrap').classList.toggle('hidden', item.type !== 'visa');
        document.getElementById('ostSourceLink').href = item.source_url;
        const headerLink = document.getElementById('ostHeaderSource');
        headerLink.href = item.source_url; headerLink.classList.remove('hidden');

        const suffix = `/${encodeURIComponent(item.type)}/${item.id}`;
        form.action = `${baseUrl}${suffix}`;
        assignForm.action = `${baseUrl}${suffix}/assignees`;
        noteForm.action = `${baseUrl}${suffix}/notes`;

        renderPeople(item);
        renderNotes(item);

        const timeline = document.getElementById('ostTimeline');
        timeline.innerHTML = item.histories.length ? item.histories.map(history => {
            // A note has no state change to show, so the arrow line is omitted.
            const transition = (history.from || history.to)
                ? `<span class="block text-[9px] text-slate-400">${escapeHtml(history.from || '—')} → ${escapeHtml(history.to || '—')}</span>`
                : '';
            return `<div class="ost-event"><strong class="block text-[11px] capitalize text-slate-700">${escapeHtml(history.event)}</strong>${transition}<p class="my-1 text-[10px] text-slate-600">${escapeHtml(history.note)}</p><span class="text-[9px] text-slate-400">${escapeHtml(history.by)} · ${escapeHtml(history.at)}</span></div>`;
        }).join('') : '<div class="-ml-4 rounded-lg border border-dashed border-slate-200 p-4 text-center text-[10px] text-slate-400">No tracker updates yet.<br>The first save will start the audit trail.</div>';

        showPanel(panel || 'update');
        modal.classList.remove('hidden'); modal.classList.add('flex'); document.body.style.overflow = 'hidden';
        // Focus something that can actually take it — the disabled status select cannot.
        (panel === 'notes' ? document.getElementById('ostNoteBody') : fields.assignee)?.focus();
    }
    function close() { modal.classList.add('hidden'); modal.classList.remove('flex'); document.body.style.overflow = ''; }

    document.querySelectorAll('.ost-open').forEach(button => button.addEventListener('click', () => open(button.dataset.key, button.dataset.openPanel)));
    modal.querySelectorAll('[data-panel-tab]').forEach(tab => tab.addEventListener('click', () => showPanel(tab.dataset.panelTab)));
    document.getElementById('ostModalClose').addEventListener('click', close);
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !modal.classList.contains('hidden')) close(); });

    document.getElementById('ostPersonFilter').addEventListener('input', event => {
        const term = event.target.value.trim().toLowerCase();
        const already = new Set([current?.assignee_id, ...((current?.collaborators) || []).map(person => person.id)].filter(Boolean).map(Number));
        document.querySelectorAll('#ostPersonPicker label').forEach(label => {
            const isOn = already.has(Number(label.querySelector('input').value));
            label.hidden = isOn || (term !== '' && !label.dataset.name.includes(term));
        });
    });

    assignForm.addEventListener('submit', event => {
        if (!assignForm.querySelector('input[name="users[]"]:checked')) {
            event.preventDefault();
            alert('Pick at least one person to add.');
        }
    });

    // Arriving from a notification, or straight after saving: open that record.
    if (openKey && items[openKey]) open(openKey);
})();
</script>
@endcan
@endsection
