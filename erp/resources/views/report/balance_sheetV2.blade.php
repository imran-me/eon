@extends('layout.app')

@section('meta-information')
    <title>Balance Sheet</title>
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
    .filter-content.active { padding:20px; max-height:200px; }
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

    /* ── Balance alert ── */
    .balance-alert { display:flex; align-items:center; gap:12px; padding:14px 20px; border-radius:8px; margin-bottom:20px; font-weight:600; font-size:14px; }
    .balance-alert.ok   { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; }
    .balance-alert.warn { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; }

    /* ── Summary cards ── */
    .summary-cards { display:flex; gap:16px; margin-bottom:20px; flex-wrap:wrap; }
    .summary-card { flex:1; min-width:180px; border-radius:10px; padding:20px 24px; }
    .summary-card.asset     { background:#dbeafe; border-left:5px solid #2563eb; }
    .summary-card.liability { background:#fee2e2; border-left:5px solid #dc2626; }
    .summary-card.equity    { background:#d1fae5; border-left:5px solid #059669; }
    .summary-card .s-label { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; margin-bottom:6px; }
    .summary-card.asset     .s-label { color:#1d4ed8; }
    .summary-card.liability .s-label { color:#b91c1c; }
    .summary-card.equity    .s-label { color:#065f46; }
    .summary-card .s-value { font-size:22px; font-weight:800; font-family:monospace; }
    .summary-card.asset     .s-value { color:#1d4ed8; }
    .summary-card.liability .s-value { color:#b91c1c; }
    .summary-card.equity    .s-value { color:#065f46; }
    .summary-card .s-sub { font-size:12px; margin-top:4px; opacity:.7; }

    /* ── BS Grid ── */
    .bs-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
    @media (max-width:768px) { .bs-grid { grid-template-columns:1fr; } }

    /* ── BS Card ── */
    .bs-card { background:#fff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.08); overflow:hidden; }
    .bs-card-header { padding:14px 20px; border-bottom:2px solid #e2e8f0; display:flex; align-items:center; gap:10px; }
    .bs-card-header h4 { margin:0; font-size:15px; font-weight:700; }
    .bs-card-header.asset-hdr     { background:#eff6ff; color:#1d4ed8; border-left:4px solid #2563eb; }
    .bs-card-header.liability-hdr { background:#fff1f2; color:#b91c1c; border-left:4px solid #dc2626; }
    .bs-card-header.equity-hdr    { background:#f0fdf4; color:#065f46; border-left:4px solid #059669; }

    .bs-table { width:100%; border-collapse:collapse; }
    .bs-table tbody tr { border-bottom:1px solid #f1f5f9; transition:background .15s; }
    .bs-table tbody tr:hover { background:#f8fafc; }
    .bs-table tbody td { padding:10px 16px; font-size:13px; color:#334155; }
    .bs-table tfoot td { padding:12px 16px; font-size:13px; font-weight:700; border-top:2px solid #e2e8f0; }
    .bs-table tfoot.asset-foot     td { background:#eff6ff; color:#1d4ed8; }
    .bs-table tfoot.liability-foot td { background:#fff1f2; color:#b91c1c; }
    .bs-table tfoot.equity-foot    td { background:#f0fdf4; color:#065f46; }

    /* ── Net profit row inside equity ── */
    .net-profit-row td { background:#f0fdf4; font-style:italic; color:#065f46; }
    .net-loss-row   td { background:#fff1f2; font-style:italic; color:#b91c1c; }

    /* ── Accounting equation bar ── */
    .equation-bar { background:#1e293b; border-radius:10px; padding:20px 28px; display:flex; align-items:center; justify-content:center; gap:16px; flex-wrap:wrap; margin-bottom:20px; }
    .eq-item { text-align:center; }
    .eq-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#94a3b8; margin-bottom:4px; }
    .eq-value { font-size:18px; font-weight:800; font-family:monospace; color:#fff; }
    .eq-value.asset-v     { color:#60a5fa; }
    .eq-value.liability-v { color:#f87171; }
    .eq-value.equity-v    { color:#34d399; }
    .eq-sign { font-size:24px; font-weight:900; color:#64748b; margin:0 4px; }
    .eq-balanced { font-size:12px; padding:4px 12px; border-radius:999px; font-weight:700; }
    .eq-balanced.ok   { background:#059669; color:#fff; }
    .eq-balanced.warn { background:#dc2626; color:#fff; }

    .code-chip { font-family:monospace; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:4px; padding:2px 7px; font-size:12px; color:#475569; }
    .asset-val     { color:#1d4ed8; font-family:monospace; font-weight:600; }
    .liability-val { color:#b91c1c; font-family:monospace; font-weight:600; }
    .equity-val    { color:#065f46; font-family:monospace; font-weight:600; }
    .zero-val      { color:#cbd5e1; font-family:monospace; }

    .empty-report { text-align:center; padding:60px 20px; color:#94a3b8; }
    .empty-report i { font-size:48px; margin-bottom:12px; }

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
                <h2><i class="fas fa-building-columns mr-2"></i>Balance Sheet</h2>
                <div class="meta">
                    As of: {{ \Carbon\Carbon::parse($asOf)->format('d M Y') }}
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
                <div class="filter-header {{ request('as_of') ? 'active' : '' }}" id="filterHeader">
                    <h3><i class="fas fa-filter mr-2"></i>Filter Options</h3>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
                <div class="filter-content {{ request('as_of') ? 'active' : '' }}" id="filterBody">
                    <div class="filter-row">
                        <div class="filter-group" style="max-width:280px;">
                            <label>As of Date</label>
                            <input type="date" name="as_of" value="{{ request('as_of', now()->toDateString()) }}">
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="button" class="reset-btn" id="resetBtn"><i class="fas fa-undo mr-1"></i>Reset</button>
                        <button type="submit" class="apply-btn"><i class="fas fa-search mr-1"></i>Apply</button>
                    </div>
                </div>
            </div>
        </form>

        @if($assetRows->isEmpty() && $liabilityRows->isEmpty() && $equityRows->isEmpty())
            <div class="empty-report">
                <i class="fas fa-building-columns"></i>
                <p>No balance sheet data found for the selected date.</p>
            </div>
        @else

        {{-- Balance alert --}}
        <div class="balance-alert {{ $isBalanced ? 'ok' : 'warn' }}">
            <i class="fas {{ $isBalanced ? 'fa-check-circle' : 'fa-exclamation-triangle' }}"></i>
            @if($isBalanced)
                Balance Sheet is balanced — Assets = Liabilities + Equity
            @else
                Balance Sheet is NOT balanced —
                Difference: {{ number_format(abs($totalAssets - $totalLiabEquity), 2) }}
            @endif
        </div>

        {{-- Summary cards --}}
        <div class="summary-cards">
            <div class="summary-card asset">
                <div class="s-label"><i class="fas fa-coins mr-1"></i>Total Assets</div>
                <div class="s-value">{{ number_format($totalAssets, 2) }}</div>
                <div class="s-sub">{{ $assetRows->count() }} {{ Str::plural('account', $assetRows->count()) }}</div>
            </div>
            <div class="summary-card liability">
                <div class="s-label"><i class="fas fa-file-invoice-dollar mr-1"></i>Total Liabilities</div>
                <div class="s-value">{{ number_format($totalLiabilities, 2) }}</div>
                <div class="s-sub">{{ $liabilityRows->count() }} {{ Str::plural('account', $liabilityRows->count()) }}</div>
            </div>
            <div class="summary-card equity">
                <div class="s-label"><i class="fas fa-landmark mr-1"></i>Total Equity</div>
                <div class="s-value">{{ number_format($totalEquity, 2) }}</div>
                <div class="s-sub">Includes net {{ $netProfit >= 0 ? 'profit' : 'loss' }} {{ number_format(abs($netProfit), 2) }}</div>
            </div>
        </div>

        {{-- Accounting equation bar --}}
        <div class="equation-bar">
            <div class="eq-item">
                <div class="eq-label">Assets</div>
                <div class="eq-value asset-v">{{ number_format($totalAssets, 2) }}</div>
            </div>
            <div class="eq-sign">=</div>
            <div class="eq-item">
                <div class="eq-label">Liabilities</div>
                <div class="eq-value liability-v">{{ number_format($totalLiabilities, 2) }}</div>
            </div>
            <div class="eq-sign">+</div>
            <div class="eq-item">
                <div class="eq-label">Equity</div>
                <div class="eq-value equity-v">{{ number_format($totalEquity, 2) }}</div>
            </div>
            <div style="margin-left:12px;">
                <span class="eq-balanced {{ $isBalanced ? 'ok' : 'warn' }}">
                    <i class="fas {{ $isBalanced ? 'fa-check' : 'fa-times' }} mr-1"></i>
                    {{ $isBalanced ? 'Balanced' : 'Unbalanced' }}
                </span>
            </div>
        </div>

        {{-- ASSETS (full width) --}}
        <div class="bs-card" style="margin-bottom:20px;">
            <div class="bs-card-header asset-hdr">
                <i class="fas fa-coins"></i>
                <h4>Assets</h4>
            </div>
            <div style="overflow-x:auto;">
                <table class="bs-table">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <th style="padding:10px 16px;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #e2e8f0;">Code</th>
                            <th style="padding:10px 16px;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #e2e8f0;">Account Name</th>
                            <th style="padding:10px 16px;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #e2e8f0;text-align:right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assetRows as $row)
                        <tr>
                            <td><span class="code-chip">{{ $row['account']->code }}</span></td>
                            <td>{{ $row['account']->name }}</td>
                            <td class="text-right">
                                <span class="asset-val">{{ number_format($row['net'], 2) }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center" style="padding:24px;color:#94a3b8;">No asset accounts</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="asset-foot">
                        <tr>
                            <td colspan="2"><i class="fas fa-sigma mr-1"></i>Total Assets</td>
                            <td class="text-right">{{ number_format($totalAssets, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- LIABILITIES + EQUITY side by side --}}
        <div class="bs-grid">

            {{-- Liabilities --}}
            <div class="bs-card">
                <div class="bs-card-header liability-hdr">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <h4>Liabilities</h4>
                </div>
                <table class="bs-table">
                    <tbody>
                        @forelse($liabilityRows as $row)
                        <tr>
                            <td>
                                <span class="code-chip">{{ $row['account']->code }}</span>
                                <span class="ml-1">{{ $row['account']->name }}</span>
                            </td>
                            <td class="text-right" style="width:130px;">
                                <span class="liability-val">{{ number_format($row['net'], 2) }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="2" class="text-center" style="padding:24px;color:#94a3b8;">No liabilities</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="liability-foot">
                        <tr>
                            <td><i class="fas fa-sigma mr-1"></i>Total Liabilities</td>
                            <td class="text-right">{{ number_format($totalLiabilities, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Equity --}}
            <div class="bs-card">
                <div class="bs-card-header equity-hdr">
                    <i class="fas fa-landmark"></i>
                    <h4>Equity</h4>
                </div>
                <table class="bs-table">
                    <tbody>
                        @forelse($equityRows as $row)
                        <tr>
                            <td>
                                <span class="code-chip">{{ $row['account']->code }}</span>
                                <span class="ml-1">{{ $row['account']->name }}</span>
                            </td>
                            <td class="text-right" style="width:130px;">
                                <span class="equity-val">{{ number_format($row['net'], 2) }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="2" class="text-center" style="padding:24px;color:#94a3b8;">No equity accounts</td></tr>
                        @endforelse

                        {{-- Net Profit/Loss folded into equity --}}
                        <tr class="{{ $netProfit >= 0 ? 'net-profit-row' : 'net-loss-row' }}">
                            <td>
                                <i class="fas {{ $netProfit >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }} mr-1"></i>
                                Net {{ $netProfit >= 0 ? 'Profit' : 'Loss' }} (Current Period)
                            </td>
                            <td class="text-right" style="width:130px;">
                                <span class="{{ $netProfit >= 0 ? 'equity-val' : 'liability-val' }}">
                                    {{ number_format(abs($netProfit), 2) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="equity-foot">
                        <tr>
                            <td><i class="fas fa-sigma mr-1"></i>Total Equity</td>
                            <td class="text-right">{{ number_format($totalEquity, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </div>

        {{-- Liabilities + Equity total --}}
        <div class="bs-card">
            <table class="bs-table">
                <tfoot>
                    <tr style="background:#1e293b;">
                        <td style="padding:14px 16px;color:#fff;font-weight:700;font-size:14px;">
                            <i class="fas fa-sigma mr-2"></i>Total Liabilities + Equity
                        </td>
                        <td class="text-right" style="padding:14px 16px;color:#34d399;font-weight:800;font-family:monospace;font-size:15px;">
                            {{ number_format($totalLiabEquity, 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
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
    const baseUrl = '{{ route("role.report.balance-sheet", ["role" => Str::slug(Auth::user()->getRoleNames()->first())]) }}';

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