@extends('layout.app')
@section('meta-information')
    <title>Contract Flight — Passenger Board</title>
@endsection
@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @include('layout.table-design')
    <style>
        {{-- Copied from resources/views/visa-processing/index.blade.php's .kb-* board
             — same board, same rules, per the owner's request to reuse it rather
             than invent a new pattern. Only the column count/colors differ. --}}
        .kb-board {
            display: flex;
            gap: 14px;
            overflow-x: auto;
            padding: 16px;
            min-height: calc(100vh - 200px);
            align-items: flex-start;
        }
        .kb-col {
            flex: 0 0 230px;
            min-width: 230px;
            background: #f8fafc;
            border-radius: 12px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: calc(100vh - 260px);
        }
        .kb-col-h {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .06em;
            color: #475569;
            padding: 4px 4px 8px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 2px;
            flex-shrink: 0;
        }
        .kb-col-cards {
            display: flex;
            flex-direction: column;
            gap: 8px;
            overflow-y: auto;
            flex: 1;
            min-height: 0;
        }
        .kb-col-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .kb-col-count {
            margin-left: auto;
            background: #e2e8f0;
            color: #475569;
            font-size: 10px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 20px;
        }
        .kb-card {
            background: #fff;
            border-radius: 9px;
            border-left: 3px solid #94a3b8;
            box-shadow: 0 1px 4px rgba(0,0,0,.07);
            padding: 10px 11px;
            cursor: pointer;
            transition: box-shadow .15s, transform .1s;
        }
        .kb-card:hover {
            box-shadow: 0 4px 14px rgba(0,0,0,.12);
            transform: translateY(-1px);
        }
        .kb-card-name {
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .kb-card-meta {
            font-size: 11px;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 6px;
        }
        .kb-card-foot {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
            padding-top: 5px;
            margin-top: 4px;
        }
        .kb-empty {
            text-align: center;
            color: #cbd5e1;
            font-size: 11px;
            padding: 20px 0;
        }
        .kb-board-header {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .kb-board-title {
            font-size: 17px;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .kb-board-actions { display: flex; gap: 8px; }
        .kb-btn-filter {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            border-radius: 7px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .kb-btn-new {
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 7px;
            padding: 7px 16px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .kb-btn-new:hover { background: #1d4ed8; color: #fff; text-decoration: none; }
        .kb-filter-panel {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 18px;
            display: none;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .kb-filter-panel.open { display: flex; }
        .kb-filter-group { display: flex; flex-direction: column; gap: 4px; min-width: 160px; flex: 1; }
        .kb-filter-group label { font-size: 11px; font-weight: 600; color: #64748b; }
        .kb-filter-group select { font-size: 12px; border: 1px solid #e2e8f0; border-radius: 6px; padding: 5px 8px; background: #fff; color: #1e293b; }
        .kb-filter-actions { display: flex; gap: 6px; padding-top: 1px; }
        .kb-filter-apply { background: #2563eb; color: #fff; border: none; border-radius: 6px; padding: 6px 14px; font-size: 12px; font-weight: 600; cursor: pointer; }
        .kb-filter-reset { background: #e2e8f0; color: #475569; border: none; border-radius: 6px; padding: 6px 14px; font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        .kb-card.dragging {
            opacity: .4;
            transform: rotate(1.5deg) scale(1.02);
            box-shadow: 0 8px 24px rgba(0,0,0,.18);
        }
        .kb-col.drag-over {
            background: #eff6ff;
            outline: 2px dashed #3b82f6;
            outline-offset: -4px;
        }
        .kb-col.drag-over .kb-col-h {
            color: #2563eb;
        }
        .kb-exc-badge {
            display: inline-block;
            margin-top: 4px;
            padding: 1px 7px;
            border-radius: 9px;
            font-size: 9px;
            font-weight: 700;
            background: #fee2e2;
            color: #dc2626;
        }
    </style>
@endsection
@section('main-content')
    @php
        $role = Str::slug(Auth::user()->getRoleNames()->first());

        // One color per pipeline stage — cool tones early, warm mid-pipeline,
        // green at the natural finish line (contract_track), red on the
        // conditional refund/reissue column since landing there is itself an
        // exception to the happy path.
        $stageColors = [
            'passenger_received' => '#94a3b8', 'information_entered' => '#64748b',
            'documents_received' => '#0ea5e9', 'documents_verified' => '#0284c7',
            'service_defined' => '#6366f1', 'route_confirmed' => '#4f46e5',
            'ticket_issued' => '#2563eb', 'ready_for_travel' => '#7c3aed', 'confirmation_call' => '#0891b2',
            'preflight_coordination' => '#9333ea', 'airport_processing' => '#d97706',
            'immigration' => '#ea580c', 'boarding' => '#f97316', 'departed' => '#dc2626',
            'transit' => '#0891b2', 'landed' => '#0d9488',
            'arrival_confirmed' => '#16a34a', 'case_closed' => '#15803d',
        ];
    @endphp

    @include('layout.contract-flight-tabs')

    <div class="bg-white rounded-lg shadow-md overflow-hidden mt-0">

        <div class="kb-board-header">
            <div class="kb-board-title">
                <i class="fas fa-columns text-blue-600"></i>
                Contract Flight — Passenger Board
                <span style="font-size:12px;font-weight:500;color:#64748b;">{{ $passengers->count() }} total</span>
            </div>
            <div class="kb-board-actions">
                <button class="kb-btn-filter" id="toggleFilter" type="button">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="{{ route('role.contract-flights.index', ['role' => $role]) }}" class="kb-btn-new">
                    <i class="fas fa-suitcase"></i> Flights &amp; Passengers
                </a>
                <a href="{{ route('role.contract-flights.index', ['role' => $role, 'open' => 'create']) }}" class="kb-btn-new">
                    <i class="fas fa-plus"></i> Add Flight
                </a>
            </div>
        </div>

        <form action="" method="get" id="filterForm">
            <div class="kb-filter-panel" id="filterPanel">
                <div class="kb-filter-group">
                    <label>Agent</label>
                    <select name="agent_id" class="select2-filter">
                        <option value="">All</option>
                        @foreach ($agents as $agent)
                            <option value="{{ $agent->id }}" {{ request('agent_id') == $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="kb-filter-group">
                    <label>Flight</label>
                    <select name="contract_flight_id" class="select2-filter">
                        <option value="">All</option>
                        @foreach ($flights as $flight)
                            <option value="{{ $flight->id }}" {{ request('contract_flight_id') == $flight->id ? 'selected' : '' }}>{{ $flight->flight_number }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="kb-filter-group">
                    <label>Exception</label>
                    <select name="exception_flag" class="select2-filter">
                        <option value="">All</option>
                        @foreach (\App\Models\ContractFlightPassenger::EXCEPTION_FLAGS as $key => $label)
                            <option value="{{ $key }}" {{ request('exception_flag') == $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="kb-filter-actions">
                    <button type="submit" class="kb-filter-apply">Apply</button>
                    <a href="{{ route('role.contract-flight-board.index', ['role' => $role]) }}" class="kb-filter-reset">Reset</a>
                </div>
            </div>
        </form>

        <div class="kb-board" id="kanbanView">
            @foreach (\App\Models\ContractFlightPassenger::stages() as $stage => $label)
                @php $cards = $grouped->get($stage, collect()); @endphp
                <div class="kb-col" data-stage="{{ $stage }}">
                    <div class="kb-col-h">
                        <span class="kb-col-dot" style="background:{{ $stageColors[$stage] ?? '#94a3b8' }}"></span>
                        {{ $label }}
                        <span class="kb-col-count">{{ $cards->count() }}</span>
                    </div>

                    <div class="kb-col-cards">
                    @forelse ($cards as $passenger)
                        <div class="kb-card"
                             draggable="true"
                             data-passenger-id="{{ $passenger->id }}"
                             data-stage="{{ $stage }}"
                             data-flight-number="{{ $passenger->contractFlight?->flight_number }}"
                             style="border-left-color:{{ $stageColors[$stage] ?? '#94a3b8' }}">
                            <div class="kb-card-name">{{ $passenger->passportHolder?->name ?? '—' }}</div>
                            <div class="kb-card-meta">
                                {{ $passenger->contractFlight?->flight_number ?? '—' }}
                                @if($passenger->contractFlight?->route)
                                    · {{ $passenger->contractFlight->route }}
                                @endif
                                @if($passenger->contractFlight?->departure_at)
                                    <br><i class="fas fa-calendar-day" style="opacity:.6"></i> {{ $passenger->contractFlight->departure_at->format('d M Y, h:i A') }}
                                @endif
                                @foreach($passenger->previousFlights() as $kbPrev)
                                    <br><span style="color:#94a3b8;"><i class="fas fa-clock-rotate-left" style="opacity:.6"></i> was {{ $kbPrev['flight'] ?? '—' }} · {{ $kbPrev['departure'] ?? 'no date' }}</span>
                                @endforeach
                                <br>Agent: {{ $passenger->agent?->name ?? 'Direct' }}
                                @php $pnrs = $passenger->ticketPurchases->pluck('ticket_no')->filter()->implode(', '); @endphp
                                @if($pnrs)
                                    <br><span style="color:#7c3aed;"><i class="fas fa-ticket-alt"></i> {{ $pnrs }}</span>
                                @endif
                                @if($passenger->agent_price > 0)
                                    <br><span style="color:#2563eb;font-weight:600;">৳{{ number_format($passenger->agent_price, 0) }}</span>
                                    <span style="color:{{ $passenger->profit >= 0 ? '#16a34a' : '#dc2626' }};font-size:10px;"> · Profit ৳{{ number_format($passenger->profit, 0) }}</span>
                                @endif
                                @if($passenger->exception_flag)
                                    <br><span class="kb-exc-badge">{{ \App\Models\ContractFlightPassenger::EXCEPTION_FLAGS[$passenger->exception_flag] ?? $passenger->exception_flag }}</span>
                                @endif
                            </div>
                            <div class="kb-card-foot">
                                <span>{{ $passenger->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="kb-empty"><i class="fas fa-inbox"></i><br>No records</div>
                    @endforelse
                    </div>
                </div>
            @endforeach
        </div>

    </div>
@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    @include('contract-flights.partials.ticketing-dropdowns')
    <script>
        $(document).ready(function () {
            $('.select2-filter').select2({ width: '100%' });
            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
        });

        document.getElementById('toggleFilter').addEventListener('click', function () {
            document.getElementById('filterPanel').classList.toggle('open');
        });

        // ── Click a card (not a drag) to open its flight ──────────────────
        // Reuses the same deep-link shape Operations Status Tracker already
        // uses for contract_flight rows: filter the flight list by number,
        // rather than building a second passenger-detail view.
        let didDrag = false;
        document.querySelectorAll('.kb-card').forEach(function (card) {
            card.addEventListener('click', function () {
                if (didDrag) { didDrag = false; return; }
                const flightNo = this.dataset.flightNumber;
                if (!flightNo) return;
                window.open('{{ route('role.contract-flights.index', ['role' => $role]) }}?search=' + encodeURIComponent(flightNo), '_blank');
            });
        });

        // ── Drag-and-drop stage update — same mechanics as
        //    resources/views/visa-processing/index.blade.php's board ──
        (function () {
            const role = '{{ $role }}';
            const stageColors = @json($stageColors);
            let draggedCard = null;

            document.querySelectorAll('.kb-card').forEach(function (card) {
                card.addEventListener('dragstart', function () {
                    draggedCard = this;
                    didDrag = true;
                    setTimeout(() => this.classList.add('dragging'), 0);
                });
                card.addEventListener('dragend', function () {
                    this.classList.remove('dragging');
                    document.querySelectorAll('.kb-col').forEach(c => c.classList.remove('drag-over'));
                    draggedCard = null;
                });
            });

            document.querySelectorAll('.kb-col').forEach(function (col) {
                col.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    this.classList.add('drag-over');
                });
                col.addEventListener('dragleave', function (e) {
                    if (!this.contains(e.relatedTarget)) this.classList.remove('drag-over');
                });
                col.addEventListener('drop', function (e) {
                    e.preventDefault();
                    this.classList.remove('drag-over');
                    if (!draggedCard) return;

                    const newStage = this.dataset.stage;
                    const oldStage = draggedCard.dataset.stage;
                    const passengerId = draggedCard.dataset.passengerId;
                    if (newStage === oldStage) return;

                    const cardsEl = this.querySelector('.kb-col-cards');
                    const emptyEl = cardsEl.querySelector('.kb-empty');
                    if (emptyEl) emptyEl.remove();
                    cardsEl.appendChild(draggedCard);
                    draggedCard.dataset.stage = newStage;
                    draggedCard.style.borderLeftColor = stageColors[newStage] || '#94a3b8';

                    document.querySelectorAll('.kb-col').forEach(function (c) {
                        const cEl = c.querySelector('.kb-col-cards');
                        const count = cEl.querySelectorAll('.kb-card').length;
                        c.querySelector('.kb-col-count').textContent = count;
                        if (count === 0 && !cEl.querySelector('.kb-empty')) {
                            const empty = document.createElement('div');
                            empty.className = 'kb-empty';
                            empty.innerHTML = '<i class="fas fa-inbox"></i><br>No records';
                            cEl.appendChild(empty);
                        }
                    });

                    $.ajax({
                        url: `/${role}/contract-flight-passengers/${passengerId}/stage`,
                        method: 'PATCH',
                        data: { stage: newStage },
                        success: function (res) {
                            if (!res.success) Swal.fire('Error', 'Stage update failed.', 'error');
                        },
                        error: function () {
                            Swal.fire('Error', 'Stage update failed.', 'error');
                        }
                    });
                });
            });
        })();
    </script>
@endsection
