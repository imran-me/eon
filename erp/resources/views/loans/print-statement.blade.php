{{--
    ONE LOAN, as a printable statement — the thing you hand an employee who asks
    what is left on their loan, or file when one is settled.

    Same format as the Party Statement (party-statement/print-statement): a
    browser print page with the Back / Print / Download PDF row the print
    stylesheet hides, the borrower where the party details sit, and the running
    balance in the column where the ledger balance sits.

    Portrait, like the party statement it follows: a single loan is a handful of
    facts and a payment list, which reads better down the page than across it.

    "Balance" is walked forward from whatever had already been repaid before
    movements were recorded, so the last row lands exactly on the Still Due figure
    in the summary strip. It is deliberately not totalled — a column of balances
    at different moments has no meaningful sum, so the foot carries the closing
    balance instead.
--}}
@php
    $via = $loan->repaidByMethod();
    $openingPaid = (float) $loan->opening_paid_amount;
    $payments = $loan->payment_rows;
    $running = $openingPaid;
    $taka = fn ($v) => number_format((float) $v, 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Loan Statement — {{ $loan->user?->name }}</title>
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
    .hdr { display: flex; justify-content: space-between; align-items: center; gap: 24px; margin-bottom: 24px; }
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

    /* ── Borrower / Meta ── */
    .meta-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-bottom: 20px; }
    .bill-to h4 { font-family: var(--tf); color: var(--tc); font-size: 11px; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 5px; }
    .bill-to p { font-size: 13px; line-height: 1.7; margin: 1px 0; }
    .role-badge { display: inline-block; padding: 1px 8px; border-radius: 9px; font-size: 10px; font-weight: 600; background: #e0e7ff; color: #3730a3; margin-right: 3px; margin-top: 4px; }
    .role-badge.good { background: #dcfce7; color: #15803d; }
    .role-badge.warn { background: #fef3c7; color: #b45309; }
    .meta-tbl td { padding: 2px 0 2px 18px; font-weight: 600; font-size: 12px; }
    .meta-tbl td.k { font-weight: 400; color: #64748b; }
    .meta-tbl .nf { font-family: var(--nf); font-size: 11px; }

    /* ── Summary strip ── */
    .summary-strip { display: flex; gap: 0; border: 1px solid var(--bdr); border-radius: 6px; overflow: hidden; margin-bottom: 20px; }
    .sum-card { flex: 1; padding: 10px 14px; border-right: 1px solid var(--bdr); }
    .sum-card:last-child { border-right: none; background: var(--thd); }
    .sum-label { font-size: 9px; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; font-weight: 600; margin-bottom: 3px; }
    .sum-card:last-child .sum-label { color: #c7d2fe; }
    .sum-value { font-family: var(--nf); font-size: 12px; font-weight: 700; color: var(--txt); }
    .sum-card:last-child .sum-value { color: #fff; font-size: 13px; }

    /* ── Ledger ── */
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
    .ledger tbody tr.opening-row td { background: var(--tbg); font-weight: 600; }
    .ledger tfoot tr { background: var(--thd); color: #fff; }
    .ledger tfoot td { padding: 8px 10px; font-weight: 700; border-bottom: none; font-family: var(--tf); font-size: 10px; }
    .ledger tfoot td.r { text-align: right; font-family: var(--nf); }
    .ledger thead { display: table-header-group; }
    .ledger tr { page-break-inside: avoid; }

    .credit  { color: #16a34a; font-weight: 600; }
    .debit   { color: #dc2626; font-weight: 600; }

    /* ── Terms ── */
    .amt-tbl { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    .amt-tbl td { padding: 6px 0; border-bottom: 1px solid #eee; font-size: 12px; }
    .amt-tbl td.n { font-family: var(--nf); font-size: 11px; text-align: right; }
    .amt-tbl td.k { color: #64748b; }
    .amt-tbl .grand td { font-weight: 700; color: var(--tc); font-size: 13px; border-bottom: 2px solid var(--thd); }

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
    <div class="label-bar">Staff Loan Statement</div>

    {{-- Borrower / Meta ── --}}
    <div class="meta-row">
        <div class="bill-to">
            <h4>Borrower Details</h4>
            <p><strong>{{ $loan->user?->name ?? 'Employee #' . $loan->user_id }}</strong></p>
            @if($loan->user?->employee_id_no)
                <p style="font-family:'Space Mono',monospace;font-size:11px;">{{ $loan->user->employee_id_no }}</p>
            @endif
            @if($loan->user?->phone)<p>{{ $loan->user->phone }}</p>@endif
            @if($loan->user?->company)<p>{{ $loan->user->company->name }}</p>@endif
            <div style="margin-top:6px;">
                <span class="role-badge {{ $loan->is_cleared ? 'good' : 'warn' }}">
                    {{ $loan->is_cleared ? 'Cleared' : 'Running' }}
                </span>
                @if($loan->monthly_deduction > 0)
                    <span class="role-badge">EMI ৳{{ number_format($loan->monthly_deduction) }}/mo</span>
                @endif
            </div>
        </div>
        <div>
            <table class="meta-tbl">
                <tr><td class="k">Loan ID</td><td class="nf">{{ 'LN-' . str_pad($loan->id, 4, '0', STR_PAD_LEFT) }}</td></tr>
                <tr>
                    <td class="k">Taken On</td>
                    <td class="nf">{{ $loan->start_date ? \Carbon\Carbon::parse($loan->start_date)->format('d M Y') : '—' }}</td>
                </tr>
                <tr>
                    <td class="k">{{ $loan->is_cleared ? 'Cleared On' : 'Runs Until' }}</td>
                    <td class="nf">
                        @if($loan->is_cleared)
                            {{ $loan->cleared_on ? \Carbon\Carbon::parse($loan->cleared_on)->format('d M Y') : '—' }}
                        @else
                            {{ $loan->end_date ? \Carbon\Carbon::parse($loan->end_date)->format('d M Y') : 'no end date' }}
                        @endif
                    </td>
                </tr>
                <tr><td class="k">Generated</td><td class="nf">{{ now()->format('d M Y') }}</td></tr>
                <tr><td class="k">Printed By</td><td>{{ Auth::user()->name }}</td></tr>
            </table>
        </div>
    </div>

    {{-- Summary strip ──
         "Still Due" takes the filled card: it is the figure the whole statement
         is written to answer. --}}
    <div class="summary-strip">
        <div class="sum-card">
            <div class="sum-label">Loan Taken</div>
            <div class="sum-value">৳{{ $taka($loan->amount) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Paid So Far</div>
            <div class="sum-value" style="color:#16a34a;">৳{{ $taka($loan->paid_amount) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Repaid</div>
            <div class="sum-value">{{ $loan->progress_pct }}%</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Still Due</div>
            <div class="sum-value">৳{{ $taka($loan->outstanding) }}</div>
        </div>
    </div>

    {{-- Payment ledger ── --}}
    <table class="ledger">
        <thead>
            <tr>
                <th width="12%">Paid On</th>
                <th width="24%">How</th>
                <th width="30%">Note</th>
                <th class="r" width="17%">Paid (৳)</th>
                <th class="r" width="17%">Balance (৳)</th>
            </tr>
        </thead>
        <tbody>
            {{-- The disbursement opens the ledger, the way the party statement
                 opens with the balance brought forward: without it the first
                 payment appears to reduce a debt that was never taken on. --}}
            <tr class="opening-row">
                <td>{{ $loan->start_date ? \Carbon\Carbon::parse($loan->start_date)->format('d M y') : '—' }}</td>
                <td colspan="2">Loan disbursed{{ $loan->bank?->name ? ' — ' . $loan->bank->name : '' }}</td>
                <td class="r">—</td>
                <td class="r mono debit">{{ $taka((float) $loan->amount - $openingPaid) }}</td>
            </tr>

            @forelse($payments as $payment)
                @php
                    $running = round($running + (float) $payment->amount, 2);
                    $balance = max(0, round((float) $loan->amount - $running, 2));
                @endphp
                <tr>
                    <td class="mono">{{ \Carbon\Carbon::parse($payment->date)->format('d M y') }}</td>
                    <td>{{ $payment->method === 'salary' ? 'Salary deduction' : ($payment->bank?->name ?: 'Cash / bank') }}</td>
                    <td>{{ $payment->note ?: '—' }}</td>
                    <td class="r mono credit">{{ $taka($payment->amount) }}</td>
                    <td class="r mono {{ $balance > 0 ? 'debit' : 'credit' }}">{{ $taka($balance) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center;padding:20px;color:#94a3b8;">
                        Nothing has been repaid on this loan yet.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">Closing Balance</td>
                <td class="r">{{ $taka($payments->sum(fn ($p) => (float) $p->amount)) }}</td>
                <td class="r">{{ $taka($loan->outstanding) }}</td>
            </tr>
        </tfoot>
    </table>

    {{-- Terms ── --}}
    <div class="sh">Loan Details</div>
    <table class="amt-tbl">
        <tr>
            <td class="k">Disbursed from</td>
            <td class="n">{{ $loan->bank?->name ?: 'Cash / not specified' }}</td>
        </tr>
        <tr>
            <td class="k">Repayment plan</td>
            <td class="n">
                @if($loan->emi_months)
                    {{ $loan->emi_months }} months · ৳{{ number_format($loan->monthly_deduction) }}/mo
                @elseif($loan->monthly_deduction > 0)
                    ৳{{ number_format($loan->monthly_deduction) }} from every salary
                @else
                    None — repaid by hand
                @endif
            </td>
        </tr>
        <tr>
            <td class="k">Instalments left</td>
            <td class="n">{{ $loan->instalments_left ?: ($loan->outstanding > 0 ? 'no plan' : '—') }}</td>
        </tr>
        <tr>
            <td class="k">Recovered from salary</td>
            <td class="n">৳{{ $taka($via['salary']) }}</td>
        </tr>
        <tr>
            <td class="k">Repaid in cash / bank</td>
            <td class="n">৳{{ $taka($via['cash']) }}</td>
        </tr>
        @if($openingPaid > 0)
            <tr>
                <td class="k">Repaid before individual payments were recorded</td>
                <td class="n">৳{{ $taka($openingPaid) }}</td>
            </tr>
        @endif
        <tr>
            <td class="k">Last payment</td>
            <td class="n">{{ $loan->last_paid_on ? \Carbon\Carbon::parse($loan->last_paid_on)->format('d M Y') : 'none yet' }}</td>
        </tr>
        <tr class="grand">
            <td>Still Due</td>
            <td class="n">৳{{ $taka($loan->outstanding) }}</td>
        </tr>
    </table>

    {{-- Note ── --}}
    <div class="sh">Note:</div>
    <div class="nb">
        This statement was generated on {{ now()->format('d M Y \a\t H:i') }}.
        @if($openingPaid > 0)
            ৳{{ $taka($openingPaid) }} of this loan was already repaid before individual payments were
            recorded, so the opening balance above starts from there.
        @endif
        The Balance column is what was left immediately after each payment; the closing balance is
        this loan's position today, not a total of the column above it.
        System-generated staff loan statement — Confidential.
    </div>

    {{-- Signature ── --}}
    <div class="sig">
        <div class="sig-c">
            <div class="sig-l">Authorized Signatory</div>
            <div style="font-size:10px;margin-top:3px;">For, {{ optional($company)->name ?? config('app.name') }}</div>
        </div>
    </div>

</div>
</body>
</html>
