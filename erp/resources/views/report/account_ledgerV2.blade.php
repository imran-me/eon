@extends('layout.app')

@section('meta-information')
    <title>Account Ledger</title>
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
    .filter-content.active { padding:20px; max-height:300px; }
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
    .report-header-box { background:linear-gradient(135deg, #1e40af, #3b82f6); border-radius:10px; padding:24px 28px; color:#fff; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; }
    .report-header-box h2 { margin:0; font-size:22px; font-weight:700; }
    .report-header-box .meta { font-size:13px; opacity:.85; margin-top:4px; }
    .print-btn { background:rgba(255,255,255,.2); border:1px solid rgba(255,255,255,.4); color:#fff; padding:8px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
    .print-btn:hover { background:rgba(255,255,255,.3); }

    /* ── Account info bar ── */
    .account-info-bar { background:#fff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.08); padding:20px 24px; margin-bottom:20px; display:flex; flex-wrap:wrap; gap:24px; align-items:center; }
    .acc-info-item { display:flex; flex-direction:column; gap:3px; }
    .acc-info-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#94a3b8; }
    .acc-info-value { font-size:15px; font-weight:700; color:#1e293b; }

    /* ── Summary cards ── */
    .summary-cards { display:flex; gap:16px; margin-bottom:20px; flex-wrap:wrap; }
    .summary-card { flex:1; min-width:160px; border-radius:10px; padding:18px 22px; }
    .summary-card.opening  { background:#f8fafc;  border-left:4px solid #94a3b8; }
    .summary-card.debit    { background:#eff6ff;  border-left:4px solid #2563eb; }
    .summary-card.credit   { background:#f0fdf4;  border-left:4px solid #059669; }
    .summary-card.closing  { background:#fef3c7;  border-left:4px solid #d97706; }
    .summary-card .s-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; margin-bottom:6px; color:#64748b; }
    .summary-card .s-value { font-size:20px; font-weight:800; font-family:monospace; }
    .summary-card.opening .s-value { color:#475569; }
    .summary-card.debit   .s-value { color:#1d4ed8; }
    .summary-card.credit  .s-value { color:#059669; }
    .summary-card.closing .s-value { color:#92400e; }

    /* ── Ledger card ── */
    .ledger-card { background:#fff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.08); overflow:hidden; }
    .ledger-table { width:100%; border-collapse:collapse; }
    .ledger-table thead th { background:#f8fafc; padding:10px 16px; font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.4px; border-bottom:1px solid #e2e8f0; }
    .ledger-table tbody tr { border-bottom:1px solid #f1f5f9; transition:background .15s; }
    .ledger-table tbody tr:hover { background:#f8fafc; }
    .ledger-table tbody td { padding:10px 16px; font-size:13px; color:#334155; vertical-align:middle; }
    .ledger-table tfoot td { background:#f1f5f9; padding:11px 16px; font-size:13px; font-weight:700; border-top:2px solid #e2e8f0; }

    /* ── Row types ── */
    .opening-row td { background:#eff6ff; color:#1d4ed8; font-style:italic; font-size:12px; }
    .closing-row td { background:#fef9c3; font-weight:700; font-size:13px; }

    /* ── Values ── */
    .debit-val  { color:#1d4ed8; font-family:monospace; font-weight:600; }
    .credit-val { color:#059669; font-family:monospace; font-weight:600; }
    .bal-dr { color:#1d4ed8; font-family:monospace; font-weight:700; }
    .bal-cr { color:#dc2626; font-family:monospace; font-weight:700; }
    .zero-val { color:#cbd5e1; font-family:monospace; }

    /* ── Badges ── */
    .code-chip { font-family:monospace; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:4px; padding:2px 7px; font-size:12px; color:#475569; }
    .src-badge { display:inline-block; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; text-transform:capitalize; background:#f1f5f9; color:#475569; }
    .type-badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; text-transform:capitalize; }
    .type-asset     { background:#dbeafe; color:#1d4ed8; }
    .type-liability { background:#fee2e2; color:#b91c1c; }
    .type-equity    { background:#d1fae5; color:#065f46; }
    .type-income    { background:#dcfce7; color:#15803d; }
    .type-expense   { background:#fef3c7; color:#92400e; }

    /* ── No account selected placeholder ── */
    .placeholder-box { text-align:center; padding:60px 20px; color:#94a3b8; background:#f8fafc; border-radius:10px; border:2px dashed #e2e8f0; }
    .placeholder-box i { font-size:48px; margin-bottom:12px; }
    .placeholder-box p { font-size:15px; margin:0; }

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
        <div class="filter-container" style="margin: 0 0 15px 0 !important;">
            <div class="filter-header {{ request()->hasAny(['account_id','date_from','date_to']) ? 'active' : '' }}"
                 id="filterHeader">
                <h3><i class="fas fa-filter mr-2"></i>Filter Options</h3>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="filter-content {{ request()->hasAny(['account_id','date_from','date_to']) ? 'active' : '' }}"
                 id="filterBody">
                <div class="filter-row">
                    <div class="filter-group" style="min-width:280px;">
                        <label>Account <span style="color:#ef4444">*</span></label>
                        <select name="account_id" class="form-control select2" style="width:100%">
                            <option value="">— Select Account —</option>
                            @foreach($allAccounts as $acc)
                                <option value="{{ $acc->id }}"
                                    {{ request('account_id') == $acc->id ? 'selected' : '' }}>
                                    [{{ $acc->code }}] {{ $acc->name }} ({{ ucfirst($acc->type) }})
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

        @if(!$account)
            {{-- No account selected yet --}}
            <div class="placeholder-box">
                <i class="fas fa-hand-point-up"></i>
                <p>Select an account above to view its ledger.</p>
            </div>
        @else

        {{-- Report Header --}}
        <div class="report-header-box">
            <div>
                <h2><i class="fas fa-book-open mr-2"></i>Account Ledger</h2>
                <div class="meta">
                    [{{ $account->code }}] {{ $account->name }}
                    &nbsp;·&nbsp;
                    @if(request('date_from') || request('date_to'))
                        {{ request('date_from') ? \Carbon\Carbon::parse(request('date_from'))->format('d M Y') : 'Beginning' }}
                        —
                        {{ request('date_to')   ? \Carbon\Carbon::parse(request('date_to'))->format('d M Y')   : 'Today' }}
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

        {{-- Account info bar --}}
        <div class="account-info-bar">
            <div class="acc-info-item">
                <span class="acc-info-label">Account Code</span>
                <span class="acc-info-value"><span class="code-chip" style="font-size:14px;">{{ $account->code }}</span></span>
            </div>
            <div class="acc-info-item">
                <span class="acc-info-label">Account Name</span>
                <span class="acc-info-value">{{ $account->name }}</span>
            </div>
            <div class="acc-info-item">
                <span class="acc-info-label">Type</span>
                <span class="acc-info-value">
                    <span class="type-badge type-{{ $account->type }}">{{ ucfirst($account->type) }}</span>
                </span>
            </div>
            @if($account->parent)
            <div class="acc-info-item">
                <span class="acc-info-label">Parent Account</span>
                <span class="acc-info-value">
                    <span class="code-chip">{{ $account->parent->code }}</span>
                    {{ $account->parent->name }}
                </span>
            </div>
            @endif
            <div class="acc-info-item">
                <span class="acc-info-label">Total Transactions</span>
                <span class="acc-info-value">{{ $rows->count() }}</span>
            </div>
        </div>

        {{-- Summary cards --}}
        <div class="summary-cards">
            <div class="summary-card opening">
                <div class="s-label"><i class="fas fa-flag mr-1"></i>Opening Balance</div>
                <div class="s-value">
                    {{ number_format(abs($openingBalance), 2) }}
                    <small style="font-size:12px;">{{ $openingBalance < 0 ? 'Cr' : 'Dr' }}</small>
                </div>
            </div>
            <div class="summary-card debit">
                <div class="s-label"><i class="fas fa-arrow-right mr-1"></i>Total Debit</div>
                <div class="s-value">{{ number_format($totalDebit, 2) }}</div>
            </div>
            <div class="summary-card credit">
                <div class="s-label"><i class="fas fa-arrow-left mr-1"></i>Total Credit</div>
                <div class="s-value">{{ number_format($totalCredit, 2) }}</div>
            </div>
            <div class="summary-card closing">
                <div class="s-label"><i class="fas fa-flag-checkered mr-1"></i>Closing Balance</div>
                <div class="s-value">
                    {{ number_format(abs($closingBalance), 2) }}
                    <small style="font-size:12px;">{{ $closingBalance < 0 ? 'Cr' : 'Dr' }}</small>
                </div>
            </div>
        </div>

        {{-- Ledger table --}}
        <div class="ledger-card">
            @if($rows->isEmpty())
                <div class="empty-report">
                    <i class="fas fa-inbox"></i>
                    <p>No transactions found for this account in the selected period.</p>
                </div>
            @else
            <div style="overflow-x:auto;">
                <table class="ledger-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Source</th>
                            <th>Description / Note</th>
                            <th class="text-right">Debit</th>
                            <th class="text-right">Credit</th>
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
                            <td colspan="3">
                                <i class="fas fa-arrow-right mr-1"></i>
                                Opening Balance
                            </td>
                            <td class="text-right">—</td>
                            <td class="text-right">—</td>
                            <td class="text-right">
                                <span class="{{ $openingBalance >= 0 ? 'bal-dr' : 'bal-cr' }}">
                                    {{ number_format(abs($openingBalance), 2) }}
                                    <small>{{ $openingBalance < 0 ? 'Cr' : 'Dr' }}</small>
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
                                @if($row['reference'])
                                    <span class="code-chip">{{ $row['reference'] }}</span>
                                @else
                                    <span class="zero-val">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="src-badge">{{ ucfirst(str_replace('_', ' ', $row['source'])) }}</span>
                            </td>
                            <td>
                                <div style="font-size:13px;color:#1e293b;">
                                    {{ Str::limit($row['description'], 40) }}
                                </div>
                                @if($row['note'])
                                    <div style="font-size:11px;color:#94a3b8;margin-top:2px;">
                                        <i class="fas fa-note-sticky mr-1"></i>{{ $row['note'] }}
                                    </div>
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
                                @if($row['credit'] > 0)
                                    <span class="credit-val">{{ number_format($row['credit'], 2) }}</span>
                                @else
                                    <span class="zero-val">—</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <span class="{{ $row['balance'] >= 0 ? 'bal-dr' : 'bal-cr' }}" style="font-size:13px;">
                                    {{ number_format(abs($row['balance']), 2) }}
                                    <small>{{ $row['balance'] < 0 ? 'Cr' : 'Dr' }}</small>
                                </span>
                            </td>
                        </tr>
                        @endforeach

                        {{-- Closing balance row --}}
                        <tr class="closing-row">
                            <td colspan="5">
                                <i class="fas fa-flag-checkered mr-1"></i>
                                Closing Balance
                            </td>
                            <td class="text-right debit-val">{{ number_format($totalDebit, 2) }}</td>
                            <td class="text-right credit-val">{{ number_format($totalCredit, 2) }}</td>
                            <td class="text-right">
                                <span class="{{ $closingBalance >= 0 ? 'bal-dr' : 'bal-cr' }}" style="font-size:14px;">
                                    {{ number_format(abs($closingBalance), 2) }}
                                    <small>{{ $closingBalance < 0 ? 'Cr' : 'Dr' }}</small>
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

    const baseUrl = '{{ route("role.report.account-ledger", ["role" => Str::slug(Auth::user()->getRoleNames()->first())]) }}';

    $('#filterHeader').on('click', function () {
        $(this).toggleClass('active');
        $('#filterBody').toggleClass('active');
    });

    // Auto-open filter since account selection is required
    @if(!request('account_id'))
        $('#filterHeader').addClass('active');
        $('#filterBody').addClass('active');
    @endif

    $('#resetBtn').on('click', function (e) {
        e.preventDefault();
        window.location.href = baseUrl;
    });
});
</script>
@endsection