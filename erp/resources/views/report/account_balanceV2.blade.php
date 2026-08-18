@extends('layout.app')

@section('meta-information')
    <title>Account Balance</title>
@endsection

@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* ── Filter ── */
    .filter-container { margin:15px 15px 0 15px; border-radius:8px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,.1); }
    .filter-header { background:#f8f9fa; padding:16px; cursor:pointer; display:flex; justify-content:space-between; align-items:center; border-left:4px solid #3b82f6; }
    .filter-header h3 { margin:0; font-size:18px; font-weight:600; color:#1f2937; }
    .filter-header .toggle-icon { transition:transform .3s; }
    .filter-header.active .toggle-icon { transform:rotate(180deg); }
    .filter-content { background:#fff; padding:0; max-height:0; overflow:hidden; transition:max-height .3s ease-out,padding .3s ease-out; }
    .filter-content.active { padding:20px; max-height:400px; }
    .filter-row { display:flex; flex-wrap:wrap; gap:20px; margin-bottom:16px; }
    .filter-group { flex:1; min-width:200px; }
    .filter-group label { display:block; margin-bottom:6px; font-weight:500; color:#374151; font-size:13px; }
    .filter-group select,
    .filter-group input { width:100%; padding:10px; border:1px solid #d1d5db; border-radius:6px; font-size:14px; }
    .filter-group select:focus,
    .filter-group input:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.1); }
    .filter-actions { display:flex; justify-content:flex-end; gap:12px; margin-top:8px; }
    .apply-btn  { background:#3b82f6; color:#fff; border:none; padding:10px 20px; border-radius:6px; font-weight:500; cursor:pointer; }
    .apply-btn:hover  { background:#2563eb; }
    .reset-btn  { background:#f8f9fa; color:#6b7280; border:1px solid #d1d5db; padding:10px 20px; border-radius:6px; font-weight:500; cursor:pointer; }
    .reset-btn:hover  { background:#e5e7eb; }
    .select2-container .select2-selection--single { height:42px; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height:40px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height:42px; }

    /* ── Report ── */
    .report-wrapper { margin:16px; }
    .report-header-box { background:linear-gradient(135deg,#1e40af,#3b82f6); border-radius:10px; padding:24px 28px; color:#fff; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; }
    .report-header-box h2 { margin:0; font-size:22px; font-weight:700; }
    .report-header-box .meta { font-size:13px; opacity:.85; margin-top:4px; }
    .print-btn { background:rgba(255,255,255,.2); border:1px solid rgba(255,255,255,.4); color:#fff; padding:8px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
    .print-btn:hover { background:rgba(255,255,255,.3); }

    /* ── Type summary strip ── */
    .type-strip { display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
    .type-strip-card { flex:1; min-width:140px; border-radius:10px; padding:16px 18px; cursor:pointer; transition:transform .15s, box-shadow .15s; }
    .type-strip-card:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,.12); }
    .type-strip-card.asset     { background:#dbeafe; border-bottom:3px solid #2563eb; }
    .type-strip-card.liability { background:#fee2e2; border-bottom:3px solid #dc2626; }
    .type-strip-card.equity    { background:#d1fae5; border-bottom:3px solid #059669; }
    .type-strip-card.income    { background:#dcfce7; border-bottom:3px solid #15803d; }
    .type-strip-card.expense   { background:#fef3c7; border-bottom:3px solid #d97706; }
    .type-strip-card .ts-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; margin-bottom:6px; }
    .type-strip-card.asset     .ts-label { color:#1d4ed8; }
    .type-strip-card.liability .ts-label { color:#b91c1c; }
    .type-strip-card.equity    .ts-label { color:#065f46; }
    .type-strip-card.income    .ts-label { color:#15803d; }
    .type-strip-card.expense   .ts-label { color:#92400e; }
    .type-strip-card .ts-value { font-size:17px; font-weight:800; font-family:monospace; color:#1e293b; }
    .type-strip-card .ts-count { font-size:11px; color:#64748b; margin-top:3px; }

    /* ── Table card ── */
    .table-card { background:#fff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.08); overflow:hidden; margin-bottom:16px; }
    .table-card-header { display:flex; align-items:center; gap:10px; padding:13px 20px; border-bottom:2px solid #e2e8f0; }
    .table-card-header h4 { margin:0; font-size:15px; font-weight:700; }
    .header-asset     { background:#eff6ff; border-left:4px solid #2563eb; color:#1d4ed8; }
    .header-liability { background:#fff1f2; border-left:4px solid #dc2626; color:#b91c1c; }
    .header-equity    { background:#f0fdf4; border-left:4px solid #059669; color:#065f46; }
    .header-income    { background:#f0fdf4; border-left:4px solid #15803d; color:#15803d; }
    .header-expense   { background:#fefce8; border-left:4px solid #d97706; color:#92400e; }

    .ab-table { width:100%; border-collapse:collapse; }
    .ab-table thead th { background:#f8fafc; padding:10px 16px; font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.4px; border-bottom:1px solid #e2e8f0; }
    .ab-table tbody tr { border-bottom:1px solid #f1f5f9; transition:background .15s; }
    .ab-table tbody tr:hover { background:#f8fafc; }
    .ab-table tbody td { padding:10px 16px; font-size:13px; color:#334155; vertical-align:middle; }
    .ab-table tfoot td { padding:11px 16px; font-size:13px; font-weight:700; border-top:2px solid #e2e8f0; }
    .ab-table tfoot.asset-foot     td { background:#eff6ff; color:#1d4ed8; }
    .ab-table tfoot.liability-foot td { background:#fff1f2; color:#b91c1c; }
    .ab-table tfoot.equity-foot    td { background:#f0fdf4; color:#065f46; }
    .ab-table tfoot.income-foot    td { background:#f0fdf4; color:#15803d; }
    .ab-table tfoot.expense-foot   td { background:#fefce8; color:#92400e; }

    /* ── Balance visual bar ── */
    .bal-bar-wrap { width:80px; height:6px; background:#e2e8f0; border-radius:999px; display:inline-block; margin-left:8px; vertical-align:middle; }
    .bal-bar      { height:6px; border-radius:999px; }
    .bal-bar.pos  { background:#059669; }
    .bal-bar.neg  { background:#dc2626; }

    /* ── Grand total ── */
    .grand-total-box { background:#1e293b; border-radius:10px; overflow:hidden; margin-bottom:20px; }
    .grand-total-box table { width:100%; border-collapse:collapse; }
    .grand-total-box th { padding:12px 16px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#94a3b8; border-bottom:1px solid #334155; text-align:right; }
    .grand-total-box th:first-child { text-align:left; }
    .grand-total-box td { padding:14px 16px; font-size:15px; font-weight:800; font-family:monospace; text-align:right; }
    .grand-total-box td:first-child { text-align:left; color:#fff; font-size:14px; }
    .grand-total-box .gt-opening { color:#94a3b8; }
    .grand-total-box .gt-debit   { color:#60a5fa; }
    .grand-total-box .gt-credit  { color:#34d399; }
    .grand-total-box .gt-closing { color:#fbbf24; }

    /* ── Badges ── */
    .code-chip { font-family:monospace; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:4px; padding:2px 7px; font-size:12px; color:#475569; }
    .type-badge { display:inline-block; padding:2px 9px; border-radius:999px; font-size:11px; font-weight:700; text-transform:capitalize; }
    .type-asset     { background:#dbeafe; color:#1d4ed8; }
    .type-liability { background:#fee2e2; color:#b91c1c; }
    .type-equity    { background:#d1fae5; color:#065f46; }
    .type-income    { background:#dcfce7; color:#15803d; }
    .type-expense   { background:#fef3c7; color:#92400e; }

    .debit-val  { color:#1d4ed8; font-family:monospace; font-weight:600; }
    .credit-val { color:#059669; font-family:monospace; font-weight:600; }
    .bal-pos    { color:#059669; font-family:monospace; font-weight:700; }
    .bal-neg    { color:#dc2626; font-family:monospace; font-weight:700; }
    .zero-val   { color:#cbd5e1; font-family:monospace; }

    .empty-report { text-align:center; padding:60px 20px; color:#94a3b8; }
    .empty-report i { font-size:48px; margin-bottom:12px; }

    @media print {
        .filter-container, .print-btn, .states-table-header, .type-strip { display:none !important; }
        .table-card { box-shadow:none; border:1px solid #e2e8f0; page-break-inside:avoid; }
        body { background:#fff; }
    }
</style>
@endsection

@section('main-content')

@include('report.partials.account-report-tabs')

<div class="states-table bg-white rounded-lg shadow-md overflow-hidden">

    <div class="report-wrapper" id="printArea">

        {{-- Report Header --}}
        <div class="report-header-box">
            <div>
                <h2><i class="fas fa-coins mr-2"></i>Account Balance</h2>
                <div class="meta">
                    @if(request('date_from') || request('date_to'))
                        Period:
                        {{ request('date_from') ? \Carbon\Carbon::parse(request('date_from'))->format('d M Y') : 'Beginning' }}
                        —
                        {{ request('date_to') ? \Carbon\Carbon::parse(request('date_to'))->format('d M Y') : 'Today' }}
                    @else
                        All Periods (Cumulative)
                    @endif
                    &nbsp;·&nbsp; Generated: {{ now()->format('d M Y, h:i A') }}
                </div>
            </div>
            {{-- <button class="print-btn" onclick="window.print()">
                <i class="fas fa-print mr-1"></i>Print
            </button> --}}
        </div>

        {{-- Filter --}}
        <form action="" method="GET" id="filterForm">
            <div class="filter-container" style="margin: 0 0 15px 0 !important;">
                <div class="filter-header {{ request()->hasAny(['date_from','date_to','type','search']) ? 'active' : '' }}"
                    id="filterHeader">
                    <h3><i class="fas fa-filter mr-2"></i>Filter Options</h3>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
                <div class="filter-content {{ request()->hasAny(['date_from','date_to','type','search']) ? 'active' : '' }}"
                    id="filterBody">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Date From</label>
                            <input type="date" name="date_from" value="{{ request('date_from') }}">
                        </div>
                        <div class="filter-group">
                            <label>Date To</label>
                            <input type="date" name="date_to" value="{{ request('date_to') }}">
                        </div>
                        <div class="filter-group">
                            <label>Account Type</label>
                            <select name="type" class="form-control select2" style="width:100%">
                                <option value="">All Types</option>
                                @foreach(['asset','liability','equity','income','expense'] as $t)
                                    <option value="{{ $t }}" {{ request('type') === $t ? 'selected' : '' }}>
                                        {{ ucfirst($t) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Search Account</label>
                            <input type="text" name="search" value="{{ request('search') }}"
                                placeholder="Name or code…">
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="button" class="reset-btn" id="resetBtn">
                            <i class="fas fa-undo mr-1"></i>Reset
                        </button>
                        <button type="submit" class="apply-btn">
                            <i class="fas fa-search mr-1"></i>Apply
                        </button>
                    </div>
                </div>
            </div>
        </form>

        @if($rows->isEmpty())
            <div class="empty-report">
                <i class="fas fa-coins"></i>
                <p>No account data found for the selected filters.</p>
            </div>
        @else

        {{-- Type summary strip (clickable anchors) --}}
        @php
            $typeIcons = [
                'asset'     => 'fa-coins',
                'liability' => 'fa-file-invoice-dollar',
                'equity'    => 'fa-landmark',
                'income'    => 'fa-arrow-trend-up',
                'expense'   => 'fa-arrow-trend-down',
            ];
        @endphp
        <div class="type-strip">
            @foreach(['asset','liability','equity','income','expense'] as $type)
                @if(isset($typeTotals[$type]) && $typeTotals[$type]['count'] > 0)
                <a href="#section-{{ $type }}" style="text-decoration:none;flex:1;min-width:140px;">
                    <div class="type-strip-card {{ $type }}">
                        <div class="ts-label">
                            <i class="fas {{ $typeIcons[$type] }} mr-1"></i>{{ ucfirst($type) }}
                        </div>
                        <div class="ts-value">
                            {{ number_format(abs($typeTotals[$type]['closing']), 2) }}
                        </div>
                        <div class="ts-count">
                            {{ $typeTotals[$type]['count'] }} {{ Str::plural('account', $typeTotals[$type]['count']) }}
                        </div>
                    </div>
                </a>
                @endif
            @endforeach
        </div>

        {{-- Grand total box --}}
        <div class="grand-total-box" style="margin-bottom:20px;">
            <table>
                <thead>
                    <tr>
                        <th style="text-align:left;">Summary</th>
                        <th>Opening Balance</th>
                        <th>Period Debit</th>
                        <th>Period Credit</th>
                        <th>Closing Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <i class="fas fa-sigma mr-2"></i>All Accounts
                            <span style="font-size:12px;color:#64748b;font-weight:400;margin-left:6px;">
                                ({{ $rows->count() }} total)
                            </span>
                        </td>
                        <td class="gt-opening">{{ number_format($grandTotals['opening'], 2) }}</td>
                        <td class="gt-debit">{{ number_format($grandTotals['debit'], 2) }}</td>
                        <td class="gt-credit">{{ number_format($grandTotals['credit'], 2) }}</td>
                        <td class="gt-closing">{{ number_format($grandTotals['closing'], 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- One table per account type --}}
        @foreach(['asset','liability','equity','income','expense'] as $type)
            @if($grouped->has($type))
            @php
                $typeRows = $grouped[$type];
                $maxAbs   = $typeRows->max(fn($r) => abs($r['closing_balance']));
                $maxAbs   = $maxAbs ?: 1;
            @endphp
            <div class="table-card" id="section-{{ $type }}">
                <div class="table-card-header header-{{ $type }}">
                    <i class="fas {{ $typeIcons[$type] }}"></i>
                    <h4>{{ ucfirst($type) }} Accounts</h4>
                    <span class="type-badge type-{{ $type }}" style="margin-left:4px;">
                        {{ $typeTotals[$type]['count'] }} {{ Str::plural('account', $typeTotals[$type]['count']) }}
                    </span>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ab-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Account Name</th>
                                <th class="text-right">Opening Balance</th>
                                <th class="text-right">Period Debit</th>
                                <th class="text-right">Period Credit</th>
                                <th class="text-right">Closing Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($typeRows as $row)
                            @php
                                $pct = $maxAbs > 0 ? (abs($row['closing_balance']) / $maxAbs) * 100 : 0;
                                $isPos = $row['closing_balance'] >= 0;
                            @endphp
                            <tr>
                                <td>
                                    <span class="code-chip">{{ $row['account']->code }}</span>
                                </td>
                                <td>
                                    <span style="font-weight:600;color:#1e293b;">
                                        {{ $row['account']->name }}
                                    </span>
                                    @if($row['account']->parent)
                                        <div style="font-size:11px;color:#94a3b8;margin-top:2px;">
                                            <i class="fas fa-sitemap mr-1"></i>
                                            {{ $row['account']->parent->name }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if(abs($row['opening_balance']) > 0)
                                        <span class="{{ $row['opening_balance'] >= 0 ? 'debit-val' : 'credit-val' }}">
                                            {{ number_format(abs($row['opening_balance']), 2) }}
                                            <small style="font-size:10px;">
                                                {{ $row['opening_balance'] < 0 ? 'Cr' : 'Dr' }}
                                            </small>
                                        </span>
                                    @else
                                        <span class="zero-val">—</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if($row['period_debit'] > 0)
                                        <span class="debit-val">{{ number_format($row['period_debit'], 2) }}</span>
                                    @else
                                        <span class="zero-val">—</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if($row['period_credit'] > 0)
                                        <span class="credit-val">{{ number_format($row['period_credit'], 2) }}</span>
                                    @else
                                        <span class="zero-val">—</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <div style="display:flex;align-items:center;justify-content:flex-end;gap:6px;">
                                        <span class="{{ $isPos ? 'bal-pos' : 'bal-neg' }}" style="font-size:13px;">
                                            {{ number_format(abs($row['closing_balance']), 2) }}
                                            <small style="font-size:10px;">
                                                {{ $row['closing_balance'] < 0 ? 'Cr' : 'Dr' }}
                                            </small>
                                        </span>
                                        <div class="bal-bar-wrap">
                                            <div class="bal-bar {{ $isPos ? 'pos' : 'neg' }}"
                                                 style="width:{{ min($pct, 100) }}%"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="{{ $type }}-foot">
                            <tr>
                                <td colspan="2">
                                    <i class="fas fa-sigma mr-1"></i>{{ ucfirst($type) }} Subtotal
                                </td>
                                <td class="text-right">
                                    {{ number_format(abs($typeTotals[$type]['opening']), 2) }}
                                </td>
                                <td class="text-right">
                                    {{ number_format($typeTotals[$type]['debit'], 2) }}
                                </td>
                                <td class="text-right">
                                    {{ number_format($typeTotals[$type]['credit'], 2) }}
                                </td>
                                <td class="text-right">
                                    {{ number_format(abs($typeTotals[$type]['closing']), 2) }}
                                    <small style="font-size:10px;">
                                        {{ $typeTotals[$type]['closing'] < 0 ? 'Cr' : 'Dr' }}
                                    </small>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            @endif
        @endforeach

        @endif
    </div>
</div>

@endsection

@section('raw-script')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function () {
    $('.select2').select2();

    const baseUrl = '{{ route("role.report.account-balances", ["role" => Str::slug(Auth::user()->getRoleNames()->first())]) }}';

    $('#filterHeader').on('click', function () {
        $(this).toggleClass('active');
        $('#filterBody').toggleClass('active');
    });

    $('#resetBtn').on('click', function (e) {
        e.preventDefault();
        window.location.href = baseUrl;
    });

    // Smooth scroll to type section on strip click
    $('a[href^="#section-"]').on('click', function (e) {
        e.preventDefault();
        const target = $($(this).attr('href'));
        if (target.length) {
            $('html, body').animate({ scrollTop: target.offset().top - 80 }, 400);
        }
    });
});
</script>
@endsection