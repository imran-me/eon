{{--
    ONE PAYSLIP, as a printable statement — the document handed to the employee.

    Same format as the Party Statement and the loan statement: a browser print
    page with the Back / Print / Download PDF row the print stylesheet hides, the
    employee where the party details sit, and the month's earnings and deductions
    as the ledger.

    The ledger is built so it adds up in front of the employee: earnings first,
    then every deduction, then the net as the closing line. That is the whole
    point of a payslip — a figure they cannot check is a figure they will query.
--}}
@php
    $status = $book->status($slip);
    $due = $book->due($slip);
    $taka = fn ($v) => number_format((float) $v, 2);

    // Earnings and deductions, each with its own line, so the two sides of the
    // slip are legible rather than folded into a pair of totals. Zero lines are
    // dropped: a column of dashes is noise on a document someone has to read.
    $earnings = collect([
        ['Basic / gross salary', (float) ($salary->gross_salary ?? 0)],
        ['Overtime', (float) ($salary->overtime_salary ?? 0)],
        // ?-> not ?:, because the salary row this slip was issued against can
        // have been soft-deleted since, leaving nothing to read a label off.
        [$salary?->bonus_label ?: 'Bonus', (float) ($salary->bonus_amount ?? 0)],
    ])->filter(fn ($r) => $r[1] != 0)->values();

    $deductions = collect([
        ['Loan instalment (EMI)', (float) ($salary->loan_deduction ?? 0)],
        ['Advance salary recovered', (float) ($salary->advance_salary_deduction ?? 0)],
        ['Absence', (float) ($salary->absent_deduction ?? 0)],
        ['Leave', (float) ($salary->leave_deduction ?? 0)],
        ['Late attendance', (float) ($salary->late_deduction ?? 0)],
        ['Early leaving', (float) ($salary->early_leave_deduction ?? 0)],
    ])->filter(fn ($r) => $r[1] != 0)->values();

    $adjustment = (float) ($salary->salary_adjustment ?? 0);

    // What the itemised lines add up to, against what the salary row stores. They
    // agree in every ordinary case; when an older row was hand-edited they may
    // not, and the slip says so rather than printing a total that does not follow
    // from the lines above it.
    $earningsTotal = $earnings->sum(fn ($r) => $r[1]);
    $deductionsTotal = $deductions->sum(fn ($r) => $r[1]);
    $storedDeductions = (float) ($salary->total_deductions ?? 0);
    $derivedNet = round($earningsTotal + $adjustment - $deductionsTotal, 2);
    $storedNet = round((float) ($salary->net_salary ?? 0), 2);
    $reconciles = abs($derivedNet - $storedNet) < 0.51;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payslip — {{ $slip->user?->name }} · {{ $book->periodLabel($book->period($slip)) }}</title>
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

    .card { max-width: 794px; margin: auto; background: #fff; padding: 40px; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,.06); }

    .hdr { display: flex; justify-content: space-between; align-items: center; gap: 24px; margin-bottom: 24px; }
    .co-info { text-align: right; font-size: 12px; line-height: 1.7; }
    .co-info strong { font-family: var(--tf); font-size: 16px; display: block; }
    .logo-txt { font-family: var(--tf); color: var(--tc); font-size: 26px; font-weight: 800; }

    .label-bar {
        text-align: center; background: var(--tbg); color: var(--tc);
        text-transform: uppercase; letter-spacing: 2px; font-weight: 700;
        font-family: var(--tf); font-size: 11px; padding: 10px;
        border-top: 2px solid var(--tc); margin-bottom: 24px;
    }

    .meta-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-bottom: 20px; }
    .bill-to h4 { font-family: var(--tf); color: var(--tc); font-size: 11px; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 5px; }
    .bill-to p { font-size: 13px; line-height: 1.7; margin: 1px 0; }
    .role-badge { display: inline-block; padding: 1px 8px; border-radius: 9px; font-size: 10px; font-weight: 600; background: #e0e7ff; color: #3730a3; margin-right: 3px; margin-top: 4px; }
    .role-badge.good { background: #dcfce7; color: #15803d; }
    .role-badge.warn { background: #fef3c7; color: #b45309; }
    .role-badge.info { background: #dbeafe; color: #1d4ed8; }
    .meta-tbl td { padding: 2px 0 2px 18px; font-weight: 600; font-size: 12px; }
    .meta-tbl td.k { font-weight: 400; color: #64748b; }
    .meta-tbl .nf { font-family: var(--nf); font-size: 11px; }

    .summary-strip { display: flex; gap: 0; border: 1px solid var(--bdr); border-radius: 6px; overflow: hidden; margin-bottom: 20px; }
    .sum-card { flex: 1; padding: 10px 14px; border-right: 1px solid var(--bdr); }
    .sum-card:last-child { border-right: none; background: var(--thd); }
    .sum-label { font-size: 9px; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; font-weight: 600; margin-bottom: 3px; }
    .sum-card:last-child .sum-label { color: #c7d2fe; }
    .sum-value { font-family: var(--nf); font-size: 12px; font-weight: 700; color: var(--txt); }
    .sum-card:last-child .sum-value { color: #fff; font-size: 13px; }

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
    .ledger tbody tr.section td {
        background: var(--tbg); font-weight: 700; font-family: var(--tf);
        font-size: 9px; text-transform: uppercase; letter-spacing: .05em; color: var(--tc);
    }
    .ledger tbody tr.subtotal td { font-weight: 700; border-bottom: 1px solid #cbd5e1; }
    .ledger tfoot tr { background: var(--thd); color: #fff; }
    .ledger tfoot td { padding: 9px 10px; font-weight: 700; border-bottom: none; font-family: var(--tf); font-size: 11px; }
    .ledger tfoot td.r { text-align: right; font-family: var(--nf); font-size: 12px; }

    .credit  { color: #16a34a; font-weight: 600; }
    .debit   { color: #dc2626; font-weight: 600; }

    .amt-tbl { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    .amt-tbl td { padding: 6px 0; border-bottom: 1px solid #eee; font-size: 12px; }
    .amt-tbl td.n { font-family: var(--nf); font-size: 11px; text-align: right; }
    .amt-tbl td.k { color: #64748b; }
    .amt-tbl .grand td { font-weight: 700; color: var(--tc); font-size: 13px; border-bottom: 2px solid var(--thd); }

    .sh { background: var(--thd); color: #fff; padding: 5px 12px; font-family: var(--tf); font-size: 10px; font-weight: 600; margin-bottom: 8px; letter-spacing: .03em; }
    .nb { font-size: 11px; line-height: 1.8; color: #64748b; margin-bottom: 14px; }
    .warn-note { background: #fffbeb; border-left: 3px solid #f59e0b; padding: 8px 12px; font-size: 11px; color: #92400e; margin-bottom: 14px; }

    .sig { margin-top: 36px; display: flex; justify-content: space-between; gap: 24px; }
    .sig-c { text-align: center; width: 200px; }
    .sig-l { border-top: 1px solid #333; margin-top: 6px; padding-top: 4px; font-size: 10px; font-weight: 700; font-family: var(--tf); }

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
    <button onclick="window.print()" style="background:#fff;color:#4f46e5;border:1px solid #4f46e5;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">📄 Download PDF</button>
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
    <div class="label-bar">Payslip — {{ $book->periodLabel($book->period($slip)) }}</div>

    {{-- Employee / Meta ── --}}
    <div class="meta-row">
        <div class="bill-to">
            <h4>Employee Details</h4>
            <p><strong>{{ $slip->user?->name ?? 'Employee #' . $slip->user_id }}</strong></p>
            @if($slip->user?->employee_id_no)
                <p style="font-family:'Space Mono',monospace;font-size:11px;">{{ $slip->user->employee_id_no }}</p>
            @endif
            @if($slip->user?->phone)<p>{{ $slip->user->phone }}</p>@endif
            @if($slip->user?->company)<p>{{ $slip->user->company->name }}</p>@endif
            <div style="margin-top:6px;">
                <span class="role-badge {{ $status === 'paid' ? 'good' : ($status === 'partial' ? 'warn' : 'info') }}">
                    {{ ucfirst($status) }}
                </span>
            </div>
        </div>
        <div>
            <table class="meta-tbl">
                <tr><td class="k">Payslip No.</td><td class="nf">{{ $slip->payslip_number ?: 'PS-' . str_pad($slip->id, 4, '0', STR_PAD_LEFT) }}</td></tr>
                <tr><td class="k">Pay Period</td><td class="nf">{{ $book->periodLabel($book->period($slip)) }}</td></tr>
                <tr><td class="k">Issued On</td><td class="nf">{{ $slip->issue_date ? \Carbon\Carbon::parse($slip->issue_date)->format('d M Y') : '—' }}</td></tr>
                <tr><td class="k">Paid Via</td><td>{{ $salary?->bank?->name ?: ($salary?->payment_method ?: '—') }}</td></tr>
                <tr><td class="k">Printed By</td><td>{{ Auth::user()->name }}</td></tr>
            </table>
        </div>
    </div>

    {{-- Summary strip ──
         "Net Payable" takes the filled card: it is the figure the employee reads
         the slip to find. --}}
    <div class="summary-strip">
        <div class="sum-card">
            <div class="sum-label">Gross</div>
            <div class="sum-value">৳{{ $taka($salary?->gross_salary ?? 0) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Deductions</div>
            <div class="sum-value" style="color:#dc2626;">৳{{ $taka($storedDeductions) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Still Due</div>
            <div class="sum-value">৳{{ $taka($due) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Net Payable</div>
            <div class="sum-value">৳{{ $taka($storedNet) }}</div>
        </div>
    </div>

    {{-- Earnings & deductions ── --}}
    <table class="ledger">
        <thead>
            <tr>
                <th width="58%">Description</th>
                <th class="r" width="21%">Earnings (৳)</th>
                <th class="r" width="21%">Deductions (৳)</th>
            </tr>
        </thead>
        <tbody>
            <tr class="section"><td colspan="3">Earnings</td></tr>
            @foreach($earnings as [$label, $amount])
                <tr>
                    <td>{{ $label }}</td>
                    <td class="r mono credit">{{ $taka($amount) }}</td>
                    <td class="r">—</td>
                </tr>
            @endforeach
            @if($adjustment != 0)
                <tr>
                    <td>Salary adjustment</td>
                    <td class="r mono {{ $adjustment > 0 ? 'credit' : '' }}">{{ $adjustment > 0 ? $taka($adjustment) : '—' }}</td>
                    <td class="r mono {{ $adjustment < 0 ? 'debit' : '' }}">{{ $adjustment < 0 ? $taka(abs($adjustment)) : '—' }}</td>
                </tr>
            @endif
            <tr class="subtotal">
                <td>Total earnings</td>
                <td class="r mono credit">{{ $taka($earningsTotal + max(0, $adjustment)) }}</td>
                <td class="r">—</td>
            </tr>

            <tr class="section"><td colspan="3">Deductions</td></tr>
            @forelse($deductions as [$label, $amount])
                <tr>
                    <td>{{ $label }}</td>
                    <td class="r">—</td>
                    <td class="r mono debit">{{ $taka($amount) }}</td>
                </tr>
            @empty
                <tr>
                    <td>No deductions this month</td>
                    <td class="r">—</td>
                    <td class="r mono">0.00</td>
                </tr>
            @endforelse
            <tr class="subtotal">
                <td>Total deductions</td>
                <td class="r">—</td>
                <td class="r mono debit">{{ $taka($storedDeductions) }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td>Net Payable</td>
                <td class="r" colspan="2">৳{{ $taka($storedNet) }}</td>
            </tr>
        </tfoot>
    </table>

    @unless($reconciles)
        <div class="warn-note">
            The itemised lines above come to ৳{{ $taka($derivedNet) }}, while this salary record stores a
            net of ৳{{ $taka($storedNet) }}. The stored figure is what was paid; the difference is an
            adjustment made directly on the salary record rather than through a line item.
        </div>
    @endunless

    {{-- Payment ── --}}
    <div class="sh">Payment</div>
    <table class="amt-tbl">
        <tr>
            <td class="k">Net payable</td>
            <td class="n">৳{{ $taka($storedNet) }}</td>
        </tr>
        <tr>
            <td class="k">Paid so far</td>
            <td class="n">৳{{ $taka($storedNet - $due) }}</td>
        </tr>
        <tr>
            <td class="k">Payment status</td>
            <td class="n">{{ ucfirst($status) }}</td>
        </tr>
        @if($salary?->scheduled_date)
            <tr>
                <td class="k">Scheduled for</td>
                <td class="n">{{ \Carbon\Carbon::parse($salary->scheduled_date)->format('d M Y') }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td>Still Due</td>
            <td class="n">৳{{ $taka($due) }}</td>
        </tr>
    </table>

    {{-- Note ── --}}
    <div class="sh">Note:</div>
    <div class="nb">
        This payslip covers {{ $book->periodLabel($book->period($slip)) }} and was generated on
        {{ now()->format('d M Y \a\t H:i') }}.
        @if($salary?->notes) {{ $salary->notes }} @endif
        Deductions shown are those applied to this month's salary; a loan instalment reduces the loan
        balance and appears again on that loan's own statement.
        System-generated payslip — Confidential.
    </div>

    {{-- Signature ── --}}
    <div class="sig">
        <div class="sig-c">
            <div class="sig-l">Employee Signature</div>
            <div style="font-size:10px;margin-top:3px;">{{ $slip->user?->name }}</div>
        </div>
        <div class="sig-c">
            <div class="sig-l">Authorized Signatory</div>
            <div style="font-size:10px;margin-top:3px;">For, {{ optional($company)->name ?? config('app.name') }}</div>
        </div>
    </div>

</div>
</body>
</html>
