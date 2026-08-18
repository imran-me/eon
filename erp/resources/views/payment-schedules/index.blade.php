@extends('layout.app')

@section('meta-information')
    <title>Payment Schedules</title>
@endsection

@section('css')
@include('journals.css')
<style>
    /* ── Summary cards ─────────────────────────────── */
    .sched-summary { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; padding:18px 18px 0; }
    .sum-card { background:#fff; border-radius:10px; padding:14px 18px; border-left:4px solid; box-shadow:0 2px 8px rgba(0,0,0,.06); }
    .sum-card h4 { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; margin-bottom:5px; }
    .sum-card .val { font-size:20px; font-weight:800; }
    .sum-card small { font-size:11px; color:#94a3b8; display:block; margin-top:2px; }
    .sum-card.pay       { border-color:#f59e0b; } .sum-card.pay h4  { color:#d97706; } .sum-card.pay .val  { color:#d97706; }
    .sum-card.rcv       { border-color:#2563eb; } .sum-card.rcv h4  { color:#2563eb; } .sum-card.rcv .val  { color:#2563eb; }
    .sum-card.overdue   { border-color:#ef4444; } .sum-card.overdue h4 { color:#dc2626; } .sum-card.overdue .val { color:#dc2626; }
    .sum-card.upcoming  { border-color:#8b5cf6; } .sum-card.upcoming h4 { color:#7c3aed; } .sum-card.upcoming .val { color:#7c3aed; }

    /* ── Status / type badges ──────────────────────── */
    .s-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
    .s-pending    { background:#fef3c7; color:#92400e; }
    .s-paid       { background:#d1fae5; color:#065f46; }
    .s-overdue    { background:#fee2e2; color:#991b1b; }
    .s-cancelled  { background:#f3f4f6; color:#6b7280; }
    .s-approved   { background:#dbeafe; color:#1e40af; }
    .s-rejected   { background:#fce7f3; color:#9d174d; }
    .t-receive    { background:#dbeafe; color:#1d4ed8; }
    .t-pay        { background:#fef3c7; color:#92400e; }

    /* ── Schedule table ────────────────────────────── */
    .sched-table { width:100%; border-collapse:collapse; }
    .sched-table thead th { background:#1e293b; color:#e2e8f0; padding:10px 14px; text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; white-space:nowrap; }
    .sched-table tbody tr { border-bottom:1px solid #f1f5f9; transition:background .12s; }
    .sched-table tbody tr:hover { background:#f8fafc; }
    .sched-table tbody td { padding:11px 14px; font-size:13px; color:#334155; vertical-align:middle; }
    .sched-table tfoot td { padding:10px 14px; background:#f1f5f9; font-weight:700; font-size:13px; }
    .row-overdue { background:#fff5f5 !important; }
    .row-today   { background:#fffbeb !important; }
    .row-paid    { opacity:.65; }

    /* ── Date cell ─────────────────────────────────── */
    .date-today  { color:#d97706; font-weight:700; }
    .date-overdue { color:#dc2626; font-weight:700; }
    .date-future  { color:#334155; }
    .overdue-tag  { display:inline-block; background:#fee2e2; color:#dc2626; font-size:10px; font-weight:700; padding:1px 6px; border-radius:4px; margin-left:4px; }
    .today-tag    { display:inline-block; background:#fef3c7; color:#d97706; font-size:10px; font-weight:700; padding:1px 6px; border-radius:4px; margin-left:4px; }

    /* ── Action buttons ────────────────────────────── */
    .btn-act { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:6px; border:none; cursor:pointer; font-size:12px; transition:all .15s; }
    .btn-approve   { background:#d1fae5; color:#065f46; } .btn-approve:hover   { background:#a7f3d0; }
    .btn-reject    { background:#fce7f3; color:#9d174d; } .btn-reject:hover    { background:#fbcfe8; }
    .btn-reschedule{ background:#e0e7ff; color:#3730a3; } .btn-reschedule:hover{ background:#c7d2fe; }
    .btn-mark-paid { background:#1e40af; color:#fff;    } .btn-mark-paid:hover { background:#1e3a8a; }
    .btn-cancel-sched { background:#fef3c7; color:#d97706; } .btn-cancel-sched:hover { background:#fde68a; }
    .btn-del   { background:#fee2e2; color:#b91c1c; } .btn-del:hover { background:#fecaca; }

    /* ── Paid meta ─────────────────────────────────── */
    .paid-meta { font-size:11px; color:#1d4ed8; margin-top:3px; }

    /* ── Reschedule chip ───────────────────────────── */
    .resched-chip { display:inline-block; background:#e0e7ff; color:#3730a3; font-size:10px; font-weight:700; padding:1px 6px; border-radius:4px; margin-left:4px; }

    /* ── Approval info in table ────────────────────── */
    .approved-meta { font-size:11px; color:#059669; margin-top:3px; }
    .rejected-meta { font-size:11px; color:#9d174d; margin-top:3px; }

    /* ── Priority badges ──────────────────────────── */
    .p-badge { display:inline-flex; align-items:center; gap:3px; padding:2px 9px; border-radius:20px; font-size:10px; font-weight:800; cursor:pointer; transition:all .15s; border:none; }
    .p-high   { background:#fee2e2; color:#991b1b; }
    .p-medium { background:#fef3c7; color:#92400e; }
    .p-low    { background:#e0e7ff; color:#3730a3; }
    .p-high:hover   { background:#fecaca; }
    .p-medium:hover { background:#fde68a; }
    .p-low:hover    { background:#c7d2fe; }

    /* ── Empty state ───────────────────────────────── */
    .empty-state { text-align:center; padding:52px 20px; color:#94a3b8; }
    .empty-state i { font-size:42px; margin-bottom:12px; display:block; }
    .states-table-header { gap:12px; }
    .states-table-header h2 { flex:1 1 auto; min-width:220px; }
    .states-table-header .no-print { flex:0 1 auto; flex-wrap:wrap; justify-content:flex-end; }
    .states-table-header button { white-space:nowrap; }
    .print-only { display:none !important; }

    /* ── Split section boxes ──────────────────────── */
    .split-section { border-radius:10px; overflow:hidden; border:1.5px solid; margin-bottom:20px; box-shadow:0 2px 12px rgba(0,0,0,.06); }
    .split-section-rcv { border-color:#bfdbfe; }
    .split-section-pay { border-color:#fde68a; }
    .split-header { display:flex; justify-content:space-between; align-items:center; padding:14px 20px; }
    .split-header-rcv { background:linear-gradient(135deg,#1e40af,#2563eb); }
    .split-header-pay { background:linear-gradient(135deg,#b45309,#d97706); }
    .split-title { color:#fff; font-size:15px; font-weight:800; display:flex; align-items:center; gap:10px; }
    .split-title i { font-size:18px; opacity:.9; }
    .split-subtitle { font-size:12px; font-weight:500; opacity:.75; }
    .split-total { color:#fff; font-size:20px; font-weight:900; font-family:monospace; letter-spacing:.5px; }
    .split-total small { font-size:11px; font-weight:600; opacity:.8; display:block; text-align:right; margin-bottom:1px; }

    /* ── Grand total bar ──────────────────────────── */
    .grand-total-bar { display:flex; justify-content:flex-end; align-items:center; gap:12px; padding:14px 4px 4px; flex-wrap:wrap; }
    .grand-chip { display:inline-flex; align-items:center; gap:6px; padding:8px 18px; border-radius:8px; font-size:13px; font-weight:800; }
    .grand-chip-rcv { background:#dbeafe; color:#1e40af; }
    .grand-chip-pay { background:#fef3c7; color:#92400e; }
    .grand-chip-total { background:#1e293b; color:#f1f5f9; }

    @page {
        size: A4 portrait;
        margin: 10mm;
    }

    /* ── Print styles ──────────────────────────────── */
    @media print {
        /* journals.css sets body * { visibility:hidden } for its #printArea approach.
           Override here so our display-based approach works correctly. */
        body * { visibility: visible !important; }

        /* Hide non-print elements */
        .no-print, .states-table-header, .sched-summary,
        .filter-container, .modal-overlay, nav, header, .header,
        aside, .sidebar, #sidebar, #mainContentWrapper > .header,
        .pagination, footer, .p-4,
        #mobileMenuButton, #desktopSidebarButton, #closeSidebar,
        #chatToggleGlobal, #chatWindowGlobal, #commandPalette,
        [id*="chat"], [class*="chat-"],
        .fixed, .sticky { display:none !important; visibility:hidden !important; }
        html, body { width:auto !important; min-height:auto !important; background:#fff !important; }
        body { margin:0 !important; font-size:11px; color:#000; }
        main, #mainContentWrapper { margin:0 !important; padding:0 !important; min-height:auto !important; }
        .states-table { box-shadow:none !important; border:none !important; }
        .states-table-container { overflow:visible !important; }

        /* Show print-only blocks */
        .print-only { display:block !important; visibility:visible !important; }

        /* Company header */
        .print-header { text-align:center; border-bottom:2.5px solid #000; padding-bottom:10px; margin-bottom:14px; }
        .print-header h2 { font-size:18px; font-weight:800; letter-spacing:.5px; }
        .print-header .print-subtitle { font-size:12px; color:#333; margin-top:2px; }
        .print-meta { display:flex; justify-content:space-between; font-size:10px; color:#555; margin-bottom:14px; border-bottom:1px solid #ddd; padding-bottom:6px; }

        /* Section headings */
        .print-section-head { font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.5px;
                              border-left:4px solid; padding:5px 10px; margin:14px 0 6px; }
        .print-section-head.payable   { border-color:#d97706; color:#92400e; background:#fffbeb; }
        .print-section-head.receivable{ border-color:#2563eb; color:#1e40af; background:#eff6ff; }

        /* Print table */
        .print-table { width:100%; border-collapse:collapse; font-size:10px; margin-bottom:4px; }
        .print-table thead th { background:#1e293b !important; color:#fff !important;
                                padding:6px 8px; text-align:left; font-size:10px; font-weight:700;
                                -webkit-print-color-adjust:exact; print-color-adjust:exact; }
        .print-table tbody tr { border-bottom:1px solid #e5e7eb; }
        .print-table tbody tr.print-overdue { background:#fff5f5 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
        .print-table tbody tr.print-today   { background:#fffbeb !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
        .print-table tbody td { padding:5px 8px; vertical-align:top; }
        .print-table tfoot td { padding:6px 8px; border-top:2px solid #000; font-weight:800; background:#f9fafb !important;
                                -webkit-print-color-adjust:exact; print-color-adjust:exact; }
        .print-table .text-right { text-align:right; }
        .print-table .chk-cell { width:28px; text-align:center; border:1px solid #999; height:14px; }

        /* Approval box */
        .approval-box { margin-top:24px; border:1.5px solid #000; padding:14px 16px; border-radius:4px; page-break-inside:avoid; }
        .approval-box h4 { font-size:13px; font-weight:800; margin-bottom:6px; }
        .approval-box p { font-size:10px; color:#555; margin-bottom:10px; }
        .sig-row { display:flex; gap:24px; margin-top:16px; }
        .sig-line { flex:1; border-top:1px solid #666; padding-top:4px; font-size:10px; color:#444; text-align:center; }

        /* Grand summary */
        .print-grand { display:flex; gap:24px; justify-content:flex-end; margin-top:8px; font-size:11px; font-weight:700; }
        .print-grand span { padding:4px 12px; border-radius:4px; }
        .print-grand .pay-total { background:#fef3c7; color:#92400e; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
        .print-grand .rcv-total { background:#dbeafe; color:#1e40af; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    }
    @media (max-width:768px) {
        .states-table-header { align-items:flex-start; flex-direction:column; }
        .states-table-header h2 { min-width:0; }
        .states-table-header .no-print { justify-content:flex-start; width:100%; }
        .sched-summary { grid-template-columns:1fr 1fr; }
        .sched-table thead { display:none; }
        .sched-table tbody tr { display:block; margin-bottom:10px; border:1px solid #e2e8f0; border-radius:8px; }
        .sched-table tbody td { display:flex; justify-content:space-between; padding:9px 12px; border-bottom:1px solid #f1f5f9; }
        .sched-table tbody td::before { content:attr(data-label); font-weight:600; color:#64748b; font-size:11px; text-transform:uppercase; }
    }
</style>
@endsection

@section('main-content')
@php $role = Str::slug(Auth::user()->getRoleNames()->first()); @endphp

{{-- ═══════ MAIN CARD ═══════ --}}
<div class="states-table bg-white rounded-lg shadow-md overflow-hidden">
    <div class="states-table-container">

        {{-- Header --}}
        <div class="states-table-header bg-indigo-700 px-6 py-4 flex justify-between items-center" style="background:#4338ca;">
            <h2 class="text-white text-xl font-semibold" style="color:#fff;">
                <i class="fas fa-calendar-alt mr-2"></i>Payment Schedules
            </h2>
            <div class="no-print flex items-center gap-2">
                <button type="button" onclick="openAdHoc()" class="bg-purple-600 text-white hover:bg-purple-700 px-4 py-2 rounded-md font-semibold text-sm transition flex items-center gap-2" style="background:#7c3aed;">
                    <i class="fas fa-plus"></i> New Ad-hoc Schedule
                </button>
                <button type="button" onclick="printDailySheet()" class="bg-white text-indigo-700 hover:bg-indigo-50 px-4 py-2 rounded-md font-semibold text-sm transition flex items-center gap-2">
                    <i class="fas fa-print"></i> Print Daily Sheet
                </button>
            </div>
        </div>

        {{-- ════════════════════════════════════════════
             PRINT-ONLY FULL REPORT (hidden on screen)
             Shows payable + receivable as separate tables
             with approve/reject checkbox columns for boss
        ════════════════════════════════════════════ --}}
        <div class="print-only" style="padding:16px 20px 0;">

            {{-- Company header --}}
            <div class="print-header">
                <h2>EPAL ERP — Daily Payment Schedule</h2>
                <div class="print-subtitle">For Boss Approval &nbsp;|&nbsp; Prepared by: {{ Auth::user()->name }}</div>
            </div>

            <div class="print-meta">
                <span>Report Date: <strong>
                    {{ request('date_from') ? \Carbon\Carbon::parse(request('date_from'))->format('d M Y') : today()->format('d M Y') }}
                    {{ request('date_to') ? ' → ' . \Carbon\Carbon::parse(request('date_to'))->format('d M Y') : '' }}
                </strong></span>
                <span>Printed: {{ now()->format('d M Y, h:i A') }}</span>
                <span>Status Filter: {{ request('status') ? ucfirst(request('status')) : 'All Active' }}</span>
            </div>

            @php
                $printPayable    = $schedules->filter(fn($s) => $s->type === 'pay'     && !in_array($s->status, ['cancelled']));
                $printReceivable = $schedules->filter(fn($s) => $s->type === 'receive' && !in_array($s->status, ['cancelled']));
                $todayStr        = today()->format('Y-m-d');
            @endphp

            {{-- ── RECEIVABLE SECTION ── --}}
            @if($printReceivable->isNotEmpty())
            <div class="print-section-head receivable" style="margin-top:18px;">
                <i class="fas fa-arrow-down"></i> Receivable — Amounts Due from Customers / Agents
            </div>
            <table class="print-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer / Party</th>
                        <th>Reference</th>
                        <th>Scheduled Date</th>
                        <th>Note</th>
                        <th>Status</th>
                        <th class="text-right">Amount (৳)</th>
                        <th style="text-align:center;">Follow-up Note</th>
                    </tr>
                </thead>
                <tbody>
                @php $rcvTotal = 0; $rIdx = 1; @endphp
                @foreach($printReceivable as $ps)
                @php
                    $rcvTotal += $ps->amount;
                    $rName = $ps->party_name ?? ($ps->schedulable?->passportHolder?->name ?? $ps->schedulable?->customer?->name ?? ('#'.$ps->party_id));
                    $rRef  = $ps->schedulable?->invoice_no ?? $ps->schedulable?->ticket_no ?? ('ID#'.$ps->schedulable_id);
                    $rIsOverdue = $ps->status === 'overdue';
                    $rIsToday   = $ps->scheduled_date->format('Y-m-d') === $todayStr;
                @endphp
                <tr class="{{ $rIsOverdue ? 'print-overdue' : ($rIsToday ? 'print-today' : '') }}">
                    <td>{{ $rIdx++ }}</td>
                    <td><strong>{{ $rName }}</strong></td>
                    <td style="font-family:monospace;font-size:10px;">{{ $rRef }}</td>
                    <td>
                        {{ $ps->scheduled_date->format('d M Y') }}
                        @if($rIsOverdue) <span style="color:#dc2626;font-weight:700;">(OVERDUE)</span>
                        @elseif($rIsToday) <span style="color:#d97706;font-weight:700;">(TODAY)</span>
                        @endif
                        @if($ps->reschedule_count > 0)
                            <br><small style="color:#6b7280;">Rescheduled ×{{ $ps->reschedule_count }}</small>
                        @endif
                    </td>
                    <td style="color:#6b7280;">{{ $ps->note ?? '—' }}</td>
                    <td>{{ ucfirst($ps->status) }}</td>
                    <td class="text-right" style="font-weight:700;">{{ number_format($ps->amount, 2) }}</td>
                    <td style="border-bottom:1px dotted #999;">&nbsp;</td>
                </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" class="text-right">Total Receivable</td>
                        <td class="text-right">{{ number_format($rcvTotal, 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            @endif

            {{-- ── PAYABLE SECTION ── --}}
            @if($printPayable->isNotEmpty())
            <div class="print-section-head payable">
                <i class="fas fa-arrow-up"></i> Payable — Amounts Due to Suppliers / Vendors
            </div>
            <table class="print-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Supplier / Party</th>
                        <th>Reference</th>
                        <th>Scheduled Date</th>
                        <th>Note</th>
                        <th>Status</th>
                        <th class="text-right">Amount (৳)</th>
                        <th style="text-align:center;">✓ Pay</th>
                        <th style="text-align:center;">✗ Hold</th>
                    </tr>
                </thead>
                <tbody>
                @php $payTotal = 0; $pIdx = 1; @endphp
                @foreach($printPayable as $ps)
                @php
                    $payTotal += $ps->amount;
                    $pName = $ps->party_name ?? ($ps->schedulable?->supplier?->name ?? ($ps->party_type === 'embassy' ? 'Embassy' : ('#'.$ps->party_id)));
                    $pRef  =$ps->source_label ?? $ps->schedulable?->purchase_no ?? $ps->schedulable?->ticket_no ?? ('ID#'.$ps->schedulable_id);
                    $pIsOverdue = $ps->status === 'overdue';
                    $pIsToday   = $ps->scheduled_date->format('Y-m-d') === $todayStr;
                @endphp
                <tr class="{{ $pIsOverdue ? 'print-overdue' : ($pIsToday ? 'print-today' : '') }}">
                    <td>{{ $pIdx++ }}</td>
                    <td><strong>{{ $pName }}</strong></td>
                    <td style="font-family:monospace;font-size:10px;">{{ $pRef }}</td>
                    <td>
                        {{ $ps->scheduled_date->format('d M Y') }}
                        @if($pIsOverdue) <span style="color:#dc2626;font-weight:700;">(OVERDUE)</span>
                        @elseif($pIsToday) <span style="color:#d97706;font-weight:700;">(TODAY)</span>
                        @endif
                        @if($ps->reschedule_count > 0)
                            <br><small style="color:#6b7280;">Rescheduled ×{{ $ps->reschedule_count }}</small>
                        @endif
                    </td>
                    <td style="color:#6b7280;">{{ $ps->note ?? '—' }}</td>
                    <td>{{ ucfirst($ps->status) }}</td>
                    <td class="text-right" style="font-weight:700;">{{ number_format($ps->amount, 2) }}</td>
                    <td style="text-align:center;"><div class="chk-cell">&nbsp;</div></td>
                    <td style="text-align:center;"><div class="chk-cell">&nbsp;</div></td>
                </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" class="text-right">Total Payable</td>
                        <td class="text-right">{{ number_format($payTotal, 2) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
            @endif

            {{-- Grand summary --}}
            <div class="print-grand">
                @if($printPayable->isNotEmpty())
                <span class="pay-total">Total Payable: ৳ {{ number_format($printPayable->sum('amount'), 2) }}</span>
                @endif
                @if($printReceivable->isNotEmpty())
                <span class="rcv-total">Total Receivable: ৳ {{ number_format($printReceivable->sum('amount'), 2) }}</span>
                @endif
            </div>

            {{-- Approval box --}}
            <div class="approval-box">
                <h4><i class="fas fa-stamp" style="margin-right:6px;"></i>Approval — Boss Sign-off</h4>
                <p>Please review the above schedule. Tick ✓ Pay or ✗ Hold for each payable item. Sign below to authorise.</p>
                <div class="sig-row">
                    <div class="sig-line">Prepared By &nbsp;/&nbsp; Date</div>
                    <div class="sig-line">Reviewed By &nbsp;/&nbsp; Date</div>
                    <div class="sig-line">Approved By (Boss) &nbsp;/&nbsp; Date</div>
                </div>
            </div>

        </div>{{-- /print-only --}}

        {{-- Summary Cards --}}
        <div class="sched-summary no-print">
            <div class="sum-card pay">
                <h4><i class="fas fa-arrow-up mr-1"></i>Payable Today</h4>
                <div class="val">{{ number_format($todayPayable, 2) }}</div>
                <small>Due to suppliers today</small>
            </div>
            <div class="sum-card rcv">
                <h4><i class="fas fa-arrow-down mr-1"></i>Receivable Today</h4>
                <div class="val">{{ number_format($todayReceivable, 2) }}</div>
                <small>Due from customers today</small>
            </div>
            <div class="sum-card overdue">
                <h4><i class="fas fa-exclamation-triangle mr-1"></i>Overdue Total</h4>
                <div class="val">{{ number_format($overdueAmt, 2) }}</div>
                <small>Past due, not yet paid</small>
            </div>
            <div class="sum-card upcoming">
                <h4><i class="fas fa-calendar-week mr-1"></i>Next 7 Days</h4>
                <div class="val">{{ $upcomingCount }}</div>
                <small>Schedules upcoming</small>
            </div>
        </div>

        {{-- Filter --}}
        <form action="" method="GET" id="filterForm" class="no-print">
            <div class="filter-container" style="margin:14px 18px 0;">
                <div class="filter-header {{ request()->hasAny(['type','status','party','date_from','date_to','priority']) ? 'active' : '' }}"
                     id="filterToggleHeader">
                    <h3><i class="fas fa-filter mr-2"></i>Filter Options</h3>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
                <div class="filter-content {{ request()->hasAny(['type','status','party','date_from','date_to','priority']) ? 'active' : '' }}"
                     id="filterBody">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Date From</label>
                            <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
                        </div>
                        <div class="filter-group">
                            <label>Date To</label>
                            <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
                        </div>
                        <div class="filter-group">
                            <label>Type</label>
                            <select name="type" class="form-control">
                                <option value="">All Types</option>
                                <option value="pay"     {{ request('type') === 'pay'     ? 'selected' : '' }}>Payable (Pay)</option>
                                <option value="receive" {{ request('type') === 'receive' ? 'selected' : '' }}>Receivable (Receive)</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="pending"   {{ request('status') === 'pending'   ? 'selected' : '' }}>Pending</option>
                                <option value="overdue"   {{ request('status') === 'overdue'   ? 'selected' : '' }}>Overdue</option>
                                <option value="approved"  {{ request('status') === 'approved'  ? 'selected' : '' }}>Approved</option>
                                <option value="paid"      {{ request('status') === 'paid'      ? 'selected' : '' }}>Paid</option>
                                <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Party Type</label>
                            <select name="party" class="form-control">
                                <option value="">All Parties</option>
                                <option value="customer"        {{ request('party') === 'customer'        ? 'selected' : '' }}>Customer</option>
                                <option value="vendor"          {{ request('party') === 'vendor'          ? 'selected' : '' }}>Vendor / Supplier</option>
                                <option value="agent"           {{ request('party') === 'agent'           ? 'selected' : '' }}>Agent</option>
                                <option value="passport_holder" {{ request('party') === 'passport_holder' ? 'selected' : '' }}>Visa Applicant</option>
                                <option value="embassy"         {{ request('party') === 'embassy'         ? 'selected' : '' }}>Embassy (Visa)</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Priority</label>
                            <select name="priority" class="form-control">
                                <option value="">All Priorities</option>
                                <option value="high"   {{ request('priority') === 'high'   ? 'selected' : '' }}>High</option>
                                <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>Medium</option>
                                <option value="low"    {{ request('priority') === 'low'    ? 'selected' : '' }}>Low</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="button" class="reset-btn" id="resetFilterBtn">
                            <i class="fas fa-undo mr-1"></i>Reset
                        </button>
                        <button type="submit" class="apply-btn">
                            <i class="fas fa-search mr-1"></i>Apply Filters
                        </button>
                    </div>
                </div>
            </div>
        </form>

        {{-- Flash Messages --}}
        <div class="px-4 pt-4 no-print">
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded-md mb-3 flex items-center gap-2">
                    <i class="fas fa-check-circle"></i> {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded-md mb-3 flex items-center gap-2">
                    <i class="fas fa-times-circle"></i> {{ session('error') }}
                </div>
            @endif
            @if($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded-md mb-3">
                    @foreach($errors->all() as $err) <div><i class="fas fa-exclamation-circle mr-1"></i>{{ $err }}</div> @endforeach
                </div>
            @endif
        </div>

        {{-- Split: Receivable (top) + Payable (bottom) --}}
        <div class="p-4">
        @php
            $schedReceivable = $schedules->filter(fn($s) => $s->type === 'receive');
            $schedPayable    = $schedules->filter(fn($s) => $s->type === 'pay');
            $totalRcv        = $schedReceivable->sum('amount');
            $totalPay        = $schedPayable->sum('amount');
            $grandTotal      = $totalRcv + $totalPay;
            $todayDate       = today();

            $statusMap = [
                'pending'   => ['class' => 's-pending',   'icon' => 'clock',              'label' => 'Pending'],
                'paid'      => ['class' => 's-paid',      'icon' => 'check-circle',       'label' => 'Paid'],
                'overdue'   => ['class' => 's-overdue',   'icon' => 'exclamation-circle', 'label' => 'Overdue'],
                'cancelled' => ['class' => 's-cancelled', 'icon' => 'ban',                'label' => 'Cancelled'],
                'approved'  => ['class' => 's-approved',  'icon' => 'check-double',       'label' => 'Approved'],
                'rejected'  => ['class' => 's-rejected',  'icon' => 'times-circle',       'label' => 'Rejected'],
            ];
        @endphp

        @if($schedules->isEmpty())
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <p>No payment schedules found. Adjust your filters or create schedules from Sales / Purchases.</p>
            </div>
        @else

        {{-- ══════════════ RECEIVABLE SECTION (TOP) ══════════════ --}}
        @if($schedReceivable->isNotEmpty())
        <div class="split-section split-section-rcv">
            <div class="split-header split-header-rcv">
                <div class="split-title">
                    <i class="fas fa-arrow-circle-down"></i>
                    Receivable
                    <span class="split-subtitle">— Amounts to collect from customers / agents</span>
                </div>
                <div class="split-total">
                    <small>Total</small>
                    ৳ {{ number_format($totalRcv, 2) }}
                </div>
            </div>
            <div class="overflow-x-auto">
            <table class="sched-table">
                <thead>
                    <tr>
                        {{-- Party and Reference carry no sort link: both are resolved
                             from a morph relation in the loop below (customer /
                             supplier / ticket / passport holder), so there is no
                             column to order on. Both tables read from the one
                             $schedules paginator, so a sort click orders both. --}}
                        <th>#</th>
                        <th>Party</th>
                        <th>Reference</th>
                        <th>@include('partials.sortable-th', ['key' => 'scheduled_date', 'label' => 'Scheduled Date', 'dirDefault' => 'desc'])</th>
                        <th>@include('partials.sortable-th', ['key' => 'note', 'label' => 'Note'])</th>
                        <th>@include('partials.sortable-th', ['key' => 'priority', 'label' => 'Priority'])</th>
                        <th>@include('partials.sortable-th', ['key' => 'status', 'label' => 'Status'])</th>
                        <th class="text-right">@include('partials.sortable-th', ['key' => 'amount', 'label' => 'Amount (৳)', 'dirDefault' => 'desc'])</th>
                        <th class="no-print text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @php $rcvIdx = 1; @endphp
                @foreach($schedReceivable as $schedule)
                @php
                    $isOverdue = $schedule->status === 'overdue';
                    $isToday   = $schedule->scheduled_date ? $schedule->scheduled_date->isSameDay($todayDate) : false;
                    $isPaid    = $schedule->status === 'paid';
                    $rowClass  = $isOverdue ? 'row-overdue' : ($isToday ? 'row-today' : ($isPaid ? 'row-paid' : ''));

                    $partyName = $schedule->party_name ?? null;
                    if (!$partyName && $schedule->schedulable) {
                        $s = $schedule->schedulable;
                        $partyName = match($schedule->party_type) {
                            'customer'        => $s->customer?->name ?? ('Customer #' . $schedule->party_id),
                            'vendor'          => $s->supplier?->name ?? ('Supplier #' . $schedule->party_id),
                            'agent'           => 'Agent #' . $schedule->party_id,
                            'passport_holder' => $s->passportHolder?->name ?? ('Applicant #' . $schedule->party_id),
                            'embassy'         => $schedule->party_name ?? 'Embassy',
                            default           => $schedule->party_name ?? ('#' . $schedule->party_id),
                        };
                    }
                    $partyName ??= $schedule->party_type === 'embassy' ? 'Embassy' : ('#' . $schedule->party_id);

                    // source_label checked first (always set for visa/ticket entries)
                    $ref = $schedule->source_label ?? null;
                    if (!$ref && $schedule->schedulable) {
                        $s   = $schedule->schedulable;
                        $ref = $s->invoice_no ?? $s->purchase_no ?? $s->ticket_no ?? $s->application_id ?? ('ID#' . $s->id);
                    }
                    $sm = $statusMap[$schedule->status] ?? ['class' => 's-pending', 'icon' => 'circle', 'label' => ucfirst($schedule->status)];
                @endphp
                <tr id="payment-schedule-{{ $schedule->id }}" class="{{ $rowClass }}" data-payment-schedule-id="{{ $schedule->id }}">
                    <td data-label="#">{{ $rcvIdx++ }}</td>
                    <td data-label="Party">
                        <span class="font-semibold text-slate-800">{{ $partyName }}</span>
                        <br><small class="text-slate-400 capitalize">{{ $schedule->party_type }}</small>
                    </td>
                    <td data-label="Reference">
                        @if($ref)
                            <span class="code-chip">{{ $ref }}</span>
                        @elseif($schedule->source_label)
                            <span style="background:#f3e8ff;color:#7c3aed;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:700;">
                                <i class="fas fa-tag" style="margin-right:3px;"></i>{{ $schedule->source_label }}
                            </span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td data-label="Scheduled Date">
                        @if($schedule->scheduled_date)
                            @if($isOverdue)
                                <span class="date-overdue">{{ $schedule->scheduled_date->format('Y-m-d') }}<span class="overdue-tag">OVERDUE</span></span>
                            @elseif($isToday)
                                <span class="date-today">{{ $schedule->scheduled_date->format('Y-m-d') }}<span class="today-tag">TODAY</span></span>
                            @else
                                <span class="date-future">{{ $schedule->scheduled_date->format('Y-m-d') }}</span>
                            @endif
                            {{-- Same rule as every other table: the recorded time is
                                 shown only when it falls on the row's own date. A
                                 scheduled_date is usually in the future, so this
                                 stays hidden unless something was scheduled for the
                                 day it was entered. Inline style because this cell
                                 builds its own markup rather than using
                                 partials.date-time-cell, whose CSS ships with it. --}}
                            @if($schedule->created_at && $schedule->created_at->isSameDay($schedule->scheduled_date))
                                <div style="font-size:0.7rem;color:#94a3b8;margin-top:2px;">{{ $schedule->created_at->format('H:i:s') }}</div>
                            @endif
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                        @if($schedule->paid_date)
                            <br><small class="text-green-600">Paid: {{ $schedule->paid_date->format('d M Y') }}</small>
                        @endif
                    </td>
                    <td data-label="Note"><span class="text-slate-500 text-xs">{{ $schedule->note ?? '—' }}</span></td>
                    <td data-label="Priority" class="no-print">
                        @php $pri = $schedule->priority ?? 'medium'; @endphp
                        <div class="priority-cell" id="pri-cell-{{ $schedule->id }}" style="position:relative;">
                            <button type="button"
                                    class="p-badge p-{{ $pri }}"
                                    onclick="togglePriorityMenu({{ $schedule->id }})"
                                    title="Click to change priority">
                                <i class="fas fa-{{ $pri === 'high' ? 'exclamation-circle' : ($pri === 'low' ? 'arrow-circle-down' : 'minus-circle') }}"></i>
                                {{ ucfirst($pri) }}
                                <i class="fas fa-caret-down" style="font-size:9px;opacity:.6;"></i>
                            </button>
                            <div class="priority-menu" id="pri-menu-{{ $schedule->id }}" style="display:none;position:absolute;z-index:999;background:#fff;border:1.5px solid #e2e8f0;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.12);padding:6px;min-width:110px;margin-top:4px;">
                                @foreach(['high' => ['#991b1b','#fee2e2','exclamation-circle'], 'medium' => ['#92400e','#fef3c7','minus-circle'], 'low' => ['#3730a3','#e0e7ff','arrow-circle-down']] as $pv => $pstyle)
                                <button type="button" onclick="setPriority({{ $schedule->id }}, '{{ $pv }}')"
                                        style="display:flex;align-items:center;gap:6px;width:100%;padding:6px 10px;border-radius:6px;border:none;background:{{ $pv === $pri ? $pstyle[1] : 'transparent' }};color:{{ $pstyle[0] }};font-weight:700;font-size:12px;cursor:pointer;"
                                        onmouseover="this.style.background='{{ $pstyle[1] }}'" onmouseout="this.style.background='{{ $pv === $pri ? $pstyle[1] : 'transparent' }}'">
                                    <i class="fas fa-{{ $pstyle[2] }}"></i> {{ ucfirst($pv) }}
                                </button>
                                @endforeach
                            </div>
                        </div>
                    </td>
                    <td data-label="Status">
                        <span class="s-badge {{ $sm['class'] }}"><i class="fas fa-{{ $sm['icon'] }}"></i> {{ $sm['label'] }}</span>
                        @if($schedule->status === 'approved' && $schedule->approvedBy)
                            <div class="approved-meta"><i class="fas fa-user-check mr-1"></i>{{ $schedule->approvedBy->name }} · {{ $schedule->approved_at?->format('d M, h:i A') }}</div>
                        @endif
                        @if($schedule->status === 'rejected' && $schedule->approval_note)
                            <div class="rejected-meta"><i class="fas fa-comment-alt mr-1"></i>{{ Str::limit($schedule->approval_note, 35) }}</div>
                        @endif
                        @if($schedule->status === 'paid' && $schedule->paid_date)
                            <div class="paid-meta"><i class="fas fa-calendar-check mr-1"></i>Paid: {{ $schedule->paid_date->format('d M Y') }}</div>
                        @endif
                        @if($schedule->reschedule_count > 0)
                            <span class="resched-chip"><i class="fas fa-history"></i> ×{{ $schedule->reschedule_count }}</span>
                        @endif
                    </td>
                    <td data-label="Amount" class="text-right font-mono font-semibold" style="color:#2563eb;">
                        {{ number_format($schedule->amount, 2) }}
                        @if($schedule->schedulable && isset($schedule->schedulable->due_amount))
                            <div style="font-size:10px;font-weight:600;color:#64748b;margin-top:2px;white-space:nowrap;">Due: ৳ {{ number_format($schedule->schedulable->due_amount, 2) }}</div>
                        @endif
                    </td>
                    <td data-label="Actions" class="text-center no-print">
                        <div class="flex items-center justify-center gap-1 flex-wrap">
                            <a href="{{ route('role.payment-schedules.voucher', ['role' => $role, 'schedule' => $schedule->id]) }}" target="_blank" class="btn-act" title="Receivable Invoice" style="background:#dbeafe;color:#1d4ed8;"><i class="fas fa-file-invoice"></i></a>
                            @can('approve payment schedule')
                            @if(in_array($schedule->status, ['pending','overdue']))
                            <button class="btn-act btn-approve" title="Approve" onclick="openApprove({{ $schedule->id }}, '{{ addslashes($partyName) }}', '{{ number_format($schedule->amount, 2) }}')"><i class="fas fa-check"></i></button>
                            @endif
                            @endcan
                            @if($schedule->status === 'approved')
                            <button class="btn-act btn-mark-paid" title="Mark as Paid" onclick="openMarkPaid({{ $schedule->id }}, '{{ addslashes($partyName) }}', '{{ number_format($schedule->amount, 2) }}', {{ $schedule->amount }}, '{{ $schedule->type }}', '{{ $schedule->scheduled_date->format('Y-m-d') }}')"><i class="fas fa-money-bill-wave"></i></button>
                            @endif
                            @can('approve payment schedule')
                            @if(in_array($schedule->status, ['pending','overdue','approved']))
                            <button class="btn-act btn-reject" title="Reject" onclick="openReject({{ $schedule->id }}, '{{ addslashes($partyName) }}', '{{ number_format($schedule->amount, 2) }}')"><i class="fas fa-times"></i></button>
                            @endif
                            @endcan
                            @if(! in_array($schedule->status, ['paid','cancelled']))
                            <button class="btn-act btn-reschedule" title="Reschedule" onclick="openReschedule({{ $schedule->id }}, '{{ addslashes($partyName) }}', '{{ $schedule->scheduled_date->format('Y-m-d') }}', {{ $schedule->reschedule_count }})"><i class="fas fa-calendar-alt"></i></button>
                            @endif
                            @if(in_array($schedule->status, ['pending','overdue','approved','rejected']))
                            <form method="POST" action="{{ route('role.payment-schedules.cancel', ['role' => $role, 'schedule' => $schedule->id]) }}" id="cancelForm-{{ $schedule->id }}" style="display:inline;">@csrf @method('PATCH')</form>
                            <button class="btn-act btn-cancel-sched" title="Cancel" onclick="confirmCancel({{ $schedule->id }}, '{{ addslashes($partyName) }}', '{{ number_format($schedule->amount, 2) }}')"><i class="fas fa-ban"></i></button>
                            @endif
                            @if($schedule->status === 'cancelled')
                            <form method="POST" action="{{ route('role.payment-schedules.destroy', ['role' => $role, 'payment_schedule' => $schedule->id]) }}" id="deleteForm-{{ $schedule->id }}" style="display:inline;">@csrf @method('DELETE')</form>
                            <button class="btn-act btn-del" title="Delete" onclick="confirmDelete({{ $schedule->id }}, '{{ addslashes($partyName) }}')"><i class="fas fa-trash-alt"></i></button>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" class="text-right" style="font-size:12px;color:#64748b;">Receivable Total</td>
                        <td class="text-right" style="color:#1e40af;">৳ {{ number_format($totalRcv, 2) }}</td>
                        <td class="no-print"></td>
                    </tr>
                </tfoot>
            </table>
            </div>
        </div>
        @endif

        {{-- ══════════════ PAYABLE SECTION (BOTTOM) ══════════════ --}}
        @if($schedPayable->isNotEmpty())
        <div class="split-section split-section-pay">
            <div class="split-header split-header-pay">
                <div class="split-title">
                    <i class="fas fa-arrow-circle-up"></i>
                    Payable
                    <span class="split-subtitle">— Amounts due to suppliers / vendors</span>
                </div>
                <div class="split-total">
                    <small>Total</small>
                    ৳ {{ number_format($totalPay, 2) }}
                </div>
            </div>
            <div class="overflow-x-auto">
            <table class="sched-table">
                <thead>
                    <tr>
                        {{-- Party and Reference carry no sort link: both are resolved
                             from a morph relation in the loop below (customer /
                             supplier / ticket / passport holder), so there is no
                             column to order on. Both tables read from the one
                             $schedules paginator, so a sort click orders both. --}}
                        <th>#</th>
                        <th>Party</th>
                        <th>Reference</th>
                        <th>@include('partials.sortable-th', ['key' => 'scheduled_date', 'label' => 'Scheduled Date', 'dirDefault' => 'desc'])</th>
                        <th>@include('partials.sortable-th', ['key' => 'note', 'label' => 'Note'])</th>
                        <th>@include('partials.sortable-th', ['key' => 'priority', 'label' => 'Priority'])</th>
                        <th>@include('partials.sortable-th', ['key' => 'status', 'label' => 'Status'])</th>
                        <th class="text-right">@include('partials.sortable-th', ['key' => 'amount', 'label' => 'Amount (৳)', 'dirDefault' => 'desc'])</th>
                        <th class="no-print text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @php $payIdx = 1; @endphp
                @foreach($schedPayable as $schedule)
                @php
                    $isOverdue = $schedule->status === 'overdue';
                    $isToday   = $schedule->scheduled_date ? $schedule->scheduled_date->isSameDay($todayDate) : false;
                    $isPaid    = $schedule->status === 'paid';
                    $rowClass  = $isOverdue ? 'row-overdue' : ($isToday ? 'row-today' : ($isPaid ? 'row-paid' : ''));

                    $partyName = $schedule->party_name ?? null;
                    if (!$partyName && $schedule->schedulable) {
                        $s = $schedule->schedulable;
                        $partyName = match($schedule->party_type) {
                            'customer'        => $s->customer?->name ?? ('Customer #' . $schedule->party_id),
                            'vendor'          => $s->supplier?->name ?? ('Supplier #' . $schedule->party_id),
                            'agent'           => 'Agent #' . $schedule->party_id,
                            'passport_holder' => $s->passportHolder?->name ?? ('Applicant #' . $schedule->party_id),
                            'embassy'         => $schedule->party_name ?? 'Embassy',
                            default           => $schedule->party_name ?? ('#' . $schedule->party_id),
                        };
                    }
                    $partyName ??= $schedule->party_type === 'embassy' ? 'Embassy' : ('#' . $schedule->party_id);

                    // source_label checked first (always set for visa/ticket entries)
                    $ref = $schedule->source_label ?? null;
                    if (!$ref && $schedule->schedulable) {
                        $s   = $schedule->schedulable;
                        $ref = $s->invoice_no ?? $s->purchase_no ?? $s->ticket_no ?? $s->application_id ?? ('ID#' . $s->id);
                    }
                    $sm = $statusMap[$schedule->status] ?? ['class' => 's-pending', 'icon' => 'circle', 'label' => ucfirst($schedule->status)];
                @endphp
                <tr id="payment-schedule-{{ $schedule->id }}" class="{{ $rowClass }}" data-payment-schedule-id="{{ $schedule->id }}">
                    <td data-label="#">{{ $payIdx++ }}</td>
                    <td data-label="Party">
                        <span class="font-semibold text-slate-800">{{ $partyName }}</span>
                        <br><small class="text-slate-400 capitalize">{{ $schedule->party_type }}</small>
                    </td>
                    <td data-label="Reference">
                        @if($ref)
                            <span class="code-chip">{{ $ref }}</span>
                        @elseif($schedule->source_label)
                            <span style="background:#f3e8ff;color:#7c3aed;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:700;">
                                <i class="fas fa-tag" style="margin-right:3px;"></i>{{ $schedule->source_label }}
                            </span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td data-label="Scheduled Date">
                        @if($schedule->scheduled_date)
                            @if($isOverdue)
                                <span class="date-overdue">{{ $schedule->scheduled_date->format('Y-m-d') }}<span class="overdue-tag">OVERDUE</span></span>
                            @elseif($isToday)
                                <span class="date-today">{{ $schedule->scheduled_date->format('Y-m-d') }}<span class="today-tag">TODAY</span></span>
                            @else
                                <span class="date-future">{{ $schedule->scheduled_date->format('Y-m-d') }}</span>
                            @endif
                            {{-- Same rule as every other table: the recorded time is
                                 shown only when it falls on the row's own date. A
                                 scheduled_date is usually in the future, so this
                                 stays hidden unless something was scheduled for the
                                 day it was entered. Inline style because this cell
                                 builds its own markup rather than using
                                 partials.date-time-cell, whose CSS ships with it. --}}
                            @if($schedule->created_at && $schedule->created_at->isSameDay($schedule->scheduled_date))
                                <div style="font-size:0.7rem;color:#94a3b8;margin-top:2px;">{{ $schedule->created_at->format('H:i:s') }}</div>
                            @endif
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                        @if($schedule->paid_date)
                            <br><small class="text-green-600">Paid: {{ $schedule->paid_date->format('d M Y') }}</small>
                        @endif
                    </td>
                    <td data-label="Note"><span class="text-slate-500 text-xs">{{ $schedule->note ?? '—' }}</span></td>
                    <td data-label="Priority" class="no-print">
                        @php $pri = $schedule->priority ?? 'medium'; @endphp
                        <div class="priority-cell" id="pri-cell-{{ $schedule->id }}" style="position:relative;">
                            <button type="button"
                                    class="p-badge p-{{ $pri }}"
                                    onclick="togglePriorityMenu({{ $schedule->id }})"
                                    title="Click to change priority">
                                <i class="fas fa-{{ $pri === 'high' ? 'exclamation-circle' : ($pri === 'low' ? 'arrow-circle-down' : 'minus-circle') }}"></i>
                                {{ ucfirst($pri) }}
                                <i class="fas fa-caret-down" style="font-size:9px;opacity:.6;"></i>
                            </button>
                            <div class="priority-menu" id="pri-menu-{{ $schedule->id }}" style="display:none;position:absolute;z-index:999;background:#fff;border:1.5px solid #e2e8f0;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.12);padding:6px;min-width:110px;margin-top:4px;">
                                @foreach(['high' => ['#991b1b','#fee2e2','exclamation-circle'], 'medium' => ['#92400e','#fef3c7','minus-circle'], 'low' => ['#3730a3','#e0e7ff','arrow-circle-down']] as $pv => $pstyle)
                                <button type="button" onclick="setPriority({{ $schedule->id }}, '{{ $pv }}')"
                                        style="display:flex;align-items:center;gap:6px;width:100%;padding:6px 10px;border-radius:6px;border:none;background:{{ $pv === $pri ? $pstyle[1] : 'transparent' }};color:{{ $pstyle[0] }};font-weight:700;font-size:12px;cursor:pointer;"
                                        onmouseover="this.style.background='{{ $pstyle[1] }}'" onmouseout="this.style.background='{{ $pv === $pri ? $pstyle[1] : 'transparent' }}'">
                                    <i class="fas fa-{{ $pstyle[2] }}"></i> {{ ucfirst($pv) }}
                                </button>
                                @endforeach
                            </div>
                        </div>
                    </td>
                    <td data-label="Status">
                        <span class="s-badge {{ $sm['class'] }}"><i class="fas fa-{{ $sm['icon'] }}"></i> {{ $sm['label'] }}</span>
                        @if($schedule->status === 'approved' && $schedule->approvedBy)
                            <div class="approved-meta"><i class="fas fa-user-check mr-1"></i>{{ $schedule->approvedBy->name }} · {{ $schedule->approved_at?->format('d M, h:i A') }}</div>
                        @endif
                        @if($schedule->status === 'rejected' && $schedule->approval_note)
                            <div class="rejected-meta"><i class="fas fa-comment-alt mr-1"></i>{{ Str::limit($schedule->approval_note, 35) }}</div>
                        @endif
                        @if($schedule->status === 'paid' && $schedule->paid_date)
                            <div class="paid-meta"><i class="fas fa-calendar-check mr-1"></i>Paid: {{ $schedule->paid_date->format('d M Y') }}</div>
                        @endif
                        @if($schedule->reschedule_count > 0)
                            <span class="resched-chip"><i class="fas fa-history"></i> ×{{ $schedule->reschedule_count }}</span>
                        @endif
                    </td>
                    <td data-label="Amount" class="text-right font-mono font-semibold" style="color:#d97706;">
                        {{ number_format($schedule->amount, 2) }}
                        @if($schedule->schedulable && isset($schedule->schedulable->due_amount))
                            <div style="font-size:10px;font-weight:600;color:#64748b;margin-top:2px;white-space:nowrap;">Due: ৳ {{ number_format($schedule->schedulable->due_amount, 2) }}</div>
                        @endif
                    </td>
                    <td data-label="Actions" class="text-center no-print">
                        <div class="flex items-center justify-center gap-1 flex-wrap">
                            <a href="{{ route('role.payment-schedules.voucher', ['role' => $role, 'schedule' => $schedule->id]) }}" target="_blank" class="btn-act" title="Payment Voucher" style="background:#fef3c7;color:#92400e;"><i class="fas fa-file-invoice"></i></a>
                            @can('approve payment schedule')
                            @if(in_array($schedule->status, ['pending','overdue']))
                            <button class="btn-act btn-approve" title="Approve" onclick="openApprove({{ $schedule->id }}, '{{ addslashes($partyName) }}', '{{ number_format($schedule->amount, 2) }}')"><i class="fas fa-check"></i></button>
                            @endif
                            @endcan
                            @if($schedule->status === 'approved')
                            <button class="btn-act btn-mark-paid" title="Mark as Paid" onclick="openMarkPaid({{ $schedule->id }}, '{{ addslashes($partyName) }}', '{{ number_format($schedule->amount, 2) }}', {{ $schedule->amount }}, '{{ $schedule->type }}', '{{ $schedule->scheduled_date->format('Y-m-d') }}')"><i class="fas fa-money-bill-wave"></i></button>
                            @endif
                            @can('approve payment schedule')
                            @if(in_array($schedule->status, ['pending','overdue','approved']))
                            <button class="btn-act btn-reject" title="Reject" onclick="openReject({{ $schedule->id }}, '{{ addslashes($partyName) }}', '{{ number_format($schedule->amount, 2) }}')"><i class="fas fa-times"></i></button>
                            @endif
                            @endcan
                            @if(! in_array($schedule->status, ['paid','cancelled']))
                            <button class="btn-act btn-reschedule" title="Reschedule" onclick="openReschedule({{ $schedule->id }}, '{{ addslashes($partyName) }}', '{{ $schedule->scheduled_date->format('Y-m-d') }}', {{ $schedule->reschedule_count }})"><i class="fas fa-calendar-alt"></i></button>
                            @endif
                            @if(in_array($schedule->status, ['pending','overdue','approved','rejected']))
                            <form method="POST" action="{{ route('role.payment-schedules.cancel', ['role' => $role, 'schedule' => $schedule->id]) }}" id="cancelForm-{{ $schedule->id }}" style="display:inline;">@csrf @method('PATCH')</form>
                            <button class="btn-act btn-cancel-sched" title="Cancel" onclick="confirmCancel({{ $schedule->id }}, '{{ addslashes($partyName) }}', '{{ number_format($schedule->amount, 2) }}')"><i class="fas fa-ban"></i></button>
                            @endif
                            @if($schedule->status === 'cancelled')
                            <form method="POST" action="{{ route('role.payment-schedules.destroy', ['role' => $role, 'payment_schedule' => $schedule->id]) }}" id="deleteForm-{{ $schedule->id }}" style="display:inline;">@csrf @method('DELETE')</form>
                            <button class="btn-act btn-del" title="Delete" onclick="confirmDelete({{ $schedule->id }}, '{{ addslashes($partyName) }}')"><i class="fas fa-trash-alt"></i></button>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" class="text-right" style="font-size:12px;color:#64748b;">Payable Total</td>
                        <td class="text-right" style="color:#b45309;">৳ {{ number_format($totalPay, 2) }}</td>
                        <td class="no-print"></td>
                    </tr>
                </tfoot>
            </table>
            </div>
        </div>
        @endif

        {{-- Grand Total Bar --}}
        @if($schedReceivable->isNotEmpty() || $schedPayable->isNotEmpty())
        <div class="grand-total-bar no-print">
            @if($schedReceivable->isNotEmpty())
            <span class="grand-chip grand-chip-rcv"><i class="fas fa-arrow-circle-down mr-1"></i>Receivable: ৳ {{ number_format($totalRcv, 2) }}</span>
            @endif
            @if($schedPayable->isNotEmpty())
            <span class="grand-chip grand-chip-pay"><i class="fas fa-arrow-circle-up mr-1"></i>Payable: ৳ {{ number_format($totalPay, 2) }}</span>
            @endif
            <span class="grand-chip grand-chip-total"><i class="fas fa-calculator mr-1"></i>Grand Total: ৳ {{ number_format($grandTotal, 2) }}</span>
        </div>
        @endif

        {{-- Pagination --}}
        @if($schedules->hasPages())
        <div class="mt-4 flex justify-between items-center text-sm text-gray-500 no-print">
            <span>Showing {{ $schedules->firstItem() }}–{{ $schedules->lastItem() }} of {{ $schedules->total() }} schedules</span>
            <div>{{ $schedules->links() }}</div>
        </div>
        @endif

        @endif
        </div>{{-- /p-4 --}}

    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════
     MODAL: APPROVE
═══════════════════════════════════════════════════════════════════ --}}
<div class="modal-overlay no-print" id="approveModal">
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-header" style="background:#059669;">
            <h4><i class="fas fa-check-circle mr-2"></i>Approve Payment</h4>
            <button type="button" class="modal-close" onclick="schedClose('approveModal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="approveForm" method="POST">
            @csrf @method('PATCH')
            <div class="modal-body">
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;">
                    <div><strong>Party:</strong> <span id="ap_party" class="text-green-800 font-semibold"></span></div>
                    <div><strong>Amount:</strong> <span id="ap_amount" class="text-green-700 font-mono font-bold"></span></div>
                </div>
                <div class="form-group">
                    <label class="form-group" style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">
                        Approval Note <span style="color:#94a3b8;font-weight:400;">(optional)</span>
                    </label>
                    <textarea name="approval_note" id="ap_note" rows="3"
                              class="form-control"
                              placeholder="Any remarks from the boss…"
                              style="resize:vertical;"></textarea>
                </div>
                <div style="background:#d1fae5;border-radius:6px;padding:10px 12px;font-size:12px;color:#065f46;margin-top:4px;">
                    <i class="fas fa-info-circle mr-1"></i>
                    After approval, the accountant can <strong>Mark as Paid</strong> once the actual payment is made.
                    Your name and timestamp will be recorded.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="schedClose('approveModal')"
                        style="padding:9px 20px;border-radius:7px;background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;cursor:pointer;font-weight:600;">
                    Cancel
                </button>
                <button type="submit"
                        style="padding:9px 22px;border-radius:7px;background:#059669;color:#fff;border:none;cursor:pointer;font-weight:700;">
                    <i class="fas fa-check mr-1"></i>Confirm Approval
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════
     MODAL: REJECT
═══════════════════════════════════════════════════════════════════ --}}
<div class="modal-overlay no-print" id="rejectModal">
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-header" style="background:#be185d;">
            <h4><i class="fas fa-times-circle mr-2"></i>Reject Payment</h4>
            <button type="button" class="modal-close" onclick="schedClose('rejectModal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="rejectForm" method="POST">
            @csrf @method('PATCH')
            <div class="modal-body">
                <div style="background:#fdf2f8;border:1px solid #fbcfe8;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;">
                    <div><strong>Party:</strong> <span id="rj_party" class="font-semibold" style="color:#9d174d;"></span></div>
                    <div><strong>Amount:</strong> <span id="rj_amount" class="font-mono font-bold" style="color:#9d174d;"></span></div>
                </div>
                <div class="form-group">
                    <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">
                        Reason for Rejection <span style="color:#ef4444;">*</span>
                    </label>
                    <textarea name="approval_note" id="rj_note" rows="3"
                              class="form-control"
                              placeholder="Why is this payment being rejected?…"
                              style="resize:vertical;" required></textarea>
                    <div id="rj_err" style="color:#ef4444;font-size:12px;margin-top:4px;display:none;">Reason is required.</div>
                </div>
                <div style="background:#fce7f3;border-radius:6px;padding:10px 12px;font-size:12px;color:#9d174d;margin-top:4px;">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Rejected schedules can be <strong>rescheduled</strong> to a new date if needed later.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="schedClose('rejectModal')"
                        style="padding:9px 20px;border-radius:7px;background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;cursor:pointer;font-weight:600;">
                    Cancel
                </button>
                <button type="submit" id="rj_submitBtn"
                        style="padding:9px 22px;border-radius:7px;background:#be185d;color:#fff;border:none;cursor:pointer;font-weight:700;">
                    <i class="fas fa-times mr-1"></i>Confirm Rejection
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════
     MODAL: RESCHEDULE
═══════════════════════════════════════════════════════════════════ --}}
<div class="modal-overlay no-print" id="rescheduleModal">
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-header" style="background:#4338ca;">
            <h4><i class="fas fa-calendar-alt mr-2"></i>Reschedule Payment</h4>
            <button type="button" class="modal-close" onclick="schedClose('rescheduleModal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="rescheduleForm" method="POST">
            @csrf @method('PATCH')
            <div class="modal-body">
                <div style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;">
                    <div><strong>Party:</strong> <span id="rs_party" class="font-semibold" style="color:#3730a3;"></span></div>
                    <div style="margin-top:4px;"><strong>Current Date:</strong>
                        <span id="rs_old_date" style="color:#dc2626;font-weight:700;text-decoration:line-through;margin-right:6px;"></span>
                        <span id="rs_count_info" style="font-size:11px;color:#6b7280;"></span>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">
                        New Due Date <span style="color:#ef4444;">*</span>
                    </label>
                    <input type="date" name="new_date" id="rs_new_date"
                           class="form-control" required
                           style="width:100%;padding:10px 12px;border:1.5px solid #c7d2fe;border-radius:7px;font-size:14px;font-weight:600;color:#3730a3;">
                </div>
                <div class="form-group">
                    <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">
                        Reason <span style="color:#ef4444;">*</span>
                    </label>
                    <textarea name="reason" id="rs_reason" rows="2"
                              class="form-control"
                              placeholder="e.g. Cash flow delay, awaiting bank clearance…"
                              style="resize:vertical;" required></textarea>
                    <div id="rs_err" style="color:#ef4444;font-size:12px;margin-top:4px;display:none;">Reason is required.</div>
                </div>
                <div style="background:#e0e7ff;border-radius:6px;padding:10px 12px;font-size:12px;color:#3730a3;margin-top:8px;">
                    <i class="fas fa-history mr-1"></i>
                    The old date and reason are <strong>saved to the log</strong> automatically so your boss can see the full history on the next print.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="schedClose('rescheduleModal')"
                        style="padding:9px 20px;border-radius:7px;background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;cursor:pointer;font-weight:600;">
                    Cancel
                </button>
                <button type="submit"
                        style="padding:9px 22px;border-radius:7px;background:#4338ca;color:#fff;border:none;cursor:pointer;font-weight:700;">
                    <i class="fas fa-calendar-check mr-1"></i>Save New Date
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════
     MODAL: MARK PAID
═══════════════════════════════════════════════════════════════════ --}}
<div class="modal-overlay no-print" id="markPaidModal">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header" style="background:#1e40af;">
            <h4><i class="fas fa-money-bill-wave mr-2"></i>Mark as Paid</h4>
            <button type="button" class="modal-close" onclick="schedClose('markPaidModal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="markPaidForm" method="POST">
            @csrf @method('PATCH')
            <div class="modal-body">

                {{-- Summary banner --}}
                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 16px;margin-bottom:18px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <div style="font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.4px;">Party</div>
                            <div id="mp_party" style="font-weight:700;color:#1e293b;font-size:15px;"></div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.4px;" id="mp_type_label">Amount to Pay</div>
                            <div id="mp_amount" style="font-weight:800;font-size:20px;color:#1e40af;font-family:monospace;"></div>
                        </div>
                    </div>
                </div>

                {{-- Partial payment toggle --}}
                <div style="margin-bottom:14px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600;color:#374151;font-size:13px;">
                        <input type="checkbox" id="mp_partial_toggle" onchange="togglePartialPayment()" style="width:16px;height:16px;cursor:pointer;">
                        Partial Payment
                        <span style="font-size:11px;color:#64748b;font-weight:400;">(আংশিক পেমেন্ট করুন)</span>
                    </label>
                </div>

                {{-- Partial fields (hidden by default) --}}
                <div id="mp_partial_fields" style="display:none;background:#fef9c3;border:1px solid #fde68a;border-radius:8px;padding:14px 16px;margin-bottom:14px;">
                    <div class="form-row" style="margin-bottom:0;">
                        <div class="form-group half">
                            <label>এখন পরিশোধের পরিমাণ <span class="req">*</span></label>
                            <input type="number" name="paid_amount" id="mp_paid_amount"
                                   class="form-control" step="0.01" min="0.01"
                                   placeholder="0.00" oninput="updateRemainder()">
                        </div>
                        <div class="form-group half">
                            <label>বাকি টাকার নতুন তারিখ</label>
                            <input type="date" name="remainder_date" id="mp_remainder_date" class="form-control">
                        </div>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0 0;border-top:1px dashed #fde68a;margin-top:8px;">
                        <span style="font-size:12px;color:#92400e;font-weight:600;">বাকি থাকবে:</span>
                        <span id="mp_remaining_display" style="font-size:15px;font-weight:800;color:#b45309;font-family:monospace;">৳ 0.00</span>
                    </div>
                    <div style="font-size:11px;color:#92400e;margin-top:6px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        বাকি অংশ স্বয়ংক্রিয়ভাবে নতুন Pending Schedule হিসেবে তৈরি হবে।
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group half">
                        <label>Payment Date <span class="req">*</span></label>
                        {{-- max: a payment already made cannot be dated in the future.
                             Server-side twin lives in PaymentScheduleController::markPaid(). --}}
                        <input type="date" name="payment_date" id="mp_date"
                               class="form-control" value="{{ date('Y-m-d') }}"
                               max="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-group half">
                        <label>Payment Method <span class="req">*</span></label>
                        <select name="payment_method" id="mp_method" class="form-control" required>
                            <option value="">— Select —</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="mobile_banking">Mobile Banking (bKash/Nagad)</option>
                            <option value="cheque">Cheque</option>
                            <option value="card">Card</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group half">
                        <label>Bank Account <span class="req">*</span></label>
                        <select name="bank_id" id="mp_bank" class="form-control" required>
                            <option value="">— Select Bank —</option>
                            @foreach($banks as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->name }} — {{ $bank->account_number }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group half">
                        <label>Reference / Cheque No</label>
                        <input type="text" name="note" id="mp_note"
                               class="form-control" placeholder="Optional reference…">
                    </div>
                </div>

                {{-- Warning: no approval --}}
                <div id="mp_no_approval_warn" style="display:none;background:#fef9c3;border:1px solid #fde68a;border-radius:7px;padding:10px 12px;font-size:12px;color:#92400e;margin-top:8px;">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    This schedule has not been <strong>approved by the boss</strong> yet.
                    Marking as paid directly will bypass the approval step.
                </div>

                <div style="background:#dbeafe;border-radius:7px;padding:10px 12px;font-size:12px;color:#1e40af;margin-top:10px;">
                    <i class="fas fa-info-circle mr-1"></i>
                    This will create a <strong>Transaction record</strong> and reduce the
                    <strong>due amount</strong> on the linked Sale / Purchase automatically.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="schedClose('markPaidModal')"
                        style="padding:9px 20px;border-radius:7px;background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;cursor:pointer;font-weight:600;">
                    Cancel
                </button>
                <button type="submit" id="mp_submitBtn"
                        style="padding:9px 22px;border-radius:7px;background:#1e40af;color:#fff;border:none;cursor:pointer;font-weight:700;">
                    <i class="fas fa-check-double mr-1"></i>Confirm Payment
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════
     MODAL: NEW AD-HOC SCHEDULE
═══════════════════════════════════════════════════════════════════ --}}
<div class="modal-overlay no-print" id="adHocModal">
    <div class="modal-box" style="max-width:560px;">
        <div class="modal-header" style="background:#7c3aed;">
            <h4><i class="fas fa-plus-circle mr-2"></i>New Ad-hoc Schedule</h4>
            <button type="button" class="modal-close" onclick="schedClose('adHocModal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="adHocForm" method="POST"
              action="{{ route('role.payment-schedules.adhoc', ['role' => $role]) }}">
            @csrf
            <div class="modal-body">

                <div style="background:#f5f3ff;border:1px solid #ddd6fe;border-radius:8px;padding:12px 14px;margin-bottom:18px;font-size:12.5px;color:#6d28d9;">
                    <i class="fas fa-info-circle mr-1"></i>
                    Use this form to schedule payments not linked to any invoice — e.g. office rent, utility bill, government tax, loan instalment, advance to employee.
                </div>

                <div class="form-row">
                    {{-- company_id --}}
                    <div class="form-group half">
                        <label>Company <span class="req">*</span></label>
                        <select name="company_id" id="ah_company_id" class="form-control" required>
                            <option value="">— Select —</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group half">
                        <label>Project Category <span style="color:#94a3b8;font-weight:400;">(optional)</span></label>
                        <select name="project_category_id" id="ah_project_category_id" class="form-control">
                            <option value="">— Select Company first —</option>
                        </select>
                    </div>
                    <div class="form-group half">
                        <label>Schedule Type <span class="req">*</span></label>
                        <select name="type" id="ah_type" class="form-control" required>
                            <option value="">— Select —</option>
                            <option value="pay">Payable (Pay Out)</option>
                            <option value="receive">Receivable (Collect)</option>
                        </select>
                    </div>
                    <div class="form-group half">
                        <label>Party Type <span class="req">*</span></label>
                        <select name="party_type_id" id="ah_party_type_id" class="form-control" required>
                            <option value="">— Select —</option>
                            @foreach($partyTypes as $pt)
                                <option value="{{ $pt->id }}"
                                        data-has-model="{{ $pt->model_class ? '1' : '0' }}">
                                    {{ $pt->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Model-based party: dropdown loaded via AJAX --}}
                    <div class="form-group" style="flex:1 1 100%; display:none;" id="ah_party_select_wrap">
                        <label>Select Party <span class="req">*</span></label>
                        <select name="party_id" id="ah_party_id" class="form-control">
                            <option value="">— Select Party Type first —</option>
                        </select>
                    </div>

                    {{-- Others: free text --}}
                    <div class="form-group" style="flex:1 1 100%; display:none;" id="ah_party_name_wrap">
                        <label>Party Name <span class="req">*</span></label>
                        <input type="text" name="party_name" id="ah_party_name"
                               class="form-control" placeholder="e.g. Dhaka Power Supply, Mr. Karim…" maxlength="200">
                    </div>
                    <div class="form-group" style="flex:1 1 100%;">
                        <label>Purpose / Label <span class="req">*</span></label>
                        <input type="text" name="source_label" id="ah_source_label"
                               class="form-control" placeholder="e.g. Office Rent – May 2026, ISP Bill, Govt VAT…" required maxlength="200">
                    </div>
                    <div class="form-group half">
                        <label>Amount (৳) <span class="req">*</span></label>
                        <input type="number" name="amount" id="ah_amount"
                               class="form-control" placeholder="0.00" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group half">
                        <label>Scheduled Date <span class="req">*</span></label>
                        <input type="date" name="scheduled_date" id="ah_date"
                               class="form-control" required>
                    </div>
                    <div class="form-group half">
                        <label>Priority <span class="req">*</span></label>
                        <select name="priority" id="ah_priority" class="form-control" required>
                            <option value="medium" selected>Medium (Normal)</option>
                            <option value="high">High (Urgent)</option>
                            <option value="low">Low (Can Wait)</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1 1 100%;">
                        <label>Note <span style="color:#94a3b8;font-weight:400;">(optional)</span></label>
                        <textarea name="note" id="ah_note" rows="2"
                                  class="form-control"
                                  placeholder="Any additional details…" maxlength="500"></textarea>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" onclick="schedClose('adHocModal')"
                        style="padding:9px 20px;border-radius:7px;background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;cursor:pointer;font-weight:600;">
                    Cancel
                </button>
                <button type="submit"
                        style="padding:9px 22px;border-radius:7px;background:#7c3aed;color:#fff;border:none;cursor:pointer;font-weight:700;">
                    <i class="fas fa-save mr-1"></i>Create Schedule
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
const _role = '{{ $role }}';

$(document).ready(function () {

    /* ── Filter toggle ─────────────────────────────── */
    $('#filterToggleHeader').on('click', function () {
        $(this).toggleClass('active');
        $('#filterBody').toggleClass('active');
    });

    /* ── Reset filter ──────────────────────────────── */
    $('#resetFilterBtn').on('click', function () {
        window.location.href = '{{ route("role.payment-schedules.index", ["role" => $role]) }}';
    });

    /* ── Close modals on overlay click ────────────── */
    $('.modal-overlay').on('click', function (e) {
        if ($(e.target).hasClass('modal-overlay')) schedClose($(this).attr('id'));
    });

    /* ── Reject form validation ────────────────────── */
    $('#rejectForm').on('submit', function (e) {
        const note = $('#rj_note').val().trim();
        if (!note) { e.preventDefault(); $('#rj_err').show(); return; }
        $('#rj_err').hide();
    });

    /* ── Reschedule form validation ────────────────── */
    $('#rescheduleForm').on('submit', function (e) {
        const reason = $('#rs_reason').val().trim();
        if (!reason) { e.preventDefault(); $('#rs_err').show(); return; }
        $('#rs_err').hide();
    });
});

/* ── Modal helpers ──────────────────────────────── */
function schedClose(id) {
    document.getElementById(id).classList.remove('show');
    document.body.style.overflow = '';
}
function schedOpen(id) {
    document.getElementById(id).classList.add('show');
    document.body.style.overflow = 'hidden';
}

/* ── APPROVE modal ──────────────────────────────── */
function openApprove(id, party, amount) {
    document.getElementById('ap_party').textContent  = party;
    document.getElementById('ap_amount').textContent = '৳ ' + amount;
    document.getElementById('ap_note').value         = '';
    document.getElementById('approveForm').action    =
        '/' + _role + '/payment-schedules/' + id + '/approve';
    schedOpen('approveModal');
}

/* ── REJECT modal ───────────────────────────────── */
function openReject(id, party, amount) {
    document.getElementById('rj_party').textContent  = party;
    document.getElementById('rj_amount').textContent = '৳ ' + amount;
    document.getElementById('rj_note').value         = '';
    document.getElementById('rj_err').style.display  = 'none';
    document.getElementById('rejectForm').action     =
        '/' + _role + '/payment-schedules/' + id + '/reject';
    schedOpen('rejectModal');
}

/* ── RESCHEDULE modal ───────────────────────────── */
function openReschedule(id, party, currentDate, reschedCount) {
    document.getElementById('rs_party').textContent    = party;
    document.getElementById('rs_old_date').textContent = currentDate;
    document.getElementById('rs_count_info').textContent =
        reschedCount > 0 ? '(rescheduled ' + reschedCount + ' time' + (reschedCount > 1 ? 's' : '') + ' before)' : '';
    document.getElementById('rs_new_date').value       = '';
    document.getElementById('rs_reason').value         = '';
    document.getElementById('rs_err').style.display    = 'none';
    document.getElementById('rescheduleForm').action   =
        '/' + _role + '/payment-schedules/' + id + '/reschedule';
    schedOpen('rescheduleModal');
}

/* ── MARK PAID modal ────────────────────────────── */
var _mpRawAmount = 0;
function openMarkPaid(id, party, displayAmount, rawAmount, type, scheduledDate) {
    _mpRawAmount = rawAmount;
    document.getElementById('mp_party').textContent            = party;
    document.getElementById('mp_amount').textContent           = '৳ ' + displayAmount;
    document.getElementById('mp_type_label').textContent       = type === 'pay' ? 'Amount to Pay Out' : 'Amount to Collect';
    document.getElementById('mp_date').value                   = new Date().toISOString().split('T')[0];
    document.getElementById('mp_method').value                 = '';
    document.getElementById('mp_bank').value                   = '';
    document.getElementById('mp_note').value                   = '';
    document.getElementById('mp_partial_toggle').checked       = false;
    document.getElementById('mp_partial_fields').style.display = 'none';
    var paidInput = document.getElementById('mp_paid_amount');
    paidInput.value   = '';
    paidInput.max     = rawAmount;
    paidInput.required = false;
    document.getElementById('mp_remainder_date').value         = scheduledDate;
    document.getElementById('mp_remaining_display').textContent = '৳ 0.00';
    document.getElementById('markPaidForm').action             =
        '/' + _role + '/payment-schedules/' + id + '/mark-paid';
    schedOpen('markPaidModal');
}
function togglePartialPayment() {
    var checked = document.getElementById('mp_partial_toggle').checked;
    var fields  = document.getElementById('mp_partial_fields');
    var input   = document.getElementById('mp_paid_amount');
    fields.style.display = checked ? 'block' : 'none';
    input.required       = checked;
    if (checked) { input.focus(); updateRemainder(); }
}
function updateRemainder() {
    var paid      = parseFloat(document.getElementById('mp_paid_amount').value) || 0;
    var remaining = Math.max(0, _mpRawAmount - paid);
    document.getElementById('mp_remaining_display').textContent =
        '৳ ' + remaining.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/* ── PRINT daily sheet ──────────────────────────── */
function printDailySheet() {
    window.print();
}

/* ── AD-HOC modal ───────────────────────────────── */
function openAdHoc() {
    document.getElementById('ah_company_id').value    = '';
    document.getElementById('ah_type').value          = '';
    document.getElementById('ah_project_category_id').innerHTML = '<option value="">— Select Company first —</option>';
    document.getElementById('ah_party_type_id').innerHTML = '<option value="">— Select Company first —</option>';
    document.getElementById('ah_party_id').value           = '';
    document.getElementById('ah_party_name').value    = '';
    document.getElementById('ah_source_label').value  = '';
    document.getElementById('ah_amount').value        = '';
    document.getElementById('ah_date').value          = new Date().toISOString().split('T')[0];
    document.getElementById('ah_priority').value      = 'medium';
    document.getElementById('ah_note').value          = '';
    document.getElementById('ah_party_select_wrap').style.display = 'none';
    document.getElementById('ah_party_name_wrap').style.display   = 'none';
    document.getElementById('ah_party_id').required   = false;
    document.getElementById('ah_party_name').required = false;
    schedOpen('adHocModal');
}

/* ── Company change → reload party types + project categories ── */
document.getElementById('ah_company_id').addEventListener('change', function () {
    const companyId           = this.value;
    const partyTypeSelect     = document.getElementById('ah_party_type_id');
    const projectCatSelect    = document.getElementById('ah_project_category_id');
    const selectWrap          = document.getElementById('ah_party_select_wrap');
    const nameWrap            = document.getElementById('ah_party_name_wrap');

    // Reset dependent fields
    partyTypeSelect.innerHTML  = '<option value="">— Loading… —</option>';
    partyTypeSelect.required   = false;
    projectCatSelect.innerHTML = '<option value="">— Loading… —</option>';
    selectWrap.style.display   = 'none';
    nameWrap.style.display     = 'none';
    document.getElementById('ah_party_id').required   = false;
    document.getElementById('ah_party_name').required = false;

    if (!companyId) {
        partyTypeSelect.innerHTML  = '<option value="">— Select Company first —</option>';
        projectCatSelect.innerHTML = '<option value="">— Select Company first —</option>';
        return;
    }

    // Load party types
    fetch(`{{ route('role.payment-schedules.party-types-by-company', ['role' => $role]) }}?company_id=${companyId}`)
        .then(r => r.json())
        .then(data => {
            partyTypeSelect.innerHTML = '<option value="">— Select —</option>';
            data.forEach(pt => {
                partyTypeSelect.innerHTML +=
                    `<option value="${pt.id}" data-has-model="${pt.has_model}">${pt.name}</option>`;
            });
            partyTypeSelect.required = true;
        })
        .catch(() => {
            partyTypeSelect.innerHTML = '<option value="">— Failed to load —</option>';
        });

    // Load project categories
    fetch(`{{ route('role.payment-schedules.project-categories-by-company', ['role' => $role]) }}?company_id=${companyId}`)
        .then(r => r.json())
        .then(data => {
            projectCatSelect.innerHTML = '<option value="">— None —</option>';
            data.forEach(cat => {
                projectCatSelect.innerHTML += `<option value="${cat.id}">${cat.name}</option>`;
            });
        })
        .catch(() => {
            projectCatSelect.innerHTML = '<option value="">— Failed to load —</option>';
        });
});

/* ── Party type change → show party list or text ── */
document.getElementById('ah_party_type_id').addEventListener('change', function () {
    const opt      = this.options[this.selectedIndex];
    const hasModel = opt && opt.dataset.hasModel === '1';
    const typeId   = this.value;
    const companyId = document.getElementById('ah_company_id').value;

    const selectWrap  = document.getElementById('ah_party_select_wrap');
    const nameWrap    = document.getElementById('ah_party_name_wrap');
    const partySelect = document.getElementById('ah_party_id');
    const partyInput  = document.getElementById('ah_party_name');

    selectWrap.style.display = 'none';
    nameWrap.style.display   = 'none';
    partySelect.required     = false;
    partyInput.required      = false;
    partySelect.innerHTML    = '<option value="">— Loading… —</option>';
    partyInput.value         = '';

    if (!typeId) return;

    if (hasModel) {
        selectWrap.style.display = 'block';
        partySelect.required     = true;
        fetch(`{{ route('role.payment-schedules.parties-by-type', ['role' => $role]) }}?type_id=${typeId}&company_id=${companyId}`)
            .then(r => r.json())
            .then(data => {
                partySelect.innerHTML = '<option value="">— Select Party —</option>';
                data.forEach(p => {
                    partySelect.innerHTML += `<option value="${p.id}">${p.name}</option>`;
                });
            })
            .catch(() => {
                partySelect.innerHTML = '<option value="">— Failed to load —</option>';
            });
    } else {
        nameWrap.style.display = 'block';
        partyInput.required    = true;
        partyInput.focus();
    }
});

/* ── PRIORITY dropdown ──────────────────────────── */
const _priorityIcons = { high:'exclamation-circle', medium:'minus-circle', low:'arrow-circle-down' };
const _priorityColors = { high:['#991b1b','#fee2e2'], medium:['#92400e','#fef3c7'], low:['#3730a3','#e0e7ff'] };

function togglePriorityMenu(id) {
    const menu = document.getElementById('pri-menu-' + id);
    const isOpen = menu.style.display !== 'none';
    document.querySelectorAll('.priority-menu').forEach(m => m.style.display = 'none');
    menu.style.display = isOpen ? 'none' : 'block';
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.priority-cell')) {
        document.querySelectorAll('.priority-menu').forEach(m => m.style.display = 'none');
    }
});

function setPriority(id, priority) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
                   || '{{ csrf_token() }}';

    fetch('/' + _role + '/payment-schedules/' + id + '/priority', {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ priority: priority }),
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        const cell = document.getElementById('pri-cell-' + id);
        const [color, bg] = _priorityColors[priority];
        const icon = _priorityIcons[priority];
        const btn = cell.querySelector('button.p-badge');
        btn.className = 'p-badge p-' + priority;
        btn.innerHTML = `<i class="fas fa-${icon}"></i> ${priority.charAt(0).toUpperCase()+priority.slice(1)} <i class="fas fa-caret-down" style="font-size:9px;opacity:.6;"></i>`;
        document.getElementById('pri-menu-' + id).style.display = 'none';
    })
    .catch(() => {});
}

/* ── CANCEL confirmation ────────────────────────── */
function confirmCancel(id, party, amount) {
    Swal.fire({
        title: 'Cancel Schedule?',
        html: `Cancel <strong>${party}</strong> — ৳ ${amount}?<br>
               <small style="color:#6b7280;">Status will be set to <em>cancelled</em>.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-ban"></i> Yes, Cancel',
        cancelButtonText: 'Keep',
    }).then(result => {
        if (result.isConfirmed) document.getElementById('cancelForm-' + id).submit();
    });
}

/* ── DELETE confirmation ────────────────────────── */
function confirmDelete(id, party) {
    Swal.fire({
        title: 'Delete Schedule?',
        html: `Permanently delete the schedule for <strong>${party}</strong>?<br>
               <small style="color:#ef4444;">This cannot be undone.</small>`,
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-trash-alt"></i> Delete',
        cancelButtonText: 'Cancel',
    }).then(result => {
        if (result.isConfirmed) document.getElementById('deleteForm-' + id).submit();
    });
}
</script>
@endsection
