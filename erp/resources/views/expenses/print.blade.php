<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Expense List Report{{ optional($company)->name ? ' — ' . $company->name : '' }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
{{--
    The printed expense list, in the same shape as the party statement.

    WHY THAT SHAPE — this is the copy that gets filed or handed to an auditor, so
    it has to look like a document rather than a screenshot of a screen. The old
    version had no page width at all: the table stretched to whatever the browser
    was, which on a wide monitor spread eleven columns across 1900px and printed
    with the right-hand ones clipped. Everything below is borrowed from
    party-statement/print-statement.blade.php on purpose — one A4 card centred at
    794px, one letterhead, one summary strip, one signature block — so the two
    documents read as coming from the same system.

    The columns are NOT the same as the on-screen list, and deliberately:

      · Bank is gone. It was "—" on almost every row, because a bank is only one
        of four places the money can come from. "Paid From" answers the question
        the Bank column was trying to, in the same precedence the posting uses
        (own pocket → float → bank → the drawer) — the same order
        ExpenseController::settlementAccountId() credits in.
      · User moved into the row's second line rather than its own column: on 794mm
        of paper a column of repeated names costs more width than it earns.
      · Status says APPROVED / PENDING first, because on a printed sheet the
        question is whether the money is in the accounts, not whether the record
        is active.
--}}
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

    /* ── Filters / Meta ── */
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
    .ledger { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 11px; table-layout: fixed; }
    .ledger th {
        background: var(--thd); color: #fff;
        padding: 8px 10px; text-align: left;
        font-family: var(--tf); font-size: 9px;
        text-transform: uppercase; letter-spacing: .04em;
    }
    .ledger th.r { text-align: right; }
    .ledger td { padding: 7px 10px; border-bottom: 1px solid var(--bdr); vertical-align: top; word-wrap: break-word; }
    .ledger td.r    { text-align: right; }
    .ledger td.mono { font-family: var(--nf); font-size: 10px; }
    .ledger tbody tr:nth-child(even) td { background: #f8f9ff; }
    .ledger tfoot tr { background: var(--thd); color: #fff; }
    .ledger tfoot td { padding: 8px 10px; font-weight: 700; border-bottom: none; font-family: var(--tf); font-size: 10px; }
    .ledger tfoot td.r { text-align: right; font-family: var(--nf); }

    /* The second line under a title — who filed it, and its reference. Quieter
       than the title so the column still scans as one thing. */
    .sub { display: block; font-size: 9.5px; color: #64748b; margin-top: 2px; }
    .sub-cat { display: block; font-size: 9.5px; color: #64748b; }

    /* ── Badges ── */
    .badge { display: inline-block; padding: 1px 7px; border-radius: 9px; font-size: 9px; font-weight: 600; white-space: nowrap; }
    .badge-approved { background: #dcfce7; color: #166534; }
    .badge-pending  { background: #fef3c7; color: #92400e; }
    .badge-inactive { background: #f1f5f9; color: #475569; margin-top: 3px; }
    .badge-src      { background: #f1f5f9; color: #475569; }
    .badge-own      { background: #ede9fe; color: #5b21b6; }
    .badge-petty    { background: #ccfbf1; color: #0f766e; }
    .badge-bank     { background: #dbeafe; color: #1e40af; }

    /* ── Sub-header bar ── */
    .sh { background: var(--thd); color: #fff; padding: 5px 12px; font-family: var(--tf); font-size: 10px; font-weight: 600; margin-bottom: 8px; letter-spacing: .03em; }

    /* ── Bottom grid ── */
    .bot { display: grid; grid-template-columns: 1.3fr 0.7fr; gap: 30px; }
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
        /* A long list breaks across pages, so the header has to repeat and a row
           must not be sliced through the middle of itself. */
        .ledger thead { display: table-header-group; }
        .ledger tr    { page-break-inside: avoid; }
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

@php
    // Every filter that was actually applied, named. The report has to say what
    // it is a report OF — an unlabelled filtered list reads as the whole
    // company's spending and is the one way a printed copy can mislead.
    $chips = [];
    if ($filterLabels['company'])     $chips[] = ['Company',      $filterLabels['company']];
    if ($filterLabels['department'])  $chips[] = ['Department',    $filterLabels['department']];
    if ($filterLabels['category'])    $chips[] = ['Category',     $filterLabels['category']];
    if ($filterLabels['subcategory']) $chips[] = ['Sub-category', $filterLabels['subcategory']];
    if ($filterLabels['user'])        $chips[] = ['Filed by',     $filterLabels['user']];
    if ($filterLabels['bank'])        $chips[] = ['Bank',         $filterLabels['bank']];
    if ($filterLabels['source'])      $chips[] = ['Paid from',    $filterLabels['source']];
    if (request('title'))             $chips[] = ['Title',        request('title')];

    if (in_array(request('approval_status'), [\App\Models\Expense::PENDING, \App\Models\Expense::APPROVED], true)) {
        $chips[] = ['Approval', ucfirst(request('approval_status'))];
    }

    if (filled(request('status')) && request('status') !== 'all') {
        $chips[] = ['Record', (int) request('status') === 1 ? 'Active' : 'Inactive'];
    }

    $total    = (float) $datas->sum('amount');
    $approved = $datas->where('approval_status', \App\Models\Expense::APPROVED);
    $pending  = $datas->where('approval_status', '!=', \App\Models\Expense::APPROVED);
@endphp

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
    <div class="label-bar">Expense List Report</div>

    {{-- Filters / Meta ── --}}
    <div class="meta-row">
        <div class="bill-to">
            <h4>{{ count($chips) ? 'Report Filters' : 'Scope' }}</h4>
            @if (count($chips))
                <div style="margin-top:2px;">
                    @foreach ($chips as [$label, $value])
                        <span class="role-badge">{{ $label }}: {{ $value }}</span>
                    @endforeach
                </div>
            @else
                {{-- Said out loud rather than left blank: "no filters" and "a
                     filter I forgot to print" look identical on paper. --}}
                <p>Every expense on record, unfiltered.</p>
            @endif
        </div>
        <div>
            <table class="meta-tbl">
                <tr><td>Records</td><td class="nf">{{ number_format($datas->count()) }}</td></tr>
                <tr>
                    <td>Date</td>
                    <td class="nf">
                        {{ request('expense_date') ? \Carbon\Carbon::parse(request('expense_date'))->format('d M Y') : 'All dates' }}
                    </td>
                </tr>
                <tr><td>Generated</td><td class="nf">{{ now()->format('d M Y, h:i A') }}</td></tr>
                <tr><td>Printed By</td><td>{{ Auth::user()->name }}</td></tr>
            </table>
        </div>
    </div>

    {{-- Summary strip ── --}}
    <div class="summary-strip">
        <div class="sum-card">
            <div class="sum-label">Records</div>
            <div class="sum-value">{{ number_format($datas->count()) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Approved (posted)</div>
            <div class="sum-value" style="color:#16a34a;">৳{{ number_format($approved->sum('amount'), 2) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Pending</div>
            <div class="sum-value" style="color:#b45309;">৳{{ number_format($pending->sum('amount'), 2) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Total Amount</div>
            <div class="sum-value">৳{{ number_format($total, 2) }}</div>
        </div>
    </div>

    {{-- Ledger table ── --}}
    <table class="ledger">
        <thead>
            <tr>
                <th width="4%">#</th>
                <th width="10%">Date</th>
                <th width="26%">Title</th>
                <th width="17%">Category</th>
                <th width="13%">Department</th>
                <th width="13%">Paid From</th>
                <th width="17%" class="r">Amount (৳)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($datas as $key => $value)
            <tr>
                <td>{{ $key + 1 }}</td>
                <td class="mono">{{ $value->expense_date ? \Carbon\Carbon::parse($value->expense_date)->format('d M y') : '—' }}</td>
                <td>
                    {{ $value->title }}
                    <span class="sub">
                        {{ $value->user?->name ?? '—' }}@if ($value->reference) · <span style="font-family:'Space Mono',monospace;">{{ $value->reference }}</span>@endif
                    </span>
                </td>
                <td>
                    {{ $value->expense_category?->name ?? '—' }}
                    @if ($value->expense_sub_category?->name)
                        <span class="sub-cat">↳ {{ $value->expense_sub_category->name }}</span>
                    @elseif ($value->other_note)
                        {{-- The typed answer stands in for the level that had none,
                             exactly as Expense::classificationPath renders it. --}}
                        <span class="sub-cat">“{{ Str::limit($value->other_note, 40) }}”</span>
                    @endif
                </td>
                <td>{{ $value->expenseDepartment?->name ?? '—' }}</td>
                <td>
                    {{-- Same precedence settlementAccountId() credits in: the claim
                         outranks a float, a float outranks a bank, and the drawer is
                         what is left. Reading payment_mode instead would print
                         "Cash" for an expense the ledger sent to a bank. --}}
                    @if ($value->reimburse_to_user_id)
                        <span class="badge badge-own">Own Pocket</span>
                        <span class="sub">{{ $value->reimburseTo?->name ?? 'Staff member removed' }}</span>
                    @elseif ($value->pettyCashFloat)
                        <span class="badge badge-petty">Petty Cash</span>
                        <span class="sub">{{ $value->pettyCashFloat->custodian?->name ?? 'Custodian removed' }}</span>
                    @elseif ($value->bank)
                        <span class="badge badge-bank">Bank</span>
                        <span class="sub">{{ $value->bank->name }}</span>
                    @else
                        <span class="badge badge-src">Cash in Hand</span>
                    @endif
                </td>
                <td class="r">
                    <span class="mono" style="font-weight:700;">{{ number_format($value->amount, 2) }}</span>
                    <span class="sub">
                        @if ($value->approval_status === \App\Models\Expense::APPROVED)
                            <span class="badge badge-approved">Approved</span>
                        @else
                            <span class="badge badge-pending">Pending</span>
                        @endif
                        @unless ($value->status)
                            <span class="badge badge-inactive">Inactive</span>
                        @endunless
                    </span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align:center;padding:20px;color:#94a3b8;">No expenses match these filters.</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6">Total — {{ number_format($datas->count()) }} expense{{ $datas->count() === 1 ? '' : 's' }}</td>
                <td class="r">৳{{ number_format($total, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    {{-- Bottom grid ── --}}
    <div class="bot">
        <div>
            <div class="sh">Total In Words:</div>
            <div class="nb">
                @php
                    try { $words = ucfirst((new \NumberToWords\NumberToWords())->getNumberTransformer('en')->toWords((int) round($total))) . ' Taka'; }
                    catch (\Throwable) { $words = '৳' . number_format($total, 2); }
                @endphp
                {{ $words }} only
            </div>

            <div class="sh">Note:</div>
            <div class="nb" style="font-size:11px;color:#64748b;">
                Generated on {{ now()->format('d M Y \a\t H:i') }}.
                “Approved” expenses are posted to the ledger; “Pending” ones are recorded
                but not yet posted, so they are part of the total above and not yet part
                of the accounts.
                @if ($pending->count())
                    {{ number_format($pending->count()) }} of the
                    {{ number_format($datas->count()) }} rows below are still pending.
                @endif
            </div>
        </div>
        <div>
            <div class="sh">Summary:</div>
            <table class="amt-tbl">
                <tr><td>Approved</td><td class="n">৳{{ number_format($approved->sum('amount'), 2) }}</td></tr>
                <tr><td>Pending</td><td class="n">৳{{ number_format($pending->sum('amount'), 2) }}</td></tr>
                <tr><td>Records</td><td class="n">{{ number_format($datas->count()) }}</td></tr>
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
