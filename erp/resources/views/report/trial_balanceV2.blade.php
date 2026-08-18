@extends('layout.app')

@section('meta-information')
    <title>Trial Balance</title>
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
    .filter-group input { width:100%; padding:10px; border:1px solid #d1d5db; border-radius:6px; font-size:14px; }
    .filter-group input:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.1); }
    .filter-actions { display:flex; justify-content:flex-end; gap:12px; margin-top:8px; }
    .apply-btn { background:#3b82f6; color:#fff; border:none; padding:10px 20px; border-radius:6px; font-weight:500; cursor:pointer; }
    .apply-btn:hover { background:#2563eb; }
    .reset-btn { background:#f8f9fa; color:#6b7280; border:1px solid #d1d5db; padding:10px 20px; border-radius:6px; font-weight:500; cursor:pointer; }
    .reset-btn:hover { background:#e5e7eb; }

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
    .balance-alert i { font-size:18px; }

    /* ── Table ── */
    .tb-card { background:#fff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.08); overflow:hidden; margin-bottom:20px; }
    .tb-card-header { display:flex; align-items:center; gap:10px; padding:13px 20px; border-bottom:2px solid #e2e8f0; }
    .tb-card-header h4 { margin:0; font-size:15px; font-weight:700; color:#1e293b; }
    .type-dot { width:12px; height:12px; border-radius:50%; }
    .dot-asset     { background:#1d4ed8; }
    .dot-liability { background:#b91c1c; }
    .dot-equity    { background:#065f46; }
    .dot-income    { background:#15803d; }
    .dot-expense   { background:#92400e; }

    .tb-table { width:100%; border-collapse:collapse; }
    .tb-table thead th { background:#f8fafc; padding:10px 16px; font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.4px; border-bottom:1px solid #e2e8f0; }
    .tb-table tbody tr { border-bottom:1px solid #f1f5f9; transition:background .15s; }
    .tb-table tbody tr:hover { background:#f8fafc; }
    .tb-table tbody td { padding:10px 16px; font-size:13px; color:#334155; }
    .tb-table tfoot td { background:#f1f5f9; padding:11px 16px; font-size:13px; font-weight:700; border-top:2px solid #e2e8f0; }

    /* ── Grand total ── */
    .grand-total-row { background:#1e40af !important; }
    .grand-total-row td { color:#fff !important; font-size:14px !important; font-weight:700 !important; padding:14px 16px !important; }

    .code-chip { font-family:monospace; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:4px; padding:2px 7px; font-size:12px; color:#475569; }
    .debit-val  { color:#1d4ed8; font-family:monospace; font-weight:600; }
    .credit-val { color:#065f46; font-family:monospace; font-weight:600; }
    .zero-val   { color:#cbd5e1; font-family:monospace; }

    .type-badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; text-transform:capitalize; }
    .type-asset     { background:#dbeafe; color:#1d4ed8; }
    .type-liability { background:#fee2e2; color:#b91c1c; }
    .type-equity    { background:#d1fae5; color:#065f46; }
    .type-income    { background:#dcfce7; color:#15803d; }
    .type-expense   { background:#fef3c7; color:#92400e; }

    .empty-report { text-align:center; padding:60px 20px; color:#94a3b8; }
    .empty-report i { font-size:48px; margin-bottom:12px; }

    @media print {
        .filter-container, .print-btn, .states-table-header { display:none !important; }
        .tb-card { box-shadow:none; border:1px solid #e2e8f0; }
        
        body * {
            visibility: hidden !important;
        }

        .report-header-box { background: #f4f4f4;}
        .report-header-box .meta, .report-header-box h2 { color: #1e293b; }
        .filter-container, .print-btn, .states-table-header { display:none !important; }
        .account-block { box-shadow:none; border:1px solid #e2e8f0; }

        .print-container, 
        .print-container * {
            visibility: visible !important;
        }

        #printHeader, 
        #printHeader * {
            visibility: visible !important;
        }
        body .mp-none{
            margin: 0 !important;
            padding: 0 !important;
        }
        /* Position elements at top */
        #printHeader {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
        }

        .print-container {
            margin-top: 0px; /* space for the header */
        }

        .print-area {
            display: none !important;
        }
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
                <h2><i class="fas fa-scale-balanced mr-2"></i>Trial Balance</h2>
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
            {{-- <button class="no-print print-btn" onclick="printSelectedArea(this, '#printArea')">
                <i class="fas fa-print mr-1"></i>Print
            </button> --}}
        </div>

        {{-- Filter --}}
        <form class="no-print" action="" method="GET" id="filterForm">
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

        @if($grouped->isEmpty())
            <div class="empty-report">
                <i class="fas fa-scale-balanced"></i>
                <p>No transactions found for the selected period.</p>
            </div>
        @else

        {{-- Balance alert --}}
        <div class="balance-alert {{ $isBalanced ? 'ok' : 'warn' }}">
            <i class="fas {{ $isBalanced ? 'fa-check-circle' : 'fa-exclamation-triangle' }}"></i>
            @if($isBalanced)
                Books are balanced — Total Debits equal Total Credits.
            @else
                Books are NOT balanced — Difference: {{ number_format(abs($grandTotalDebit - $grandTotalCredit), 2) }}
            @endif
        </div>

        {{-- One card per account type --}}
        @php
            $typeOrder = ['asset', 'liability', 'equity', 'income', 'expense'];
        @endphp

        @foreach($typeOrder as $type)
            @if($grouped->has($type))
            @php $typeRows = $grouped[$type]; @endphp
            <div class="tb-card">
                <div class="tb-card-header">
                    <span class="type-dot dot-{{ $type }}"></span>
                    <h4>{{ ucfirst($type) }} Accounts</h4>
                    <span class="type-badge type-{{ $type }}">
                        {{ $typeRows->count() }} {{ Str::plural('account', $typeRows->count()) }}
                    </span>
                </div>
                <div style="overflow-x:auto;">
                    <table class="tb-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Account Name</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($typeRows as $row)
                            <tr>
                                <td><span class="code-chip">{{ $row['account']->code }}</span></td>
                                <td>{{ $row['account']->name }}</td>
                                <td class="text-right">
                                    @if($row['net_debit'] > 0)
                                        <span class="debit-val">{{ number_format($row['net_debit'], 2) }}</span>
                                    @else
                                        <span class="zero-val">—</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if($row['net_credit'] > 0)
                                        <span class="credit-val">{{ number_format($row['net_credit'], 2) }}</span>
                                    @else
                                        <span class="zero-val">—</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2">{{ ucfirst($type) }} Subtotal</td>
                                <td class="text-right debit-val">{{ number_format($typeRows->sum('net_debit'), 2) }}</td>
                                <td class="text-right credit-val">{{ number_format($typeRows->sum('net_credit'), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            @endif
        @endforeach

        {{-- Grand Total --}}
        <div class="tb-card">
            <div style="overflow-x:auto;">
                <table class="tb-table">
                    <tfoot>
                        <tr class="grand-total-row">
                            <td colspan="2"><i class="fas fa-sigma mr-2"></i>Grand Total</td>
                            <td class="text-right" style="width:200px;">{{ number_format($grandTotalDebit, 2) }}</td>
                            <td class="text-right" style="width:200px;">{{ number_format($grandTotalCredit, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        @endif
    </div>
</div>

@endsection

@section('raw-script')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jQuery.print/1.6.0/jQuery.print.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function () {
        const baseUrl = '{{ route("role.report.trial-balance", ["role" => Str::slug(Auth::user()->getRoleNames()->first())]) }}';

        $('#filterHeader').on('click', function () {
            $(this).toggleClass('active');
            $('#filterBody').toggleClass('active');
        });

        $('#resetBtn').on('click', function (e) {
            e.preventDefault();
            window.location.href = baseUrl;
        });
    });
    function printSelectedArea(obj, target){
        $(target).print({
            globalStyles: true,   // Keep global CSS
            mediaPrint: true,     // Allow @media print styles
            iframe: true,         // Print inside an iframe
            noPrintSelector: ".no-print" // Hide unwanted items
        });        
    }      
</script>
@endsection