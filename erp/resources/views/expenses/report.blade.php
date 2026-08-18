@extends('layout.app')
@section('meta-information')
    <title>Expense Report</title>
@endsection
@section('css')
{{--
    Expense report.

    Every selector is prefixed .rp- and the design tokens live on .rp-shell
    rather than :root, so nothing here can reach a page that does not include
    this view. Teal to match expenses/index.blade.php — the expense desk reads
    as one section or it reads as three different products.
--}}
<style>
    .rp-shell {
        --rp-teal: #0d9488;
        --rp-teal-dark: #0f766e;
        --rp-ink: #0f172a;
        --rp-text: #1f2937;
        --rp-muted: #64748b;
        --rp-line: #e2e8f0;
        --rp-soft: #f8fafc;
        --rp-up: #dc2626;
        --rp-down: #059669;

        background: #f5f7fb;
        padding: 16px;
        border-radius: 14px;
    }

    .rp-card {
        background: #fff;
        border: 1px solid var(--rp-line);
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
    }

    .rp-head {
        background: linear-gradient(135deg, var(--rp-teal) 0%, var(--rp-teal-dark) 100%);
        color: #fff;
        padding: 16px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .rp-head h1 { margin: 0; font-size: 18px; font-weight: 800; letter-spacing: -0.01em; }
    .rp-head .rp-sub { color: rgba(255,255,255,0.82); font-size: 12px; margin-top: 3px; }

    .rp-head-actions { display: flex; gap: 8px; flex-wrap: wrap; }

    .rp-btn {
        display: inline-flex; align-items: center; gap: 7px;
        border: 1px solid rgba(255,255,255,0.22);
        background: rgba(255,255,255,0.14); color: #fff;
        border-radius: 9px; padding: 8px 13px;
        font-size: 12.5px; font-weight: 700; cursor: pointer; text-decoration: none;
        transition: background .15s ease;
    }
    .rp-btn:hover { background: rgba(255,255,255,0.26); color: #fff; text-decoration: none; }
    .rp-btn.rp-solid { background: #fff; color: var(--rp-teal-dark); border-color: #fff; }
    .rp-btn.rp-solid:hover { background: #f0fdfa; color: var(--rp-teal-dark); }

    /* ---- Period: a mode, so it stays a segmented control, not a dropdown ---- */
    .rp-controls { padding: 14px 16px 0; }

    .rp-periodbar {
        display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;
        padding-bottom: 12px; border-bottom: 1px dashed var(--rp-line);
    }

    .rp-seg { display: inline-flex; background: #eef2f7; border-radius: 10px; padding: 3px; gap: 2px; }
    .rp-seg a {
        padding: 7px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 700;
        color: var(--rp-muted); text-decoration: none; white-space: nowrap; transition: all .15s ease;
    }
    .rp-seg a:hover { color: var(--rp-ink); text-decoration: none; }
    .rp-seg a.is-on { background: #fff; color: var(--rp-teal-dark); box-shadow: 0 1px 3px rgba(15,23,42,.12); }

    .rp-field { display: flex; flex-direction: column; gap: 5px; }
    .rp-field > label {
        font-size: 10.5px; font-weight: 800; color: var(--rp-muted);
        letter-spacing: .07em; text-transform: uppercase; margin: 0;
    }
    .rp-field input, .rp-field select {
        min-width: 165px; height: 38px;
        border: 1px solid var(--rp-line); border-radius: 9px;
        padding: 0 11px; font-size: 13px; color: var(--rp-text); background: #fff;
    }
    .rp-field input:focus, .rp-field select:focus { outline: 2px solid rgba(13,148,136,.35); outline-offset: -1px; }

    .rp-filters {
        display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;
        padding: 12px 0 14px;
    }

    .rp-filter-go { display: flex; gap: 8px; margin-left: auto; }
    .rp-go, .rp-reset {
        height: 38px; padding: 0 18px; border-radius: 9px; border: none;
        font-size: 13px; font-weight: 800; cursor: pointer; text-decoration: none;
        display: inline-flex; align-items: center;
    }
    .rp-go { background: var(--rp-teal); color: #fff; }
    .rp-go:hover { background: var(--rp-teal-dark); color: #fff; }
    .rp-reset { background: #f1f5f9; color: var(--rp-text); border: 1px solid var(--rp-line); }
    .rp-reset:hover { background: #e2e8f0; color: var(--rp-text); text-decoration: none; }

    /* ---- Applied filters, each removable ---- */
    .rp-chips { display: flex; gap: 7px; flex-wrap: wrap; padding: 0 16px 14px; align-items: center; }
    .rp-chips .rp-chips-label { font-size: 11px; font-weight: 800; color: var(--rp-muted); text-transform: uppercase; letter-spacing: .06em; }
    .rp-chip {
        display: inline-flex; align-items: center; gap: 7px;
        background: #ccfbf1; color: #115e59; border: 1px solid #99f6e4;
        border-radius: 999px; padding: 4px 6px 4px 11px; font-size: 12px; font-weight: 700;
    }
    .rp-chip span.rp-chip-k { opacity: .7; font-weight: 700; }
    .rp-chip a {
        display: inline-flex; align-items: center; justify-content: center;
        width: 17px; height: 17px; border-radius: 50%;
        background: rgba(17,94,89,.14); color: #115e59; font-size: 10px; text-decoration: none;
    }
    .rp-chip a:hover { background: #115e59; color: #fff; text-decoration: none; }
    .rp-chip-clear { font-size: 12px; font-weight: 700; color: var(--rp-muted); text-decoration: underline; }

    /* ---- KPI tiles ---- */
    .rp-kpis {
        display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 12px;
        padding: 0 16px 12px;
    }
    .rp-kpi {
        background: #fff; border: 1px solid var(--rp-line); border-radius: 12px;
        padding: 13px 15px; border-top: 3px solid var(--rp-teal);
    }
    .rp-kpi.k-txn { border-top-color: #6366f1; }
    .rp-kpi.k-petty { border-top-color: #f59e0b; }
    .rp-kpi.k-bank { border-top-color: #0ea5e9; }

    .rp-kpi .rp-k-label {
        font-size: 10.5px; font-weight: 800; color: var(--rp-muted);
        text-transform: uppercase; letter-spacing: .07em; margin-bottom: 7px;
    }
    .rp-kpi .rp-k-value { font-size: 23px; font-weight: 900; color: var(--rp-ink); line-height: 1.05; }
    .rp-kpi .rp-k-note { font-size: 11.5px; color: var(--rp-muted); margin-top: 5px; }

    .rp-delta { display: inline-flex; align-items: center; gap: 4px; font-weight: 800; }
    .rp-delta.up { color: var(--rp-up); }
    .rp-delta.down { color: var(--rp-down); }
    .rp-delta.flat { color: var(--rp-muted); }

    /* ---- Money-source split: shows Cash in Hand, which has no tile of its own ---- */
    .rp-split { padding: 0 16px 14px; }
    .rp-split-bar { display: flex; height: 10px; border-radius: 999px; overflow: hidden; background: #eef2f7; }
    .rp-split-bar i { display: block; height: 100%; }
    .rp-split-legend { display: flex; gap: 16px; flex-wrap: wrap; margin-top: 8px; }
    .rp-split-legend div { font-size: 11.5px; color: var(--rp-muted); font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
    .rp-split-legend em { width: 9px; height: 9px; border-radius: 3px; display: inline-block; }
    .rp-split-legend b { color: var(--rp-ink); font-weight: 800; }

    /* ---- Panels ---- */
    .rp-panel { background: #fff; border: 1px solid var(--rp-line); border-radius: 12px; overflow: hidden; margin: 0 16px 14px; }
    .rp-panel-head {
        background: var(--rp-soft); border-bottom: 1px solid var(--rp-line);
        padding: 11px 15px; display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap;
    }
    .rp-panel-head h3 { margin: 0; font-size: 13.5px; font-weight: 900; color: #334155; }
    .rp-panel-head .rp-meta { font-size: 11.5px; color: var(--rp-muted); }
    .rp-panel-body { padding: 15px; }

    /* ---- Group-by pills ---- */
    .rp-groupbar { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
    .rp-groupbar .rp-gb-label { font-size: 10.5px; font-weight: 800; color: var(--rp-muted); text-transform: uppercase; letter-spacing: .07em; margin-right: 3px; }
    .rp-gb {
        padding: 6px 12px; border-radius: 999px; border: 1px solid var(--rp-line);
        background: #fff; color: var(--rp-muted); font-size: 12px; font-weight: 700; text-decoration: none;
        transition: all .15s ease;
    }
    .rp-gb:hover { border-color: #5eead4; color: var(--rp-teal-dark); background: #f0fdfa; text-decoration: none; }
    .rp-gb.is-on { background: var(--rp-teal); border-color: var(--rp-teal); color: #fff; }

    /* ---- Bars (no canvas: prints correctly and can never render blank) ---- */
    .rp-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 9px; }
    .rp-bar-name { width: 190px; flex-shrink: 0; font-size: 12.5px; font-weight: 700; color: #334155;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .rp-bar-track { flex: 1; height: 9px; background: #eef2f7; border-radius: 999px; overflow: hidden; min-width: 60px; }
    .rp-bar-fill { height: 100%; border-radius: inherit; background: linear-gradient(90deg, #14b8a6 0%, #0f766e 100%); }
    .rp-bar-num { width: 108px; text-align: right; font-size: 12px; font-weight: 800; color: var(--rp-text); font-variant-numeric: tabular-nums; }

    /* ---- Tables ---- */
    .rp-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .rp-table thead th {
        background: var(--rp-soft); color: var(--rp-muted);
        font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em; font-weight: 800;
        padding: 11px 14px; border-bottom: 1px solid var(--rp-line); white-space: nowrap; text-align: left;
    }
    .rp-table thead th.rp-r, .rp-table tbody td.rp-r, .rp-table tfoot td.rp-r { text-align: right; }
    .rp-table tbody td { padding: 11px 14px; border-bottom: 1px solid #eef2f7; color: var(--rp-text); vertical-align: middle; }
    .rp-table tbody tr:hover { background: #f0fdfa; }
    .rp-table tfoot td {
        padding: 12px 14px; background: var(--rp-soft); font-weight: 900; color: var(--rp-ink);
        border-top: 2px solid var(--rp-line);
    }
    .rp-money { font-weight: 800; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .rp-drill { color: var(--rp-teal-dark); font-weight: 700; text-decoration: none; }
    .rp-drill:hover { text-decoration: underline; color: var(--rp-teal-dark); }

    .rp-tag { display: inline-flex; align-items: center; border-radius: 999px; padding: 3px 9px; font-size: 10.5px; font-weight: 800; white-space: nowrap; }
    .rp-tag.t-on { background: #dcfce7; color: #166534; }
    .rp-tag.t-off { background: #fef3c7; color: #92400e; }
    .rp-tag.t-bank { background: #e0f2fe; color: #075985; }
    .rp-tag.t-petty { background: #fef3c7; color: #92400e; }
    .rp-tag.t-cash { background: #f1f5f9; color: #475569; }

    .rp-empty { padding: 40px 18px; text-align: center; color: var(--rp-muted); }
    .rp-empty i { font-size: 38px; opacity: .35; display: block; margin-bottom: 10px; }
    .rp-empty h4 { margin: 6px 0 4px; color: var(--rp-text); font-size: 15px; font-weight: 800; }

    .rp-cap { padding: 10px 15px; background: #fffbeb; border-top: 1px solid #fde68a; color: #92400e; font-size: 12px; font-weight: 700; }

    @media (max-width: 1100px) { .rp-kpis { grid-template-columns: repeat(2, minmax(0,1fr)); } }
    @media (max-width: 720px) {
        .rp-kpis { grid-template-columns: 1fr; }
        .rp-field input, .rp-field select { min-width: 100%; width: 100%; }
        .rp-field { width: 100%; }
        .rp-filter-go { margin-left: 0; width: 100%; }
        .rp-go, .rp-reset { flex: 1; justify-content: center; }
        .rp-bar-name { width: 110px; }
        .rp-bar-num { width: 84px; }
        .rp-seg { width: 100%; overflow-x: auto; }
    }

    /* The app's dark shim recolours by class and these set their own hex. */
    html.dark .rp-shell { background: #0f172a; }
    html.dark .rp-card, html.dark .rp-panel, html.dark .rp-kpi { background: #1e293b; border-color: #334155; }
    html.dark .rp-panel-head, html.dark .rp-table thead th, html.dark .rp-table tfoot td { background: #172033; border-color: #334155; color: #94a3b8; }
    html.dark .rp-kpi .rp-k-value, html.dark .rp-table tfoot td { color: #e2e8f0; }
    html.dark .rp-table tbody td { color: #cbd5e1; border-color: #334155; }
    html.dark .rp-table tbody tr:hover { background: rgba(20,184,166,.10); }
    html.dark .rp-field input, html.dark .rp-field select { background: #0f172a; border-color: #334155; color: #e2e8f0; }
    html.dark .rp-seg { background: #0f172a; }
    html.dark .rp-seg a.is-on { background: #1e293b; color: #5eead4; }
    html.dark .rp-gb { background: #1e293b; border-color: #334155; color: #94a3b8; }
    html.dark .rp-bar-track, html.dark .rp-split-bar { background: #334155; }
    html.dark .rp-bar-name { color: #cbd5e1; }

    @media print {
        .rp-noprint { display: none !important; }
        .rp-shell { padding: 0; background: #fff; }
        .rp-card { box-shadow: none; border: none; }
        .rp-panel { break-inside: avoid; }
    }
</style>
@endsection
@section('main-content')

    @include('layout.expense-tabs')

@php
    $role = Str::slug(Auth::user()->getRoleNames()->first());
    $reportUrl = route('role.expenses.report', ['role' => $role]);
    $exportUrl = route('role.expenses.report.export', ['role' => $role]);
    $printUrl = route('role.expenses.report.print', ['role' => $role]);

    // Everything currently in the URL, minus routing noise. Every link below is
    // built from this so a click changes one thing and keeps the rest.
    // Scalars only: an array value (?x[]=1) would fatal when echoed into a
    // hidden input, and nothing on this report takes one.
    $carry = collect(request()->except(['role', 'page']))
        ->filter(fn ($v) => is_scalar($v) && $v !== '')
        ->all();

    $link = function (array $overrides) use ($reportUrl, $carry) {
        $q = collect(array_merge($carry, $overrides))->filter(fn ($v) => $v !== null && $v !== '')->all();
        return $reportUrl . (count($q) ? '?' . http_build_query($q) : '');
    };

    $dropFilter = function (string $key) use ($reportUrl, $carry) {
        $q = collect($carry)->except([$key])->all();
        return $reportUrl . (count($q) ? '?' . http_build_query($q) : '');
    };

    // Period links reset the date inputs of the mode being left behind, so
    // switching Monthly -> Daily does not carry a stale month/year along.
    $periodCarry = collect($carry)->except(['period', 'date', 'month', 'year', 'from', 'to'])->all();
    $periodLink = fn (array $p) => $reportUrl . '?' . http_build_query(array_merge($periodCarry, $p));

    $anchorDate  = request('date', now()->toDateString());
    $rangeFrom   = $customFrom ?? now()->subDays(29)->toDateString();
    $rangeTo     = $customTo ?? now()->toDateString();

    // Switching period keeps you where you were rather than jumping to today —
    // Monthly March -> Daily lands on 1 March, not on whatever date it is now.
    $switchDate = $from->toDateString();

    // Reset / Clear all drop every filter but keep the window and the grouping,
    // so the user lands on the same report unfiltered rather than on a fresh one.
    $periodParams = match ($period) {
        'monthly' => ['period' => 'monthly', 'month' => $selectedMonth, 'year' => $selectedYear],
        'custom'  => ['period' => 'custom', 'from' => $rangeFrom, 'to' => $rangeTo],
        default   => ['period' => $period, 'date' => $anchorDate],
    };
    $clearLink = $reportUrl . '?' . http_build_query(array_merge($periodParams, ['group_by' => $groupKey]));

    $total       = (float) $summary['total_amount'];
    $maxGroup    = (float) ($groupRows->max('amount') ?: 0);
    $maxDay      = (float) ($timeline->max('amount') ?: 0);

    $pct = fn ($amount) => $total > 0 ? round($amount / $total * 100, 1) : 0;

    $sourceTag = function ($expense) {
        if ($expense->petty_cash_float_id) return ['t-petty', 'Petty Cash'];
        if ($expense->bank_id)             return ['t-bank', $expense->bank?->name ?: 'Bank'];
        return ['t-cash', 'Cash in Hand'];
    };
@endphp

<div class="rp-shell">
    <div class="rp-card">

        {{-- ── Header ─────────────────────────────────────────────── --}}
        <div class="rp-head">
            <div>
                <h1><i class="fas fa-chart-line mr-2"></i>Expense Report</h1>
                <div class="rp-sub">
                    {{ $periodLabel }} &nbsp;·&nbsp; grouped by {{ strtolower($groupMeta['label']) }}
                    &nbsp;·&nbsp; {{ number_format($summary['total_expenses']) }} transactions
                </div>
            </div>
            <div class="rp-head-actions rp-noprint">
                <a href="{{ route('role.expenses.index', ['role' => $role]) }}" class="rp-btn"><i class="fas fa-arrow-left"></i> Expenses</a>
                {{-- data-no-prefetch as well as the /export/ path: belt and braces,
                     a hover must never make the server build a file. --}}
                <a href="{{ $exportUrl }}{{ count($carry) ? '?' . http_build_query($carry) : '' }}"
                   data-no-prefetch class="rp-btn"><i class="fas fa-file-csv"></i> Export CSV</a>
                {{-- A separate layout-free page rather than window.print() on this
                     one: printing here would print the sidebar, header and tabs
                     too, and the print rules that would hide them belong to the
                     shared layout, which every company loads. data-no-prefetch
                     because "print" is not one of the paths the layout's
                     speculation rules already exclude. --}}
                <a href="{{ $printUrl }}{{ count($carry) ? '?' . http_build_query($carry) : '' }}"
                   target="_blank" rel="noopener" data-no-prefetch class="rp-btn rp-solid"><i class="fas fa-print"></i> Print / PDF</a>
            </div>
        </div>

        {{-- ── Period (a mode) + filters (a narrowing) ─────────────── --}}
        <div class="rp-controls rp-noprint">
            <div class="rp-periodbar">
                <div class="rp-seg">
                    <a href="{{ $periodLink(['period' => 'daily',   'date' => $switchDate]) }}"  class="{{ $period === 'daily' ? 'is-on' : '' }}">Daily</a>
                    <a href="{{ $periodLink(['period' => 'weekly',  'date' => $switchDate]) }}"  class="{{ $period === 'weekly' ? 'is-on' : '' }}">Weekly</a>
                    <a href="{{ $periodLink(['period' => 'monthly', 'month' => $selectedMonth, 'year' => $selectedYear]) }}" class="{{ $period === 'monthly' ? 'is-on' : '' }}">Monthly</a>
                    <a href="{{ $periodLink(['period' => 'custom',  'from' => $rangeFrom, 'to' => $rangeTo]) }}" class="{{ $period === 'custom' ? 'is-on' : '' }}">Custom</a>
                </div>

                <form method="get" action="{{ $reportUrl }}" class="rp-periodbar" style="border:0;padding:0;gap:12px;flex:1;">
                    {{-- Carry the non-period state so changing the date keeps the
                         grouping and filters the user already chose. --}}
                    <input type="hidden" name="period" value="{{ $period }}">
                    @foreach($periodCarry as $k => $v)
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach

                    @if($period === 'daily' || $period === 'weekly')
                        <div class="rp-field">
                            <label>{{ $period === 'daily' ? 'Date' : 'Any day in the week' }}</label>
                            <input type="date" name="date" value="{{ $anchorDate }}">
                        </div>
                    @elseif($period === 'monthly')
                        <div class="rp-field">
                            <label>Month</label>
                            <select name="month">
                                @foreach(range(1, 12) as $m)
                                    <option value="{{ $m }}" {{ $m === (int) $selectedMonth ? 'selected' : '' }}>{{ \Carbon\Carbon::create()->month($m)->format('F') }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="rp-field">
                            <label>Year</label>
                            <input type="number" name="year" min="2000" max="2100" value="{{ $selectedYear }}">
                        </div>
                    @else
                        <div class="rp-field">
                            <label>From</label>
                            <input type="date" name="from" value="{{ $rangeFrom }}">
                        </div>
                        <div class="rp-field">
                            <label>To</label>
                            <input type="date" name="to" value="{{ $rangeTo }}">
                        </div>
                    @endif

                    <div class="rp-filter-go">
                        <button type="submit" class="rp-go"><i class="fas fa-check mr-2"></i>Apply</button>
                    </div>
                </form>
            </div>

            <form method="get" action="{{ $reportUrl }}" class="rp-filters">
                {{-- Period state rides along so narrowing a filter never throws
                     the user back to today. --}}
                <input type="hidden" name="period" value="{{ $period }}">
                <input type="hidden" name="group_by" value="{{ $groupKey }}">
                @if($period === 'daily' || $period === 'weekly')
                    <input type="hidden" name="date" value="{{ $anchorDate }}">
                @elseif($period === 'monthly')
                    <input type="hidden" name="month" value="{{ $selectedMonth }}">
                    <input type="hidden" name="year" value="{{ $selectedYear }}">
                @else
                    <input type="hidden" name="from" value="{{ $rangeFrom }}">
                    <input type="hidden" name="to" value="{{ $rangeTo }}">
                @endif

                @if($canViewAll)
                    <div class="rp-field">
                        <label>Company</label>
                        <select name="company_id">
                            <option value="">All Companies</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" {{ (string) request('company_id') === (string) $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    {{-- Pinned by permission, not by choice. Shown so the figures
                         are not silently scoped, disabled so it cannot be widened. --}}
                    <div class="rp-field">
                        <label>Company</label>
                        <select disabled title="You can only report on your own company">
                            <option>{{ $companies->first()?->name ?? 'Your company' }}</option>
                        </select>
                    </div>
                @endif

                <div class="rp-field">
                    <label>Department / Project</label>
                    <select name="expense_department_id">
                        <option value="">All Departments</option>
                        @foreach($expenseDepartments as $department)
                            <option value="{{ $department->id }}" {{ (string) request('expense_department_id') === (string) $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="rp-field">
                    <label>Category</label>
                    {{-- Submitting on change is what makes the cascade work: the
                         sub-category list below is rebuilt server-side for the
                         chosen category. --}}
                    <select name="expense_category_id" onchange="this.form.querySelector('[name=expense_sub_category_id]').value='';this.form.submit();">
                        <option value="">All Categories</option>
                        @foreach($expenseCategories as $category)
                            <option value="{{ $category->id }}" {{ (string) request('expense_category_id') === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="rp-field">
                    <label>Sub-Category</label>
                    <select name="expense_sub_category_id">
                        <option value="">{{ request('expense_category_id') ? 'All in this category' : 'All Sub-Categories' }}</option>
                        @foreach($expenseSubCategories as $subCategory)
                            <option value="{{ $subCategory->id }}" {{ (string) request('expense_sub_category_id') === (string) $subCategory->id ? 'selected' : '' }}>{{ $subCategory->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="rp-field">
                    <label>Money Source</label>
                    <select name="payment_source">
                        <option value="">Any Source</option>
                        <option value="bank"  {{ request('payment_source') === 'bank' ? 'selected' : '' }}>Bank</option>
                        <option value="petty" {{ request('payment_source') === 'petty' ? 'selected' : '' }}>Petty Cash</option>
                        <option value="cash"  {{ request('payment_source') === 'cash' ? 'selected' : '' }}>Cash in Hand</option>
                    </select>
                </div>

                <div class="rp-field">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Statuses</option>
                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>

                <div class="rp-filter-go">
                    <a href="{{ $clearLink }}" class="rp-reset">Reset</a>
                    <button type="submit" class="rp-go"><i class="fas fa-filter mr-2"></i>Apply Filters</button>
                </div>
            </form>
        </div>

        @if(count($activeFilters))
            <div class="rp-chips">
                <span class="rp-chips-label">Filtered by</span>
                @foreach($activeFilters as $chip)
                    <span class="rp-chip">
                        <span class="rp-chip-k">{{ $chip['label'] }}:</span> {{ $chip['value'] }}
                        <a href="{{ $dropFilter($chip['key']) }}" class="rp-noprint" title="Remove this filter"><i class="fas fa-times"></i></a>
                    </span>
                @endforeach
                <a href="{{ $clearLink }}" class="rp-chip-clear rp-noprint">Clear all</a>
            </div>
        @endif

        {{-- ── Headline figures ────────────────────────────────────── --}}
        @php
            $delta = $summary['change_pct'];
            $deltaClass = is_null($delta) ? 'flat' : ($delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'));
            $deltaIcon = is_null($delta) ? 'fa-minus' : ($delta > 0 ? 'fa-arrow-up' : ($delta < 0 ? 'fa-arrow-down' : 'fa-minus'));
        @endphp
        <div class="rp-kpis">
            <div class="rp-kpi">
                <div class="rp-k-label">Total Expense</div>
                <div class="rp-k-value">৳ {{ number_format($summary['total_amount'], 2) }}</div>
                <div class="rp-k-note">
                    @if(is_null($delta))
                        No spend in the previous period
                    @else
                        <span class="rp-delta {{ $deltaClass }}"><i class="fas {{ $deltaIcon }}"></i> {{ number_format(abs($delta), 1) }}%</span>
                        vs ৳ {{ number_format($summary['previous_amount'], 0) }} ({{ $prevFrom->format('d M') }}–{{ $prevTo->format('d M') }})
                    @endif
                </div>
            </div>
            <div class="rp-kpi k-txn">
                <div class="rp-k-label">Transactions</div>
                <div class="rp-k-value">{{ number_format($summary['total_expenses']) }}</div>
                <div class="rp-k-note">Average ৳ {{ number_format($summary['average_amount'], 2) }} · largest ৳ {{ number_format($summary['largest_amount'], 0) }}</div>
            </div>
            <div class="rp-kpi k-petty">
                <div class="rp-k-label">Via Petty Cash</div>
                <div class="rp-k-value">৳ {{ number_format($summary['petty_amount'], 2) }}</div>
                <div class="rp-k-note">{{ $pct($summary['petty_amount']) }}% of total · paid from a custodian's float</div>
            </div>
            <div class="rp-kpi k-bank">
                <div class="rp-k-label">Via Bank</div>
                <div class="rp-k-value">৳ {{ number_format($summary['bank_amount'], 2) }}</div>
                <div class="rp-k-note">{{ $pct($summary['bank_amount']) }}% of total · left a bank account</div>
            </div>
        </div>

        @if($total > 0)
            <div class="rp-split">
                <div class="rp-split-bar">
                    <i style="width: {{ $pct($summary['bank_amount']) }}%; background:#0ea5e9;"></i>
                    <i style="width: {{ $pct($summary['petty_amount']) }}%; background:#f59e0b;"></i>
                    <i style="width: {{ $pct($summary['cash_amount']) }}%; background:#94a3b8;"></i>
                </div>
                <div class="rp-split-legend">
                    <div><em style="background:#0ea5e9"></em> Bank <b>৳ {{ number_format($summary['bank_amount'], 0) }}</b></div>
                    <div><em style="background:#f59e0b"></em> Petty Cash <b>৳ {{ number_format($summary['petty_amount'], 0) }}</b></div>
                    <div><em style="background:#94a3b8"></em> Cash in Hand <b>৳ {{ number_format($summary['cash_amount'], 0) }}</b></div>
                </div>
            </div>
        @endif

        {{-- ── The breakdown: one report, five views ────────────────── --}}
        <div class="rp-panel">
            <div class="rp-panel-head">
                <h3>Breakdown by {{ $groupMeta['label'] }}</h3>
                <div class="rp-groupbar rp-noprint">
                    <span class="rp-gb-label">Group by</span>
                    <a href="{{ $link(['group_by' => 'company']) }}"     class="rp-gb {{ $groupKey === 'company' ? 'is-on' : '' }}">Company</a>
                    <a href="{{ $link(['group_by' => 'department']) }}"  class="rp-gb {{ $groupKey === 'department' ? 'is-on' : '' }}">Department</a>
                    <a href="{{ $link(['group_by' => 'category']) }}"    class="rp-gb {{ $groupKey === 'category' ? 'is-on' : '' }}">Category</a>
                    <a href="{{ $link(['group_by' => 'subcategory']) }}" class="rp-gb {{ $groupKey === 'subcategory' ? 'is-on' : '' }}">Sub-Category</a>
                    <a href="{{ $link(['group_by' => 'source']) }}"      class="rp-gb {{ $groupKey === 'source' ? 'is-on' : '' }}">Money Source</a>
                </div>
            </div>

            @if($groupRows->isNotEmpty())
                <div class="rp-panel-body" style="border-bottom:1px solid var(--rp-line);">
                    @foreach($groupRows->take(12) as $row)
                        <div class="rp-bar">
                            <div class="rp-bar-name" title="{{ $row->name }}">{{ $row->name }}</div>
                            <div class="rp-bar-track">
                                <div class="rp-bar-fill" style="width: {{ $maxGroup > 0 ? round($row->amount / $maxGroup * 100, 2) : 0 }}%"></div>
                            </div>
                            <div class="rp-bar-num">৳ {{ number_format($row->amount, 0) }}</div>
                        </div>
                    @endforeach
                    @if($groupRows->count() > 12)
                        <div style="font-size:11.5px;color:var(--rp-muted);margin-top:4px;">
                            Chart shows the 12 largest of {{ $groupRows->count() }}. The table below lists all of them.
                        </div>
                    @endif
                </div>

                <div style="overflow-x:auto;">
                    <table class="rp-table">
                        <thead>
                            <tr>
                                <th>{{ $groupMeta['label'] }}</th>
                                <th class="rp-r">Transactions</th>
                                <th class="rp-r">Amount</th>
                                <th class="rp-r">% of Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($groupRows as $row)
                                <tr>
                                    <td>
                                        @if(!is_null($row->id))
                                            {{-- Drill-down: adds this row as a filter and keeps everything else. --}}
                                            <a href="{{ $link([$groupMeta['drill'] => $row->id]) }}" class="rp-drill" title="Filter the report to {{ $row->name }}">{{ $row->name }}</a>
                                        @else
                                            {{ $row->name }}
                                        @endif
                                    </td>
                                    <td class="rp-r">{{ number_format($row->count) }}</td>
                                    <td class="rp-r rp-money">৳ {{ number_format($row->amount, 2) }}</td>
                                    <td class="rp-r">{{ $pct($row->amount) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td>Total</td>
                                <td class="rp-r">{{ number_format($groupRows->sum('count')) }}</td>
                                <td class="rp-r rp-money">৳ {{ number_format($groupRows->sum('amount'), 2) }}</td>
                                <td class="rp-r">100%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="rp-empty">
                    <i class="fas fa-chart-pie"></i>
                    <h4>Nothing to break down</h4>
                    <p>No expenses matched {{ strtolower($periodLabel) }} with the filters above.</p>
                </div>
            @endif
        </div>

        {{-- ── Day-by-day, only when the window is more than one day ── --}}
        @if($timeline->isNotEmpty())
            <div class="rp-panel">
                <div class="rp-panel-head">
                    <h3>Daily Trend</h3>
                    <div class="rp-meta">{{ $from->format('d M Y') }} — {{ $to->format('d M Y') }}</div>
                </div>
                <div class="rp-panel-body">
                    @foreach($timeline as $day)
                        <div class="rp-bar">
                            <div class="rp-bar-name" style="width:78px;">{{ $day['label'] }}</div>
                            <div class="rp-bar-track">
                                <div class="rp-bar-fill" style="width: {{ $maxDay > 0 ? round($day['amount'] / $maxDay * 100, 2) : 0 }}%"></div>
                            </div>
                            <div class="rp-bar-num">
                                ৳ {{ number_format($day['amount'], 0) }}
                                <span style="color:var(--rp-muted);font-weight:600;">({{ $day['count'] }})</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ── The ledger ──────────────────────────────────────────── --}}
        <div class="rp-panel">
            <div class="rp-panel-head">
                <h3>Expense Ledger</h3>
                <div class="rp-meta">
                    @if($ledgerTotal > $expenses->count())
                        Showing {{ number_format($expenses->count()) }} of {{ number_format($ledgerTotal) }} records
                    @else
                        {{ number_format($ledgerTotal) }} record{{ $ledgerTotal === 1 ? '' : 's' }}
                    @endif
                </div>
            </div>
            <div style="overflow-x:auto;">
                <table class="rp-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Title</th>
                            <th>Classification</th>
                            <th>Source</th>
                            <th class="rp-r">Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($expenses as $index => $expense)
                            @php [$tagClass, $tagText] = $sourceTag($expense); @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td style="white-space:nowrap;">{{ \Carbon\Carbon::parse($expense->expense_date)->format('d M Y') }}</td>
                                <td>
                                    <div style="font-weight:800;color:#0f172a;">{{ $expense->title }}</div>
                                    @if($expense->reference)
                                        <div style="font-size:11px;color:#94a3b8;">Ref: {{ $expense->reference }}</div>
                                    @endif
                                </td>
                                <td style="font-size:12px;color:#475569;">{{ $expense->classification_path ?: '—' }}</td>
                                <td><span class="rp-tag {{ $tagClass }}">{{ $tagText }}</span></td>
                                <td class="rp-r rp-money">৳ {{ number_format($expense->amount, 2) }}</td>
                                <td>
                                    <span class="rp-tag {{ $expense->status ? 't-on' : 't-off' }}">{{ $expense->status ? 'Active' : 'Inactive' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="rp-empty">
                                        <i class="fas fa-receipt"></i>
                                        <h4>No expense records</h4>
                                        <p>The selected period and filters returned no rows.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($ledgerTotal > $expenses->count())
                <div class="rp-cap">
                    <i class="fas fa-circle-info mr-1"></i>
                    Ledger capped at {{ number_format($expenses->count()) }} rows — the figures and breakdown above cover all
                    {{ number_format($ledgerTotal) }}. Narrow the period or filters to see the rest.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
