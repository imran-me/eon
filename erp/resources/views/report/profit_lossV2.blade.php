@extends('layout.app')

@section('meta-information')
    <title>Profit & Loss</title>
@endsection

@section('css')
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
    .filter-group input { width:100%; padding:10px; border:1px solid #d1d5db; border-radius:6px; font-size:14px; }
    .filter-group input:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.1); }
    .filter-actions { display:flex; justify-content:flex-end; gap:12px; margin-top:8px; }
    .apply-btn  { background:#3b82f6; color:#fff; border:none; padding:10px 20px; border-radius:6px; font-weight:500; cursor:pointer; }
    .apply-btn:hover  { background:#2563eb; }
    .reset-btn  { background:#f8f9fa; color:#6b7280; border:1px solid #d1d5db; padding:10px 20px; border-radius:6px; font-weight:500; cursor:pointer; }
    .reset-btn:hover  { background:#e5e7eb; }

    /* ── Report ── */
    .report-wrapper { margin:16px; }
    .report-header-box { background:linear-gradient(135deg, #1e40af, #3b82f6); border-radius:10px; padding:24px 28px; color:#fff; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; }
    .report-header-box h2 { margin:0; font-size:22px; font-weight:700; }
    .report-header-box .meta { font-size:13px; opacity:.85; margin-top:4px; }
    .print-btn { background:rgba(255,255,255,.2); border:1px solid rgba(255,255,255,.4); color:#fff; padding:8px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
    .print-btn:hover { background:rgba(255,255,255,.3); }

    /* ── Summary cards ── */
    .summary-cards { display:flex; gap:16px; margin-bottom:20px; flex-wrap:wrap; }
    .summary-card { flex:1; min-width:180px; border-radius:10px; padding:20px 24px; }
    .summary-card.income  { background:#d1fae5; border-left:5px solid #059669; }
    .summary-card.expense { background:#fee2e2; border-left:5px solid #dc2626; }
    .summary-card.profit  { background:#dbeafe; border-left:5px solid #2563eb; }
    .summary-card.loss    { background:#fef3c7; border-left:5px solid #d97706; }
    .summary-card .s-label { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; margin-bottom:6px; }
    .summary-card.income  .s-label { color:#065f46; }
    .summary-card.expense .s-label { color:#b91c1c; }
    .summary-card.profit  .s-label { color:#1d4ed8; }
    .summary-card.loss    .s-label { color:#92400e; }
    .summary-card .s-value { font-size:24px; font-weight:800; font-family:monospace; }
    .summary-card.income  .s-value { color:#065f46; }
    .summary-card.expense .s-value { color:#b91c1c; }
    .summary-card.profit  .s-value { color:#1d4ed8; }
    .summary-card.loss    .s-value { color:#92400e; }
    .summary-card .s-sub { font-size:12px; margin-top:4px; opacity:.7; }

    /* ── PL layout ── */
    .pl-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
    @media (max-width:768px) { .pl-grid { grid-template-columns:1fr; } }

    .pl-card { background:#fff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.08); overflow:hidden; }
    .pl-card-header { padding:14px 20px; border-bottom:2px solid #e2e8f0; display:flex; align-items:center; gap:10px; }
    .pl-card-header h4 { margin:0; font-size:15px; font-weight:700; }
    .pl-card-header.income-hdr  { background:#f0fdf4; color:#065f46; border-left:4px solid #059669; }
    .pl-card-header.expense-hdr { background:#fff1f2; color:#b91c1c; border-left:4px solid #dc2626; }

    .pl-table { width:100%; border-collapse:collapse; }
    .pl-table tbody tr { border-bottom:1px solid #f1f5f9; transition:background .15s; }
    .pl-table tbody tr:hover { background:#f8fafc; }
    .pl-table tbody td { padding:10px 16px; font-size:13px; color:#334155; }
    .pl-table tfoot td { padding:12px 16px; font-size:13px; font-weight:700; border-top:2px solid #e2e8f0; }
    .pl-table tfoot.income-foot  td { background:#f0fdf4; color:#065f46; }
    .pl-table tfoot.expense-foot td { background:#fff1f2; color:#b91c1c; }

    .code-chip { font-family:monospace; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:4px; padding:2px 7px; font-size:12px; color:#475569; }
    .income-val  { color:#059669; font-family:monospace; font-weight:600; }
    .expense-val { color:#dc2626; font-family:monospace; font-weight:600; }
    .zero-val    { color:#cbd5e1; font-family:monospace; }

    /* ── Net result ── */
    .net-result-box { border-radius:10px; padding:24px 28px; display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .net-result-box.profit { background:linear-gradient(135deg, #059669, #10b981); color:#fff; }
    .net-result-box.loss   { background:linear-gradient(135deg, #dc2626, #f87171); color:#fff; }
    .net-result-box .nr-label { font-size:16px; font-weight:600; opacity:.9; margin-bottom:4px; }
    .net-result-box .nr-value { font-size:32px; font-weight:900; font-family:monospace; }
    .net-result-box .nr-icon  { font-size:48px; opacity:.3; }

    .empty-report { text-align:center; padding:60px 20px; color:#94a3b8; }
    .empty-report i { font-size:48px; margin-bottom:12px; }

    /* ── Percentage bar ── */
    .pct-bar-wrap { height:5px; background:#e2e8f0; border-radius:999px; margin-top:4px; }
    .pct-bar { height:5px; border-radius:999px; }
    .pct-bar.income-bar  { background:#059669; }
    .pct-bar.expense-bar { background:#dc2626; }

    @media print {
        .filter-container, .print-btn, .states-table-header { display:none !important; }
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
                <h2><i class="fas fa-chart-line mr-2"></i>Profit & Loss Statement</h2>
                <div class="meta">
                    @if(request('date_from') || request('date_to'))
                        Period:
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

        {{-- Filter --}}
        <form action="" method="GET" id="filterForm">
            <div class="filter-container" style="margin: 0 0 15px 0 !important;">
                <div class="filter-header {{ request()->hasAny(['date_from','date_to']) ? 'active' : '' }}" id="filterHeader">
                    <h3><i class="fas fa-filter mr-2"></i>Filter Options</h3>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
                <div class="filter-content {{ request()->hasAny(['date_from','date_to']) ? 'active' : '' }}" id="filterBody">
                    <div class="filter-row">
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
                        <button type="button" class="reset-btn" id="resetBtn"><i class="fas fa-undo mr-1"></i>Reset</button>
                        <button type="submit" class="apply-btn"><i class="fas fa-search mr-1"></i>Apply</button>
                    </div>
                </div>
            </div>
        </form>

        @if($incomeAccounts->isEmpty() && $expenseAccounts->isEmpty())
            <div class="empty-report">
                <i class="fas fa-chart-line"></i>
                <p>No income or expense data found for the selected period.</p>
            </div>
        @else

        {{-- Summary cards --}}
        <div class="summary-cards">
            <div class="summary-card income">
                <div class="s-label"><i class="fas fa-arrow-up mr-1"></i>Total Income</div>
                <div class="s-value">{{ number_format($totalIncome, 2) }}</div>
                <div class="s-sub">{{ $incomeAccounts->count() }} income {{ Str::plural('account', $incomeAccounts->count()) }}</div>
            </div>
            <div class="summary-card expense">
                <div class="s-label"><i class="fas fa-arrow-down mr-1"></i>Total Expense</div>
                <div class="s-value">{{ number_format($totalExpense, 2) }}</div>
                <div class="s-sub">{{ $expenseAccounts->count() }} expense {{ Str::plural('account', $expenseAccounts->count()) }}</div>
            </div>
            <div class="summary-card {{ $netProfit >= 0 ? 'profit' : 'loss' }}">
                <div class="s-label">
                    <i class="fas {{ $netProfit >= 0 ? 'fa-trophy' : 'fa-exclamation-triangle' }} mr-1"></i>
                    Net {{ $netProfit >= 0 ? 'Profit' : 'Loss' }}
                </div>
                <div class="s-value">{{ number_format(abs($netProfit), 2) }}</div>
                <div class="s-sub">
                    @if($totalIncome > 0)
                        {{ number_format(($netProfit / $totalIncome) * 100, 1) }}% {{ $netProfit >= 0 ? 'profit' : 'loss' }} margin
                    @else
                        No income recorded
                    @endif
                </div>
            </div>
        </div>

        {{-- Income & Expense side by side --}}
        <div class="pl-grid">

            {{-- INCOME --}}
            <div class="pl-card">
                <div class="pl-card-header income-hdr">
                    <i class="fas fa-arrow-trend-up"></i>
                    <h4>Income</h4>
                </div>
                <table class="pl-table">
                    <tbody>
                        @forelse($incomeAccounts as $row)
                        @php $pct = $totalIncome > 0 ? ($row['net'] / $totalIncome) * 100 : 0; @endphp
                        <tr>
                            <td>
                                <span class="code-chip">{{ $row['account']->code }}</span>
                                <span class="ml-1">{{ $row['account']->name }}</span>
                                <div class="pct-bar-wrap">
                                    <div class="pct-bar income-bar" style="width:{{ min($pct, 100) }}%"></div>
                                </div>
                            </td>
                            <td class="text-right" style="width:130px;">
                                <span class="income-val">{{ number_format($row['net'], 2) }}</span>
                                <div style="font-size:10px;color:#94a3b8;">{{ number_format($pct, 1) }}%</div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="2" class="text-center" style="padding:24px;color:#94a3b8;">
                                No income recorded
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="income-foot">
                        <tr>
                            <td><i class="fas fa-sigma mr-1"></i>Total Income</td>
                            <td class="text-right">{{ number_format($totalIncome, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- EXPENSE --}}
            <div class="pl-card">
                <div class="pl-card-header expense-hdr">
                    <i class="fas fa-arrow-trend-down"></i>
                    <h4>Expenses</h4>
                </div>
                <table class="pl-table">
                    <tbody>
                        @forelse($expenseAccounts as $row)
                        @php $pct = $totalExpense > 0 ? ($row['net'] / $totalExpense) * 100 : 0; @endphp
                        <tr>
                            <td>
                                <span class="code-chip">{{ $row['account']->code }}</span>
                                <span class="ml-1">{{ $row['account']->name }}</span>
                                <div class="pct-bar-wrap">
                                    <div class="pct-bar expense-bar" style="width:{{ min($pct, 100) }}%"></div>
                                </div>
                            </td>
                            <td class="text-right" style="width:130px;">
                                <span class="expense-val">{{ number_format($row['net'], 2) }}</span>
                                <div style="font-size:10px;color:#94a3b8;">{{ number_format($pct, 1) }}%</div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="2" class="text-center" style="padding:24px;color:#94a3b8;">
                                No expenses recorded
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="expense-foot">
                        <tr>
                            <td><i class="fas fa-sigma mr-1"></i>Total Expenses</td>
                            <td class="text-right">{{ number_format($totalExpense, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </div>

        {{-- Net result --}}
        <div class="net-result-box {{ $netProfit >= 0 ? 'profit' : 'loss' }}">
            <div>
                <div class="nr-label">
                    {{ $netProfit >= 0 ? 'Net Profit' : 'Net Loss' }} for the period
                </div>
                <div class="nr-value">
                    {{ $netProfit < 0 ? '(' : '' }}{{ number_format(abs($netProfit), 2) }}{{ $netProfit < 0 ? ')' : '' }}
                </div>
                <div style="font-size:13px;opacity:.8;margin-top:6px;">
                    Income {{ number_format($totalIncome, 2) }}
                    − Expenses {{ number_format($totalExpense, 2) }}
                    = {{ number_format($netProfit, 2) }}
                </div>
            </div>
            <div class="nr-icon">
                <i class="fas {{ $netProfit >= 0 ? 'fa-trophy' : 'fa-triangle-exclamation' }}"></i>
            </div>
        </div>

        @endif
    </div>
</div>

@endsection

@section('raw-script')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function () {
    const baseUrl = '{{ route("role.report.profit-loss", ["role" => Str::slug(Auth::user()->getRoleNames()->first())]) }}';

    $('#filterHeader').on('click', function () {
        $(this).toggleClass('active');
        $('#filterBody').toggleClass('active');
    });

    $('#resetBtn').on('click', function (e) {
        e.preventDefault();
        window.location.href = baseUrl;
    });
});
</script>
@endsection