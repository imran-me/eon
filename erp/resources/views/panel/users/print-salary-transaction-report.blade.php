<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Salary Transaction Report — {{ $employee->name }}</title>
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

    /* ── Employee info / Meta ── */
    .meta-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-bottom: 20px; }
    .bill-to h4 { font-family: var(--tf); color: var(--tc); font-size: 11px; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 5px; }
    .bill-to p { font-size: 13px; line-height: 1.7; margin: 1px 0; }
    .role-badge { display: inline-block; padding: 1px 8px; border-radius: 9px; font-size: 10px; font-weight: 600; background: #e0e7ff; color: #3730a3; margin-right: 3px; margin-top: 4px; }
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

    /* ── Ledger table ── */
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

    /* ── Badges ── */
    .badge { display: inline-block; padding: 1px 7px; border-radius: 9px; font-size: 9px; font-weight: 600; }
    .badge-credit  { background: #dcfce7; color: #166534; }
    .badge-bonus   { background: #f3e8ff; color: #7e22ce; }
    .badge-recon   { background: #dbeafe; color: #1e40af; }
    .badge-opening { background: #f1f5f9; color: #475569; }
    .badge-default { background: #e0e7ff; color: #3730a3; }

    .debit      { color: #dc2626; font-weight: 600; }
    .credit     { color: #16a34a; font-weight: 600; }
    .balance-dr { color: #dc2626; font-weight: 700; }
    .balance-cr { color: #16a34a; font-weight: 700; }

    /* ── Sub-header bar ── */
    .sh { background: var(--thd); color: #fff; padding: 5px 12px; font-family: var(--tf); font-size: 10px; font-weight: 600; margin-bottom: 8px; letter-spacing: .03em; }

    /* ── Bottom grid ── */
    .bot { display: grid; grid-template-columns: 1.3fr 0.7fr; gap: 30px; }
    .nb { font-size: 12px; line-height: 1.8; color: #475569; margin-bottom: 14px; }
    .amt-tbl { width: 100%; border-collapse: collapse; }
    .amt-tbl td { padding: 6px 0; border-bottom: 1px solid #eee; font-size: 12px; }
    .amt-tbl td.n { font-family: var(--nf); font-size: 11px; text-align: right; }
    .amt-tbl .grand td { font-weight: 700; color: var(--tc); font-size: 13px; border-bottom: 2px solid var(--thd); }
    .amt-tbl .dr-row td { color: #dc2626; font-weight: 600; }
    .amt-tbl .cr-row td { color: #16a34a; font-weight: 600; }

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
    <div class="label-bar">Salary Transaction Report</div>

    {{-- Employee info / Meta ── --}}
    <div class="meta-row">
        <div class="bill-to">
            <h4>Employee Details</h4>
            <p><strong>{{ $employee->name }}</strong></p>
            @if($employee->profile->designation ?? null)<p>{{ $employee->profile->designation->name }}</p>@endif
            @if($employee->phone)<p style="font-family:'Space Mono',monospace;font-size:11px;">{{ $employee->phone }}</p>@endif
            @if($employee->email)<p>{{ $employee->email }}</p>@endif
            <div style="margin-top:6px;">
                @if($employee->profile->department->name ?? null)<span class="role-badge">{{ $employee->profile->department->name }}</span>@endif
                @if($employee->company->name ?? null)<span class="role-badge">{{ $employee->company->name }}</span>@endif
            </div>
        </div>
        <div>
            <table class="meta-tbl">
                <tr><td>Employee ID</td><td class="nf">{{ 'EMP-'.str_pad($employee->id,4,'0',STR_PAD_LEFT) }}</td></tr>
                <tr><td>Period</td><td class="nf">All Time</td></tr>
                <tr><td>Generated</td><td class="nf">{{ now()->format('d M Y') }}</td></tr>
                <tr><td>Printed By</td><td>{{ Auth::user()->name }}</td></tr>
            </table>
        </div>
    </div>

    {{-- Summary strip ── --}}
    <div class="summary-strip">
        <div class="sum-card">
            <div class="sum-label">Total Owed (Dr)</div>
            <div class="sum-value" style="color:#dc2626;">৳{{ number_format($totalDebit, 2) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Total Paid / Recovered (Cr)</div>
            <div class="sum-value" style="color:#16a34a;">৳{{ number_format($totalCredit, 2) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">{{ $closingBalance >= 0 ? 'Company Owes' : 'Overpaid' }}</div>
            <div class="sum-value">৳{{ number_format(abs($closingBalance), 2) }}</div>
        </div>
    </div>

    {{-- Ledger table ── --}}
    <table class="ledger">
        <thead>
            <tr>
                <th width="12%">Date</th>
                <th width="14%">Type</th>
                <th width="34%">Detail</th>
                <th class="r" width="13%">Owed to Emp (৳)</th>
                <th class="r" width="13%">Paid / Recovered (৳)</th>
                <th class="r" width="14%">Net Due (৳)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ledgerEntries as $entry)
            @php
                $badgeCls = match(true) {
                    $entry->credit > 0 => 'badge-credit',
                    $entry->type === 'bonus' => 'badge-bonus',
                    $entry->type === 'salary_reconciliation' => 'badge-recon',
                    $entry->type === 'opening_balance' => 'badge-opening',
                    default => 'badge-default',
                };
                $bal = (float) $entry->balance;
            @endphp
            <tr>
                <td class="mono">{{ $entry->entry_date->format('d M y') }}</td>
                <td><span class="badge {{ $badgeCls }}">{{ \Illuminate\Support\Str::headline($entry->type) }}</span></td>
                <td>{{ \Illuminate\Support\Str::limit($entry->reference, 60) }}</td>
                <td class="r {{ $entry->debit > 0 ? 'debit' : '' }}">{{ $entry->debit > 0 ? number_format($entry->debit, 2) : '—' }}</td>
                <td class="r {{ $entry->credit > 0 ? 'credit' : '' }}">{{ $entry->credit > 0 ? number_format($entry->credit, 2) : '—' }}</td>
                <td class="r {{ $bal > 0 ? 'balance-dr' : ($bal < 0 ? 'balance-cr' : '') }}">
                    {{ number_format(abs($bal), 2) }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align:center;padding:20px;color:#94a3b8;">No account activity found for this employee.</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">Closing Balance — {{ now()->format('d M Y') }}</td>
                <td class="r">৳{{ number_format($totalDebit, 2) }}</td>
                <td class="r">৳{{ number_format($totalCredit, 2) }}</td>
                <td class="r">৳{{ number_format(abs($closingBalance), 2) }}</td>
            </tr>
        </tfoot>
    </table>

    {{-- Bottom grid ── --}}
    <div class="bot">
        <div>
            <div class="sh">Balance In Words:</div>
            <div class="nb">
                @php
                    try { $words = ucfirst((new \NumberToWords\NumberToWords())->getNumberTransformer('en')->toWords((int) abs($closingBalance))) . ' Taka'; }
                    catch (\Throwable) { $words = '৳' . number_format(abs($closingBalance), 2); }
                @endphp
                {{ $words }}
                {{ $closingBalance > 0 ? '(Payable to Employee)' : ($closingBalance < 0 ? '(Recoverable from Employee)' : '(Settled)') }}
            </div>

            <div class="sh">Note:</div>
            <div class="nb" style="font-size:11px;color:#64748b;">
                This statement was generated on {{ now()->format('d M Y \a\t H:i') }} and reflects all salary-related
                transactions (salary earned, payments, advances, loans, bonuses) for {{ $employee->name }} from inception to date.
            </div>
        </div>
        <div>
            <div class="sh">Summary:</div>
            <table class="amt-tbl">
                <tr class="dr-row"><td>Total Owed (Dr)</td><td class="n">৳{{ number_format($totalDebit, 2) }}</td></tr>
                <tr class="cr-row"><td>Total Paid / Recovered (Cr)</td><td class="n">৳{{ number_format($totalCredit, 2) }}</td></tr>
                <tr class="grand">
                    <td>{{ $closingBalance >= 0 ? 'Company Owes' : 'Overpaid' }}</td>
                    <td class="n">৳{{ number_format(abs($closingBalance), 2) }}</td>
                </tr>
            </table>
        </div>
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
