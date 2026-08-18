{{--
    Printable expense report.

    Standalone on purpose — no @extends('layout.app'). Same pattern as
    expenses/print.blade.php and party-statement/print-statement.blade.php: the
    page owns its whole document, so the sidebar, header and tab bar simply are
    not there to print. Opened in a new tab and prints itself on load.

    The look is the shared one — A4 card at 794px, indigo letterhead, label bar,
    summary strip, signature — so this, the expense list, the petty cash
    statement and the party statement all read as documents from one system
    rather than four screens that happened to be printed.

    What is NOT shared is the content: this is an analysis, not a list, so it
    keeps its three sections (breakdown, daily trend, ledger) and its share bars.
    Those bars are plain divs on purpose; a chart engine would need JavaScript to
    have finished before window.print() fires, and a half-drawn canvas prints
    blank.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Expense Report — {{ $periodLabel }}</title>
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
        /* Without these the browser drops every background in the printout,
           which would flatten the summary strip, badges and share bars into
           blank boxes. */
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

    /* ── Filters / Meta ── */
    .meta-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-bottom: 20px; }
    .bill-to h4 { font-family: var(--tf); color: var(--tc); font-size: 11px; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 5px; }
    .bill-to p { font-size: 13px; line-height: 1.7; margin: 1px 0; }
    .role-badge { display: inline-block; padding: 1px 8px; border-radius: 9px; font-size: 10px; font-weight: 600; background: #e0e7ff; color: #3730a3; margin-right: 3px; margin-top: 4px; }
    .meta-tbl td { padding: 2px 0 2px 18px; font-weight: 600; font-size: 12px; }
    .meta-tbl .nf { font-family: var(--nf); font-size: 11px; }

    /* ── Summary strip ── */
    .summary-strip { display: flex; gap: 0; border: 1px solid var(--bdr); border-radius: 6px; overflow: hidden; margin-bottom: 20px; }
    .sum-card { flex: 1; padding: 10px 12px; border-right: 1px solid var(--bdr); }
    .sum-card:last-child { border-right: none; }
    .sum-card.hero { background: var(--thd); }
    .sum-label { font-size: 9px; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; font-weight: 600; margin-bottom: 3px; }
    .sum-card.hero .sum-label { color: #c7d2fe; }
    .sum-value { font-family: var(--nf); font-size: 12px; font-weight: 700; color: var(--txt); }
    .sum-card.hero .sum-value { color: #fff; font-size: 13px; }
    .sum-note { font-size: 9px; color: #94a3b8; margin-top: 2px; }
    .sum-card.hero .sum-note { color: #c7d2fe; }

    /* ── Sub-header bar ── */
    .sh { background: var(--thd); color: #fff; padding: 5px 12px; font-family: var(--tf); font-size: 10px; font-weight: 600; margin: 20px 0 8px; letter-spacing: .03em; }

    /* ── Tables ── */
    .ledger { width: 100%; border-collapse: collapse; margin-bottom: 4px; font-size: 11px; }
    .ledger thead { display: table-header-group; }   /* repeat the header across pages */
    .ledger tr { page-break-inside: avoid; }
    .ledger th {
        background: var(--thd); color: #fff;
        padding: 8px 10px; text-align: left;
        font-family: var(--tf); font-size: 9px;
        text-transform: uppercase; letter-spacing: .04em;
    }
    .ledger th.r { text-align: right; }
    .ledger td { padding: 7px 10px; border-bottom: 1px solid var(--bdr); vertical-align: top; }
    .ledger td.r    { text-align: right; }
    .ledger td.mono { font-family: var(--nf); font-size: 10px; }
    .ledger tbody tr:nth-child(even) td { background: #f8f9ff; }
    .ledger tfoot tr { background: var(--thd); color: #fff; }
    .ledger tfoot td { padding: 8px 10px; font-weight: 700; border-bottom: none; font-family: var(--tf); font-size: 10px; }
    .ledger tfoot td.r { text-align: right; font-family: var(--nf); }

    .muted { color: #64748b; font-size: 9.5px; display: block; margin-top: 2px; }

    /* ── Badges ── */
    .badge { display: inline-block; padding: 1px 7px; border-radius: 9px; font-size: 9px; font-weight: 600; white-space: nowrap; }
    .badge-own   { background: #ede9fe; color: #5b21b6; }
    .badge-petty { background: #ccfbf1; color: #0f766e; }
    .badge-bank  { background: #dbeafe; color: #1e40af; }
    .badge-cash  { background: #f1f5f9; color: #475569; }
    .badge-on    { background: #dcfce7; color: #166534; }
    .badge-off   { background: #fef3c7; color: #92400e; }

    /* ── Share bar: reads at a glance without a chart engine ── */
    .share { height: 7px; background: #eef2f7; border-radius: 4px; overflow: hidden; width: 100%; margin-top: 3px; }
    .share i { display: block; height: 100%; background: var(--thd); }

    .empty { padding: 22px; text-align: center; color: #94a3b8; font-style: italic; border: 1px dashed var(--bdr); border-radius: 5px; }

    .cap-note {
        margin-top: 8px; padding: 6px 9px; background: #fffbeb;
        border: 1px solid #fde68a; border-radius: 4px; color: #92400e; font-size: 9.5px;
    }

    /* ── Bottom grid ── */
    .bot { display: grid; grid-template-columns: 1.3fr 0.7fr; gap: 30px; margin-top: 24px; }
    .nb { font-size: 12px; line-height: 1.8; color: #475569; margin-bottom: 14px; }
    .amt-tbl { width: 100%; border-collapse: collapse; }
    .amt-tbl td { padding: 6px 0; border-bottom: 1px solid #eee; font-size: 12px; }
    .amt-tbl td.n { font-family: var(--nf); font-size: 11px; text-align: right; }
    .amt-tbl .grand td { font-weight: 700; color: var(--tc); font-size: 13px; border-bottom: 2px solid var(--thd); }

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
        .sh        { margin: 14px 0 6px; }
        .sig       { margin-top: 24px; }
    }
</style>
</head>
<body>

@php
    $total = (float) $summary['total_amount'];
    $pct = fn ($amount) => $total > 0 ? round($amount / $total * 100, 1) : 0;

    /* Where the money actually left, in the precedence the posting uses — the
       claim outranks a float, a float outranks a bank, the drawer is what is
       left. Same order as ExpenseController::settlementAccountId(), so this can
       never name a source the ledger disagrees with. */
    $sourceTag = function ($expense) {
        if ($expense->reimburse_to_user_id) return ['badge-own',   'Own Pocket'];
        if ($expense->petty_cash_float_id)  return ['badge-petty', 'Petty Cash'];
        if ($expense->bank_id)              return ['badge-bank',  $expense->bank?->name ?: 'Bank'];
        return ['badge-cash', 'Cash in Hand'];
    };

    $delta = $summary['change_pct'];
@endphp

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
            @if (optional($company)->logo)
                @php $logoSrc = file_exists(public_path($company->logo)) ? asset($company->logo) : asset('logo.png'); @endphp
                <img src="{{ $logoSrc }}" alt="" style="max-width:80px;">
            @else
                <div class="logo-txt">{{ optional($company)->name ?? config('app.name') }}</div>
            @endif
        </div>
        <div class="co-info">
            <strong>{{ optional($company)->name ?? config('app.name') }}</strong>
            @if (optional($company)->address){{ $company->address }}<br>@endif
            {{ optional($company)->phone }}{{ optional($company)->phone && optional($company)->email ? ' | ' : '' }}{{ optional($company)->email }}
        </div>
    </div>

    {{-- Label bar ── --}}
    <div class="label-bar">Expense Report — by {{ $groupMeta['label'] }}</div>

    {{-- Scope / Meta ── --}}
    <div class="meta-row">
        <div class="bill-to">
            <h4>Scope</h4>
            <p><strong>{{ $scopeCompany?->name ?? 'All Companies' }}</strong></p>
            @if (count($activeFilters))
                <div style="margin-top:2px;">
                    @foreach ($activeFilters as $chip)
                        <span class="role-badge">{{ $chip['label'] }}: {{ $chip['value'] }}</span>
                    @endforeach
                </div>
            @else
                {{-- Said out loud rather than left blank: "no filters" and "a
                     filter I forgot to print" look identical on paper. --}}
                <p style="font-size:11px;color:#64748b;">No filters beyond the period.</p>
            @endif
        </div>
        <div>
            <table class="meta-tbl">
                <tr><td>Period</td><td class="nf">{{ $periodLabel }}</td></tr>
                <tr><td>Covering</td><td class="nf">{{ $from->format('d M Y') }} → {{ $to->format('d M Y') }}</td></tr>
                <tr><td>Generated</td><td class="nf">{{ now()->format('d M Y, h:i A') }}</td></tr>
                <tr><td>Printed By</td><td>{{ auth()->user()->name }}</td></tr>
            </table>
        </div>
    </div>

    {{-- Headline figures ── --}}
    <div class="summary-strip">
        <div class="sum-card hero">
            <div class="sum-label">Total Expense</div>
            <div class="sum-value">৳{{ number_format($summary['total_amount'], 2) }}</div>
            <div class="sum-note">
                @if (is_null($delta))
                    No spend in previous period
                @else
                    {{ $delta > 0 ? '▲' : ($delta < 0 ? '▼' : '■') }} {{ number_format(abs($delta), 1) }}% vs previous
                @endif
            </div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Transactions</div>
            <div class="sum-value">{{ number_format($summary['total_expenses']) }}</div>
            <div class="sum-note">Avg ৳{{ number_format($summary['average_amount'], 2) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Via Petty Cash</div>
            <div class="sum-value">৳{{ number_format($summary['petty_amount'], 2) }}</div>
            <div class="sum-note">{{ $pct($summary['petty_amount']) }}% of total</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Via Bank</div>
            <div class="sum-value">৳{{ number_format($summary['bank_amount'], 2) }}</div>
            <div class="sum-note">{{ $pct($summary['bank_amount']) }}% of total</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Cash in Hand</div>
            <div class="sum-value">৳{{ number_format($summary['cash_amount'], 2) }}</div>
            <div class="sum-note">{{ $pct($summary['cash_amount']) }}% of total</div>
        </div>
    </div>

    {{-- Breakdown ── --}}
    <div class="sh">Breakdown by {{ $groupMeta['label'] }}</div>

    @if ($groupRows->isNotEmpty())
        <table class="ledger">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th width="42%">{{ $groupMeta['label'] }}</th>
                    <th class="r" width="10%">Txns</th>
                    <th class="r" width="18%">Amount (৳)</th>
                    <th class="r" width="9%">%</th>
                    <th width="16%">Share</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($groupRows as $index => $row)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $row->name }}</td>
                        <td class="r mono">{{ number_format($row->count) }}</td>
                        <td class="r mono">{{ number_format($row->amount, 2) }}</td>
                        <td class="r mono">{{ $pct($row->amount) }}%</td>
                        <td><div class="share"><i style="width: {{ $pct($row->amount) }}%"></i></div></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2">Total</td>
                    <td class="r">{{ number_format($groupRows->sum('count')) }}</td>
                    <td class="r">৳{{ number_format($groupRows->sum('amount'), 2) }}</td>
                    <td class="r">100%</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @else
        <div class="empty">No expenses matched this period and these filters.</div>
    @endif

    {{-- Daily trend, only when the window spans more than a day ── --}}
    @if ($timeline->isNotEmpty())
        <div class="sh">Daily Trend</div>
        <table class="ledger">
            <thead>
                <tr>
                    <th width="42%">Date</th>
                    <th class="r" width="12%">Txns</th>
                    <th class="r" width="22%">Amount (৳)</th>
                    <th width="24%">Share of Period</th>
                </tr>
            </thead>
            <tbody>
                @php $maxDay = (float) ($timeline->max('amount') ?: 0); @endphp
                @foreach ($timeline as $day)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($day['date'])->format('d M Y (D)') }}</td>
                        <td class="r mono">{{ number_format($day['count']) }}</td>
                        <td class="r mono">{{ number_format($day['amount'], 2) }}</td>
                        <td><div class="share"><i style="width: {{ $maxDay > 0 ? round($day['amount'] / $maxDay * 100, 2) : 0 }}%"></i></div></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td>Total</td>
                    <td class="r">{{ number_format($timeline->sum('count')) }}</td>
                    <td class="r">৳{{ number_format($timeline->sum('amount'), 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @endif

    {{-- Ledger ── --}}
    <div class="sh">Expense Ledger</div>

    @if ($expenses->isNotEmpty())
        <table class="ledger">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th width="10%">Date</th>
                    <th width="27%">Title</th>
                    <th width="25%">Classification</th>
                    <th width="15%">Paid From</th>
                    <th class="r" width="18%">Amount (৳)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($expenses as $index => $expense)
                    @php [$tagClass, $tagText] = $sourceTag($expense); @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td class="mono">{{ \Carbon\Carbon::parse($expense->expense_date)->format('d M y') }}</td>
                        <td>
                            {{ $expense->title }}
                            @if ($expense->reference)
                                <span class="muted">Ref: {{ $expense->reference }}</span>
                            @endif
                        </td>
                        <td class="muted" style="margin-top:0;">{{ $expense->classification_path ?: '—' }}</td>
                        <td><span class="badge {{ $tagClass }}">{{ $tagText }}</span></td>
                        <td class="r">
                            <span class="mono" style="font-weight:700;">{{ number_format($expense->amount, 2) }}</span>
                            <span class="muted">
                                <span class="badge {{ $expense->status ? 'badge-on' : 'badge-off' }}">{{ $expense->status ? 'Active' : 'Inactive' }}</span>
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5">
                        Total of {{ number_format($expenses->count()) }} listed record{{ $expenses->count() === 1 ? '' : 's' }}
                    </td>
                    <td class="r">৳{{ number_format($expenses->sum('amount'), 2) }}</td>
                </tr>
            </tfoot>
        </table>

        @if ($ledgerTotal > $expenses->count())
            <div class="cap-note">
                This ledger lists the {{ number_format($expenses->count()) }} most recent of
                {{ number_format($ledgerTotal) }} records. The summary and breakdown above cover all
                {{ number_format($ledgerTotal) }} — only this list is shortened. Narrow the period or
                filters to print the remainder.
            </div>
        @endif
    @else
        <div class="empty">No expense records in this period.</div>
    @endif

    {{-- Bottom grid ── --}}
    <div class="bot">
        <div>
            <div class="sh" style="margin-top:0;">Total In Words:</div>
            <div class="nb">
                @php
                    try { $words = ucfirst((new \NumberToWords\NumberToWords())->getNumberTransformer('en')->toWords((int) round($total))) . ' Taka'; }
                    catch (\Throwable) { $words = '৳' . number_format($total, 2); }
                @endphp
                {{ $words }} only
            </div>

            <div class="sh" style="margin-top:0;">Note:</div>
            <div class="nb" style="font-size:11px;color:#64748b;">
                Covers {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }},
                {{ $scopeCompany ? 'for ' . $scopeCompany->name : 'across every company' }}.
                The breakdown and the headline figures are computed over the whole period;
                only the ledger below them can be shortened, and it says so when it is.
            </div>
        </div>
        <div>
            <div class="sh" style="margin-top:0;">Summary:</div>
            <table class="amt-tbl">
                <tr><td>Petty Cash</td><td class="n">৳{{ number_format($summary['petty_amount'], 2) }}</td></tr>
                <tr><td>Bank</td><td class="n">৳{{ number_format($summary['bank_amount'], 2) }}</td></tr>
                <tr><td>Cash in Hand</td><td class="n">৳{{ number_format($summary['cash_amount'], 2) }}</td></tr>
                <tr class="grand">
                    <td>Total</td>
                    <td class="n">৳{{ number_format($total, 2) }}</td>
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
