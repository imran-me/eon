@php
    use Illuminate\Support\Str;
    $role = Str::slug(auth()->user()->getRoleNames()->first());
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>General Ledger {{ $data ? '— ' . $data['account']->name : '' }} — {{ optional($company)->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
    @page { size: A4 portrait; margin: 14mm; }

    *, *::before, *::after {
        box-sizing: border-box; margin: 0; padding: 0;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    body { font-family: var(--ff); padding: 28px; background: #f0f2f5; color: var(--txt); font-size: 12px; }

    :root {
        --tc:  #1d4ed8;
        --tbg: #eff6ff;
        --thd: #2563eb;
        --bdr: #e2e8f0;
        --txt: #1e293b;
        --tf:  "Montserrat", sans-serif;
        --ff:  "Inter", sans-serif;
        --nf:  "Space Mono", monospace;
    }

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
    .meta-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-bottom: 22px; }
    .acc-info h4 { font-family: var(--tf); color: var(--tc); font-size: 11px; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 5px; }
    .acc-info p { font-size: 13px; line-height: 1.7; margin: 1px 0; }
    .acc-code { font-family: var(--nf); background: #f1f5f9; border-radius: 4px; padding: 2px 8px; font-size: 11px; color: #475569; }
    .type-pill { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: capitalize; background: var(--tbg); color: var(--tc); margin-left: 6px; }
    .meta-tbl td { padding: 2px 0 2px 18px; font-weight: 600; font-size: 12px; }
    .meta-tbl .nf { font-family: var(--nf); font-size: 11px; }

    /* ── Ledger table ── */
    .items-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
    .items-table th {
        background: var(--thd); color: #fff; text-align: left;
        padding: 9px 11px; font-family: var(--tf); font-size: 10px;
        text-transform: uppercase; letter-spacing: .04em;
    }
    .items-table th.r, .items-table td.r { text-align: right; }
    .items-table td { padding: 9px 11px; border-bottom: 1px solid var(--bdr); font-size: 11px; }
    .items-table td.nf { font-family: var(--nf); font-size: 10px; }
    .items-table td .sub { display: block; font-size: 10px; color: #94a3b8; margin-top: 2px; }
    .items-table tbody tr:nth-child(even) td { background: #f5f8ff; }
    .items-table .opening-row td { background: #eff6ff !important; color: var(--tc); font-style: italic; font-weight: 600; }
    .items-table .total-row td {
        background: #f8fafc; font-weight: 700; font-family: var(--tf);
        font-size: 12px; border-top: 2px solid var(--thd); border-bottom: none;
    }
    .items-table .total-row td.nf { font-family: var(--nf); }
    .src-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; text-transform: capitalize; background: #f1f5f9; color: #475569; }
    .debit-val  { color: var(--tc); }
    .credit-val { color: #16a34a; }
    .bal-pos { color: #16a34a; }
    .bal-neg { color: #dc2626; }

    /* ── Sub-header bar ── */
    .sh { background: var(--thd); color: #fff; padding: 5px 12px; font-family: var(--tf); font-size: 10px; font-weight: 600; margin-bottom: 8px; letter-spacing: .03em; }

    /* ── Amounts box ── */
    .amt-wrap { display: flex; justify-content: flex-end; }
    .amt-tbl { width: 280px; border-collapse: collapse; }
    .amt-tbl td { padding: 6px 0; border-bottom: 1px solid #eee; font-size: 12px; }
    .amt-tbl td.n { font-family: var(--nf); font-size: 11px; text-align: right; }
    .amt-tbl .sub-total td { color: #64748b; }
    .amt-tbl .grand td { font-weight: 700; color: var(--tc); font-size: 14px; border-bottom: 2px solid var(--thd); }

    /* ── Empty state ── */
    .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }

    /* ── Print ── */
    @media print {
        body       { background: #fff !important; padding: 0 !important; }
        .btn-row   { display: none !important; }
        .card      { box-shadow: none !important; padding: 0 !important; max-width: 100% !important; }
        .label-bar { padding: 7px; margin-bottom: 16px; }
        .items-table tr { page-break-inside: avoid; }
    }
</style>
</head>
<body>

{{-- Action buttons ── --}}
<div class="btn-row" style="max-width:794px;margin:0 auto 12px auto;display:flex;gap:8px;justify-content:flex-end;">
    <a href="{{ route('role.report.general-ledger', array_merge(['role' => $role], $request->query())) }}" style="background:#6b7280;color:#fff;text-decoration:none;display:inline-block;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;border-radius:4px;">← Back</a>
    <button onclick="window.print()" style="background:#2563eb;color:#fff;border:none;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">🖨️ Print</button>
    <button onclick="window.print()" style="background:#fff;color:#2563eb;border:1px solid #2563eb;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">📄 Download PDF</button>
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
    <div class="label-bar">General Ledger</div>

    @if(!$data)
        <div class="empty-state">
            <p>No ledger data found for the selected account/filters.</p>
        </div>
    @else
        {{-- Meta row ── --}}
        <div class="meta-row">
            <div class="acc-info">
                <h4>Account</h4>
                <p>
                    <span class="acc-code">{{ $data['account']->code }}</span>
                    <strong>{{ $data['account']->name }}</strong>
                    <span class="type-pill">{{ ucfirst($data['account']->type) }}</span>
                </p>
            </div>
            <div>
                <table class="meta-tbl">
                    <tr>
                        <td>Period</td>
                        <td class="nf">
                            @if(request('date_from') || request('date_to'))
                                {{ request('date_from') ? \Carbon\Carbon::parse(request('date_from'))->format('d M Y') : 'Beginning' }}
                                &mdash;
                                {{ request('date_to') ? \Carbon\Carbon::parse(request('date_to'))->format('d M Y') : 'Today' }}
                            @else
                                All Periods
                            @endif
                        </td>
                    </tr>
                    <tr><td>Generated</td><td class="nf">{{ now()->format('d M Y, h:i A') }}</td></tr>
                </table>
            </div>
        </div>

        {{-- Ledger table ── --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th width="11%">Date</th>
                    <th width="14%">Reference</th>
                    <th width="11%">Source</th>
                    <th width="30%">Description / Note</th>
                    <th class="r" width="12%">Debit</th>
                    <th class="r" width="12%">Credit</th>
                    <th class="r" width="10%">Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr class="opening-row">
                    <td colspan="4">Opening Balance</td>
                    <td class="r">—</td>
                    <td class="r">—</td>
                    <td class="r nf">
                        {{ number_format(abs($data['opening_balance']), 2) }} {{ $data['opening_balance'] < 0 ? 'Cr' : 'Dr' }}
                    </td>
                </tr>
                @foreach($data['rows'] as $row)
                <tr>
                    <td class="nf">{{ \Carbon\Carbon::parse($row['date'])->format('d M Y') }}</td>
                    <td>{{ $row['reference'] ?: '—' }}</td>
                    <td><span class="src-badge">{{ ucfirst($row['source']) }}</span></td>
                    <td>
                        {{ Str::limit($row['description'], 45) }}
                        @if($row['note'])
                        <span class="sub">{{ $row['note'] }}</span>
                        @endif
                    </td>
                    <td class="r nf">{{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '—' }}</td>
                    <td class="r nf">{{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '—' }}</td>
                    <td class="r nf {{ $row['balance'] >= 0 ? 'bal-pos' : 'bal-neg' }}">
                        {{ number_format(abs($row['balance']), 2) }} {{ $row['balance'] < 0 ? 'Cr' : 'Dr' }}
                    </td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="4">Closing Balance</td>
                    <td class="r nf debit-val">{{ number_format($data['total_debit'], 2) }}</td>
                    <td class="r nf credit-val">{{ number_format($data['total_credit'], 2) }}</td>
                    <td class="r nf {{ $data['closing_balance'] >= 0 ? 'bal-pos' : 'bal-neg' }}">
                        {{ number_format(abs($data['closing_balance']), 2) }} {{ $data['closing_balance'] < 0 ? 'Cr' : 'Dr' }}
                    </td>
                </tr>
            </tbody>
        </table>

        {{-- Amounts summary ── --}}
        <div class="amt-wrap">
            <table class="amt-tbl">
                <tr class="sub-total"><td>Total Debit</td><td class="n">{{ number_format($data['total_debit'], 2) }}</td></tr>
                <tr class="sub-total"><td>Total Credit</td><td class="n">{{ number_format($data['total_credit'], 2) }}</td></tr>
                <tr class="grand"><td>Closing Balance</td><td class="n">{{ number_format(abs($data['closing_balance']), 2) }} {{ $data['closing_balance'] < 0 ? 'Cr' : 'Dr' }}</td></tr>
            </table>
        </div>
    @endif

</div>

<script>window.onload = function () { window.print(); };</script>
</body>
</html>
