@extends('layout.app')
@section('meta-information')
    <title>Visa Application Board</title>
@endsection
@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @include('layout.table-design')
    <style>
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
        .kb-card-actions {
            display: flex;
            gap: 5px;
            margin-top: 7px;
        }
        .kb-btn {
            border: none;
            border-radius: 5px;
            padding: 3px 8px;
            font-size: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: opacity .15s;
        }
        .kb-btn:hover { opacity: .8; }
        .kb-btn-edit { background: #dbeafe; color: #1d4ed8; }
        .kb-btn-del  { background: #fee2e2; color: #dc2626; }
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
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .kb-btn-new:hover { background: #1d4ed8; }
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
        .kb-filter-reset { background: #e2e8f0; color: #475569; border: none; border-radius: 6px; padding: 6px 14px; font-size: 12px; font-weight: 600; cursor: pointer; }
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
        .attach-badge {
            background: #e0f2fe;
            color: #0369a1;
            font-size: 9px;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 4px;
        }

        /* Status stat cards */
        .vp-stat-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 14px;
            padding: 16px 18px 0;
        }
        @media (max-width: 1100px) { .vp-stat-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 640px)  { .vp-stat-grid { grid-template-columns: repeat(2, 1fr); } }
        .vp-stat {
            border-radius: 14px;
            padding: 18px 16px;
            cursor: pointer;
            transition: transform .18s, box-shadow .18s;
            text-decoration: none;
            display: block;
            position: relative;
            overflow: hidden;
        }
        .vp-stat:hover { transform: translateY(-3px); box-shadow: 0 12px 26px rgba(30,50,100,.12); }
        .vp-stat-ic {
            width: 42px; height: 42px;
            border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            font-size: 17px;
            color: #fff;
            margin-bottom: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,.10);
        }
        .vp-stat-num { font-size: 26px; font-weight: 800; line-height: 1; letter-spacing: -.5px; margin-bottom: 2px; }
        .vp-stat-lbl { font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
        .vp-stat-sub { font-size: 10.5px; color: #94a3b8; margin-top: 5px; font-weight: 500; }

        .vp-stat.c-blue   { background: #eff6ff; }
        .vp-stat.c-cyan   { background: #ecfeff; }
        .vp-stat.c-amber  { background: #fffbeb; }
        .vp-stat.c-orange { background: #fff7ed; }
        .vp-stat.c-green  { background: #f0fdf4; }
        .vp-stat.c-red    { background: #fef2f2; }

        .vp-stat.c-blue   .vp-stat-ic { background: linear-gradient(135deg,#3b82f6,#2563eb); }
        .vp-stat.c-cyan   .vp-stat-ic { background: linear-gradient(135deg,#22d3ee,#0891b2); }
        .vp-stat.c-amber  .vp-stat-ic { background: linear-gradient(135deg,#f59e0b,#d97706); }
        .vp-stat.c-orange .vp-stat-ic { background: linear-gradient(135deg,#fb923c,#ea580c); }
        .vp-stat.c-green  .vp-stat-ic { background: linear-gradient(135deg,#22c55e,#16a34a); }
        .vp-stat.c-red    .vp-stat-ic { background: linear-gradient(135deg,#ef4444,#dc2626); }

        .vp-stat.c-blue   .vp-stat-num { color: #1e3a8a; }
        .vp-stat.c-cyan   .vp-stat-num { color: #164e63; }
        .vp-stat.c-amber  .vp-stat-num { color: #78350f; }
        .vp-stat.c-orange .vp-stat-num { color: #7c2d12; }
        .vp-stat.c-green  .vp-stat-num { color: #14532d; }
        .vp-stat.c-red    .vp-stat-num { color: #7f1d1d; }

        .vp-stat.c-blue   .vp-stat-lbl { color: #1d4ed8; }
        .vp-stat.c-cyan   .vp-stat-lbl { color: #0e7490; }
        .vp-stat.c-amber  .vp-stat-lbl { color: #b45309; }
        .vp-stat.c-orange .vp-stat-lbl { color: #c2410c; }
        .vp-stat.c-green  .vp-stat-lbl { color: #15803d; }
        .vp-stat.c-red    .vp-stat-lbl { color: #b91c1c; }

        /* List / Kanban view toggle */
        .vp-view-toggle {
            display: flex;
            gap: 3px;
            background: #f1f5f9;
            border-radius: 8px;
            padding: 3px;
        }
        .vp-view-toggle button {
            border: none;
            background: transparent;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
        }
        .vp-view-toggle button.active { background: #2563eb; color: #fff; }

        /* List view table */
        .vp-table-wrap { padding: 16px; overflow-x: auto; }
        .vp-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        .vp-table th {
            background: #f8fafc;
            text-align: left;
            padding: 10px 12px;
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }
        .vp-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .vp-table tbody tr:hover { background: #f8fafc; cursor: pointer; }
        .vp-table .vp-name { font-weight: 600; color: #1e293b; }
        .vp-table .vp-sub { font-size: 11px; color: #94a3b8; }
        .vp-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 10.5px; font-weight: 700; color: #fff; white-space: nowrap; }
        .pay-badge { display: inline-block; padding: 1px 7px; border-radius: 20px; font-size: 9.5px; font-weight: 700; white-space: nowrap; }
        .pay-pending { background: #f1f5f9; color: #64748b; }
        .pay-partial { background: #fef3c7; color: #92400e; }
        .pay-paid    { background: #dcfce7; color: #166534; }
        .vp-empty { text-align: center; padding: 40px 0; color: #94a3b8; }
        .vp-row-actions { display: flex; gap: 5px; }

        /* Passport Holder quick-add button */
        .vp-ph-add-btn { height:38px; border-radius:8px; border:1px solid #3b82f6; background:#eff6ff; color:#2563eb; font-size:.8rem; font-weight:600; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:.15s; padding:0 8px; white-space:nowrap; }
        .vp-ph-add-btn:hover { background:#2563eb; color:#fff; }

        /* Passport Holder quick-create modal */
        .vp-ph-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; display:none; align-items:center; justify-content:center; }
        .vp-ph-overlay.open { display:flex; }
        .vp-ph-modal { background:#fff; border-radius:14px; width:100%; max-width:520px; margin:16px; box-shadow:0 20px 60px rgba(0,0,0,.22); overflow:hidden; animation:vpPhIn .18s ease; }
        @keyframes vpPhIn { from{transform:translateY(-18px);opacity:0} to{transform:translateY(0);opacity:1} }
        .vp-ph-head { padding:16px 20px; background:linear-gradient(135deg,#2563eb,#1d4ed8); display:flex; align-items:center; gap:10px; }
        .vp-ph-head-title { font-size:14px; font-weight:700; color:#fff; }
        .vp-ph-head-close { margin-left:auto; background:rgba(255,255,255,.2); border:none; color:#fff; width:28px; height:28px; border-radius:6px; cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center; }
        .vp-ph-head-close:hover { background:rgba(255,255,255,.35); }
        .vp-ph-body { padding:18px 20px; }
        .vp-ph-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .vp-ph-grid .full { grid-column:1/-1; }
        .vp-ph-lbl { font-size:11px; font-weight:600; color:#64748b; margin-bottom:5px; display:block; letter-spacing:.2px; }
        .vp-ph-lbl .req { color:#dc2626; }
        .vp-ph-input, .vp-ph-select { width:100%; padding:9px 12px; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; outline:none; transition:border-color .12s; background:#fff; }
        .vp-ph-input:focus, .vp-ph-select:focus { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.12); }
        .vp-ph-err { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; border-radius:8px; padding:8px 12px; font-size:12px; margin-bottom:12px; display:none; }
        .vp-ph-foot { padding:14px 20px; background:#f8fafc; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end; gap:8px; }
        .vp-ph-btn { padding:9px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; border:none; transition:.15s; }
        .vp-ph-btn-cancel { background:#f1f5f9; color:#64748b; }
        .vp-ph-btn-cancel:hover { background:#e2e8f0; }
        .vp-ph-btn-save { background:#2563eb; color:#fff; }
        .vp-ph-btn-save:hover { background:#1d4ed8; }
    </style>
@endsection
@section('main-content')
    @php
        $columns = [
            'pending'    => ['label' => 'NEW',           'color' => '#94a3b8'],
            'received'   => ['label' => 'DOC COLLECTION','color' => '#3b82f6'],
            'in_embassy' => ['label' => 'IN EMBASSY',    'color' => '#f59e0b'],
            'delivered'  => ['label' => 'IN PROGRESS',   'color' => '#f59e0b'],
            'approved'   => ['label' => 'APPROVED',      'color' => '#16a34a'],
            'rejected'   => ['label' => 'REJECTED',      'color' => '#dc2626'],
        ];
        $statCardMeta = [
            'pending'    => ['theme' => 'c-blue',   'icon' => 'fa-file-pen',        'sub' => 'Awaiting review'],
            'received'   => ['theme' => 'c-cyan',   'icon' => 'fa-folder-open',     'sub' => 'Documents collected'],
            'in_embassy' => ['theme' => 'c-amber',  'icon' => 'fa-building-columns','sub' => 'Submitted to embassy'],
            'delivered'  => ['theme' => 'c-orange', 'icon' => 'fa-hourglass-half',  'sub' => 'In progress'],
            'approved'   => ['theme' => 'c-green',  'icon' => 'fa-circle-check',    'sub' => 'Approved'],
            'rejected'   => ['theme' => 'c-red',    'icon' => 'fa-circle-xmark',    'sub' => 'Need follow-up'],
        ];
        $role = Str::slug(auth()->user()->getRoleNames()->first());
    @endphp

    <div class="bg-white rounded-lg shadow-md overflow-hidden mt-0">

        {{-- Board Header --}}
        <div class="kb-board-header">
            <div class="kb-board-title">
                <i class="fas fa-th-large text-blue-600"></i>
                Visa Application Board
                <span style="font-size:12px;font-weight:500;color:#64748b;">{{ $datas->count() }} total</span>
            </div>
            <div class="kb-board-actions">
                <div class="vp-view-toggle" id="viewToggle">
                    <button type="button" class="active" data-view="kanban"><i class="fas fa-columns"></i> Kanban View</button>
                    <button type="button" data-view="list"><i class="fas fa-list"></i> List View</button>
                </div>
                <button class="kb-btn-filter" id="toggleFilter">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <button class="kb-btn-new create-btn">
                    <i class="fas fa-plus"></i> New Application
                </button>
            </div>
        </div>

        {{-- Status Summary --}}
        <div class="vp-stat-grid">
            @foreach ($columns as $status => $col)
                @php
                    $count = $grouped->get($status, collect())->count();
                    $meta  = $statCardMeta[$status];
                @endphp
                <a href="{{ route('role.visa.index', ['role' => $role, 'status' => $status]) }}" class="vp-stat {{ $meta['theme'] }}">
                    <div class="vp-stat-ic"><i class="fas {{ $meta['icon'] }}"></i></div>
                    <div class="vp-stat-num">{{ $count }}</div>
                    <div class="vp-stat-lbl">{{ $col['label'] }}</div>
                    <div class="vp-stat-sub">{{ $meta['sub'] }}</div>
                </a>
            @endforeach
        </div>

        {{-- Filter Panel --}}
        <form action="" method="get" id="filterForm">
            <div class="kb-filter-panel" id="filterPanel">
                <div class="kb-filter-group">
                    <label>Passport Holder</label>
                    <select name="passport_holder_id" class="select2-filter">
                        <option value="">All</option>
                        @foreach ($passportHolders as $ph)
                            <option value="{{ $ph->id }}" {{ request('passport_holder_id') == $ph->id ? 'selected' : '' }}>{{ $ph->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="kb-filter-group">
                    <label>Country</label>
                    <select name="country_id" class="select2-filter">
                        <option value="">All</option>
                        @foreach ($countries as $c)
                            <option value="{{ $c->id }}" {{ request('country_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="kb-filter-group">
                    <label>Visa Category</label>
                    <select name="visa_category_id" class="select2-filter">
                        <option value="">All</option>
                        @foreach ($visaCategories as $vc)
                            <option value="{{ $vc->id }}" {{ request('visa_category_id') == $vc->id ? 'selected' : '' }}>{{ $vc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="kb-filter-group">
                    <label>Status</label>
                    <select name="status" class="select2-filter">
                        <option value="">All</option>
                        @foreach ($columns as $val => $col)
                            <option value="{{ $val }}" {{ request('status') == $val ? 'selected' : '' }}>{{ $col['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="kb-filter-actions">
                    <button type="submit" class="kb-filter-apply">Apply</button>
                    <a href="{{ route('role.visa.index', ['role' => $role]) }}" class="kb-filter-reset" style="text-decoration:none;display:inline-flex;align-items:center;">Reset</a>
                </div>
            </div>
        </form>

        {{-- Kanban Board --}}
        <div class="kb-board" id="kanbanView">
            @foreach ($columns as $status => $col)
                @php $cards = $grouped->get($status, collect()); @endphp
                <div class="kb-col" data-status="{{ $status }}">
                    <div class="kb-col-h">
                        <span class="kb-col-dot" style="background:{{ $col['color'] }}"></span>
                        {{ $col['label'] }}
                        <span class="kb-col-count">{{ $cards->count() }}</span>
                    </div>

                    <div class="kb-col-cards">
                    @forelse ($cards as $visa)
                        <div class="kb-card" draggable="true" data-visa-id="{{ $visa->id }}" data-status="{{ $status }}" style="border-left-color:{{ $col['color'] }}">
                            @if($visa->application_id)
                                <div style="font-size:9px;font-weight:700;color:#94a3b8;font-family:monospace;margin-bottom:2px;">{{ $visa->application_id }}</div>
                            @endif
                            <div class="kb-card-name">{{ $visa->passportHolder?->name ?? '—' }}</div>
                            <div class="kb-card-meta">
                                @if($visa->country)
                                    <span>{{ $visa->country->name }}</span> ·
                                @endif
                                {{ $visa->visaCategory?->name ?? '—' }}
                                @if($visa->visa_type)
                                    · <span style="color:#64748b">{{ $visa->visa_type }}</span>
                                @endif
                                @if($visa->stage)
                                    <br><span style="color:#7c3aed;font-size:10px;"><i class="fas fa-route"></i> {{ $visa->stage }}</span>
                                @endif
                                @if($visa->total_fee > 0)
                                    <br><span style="color:#2563eb;font-weight:600;">৳{{ number_format($visa->total_fee, 0) }}</span>
                                    @if($visa->client_due > 0)
                                        <span style="color:#dc2626;font-size:10px;"> · Due ৳{{ number_format($visa->client_due, 0) }}</span>
                                    @endif
                                    <span class="pay-badge pay-{{ $visa->payment_status }}">{{ ucfirst($visa->payment_status) }}</span>
                                @endif
                                @if($visa->visaAttachments->isNotEmpty())
                                    <br><span class="attach-badge"><i class="fas fa-paperclip"></i> {{ $visa->visaAttachments->count() }} file(s)</span>
                                @endif
                                @if($visa->travel_date)
                                    <br><span style="color:#94a3b8;font-size:10px;"><i class="fas fa-plane"></i> {{ $visa->travel_date->format('d M Y') }}</span>
                                @endif
                            </div>
                            <div class="kb-card-foot">
                                <span>{{ $visa->created_at->diffForHumans() }}</span>
                            </div>
                            <div class="kb-card-actions">
                                <button class="kb-btn kb-btn-edit edit-btn"
                                    data-item_id="{{ $visa->id }}"
                                    data-passport_holder_id="{{ $visa->passport_holder_id }}"
                                    data-vendor_id="{{ $visa->vendor_id }}"
                                    data-portal_id="{{ $visa->portal_id }}"
                                    data-cost_bank_id="{{ $visa->cost_bank_id }}"
                                    data-cost_paid_amount="{{ $visa->cost_paid_amount }}"
                                    data-purchase_date="{{ $visa->purchase_date?->format('Y-m-d') }}"
                                    data-country_id="{{ $visa->country_id }}"
                                    data-visa_category_id="{{ $visa->visa_category_id }}"
                                    data-duration="{{ $visa->duration }}"
                                    data-travel_date="{{ $visa->travel_date?->format('Y-m-d') }}"
                                    data-costing_price="{{ $visa->costing_price }}"
                                    data-sale_price="{{ $visa->sale_price }}"
                                    data-advance_received="{{ $visa->advance_received }}"
                                    data-payable_date="{{ $visa->payable_date?->format('Y-m-d') }}"
                                    data-payment_status="{{ $visa->payment_status }}"
                                    data-assigned_officer_id="{{ $visa->assigned_officer_id }}"
                                    data-status="{{ $visa->status }}"
                                    data-remarks="{{ $visa->remarks }}">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="kb-btn kb-btn-del"
                                    onclick="confirmDelete('{{ $visa->id }}', '{{ addslashes($visa->passportHolder?->name ?? 'this item') }}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                                @if($visa->due_amount > 0)
                                    <button class="kb-btn"
                                        style="background:#fef3c7;color:#92400e;"
                                        onclick="openPayVendorModal({{ $visa->id }}, '{{ addslashes($visa->application_id) }}', '{{ addslashes($visa->vendor?->name ?? 'Vendor (unassigned)') }}', {{ $visa->due_amount }})">
                                        <i class="fas fa-money-bill-wave"></i> Pay Vendor
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="kb-empty"><i class="fas fa-inbox"></i><br>No records</div>
                    @endforelse
                    </div>
                </div>
            @endforeach
        </div>

        {{-- List View --}}
        <div class="vp-table-wrap" id="listView" style="display:none;">
            @if($datas->isEmpty())
                <div class="vp-empty"><i class="fas fa-inbox fa-2x"></i><br><br>No records found</div>
            @else
                <table class="vp-table">
                    <thead>
                        <tr>
                            <th>App#</th>
                            <th>Applicant</th>
                            <th>Country / Type</th>
                            <th>Stage</th>
                            <th>Days</th>
                            <th>Expected</th>
                            <th>Sale Price (৳)</th>
                            <th>Status</th>
                            <th>Officer</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $visa)
                            @php $col = $columns[$visa->status] ?? ['label' => $visa->status, 'color' => '#94a3b8']; @endphp
                            <tr class="view-details-row" data-visa-id="{{ $visa->id }}">
                                <td><span class="vp-sub" style="font-family:monospace;">{{ $visa->application_id ?? '—' }}</span></td>
                                <td>
                                    <div class="vp-name">{{ $visa->passportHolder?->name ?? '—' }}</div>
                                    <div class="vp-sub">{{ $visa->passportHolder?->passport_no ?? '' }}</div>
                                </td>
                                <td>
                                    {{ $visa->country?->name ?? '—' }}
                                    <div class="vp-sub">{{ $visa->visaCategory?->name ?? '—' }}@if($visa->visa_type) · {{ $visa->visa_type }}@endif</div>
                                </td>
                                <td>{{ $visa->stage ?? '—' }}</td>
                                @php $ageDays = (int) floor($visa->created_at->diffInDays(now())); @endphp
                                <td>{{ $ageDays }} day{{ $ageDays == 1 ? '' : 's' }}</td>
                                <td>{{ $visa->travel_date ? $visa->travel_date->format('d M Y') : '—' }}</td>
                                <td>
                                    ৳{{ number_format($visa->sale_price, 0) }}
                                    <div><span class="pay-badge pay-{{ $visa->payment_status }}">{{ ucfirst($visa->payment_status) }}</span></div>
                                </td>
                                <td><span class="vp-badge" style="background:{{ $col['color'] }};">{{ $col['label'] }}</span></td>
                                <td>{{ $visa->assignedOfficer?->name ?? '—' }}</td>
                                <td>
                                    <div class="vp-row-actions">
                                        <button class="kb-btn kb-btn-edit edit-btn"
                                            data-item_id="{{ $visa->id }}"
                                            data-passport_holder_id="{{ $visa->passport_holder_id }}"
                                            data-vendor_id="{{ $visa->vendor_id }}"
                                            data-portal_id="{{ $visa->portal_id }}"
                                            data-cost_bank_id="{{ $visa->cost_bank_id }}"
                                            data-cost_paid_amount="{{ $visa->cost_paid_amount }}"
                                            data-purchase_date="{{ $visa->purchase_date?->format('Y-m-d') }}"
                                            data-country_id="{{ $visa->country_id }}"
                                            data-visa_category_id="{{ $visa->visa_category_id }}"
                                            data-duration="{{ $visa->duration }}"
                                            data-travel_date="{{ $visa->travel_date?->format('Y-m-d') }}"
                                            data-costing_price="{{ $visa->costing_price }}"
                                            data-sale_price="{{ $visa->sale_price }}"
                                            data-advance_received="{{ $visa->advance_received }}"
                                            data-payable_date="{{ $visa->payable_date?->format('Y-m-d') }}"
                                            data-payment_status="{{ $visa->payment_status }}"
                                            data-assigned_officer_id="{{ $visa->assigned_officer_id }}"
                                            data-status="{{ $visa->status }}"
                                            data-stage="{{ $visa->stage }}"
                                            data-remarks="{{ $visa->remarks }}">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="kb-btn kb-btn-del"
                                            onclick="confirmDelete('{{ $visa->id }}', '{{ addslashes($visa->passportHolder?->name ?? 'this item') }}')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    @include('visa-processing.create-modal')
    @include('visa-processing.edit-modal')
    @include('visa-processing.delete-modal')
    @include('visa-processing.details-modal')
    @include('visa-processing.pay-vendor-modal')

    {{-- Passport Holder Quick-Create Modal --}}
    <div class="vp-ph-overlay" id="vpPhOverlay">
        <div class="vp-ph-modal">
            <div class="vp-ph-head">
                <span style="font-size:18px">🪪</span>
                <span class="vp-ph-head-title">Add New Passport Holder</span>
                <button type="button" class="vp-ph-head-close" onclick="closeVpPhModal()">✕</button>
            </div>
            <div class="vp-ph-body">
                <div class="vp-ph-err" id="vpPhErr"></div>
                <div class="vp-ph-grid">
                    <div class="full">
                        <label class="vp-ph-lbl">Full Name <span class="req">*</span></label>
                        <input type="text" id="vpPhName" class="vp-ph-input" placeholder="e.g. Md. Rahman">
                    </div>
                    <div>
                        <label class="vp-ph-lbl">Passport No <span class="req">*</span></label>
                        <input type="text" id="vpPhPassportNo" class="vp-ph-input" placeholder="e.g. BD1234567">
                    </div>
                    <div>
                        <label class="vp-ph-lbl">Nationality <span class="req">*</span></label>
                        <input type="text" id="vpPhNationality" class="vp-ph-input" placeholder="e.g. Bangladeshi">
                    </div>
                    <div>
                        <label class="vp-ph-lbl">Phone</label>
                        <input type="text" id="vpPhPhone" class="vp-ph-input" placeholder="e.g. 01XXXXXXXXX">
                    </div>
                    <div>
                        <label class="vp-ph-lbl">Date of Birth</label>
                        <input type="date" id="vpPhDob" class="vp-ph-input">
                    </div>
                    <div>
                        <label class="vp-ph-lbl">Category</label>
                        <select id="vpPhCategory" class="vp-ph-select">
                            <option value="">— Select category —</option>
                            @foreach ($phCategories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="vp-ph-lbl">Type <span class="req">*</span></label>
                        <select id="vpPhType" class="vp-ph-select">
                            @include('passport-holder.type-options')
                        </select>
                    </div>
                    <div>
                        <label class="vp-ph-lbl">Issue Date</label>
                        <input type="date" id="vpPhIssueDate" class="vp-ph-input">
                    </div>
                    <div>
                        <label class="vp-ph-lbl">Expiry Date</label>
                        <input type="date" id="vpPhExpiryDate" class="vp-ph-input">
                    </div>
                </div>
            </div>
            <div class="vp-ph-foot">
                <button type="button" class="vp-ph-btn vp-ph-btn-cancel" onclick="closeVpPhModal()">Cancel</button>
                <button type="button" class="vp-ph-btn vp-ph-btn-save" id="vpPhSaveBtn" onclick="submitVpPhModal()">
                    💾 Save Passport Holder
                </button>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        const allVisaCategories = @json($visaCategoriesJson);
        const allPassportHolders = @json($passportHoldersJson);
        const statusLabels = @json(collect($columns)->mapWithKeys(fn($v, $k) => [$k => $v['label']]));

        function showHolderInfo(holderId, prefix) {
            const h = allPassportHolders.find(x => x.id == holderId);
            if (h) {
                $('#' + prefix + '_holder_passport').text(h.passport_no || '—');
                $('#' + prefix + '_holder_phone').text(h.phone || '—');
                $('#' + prefix + '_holder_nationality').text(h.nationality || '—');
                $('#' + prefix + '_holder_info').removeClass('hidden');
            } else {
                $('#' + prefix + '_holder_info').addClass('hidden');
            }
        }

        $(document).ready(function() {
            $('.select2').select2();
            $('.select2-filter').select2({ minimumResultsForSearch: 5 });

            // Rebuild a category <select> filtered to the given countryId (all if empty)
            function filterCategorySelect(selectId, countryId, preselectId) {
                const $sel = $('#' + selectId);
                const cats = countryId
                    ? allVisaCategories.filter(c => c.country_id == countryId)
                    : allVisaCategories;
                $sel.empty().append('<option value="">— Select Category —</option>');
                cats.forEach(c => {
                    const opt = new Option(c.name, c.id, false, preselectId && c.id == preselectId);
                    $sel.append(opt);
                });
                $sel.trigger('change.select2');
            }

            // Country → filter category list (create)
            $('#create_country_id').on('change', function() {
                filterCategorySelect('create_visa_category_id', $(this).val());
                $('#c_costing_price,#c_sale_price').val('');
                $('#c_visa_type_display,#c_avg_processing_days_display').val('');
                calcCreateTotal();
            });

            // Country → filter category list (edit)
            $('#edit_country_id').on('change', function() {
                if (window._editLoading) return; // suppress during modal open; we handle it there
                filterCategorySelect('edit_visa_category_id', $(this).val());
                $('#e_costing_price,#e_sale_price').val('');
                $('#e_visa_type_display,#e_avg_processing_days_display').val('');
                calcEditTotal();
            });

            // Category → auto-fill costing/sale price + show visa type (create)
            $('#create_visa_category_id').on('change', function() {
                const cat = allVisaCategories.find(c => c.id == $(this).val());
                if (cat) {
                    $('#c_costing_price').val(cat.costing_price > 0 ? cat.costing_price : '');
                    $('#c_sale_price').val(cat.sale_price > 0 ? cat.sale_price : '');
                    $('#c_visa_type_display').val(cat.visa_type || '');
                    $('#c_avg_processing_days_display').val(cat.avg_processing_days ? cat.avg_processing_days + ' days' : '');
                    calcCreateTotal();
                } else {
                    $('#c_visa_type_display').val('');
                    $('#c_avg_processing_days_display').val('');
                }
            });

            // Passport holder → show info (create)
            $('#create_passport_holder_id').on('change', function() {
                showHolderInfo($(this).val(), 'c');
            });

            // Category → show visa type, processing days, costing/sale price (edit)
            $('#edit_visa_category_id').on('change', function() {
                const cat = allVisaCategories.find(c => c.id == $(this).val());
                $('#e_visa_type_display').val(cat ? (cat.visa_type || '') : '');
                $('#e_avg_processing_days_display').val(cat && cat.avg_processing_days ? cat.avg_processing_days + ' days' : '');
                if (cat) {
                    $('#e_costing_price').val(cat.costing_price > 0 ? cat.costing_price : '');
                    $('#e_sale_price').val(cat.sale_price > 0 ? cat.sale_price : '');
                    calcEditTotal();
                }
            });

            // Passport holder → show info (edit)
            $('#edit_passport_holder_id').on('change', function() {
                showHolderInfo($(this).val(), 'e');
            });

            @if(request('open_create'))
            $('#create_attachments_wrapper').empty();
            (function createRow(w) {
                const r = $(
                    '<div class="flex items-center gap-2 mb-1">' +
                    '<input type="file" name="attachments[]" class="form-control w-full border border-gray-300 rounded-md p-2" />' +
                    '<button type="button" class="remove-attachment btn btn-danger px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600">Remove</button>' +
                    '</div>'
                );
                $(w).append(r);
            })('#create_attachments_wrapper');
            $('#createModal').removeClass('hidden');
            @endif

            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

            // Filter toggle
            $('#toggleFilter').click(function() {
                $('#filterPanel').toggleClass('open');
                $(this).find('i').toggleClass('fa-filter fa-times');
            });

            // List / Kanban view toggle
            $('#viewToggle button').click(function() {
                const view = $(this).data('view');
                $('#viewToggle button').removeClass('active');
                $(this).addClass('active');
                if (view === 'list') {
                    $('#kanbanView').hide();
                    $('#listView').show();
                } else {
                    $('#listView').hide();
                    $('#kanbanView').show();
                }
                localStorage.setItem('vpVisaBoardView', view);
            });
            if (localStorage.getItem('vpVisaBoardView') === 'list') {
                $('#viewToggle button[data-view="list"]').click();
            }
            @if(request()->hasAny(['passport_holder_id','country_id','visa_category_id','status']))
                $('#filterPanel').addClass('open');
            @endif

            // Attachment rows
            function createAttachmentRow(wrapper) {
                const row = $(
                    '<div class="flex items-center gap-2 mb-1">' +
                        '<input type="file" name="attachments[]" class="form-control w-full border border-gray-300 rounded-md p-2" />' +
                        '<button type="button" class="remove-attachment btn btn-danger px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600">Remove</button>' +
                    '</div>'
                );
                $(wrapper).append(row);
            }

            $('#create_add_attachment').click(function() { createAttachmentRow('#create_attachments_wrapper'); });
            $('#edit_add_attachment').click(function() { createAttachmentRow('#edit_attachments_wrapper'); });
            $(document).on('click', '.remove-attachment', function() { $(this).closest('div').remove(); });

            // Delete attachment
            $(document).on('click', '.delete-attachment', function() {
                const attachmentId = $(this).data('attachment-id');
                const visaId = $('#editItemId').val();
                const button = $(this);
                Swal.fire({
                    title: 'Are you sure?', text: 'This will permanently delete the attachment.',
                    icon: 'warning', showCancelButton: true,
                    confirmButtonColor: '#d33', cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: `/{{ Str::slug(Auth::user()->getRoleNames()->first()) }}/visa/${visaId}/attachment/${attachmentId}`,
                            method: 'DELETE',
                            success: function(response) {
                                if (response.success) {
                                    button.closest('div').remove();
                                    Swal.fire('Deleted!', 'Attachment deleted.', 'success');
                                    if ($('#edit_existing_attachments').children().length === 0) {
                                        $('#edit_existing_attachments_toggle').hide();
                                        $('#edit_existing_attachments').addClass('hidden');
                                    }
                                } else {
                                    Swal.fire('Error!', response.message || 'Failed.', 'error');
                                }
                            }
                        });
                    }
                });
            });

            $(document).on('click', '#edit_existing_attachments_toggle', function() {
                $('#edit_existing_attachments').toggleClass('hidden');
                $('#edit_existing_attachments_icon').toggleClass('fa-chevron-down fa-chevron-up');
            });

            // Open modals
            $('.create-btn').click(function() {
                $('#createModal form')[0].reset();
                $('#create_country_id').val('').trigger('change.select2');
                filterCategorySelect('create_visa_category_id', '');
                $('#create_passport_holder_id').val('').trigger('change.select2');
                $('#c_holder_info').addClass('hidden');
                $('#c_vendor_id, #c_portal_id').prop('disabled', false).val('').trigger('change.select2');
                $('#c_cost_bank_id').prop('disabled', false).val('').trigger('change.select2');
                $('#c_cost_bank_wrap, #c_cost_sched_wrap').show();
                $('#c_visa_type_display').val('');
                $('#c_avg_processing_days_display').val('');
                $('#c_due').val('৳ 0');
                $('#create_attachments_wrapper').empty();
                createAttachmentRow('#create_attachments_wrapper');
                $('#createModal').removeClass('hidden');
            });

            $(document).on('click', '.edit-btn', function() {
                const itemId = $(this).data('item_id');
                const role = '{{ Str::slug(auth()->user()->getRoleNames()->first()) }}';
                window._editLoading = true;
                $('#edit_attachments_wrapper').empty();
                createAttachmentRow('#edit_attachments_wrapper');
                $('#editItemId').val(itemId);
                $('#editSubmit').data('action', `/${role}/visa/${itemId}`);

                // Country / Category — filter category list by country, then pre-select saved value
                const countryId = $(this).data('country_id');
                const categoryId = $(this).data('visa_category_id');
                $('#edit_country_id').val(countryId).trigger('change.select2');
                filterCategorySelect('edit_visa_category_id', countryId, categoryId);

                const cat = allVisaCategories.find(c => c.id == categoryId);
                $('#e_visa_type_display').val(cat ? (cat.visa_type || '') : '');
                $('#e_avg_processing_days_display').val(cat && cat.avg_processing_days ? cat.avg_processing_days + ' days' : '');

                // Passport holder + show info
                const holderId = $(this).data('passport_holder_id');
                $('#edit_passport_holder_id').val(holderId).trigger('change.select2');
                showHolderInfo(holderId, 'e');

                $('#edit_duration').val($(this).data('duration'));
                $('#edit_travel_date').val($(this).data('travel_date'));
                $('#e_costing_price').val($(this).data('costing_price'));
                $('#edit_vendor_id').val($(this).data('vendor_id')).trigger('change.select2');
                $('#edit_portal_id').val($(this).data('portal_id')).trigger('change.select2');
                $('#edit_cost_bank_id').val($(this).data('cost_bank_id')).trigger('change.select2');
                $('#edit_cost_paid_amount').val($(this).data('cost_paid_amount'));
                $('#edit_purchase_date').val($(this).data('purchase_date'));
                visaOnVendorChange(document.getElementById('edit_vendor_id'));
                visaOnPortalChange(document.getElementById('edit_portal_id'));
                $('#e_sale_price').val($(this).data('sale_price'));
                $('#e_advance').val($(this).data('advance_received'));
                $('#edit_payable_date').val($(this).data('payable_date'));
                $('#edit_payment_status').val($(this).data('payment_status'));
                $('#edit_assigned_officer_id').val($(this).data('assigned_officer_id')).trigger('change.select2');
                $('#edit_status').val($(this).data('status'));
                $('#edit_stage').val($(this).data('stage'));
                $('#edit_remarks').val($(this).data('remarks'));
                calcEditTotal();
                window._editLoading = false;

                $.ajax({
                    url: `/{{ Str::slug(Auth::user()->getRoleNames()->first()) }}/visa/${itemId}/attachments`,
                    method: 'GET',
                    success: function(response) {
                        if (response.success && response.attachments.length > 0) {
                            const html = response.attachments.map(a => `
                                <div class="flex items-center justify-between bg-gray-50 p-2 rounded-md mb-1">
                                    <a href="/${a.file_path}" target="_blank" class="text-blue-600 hover:underline text-sm">
                                        <i class="fas fa-file mr-1"></i>${a.file_name}
                                    </a>
                                    <button type="button" class="delete-attachment kb-btn kb-btn-del" data-attachment-id="${a.id}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>`).join('');
                            $('#edit_existing_attachments').html(html).removeClass('hidden');
                            $('#edit_existing_attachments_toggle').show();
                        } else {
                            $('#edit_existing_attachments').addClass('hidden').empty();
                            $('#edit_existing_attachments_toggle').hide();
                        }
                    }
                });
                $('#editModal').removeClass('hidden');
            });

            // Close modals
            $('.modal-close-create, .modal-close-edit, .modal-close-delete, .modal-close-details').click(function() {
                $('#createModal, #editModal, #deleteModal, #detailsModal').addClass('hidden');
            });
            $(document).on('click', '.modal-backdrop', function(e) {
                if ($(e.target).hasClass('modal-backdrop')) {
                    $('#createModal, #editModal, #deleteModal, #detailsModal').addClass('hidden');
                }
            });

            // Open details modal on card / row click
            $(document).on('click', '.kb-card, .view-details-row', function(e) {
                if ($(e.target).closest('.kb-card-actions, .vp-row-actions').length) return;

                const visaId = $(this).data('visa-id');
                const role = '{{ Str::slug(Auth::user()->getRoleNames()->first()) }}';

                $('#d_content').addClass('hidden');
                $('#d_loading').removeClass('hidden');
                $('#d_application_id').text('');
                $('#detailsModal').removeClass('hidden');

                $.ajax({
                    url: `/${role}/visa/${visaId}/details`,
                    method: 'GET',
                    success: function(response) {
                        if (response.success) {
                            renderDetails(response.data);
                        }
                    }
                });
            });

            function fmtMoney(v) {
                return '৳ ' + (parseFloat(v) || 0).toLocaleString('en-BD', { minimumFractionDigits: 0 });
            }

            function renderDetails(visa) {
                $('#d_application_id').text(visa.application_id ? '#' + visa.application_id : '');
                $('#d_country').text(visa.country?.name ?? '—');
                $('#d_visa_category').text(visa.visa_category?.name ?? '—');
                $('#d_visa_type').text(visa.visa_type || '—');
                $('#d_duration').text(visa.duration || '—');
                $('#d_travel_date').text(visa.travel_date ? new Date(visa.travel_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—');
                $('#d_status').text(statusLabels[visa.status] || visa.status);
                $('#d_stage').text(visa.stage || '—');

                $('#d_holder_name').text(visa.passport_holder?.name ?? '—');
                $('#d_holder_passport').text(visa.passport_holder?.passport_no ?? '—');
                $('#d_holder_phone').text(visa.passport_holder?.phone ?? '—');
                $('#d_holder_nationality').text(visa.passport_holder?.nationality ?? '—');

                const costing = parseFloat(visa.costing_price) || 0;
                const sale = parseFloat(visa.sale_price) || 0;
                const advance = parseFloat(visa.advance_received) || 0;
                const due = Math.max(0, sale - advance);

                $('#d_costing_price').text(fmtMoney(costing));
                $('#d_sale_price').text(fmtMoney(sale));
                $('#d_advance').text(fmtMoney(advance));
                $('#d_due').text(fmtMoney(due));
                $('#d_officer').text(visa.assigned_officer?.name ?? '—');

                const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
                $('#d_payable_date').text(fmtDate(visa.payable_date));
                const payStatus = visa.payment_status || 'pending';
                $('#d_payment_status').html(`<span class="pay-badge pay-${payStatus}">${payStatus.charAt(0).toUpperCase() + payStatus.slice(1)}</span>`);

                $('#d_remarks').text(visa.remarks || '—');

                // Document checklist
                const $docs = $('#d_documents').empty();
                if (visa.process_documents && visa.process_documents.length) {
                    visa.process_documents.forEach(function(doc) {
                        const colors = {
                            received: 'background:#dcfce7;color:#166534;',
                            pending: 'background:#fef9c3;color:#854d0e;',
                            not_required: 'background:#f1f5f9;color:#94a3b8;'
                        };
                        const style = colors[doc.status] || colors.pending;
                        $docs.append(`<span class="attach-badge" style="${style}">${doc.document_name}</span>`);
                    });
                } else {
                    $docs.append('<span class="text-xs text-gray-400">No documents tracked</span>');
                }

                // Attachments
                const $att = $('#d_attachments').empty();
                if (visa.visa_attachments && visa.visa_attachments.length) {
                    visa.visa_attachments.forEach(function(a) {
                        $att.append(`
                            <div class="flex items-center bg-gray-50 p-2 rounded-md">
                                <a href="/${a.file_path}" target="_blank" class="text-blue-600 hover:underline text-sm">
                                    <i class="fas fa-file mr-1"></i>${a.file_name}
                                </a>
                            </div>`);
                    });
                } else {
                    $att.append('<span class="text-xs text-gray-400">No attachments</span>');
                }

                $('#d_created_at').text('Created ' + new Date(visa.created_at).toLocaleString());

                $('#d_loading').addClass('hidden');
                $('#d_content').removeClass('hidden');
            }

            // Create submit
            $('#createSubmit').click(function(e) {
                e.preventDefault();
                $.ajax({
                    url: $('#createForm').attr('action'), method: 'POST',
                    data: new FormData($('#createForm')[0]),
                    processData: false, contentType: false,
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Application created!' });
                            $('#createModal').addClass('hidden');
                            setTimeout(() => window.location.reload(), 600);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                        }
                    }
                });
            });

            // Edit submit
            $('#editSubmit').click(function(e) {
                e.preventDefault();
                $.ajax({
                    url: $(this).data('action'), method: 'POST',
                    data: new FormData($('#editForm')[0]),
                    processData: false, contentType: false,
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Application updated!' });
                            $('#editModal').addClass('hidden');
                            setTimeout(() => window.location.reload(), 600);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                        }
                    }
                });
            });

            // Delete submit
            $('#confirmDeleteBtn').click(function() {
                $.ajax({
                    url: $(this).data('action'), method: 'DELETE',
                    data: { item_id: $(this).data('item-id') },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Deleted!' });
                            $('#deleteModal').addClass('hidden');
                            setTimeout(() => window.location.reload(), 500);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                        }
                    }
                });
            });
        });

        // ── Drag-and-drop status update ──
        (function() {
            const role = '{{ Str::slug(auth()->user()->getRoleNames()->first()) }}';
            const colColors = @json(collect($columns)->mapWithKeys(fn($v, $k) => [$k => $v['color']]));
            let draggedCard = null;

            document.querySelectorAll('.kb-card').forEach(card => {
                card.addEventListener('dragstart', function(e) {
                    draggedCard = this;
                    setTimeout(() => this.classList.add('dragging'), 0);
                    e.dataTransfer.effectAllowed = 'move';
                });
                card.addEventListener('dragend', function() {
                    this.classList.remove('dragging');
                    document.querySelectorAll('.kb-col').forEach(c => c.classList.remove('drag-over'));
                    draggedCard = null;
                });
            });

            document.querySelectorAll('.kb-col').forEach(col => {
                col.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    this.classList.add('drag-over');
                });
                col.addEventListener('dragleave', function(e) {
                    if (!this.contains(e.relatedTarget)) {
                        this.classList.remove('drag-over');
                    }
                });
                col.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.classList.remove('drag-over');
                    if (!draggedCard) return;

                    const newStatus = this.dataset.status;
                    const oldStatus = draggedCard.dataset.status;
                    const visaId   = draggedCard.dataset.visaId;

                    if (newStatus === oldStatus) return;

                    // Move card in DOM
                    const cardsEl = this.querySelector('.kb-col-cards');
                    const emptyEl = cardsEl.querySelector('.kb-empty');
                    if (emptyEl) emptyEl.remove();
                    cardsEl.appendChild(draggedCard);
                    draggedCard.dataset.status = newStatus;
                    draggedCard.style.borderLeftColor = colColors[newStatus] || '#94a3b8';

                    // Update column counts
                    document.querySelectorAll('.kb-col').forEach(c => {
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

                    // AJAX update
                    $.ajax({
                        url: `/${role}/visa/${visaId}/status`,
                        method: 'PATCH',
                        data: { status: newStatus, _token: $('meta[name="csrf-token"]').attr('content') },
                        success: function(res) {
                            if (!res.success) {
                                Swal.fire('Error', 'Status update failed.', 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'Status update failed.', 'error');
                        }
                    });
                });
            });
        })();

        // ── Pay Vendor ────────────────────────────────────────────────
        let _payVendorId = null;

        function openPayVendorModal(id, applicationId, vendorName, dueAmount) {
            _payVendorId = id;
            $('#pv_application_id').text(applicationId);
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
            _payVendorId = null;
        });

        $('#pv_submit_btn').on('click', function () {
            const role = '{{ Str::slug(auth()->user()->getRoleNames()->first()) }}';
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
                url: `/${role}/visa/${_payVendorId}/pay-vendor`,
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

        function confirmDelete(id, name) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            const role = '{{ Str::slug(auth()->user()->getRoleNames()->first()) }}';
            $('#confirmDeleteBtn').data('action', `/${role}/visa/${id}`);
            $('#deleteModal').removeClass('hidden');
        }

        // ── Vendor / Portal / Cost Bank mutual exclusion ────────────────
        function visaFieldPrefix(el) {
            return el.id.startsWith('edit_') ? 'edit' : 'c';
        }

        function visaOnVendorChange(sel) {
            const prefix = visaFieldPrefix(sel);
            const portalSel = document.getElementById(prefix === 'edit' ? 'edit_portal_id' : 'c_portal_id');
            if (!portalSel) return;
            setTimeout(function () {
                if (sel.value) {
                    $(portalSel).val(null).prop('disabled', true).trigger('change.select2');
                } else {
                    $(portalSel).prop('disabled', false).trigger('change.select2');
                }
                visaToggleCostBank(portalSel);
            }, 0);
        }

        function visaOnPortalChange(sel) {
            const prefix = visaFieldPrefix(sel);
            const vendorSel = document.getElementById(prefix === 'edit' ? 'edit_vendor_id' : 'c_vendor_id');
            if (!vendorSel) return;
            setTimeout(function () {
                if (sel.value) {
                    $(vendorSel).val(null).prop('disabled', true).trigger('change.select2');
                } else {
                    $(vendorSel).prop('disabled', false).trigger('change.select2');
                }
                visaToggleCostBank(sel);
            }, 0);
        }

        function visaToggleCostBank(portalSel) {
            const prefix = visaFieldPrefix(portalSel);
            const bankSel = document.getElementById(prefix === 'edit' ? 'edit_cost_bank_id' : 'c_cost_bank_id');
            const bankWrap = document.getElementById(prefix === 'edit' ? 'edit_cost_bank_wrap' : 'c_cost_bank_wrap');
            const schedWrap = document.getElementById(prefix === 'edit' ? 'edit_cost_sched_wrap' : 'c_cost_sched_wrap');
            if (bankSel) {
                $(bankSel).prop('disabled', !!portalSel.value);
                if (portalSel.value) { $(bankSel).val(null).trigger('change.select2'); }
            }
            if (bankWrap) bankWrap.style.display = portalSel.value ? 'none' : '';
            if (schedWrap) schedWrap.style.display = portalSel.value ? 'none' : '';
        }

        // ── Passport Holder quick-create ─────────────────────────────
        const vpPhStoreUrl = "{{ route('role.passport-holder.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}";
        let _vpPhTargetSelectId = null;

        function openVpPhModal(selectId) {
            _vpPhTargetSelectId = selectId;
            ['vpPhName','vpPhPassportNo','vpPhNationality','vpPhPhone','vpPhDob','vpPhIssueDate','vpPhExpiryDate'].forEach(id => {
                document.getElementById(id).value = '';
            });
            document.getElementById('vpPhCategory').value = '';
            document.getElementById('vpPhType').value = 'visa';
            document.getElementById('vpPhErr').style.display = 'none';
            document.getElementById('vpPhOverlay').classList.add('open');
        }

        function closeVpPhModal() {
            document.getElementById('vpPhOverlay').classList.remove('open');
            _vpPhTargetSelectId = null;
        }

        document.getElementById('vpPhOverlay').addEventListener('click', function(e) {
            if (e.target === this) closeVpPhModal();
        });

        function submitVpPhModal() {
            const err     = document.getElementById('vpPhErr');
            const saveBtn = document.getElementById('vpPhSaveBtn');
            err.style.display = 'none';

            const payload = {
                name:          document.getElementById('vpPhName').value.trim(),
                passport_no:   document.getElementById('vpPhPassportNo').value.trim(),
                nationality:   document.getElementById('vpPhNationality').value.trim(),
                phone:         document.getElementById('vpPhPhone').value.trim(),
                date_of_birth: document.getElementById('vpPhDob').value,
                category_id:   document.getElementById('vpPhCategory').value,
                type:          document.getElementById('vpPhType').value,
                issue_date:    document.getElementById('vpPhIssueDate').value,
                expiry_date:   document.getElementById('vpPhExpiryDate').value,
                status:        1,
                _token:        $('meta[name="csrf-token"]').attr('content'),
            };

            if (!payload.name) {
                err.textContent = 'Full name is required.';
                err.style.display = 'block';
                return;
            }
            if (!payload.passport_no) {
                err.textContent = 'Passport number is required.';
                err.style.display = 'block';
                return;
            }

            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving…';

            $.ajax({
                url: vpPhStoreUrl,
                method: 'POST',
                data: payload,
                success: function(res) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '💾 Save Passport Holder';
                    if (!res.success) {
                        err.textContent = res.message || 'Failed to save.';
                        err.style.display = 'block';
                        return;
                    }
                    const newId   = res.data.id;
                    const newName = res.data.name;
                    const newPhone = res.data.phone || '';
                    const label   = newName + (newPhone ? ' · ' + newPhone : '');

                    // Add to both passport holder dropdowns
                    $('.vp-ph-select').each(function() {
                        if (!$(this).find('option[value="' + newId + '"]').length) {
                            $(this).append(new Option(label, newId, false, false));
                        }
                    });

                    // Auto-select in the triggering dropdown and show info
                    if (_vpPhTargetSelectId) {
                        const prefix = _vpPhTargetSelectId.startsWith('create') ? 'c' : 'e';
                        $('#' + _vpPhTargetSelectId).val(newId).trigger('change');
                        // Push into allPassportHolders so showHolderInfo works immediately
                        allPassportHolders.push({
                            id:          newId,
                            passport_no: payload.passport_no,
                            phone:       payload.phone,
                            nationality: payload.nationality,
                        });
                        showHolderInfo(newId, prefix);
                    }

                    closeVpPhModal();
                    Swal.fire({ icon:'success', title:'Added!', text: newName + ' has been saved.', timer:1800, showConfirmButton:false });
                },
                error: function(xhr) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '💾 Save Passport Holder';
                    err.textContent = xhr.responseJSON?.message || 'Server error. Please try again.';
                    err.style.display = 'block';
                }
            });
        }
    </script>
@endsection
