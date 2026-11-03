@extends('layout.app')
@section('meta-information')
    <title>Flights &amp; Passengers</title>
@endsection
@section('css')
<style>
    /* Filter bar — the Flight Dashboard's filter strip, under this page's prefix. */
    .mfp-filter {
        display: grid; grid-template-columns: minmax(200px, 1.4fr) 150px 150px max-content 118px 118px max-content;
        align-items: end; gap: 10px; padding: 10px 12px 12px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;
    }
    .mfp-filter-field { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
    .mfp-filter label { color: #475569; font-size: 11px; font-weight: 600; margin: 0; }
    .mfp-filter input, .mfp-filter select {
        width: 100%; height: 28px; min-width: 0; border: 1px solid #dce2eb; border-radius: 9px; background: #fff;
        color: #334155; font-size: 12px; padding: 0 7px; box-shadow: 0 1px 2px #0f172a03; box-sizing: border-box;
        transition: border-color .18s ease, box-shadow .18s ease;
    }
    .mfp-filter input[type="search"] { padding-left: 32px; }
    .mfp-filter-search { position: relative; }
    .mfp-filter-search > i { position: absolute; left: 11px; top: 8px; color: #94a3b8; font-size: 12px; pointer-events: none; }
    .mfp-filter input::placeholder { color: #8793a5; }
    .mfp-filter input:hover, .mfp-filter select:hover { border-color: #a5b4fc; }
    .mfp-filter input:focus, .mfp-filter select:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px #6366f11a; }
    .mfp-filter .select2-container .select2-selection--single { height: 28px !important; border: 1px solid #dce2eb !important; border-radius: 9px !important; background: #fff; box-shadow: 0 1px 2px #0f172a03; }
    .mfp-filter .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 26px !important; padding-left: 7px; padding-right: 28px; font-size: 12px; color: #334155; }
    .mfp-filter .select2-container--default .select2-selection--single .select2-selection__placeholder { color: #334155; }
    .mfp-filter .select2-container--default .select2-selection--single .select2-selection__arrow { height: 26px !important; right: 4px; }
    .mfp-filter .select2-container--default .select2-selection--single .select2-selection__clear { margin-right: 14px; font-size: 14px; color: #94a3b8; }
    .mfp-filter .select2-container--default.select2-container--open .select2-selection--single,
    .mfp-filter .select2-container--default.select2-container--focus .select2-selection--single { border-color: #6366f1 !important; box-shadow: 0 0 0 3px #6366f11a; }
    .mfp-filter .select2-container:hover .select2-selection--single { border-color: #a5b4fc !important; }
    .mfp-date-presets { display: flex; align-items: center; gap: 5px; flex-wrap: nowrap; padding-bottom: 0; }
    .mfp-date-presets button, .mfp-filter-actions button, .mfp-filter-reset {
        height: 28px; padding: 0 8px; border: 1px solid #dce2eb; border-radius: 7px; background: #fff; color: #64748b;
        font-size: 10px; font-weight: 600; white-space: nowrap; cursor: pointer; transition: background .18s ease, border-color .18s ease, color .18s ease;
    }
    .mfp-date-presets button:hover, .mfp-date-presets button.is-active, .mfp-filter-reset:hover { background: #eef2ff; border-color: #c7d2fe; color: #4338ca; }
    .mfp-filter-actions { display: flex; align-items: center; gap: 6px; }
    .mfp-filter-actions button, .mfp-filter-reset { font-size: 12px; padding: 0 14px; display: inline-flex; align-items: center; text-decoration: none; }
    .mfp-filter-actions button i { margin-right: 6px; }
    .mfp-filter-actions button.is-primary { background: #4f46e5; border-color: #4f46e5; color: #fff; }
    .mfp-filter-actions button.is-primary:hover { background: #4338ca; }
    @media (max-width: 1200px) {
        .mfp-filter { grid-template-columns: 1fr 1fr 1fr 1fr; }
        .mfp-filter-search-field { grid-column: 1 / 3; }
        .mfp-date-presets { grid-column: 1 / -1; flex-wrap: wrap; }
        .mfp-filter-actions { grid-column: 3 / -1; justify-content: flex-end; }
    }
    @media (max-width: 640px) { .mfp-filter { grid-template-columns: 1fr 1fr; } .mfp-filter-actions { grid-column: 1 / -1; } }
    /* Nine cards on one row: compact, icon beside the number, the hint
       only where there is room for it. */
    #mfpTable { scroll-margin-top: 84px; }
    .mfp-kpis { display: grid; grid-template-columns: repeat(9, minmax(0, 1fr)); gap: 8px; }
    @media (max-width: 1100px) { .mfp-kpis { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    @media (max-width: 640px) { .mfp-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .mfp-kpi { position: relative; display: grid; grid-template-columns: 30px 1fr; grid-template-rows: auto auto; column-gap: 8px; row-gap: 2px; align-items: center; padding: 10px 10px 9px; border-radius: 10px; text-decoration: none; min-width: 0;
        background: linear-gradient(135deg, var(--mfp-a), var(--mfp-b)); border: 1px solid transparent;
        transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease; }
    .mfp-kpi-icon { grid-row: 1; grid-column: 1; display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 9px; color: #fff; font-size: 12px;
        background: linear-gradient(135deg, var(--mfp-ia), var(--mfp-ib)); }
    .mfp-kpi-num { grid-row: 1; grid-column: 2; font-size: 1.35rem; line-height: 1; font-weight: 900; color: var(--mfp-num); }
    .mfp-kpi-lbl { grid-row: 2; grid-column: 1 / -1; font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; line-height: 1.15; color: var(--mfp-lbl); }
    .mfp-kpi-hint { display: none; grid-row: 3; grid-column: 1 / -1; font-size: 10px; line-height: 1.2; color: var(--mfp-hint); }
    @media (min-width: 1500px) { .mfp-kpi-hint { display: block; } }
    .mfp-kpi.is-on .mfp-kpi-hint { display: block; }
    .mfp-kpi:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(15, 23, 42, .08); }
    .mfp-kpi.is-on { border-color: var(--mfp-ring); box-shadow: 0 0 0 3px color-mix(in srgb, var(--mfp-ring) 25%, transparent), 0 8px 20px rgba(15, 23, 42, .08); }
    /* Cancelled card: the off-flight block's dividers. */
    .mfp-table .mfp-section-row td { background: #fef2f2; border-top: 1px solid #fecaca; border-bottom: 1px solid #fecaca; color: #991b1b; font-size: 11.5px; font-weight: 700; padding: 7px 12px; }
    .mfp-table .mfp-section-row td i { margin-right: 6px; }
    .mfp-table .mfp-section-row td b { display: inline-block; min-width: 20px; margin-left: 4px; padding: 0 6px; border-radius: 999px; background: #dc2626; color: #fff; font-size: 10.5px; text-align: center; }
    .mfp-table .mfp-section-row td span { margin-left: 10px; color: #b91c1c; font-weight: 500; font-size: 10.5px; }
    .mfp-kpi.is-on::after { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; top: 6px; right: 7px; width: 16px; height: 16px; border-radius: 50%; background: var(--mfp-ring); color: #fff; font-size: 8px; display: flex; align-items: center; justify-content: center; }
</style>
@include('contract-flights.partials.passenger-table-styles')
<style>
/* Edit passenger / Edit flight / Delete / Cancel from flight live in the eye
   sheet's bar and the row's own Edit button (owner, 2026-09-30). Hidden, not
   removed — those click these originals. Here in <head>, not with the page
   scripts, so the buttons never flash on load. */
.mfp-row .mfp-actions :is(button, a):has(> .fa-user-pen, > .fa-pencil-alt, > .fa-trash, > .fa-user-xmark) { display: none; }
</style>
<style>
</style>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* Status pill — the View modal's JS (viewFlight) still injects these. */
    .mf-status-pill { display:inline-block; padding:3px 10px; border-radius:20px; font-size:10.5px; font-weight:700; }
    .mf-status-open      { background:#fef3c7; color:#92400e; }
    .mf-status-boarding  { background:#fef3c7; color:#92400e; }
    .mf-status-departed  { background:#dcfce7; color:#166534; }
    .mf-status-cancelled { background:#fee2e2; color:#991b1b; }
</style>
@endsection
@section('main-content')
@php
    $role = \Illuminate\Support\Str::slug(Auth::user()->getRoleNames()->first());
    $taka = '৳';
    // Guarded: a top-level function in a view fatals ("Cannot redeclare") the
    // moment the compiled file is evaluated twice in one process — which the
    // view engine does when it retries after a lost compile race.
    if (! function_exists('mfFmtL')) {
        function mfFmtL($n) { if($n >= 100000) return number_format($n/100000, 1).'L'; if($n >= 1000) return number_format($n/1000, 1).'K'; return number_format($n, 0); }
    }
    $mfStatusPill = [
        'open'      => 'bg-amber-100 text-amber-700',
        'boarding'  => 'bg-amber-100 text-amber-700',
        'departed'  => 'bg-emerald-100 text-emerald-700',
        'cancelled' => 'bg-red-100 text-red-700',
    ];
@endphp

@include('layout.contract-flight-tabs')

{{-- Page header --}}
<header class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm mb-4">
    <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-orange-50 text-orange-600 flex-shrink-0"><i class="fas fa-suitcase"></i></span>
    <div class="flex-1">
        <h1 class="text-lg font-bold text-slate-900">Flights &amp; Passengers</h1>
        <p class="text-xs text-slate-500">Every contract flight and the passengers on it — add, edit, ticket and bill</p>
    </div>
    {{-- .create-btn is what the Add Flight script binds to (and what ?open=create
         triggers) — the class stays, only the look changed. --}}
    {{-- Status: shows every passenger's journey strip under their row
         (partials.passenger-journey-row); hidden on every load. --}}
    <button type="button" id="mfpStatusBtn" class="mfp-status-btn rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600" onclick="mfpToggleStatus()" aria-pressed="false">
        <i class="fas fa-route mr-1"></i> Status
    </button>
    <button type="button" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600" onclick="window.print()">
        <i class="fas fa-file-export mr-1"></i> Export
    </button>
    <button type="button" class="create-btn rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700">
        <i class="fas fa-plus mr-1"></i> Add Flight
    </button>
</header>

@if(session('success'))
<div class="mb-4 flex gap-3 items-center rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
    <i class="fas fa-check-circle text-emerald-500"></i> {{ session('success') }}
</div>
@endif

{{-- Passenger KPI cards — six passenger-level counts, each a link that
     filters the table below (?pax=). The active card is outlined; clicking
     it again clears the filter. Other filters in the query string carry. --}}
@php
    $mfpQuery = request()->except(['pax', 'page']);
    // [card gradient a, b] [icon gradient a, b] number / label / hint / ring
    $mfpTones = [
        'indigo'  => ['#eef2ff', '#e0e7ff', '#818cf8', '#4f46e5', '#3730a3', '#4f46e5', '#818cf8', '#6366f1'],
        'amber'   => ['#fffbeb', '#fef3c7', '#fbbf24', '#d97706', '#92400e', '#d97706', '#fbbf24', '#f59e0b'],
        'emerald' => ['#ecfdf5', '#d1fae5', '#34d399', '#059669', '#065f46', '#059669', '#34d399', '#10b981'],
        'rose'    => ['#fff1f2', '#ffe4e6', '#fb7185', '#e11d48', '#9f1239', '#e11d48', '#fb7185', '#f43f5e'],
        'red'     => ['#fef2f2', '#fee2e2', '#f87171', '#dc2626', '#991b1b', '#dc2626', '#f87171', '#ef4444'],
        'slate'   => ['#f8fafc', '#e2e8f0', '#94a3b8', '#475569', '#1e293b', '#475569', '#94a3b8', '#64748b'],
        'sky'     => ['#f0f9ff', '#e0f2fe', '#38bdf8', '#0284c7', '#075985', '#0284c7', '#38bdf8', '#0ea5e9'],
        'violet'  => ['#f5f3ff', '#ede9fe', '#a78bfa', '#7c3aed', '#4c1d95', '#7c3aed', '#a78bfa', '#8b5cf6'],
    ];
@endphp
<div class="mfp-kpis mb-4">
    @foreach($paxKpis as $key => $kpi)
        @php
            $t = $mfpTones[$kpi['tone']];
            $on = $paxFilter === $key;
            // The link lands on the table (#mfpTable) so a click scrolls
            // straight to the filtered rows instead of leaving the cards in view.
            $href = route('role.contract-flights.index', ['role' => $role] + $mfpQuery + ($on ? [] : ['pax' => $key])) . '#mfpTable';
        @endphp
        <a href="{{ $href }}" class="mfp-kpi{{ $on ? ' is-on' : '' }}"
           style="--mfp-a:{{ $t[0] }};--mfp-b:{{ $t[1] }};--mfp-ia:{{ $t[2] }};--mfp-ib:{{ $t[3] }};--mfp-num:{{ $t[4] }};--mfp-lbl:{{ $t[5] }};--mfp-hint:{{ $t[6] }};--mfp-ring:{{ $t[7] }}"
           title="{{ $on ? 'Click to clear this filter' : 'Show only ' . strtolower($kpi['label']) }}" aria-pressed="{{ $on ? 'true' : 'false' }}">
            <span class="mfp-kpi-icon"><i class="fas {{ $kpi['icon'] }}"></i></span>
            <span class="mfp-kpi-num">{{ $kpi['count'] }}</span>
            <span class="mfp-kpi-lbl">{{ $kpi['label'] }}</span>
            <span class="mfp-kpi-hint">{{ $on ? 'Filtering · click to clear' : $kpi['hint'] }}</span>
        </a>
    @endforeach
</div>

{{-- Table card with integrated filter --}}
<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" id="mfpTable">

    {{-- Integrated filter — the Manage Bank Accounts bar with this page's
         status / category / agent selects and a departure-day date range.
         The controller still honours a bookmarked ?month=. --}}
    {{-- Filter bar — the Flight Dashboard's filter strip (search, agent,
         date presets, departure range) plus this page's own Status and
         Category. Presets fill the two date boxes and submit; nothing here
         filters client-side because the list is paginated server-side. --}}
    <form method="GET" action="{{ route('role.contract-flights.index', ['role' => $role]) }}" class="mfp-filter" role="search" aria-label="Filter passengers" id="mfpFilterForm">
        @if($paxFilter)<input type="hidden" name="pax" value="{{ $paxFilter }}">@endif
        <div class="mfp-filter-field mfp-filter-search-field">
            <label for="mfpSearch">Search</label>
            <div class="mfp-filter-search"><i class="fas fa-search" aria-hidden="true"></i><input type="search" id="mfpSearch" name="search" value="{{ request('search') }}" placeholder="Search flight, airline, route or agent…"></div>
        </div>
        <div class="mfp-filter-field">
            <label for="mfpCategory">Category</label>
            <select id="mfpCategory" name="flight_category_id" class="mfp-filter-select" data-placeholder="All categories">
                <option value="">All categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('flight_category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mfp-filter-field">
            <label for="mfpAgent">Agent</label>
            <select id="mfpAgent" name="agent_id" class="mfp-filter-select" data-placeholder="All agents">
                <option value="">All agents</option>
                @foreach($agents as $agent)
                    <option value="{{ $agent->id }}" {{ request('agent_id') == $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mfp-date-presets" id="mfpDatePresets" aria-label="Quick departure date filters">
            <button type="button" data-date-preset="today" onclick="mfpApplyDatePreset('today')">Today</button>
            <button type="button" data-date-preset="yesterday" onclick="mfpApplyDatePreset('yesterday')">Yesterday</button>
            <button type="button" data-date-preset="tomorrow" onclick="mfpApplyDatePreset('tomorrow')">Tomorrow</button>
            <button type="button" data-date-preset="week" onclick="mfpApplyDatePreset('week')">This week</button>
            <button type="button" data-date-preset="clear" onclick="mfpApplyDatePreset('clear')">Clear</button>
        </div>
        <div class="mfp-filter-field"><label for="mfpFrom">Departure from</label><input type="date" id="mfpFrom" name="from_date" value="{{ request('from_date') }}"></div>
        <div class="mfp-filter-field"><label for="mfpTo">Departure to</label><input type="date" id="mfpTo" name="to_date" value="{{ request('to_date') }}"></div>
        <div class="mfp-filter-actions">
            <button type="submit" class="is-primary"><i class="fas fa-filter"></i>Filter</button>
            <a href="{{ route('role.contract-flights.index', array_filter(['role' => $role, 'company' => request('company')])) }}" class="mfp-filter-reset">Reset</a>
        </div>
    </form>

    {{-- Table — passenger-wise, in the Flight Dashboard ledger's dress: the
         same tidy cells (ticket-legs-styles), plus Actions. Stage / Status
         returned between Tickets and Route (owner, 2026-10-29). The passenger
         is the unit of work, so each one gets a row; the flight is a column.
         Flight-level actions (edit flight & passengers / invoice / pay vendor
         / delete) ride on every row of that flight and are titled by flight
         so they are not read as acting on one person. --}}
    <div class="overflow-x-auto">
        <table class="mfp-table" data-cf-sort>
            {{-- Tickets / PNR originally went 16% → 20% (owner, 2026-09-22): measured in
                 Chrome, its widest line — "Ticket 1  KGHJH5674  purchased" —
                 needs 201px and 16% of this table gives the cell only 164px,
                 so every ticket wrapped onto three lines. The restored Stage /
                 Status column now gets its own 8%; keep these summing to 100. --}}
            <colgroup><col style="width:3%"><col style="width:15%"><col style="width:15%"><col style="width:18%"><col style="width:8%"><col style="width:13%"><col style="width:12%"><col style="width:9%"><col style="width:7%"></colgroup>
            <thead>
                <tr>
                    <th>SL</th>
                    <th>Passenger / Passport</th>
                    <th>Agent / Travel Details</th>
                    <th>Tickets / PNR</th>
                    <th>Stage / Status</th>
                    <th>Route</th>
                    <th title="What the agent is charged, then each cost line">Charge &amp; Cost Breakdown</th>
                    <th class="mfp-num">Cost / Profit</th>
                    <th class="mfp-center" data-nosort>Actions</th>
                </tr>
            </thead>
            <tbody>
                @php $paxSl = 0; $offFlight = $offFlight ?? collect(); @endphp
                {{-- Cancelled card: passengers taken off a flight come first,
                     on their own rows — they belong to no flight, so the
                     flight-wise loop below can never reach them. The two
                     data-sort-section rows keep a column sort from mixing
                     them with the passengers of cancelled flights. --}}
                @if($offFlight->isNotEmpty())
                    <tr class="mfp-section-row" data-sort-section>
                        <td colspan="9"><i class="fas fa-user-xmark"></i> Off flight — waiting for a new flight <b>{{ $offFlight->count() }}</b><span>Re-assign puts him on another flight as a reschedule, a reissue or a new ticket, with a charge · Undo puts him straight back</span></td>
                    </tr>
                    @foreach($offFlight as $op)
                        @php $paxSl++; @endphp
                        @include('contract-flights.partials.off-flight-row', ['p' => $op, 'sl' => $paxSl, 'tone' => $mfpTones['red']])
                    @endforeach
                    @if($datas->count())
                        <tr class="mfp-section-row" data-sort-section>
                            <td colspan="9"><i class="fas fa-ban"></i> On cancelled flights, or flagged Flight Cancelled</td>
                        </tr>
                    @endif
                @endif
                @forelse ($datas as $key => $flight)
                    @forelse ($flight->passengers as $p)
                        @php $paxSl++; @endphp
                        @include('contract-flights.partials.passenger-row', ['p' => $p, 'flight' => $flight, 'sl' => $paxSl, 'mode' => 'list'])
                        @include('contract-flights.partials.passenger-journey-row', ['p' => $p, 'colspan' => 9])
                    @empty
                        <tr class="cf-row" id="contract-flight-{{ $flight->id }}">
                            <td class="mfp-sl">—</td>
                            <td colspan="7"><strong>{{ $flight->flight_number }}</strong> <small style="display:inline">· {{ optional($flight->airline)->name ?? 'Airline pending' }} · {{ $flight->departure_at ? $flight->departure_at->format('d M Y, h:i A') : 'Date not set' }} — no passengers yet.</small></td>
                            <td>
                                <div class="mfp-actions">
                                    <button type="button" class="mfp-btn blue" onclick="viewFlight({{ $flight->id }})" title="View flight {{ $flight->flight_number }}"><i class="fas fa-eye"></i></button>
                                    @include('contract-flights.partials.flight-row-actions', ['flight' => $flight, 'role' => $role])
                                </div>
                            </td>
                        </tr>
                    @endforelse
                @empty
                @if($offFlight->isEmpty())
                <tr>
                    <td colspan="9" class="mfp-empty">
                        <i class="fas fa-plane-slash mb-3 block text-4xl text-slate-300"></i>
                        <p class="text-sm font-semibold text-slate-500">No contract flights found.</p>
                        @if($paxFilter)
                            {{-- A KPI card is still filtering: say so, and offer the same
                                 search without it (a name search under "Departed" found
                                 nobody because his flight had not departed). --}}
                            <p class="text-xs text-slate-500 mt-1">The <b>{{ $paxKpis[$paxFilter]['label'] }}</b> card is still filtering{{ request('search') ? ' — nobody matching "' . request('search') . '" is ' . strtolower($paxKpis[$paxFilter]['label']) : '' }}.</p>
                            <a href="{{ route('role.contract-flights.index', ['role' => $role] + request()->except(['pax', 'page'])) }}#mfpTable" class="mt-2 inline-block rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700">Show all results</a>
                        @else
                            <p class="text-xs text-slate-400 mt-1">Try adjusting your filters, or add a new contract flight.</p>
                        @endif
                    </td>
                </tr>
                @endif
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="p-4" id="mfpPager">{{ $datas->links() }}</div>
</div>

@include('contract-flights.create-modal')
@include('contract-flights.edit-modal')
@include('contract-flights.delete-modal')
@include('contract-flights.view-modal')
@include('contract-flights.passenger-edit-modal')
@include('contract-flights.pay-vendor-modal')
@include('contract-flights.partials.reassign-modal')

@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    @include('contract-flights.partials.ticketing-dropdowns')
    <script>
        const mfFlights = @json($datas->items());
        const scheduleCategories = @json($categories);

        $(document).ready(function() {
            $('#create_ticket_id').select2({ dropdownParent: $('#createModal'), placeholder: 'Select ticket route...' });
            $('#edit_ticket_id').select2({ dropdownParent: $('#editModal'), placeholder: 'Select ticket route...' });

            // "Add New Ticket Route" popup — these five never got select2
            // wired in, so they rendered as plain native <select> boxes with
            // no search (hundreds of airports, unusable without one).
            $('#tkrFromAirport, #tkrToAirport').select2({ dropdownParent: $('#tkrOverlay'), placeholder: '— Select airport —', allowClear: true });
            $('#tkrAirline').select2({ dropdownParent: $('#tkrOverlay'), placeholder: '— Select airline —' });
            $('#tkrVendor').select2({ dropdownParent: $('#tkrOverlay'), placeholder: '— Select vendor —', allowClear: true });
            $('#tkrPortal').select2({ dropdownParent: $('#tkrOverlay'), placeholder: '— Select portal —', allowClear: true });

            $.ajaxSetup({
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
            });

            // Show create modal
            $('.create-btn').click(function() {
                $('#createForm')[0].reset();
                $('#createForm .error-message').addClass('hidden');
                $("#create_ticket_id").val(null).trigger('change');
                $('#createForm input[name="handling_type"][value="manpower_wise"]').prop('checked', true);
                $('#create_status').val('open');
                $('#createModal').removeClass('hidden');
            });
            // Deferred past every ready handler: the passenger builder's own
            // .create-btn binding (operations-script, included after this
            // block) is what opens the first passenger card, and firing the
            // click here, inside the first ready callback, ran before it
            // existed — the modal opened on the "No passengers yet" state.
            const mfpParams = new URLSearchParams(window.location.search);
            if (mfpParams.get('open') === 'create') {
                setTimeout(function () { $('.create-btn').first().trigger('click'); }, 0);
            }
            // ?edit={flight id} — the dashboard ledger's "Edit flight" button.
            // Opens the Edit Flight modal for that batch if it is on this page.
            if (mfpParams.get('edit')) {
                setTimeout(function () { $('.edit-btn[data-item_id="' + mfpParams.get('edit').replace(/[^\d]/g, '') + '"]').first().trigger('click'); }, 0);
            }
            // Used once: taken out of the address bar so a later reload (every save reloads) does not
            // open the editor again (owner, 2026-11-02). ?issue / ?leg go when the builder has used them.
            if (mfpParams.has('open') || mfpParams.has('edit')) {
                const mfpClean = new URL(window.location.href);
                mfpClean.searchParams.delete('open');
                mfpClean.searchParams.delete('edit');
                window.history.replaceState(window.history.state, '', mfpClean.toString());
            }

            // Show edit modal
            $(document).on('click', '.edit-btn', function() {
                $('#editItemId').val($(this).data('item_id'));
                $('#editeForm input[name="handling_type"][value="' + ($(this).data('handling_type') || 'manpower_wise') + '"]').prop('checked', true);
                $('#edit_ticket_id').val($(this).data('ticket_id')).trigger('change');
                $('#edit_airline_flight_no').val($(this).data('airline_flight_no'));
                $('#edit_departure_at').val($(this).data('departure_at'));
                $('#edit_status').val($(this).data('status'));
                $('#edit_notes').val($(this).data('notes'));
                $('#editeForm .error-message').addClass('hidden');
                $('#editSubmit').data('action', $(this).data('action'));
                $('#editModal').removeClass('hidden');
            });

            // Close modals — the ✕ only (owner, 2026-09-22). Clicking the
            // backdrop no longer closes anything: these are long entry forms
            // and a stray click beside the box threw the work away.
            $('.modal-close-create').click(function() { $('#createModal').addClass('hidden'); });
            $('.modal-close-edit').click(function()   { $('#editModal').addClass('hidden'); });
            $('.modal-close-delete').click(function() { $('#deleteModal').addClass('hidden'); });
            $('.modal-close-view').click(function()   { $('#viewModal').addClass('hidden'); });

            // Create submit
            $('#createSubmit').click(function(e) {
                e.preventDefault();
                if (validateCreateForm()) {
                    $.ajax({
                        url: $('#createForm').attr('action'),
                        method: 'POST',
                        data: $('#createForm').serialize(),
                        success: function (response) {
                            if (response.success) {
                                Swal.fire({ icon: 'success', title: 'Done', text: 'Contract flight created successfully!' });
                                $('#createModal').addClass('hidden');
                                $('#createForm')[0].reset();
                                setTimeout(() => window.location.reload(), 800);
                            } else {
                                Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Something went wrong.' });
                            }
                        },
                        error: function (xhr) {
                            console.error(xhr.responseText);
                            Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to create contract flight.' });
                        }
                    });
                }
            });

            // Edit submit
            $('#editSubmit').click(function() {
                if (validateEditForm()) {
                    $.ajax({
                        url: $(this).data('action'),
                        method: 'POST',
                        data: $('#editeForm').serialize() + '&_method=PUT',
                        success: function (response) {
                            if (response.success) {
                                Swal.fire({ icon: 'success', title: 'Done', text: 'Contract flight updated successfully!' });
                                $('#editModal').addClass('hidden');
                                setTimeout(() => window.location.reload(), 800);
                            } else {
                                Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Update failed.' });
                            }
                        },
                        error: function (xhr) {
                            console.error('Error:', xhr.responseText);
                            Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong!' });
                        }
                    });
                }
            });

            // Delete confirmation
            $('#confirmDeleteBtn').click(function() {
                const dataId = $(this).data('item-id');
                const deleteUrl = $(this).data('action');
                $.ajax({
                    url: deleteUrl,
                    method: 'DELETE',
                    data: { item_id: dataId },
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Contract flight deleted successfully!' });
                            $('#deleteModal').addClass('hidden');
                            setTimeout(() => window.location.reload(), 500);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops...', text: response.message });
                        }
                    },
                    error: function (xhr) {
                        console.error('Error:', xhr.responseText);
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong!' });
                    }
                });
            });
        });

        function validateCreateForm() {
            return validateFlightForm('create');
        }

        function validateEditForm() {
            return validateFlightForm('edit');
        }

        // Quick-create Ticket Route: contract-flights.partials.ticket-route-quick-create-script

        function confirmDelete(id, name) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            const role = '{{ $role }}';
            $('#confirmDeleteBtn').data('action', `/${role}/contract-flights/${id}`);
            $('#deleteModal').removeClass('hidden');
        }

        // ── Pay Vendor ────────────────────────────────────────────────
        let _payVendorFlightId = null;

        function openPayVendorModal(id, flightNumber, vendorName, dueAmount) {
            _payVendorFlightId = id;
            $('#pv_flight_number').text(flightNumber);
            $('#pv_vendor_name').text(vendorName);
            $('#pv_due_amount').text(Number(dueAmount).toFixed(2));
            $('#pv_bank_id').val('');
            $('#pv_payment_date').val('{{ now()->toDateString() }}');
            $('#pv_payment_amount').val(dueAmount);
            $('#pv_reference_no').val('');
            $('#pv_error').addClass('hidden').text('');
            $('#payVendorModal').removeClass('hidden');
        }

        $('.modal-close-pay-vendor').on('click', function () {
            $('#payVendorModal').addClass('hidden');
            _payVendorFlightId = null;
        });

        $('#pv_submit_btn').on('click', function () {
            const role = '{{ $role }}';
            const err  = $('#pv_error');
            err.addClass('hidden').text('');

            if (!$('#pv_bank_id').val()) {
                err.removeClass('hidden').text('Please select a bank.');
                return;
            }
            if (!$('#pv_payment_amount').val() || Number($('#pv_payment_amount').val()) <= 0) {
                err.removeClass('hidden').text('Please enter a valid payment amount.');
                return;
            }

            $.ajax({
                url: `/${role}/contract-flights/${_payVendorFlightId}/pay-vendor`,
                method: 'POST',
                data: {
                    bank_id:        $('#pv_bank_id').val(),
                    payment_date:   $('#pv_payment_date').val(),
                    payment_amount: $('#pv_payment_amount').val(),
                    reference_no:   $('#pv_reference_no').val(),
                },
                success: function (response) {
                    if (response.success) {
                        $('#payVendorModal').addClass('hidden');
                        window.location.reload();
                    } else {
                        err.removeClass('hidden').text(response.message || 'Payment failed.');
                    }
                },
                error: function (xhr) {
                    err.removeClass('hidden').text(xhr.responseJSON?.message || 'Payment failed.');
                }
            });
        });

        function viewFlight(id) {
            const f = mfFlights.find(x => x.id === id);
            if (!f) return;
            $('#view_flight_number').text(f.flight_number);
            $('#view_airline').text((f.airline ? f.airline.name : '—') + (f.airline_flight_no ? ' (' + f.airline_flight_no + ')' : ''));
            $('#view_route').text(f.route || '—');
            $('#view_departure').text(f.departure_at ? new Date(f.departure_at).toLocaleString() : '—');
            $('#view_category').text(f.flight_category ? f.flight_category.name : '—');
            $('#view_category_type').text(f.category_type ? f.category_type.name : '-');
            $('#view_handling_type').text((f.handling_type || 'manpower_wise').replace('_', '-').replace(/\b\w/g, c => c.toUpperCase()));
            $('#view_immigration_tracking_note').toggleClass('hidden', f.handling_type !== 'immigration_wise');
            $('#view_agent').text(f.agent_summary || 'Direct');
            $('#view_total_agent_charge, #view_total_travel_cost, #view_total_profit').text('৳0');
            $('#view_status').html(`<span class="mf-status-pill mf-status-${f.status}">${f.status.charAt(0).toUpperCase() + f.status.slice(1)}</span>`);
            $('#view_notes').text(f.notes || '—');
            $('#viewModal').removeClass('hidden');
            $('#fcm-cmt-placeholder').html(cmtHtml('contract_flight', f.id));
            loadComments('contract_flight', f.id);
        }
    </script>
    <script>
    // Filter-bar dropdowns get select2 for its search box. Own class, so the
    // Add Flight modal's select2 widgets are left untouched.
    $(function () {
        $('.mfp-filter-select').select2({ width: '100%', allowClear: true, placeholder: function () { return $(this).data('placeholder'); } });
    });
    // Date presets on the filter bar: fill the two boxes and submit. Same
    // week rule as the Flight Dashboard (Monday to Sunday). On load, the
    // preset matching the boxes' current values is shown active.
    function mfpResetUrl() {
        const base = @json(route('role.contract-flights.index', ['role' => $role]));
        const company = new URLSearchParams(window.location.search).get('company');
        return company ? base + '?company=' + encodeURIComponent(company) : base;
    }
    function mfpLocalDate(d) { return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; }
    function mfpPresetRange(preset) {
        const today = new Date();
        if (preset === 'week') {
            const day = today.getDay(), monday = new Date(today);
            monday.setDate(today.getDate() + (day === 0 ? -6 : 1 - day));
            const sunday = new Date(monday); sunday.setDate(monday.getDate() + 6);
            return [mfpLocalDate(monday), mfpLocalDate(sunday)];
        }
        if (preset === 'clear') return ['', ''];
        const d = new Date(today); d.setDate(today.getDate() + (preset === 'yesterday' ? -1 : preset === 'tomorrow' ? 1 : 0));
        return [mfpLocalDate(d), mfpLocalDate(d)];
    }
    function mfpApplyDatePreset(preset) {
        // Clear = every filter off (search, category, agent, dates and the
        // highlighted card), not just the dates — only ?company= is kept so
        // the sidebar stays on this company.
        if (preset === 'clear') { window.location.href = mfpResetUrl(); return; }
        const [from, to] = mfpPresetRange(preset);
        document.getElementById('mfpFrom').value = from;
        document.getElementById('mfpTo').value = to;
        document.getElementById('mfpFilterForm').submit();
    }
    (function () {
        const from = document.getElementById('mfpFrom').value, to = document.getElementById('mfpTo').value;
        if (!from && !to) return;
        document.querySelectorAll('#mfpDatePresets button').forEach(button => {
            const [f, t] = mfpPresetRange(button.dataset.datePreset);
            button.classList.toggle('is-active', button.dataset.datePreset !== 'clear' && f === from && t === to);
        });
    })();
    </script>
    @include('contract-flights.partials.ticket-route-quick-create-script')
    @include('contract-flights.operations-script')
    @include('contract-flights.partials.reassign-script')
    @include('contract-flights.partials.suspend-withdraw-script')
    @include('contract-flights.partials.change-correction'){{-- super admin: Flight changes → Correct (2026-10-19) --}}
    @include('contract-flights.partials.table-sort')
    @include('contract-flights.partials.passenger-journey-assets')
    {{-- The stage editor the journey card's ✎ opens — the dashboard's, shared. --}}
    @include('contract-flights.partials.stage-editor')
    @include('contract-flights.partials.passenger-sheet-list')
    @include('components.comment-panel')
    <style>#mfpTable.is-loading .mfp-table tbody { opacity: .55; transition: opacity .15s; }</style>
    <script>
    // Live search (owner, 2026-10-01: "why not happening live while typing?").
    // The list is paginated on the server, so typing fetches this same page with
    // the bar's filters and swaps in the KPI cards, the rows and the page links.
    // The search box itself is never replaced, so focus and the caret stay put;
    // the address bar follows, so a reload or a shared link shows the same list.
    // The Filter button still submits the whole bar as before.
    (function () {
        const form = document.getElementById('mfpFilterForm');
        const input = document.getElementById('mfpSearch');
        const card = document.getElementById('mfpTable');
        if (!form || !input || !card || !window.fetch || !window.DOMParser) return;
        let timer = null, ctrl = null, last = input.value.trim();

        const target = () => {
            const params = new URLSearchParams();
            for (const [k, v] of new FormData(form)) if (v !== '') params.append(k, v);
            return form.action + (params.toString() ? '?' + params : '');
        };

        async function run() {
            const q = input.value.trim();
            if (q === last) return;
            last = q;
            ctrl?.abort();
            ctrl = new AbortController();
            const url = target();
            card.classList.add('is-loading');
            try {
                const res = await fetch(url, { headers: { Accept: 'text/html' }, credentials: 'same-origin', signal: ctrl.signal });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const html = await res.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const swap = sel => { const a = document.querySelector(sel), b = doc.querySelector(sel); if (a && b) a.replaceWith(b); };
                swap('.mfp-kpis');
                swap('#mfpTable .mfp-table tbody');
                swap('#mfpPager');
                // Edit reads the page's flights from mfFlights — keep it in step.
                const m = html.match(/const mfFlights = (\[[\s\S]*?\]);\s*const scheduleCategories/);
                if (m) { try { mfFlights.splice(0, mfFlights.length, ...JSON.parse(m[1])); } catch (_) {} }
                // The status tracker rows follow the Status button, as on load.
                const on = document.getElementById('mfpStatusBtn')?.classList.contains('is-on');
                document.querySelectorAll('.mfp-journey-row').forEach(tr => { tr.hidden = !on; });
                if (window.cfSortReapply) cfSortReapply(document.querySelector('#mfpTable .mfp-table'));
                if (window.mfpTicketLogos) mfpTicketLogos();
                history.replaceState(null, '', url + location.hash);
            } catch (e) {
                if (e.name !== 'AbortError') last = null;   // let the next keystroke retry
            } finally {
                if (!ctrl.signal.aborted) card.classList.remove('is-loading');
            }
        }

        input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(run, 300); });
        // Enter searches at once instead of reloading the page.
        input.addEventListener('keydown', e => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            clearTimeout(timer);
            run();
        });
    })();
    </script>
@endsection
