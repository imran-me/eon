@extends('layout.app')

@section('meta-information')
    <title>General Ledger</title>
@endsection

@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* ── Page wrapper ── */
    .gl-page { background:#f1f5f9; min-height:100vh; padding:20px; font-family:'Segoe UI',Arial,sans-serif; }

    /* ── Filter card ── */
    .filter-card { background:#fff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.07); margin-bottom:20px; overflow:hidden; }
    .filter-header { background:#f8f9fa; padding:14px 20px; cursor:pointer; display:flex; justify-content:space-between; align-items:center; border-left:4px solid #3b82f6; }
    .filter-header h3 { margin:0; font-size:15px; font-weight:600; color:#1f2937; }
    .filter-header .toggle-icon { transition:transform .3s; color:#6b7280; }
    .filter-header.active .toggle-icon { transform:rotate(180deg); }
    .filter-body { padding:0; max-height:0; overflow:hidden; transition:max-height .35s ease-out, padding .35s ease-out; }
    .filter-body.active { padding:20px; max-height:400px; }
    .filter-row { display:flex; flex-wrap:wrap; gap:16px; margin-bottom:12px; }
    .filter-group { flex:1; min-width:180px; }
    .filter-group label { display:block; margin-bottom:5px; font-weight:600; color:#374151; font-size:12px; text-transform:uppercase; letter-spacing:.4px; }
    .filter-group select, .filter-group input { width:100%; padding:9px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:13px; color:#374151; }
    .filter-group select:focus, .filter-group input:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.12); }
    .filter-actions { display:flex; justify-content:flex-end; gap:10px; }
    .btn-apply { background:#3b82f6; color:#fff; border:none; padding:9px 22px; border-radius:6px; font-weight:600; font-size:13px; cursor:pointer; }
    .btn-apply:hover { background:#2563eb; }
    .btn-reset { background:#fff; color:#6b7280; border:1px solid #d1d5db; padding:9px 18px; border-radius:6px; font-weight:600; font-size:13px; cursor:pointer; }
    .btn-reset:hover { background:#f3f4f6; }

    /* ── Select2 ── */
    .select2-container .select2-selection--single { height:40px; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height:38px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height:40px; }

    /* ── Report wrapper ── */
    .report-wrapper { background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.08); overflow:hidden; }

    /* ── Company header ── */
    .company-header { padding:28px 36px 20px; border-bottom:3px solid #1e3a5f; display:flex; align-items:center; gap:24px; }
    .company-logo-wrap { flex-shrink:0; }
    .company-logo-wrap img { width:80px; height:80px; object-fit:contain; border-radius:8px; border:1px solid #e2e8f0; background:#f8fafc; padding:4px; }
    .company-logo-wrap .logo-placeholder { width:80px; height:80px; border-radius:8px; background:#1e3a5f; display:flex; align-items:center; justify-content:center; color:#fff; font-size:28px; font-weight:700; letter-spacing:1px; }
    .company-info { flex:1; }
    .company-info .co-name { font-size:22px; font-weight:800; color:#1e3a5f; margin:0 0 4px; letter-spacing:.3px; }
    .company-info .co-meta { display:flex; flex-wrap:wrap; gap:6px 20px; margin-top:6px; }
    .company-info .co-meta span { font-size:12px; color:#64748b; display:flex; align-items:center; gap:5px; }
    .company-info .co-meta i { color:#3b82f6; width:13px; }
    .report-title-block { text-align:right; flex-shrink:0; }
    .report-title-block .rpt-title { font-size:18px; font-weight:700; color:#1e3a5f; margin:0; text-transform:uppercase; letter-spacing:1px; }
    .report-title-block .rpt-period { font-size:12px; color:#64748b; margin-top:4px; }
    .report-title-block .rpt-generated { font-size:11px; color:#94a3b8; margin-top:2px; }

    /* ── Report action bar ── */
    .report-action-bar { background:#f8fafc; padding:10px 24px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; }
    .report-action-bar .summary-chips { display:flex; gap:12px; flex-wrap:wrap; }
    .chip { padding:4px 14px; border-radius:20px; font-size:12px; font-weight:600; }
    .chip-blue { background:#dbeafe; color:#1d4ed8; }
    .chip-green { background:#dcfce7; color:#15803d; }
    .chip-orange { background:#fef3c7; color:#92400e; }

    /* ── Account block ── */
    .account-block { border-bottom:1px solid #e2e8f0; }
    .account-block:last-child { border-bottom:none; }
    .account-block-header { display:flex; justify-content:space-between; align-items:center; padding:14px 24px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
    .acc-left { display:flex; align-items:center; gap:10px; }
    .code-chip { font-family:monospace; background:#e2e8f0; border-radius:4px; padding:3px 9px; font-size:12px; color:#475569; font-weight:600; }
    .acc-name { font-size:15px; font-weight:700; color:#1e293b; }
    .type-badge { display:inline-block; padding:2px 10px; border-radius:20px; font-size:11px; font-weight:700; text-transform:capitalize; }
    .type-asset     { background:#dbeafe; color:#1d4ed8; }
    .type-liability { background:#fee2e2; color:#b91c1c; }
    .type-equity    { background:#d1fae5; color:#065f46; }
    .type-income    { background:#dcfce7; color:#15803d; }
    .type-expense   { background:#fef3c7; color:#92400e; }
    .acc-right { display:flex; align-items:center; gap:24px; }
    .acc-stat { text-align:right; }
    .acc-stat .st-label { font-size:10px; font-weight:700; text-transform:uppercase; color:#94a3b8; letter-spacing:.4px; }
    .acc-stat .st-val { font-size:14px; font-weight:700; font-family:monospace; margin-top:1px; }
    .st-debit  { color:#1d4ed8; }
    .st-credit { color:#065f46; }
    .st-bal-pos { color:#065f46; }
    .st-bal-neg { color:#b91c1c; }
    .btn-print-account { background:#1e3a5f; color:#fff; border:none; padding:6px 14px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px; text-decoration:none; white-space:nowrap; }
    .btn-print-account:hover { background:#16325a; }

    /* ── Ledger table ── */
    .ledger-table { width:100%; border-collapse:collapse; }
    .ledger-table thead th { background:#f1f5f9; padding:9px 16px; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.5px; border-bottom:2px solid #e2e8f0; }
    .ledger-table tbody tr:hover { background:#f8fafc; }
    .ledger-table tbody td { padding:9px 16px; font-size:13px; color:#334155; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
    .ledger-table tfoot td { background:#f1f5f9; padding:9px 16px; font-size:13px; font-weight:700; border-top:2px solid #e2e8f0; }
    .opening-row td { background:#eff6ff; color:#1d4ed8; font-style:italic; font-size:12px; font-weight:600; }
    .src-badge { display:inline-block; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; text-transform:capitalize; background:#f1f5f9; color:#475569; }
    .debit-val  { color:#1d4ed8; font-family:monospace; font-weight:600; }
    .credit-val { color:#065f46; font-family:monospace; font-weight:600; }
    .bal-pos { color:#065f46; font-family:monospace; font-weight:600; }
    .bal-neg { color:#b91c1c; font-family:monospace; font-weight:600; }

    /* ── Empty state ── */
    .empty-state { text-align:center; padding:60px 20px; color:#94a3b8; }
    .empty-state i { font-size:44px; margin-bottom:12px; display:block; }
</style>
@endsection

@section('main-content')
@php $company = Auth::user()->company; @endphp

<div class="gl-page">

    @include('report.partials.account-report-tabs')

    {{-- Filter Card --}}
    <div class="filter-card no-print">
        <div class="filter-header {{ request()->hasAny(['date_from','date_to','account_id','type']) ? 'active' : '' }}" id="filterHeader">
            <h3><i class="fas fa-sliders-h mr-2"></i>Filter Options</h3>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="filter-body {{ request()->hasAny(['date_from','date_to','account_id','type']) ? 'active' : '' }}" id="filterBody">
            <form action="" method="GET" id="filterForm">
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
                        <label>Account</label>
                        <select name="account_id" class="select2" style="width:100%">
                            <option value="">All Accounts</option>
                            @foreach($allAccounts as $acc)
                                <option value="{{ $acc->id }}" {{ request('account_id') == $acc->id ? 'selected' : '' }}>
                                    [{{ $acc->code }}] {{ $acc->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Account Type</label>
                        <select name="type" class="select2" style="width:100%">
                            <option value="">All Types</option>
                            @foreach(['asset','liability','equity','income','expense'] as $t)
                                <option value="{{ $t }}" {{ request('type') === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="button" class="btn-reset" id="resetBtn"><i class="fas fa-undo mr-1"></i>Reset</button>
                    <button type="submit" class="btn-apply"><i class="fas fa-search mr-1"></i>Apply Filter</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Report Wrapper --}}
    <div class="report-wrapper" id="reportWrapper">

        {{-- Company Header --}}
        <div class="company-header">
            <div class="company-logo-wrap">
                @if($company && $company->logo)
                    <img src="{{ asset($company->logo) }}" alt="{{ $company->name }}">
                @else
                    <div class="logo-placeholder">
                        {{ strtoupper(substr($company->name ?? 'C', 0, 2)) }}
                    </div>
                @endif
            </div>
            <div class="company-info">
                <div class="co-name">{{ $company->name ?? config('app.name') }}</div>
                <div class="co-meta">
                    @if($company && $company->address)
                        <span><i class="fas fa-map-marker-alt"></i>{{ $company->address }}</span>
                    @endif
                    @if($company && $company->phone)
                        <span><i class="fas fa-phone"></i>{{ $company->phone }}</span>
                    @endif
                    @if($company && $company->email)
                        <span><i class="fas fa-envelope"></i>{{ $company->email }}</span>
                    @endif
                    @if($company && $company->website)
                        <span><i class="fas fa-globe"></i>{{ $company->website }}</span>
                    @endif
                </div>
            </div>
            <div class="report-title-block">
                <div class="rpt-title"><i class="fas fa-book mr-1"></i>General Ledger</div>
                <div class="rpt-period">
                    @if(request('date_from') || request('date_to'))
                        {{ request('date_from') ? \Carbon\Carbon::parse(request('date_from'))->format('d M Y') : 'Beginning' }}
                        &mdash;
                        {{ request('date_to') ? \Carbon\Carbon::parse(request('date_to'))->format('d M Y') : 'Today' }}
                    @else
                        All Periods
                    @endif
                </div>
                <div class="rpt-generated">Generated: {{ now()->format('d M Y, h:i A') }}</div>
            </div>
        </div>

        {{-- Action Bar --}}
        <div class="report-action-bar">
            <div class="summary-chips">
                <span class="chip chip-blue">
                    <i class="fas fa-layer-group mr-1"></i>{{ $accountsData->count() }} Accounts
                </span>
                @php
                    $grandDebit  = $accountsData->sum('total_debit');
                    $grandCredit = $accountsData->sum('total_credit');
                @endphp
                <span class="chip chip-blue">Total Debit: {{ number_format($grandDebit, 2) }}</span>
                <span class="chip chip-green">Total Credit: {{ number_format($grandCredit, 2) }}</span>
            </div>
        </div>

        {{-- Ledger Data --}}
        @if($accountsData->isEmpty())
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <p>No ledger data found for the selected filters.</p>
            </div>
        @else
            @foreach($accountsData as $data)
            <div class="account-block">

                {{-- Account Header --}}
                <div class="account-block-header">
                    <div class="acc-left">
                        <span class="code-chip">{{ $data['account']->code }}</span>
                        <span class="acc-name">{{ $data['account']->name }}</span>
                        <span class="type-badge type-{{ $data['account']->type }}">{{ ucfirst($data['account']->type) }}</span>
                    </div>
                    <div class="acc-right">
                        <div class="acc-stat">
                            <div class="st-label">Total Debit</div>
                            <div class="st-val st-debit">{{ number_format($data['total_debit'], 2) }}</div>
                        </div>
                        <div class="acc-stat">
                            <div class="st-label">Total Credit</div>
                            <div class="st-val st-credit">{{ number_format($data['total_credit'], 2) }}</div>
                        </div>
                        <div class="acc-stat">
                            <div class="st-label">Closing Balance</div>
                            <div class="st-val {{ $data['closing_balance'] >= 0 ? 'st-bal-pos' : 'st-bal-neg' }}">
                                {{ number_format(abs($data['closing_balance']), 2) }}
                                <small style="font-size:10px;">{{ $data['closing_balance'] < 0 ? 'Cr' : 'Dr' }}</small>
                            </div>
                        </div>
                        <a class="btn-print-account no-print" target="_blank"
                           href="{{ route('role.report.general-ledger.print', array_merge(['role' => Str::slug(Auth::user()->getRoleNames()->first())], request()->query(), ['account_id' => $data['account']->id])) }}">
                            <i class="fas fa-print"></i> Print
                        </a>
                    </div>
                </div>

                {{-- Ledger Table --}}
                <div style="overflow-x:auto;">
                    <table class="ledger-table">
                        <thead>
                            <tr>
                                <th style="width:100px;">Date</th>
                                <th style="width:110px;">Reference</th>
                                <th style="width:90px;">Source</th>
                                <th>Description / Note</th>
                                <th class="text-right" style="width:110px;">Debit</th>
                                <th class="text-right" style="width:110px;">Credit</th>
                                <th class="text-right" style="width:120px;">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="opening-row">
                                <td colspan="4"><i class="fas fa-arrow-right mr-1"></i>Opening Balance</td>
                                <td class="text-right">—</td>
                                <td class="text-right">—</td>
                                <td class="text-right">
                                    {{ number_format(abs($data['opening_balance']), 2) }}
                                    <small>{{ $data['opening_balance'] < 0 ? 'Cr' : 'Dr' }}</small>
                                </td>
                            </tr>

                            @foreach($data['rows'] as $row)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($row['date'])->format('d M Y') }}</td>
                                <td>
                                    @if($row['reference'])
                                        <span class="code-chip">{{ $row['reference'] }}</span>
                                    @else
                                        <span style="color:#cbd5e0;">—</span>
                                    @endif
                                </td>
                                <td><span class="src-badge">{{ ucfirst($row['source']) }}</span></td>
                                <td>
                                    <div style="font-size:13px;color:#1e293b;">{{ Str::limit($row['description'], 40) }}</div>
                                    @if($row['note'])
                                        <div style="font-size:11px;color:#94a3b8;margin-top:2px;">{{ $row['note'] }}</div>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if($row['debit'] > 0)
                                        <span class="debit-val">{{ number_format($row['debit'], 2) }}</span>
                                    @else
                                        <span style="color:#cbd5e0;">—</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if($row['credit'] > 0)
                                        <span class="credit-val">{{ number_format($row['credit'], 2) }}</span>
                                    @else
                                        <span style="color:#cbd5e0;">—</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <span class="{{ $row['balance'] >= 0 ? 'bal-pos' : 'bal-neg' }}">
                                        {{ number_format(abs($row['balance']), 2) }}
                                        <small style="font-size:10px;">{{ $row['balance'] < 0 ? 'Cr' : 'Dr' }}</small>
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-right" style="color:#475569;">Closing Balance</td>
                                <td class="text-right debit-val">{{ number_format($data['total_debit'], 2) }}</td>
                                <td class="text-right credit-val">{{ number_format($data['total_credit'], 2) }}</td>
                                <td class="text-right {{ $data['closing_balance'] >= 0 ? 'bal-pos' : 'bal-neg' }}">
                                    {{ number_format(abs($data['closing_balance']), 2) }}
                                    <small style="font-size:10px;">{{ $data['closing_balance'] < 0 ? 'Cr' : 'Dr' }}</small>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            </div>
            @endforeach
        @endif

    </div>{{-- end report-wrapper --}}

</div>{{-- end gl-page --}}
@endsection

@section('raw-script')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function () {
        $('.select2').select2();

        const baseUrl = '{{ route("role.report.general-ledger", ["role" => Str::slug(Auth::user()->getRoleNames()->first())]) }}';

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
