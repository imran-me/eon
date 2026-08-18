<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Salary Paid / Due Report</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
    @page { size: A4 portrait; margin: 12mm; }

    :root {
        --tc:  #3730a3;
        --tbg: #eef2ff;
        --thd: #4f46e5;
        --bdr: #e2e8f0;
        --txt: #1e293b;
        --tf:  "Montserrat", sans-serif;
        --ff:  "Inter", sans-serif;
        --nf:  "Space Mono", monospace;
    }

    *, *::before, *::after {
        box-sizing: border-box; margin: 0; padding: 0;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    body { font-family: var(--ff); padding: 28px; background: #f0f2f5; color: var(--txt); font-size: 12px; }

    /* ── Card ── */
    .card { max-width: 794px; margin: auto; background: #fff; padding: 40px; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,.06); }

    /* ── Header ── */
    .hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .co-info { text-align: right; font-size: 12px; line-height: 1.7; }
    .co-info strong { font-family: var(--tf); font-size: 16px; display: block; }
    .logo-txt { font-family: var(--tf); color: var(--tc); font-size: 26px; font-weight: 800; }

    /* ── Label bar ── */
    .label-bar {
        text-align: center; background: var(--tbg); color: var(--tc);
        text-transform: uppercase; letter-spacing: 2px; font-weight: 700;
        font-family: var(--tf); font-size: 11px; padding: 10px;
        border-top: 2px solid var(--tc); margin-bottom: 24px;
    }

    /* ── Meta ── */
    .meta-row { display: flex; justify-content: flex-end; margin-bottom: 20px; }
    .meta-tbl td { padding: 2px 0 2px 18px; font-weight: 600; font-size: 12px; }
    .meta-tbl .nf { font-family: var(--nf); font-size: 11px; }

    /* ── Summary strip ── */
    .summary-strip { display: flex; gap: 0; border: 1px solid var(--bdr); border-radius: 6px; overflow: hidden; margin-bottom: 20px; }
    .sum-card { flex: 1; padding: 10px 14px; border-right: 1px solid var(--bdr); }
    .sum-card:last-child { border-right: none; background: var(--thd); }
    .sum-label { font-size: 9px; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; font-weight: 600; margin-bottom: 3px; }
    .sum-card:last-child .sum-label { color: #c7d2fe; }
    .sum-value { font-family: var(--nf); font-size: 12px; font-weight: 700; color: var(--txt); }
    .sum-card:last-child .sum-value { color: #fff; font-size: 13px; }

    /* ── Report table ── */
    .ledger { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 11px; }
    .ledger th {
        background: var(--thd); color: #fff;
        padding: 8px 10px; text-align: left;
        font-family: var(--tf); font-size: 9px;
        text-transform: uppercase; letter-spacing: .04em;
    }
    .ledger th.r { text-align: right; }
    .ledger td { padding: 7px 10px; border-bottom: 1px solid var(--bdr); vertical-align: middle; }
    .ledger td.r    { text-align: right; }
    .ledger td.mono { font-family: var(--nf); font-size: 10px; }
    .ledger tbody tr:nth-child(even) td { background: #f8f9ff; }
    .ledger tfoot tr { background: var(--thd); color: #fff; }
    .ledger tfoot td { padding: 8px 10px; font-weight: 700; border-bottom: none; font-family: var(--tf); font-size: 10px; }
    .ledger tfoot td.r { text-align: right; font-family: var(--nf); }

    .credit  { color: #16a34a; font-weight: 600; }
    .debit   { color: #dc2626; font-weight: 600; }

    /* ── Sub-header bar ── */
    .sh { background: var(--thd); color: #fff; padding: 5px 12px; font-family: var(--tf); font-size: 10px; font-weight: 600; margin-bottom: 8px; letter-spacing: .03em; }
    .nb { font-size: 11px; line-height: 1.8; color: #64748b; margin-bottom: 14px; }

    /* ── Signature ── */
    .sig { margin-top: 36px; display: flex; justify-content: flex-end; }
    .sig-c { text-align: center; width: 200px; }
    .sig-l { border-top: 1px solid #333; margin-top: 6px; padding-top: 4px; font-size: 10px; font-weight: 700; font-family: var(--tf); }

    /* ── Print ── */
    @media print {
        body       { background: #fff !important; padding: 0 !important; }
        .btn-row   { display: none !important; }
        .card      { box-shadow: none !important; padding: 0 !important; max-width: 100% !important; }
        .label-bar { padding: 7px; margin-bottom: 14px; }
        .ledger    { font-size: 10px; }
        .ledger td { padding: 5px 8px; }
        .ledger th { padding: 6px 8px; }
        .sig       { margin-top: 24px; }
    }
</style>
</head>
<body>

{{-- Action buttons ── --}}
<div class="btn-row" style="max-width:794px;margin:0 auto 12px auto;display:flex;gap:8px;justify-content:flex-end;">
    <a href="javascript:history.back()" style="background:#6b7280;color:#fff;text-decoration:none;display:inline-block;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;border-radius:4px;">← Back</a>
    <button onclick="window.print()" style="background:#4f46e5;color:#fff;border:none;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">🖨️ Print</button>
</div>

<div class="card">

    {{-- Header ── --}}
    <div class="hdr">
        <div>
            @if(optional($company)->logo)
            @php $logoSrc = file_exists(public_path($company->logo)) ? asset($company->logo) : asset('logo.png'); @endphp
            <img src="{{ $logoSrc }}" alt="" style="max-width:80px;">
            @else
            <div class="logo-txt">{{ optional($company)->name ?? config('app.name') }}</div>
            @endif
        </div>
        <div class="co-info">
            <strong>{{ optional($company)->name ?? config('app.name') }}</strong>
            @if(optional($company)->address){{ $company->address }}<br>@endif
            {{ optional($company)->phone }}{{ optional($company)->phone && optional($company)->email ? ' | ' : '' }}{{ optional($company)->email }}
        </div>
    </div>

    {{-- Label bar ── --}}
    <div class="label-bar">Salary Paid / Due Report</div>

    {{-- Meta ── --}}
    <div class="meta-row">
        <table class="meta-tbl">
            <tr>
                <td>Period</td>
                <td class="nf">
                    @if($from || $to)
                        {{ $from ? \Carbon\Carbon::createFromFormat('Y-m', $from)->format('M Y') : 'Beginning' }}
                        → {{ $to ? \Carbon\Carbon::createFromFormat('Y-m', $to)->format('M Y') : 'Now' }}
                    @else
                        All Time
                    @endif
                </td>
            </tr>
            <tr><td>Generated</td><td class="nf">{{ now()->format('d M Y') }}</td></tr>
            <tr><td>Printed By</td><td>{{ Auth::user()->name }}</td></tr>
        </table>
    </div>

    {{-- Summary strip ── --}}
    <div class="summary-strip">
        <div class="sum-card">
            <div class="sum-label">Total Gross</div>
            <div class="sum-value">৳{{ number_format($grandTotals['total_gross'], 2) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Total Paid</div>
            <div class="sum-value" style="color:#16a34a;">৳{{ number_format($grandTotals['total_paid'], 2) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Total Due</div>
            <div class="sum-value">৳{{ number_format($grandTotals['total_due'], 2) }}</div>
        </div>
    </div>

    {{-- Report table ── --}}
    <table class="ledger">
        <thead>
            <tr>
                <th width="6%">#</th>
                <th width="34%">Employee</th>
                <th class="r" width="18%">Gross (৳)</th>
                <th class="r" width="16%">Paid (৳)</th>
                <th class="r" width="16%">Due (৳)</th>
                <th class="r" width="10%">Records</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $row->employee_name }}</td>
                <td class="r mono">{{ number_format($row->total_gross, 2) }}</td>
                <td class="r mono credit">{{ number_format($row->total_paid, 2) }}</td>
                <td class="r mono {{ $row->total_due > 0 ? 'debit' : '' }}">{{ number_format($row->total_due, 2) }}</td>
                <td class="r" style="font-size:9px;color:#64748b;">{{ $row->paid_count }}P{{ $row->partial_count > 0 ? ' / ' . $row->partial_count . 'PT' : '' }} / {{ $row->due_count }}D</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align:center;padding:20px;color:#94a3b8;">No salary records found for this period.</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2">Grand Total</td>
                <td class="r">৳{{ number_format($grandTotals['total_gross'], 2) }}</td>
                <td class="r">৳{{ number_format($grandTotals['total_paid'], 2) }}</td>
                <td class="r">৳{{ number_format($grandTotals['total_due'], 2) }}</td>
                <td class="r"></td>
            </tr>
        </tfoot>
    </table>

    <div class="sh">Note:</div>
    <div class="nb">
        This report was generated on {{ now()->format('d M Y \a\t H:i') }} and reflects running totals per employee
        @if($from || $to)
            from {{ $from ? \Carbon\Carbon::createFromFormat('Y-m', $from)->format('M Y') : 'inception' }} to {{ $to ? \Carbon\Carbon::createFromFormat('Y-m', $to)->format('M Y') : 'now' }}.
        @else
            from inception to date.
        @endif
        "Paid" and "Due" reflect each salary record's current status; "Due" does not necessarily mean overdue.
    </div>

    {{-- Signature ── --}}
    <div class="sig">
        <div class="sig-c">
            <div class="sig-l">Authorized Signatory</div>
            <div style="font-size:10px;margin-top:3px;">For, {{ optional($company)->name ?? config('app.name') }}</div>
        </div>
    </div>

</div>

<script>window.onload = function () { window.print(); };</script>
</body>
</html>
