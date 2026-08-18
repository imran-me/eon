@extends('layout.app')

@section('meta-information')
    <title>Account Statement</title>
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
    .filter-content { background:#fff; padding:0; max-height:0; overflow:hidden; transition:max-height .3s ease-out, padding .3s ease-out; }
    .filter-content.active { padding:20px; max-height:400px; }
    .filter-row { display:flex; flex-wrap:wrap; gap:20px; margin-bottom:16px; }
    .filter-group { flex:1; min-width:200px; }
    .filter-group label { display:block; margin-bottom:6px; font-weight:500; color:#374151; font-size:13px; }
    .filter-group select,
    .filter-group input { width:100%; padding:10px; border:1px solid #d1d5db; border-radius:6px; font-size:14px; }
    .filter-group select:focus,
    .filter-group input:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.1); }
    .filter-actions { display:flex; justify-content:flex-end; gap:12px; margin-top:8px; }
    .apply-btn { background:#3b82f6; color:#fff; border:none; padding:10px 20px; border-radius:6px; font-weight:500; cursor:pointer; }
    .apply-btn:hover { background:#2563eb; }
    .reset-btn { background:#f8f9fa; color:#6b7280; border:1px solid #d1d5db; padding:10px 20px; border-radius:6px; font-weight:500; cursor:pointer; }
    .reset-btn:hover { background:#e5e7eb; }
    .select2-container .select2-selection--single { height:42px; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height:40px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height:42px; }

    /* ── User type toggle ── */
    .type-toggle { display:flex; gap:0; border-radius:8px; overflow:hidden; border:1px solid #d1d5db; width:fit-content; }
    .type-toggle-btn { padding:9px 22px; font-size:13px; font-weight:600; cursor:pointer; border:none; background:#fff; color:#6b7280; transition:all .2s; }
    .type-toggle-btn.active { background:#2563eb; color:#fff; }
    .type-toggle-btn:hover:not(.active) { background:#f1f5f9; }

    /* ── Report ── */
    .report-wrapper { margin:16px; }
    .report-header-box { background:linear-gradient(135deg, #1e40af, #3b82f6); border-radius:10px; padding:24px 28px; color:#fff; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; }
    .report-header-box h2 { margin:0; font-size:22px; font-weight:700; }
    .report-header-box .meta { font-size:13px; opacity:.85; margin-top:4px; }
    .print-btn { background:rgba(255,255,255,.2); border:1px solid rgba(255,255,255,.4); color:#fff; padding:8px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
    .print-btn:hover { background:rgba(255,255,255,.3); }

    /* ── User info card ── */
    .user-info-card { background:#fff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.08); padding:20px 24px; margin-bottom:20px; display:flex; flex-wrap:wrap; gap:24px; align-items:center; border-left:5px solid #2563eb; }
    .u-info-item { display:flex; flex-direction:column; gap:3px; }
    .u-info-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#94a3b8; }
    .u-info-value { font-size:14px; font-weight:700; color:#1e293b; }

    /* ── Summary cards ── */
    .summary-cards { display:flex; gap:16px; margin-bottom:20px; flex-wrap:wrap; }
    .summary-card { flex:1; min-width:160px; border-radius:10px; padding:18px 22px; }
    .summary-card.opening  { background:#f8fafc; border-left:4px solid #94a3b8; }
    .summary-card.debit    { background:#fff1f2; border-left:4px solid #dc2626; }
    .summary-card.credit   { background:#f0fdf4; border-left:4px solid #059669; }
    .summary-card.closing  { background:#fef3c7; border-left:4px solid #d97706; }
    .summary-card.closing.positive { background:#f0fdf4; border-left:4px solid #059669; }
    .summary-card.closing.negative { background:#fff1f2; border-left:4px solid #dc2626; }
    .summary-card .s-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; margin-bottom:6px; color:#64748b; }
    .summary-card .s-value { font-size:20px; font-weight:800; font-family:monospace; }
    .summary-card.opening .s-value { color:#475569; }
    .summary-card.debit   .s-value { color:#b91c1c; }
    .summary-card.credit  .s-value { color:#059669; }

    /* ── Statement table ── */
    .statement-card { background:#fff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.08); overflow:hidden; }
    .stmt-table { width:100%; border-collapse:collapse; }
    .stmt-table thead th { background:#f8fafc; padding:10px 16px; font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.4px; border-bottom:1px solid #e2e8f0; }
    .stmt-table tbody tr { border-bottom:1px solid #f1f5f9; transition:background .15s; }
    .stmt-table tbody tr:hover { background:#f8fafc; }
    .stmt-table tbody td { padding:10px 16px; font-size:13px; color:#334155; vertical-align:middle; }
    .stmt-table tfoot td { padding:12px 16px; font-size:13px; font-weight:700; border-top:2px solid #e2e8f0; background:#f1f5f9; }

    /* ── Row types ── */
    .opening-row td { background:#eff6ff; color:#1d4ed8; font-style:italic; font-size:12px; }
    .closing-row td { background:#fef9c3; font-weight:700; }

    /* ── Values ── */
    .debit-val  { color:#b91c1c; font-family:monospace; font-weight:600; }
    .credit-val { color:#059669; font-family:monospace; font-weight:600; }
    .bal-positive { color:#059669; font-family:monospace; font-weight:700; }
    .bal-negative { color:#b91c1c; font-family:monospace; font-weight:700; }
    .zero-val { color:#cbd5e1; font-family:monospace; }

    /* ── Badges ── */
    .code-chip { font-family:monospace; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:4px; padding:2px 7px; font-size:12px; color:#475569; }
    .type-badge { display:inline-block; padding:2px 9px; border-radius:999px; font-size:11px; font-weight:700; text-transform:capitalize; }
    .type-sale            { background:#dbeafe; color:#1d4ed8; }
    .type-purchase        { background:#fef3c7; color:#92400e; }
    .type-ticket_sale     { background:#d1fae5; color:#065f46; }
    .type-ticket_purchase { background:#f3e8ff; color:#6b21a8; }
    .type-expense         { background:#fee2e2; color:#b91c1c; }
    .type-salary          { background:#e0e7ff; color:#3730a3; }
    .pm-badge { display:inline-block; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; text-transform:capitalize; background:#f1f5f9; color:#475569; }

    /* ── Placeholder ── */
    .placeholder-box { text-align:center; padding:60px 20px; color:#94a3b8; background:#f8fafc; border-radius:10px; border:2px dashed #e2e8f0; }
    .placeholder-box i { font-size:48px; margin-bottom:12px; }
    .empty-report { text-align:center; padding:48px 20px; color:#94a3b8; }
    .empty-report i { font-size:40px; margin-bottom:10px; }

    @media print {
        .filter-container, .print-btn, .states-table-header { display:none !important; }
        body { background:#fff; }
    }
</style>
@endsection

@section('main-content')

@include('report.partials.account-report-tabs')

<div class="states-table bg-white rounded-lg shadow-md overflow-hidden">

    {{-- Filter --}}
    <form action="" method="GET" id="filterForm">
        {{-- Hidden user_type synced from toggle --}}
        <input type="hidden" name="user_type" id="userTypeInput" value="{{ request('user_type', 'customer') }}">

        <div class="filter-container" style="margin: 0 0 15px 0 !important;">
            <div class="filter-header {{ request()->hasAny(['user_id','date_from','date_to','source']) ? 'active' : '' }}"
                 id="filterHeader">
                <h3><i class="fas fa-filter mr-2"></i>Filter Options</h3>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="filter-content {{ request()->hasAny(['user_id','date_from','date_to','source']) ? 'active' : '' }}"
                 id="filterBody">

                {{-- User type toggle --}}
                <div style="margin-bottom:16px;">
                    <label style="display:block;margin-bottom:8px;font-weight:500;color:#374151;font-size:13px;">
                        Statement For
                    </label>
                    <div class="type-toggle">
                        <button type="button"
                                class="type-toggle-btn {{ request('user_type','customer') === 'customer' ? 'active' : '' }}"
                                data-type="customer">
                            <i class="fas fa-user mr-1"></i>Customer
                        </button>
                        <button type="button"
                                class="type-toggle-btn {{ request('user_type') === 'supplier' ? 'active' : '' }}"
                                data-type="supplier">
                            <i class="fas fa-truck mr-1"></i>Supplier
                        </button>
                    </div>
                </div>

                <div class="filter-row">
                    <div class="filter-group" style="min-width:260px;">
                        <label>
                            {{ request('user_type','customer') === 'supplier' ? 'Supplier' : 'Customer' }}
                            <span style="color:#ef4444">*</span>
                        </label>
                        <select name="user_id" class="form-control select2" style="width:100%">
                            <option value="">— Select —</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}"
                                    {{ request('user_id') == $u->id ? 'selected' : '' }}>
                                    {{ $u->name }}
                                    @if($u->phone) · {{ $u->phone }} @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}">
                    </div>
                    <div class="filter-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}">
                    </div>
                    <div class="filter-group">
                        <label>Transaction Type</label>
                        <select name="source" class="form-control select2" style="width:100%">
                            <option value="">All Types</option>
                            @foreach($sources as $src)
                                <option value="{{ $src }}" {{ request('source') === $src ? 'selected' : '' }}>
                                    {{ ucfirst(str_replace('_', ' ', $src)) }}
                                </option>
                            @endforeach
                        </select>
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

    <div class="report-wrapper" id="printArea">

        @if(!$user)
            <div class="placeholder-box">
                <i class="fas fa-hand-point-up"></i>
                <p>Select a {{ request('user_type','customer') === 'supplier' ? 'supplier' : 'customer' }} above to view their statement.</p>
            </div>
        @else

        {{-- Report Header --}}
        <div class="report-header-box">
            <div>
                <h2><i class="fas fa-file-lines mr-2"></i>Account Statement</h2>
                <div class="meta">
                    {{ ucfirst($userType) }}: {{ $user->name }}
                    &nbsp;·&nbsp;
                    @if(request('date_from') || request('date_to'))
                        {{ request('date_from') ? \Carbon\Carbon::parse(request('date_from'))->format('d M Y') : 'Beginning' }}
                        —
                        {{ request('date_to') ? \Carbon\Carbon::parse(request('date_to'))->format('d M Y') : 'Today' }}
                    @else
                        All Periods
                    @endif
                    &nbsp;·&nbsp; Generated: {{ now()->format('d M Y, h:i A') }}
                </div>
            </div>
            {{-- <button class="print-btn" onclick="window.print()">
                <i class="fas fa-print mr-1"></i>Print
            </button> --}}
        </div>

        {{-- User info card --}}
        <div class="user-info-card">
            <div class="u-info-item">
                <span class="u-info-label">Name</span>
                <span class="u-info-value">{{ $user->name }}</span>
            </div>
            @if(isset($user->phone) && $user->phone)
            <div class="u-info-item">
                <span class="u-info-label">Phone</span>
                <span class="u-info-value">{{ $user->phone }}</span>
            </div>
            @endif
            @if(isset($user->email) && $user->email)
            <div class="u-info-item">
                <span class="u-info-label">Email</span>
                <span class="u-info-value">{{ $user->email }}</span>
            </div>
            @endif
            @if(isset($user->address) && $user->address)
            <div class="u-info-item">
                <span class="u-info-label">Address</span>
                <span class="u-info-value">{{ $user->address }}</span>
            </div>
            @endif
            <div class="u-info-item">
                <span class="u-info-label">Type</span>
                <span class="u-info-value">
                    <span class="type-badge" style="background:#dbeafe;color:#1d4ed8;">
                        {{ ucfirst($userType) }}
                    </span>
                </span>
            </div>
            <div class="u-info-item">
                <span class="u-info-label">Transactions</span>
                <span class="u-info-value">{{ $rows->count() }}</span>
            </div>
        </div>

        {{-- Summary cards --}}
        @php
            $isPositive = $closingBalance >= 0;
        @endphp
        <div class="summary-cards">
            <div class="summary-card opening">
                <div class="s-label"><i class="fas fa-flag mr-1"></i>Opening Balance</div>
                <div class="s-value">
                    {{ number_format(abs($openingBalance), 2) }}
                    <small style="font-size:12px;">{{ $openingBalance < 0 ? 'Dr' : 'Cr' }}</small>
                </div>
            </div>
            <div class="summary-card credit">
                <div class="s-label">
                    <i class="fas fa-arrow-down mr-1"></i>
                    {{ $userType === 'customer' ? 'Total Received' : 'Total Paid' }}
                </div>
                <div class="s-value">{{ number_format($totalCredit, 2) }}</div>
            </div>
            <div class="summary-card debit">
                <div class="s-label">
                    <i class="fas fa-arrow-up mr-1"></i>
                    {{ $userType === 'customer' ? 'Total Refunded' : 'Total Charged' }}
                </div>
                <div class="s-value">{{ number_format($totalDebit, 2) }}</div>
            </div>
            <div class="summary-card closing {{ $isPositive ? 'positive' : 'negative' }}">
                <div class="s-label"><i class="fas fa-flag-checkered mr-1"></i>Closing Balance</div>
                <div class="s-value" style="color:{{ $isPositive ? '#059669' : '#b91c1c' }};">
                    {{ number_format(abs($closingBalance), 2) }}
                    <small style="font-size:12px;">{{ $closingBalance < 0 ? 'Dr' : 'Cr' }}</small>
                </div>
            </div>
        </div>

        {{-- Statement table --}}
        <div class="statement-card">
            @if($rows->isEmpty())
                <div class="empty-report">
                    <i class="fas fa-inbox"></i>
                    <p>No transactions found for this {{ $userType }} in the selected period.</p>
                </div>
            @else
            <div style="overflow-x:auto;">
                <table class="stmt-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Reference</th>
                            <th>Payment Method</th>
                            <th>Note</th>
                            <th class="text-right">
                                {{ $userType === 'customer' ? 'Received' : 'Paid' }}
                            </th>
                            <th class="text-right">
                                {{ $userType === 'customer' ? 'Refunded' : 'Charged' }}
                            </th>
                            <th class="text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody>

                        {{-- Opening balance row --}}
                        <tr class="opening-row">
                            <td>—</td>
                            <td>
                                {{ request('date_from')
                                    ? \Carbon\Carbon::parse(request('date_from'))->format('d M Y')
                                    : 'B/F' }}
                            </td>
                            <td colspan="5">
                                <i class="fas fa-arrow-right mr-1"></i>Opening Balance
                            </td>
                            <td class="text-right">—</td>
                            <td class="text-right">
                                <span class="{{ $openingBalance >= 0 ? 'bal-positive' : 'bal-negative' }}">
                                    {{ number_format(abs($openingBalance), 2) }}
                                    <small>{{ $openingBalance < 0 ? 'Dr' : 'Cr' }}</small>
                                </span>
                            </td>
                        </tr>

                        @foreach($rows as $i => $row)
                        <tr>
                            <td style="color:#94a3b8;">{{ $i + 1 }}</td>
                            <td style="white-space:nowrap;">
                                {{ \Carbon\Carbon::parse($row['date'])->format('d M Y') }}
                            </td>
                            <td>
                                <span class="type-badge type-{{ $row['type'] }}">
                                    {{ ucfirst(str_replace('_', ' ', $row['type'])) }}
                                </span>
                            </td>
                            <td>
                                @if($row['reference_no'])
                                    <span class="code-chip">{{ $row['reference_no'] }}</span>
                                @else
                                    <span class="zero-val">—</span>
                                @endif
                            </td>
                            <td>
                                @if($row['payment_method'])
                                    <span class="pm-badge">
                                        {{ ucfirst(str_replace('_', ' ', $row['payment_method'])) }}
                                    </span>
                                @else
                                    <span class="zero-val">—</span>
                                @endif
                            </td>
                            <td>
                                <span style="font-size:12px;color:#64748b;">
                                    {{ $row['note'] ?? '—' }}
                                </span>
                            </td>
                            <td class="text-right">
                                @if($row['credit'] > 0)
                                    <span class="credit-val">{{ number_format($row['credit'], 2) }}</span>
                                @else
                                    <span class="zero-val">—</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if($row['debit'] > 0)
                                    <span class="debit-val">{{ number_format($row['debit'], 2) }}</span>
                                @else
                                    <span class="zero-val">—</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <span class="{{ $row['balance'] >= 0 ? 'bal-positive' : 'bal-negative' }}"
                                      style="font-size:13px;">
                                    {{ number_format(abs($row['balance']), 2) }}
                                    <small>{{ $row['balance'] < 0 ? 'Dr' : 'Cr' }}</small>
                                </span>
                            </td>
                        </tr>
                        @endforeach

                        {{-- Closing row --}}
                        <tr class="closing-row">
                            <td colspan="6">
                                <i class="fas fa-flag-checkered mr-1"></i>Closing Balance
                            </td>
                            <td class="text-right credit-val">{{ number_format($totalCredit, 2) }}</td>
                            <td class="text-right debit-val">{{ number_format($totalDebit, 2) }}</td>
                            <td class="text-right">
                                <span class="{{ $closingBalance >= 0 ? 'bal-positive' : 'bal-negative' }}"
                                      style="font-size:14px;">
                                    {{ number_format(abs($closingBalance), 2) }}
                                    <small>{{ $closingBalance < 0 ? 'Dr' : 'Cr' }}</small>
                                </span>
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>
            @endif
        </div>

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

    const baseUrl = '{{ route("role.report.account-statement", ["role" => Str::slug(Auth::user()->getRoleNames()->first())]) }}';

    // Filter toggle
    $('#filterHeader').on('click', function () {
        $(this).toggleClass('active');
        $('#filterBody').toggleClass('active');
    });

    // Auto open if no user selected
    @if(!request('user_id'))
        $('#filterHeader').addClass('active');
        $('#filterBody').addClass('active');
    @endif

    // User type toggle
    $('.type-toggle-btn').on('click', function () {
        $('.type-toggle-btn').removeClass('active');
        $(this).addClass('active');
        const type = $(this).data('type');
        $('#userTypeInput').val(type);
        // Submit form to reload users list for new type
        $('#filterForm').submit();
    });

    $('#resetBtn').on('click', function (e) {
        e.preventDefault();
        window.location.href = baseUrl;
    });
});
</script>
@endsection