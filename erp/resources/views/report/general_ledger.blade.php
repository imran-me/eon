@extends('layout.app')

@section('meta-information')
    <title>General Ledger Report</title>
@endsection

@section('css')
<style>
    .gl-page { background:#f1f5f9; min-height:100vh; padding:20px; font-family:'Segoe UI',Arial,sans-serif; }

    /* ── Filter bar ── */
    .gl-filter { background:#fff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.07); padding:16px 20px; margin-bottom:20px; display:flex; justify-content:center; gap:16px; align-items:flex-end; flex-wrap:wrap; }
    .gl-filter .fg { display:flex; flex-direction:column; gap:5px; }
    .gl-filter label { font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.4px; }
    .gl-filter input { padding:9px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:13px; }
    .gl-filter input:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.12); }
    .btn-generate { background:#1e3a5f; color:#fff; border:none; padding:9px 22px; border-radius:6px; font-weight:600; font-size:13px; cursor:pointer; }
    .btn-generate:hover { background:#16325a; }

    /* ── Report wrapper ── */
    .report-wrapper { background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.08); overflow:hidden; }

    /* ── Company header ── */
    .company-header { padding:28px 36px 20px; border-bottom:3px solid #1e3a5f; display:flex; align-items:center; gap:24px; }
    .company-logo-wrap img { width:80px; height:80px; object-fit:contain; border-radius:8px; border:1px solid #e2e8f0; background:#f8fafc; padding:4px; }
    .logo-placeholder { width:80px; height:80px; border-radius:8px; background:#1e3a5f; display:flex; align-items:center; justify-content:center; color:#fff; font-size:28px; font-weight:700; }
    .co-name { font-size:22px; font-weight:800; color:#1e3a5f; margin:0 0 4px; }
    .co-meta { display:flex; flex-wrap:wrap; gap:6px 20px; margin-top:6px; }
    .co-meta span { font-size:12px; color:#64748b; display:flex; align-items:center; gap:5px; }
    .co-meta i { color:#3b82f6; width:13px; }
    .report-title-block { text-align:right; flex-shrink:0; margin-left:auto; }
    .rpt-title { font-size:18px; font-weight:700; color:#1e3a5f; text-transform:uppercase; letter-spacing:1px; }
    .rpt-period { font-size:12px; color:#64748b; margin-top:4px; }
    .rpt-generated { font-size:11px; color:#94a3b8; margin-top:2px; }

    /* ── Action bar ── */
    .action-bar { background:#f8fafc; padding:10px 24px; display:flex; justify-content:flex-end; border-bottom:1px solid #e2e8f0; }
    .btn-print { background:#1e3a5f; color:#fff; border:none; padding:8px 20px; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; }
    .btn-print:hover { background:#16325a; }

    /* ── Account block ── */
    .account-block { border-bottom:1px solid #e2e8f0; }
    .account-block:last-child { border-bottom:none; }
    .account-block-header { display:flex; justify-content:space-between; align-items:center; padding:12px 24px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
    .acc-title { font-size:14px; font-weight:700; color:#1e293b; }
    .acc-type { font-size:11px; background:#dbeafe; color:#1d4ed8; padding:2px 10px; border-radius:20px; font-weight:700; }

    /* ── Ledger table ── */
    .gl-table { width:100%; border-collapse:collapse; }
    .gl-table thead th { background:#f1f5f9; padding:9px 16px; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.5px; border-bottom:2px solid #e2e8f0; }
    .gl-table tbody td { padding:9px 16px; font-size:13px; color:#334155; border-bottom:1px solid #f1f5f9; }
    .gl-table tbody tr:hover { background:#f8fafc; }
    .gl-table .opening-row td { background:#eff6ff; color:#1d4ed8; font-style:italic; font-weight:600; font-size:12px; }
    .gl-table .summary-row td { background:#f1f5f9; font-weight:700; border-top:2px solid #e2e8f0; }
    .text-right { text-align:right; }
    .running-bal { color:#065f46; font-family:monospace; font-weight:600; }
    .debit-val { color:#1d4ed8; font-family:monospace; font-weight:600; }
    .credit-val { color:#065f46; font-family:monospace; font-weight:600; }

    /* ── Print ── */
    @media print {
        @page { margin:15mm 12mm; size: A4; }

        /* Step 1: hide everything */
        body * { visibility:hidden !important; }

        /* Step 2: show only report wrapper */
        .report-wrapper,
        .report-wrapper * { visibility:visible !important; }

        /* Step 3: position at top-left */
        .report-wrapper {
            position:absolute !important;
            top:0 !important;
            left:0 !important;
            width:100% !important;
            box-shadow:none !important;
            border-radius:0 !important;
        }

        /* Step 4: hide print button inside wrapper */
        .action-bar { display:none !important; }

        /* Step 5: preserve colors */
        .account-block-header { background:#f8fafc !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
        .gl-table thead th, .gl-table .summary-row td { background:#f1f5f9 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
        .gl-table .opening-row td { background:#eff6ff !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
        .company-header { border-bottom:2px solid #1e3a5f !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }

        .account-block { page-break-inside:avoid; }
    }
</style>
@endsection

@section('main-content')
@php $company = Auth::user()->company; @endphp

<div class="gl-page">

    {{-- Filter --}}
    <form action="" method="GET" class="gl-filter no-print">
        <div class="fg">
            <label>From Date</label>
            <input type="date" name="from_date" value="{{ $fromDate }}">
        </div>
        <div class="fg">
            <label>To Date</label>
            <input type="date" name="to_date" value="{{ $toDate }}">
        </div>
        <button type="submit" class="btn-generate"><i class="fas fa-search mr-1"></i>Generate</button>
    </form>

    {{-- Report --}}
    <div class="report-wrapper">

        {{-- Company Header --}}
        <div class="company-header">
            <div class="company-logo-wrap">
                @if($company && $company->logo)
                    <img src="{{ asset($company->logo) }}" alt="{{ $company->name }}">
                @else
                    <div class="logo-placeholder">{{ strtoupper(substr($company->name ?? 'C', 0, 2)) }}</div>
                @endif
            </div>
            <div>
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
                <div class="rpt-period">{{ date('d M Y', strtotime($fromDate)) }} &mdash; {{ date('d M Y', strtotime($toDate)) }}</div>
                <div class="rpt-generated">Generated: {{ now()->format('d M Y, h:i A') }}</div>
            </div>
        </div>

        {{-- Action bar --}}
        <div class="action-bar">
            <button class="btn-print" onclick="window.print()">
                <i class="fas fa-print mr-1"></i>Print / Save PDF
            </button>
        </div>

        {{-- Account Sections --}}
        @foreach($report as $data)
        <div class="account-block">
            <div class="account-block-header">
                <span class="acc-title">{{ $data->account->code }} &mdash; {{ $data->account->name }}</span>
                <span class="acc-type">{{ ucfirst($data->account->type) }}</span>
            </div>
            <table class="gl-table">
                <thead>
                    <tr>
                        <th style="width:100px;">Date</th>
                        <th style="width:120px;">Reference</th>
                        <th>Description / Note</th>
                        <th class="text-right" style="width:110px;">Debit</th>
                        <th class="text-right" style="width:110px;">Credit</th>
                        <th class="text-right" style="width:120px;">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="opening-row">
                        <td colspan="5">Opening Balance</td>
                        <td class="text-right">{{ number_format($data->opening, 2) }}</td>
                    </tr>

                    @php
                        $currentBal = $data->opening;
                        $totalDr = 0;
                        $totalCr = 0;
                    @endphp

                    @forelse($data->items as $item)
                        @php
                            $totalDr += $item->debit;
                            $totalCr += $item->credit;
                            if (in_array($data->account->type, ['asset', 'expense'])) {
                                $currentBal += ($item->debit - $item->credit);
                            } else {
                                $currentBal += ($item->credit - $item->debit);
                            }
                        @endphp
                        <tr>
                            <td>{{ date('d M Y', strtotime($item->journalEntry->date)) }}</td>
                            <td><span style="font-family:monospace;font-size:12px;background:#f1f5f9;padding:2px 7px;border-radius:4px;">{{ $item->journalEntry->reference }}</span></td>
                            <td>
                                <div style="font-size:13px;">{{ $item->note ?? $item->journalEntry->description }}</div>
                                <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;">{{ $item->journalEntry->source }}</div>
                            </td>
                            <td class="text-right">
                                @if($item->debit > 0)
                                    <span class="debit-val">{{ number_format($item->debit, 2) }}</span>
                                @else
                                    <span style="color:#cbd5e0;">—</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if($item->credit > 0)
                                    <span class="credit-val">{{ number_format($item->credit, 2) }}</span>
                                @else
                                    <span style="color:#cbd5e0;">—</span>
                                @endif
                            </td>
                            <td class="text-right running-bal">{{ number_format($currentBal, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center;color:#94a3b8;padding:20px;">No transactions in this period.</td>
                        </tr>
                    @endforelse

                    <tr class="summary-row">
                        <td colspan="3" class="text-right">Totals &amp; Closing Balance</td>
                        <td class="text-right debit-val">{{ number_format($totalDr, 2) }}</td>
                        <td class="text-right credit-val">{{ number_format($totalCr, 2) }}</td>
                        <td class="text-right running-bal">{{ number_format($currentBal, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        @endforeach

    </div>{{-- end report-wrapper --}}

</div>{{-- end gl-page --}}
@endsection
