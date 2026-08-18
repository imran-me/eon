@extends('layout.app')

@section('meta-information')
    <title>Journal Entry Report</title>
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
    span [aria-current="page"] span { background-color:#2563eb !important; color:#fff; border-color:#2563eb; }

    /* ── Report ── */
    .report-wrapper { margin:16px; }
    .report-header-box { background:linear-gradient(135deg,#1e40af,#3b82f6); border-radius:10px; padding:24px 28px; color:#fff; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; }
    .report-header-box h2 { margin:0; font-size:22px; font-weight:700; }
    .report-header-box .meta { font-size:13px; opacity:.85; margin-top:4px; }
    .print-btn { background:rgba(255,255,255,.2); border:1px solid rgba(255,255,255,.4); color:#fff; padding:8px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
    .print-btn:hover { background:rgba(255,255,255,.3); }

    /* ── Summary cards ── */
    .summary-cards { display:flex; gap:16px; margin-bottom:20px; flex-wrap:wrap; }
    .summary-card { flex:1; min-width:160px; border-radius:10px; padding:18px 22px; }
    .summary-card.entries { background:#eff6ff; border-left:4px solid #2563eb; }
    .summary-card.debit   { background:#eff6ff; border-left:4px solid #1d4ed8; }
    .summary-card.credit  { background:#f0fdf4; border-left:4px solid #059669; }
    .summary-card.balanced-yes { background:#d1fae5; border-left:4px solid #059669; }
    .summary-card.balanced-no  { background:#fee2e2; border-left:4px solid #dc2626; }
    .summary-card .s-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; margin-bottom:6px; color:#64748b; }
    .summary-card .s-value { font-size:20px; font-weight:800; font-family:monospace; }
    .summary-card.entries .s-value { color:#1d4ed8; }
    .summary-card.debit   .s-value { color:#1d4ed8; }
    .summary-card.credit  .s-value { color:#059669; }
    .summary-card.balanced-yes .s-value { color:#065f46; }
    .summary-card.balanced-no  .s-value { color:#b91c1c; }

    /* ── Journal entry block ── */
    .je-block { background:#fff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.08); margin-bottom:16px; overflow:hidden; border-left:4px solid #e2e8f0; }
    .je-block.balanced   { border-left-color:#059669; }
    .je-block.unbalanced { border-left-color:#dc2626; }

    .je-header { display:flex; flex-wrap:wrap; gap:16px; justify-content:space-between; align-items:center; padding:14px 20px; border-bottom:1px solid #f1f5f9; background:#f8fafc; }
    .je-header-left  { display:flex; flex-wrap:wrap; gap:16px; align-items:center; }
    .je-header-right { display:flex; gap:12px; align-items:center; }

    .je-meta { display:flex; flex-direction:column; gap:2px; }
    .je-meta-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#94a3b8; }
    .je-meta-value { font-size:13px; font-weight:700; color:#1e293b; }

    /* ── Items table ── */
    .je-items { overflow-x:auto; }
    .items-table { width:100%; border-collapse:collapse; }
    .items-table thead th { background:#f1f5f9; padding:8px 16px; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.4px; border-bottom:1px solid #e2e8f0; }
    .items-table tbody tr { border-bottom:1px solid #f8fafc; }
    .items-table tbody tr:last-child { border-bottom:none; }
    .items-table tbody td { padding:9px 16px; font-size:13px; color:#334155; }
    .items-table tfoot td { background:#f8fafc; padding:9px 16px; font-size:13px; font-weight:700; border-top:2px solid #e2e8f0; }

    /* ── Badges ── */
    .code-chip { font-family:monospace; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:4px; padding:2px 7px; font-size:12px; color:#475569; }
    .src-badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; text-transform:capitalize; }
    .src-manual         { background:#e0e7ff; color:#3730a3; }
    .src-sale           { background:#d1fae5; color:#065f46; }
    .src-purchase       { background:#fef3c7; color:#92400e; }
    .src-expense        { background:#fee2e2; color:#b91c1c; }
    .src-salary         { background:#dbeafe; color:#1d4ed8; }
    .src-loan           { background:#f3e8ff; color:#6b21a8; }
    .src-ticket_sale    { background:#d1fae5; color:#065f46; }
    .src-ticket_purchase{ background:#fef3c7; color:#92400e; }

    .bal-badge { display:inline-block; padding:3px 9px; border-radius:999px; font-size:11px; font-weight:700; }
    .bal-badge.ok   { background:#d1fae5; color:#065f46; }
    .bal-badge.warn { background:#fee2e2; color:#b91c1c; }

    .type-badge-acc { display:inline-block; padding:2px 7px; border-radius:4px; font-size:11px; font-weight:600; text-transform:capitalize; }
    .type-asset     { background:#dbeafe; color:#1d4ed8; }
    .type-liability { background:#fee2e2; color:#b91c1c; }
    .type-equity    { background:#d1fae5; color:#065f46; }
    .type-income    { background:#dcfce7; color:#15803d; }
    .type-expense   { background:#fef3c7; color:#92400e; }

    .debit-val  { color:#1d4ed8; font-family:monospace; font-weight:600; }
    .credit-val { color:#059669; font-family:monospace; font-weight:600; }
    .zero-val   { color:#cbd5e1; font-family:monospace; }

    /* ── Grand total bar ── */
    .grand-total-bar { background:#1e293b; border-radius:10px; padding:18px 24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-bottom:20px; }
    .gt-item { display:flex; flex-direction:column; gap:3px; }
    .gt-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#94a3b8; }
    .gt-value { font-size:18px; font-weight:800; font-family:monospace; }
    .gt-value.dv { color:#60a5fa; }
    .gt-value.cv { color:#34d399; }
    .gt-value.bv.ok   { color:#34d399; }
    .gt-value.bv.warn { color:#f87171; }

    .empty-report { text-align:center; padding:60px 20px; color:#94a3b8; }
    .empty-report i { font-size:48px; margin-bottom:12px; }

    @media print {
        .filter-container, .print-btn, .states-table-header { display:none !important; }
        .je-block { box-shadow:none; border:1px solid #e2e8f0; page-break-inside:avoid; }
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
                <h2><i class="fas fa-receipt mr-2"></i>Journal Entry Report</h2>
                <div class="meta">
                    @if(request('date_from') || request('date_to'))
                        Period:
                        {{ request('date_from') ? \Carbon\Carbon::parse(request('date_from'))->format('d M Y') : 'Beginning' }}
                        —
                        {{ request('date_to') ? \Carbon\Carbon::parse(request('date_to'))->format('d M Y') : 'Today' }}
                    @else
                        All Periods
                    @endif
                    @if(request('source'))
                        &nbsp;·&nbsp; Source: {{ ucfirst(str_replace('_', ' ', request('source'))) }}
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
                <div class="filter-header {{ request()->hasAny(['date_from','date_to','source','reference']) ? 'active' : '' }}"
                    id="filterHeader">
                    <h3><i class="fas fa-filter mr-2"></i>Filter Options</h3>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
                <div class="filter-content {{ request()->hasAny(['date_from','date_to','source','reference']) ? 'active' : '' }}"
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
                            <label>Source</label>
                            <select name="source" class="form-control select2" style="width:100%">
                                <option value="">All Sources</option>
                                @foreach($sources as $src)
                                    <option value="{{ $src }}" {{ request('source') === $src ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $src)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Reference</label>
                            <input type="text" name="reference" value="{{ request('reference') }}" placeholder="Search by reference…">
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

        @if($journals->isEmpty())
            <div class="empty-report">
                <i class="fas fa-receipt"></i>
                <p>No journal entries found for the selected filters.</p>
            </div>
        @else

        {{-- Summary cards --}}
        <div class="summary-cards">
            <div class="summary-card entries">
                <div class="s-label"><i class="fas fa-list mr-1"></i>Total Entries</div>
                <div class="s-value">{{ $journals->total() }}</div>
            </div>
            <div class="summary-card debit">
                <div class="s-label"><i class="fas fa-arrow-right mr-1"></i>Grand Total Debit</div>
                <div class="s-value">{{ number_format($grandTotalDebit, 2) }}</div>
            </div>
            <div class="summary-card credit">
                <div class="s-label"><i class="fas fa-arrow-left mr-1"></i>Grand Total Credit</div>
                <div class="s-value">{{ number_format($grandTotalCredit, 2) }}</div>
            </div>
            <div class="summary-card {{ $isBalanced ? 'balanced-yes' : 'balanced-no' }}">
                <div class="s-label"><i class="fas fa-scale-balanced mr-1"></i>Balance Status</div>
                <div class="s-value">{{ $isBalanced ? 'Balanced' : 'Unbalanced' }}</div>
            </div>
        </div>

        {{-- Grand total bar --}}
        <div class="grand-total-bar">
            <div class="gt-item">
                <span class="gt-label">Showing</span>
                <span class="gt-value" style="color:#fff;">
                    {{ $journals->firstItem() }}–{{ $journals->lastItem() }}
                    of {{ $journals->total() }} entries
                </span>
            </div>
            <div class="gt-item">
                <span class="gt-label">Grand Total Debit</span>
                <span class="gt-value dv">{{ number_format($grandTotalDebit, 2) }}</span>
            </div>
            <div class="gt-item">
                <span class="gt-label">Grand Total Credit</span>
                <span class="gt-value cv">{{ number_format($grandTotalCredit, 2) }}</span>
            </div>
            <div class="gt-item">
                <span class="gt-label">Difference</span>
                <span class="gt-value bv {{ $isBalanced ? 'ok' : 'warn' }}">
                    {{ number_format(abs($grandTotalDebit - $grandTotalCredit), 2) }}
                </span>
            </div>
            <div>
                <span class="bal-badge {{ $isBalanced ? 'ok' : 'warn' }}" style="font-size:13px;padding:6px 14px;">
                    <i class="fas {{ $isBalanced ? 'fa-check' : 'fa-times' }} mr-1"></i>
                    {{ $isBalanced ? 'Balanced' : 'Unbalanced' }}
                </span>
            </div>
        </div>

        {{-- Journal entry blocks --}}
        @foreach($journals as $journal)
        @php
            $jDebit    = $journal->items->sum('debit');
            $jCredit   = $journal->items->sum('credit');
            $jBalanced = abs($jDebit - $jCredit) < 0.01;
        @endphp
        <div class="je-block {{ $jBalanced ? 'balanced' : 'unbalanced' }}">

            {{-- Entry header --}}
            <div class="je-header">
                <div class="je-header-left">
                    <div class="je-meta">
                        <span class="je-meta-label">Date</span>
                        <span class="je-meta-value">
                            {{ \Carbon\Carbon::parse($journal->date)->format('d M Y') }}
                        </span>
                    </div>
                    <div class="je-meta">
                        <span class="je-meta-label">Reference</span>
                        <span class="je-meta-value">
                            @if($journal->reference)
                                <span class="code-chip">{{ $journal->reference }}</span>
                            @else
                                <span style="color:#94a3b8;">—</span>
                            @endif
                        </span>
                    </div>
                    <div class="je-meta">
                        <span class="je-meta-label">Source</span>
                        <span class="je-meta-value">
                            <span class="src-badge src-{{ $journal->source }}">
                                {{ ucfirst(str_replace('_', ' ', $journal->source)) }}
                            </span>
                        </span>
                    </div>
                    <div class="je-meta">
                        <span class="je-meta-label">Description</span>
                        <span class="je-meta-value" style="font-weight:500;color:#475569;">
                            {{ Str::limit($journal->description, 50) ?? '—' }}
                        </span>
                    </div>
                    <div class="je-meta">
                        <span class="je-meta-label">Created By</span>
                        <span class="je-meta-value" style="font-weight:500;color:#475569;">
                            {{ $journal->createdBy?->name ?? '—' }}
                        </span>
                    </div>
                </div>
                <div class="je-header-right">
                    <div style="text-align:right;">
                        <div style="font-size:11px;color:#94a3b8;font-weight:700;text-transform:uppercase;margin-bottom:3px;">
                            Total
                        </div>
                        <div class="debit-val" style="font-size:15px;">
                            Dr {{ number_format($jDebit, 2) }}
                        </div>
                        <div class="credit-val" style="font-size:15px;">
                            Cr {{ number_format($jCredit, 2) }}
                        </div>
                    </div>
                    <span class="bal-badge {{ $jBalanced ? 'ok' : 'warn' }}">
                        <i class="fas {{ $jBalanced ? 'fa-check' : 'fa-times' }} mr-1"></i>
                        {{ $jBalanced ? 'Balanced' : 'Unbalanced' }}
                    </span>
                </div>
            </div>

            {{-- Line items --}}
            <div class="je-items">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Account Code</th>
                            <th>Account Name</th>
                            <th>Type</th>
                            <th>Note</th>
                            <th class="text-right">Debit</th>
                            <th class="text-right">Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($journal->items as $i => $item)
                        <tr>
                            <td style="color:#94a3b8;width:36px;">{{ $i + 1 }}</td>
                            <td>
                                @if($item->account)
                                    <span class="code-chip">{{ $item->account->code }}</span>
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>
                            <td>{{ $item->account?->name ?? '—' }}</td>
                            <td>
                                @if($item->account)
                                    <span class="type-badge-acc type-{{ $item->account->type }}">
                                        {{ ucfirst($item->account->type) }}
                                    </span>
                                @endif
                            </td>
                            <td style="font-size:12px;color:#64748b;">{{ $item->note ?? '—' }}</td>
                            <td class="text-right">
                                @if($item->debit > 0)
                                    <span class="debit-val">{{ number_format($item->debit, 2) }}</span>
                                @else
                                    <span class="zero-val">—</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if($item->credit > 0)
                                    <span class="credit-val">{{ number_format($item->credit, 2) }}</span>
                                @else
                                    <span class="zero-val">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="text-right">Entry Total</td>
                            <td class="text-right debit-val">{{ number_format($jDebit, 2) }}</td>
                            <td class="text-right credit-val">{{ number_format($jCredit, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </div>
        @endforeach

        {{-- Pagination --}}
        @if($journals->hasPages())
        <div class="mt-4 flex justify-between items-center text-sm text-gray-500 pb-4">
            <span>
                Showing {{ $journals->firstItem() }}–{{ $journals->lastItem() }}
                of {{ $journals->total() }} entries
            </span>
            <div>{{ $journals->links() }}</div>
        </div>
        @endif

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

    const baseUrl = '{{ route("role.report.journal-entries", ["role" => Str::slug(Auth::user()->getRoleNames()->first())]) }}';

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