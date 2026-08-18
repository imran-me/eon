<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Petty Cash Statement — {{ $float->custodian->name ?? 'Custodian' }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
    @page { size: A4 portrait; margin: 12mm; }

    :root {
        /* One palette across every printed document — party statement, expense
           list, expense report, and both petty cash sheets. The desk's teal was
           right while this was the only place it appeared; once four documents
           carried four accents, "which system is this from" stopped being
           answerable at a glance. On screen the petty cash desk is still teal —
           it is only PAPER that is unified. */
        --tc:  #3730a3;
        --tbg: #eef2ff;
        --thd: #4f46e5;   /* solid table header, as on the expense prints */
        --bdr: #e2e8f0;
        --txt: #1e293b;
        --mut: #64748b;
        --owe: #b45309;
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

    .hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .logo-txt { font-family: var(--tf); color: var(--tc); font-size: 26px; font-weight: 800; }
    .co-info { text-align: right; font-size: 12px; line-height: 1.7; }
    .co-info strong { font-family: var(--tf); font-size: 16px; display: block; }

    .label-bar {
        text-align: center; background: var(--tbg); color: var(--tc);
        text-transform: uppercase; letter-spacing: 2px; font-weight: 700;
        font-family: var(--tf); font-size: 11px; padding: 10px;
        border-top: 2px solid var(--tc); margin-bottom: 24px;
    }

    .meta-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-bottom: 20px; }
    .held-by h4 { font-family: var(--tf); color: var(--tc); font-size: 11px; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 5px; }
    .held-by p { font-size: 13px; line-height: 1.7; margin: 1px 0; }
    .held-by .nm { font-weight: 700; font-size: 15px; }
    .meta-tbl td { padding: 2px 0 2px 18px; font-weight: 600; font-size: 12px; }
    .meta-tbl .nf { font-family: var(--nf); font-size: 11px; }

    .summary-strip { display: flex; border: 1px solid var(--bdr); border-radius: 6px; overflow: hidden; margin-bottom: 20px; }
    .sum-card { flex: 1; padding: 10px 14px; border-right: 1px solid var(--bdr); }
    .sum-card:last-child { border-right: none; background: var(--thd); color: #fff; }
    .sum-card .k { font-size: 9.5px; text-transform: uppercase; letter-spacing: .06em; color: var(--mut); font-weight: 700; }
    .sum-card:last-child .k { color: #c7d2fe; }
    .sum-card .v { font-family: var(--nf); font-size: 14px; font-weight: 700; margin-top: 3px; }

    table.led { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    table.led thead th {
        background: var(--thd); color: #fff; font-family: var(--tf);
        font-size: 9.5px; text-transform: uppercase; letter-spacing: .05em;
        padding: 8px 9px; text-align: left;
    }
    table.led td { padding: 8px 9px; border-bottom: 1px solid var(--bdr); font-size: 11.5px; vertical-align: top; }
    table.led .n { text-align: right; font-family: var(--nf); white-space: nowrap; }
    table.led tbody tr:nth-child(even) { background: #fafafa; }
    .sub { display: block; color: var(--mut); font-size: 10px; margin-top: 2px; }
    .tag { display: inline-block; padding: 1px 7px; border-radius: 9px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    .tag.in  { background: #f0fdf4; color: #15803d; }
    .tag.out { background: #fff7ed; color: #c2410c; }
    .owe-note { color: var(--owe); font-weight: 700; font-size: 10px; display: block; margin-top: 2px; }

    .foot-row { display: flex; justify-content: space-between; gap: 24px; margin-top: 6px; }
    .sh { font-family: var(--tf); font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em; color: var(--tc); margin-bottom: 6px; }
    .amt-tbl { border-collapse: collapse; min-width: 250px; }
    .amt-tbl td { padding: 5px 10px; font-size: 11.5px; border-bottom: 1px solid var(--bdr); }
    .amt-tbl .n { text-align: right; font-family: var(--nf); font-weight: 700; }
    .amt-tbl tr.grand td { background: var(--thd); color: #fff; font-weight: 700; border: none; }
    .amt-tbl tr.owed td { color: var(--owe); font-weight: 700; }

    .note-box { max-width: 330px; font-size: 10.5px; color: var(--mut); line-height: 1.65; }

    .sig { margin-top: 46px; display: flex; justify-content: space-between; gap: 40px; }
    .sig-c { text-align: center; width: 210px; }
    .sig-l { border-top: 1px solid #94a3b8; padding-top: 5px; font-size: 10.5px; font-weight: 600; }

    .printed { margin-top: 24px; text-align: center; font-size: 9.5px; color: var(--mut); }

    @media print {
        body { background: #fff; padding: 0; }
        .card { box-shadow: none; padding: 0; max-width: none; }
        table.led thead { display: table-header-group; }
        table.led tr { page-break-inside: avoid; }
    }
</style>
</head>
<body>
<div class="card">

    <div class="hdr">
        <div class="logo-txt">{{ optional($company)->short_name ?: (optional($company)->name ?? config('app.name')) }}</div>
        <div class="co-info">
            <strong>{{ optional($company)->name ?? config('app.name') }}</strong>
            @if(optional($company)->address)<div>{{ $company->address }}</div>@endif
            @if(optional($company)->phone)<div>{{ $company->phone }}</div>@endif
        </div>
    </div>

    <div class="label-bar">Petty Cash Statement</div>

    @php
        $received = $movements->sum('in');
        $spent    = $movements->sum('out');
        // What the receipts totalled, against what the float actually paid. They
        // differ exactly when the holder covered part of a purchase themselves.
        $receipts = $movements->sum(fn ($m) => $m['total'] ?? 0);
        $ownMoney = $movements->sum(fn ($m) => $m['owed'] ?? 0);
    @endphp

    <div class="meta-row">
        <div class="held-by">
            <h4>Cash Held By</h4>
            <p class="nm">{{ $float->custodian->name ?? 'Custodian' }}</p>
            <p>{{ $float->company->name ?? '—' }}</p>
            @if($float->note)<p style="color:#64748b">{{ $float->note }}</p>@endif
        </div>
        <table class="meta-tbl">
            <tr><td>Float</td><td class="nf">#{{ str_pad($float->id, 5, '0', STR_PAD_LEFT) }}</td></tr>
            <tr><td>Account</td><td class="nf">{{ $float->account->code ?? '—' }} {{ $float->account->name ?? '' }}</td></tr>
            <tr><td>Status</td><td>{{ $float->status ? 'Active' : 'Closed' }}</td></tr>
            <tr><td>Movements</td><td class="nf">{{ $movements->count() }}</td></tr>
        </table>
    </div>

    <div class="summary-strip">
        <div class="sum-card">
            <div class="k">Cash Received</div>
            <div class="v">৳{{ number_format($received, 2) }}</div>
        </div>
        <div class="sum-card">
            <div class="k">Spent From Float</div>
            <div class="v">৳{{ number_format($spent, 2) }}</div>
        </div>
        @if($ownMoney > 0)
        <div class="sum-card">
            <div class="k">Paid From Own Pocket</div>
            <div class="v" style="color:var(--owe)">৳{{ number_format($ownMoney, 2) }}</div>
        </div>
        @endif
        <div class="sum-card">
            <div class="k">Should Be In Pocket</div>
            <div class="v">৳{{ number_format($balance, 2) }}</div>
        </div>
    </div>

    <table class="led">
        <thead>
            <tr>
                <th style="width:78px">Date</th>
                <th style="width:62px">What</th>
                <th>Detail</th>
                <th class="n" style="width:82px">In</th>
                <th class="n" style="width:82px">Out</th>
                <th class="n" style="width:90px">Balance</th>
            </tr>
        </thead>
        <tbody>
        @forelse($movements as $m)
            <tr>
                <td>{{ \Carbon\Carbon::parse($m['date'])->format('d M Y') }}</td>
                <td>
                    <span class="tag {{ $m['in'] > 0 ? 'in' : 'out' }}">
                        {{ $m['in'] > 0 ? 'Received' : ($m['type'] === 'expense' ? 'Spent' : 'Returned') }}
                    </span>
                </td>
                <td>
                    {{ $m['label'] }}
                    @if(!empty($m['note']))<span class="sub">{{ $m['note'] }}</span>@endif
                    @if(!empty($m['owed']) && $m['owed'] > 0)
                        {{-- The receipt was larger than the float could cover. Spelled
                             out so the Out column reading less than the purchase is not
                             mistaken for a missing amount. --}}
                        <span class="owe-note">
                            Receipt ৳{{ number_format($m['total'], 2) }} — ৳{{ number_format($m['owed'], 2) }}
                            paid by {{ $float->custodian->name ?? 'the holder' }} and owed back
                        </span>
                    @endif
                </td>
                <td class="n">{{ $m['in'] > 0 ? '৳' . number_format($m['in'], 2) : '' }}</td>
                <td class="n">{{ $m['out'] > 0 ? '৳' . number_format($m['out'], 2) : '' }}</td>
                <td class="n">৳{{ number_format($m['balance'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center;padding:26px;color:#94a3b8">No movements on this float yet.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="foot-row">
        <div class="note-box">
            <div class="sh">Notes</div>
            <p>
                <strong>Should be in pocket</strong> is cash received less what the float actually paid.
                Count the cash — it should match.
            </p>
            @if($ownMoney > 0)
            <p style="margin-top:6px">
                Where a receipt came to more than the float was holding, only the float's
                share appears in the Out column. The remainder was
                {{ $float->custodian->name ?? 'the holder' }}'s own money and is owed back
                separately — it is not part of the pocket figure.
            </p>
            @endif
        </div>
        <div>
            <div class="sh">Summary</div>
            <table class="amt-tbl">
                <tr><td>Cash received</td><td class="n">৳{{ number_format($received, 2) }}</td></tr>
                <tr><td>Spent from float</td><td class="n">৳{{ number_format($spent, 2) }}</td></tr>
                @if($ownMoney > 0)
                <tr><td>Receipts total</td><td class="n">৳{{ number_format($receipts, 2) }}</td></tr>
                <tr class="owed"><td>Paid from own pocket</td><td class="n">৳{{ number_format($ownMoney, 2) }}</td></tr>
                @endif
                <tr class="grand"><td>Should be in pocket</td><td class="n">৳{{ number_format($balance, 2) }}</td></tr>
                @if($owedBack > 0)
                <tr class="owed"><td>Still owed to them</td><td class="n">৳{{ number_format($owedBack, 2) }}</td></tr>
                @endif
            </table>
        </div>
    </div>

    <div class="sig">
        <div class="sig-c">
            <div class="sig-l">{{ $float->custodian->name ?? 'Custodian' }}</div>
            <div style="font-size:10px;margin-top:3px;">Cash holder</div>
        </div>
        <div class="sig-c">
            <div class="sig-l">Authorized Signatory</div>
            <div style="font-size:10px;margin-top:3px;">For, {{ optional($company)->name ?? config('app.name') }}</div>
        </div>
    </div>

    <div class="printed">
        Printed {{ now()->format('d M Y, h:i A') }}@if(!empty($printedBy)) by {{ $printedBy }}@endif
    </div>

</div>

<script>window.onload = function () { window.print(); };</script>
</body>
</html>
