@extends('layout.app')
@section('meta-information')
    <title>Flight Schedule</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .mf-status-open, .mf-status-boarding { background:#fef3c7; color:#92400e; border-radius:20px; padding:3px 10px; font-size:10.5px; font-weight:800; display:inline-block; }
    .mf-status-departed { background:#dcfce7; color:#166534; border-radius:20px; padding:3px 10px; font-size:10.5px; font-weight:800; display:inline-block; }
    .mf-status-cancelled { background:#fee2e2; color:#991b1b; border-radius:20px; padding:3px 10px; font-size:10.5px; font-weight:800; display:inline-block; }
    .swal2-container { z-index: 100000 !important; }
    .select2-container--open, .select2-dropdown { z-index: 99999; }

    .mf-ready-pill { display:inline-flex; align-items:center; gap:3px; border-radius:20px; padding:2px 7px; font-size:9.5px; font-weight:800; white-space:nowrap; margin:1px 2px 1px 0; }
    .mf-ready-pill i { font-size:9px; }
    .mf-ready-ok { background:#dcfce7; color:#166534; }
    .mf-ready-warn { background:#fef3c7; color:#92400e; }
    .mf-ready-bad { background:#fee2e2; color:#991b1b; }
    .mf-ready-none { background:#f1f5f9; color:#64748b; }

    /* Itinerary card — one per flight, modeled on the airline e-ticket
       confirmation slip (airline/flight header, DAC ▸ BAH route block, times,
       duration). Real fields only: this ERP has no aircraft type, terminal,
       meal or seat-map data, so those rows from a GDS slip are left out
       rather than faked. */
    .fs-card { position:relative; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; margin-bottom:10px; background:#fff; transition:box-shadow .15s,border-color .15s; }
    .fs-card:hover { box-shadow:0 4px 16px rgba(30,50,100,.10); border-color:#d0d6e8; }
    /* A 3px status rail on the left edge — same signal as the status pill,
       readable while scanning down the list without reading any text.
       Matches contract-flights/index.blade.php's .mfc-card rail so both
       screens in this section read the same way. */
    .fs-card::before { content:''; position:absolute; left:0; top:0; bottom:0; width:3px; z-index:1; }
    .fs-card.st-open::before, .fs-card.st-boarding::before { background:#f59e0b; }
    .fs-card.st-departed::before { background:#16a34a; }
    .fs-card.st-cancelled::before { background:#dc2626; }
    .fs-card-top { display:flex; align-items:center; gap:8px; padding:7px 12px 7px 15px; background:#0f172a; color:#fff; }
    .fs-card-top .fs-plane { font-size:13px; transform:rotate(45deg); opacity:.9; }
    .fs-card-top .fs-dep-label { font-size:9px; font-weight:700; letter-spacing:.04em; opacity:.75; text-transform:uppercase; }
    .fs-card-top .fs-dep-date { font-size:12px; font-weight:800; }
    .fs-card-top .fs-spacer { flex:1; }

    .fs-card-body { display:grid; grid-template-columns:1.3fr 1.6fr 1fr 1.1fr; gap:0; }
    @media(max-width:900px){ .fs-card-body { grid-template-columns:1fr 1fr; } }
    .fs-cell { padding:10px 12px; border-right:1px solid #eef1f6; }
    .fs-cell:last-child { border-right:none; }
    @media(max-width:900px){ .fs-cell:nth-child(2n) { border-right:none; } .fs-cell { border-bottom:1px solid #eef1f6; } }

    .fs-airline-name { font-size:13.5px; font-weight:800; color:#0f172a; line-height:1.2; }
    .fs-flight-no { font-size:10.5px; font-weight:700; color:#64748b; margin-top:1px; }

    .fs-route { display:flex; align-items:center; gap:7px; }
    .fs-route-side { min-width:0; }
    .fs-route-code { font-size:16px; font-weight:800; color:#0f172a; line-height:1; }
    .fs-route-place { font-size:9.5px; color:#64748b; font-weight:600; text-transform:uppercase; margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:150px; }
    .fs-route-arrow { color:#94a3b8; font-size:11px; }

    .fs-label { font-size:9px; font-weight:800; letter-spacing:.03em; text-transform:uppercase; color:#94a3b8; }
    .fs-value { font-size:13.5px; font-weight:800; color:#0f172a; font-family:ui-monospace,monospace; margin-top:1px; }
    .fs-sub { font-size:10.5px; color:#64748b; font-weight:600; margin-top:4px; }
    .fs-sub + .fs-sub { margin-top:3px; }

    .fs-actions { display:flex; align-items:center; gap:5px; }

    .fs-pax-wrap { padding:9px 12px 12px; background:#f8fafc; border-top:1px solid #eef1f6; }
    .fs-pax-agent-label { font-size:9.5px; font-weight:800; text-transform:uppercase; letter-spacing:.03em; color:#64748b; margin:7px 0 4px; }
    .fs-pax-agent-label:first-child { margin-top:0; }
    .fs-pax-grid { display:flex; flex-wrap:wrap; gap:6px; }
    .fs-pax-box { display:flex; align-items:center; gap:6px; border:1px solid #e2e8f0; background:#fff; border-radius:9px; padding:5px 8px; min-width:150px; }
    .fs-pax-avatar { width:22px; height:22px; border-radius:50%; background:#e0e7ff; color:#3730a3; display:flex; align-items:center; justify-content:center; font-size:9.5px; font-weight:800; flex-shrink:0; }
    .fs-pax-name { font-size:11px; font-weight:700; color:#0f172a; }
    .fs-pax-meta { font-size:9.5px; color:#64748b; font-weight:600; }
    .fs-pax-flag { margin-left:2px; }
    .fs-pax-empty { font-size:11px; color:#94a3b8; font-style:italic; }

    html.dark .fs-card { background:#1e293b; border-color:#334155; }
    html.dark .fs-cell { border-color:#334155; }
    html.dark .fs-airline-name, html.dark .fs-route-code, html.dark .fs-value { color:#f1f5f9; }
    html.dark .fs-pax-wrap { background:#0f172a; border-color:#334155; }
    html.dark .fs-pax-box { background:#1e293b; border-color:#334155; }
    html.dark .fs-pax-name { color:#f1f5f9; }
</style>
@endsection
@section('main-content')
@php
    $role = \Illuminate\Support\Str::slug(Auth::user()->getRoleNames()->first());
    $today = now()->startOfDay();
    $groupedFlights = $datas->groupBy(fn($flight) => $flight->departure_at ? $flight->departure_at->toDateString() : 'unscheduled');
    $labelForDate = function ($dateKey) use ($today) {
        if ($dateKey === 'unscheduled') return 'Unscheduled Flights';
        $date = \Carbon\Carbon::parse($dateKey);
        $prefix = $date->isSameDay((clone $today)->subDay()) ? 'Yesterday' : ($date->isSameDay($today) ? 'Today' : ($date->isSameDay((clone $today)->addDay()) ? 'Tomorrow' : $date->format('l')));
        return $prefix . ' - ' . $date->format('d M Y (l)');
    };
    $departedCount = fn($flights) => $flights->where('status', 'departed')->count();
    $flightStoreRoute = route('role.flight-schedules.store', ['role' => $role]);
    $flightCreateTitle = 'Add Flight';
    $flightCreateSub = 'Create a scheduled contract flight';
    $flightEditTitle = 'Edit Flight';
    $flightEditSub = 'Update scheduled flight details';
@endphp

@include('layout.contract-flight-tabs')

<header class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm mb-4">
    <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-blue-50 text-blue-600 flex-shrink-0">
        <i class="fas fa-calendar-alt"></i>
    </span>
    <div class="flex-1">
        <h1 class="text-lg font-bold text-slate-900">Flight Schedule</h1>
        <p class="text-xs text-slate-500">Contract flights - yesterday / today / tomorrow + filter by date or category</p>
    </div>
    <button class="create-btn rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700"><i class="fas fa-plus mr-1"></i> Add Flight</button>
</header>

@if($reminderFlights->isNotEmpty())
<div class="flex items-center gap-4 rounded-2xl bg-gradient-to-r from-orange-500 to-red-500 text-white px-5 py-4 mb-4 shadow-lg" id="scheduleReminder">
    <div class="h-11 w-11 rounded-xl bg-white/20 flex items-center justify-center text-xl flex-shrink-0"><i class="fas fa-clock"></i></div>
    <div class="flex-1 min-w-0">
        <div class="text-sm font-bold">Reminder - tomorrow ({{ now()->addDay()->format('d M Y') }}) {{ $reminderFlights->count() }} flights scheduled</div>
        <div class="text-xs mt-1 font-semibold opacity-95">
            {{ $reminderFlights->sum(fn($f) => $f->passengers->count()) }} passengers -
            first departure {{ optional($reminderFlights->first()->departure_at)->format('h:i A') }} -
            {{ $reminderFlights->first()->airline?->name ?? 'Airline pending' }}
            {{ $reminderFlights->first()->airline_flight_no ? '(' . $reminderFlights->first()->airline_flight_no . ')' : '' }}
            {{ $reminderFlights->first()->route ? ' ' . $reminderFlights->first()->route : '' }}
        </div>
    </div>
    <button type="button" class="rounded-xl bg-white text-orange-700 px-4 py-2 text-xs font-bold cursor-pointer"><i class="fas fa-bell"></i> Send Reminders</button>
    <button type="button" class="h-7 w-7 rounded-lg border border-white/40 bg-white/15 text-white cursor-pointer" onclick="document.getElementById('scheduleReminder').style.display='none'">x</button>
</div>
@endif

<section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <div class="rounded-xl bg-amber-50 p-4">
        <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-orange-400 to-orange-600 flex items-center justify-center text-white text-xl mb-3 shadow-md"><i class="fas fa-chart-bar"></i></div>
        <div class="text-3xl font-black text-amber-900 leading-none mb-1">{{ $stats['yesterday_count'] }}</div>
        <div class="text-[10px] font-bold uppercase tracking-wide text-orange-700">Yesterday ({{ $stats['yesterday_label'] }})</div>
        <div class="text-xs text-slate-500 mt-1.5 font-semibold">Departed flights</div>
    </div>
    <div class="rounded-xl bg-blue-50 p-4">
        <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-blue-400 to-blue-700 flex items-center justify-center text-white text-xl mb-3 shadow-md"><i class="fas fa-plane"></i></div>
        <div class="text-3xl font-black text-blue-900 leading-none mb-1">{{ $stats['today_count'] }}</div>
        <div class="text-[10px] font-bold uppercase tracking-wide text-blue-700">Today ({{ $stats['today_label'] }})</div>
        <div class="text-xs text-slate-500 mt-1.5 font-semibold">{{ $stats['today_departed'] }} departed, {{ $stats['today_upcoming'] }} upcoming</div>
    </div>
    <div class="rounded-xl bg-emerald-50 p-4">
        <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center text-white text-xl mb-3 shadow-md"><i class="fas fa-user-clock"></i></div>
        <div class="text-3xl font-black text-emerald-900 leading-none mb-1">{{ $stats['tomorrow_count'] }}</div>
        <div class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Tomorrow ({{ $stats['tomorrow_label'] }})</div>
        <div class="text-xs text-slate-500 mt-1.5 font-semibold">Scheduled flights</div>
    </div>
    <div class="rounded-xl bg-violet-50 p-4">
        <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-violet-400 to-violet-700 flex items-center justify-center text-white text-xl mb-3 shadow-md"><i class="fas fa-chart-line"></i></div>
        <div class="text-3xl font-black text-violet-900 leading-none mb-1">{{ $stats['week_count'] }}</div>
        <div class="text-[10px] font-bold uppercase tracking-wide text-violet-700">This Week</div>
        <div class="text-xs text-slate-500 mt-1.5 font-semibold">Total contract flights</div>
    </div>
</section>

{{-- The Manage Banks filter bar, with category / status slotted in. With no
     date chosen the list is its usual yesterday–tomorrow window; a range from
     the bar replaces that window on the departure day. --}}
<div class="lfb-panel" style="margin-bottom:16px;">
 <form method="GET" action="{{ route('role.flight-schedules.index', ['role' => $role]) }}">
  <x-list-filter-bar search="search" placeholder="Search flight #, airline, route…"
                     :reset="route('role.flight-schedules.index', ['role' => $role])"
                     :total="$datas->count()">
   <select name="flight_category_id" onchange="this.form.requestSubmit()" aria-label="Category">
    <option value="">All Categories</option>
    @foreach($categories as $cat)
     <option value="{{ $cat->id }}" {{ request('flight_category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
    @endforeach
   </select>
   <select name="status" onchange="this.form.requestSubmit()" aria-label="Status">
    <option value="">All Status</option>
    <option value="open" {{ request('status') == 'open' ? 'selected' : '' }}>Open</option>
    <option value="boarding" {{ request('status') == 'boarding' ? 'selected' : '' }}>Boarding</option>
    <option value="departed" {{ request('status') == 'departed' ? 'selected' : '' }}>Departed</option>
    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
   </select>
  </x-list-filter-bar>
 </form>
</div>

@forelse($groupedFlights as $dateKey => $flights)
<div class="mb-4">
    <div class="flex items-center gap-2 px-1 py-1.5">
        <i class="fas fa-chart-bar text-orange-500"></i>
        <div class="text-sm font-bold text-slate-900">{{ $labelForDate($dateKey) }}</div>
        <div class="rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-bold text-orange-700">{{ $departedCount($flights) ?: $flights->count() }} {{ $departedCount($flights) ? 'flights departed' : 'flights scheduled' }}</div>
    </div>

    @foreach($flights as $flight)
    @php
        $r = $flight->readiness_summary;
        $duration = ($flight->departure_at && $flight->arrival_at)
            ? $flight->departure_at->diff($flight->arrival_at)->format('%hh %im')
            : null;
        $paxByAgent = $flight->passengers->groupBy(fn ($p) => $p->agent?->name ?? 'Unassigned / Direct');
    @endphp
    <div class="fs-card st-{{ $flight->status }}">
        <div class="fs-card-top">
            <i class="fas fa-paper-plane fs-plane"></i>
            <div>
                <div class="fs-dep-label">Departure</div>
                <div class="fs-dep-date">{{ $flight->departure_at ? $flight->departure_at->format('l, d M Y') : 'Date not set' }}</div>
            </div>
            <div class="fs-spacer"></div>
            <span class="mf-status-pill mf-status-{{ $flight->status }}">{{ ucfirst($flight->status) }}</span>
        </div>

        <div class="fs-card-body">
            <div class="fs-cell">
                <div class="fs-airline-name">{{ optional($flight->airline)->name ?? 'Airline pending' }}</div>
                <div class="fs-flight-no">{{ $flight->airline_flight_no ?: $flight->flight_number }}</div>
                <div class="fs-sub"><i class="fas fa-tag text-[9px] mr-1"></i>{{ $flight->category_summary }}</div>
                <div class="fs-sub"><i class="fas fa-user-tie text-[9px] mr-1"></i>{{ $flight->agent_summary }}</div>
            </div>

            <div class="fs-cell">
                @php
                    $fromLabel = optional($flight->ticket?->from_airport)->name . ($flight->ticket?->from_airport?->country ? ', ' . $flight->ticket->from_airport->country->name : '');
                    $toLabel = optional($flight->ticket?->to_airport)->name . ($flight->ticket?->to_airport?->country ? ', ' . $flight->ticket->to_airport->country->name : '');
                @endphp
                <div class="fs-route">
                    <div class="fs-route-side">
                        <div class="fs-route-code">{{ optional($flight->ticket?->from_airport)->code ?? '—' }}</div>
                        <div class="fs-route-place" title="{{ $fromLabel }}">{{ $fromLabel }}</div>
                    </div>
                    <i class="fas fa-arrow-right-long fs-route-arrow"></i>
                    <div class="fs-route-side">
                        <div class="fs-route-code">{{ optional($flight->ticket?->to_airport)->code ?? '—' }}</div>
                        <div class="fs-route-place" title="{{ $toLabel }}">{{ $toLabel }}</div>
                    </div>
                </div>
                <div class="fs-sub" style="margin-top:7px;">
                    <span class="fs-label">Departing At</span><br>
                    <span class="fs-value" style="font-size:12px;">{{ $flight->departure_at ? $flight->departure_at->format('h:i A') : '-' }}</span>
                    @if($duration) &nbsp;<span class="fs-label" style="text-transform:none;">({{ trim($duration) }})</span> @endif
                </div>
            </div>

            <div class="fs-cell">
                <div class="fs-label">Passengers</div>
                <div class="fs-value">{{ $flight->passengers->count() }}</div>
                <div class="fs-sub">Revenue: ৳{{ number_format($flight->revenue, 0) }}</div>
            </div>

            <div class="fs-cell">
                <div class="fs-label">Readiness</div>
                <div style="margin-top:3px;">
                    @if($r['total'] === 0)
                        <span class="mf-ready-pill mf-ready-none">No passengers yet</span>
                    @else
                        <span class="mf-ready-pill {{ $r['unticketed'] > 0 ? 'mf-ready-warn' : 'mf-ready-ok' }}">
                            <i class="fas fa-ticket-alt"></i> {{ $r['ticketed'] }}/{{ $r['total'] }} ticketed
                        </span>
                        @if($r['needs_immigration_officer'])
                            <span class="mf-ready-pill mf-ready-bad" title="A passenger needs immigration support but no officer is assigned"><i class="fas fa-user-shield"></i> No officer</span>
                        @endif
                        @if($r['flagged'] > 0)
                            <span class="mf-ready-pill mf-ready-bad" title="Cancelled / rescheduled / transit / deported"><i class="fas fa-flag"></i> {{ $r['flagged'] }} flagged</span>
                        @endif
                    @endif
                </div>
                <div class="fs-actions" style="margin-top:7px;">
                    <button type="button" class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600" onclick="viewFlight({{ $flight->id }})" title="View"><i class="fas fa-eye text-xs"></i></button>
                    <button type="button" class="flex h-7 w-7 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100 edit-btn"
                        data-item_id="{{ $flight->id }}"
                        data-action="{{ route('role.flight-schedules.update', ['role' => $role, 'flight_schedule' => $flight->id]) }}"
                        data-handling_type="{{ $flight->handling_type }}"
                        data-ticket_id="{{ $flight->ticket_id }}"
                        data-airline_flight_no="{{ $flight->airline_flight_no }}"
                        data-departure_at="{{ $flight->departure_at ? $flight->departure_at->format('Y-m-d\TH:i') : '' }}"
                        data-cost_price="{{ $flight->cost_price }}"
                        data-revenue="{{ $flight->revenue }}"
                        data-agent_id="{{ $flight->agent_id }}"
                        data-status="{{ $flight->status }}"
                        data-notes="{{ $flight->notes }}"
                        title="Edit"><i class="fas fa-edit text-xs"></i></button>
                    <button type="button" class="flex h-7 w-7 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100" onclick="confirmDelete('{{ $flight->id }}', '{{ addslashes($flight->flight_number) }}')" title="Delete"><i class="fas fa-trash text-xs"></i></button>
                </div>
            </div>
        </div>

        <div class="fs-pax-wrap">
            @forelse($paxByAgent as $agentName => $paxGroup)
                <div class="fs-pax-agent-label"><i class="fas fa-user-tie mr-1"></i>{{ $agentName }} — {{ $paxGroup->count() }} {{ \Illuminate\Support\Str::plural('passenger', $paxGroup->count()) }}</div>
                <div class="fs-pax-grid">
                    @foreach($paxGroup as $p)
                        <div class="fs-pax-box" title="{{ $p->passportHolder?->passport_no }}">
                            <div class="fs-pax-avatar">{{ strtoupper(substr($p->passportHolder?->name ?? '?', 0, 1)) }}</div>
                            <div>
                                <div class="fs-pax-name">
                                    {{ $p->passportHolder?->name ?? 'Unnamed' }}
                                    @if($p->ticketPurchases->isNotEmpty())
                                        <i class="fas fa-check-circle text-emerald-500 fs-pax-flag" title="Ticketed"></i>
                                    @else
                                        <i class="fas fa-exclamation-circle text-amber-500 fs-pax-flag" title="Not ticketed yet"></i>
                                    @endif
                                    @if($p->exception_flag)
                                        <i class="fas fa-flag text-red-500 fs-pax-flag" title="{{ \App\Models\ContractFlightPassenger::EXCEPTION_FLAGS[$p->exception_flag] ?? $p->exception_flag }}"></i>
                                    @endif
                                </div>
                                <div class="fs-pax-meta">{{ $p->flightCategory?->name ?? 'No category' }} · {{ \App\Models\ContractFlightPassenger::TICKET_CLASSES[$p->ticket_class] ?? $p->ticket_class }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @empty
                <div class="fs-pax-empty">No passengers added to this flight yet.</div>
            @endforelse
        </div>
    </div>
    @endforeach
</div>
@empty
<div class="overflow-hidden rounded-xl bg-white shadow-sm mb-4">
    <div class="p-12 text-center text-sm text-slate-500">
        <i class="fas fa-plane-slash text-4xl opacity-30 mb-3 block"></i>
        <h4 class="font-bold mb-1">No scheduled flights found</h4>
        <p>Try another date or add a new flight.</p>
    </div>
</div>
@endforelse

@include('contract-flights.create-modal')
@include('contract-flights.edit-modal')
@include('contract-flights.delete-modal')
@include('contract-flights.view-modal')
@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    @include('contract-flights.partials.ticketing-dropdowns')
    <script>
        const mfFlights = @json($datas->values());
        const scheduleCategories = @json($categories);
        $(document).ready(function() {
            $("#create_agent_id").select2({ dropdownParent: $('#createModal') });
            $("#edit_agent_id").select2({ dropdownParent: $('#editModal') });
            $('#create_ticket_id').select2({ dropdownParent: $('#createModal'), placeholder: 'Select ticket route...' });
            $('#edit_ticket_id').select2({ dropdownParent: $('#editModal'), placeholder: 'Select ticket route...' });
            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
            $('.create-btn').click(function() {
                $('#createForm')[0].reset();
                $('#createForm .error-message').addClass('hidden');
                $("#create_agent_id, #create_ticket_id").val(null).trigger('change');
                $('#createForm input[name="handling_type"][value="manpower_wise"]').prop('checked', true);
                $('#create_status').val('open');
                $('#createModal').removeClass('hidden');
            });
            if (new URLSearchParams(window.location.search).get('open') === 'create') {
                $('.create-btn').first().trigger('click');
            }
            $('.edit-btn').click(function() {
                $('#editItemId').val($(this).data('item_id'));
                $('#editeForm input[name="handling_type"][value="' + ($(this).data('handling_type') || 'manpower_wise') + '"]').prop('checked', true);
                $('#edit_ticket_id').val($(this).data('ticket_id')).trigger('change');
                $('#edit_airline_flight_no').val($(this).data('airline_flight_no'));
                $('#edit_departure_at').val($(this).data('departure_at'));
                $('#edit_cost_price').val($(this).data('cost_price'));
                $('#edit_revenue').val($(this).data('revenue'));
                $('#edit_agent_id').val($(this).data('agent_id')).trigger('change');
                $('#edit_status').val($(this).data('status'));
                $('#edit_notes').val($(this).data('notes'));
                $('#editeForm .error-message').addClass('hidden');
                $('#editSubmit').data('action', $(this).data('action'));
                $('#editModal').removeClass('hidden');
            });
            // The ✕ only (owner, 2026-09-22) — the backdrop no longer closes
            // these, so a stray click beside the box cannot discard entry work.
            $('.modal-close-create').click(function() { $('#createModal').addClass('hidden'); });
            $('.modal-close-edit').click(function()   { $('#editModal').addClass('hidden'); });
            $('.modal-close-delete').click(function() { $('#deleteModal').addClass('hidden'); });
            $('.modal-close-view').click(function()   { $('#viewModal').addClass('hidden'); });
            $('#createSubmit').click(function(e) {
                e.preventDefault();
                if (!validateCreateForm()) return;
                $.ajax({
                    url: $('#createForm').attr('action'), method: 'POST', data: $('#createForm').serialize(),
                    success: function(response) {
                        if (response.success) { Swal.fire({ icon: 'success', title: 'Done', text: 'Flight scheduled successfully!' }); $('#createModal').addClass('hidden'); setTimeout(() => window.location.reload(), 800); }
                        else { Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Something went wrong.' }); }
                    },
                    error: function(xhr) { console.error(xhr.responseText); Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to schedule flight.' }); }
                });
            });
            $('#editSubmit').click(function() {
                if (!validateEditForm()) return;
                $.ajax({
                    url: $(this).data('action'), method: 'POST', data: $('#editeForm').serialize() + '&_method=PUT',
                    success: function(response) {
                        if (response.success) { Swal.fire({ icon: 'success', title: 'Done', text: 'Flight updated successfully!' }); $('#editModal').addClass('hidden'); setTimeout(() => window.location.reload(), 800); }
                        else { Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Update failed.' }); }
                    },
                    error: function(xhr) { console.error(xhr.responseText); Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong!' }); }
                });
            });
            $('#confirmDeleteBtn').click(function() {
                const dataId = $(this).data('item-id');
                $.ajax({
                    url: $(this).data('action'), method: 'DELETE', data: { item_id: dataId },
                    success: function(response) {
                        if (response.success) { Swal.fire({ icon: 'success', title: 'Done', text: 'Flight deleted successfully!' }); $('#deleteModal').addClass('hidden'); setTimeout(() => window.location.reload(), 500); }
                        else { Swal.fire({ icon: 'error', title: 'Oops...', text: response.message }); }
                    },
                    error: function(xhr) { console.error(xhr.responseText); Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong!' }); }
                });
            });
        });
        function validateCreateForm() {
            return validateFlightForm('create');
        }
        function validateEditForm() {
            return validateFlightForm('edit');
        }
        const tkrStoreUrl = "{{ route('role.tickets.store', ['role' => $role]) }}";
        let _tkrTargetSelectId = null;
        function openTicketRouteModal(selectId) {
            _tkrTargetSelectId = selectId;
            ['tkrTitle'].forEach(id => { document.getElementById(id).value = ''; });
            $('#tkrFromAirport, #tkrToAirport, #tkrAirline, #tkrVendor, #tkrPortal').val('');
            $('#tkrPrice').val(0); $('#tkrQty').val(0); $('#tkrErr').hide(); $('#tkrOverlay').addClass('open');
        }
        function closeTicketRouteModal() { $('#tkrOverlay').removeClass('open'); _tkrTargetSelectId = null; }
        // Backdrop click deliberately does NOT close this (owner, 2026-09-22) —
        // the ✕ / Cancel in the box is the way out.
        function submitTicketRouteModal() {
            const err = $('#tkrErr'); const saveBtn = $('#tkrSaveBtn'); err.hide();
            const title = $('#tkrTitle').val().trim(); const airlineId = $('#tkrAirline').val(); const vendorId = $('#tkrVendor').val(); const portalId = $('#tkrPortal').val();
            if (!title) { err.text('Ticket title is required.').show(); return; }
            if (!airlineId) { err.text('Please select an airline.').show(); return; }
            if (!vendorId && !portalId) { err.text('Please select at least a vendor or portal.').show(); return; }
            saveBtn.prop('disabled', true).text('Saving...');
            $.ajax({
                url: tkrStoreUrl, method: 'POST',
                data: { title: title, airline_id: airlineId, from_airport_id: $('#tkrFromAirport').val(), to_airport_id: $('#tkrToAirport').val(), vendor_id: vendorId, portal_id: portalId, price: $('#tkrPrice').val() || 0, qty: $('#tkrQty').val() || 0, status: 1 },
                success: function(res) {
                    saveBtn.prop('disabled', false).html('Save Route');
                    if (!res.success) { err.text(res.message || 'Failed to save.').show(); return; }
                    const t = res.data; const fromCode = t.from_airport ? t.from_airport.code : ''; const toCode = t.to_airport ? t.to_airport.code : ''; const airlineName = t.airline ? t.airline.name : '';
                    let label = t.title; if (airlineName) label += ' - ' + airlineName; if (fromCode && toCode) label += ' (' + fromCode + ' to ' + toCode + ')';
                    $('.ticket-route-select').each(function() { if (!$(this).find('option[value="' + t.id + '"]').length) $(this).append(new Option(label, t.id, false, false)); });
                    if (_tkrTargetSelectId) $('#' + _tkrTargetSelectId).val(t.id).trigger('change');
                    closeTicketRouteModal(); Swal.fire({ icon: 'success', title: 'Added!', text: 'Ticket route saved.', timer: 1500, showConfirmButton: false });
                },
                error: function(xhr) { saveBtn.prop('disabled', false).html('Save Route'); err.text(xhr.responseJSON?.message || 'Server error. Please try again.').show(); }
            });
        }
        function confirmDelete(id, name) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            $('#confirmDeleteBtn').data('action', '/{{ $role }}/flight-schedules/' + id);
            $('#deleteModal').removeClass('hidden');
        }
        function viewFlight(id) {
            const f = mfFlights.find(x => x.id === id); if (!f) return;
            $('#view_flight_number').text(f.flight_number);
            $('#view_airline').text((f.airline ? f.airline.name : '-') + (f.airline_flight_no ? ' (' + f.airline_flight_no + ')' : ''));
            $('#view_route').text(f.route || '-');
            $('#view_departure').text(f.departure_at ? new Date(f.departure_at).toLocaleString() : '-');
            $('#view_category').text(f.flight_category ? f.flight_category.name : '-');
            $('#view_category_type').text(f.category_type ? f.category_type.name : '-');
            $('#view_handling_type').text((f.handling_type || 'manpower_wise').replace('_', '-').replace(/\b\w/g, c => c.toUpperCase()));
            $('#view_revenue').text('BDT ' + Number(f.revenue).toLocaleString());
            $('#view_profit').text('BDT ' + Number(f.revenue - f.cost_price).toLocaleString());
            $('#view_agent').text(f.agent ? f.agent.name : 'Direct');
            $('#view_status').html('<span class="mf-status-pill mf-status-' + f.status + '">' + f.status.charAt(0).toUpperCase() + f.status.slice(1) + '</span>');
            $('#view_notes').text(f.notes || '-');
            $('#viewModal').removeClass('hidden');
        }
    </script>
    @include('contract-flights.operations-script')
@endsection
