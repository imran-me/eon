<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Petty Cash Report — {{ $periodLabel }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
    @page { size: A4 landscape; margin: 10mm; }

    :root {
        /* The shared print palette — see the note in print-statement. Every
           printed document in the ERP carries the same accent so they read as
           one system; the desk's teal stays on screen, where it distinguishes a
           page rather than a document. */
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

    body { font-family: var(--ff); padding: 24px; background: #f0f2f5; color: var(--txt); font-size: 11.5px; }
    .card { max-width: 1120px; margin: auto; background: #fff; padding: 34px; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,.06); }

    .hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .logo-txt { font-family: var(--tf); color: var(--tc); font-size: 24px; font-weight: 800; }
    .co-info { text-align: right; font-size: 11.5px; line-height: 1.7; }
    .co-info strong { font-family: var(--tf); font-size: 15px; display: block; }

    .label-bar {
        text-align: center; background: var(--tbg); color: var(--tc);
        text-transform: uppercase; letter-spacing: 2px; font-weight: 700;
        font-family: var(--tf); font-size: 10.5px; padding: 9px;
        border-top: 2px solid var(--tc); margin-bottom: 8px;
    }
    .period { text-align: center; font-size: 13px; font-weight: 700; margin-bottom: 18px; }
    .period span { color: var(--mut); font-weight: 500; font-size: 11px; display: block; margin-top: 2px; }

    .summary-strip { display: flex; border: 1px solid var(--bdr); border-radius: 6px; overflow: hidden; margin-bottom: 18px; }
    .sum-card { flex: 1; padding: 9px 13px; border-right: 1px solid var(--bdr); }
    .sum-card:last-child { border-right: none; }
    .sum-card.lead { background: var(--thd); color: #fff; }
    .sum-card .k { font-size: 9px; text-transform: uppercase; letter-spacing: .06em; color: var(--mut); font-weight: 700; }
    .sum-card.lead .k { color: #c7d2fe; }
    .sum-card .v { font-family: var(--nf); font-size: 13px; font-weight: 700; margin-top: 3px; }
    .sum-card.owe .v { color: var(--owe); }

    table.led { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    table.led thead th {
        background: var(--thd); color: #fff; font-family: var(--tf);
        font-size: 9px; text-transform: uppercase; letter-spacing: .05em;
        padding: 7px 8px; text-align: left;
    }
    table.led td { padding: 7px 8px; border-bottom: 1px solid var(--bdr); font-size: 11px; }
    table.led .n { text-align: right; font-family: var(--nf); white-space: nowrap; }
    table.led tbody tr:nth-child(even) { background: #f8f9ff; }
    table.led tfoot td { background: var(--thd); color: #fff; font-weight: 700; border-bottom: none; }
    .owe-n { color: var(--owe); font-weight: 700; }
    /* The total row is solid indigo now, and the amber "owed" tint vanishes
       against it. Keep the emphasis, in a colour that survives the background. */
    table.led tfoot td.owe-n { color: #fde68a; }
    .zero { color: #cbd5e1; }
    .closed-tag { font-size: 8.5px; font-weight: 700; color: var(--mut); background: #f1f5f9; padding: 1px 6px; border-radius: 8px; margin-left: 5px; }

    .note-box { font-size: 10px; color: var(--mut); line-height: 1.6; max-width: 620px; }
    .sh { font-family: var(--tf); font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: var(--tc); margin-bottom: 5px; }

    .sig { margin-top: 40px; display: flex; justify-content: space-between; gap: 40px; }
    .sig-c { text-align: center; width: 200px; }
    .sig-l { border-top: 1px solid #94a3b8; padding-top: 5px; font-size: 10px; font-weight: 600; }

    .printed { margin-top: 20px; text-align: center; font-size: 9px; color: var(--mut); }

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

    <div class="label-bar">Petty Cash Report</div>
    <div class="period">
        {{ $periodLabel }}
        <span>{{ \Carbon\Carbon::parse($from)->format('d M Y') }} to {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</span>
    </div>

    <div class="summary-strip">
        <div class="sum-card"><div class="k">Opening</div><div class="v">৳{{ number_format($totals['opening'], 2) }}</div></div>
        <div class="sum-card"><div class="k">Issued</div><div class="v">৳{{ number_format($totals['issued'], 2) }}</div></div>
        <div class="sum-card"><div class="k">Spent</div><div class="v">৳{{ number_format($totals['spent'], 2) }}</div></div>
        <div class="sum-card"><div class="k">Returned</div><div class="v">৳{{ number_format($totals['returned'], 2) }}</div></div>
        <div class="sum-card lead"><div class="k">Closing</div><div class="v">৳{{ number_format($totals['closing'], 2) }}</div></div>
        @if($totals['owed'] > 0)
        <div class="sum-card owe"><div class="k">Owed to staff</div><div class="v">৳{{ number_format($totals['owed'], 2) }}</div></div>
        @endif
    </div>

    <table class="led">
        <thead>
            <tr>
                <th>Custodian</th>
                <th>Company</th>
                <th class="n">Opening</th>
                <th class="n">Issued</th>
                <th class="n">Spent</th>
                <th class="n">Returned</th>
                @if($totals['other'] != 0)<th class="n">Other</th>@endif
                <th class="n">Closing</th>
                <th class="n">Owed</th>
                {{-- Left blank on purpose. This is the column the report is carried
                     to the cash box for: count what is in the pocket, write it in,
                     and the two figures either agree or they do not. --}}
                <th class="n" style="width:92px">Counted</th>
            </tr>
        </thead>
        <tbody>
        @forelse($rows as $r)
            <tr>
                <td>
                    {{ $r['custodian_name'] }}
                    @unless($r['active'])<span class="closed-tag">Closed</span>@endunless
                </td>
                <td>{{ $r['company_name'] }}</td>
                <td class="n {{ abs($r['opening']) < 0.001 ? 'zero' : '' }}">{{ number_format($r['opening'], 2) }}</td>
                <td class="n {{ abs($r['issued']) < 0.001 ? 'zero' : '' }}">{{ number_format($r['issued'], 2) }}</td>
                <td class="n {{ abs($r['spent']) < 0.001 ? 'zero' : '' }}">{{ number_format($r['spent'], 2) }}</td>
                <td class="n {{ abs($r['returned']) < 0.001 ? 'zero' : '' }}">{{ number_format($r['returned'], 2) }}</td>
                @if($totals['other'] != 0)
                <td class="n {{ abs($r['other']) < 0.001 ? 'zero' : '' }}">{{ number_format($r['other'], 2) }}</td>
                @endif
                <td class="n" style="font-weight:700">{{ number_format($r['closing'], 2) }}</td>
                <td class="n {{ $r['owed'] > 0.001 ? 'owe-n' : 'zero' }}">{{ number_format($r['owed'], 2) }}</td>
                <td class="n" style="border-bottom:1px solid #94a3b8"></td>
            </tr>
        @empty
            <tr><td colspan="10" style="text-align:center;padding:26px;color:#94a3b8">Nothing moved in this period.</td></tr>
        @endforelse
        </tbody>
        @if($rows->isNotEmpty())
        <tfoot>
            <tr>
                <td colspan="2">Total</td>
                <td class="n">{{ number_format($totals['opening'], 2) }}</td>
                <td class="n">{{ number_format($totals['issued'], 2) }}</td>
                <td class="n">{{ number_format($totals['spent'], 2) }}</td>
                <td class="n">{{ number_format($totals['returned'], 2) }}</td>
                @if($totals['other'] != 0)<td class="n">{{ number_format($totals['other'], 2) }}</td>@endif
                <td class="n">{{ number_format($totals['closing'], 2) }}</td>
                <td class="n {{ $totals['owed'] > 0.001 ? 'owe-n' : '' }}">{{ number_format($totals['owed'], 2) }}</td>
                <td class="n"></td>
            </tr>
        </tfoot>
        @endif
    </table>

    <div class="note-box">
        <div class="sh">How to read this</div>
        <p><strong>Opening + Issued − Spent − Returned = Closing.</strong> Every figure comes from the
        ledger, so this report and the accounts cannot disagree.</p>
        <p style="margin-top:5px">Where a receipt came to more than the pocket held, only the float's
        share counts as spent; the remainder was the custodian's own money and sits under
        <strong>Owed</strong>, which is why that column is not part of the sum.</p>
        <p style="margin-top:5px"><strong>Counted</strong> is left blank to be filled in by hand at the
        cash box. It should equal Closing.</p>
    </div>

    <div class="sig">
        <div class="sig-c">
            <div class="sig-l">Counted By</div>
        </div>
        <div class="sig-c">
            <div class="sig-l">Checked By</div>
        </div>
        <div class="sig-c">
            <div class="sig-l">Authorized Signatory</div>
            <div style="font-size:9.5px;margin-top:3px;">For, {{ optional($company)->name ?? config('app.name') }}</div>
        </div>
    </div>

    <div class="printed">
        Printed {{ now()->format('d M Y, h:i A') }}@if(!empty($printedBy)) by {{ $printedBy }}@endif
    </div>

</div>

<script>window.onload = function () { window.print(); };</script>
</body>
</html>
