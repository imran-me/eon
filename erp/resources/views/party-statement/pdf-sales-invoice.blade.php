{{-- The sales invoice given to an agent / client — the owner's pick, "3.08 Sky
     blue, split header" (2026-10-14), its colour then deepened to the EPAL logo's
     navy #283088 (owner: "colour a bit deep — the logo's colour for coloured text"), out of 5 designs and 15 looks of the one he
     called standard: grouped by service, each kind of sale with the columns
     that fit it and its own subtotal, a service summary at the end.

     One dompdf sheet for the Invoice window, the PDF button and the after-posting
     email (App\Services\SalesInvoice::data supplies everything; this file only
     lays it out). DejaVu Sans only — no font files to ship. Each line keeps its
     own ledger reference; there is no invoice series. What an agent may see of
     each sale is decided in SalesInvoice::details() — never a vendor, never a cost. --}}
@php
    $m = fn ($x) => number_format((float) $x, 2);
    $sg = fn ($x) => $x < 0 ? '− ' . number_format(-$x, 2) : number_format((float) $x, 2);   // a net credit reads "− 295,000.00"
    $order = ['flight' => 'Contract flight packages', 'ticket' => 'Air tickets', 'visa' => 'Visa processing', 'file' => 'File processing', 'other' => 'Other services & adjustments'];
    $groups = [];
    foreach ($lines as $i => $r) $groups[$details[$i]['group']][] = $i;
    // Other services & adjustments are rows of the main table, not a table of their own
    // (owner, 2026-10-27) — they go at the end of the last service shown; alone, they keep theirs.
    $extra = [];
    $host = collect(array_keys($order))->reject(fn ($g) => $g === 'other' || empty($groups[$g]))->last();
    if ($host && ! empty($groups['other'])) {
        $extra = array_flip($groups['other']);
        $groups[$host] = array_merge($groups[$host], $groups['other']);
        unset($groups['other']);
    }
    // one such row inside a table of $cols columns: ref, passenger, the description across the middle, amount
    $otherRow = function ($i, $cols) use ($lines, $details, $m) {
        $r = $lines[$i]; $d = $details[$i]; $cr = $r['credit'] > 0 && $r['debit'] <= 0;
        $desc = collect($d['facts'])->firstWhere(0, 'Description')[1] ?? collect($d['facts'])->map(fn ($p) => $p[0] . ': ' . $p[1])->implode(' · ');
        return '<tr><td><span class="ref">' . e($r['ref']) . '</span><span class="s">' . e($r['posted']->format('d M Y')) . '</span></td>'
            . '<td><span class="bb">' . e($d['title']) . '</span><span class="s">' . ($cr ? 'Less · ' : '') . e($d['service']) . '</span></td>'
            . '<td colspan="' . ($cols - 3) . '">' . e($desc) . '</td>'
            . '<td class="r bb ' . ($cr ? 'cr' : '') . '">' . ($cr ? '− ' . $m($r['credit']) : $m($r['debit'])) . '</td></tr>';
    };
    $sumOf = fn ($ids) => array_sum(array_map(fn ($i) => $lines[$i]['debit'] - $lines[$i]['credit'], $ids));
    $f = fn ($i, $label) => collect($details[$i]['facts'])->firstWhere(0, $label)[1] ?? null;
    $balanceWord = $balance === null ? null : ($balance > 0 ? 'Due' : ($balance < 0 ? 'Advance' : 'Settled'));
@endphp
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Invoice — {{ $party->name }}</title>
<style>
/* The header is the invoice's own; the body (tables, words, summary, signature) takes the
   ledger print's styling (owner, 2026-10-27): #564fc8 headings, striped rows, its sizes and
   colours. dompdf has no web fonts, so the face stays DejaVu Sans. */
@page{margin:12mm}
body{font-family:'DejaVu Sans',sans-serif;font-size:9.6px;color:#1e293b;margin:0}
table{border-collapse:collapse;width:100%}
.hd td{vertical-align:middle}
.bigt{font-size:23.6px;font-weight:bold;color:#283088;letter-spacing:4px}
.sm{font-size:9px;color:#4b5563;line-height:1.5}
.card{background:#eef0fa;border-radius:8px;padding:10px 12px}
.co{font-size:12.6px;font-weight:bold;color:#111827}
.co-sub{font-size:8.6px;color:#6b7280;line-height:1.55}
.bill{margin:12px 0 4px;border-top:1px solid #c9cdee;border-bottom:1px solid #c9cdee}
.bill td{padding:9px 4px;vertical-align:top}
.lab{font-size:8px;font-weight:bold;color:#283088;letter-spacing:1px;text-transform:uppercase}
.pn{font-size:12.9px;font-weight:bold;margin-top:2px}
.gh{margin-top:12px}
.gh td{padding:5px 2px;font-size:8.8px;font-weight:bold;color:#3730a3;text-transform:uppercase;letter-spacing:.5px}
.gh .r{text-align:right}
.pill{display:inline}
.gt{table-layout:fixed}
/* the headings sit on ONE block (.hbar) — any per-cell or per-row fill leaves hairline seams in dompdf;
   the block's table and the rows' table share the same fixed column widths, so the columns line up */
.hbar{background:#564fc8}
/* each section's rows start with an empty sizing row carrying the headings' widths (dompdf sizes a fixed table from its first row) */
.gt tr.sz td{padding:0;border:0;height:0;line-height:0;font-size:0}
.gt th{font-size:7.2px;font-weight:bold;text-transform:uppercase;letter-spacing:.3px;text-align:left;padding:7px 8px;color:#fff;vertical-align:middle}
.gt td{padding:5px 8px;vertical-align:top;font-size:8.8px;border-bottom:1px solid #e2e8f0}
.gt tr:nth-child(odd) td{background:#f8f9ff}
.gt tr.sz td{background:none}
.gt tr{page-break-inside:avoid}
.gt .r{text-align:right;white-space:nowrap}
.bb{font-weight:bold;color:#111827;font-size:8.8px}
.s{display:block;color:#6b7280;font-size:7.6px;margin-top:1px}
.ref{color:#3730a3;font-weight:bold}
.pnr{background:#fde047;color:#713f12;font-weight:bold;padding:0 2px}
.flag{color:#dc2626;font-weight:bold}
.dr{color:#dc2626}
.cr{color:#16a34a}
.svs{margin-top:18px;page-break-inside:avoid}
.svs>tbody>tr>td{vertical-align:top}
.sh{background:#564fc8;color:#fff;padding:4px 10px;font-size:8px;font-weight:bold;margin-bottom:6px;letter-spacing:.3px}
.nb{font-size:9.6px;line-height:1.8;color:#475569;margin-bottom:12px}
.amt td{padding:5px 0;border-bottom:1px solid #eee;font-size:9.6px}
.amt .r{text-align:right;font-size:8.8px}
.amt .grand td{font-weight:bold;color:#b45309;font-size:10.4px;border-bottom:2px solid #4f46e5}
.amt .grand.advance td{color:#1e40af} .amt .grand.settled td{color:#3730a3}
.sign{width:160px;margin:40px 0 0 auto;border-top:1px solid #333;padding-top:4px;text-align:center;font-size:8px;font-weight:bold}
.sign span{display:block;font-weight:normal;font-size:8px;margin-top:2px}
.sigimg{width:160px;margin:18px 0 0 auto;text-align:center;height:56px}
</style></head><body>

<table class="hd"><tr>
    <td style="width:38%"><div class="bigt">INVOICE</div><div class="sm" style="margin-top:2px">{{ $issued->format('d M Y') }} · Party {{ $code }} · {{ $lines->count() }} {{ $lines->count() === 1 ? 'sale' : 'sales' }}</div></td>
    <td><div class="card"><table><tr><td style="width:{{ ($logoSize[0] ?? 48) + 14 }}px">@if($logo)<img src="{{ $logo }}" style="width:{{ $logoSize[0] ?? 48 }}px;height:{{ $logoSize[1] ?? 48 }}px">@endif</td>
        <td><div class="co">{{ $companyName }}</div><div class="co-sub">{{ $company?->address }}<br>{{ $company?->phone }}{{ $company?->phone && $company?->email ? ' · ' : '' }}{{ $company?->email }}</div></td></tr></table></div></td>
</tr></table>

<table class="bill"><tr>
    <td style="width:50%"><div class="lab">Bill to</div><div class="pn">{{ $party->name }}</div><div class="sm">{{ $typeLabel }} · {{ $code }}@if($party->phone) · {{ $party->phone }}@endif @if($party->email)<br>{{ $party->email }}@endif @if($party->address)<br>{{ $party->address }}@endif</div></td>
    <td><div class="lab">{{ $refs->count() === 1 ? 'Reference' : 'References' }}</div><div class="sm">{{ $refs->implode(', ') ?: '—' }}</div></td>
</tr></table>
@php $tone = $balance === null || $balance > 0 ? 'due' : ($balance < 0 ? 'advance' : 'settled'); @endphp

@foreach($order as $g => $gname)
    @continue(empty($groups[$g]))
    @php $ids = $groups[$g]; @endphp
    <table class="gh"><tr><td><span class="pill">{{ $gname }} · {{ count($ids) }}</span></td><td class="r">BDT {{ $sg($sumOf($ids)) }}</td></tr></table>
    <div class="hbar"><table class="gt">
    @if($g === 'flight')
        <tr class="h"><th style="width:12%">Ref · Sold</th><th style="width:22%">Passenger · Passport</th><th style="width:19%">Route · PNR</th><th style="width:20%">Flight · Departs</th><th style="width:15%">Category · Services</th><th class="r" style="width:12%">Amount</th></tr></table></div><table class="gt"><tr class="sz"><td style="width:12%"></td><td style="width:22%"></td><td style="width:19%"></td><td style="width:20%"></td><td style="width:15%"></td><td style="width:12%"></td></tr>
        @foreach($ids as $i)@if(isset($extra[$i])){!! $otherRow($i, 6) !!}@continue @endif @php $r = $lines[$i]; $d = $details[$i]; $cr = $r['credit'] > 0 && $r['debit'] <= 0; @endphp
        <tr><td><span class="ref">{{ $r['ref'] }}</span><span class="s">{{ $r['posted']->format('d M Y') }}</span></td>
            <td><span class="bb">{{ $d['title'] }}</span><span class="s">{{ $f($i, 'Passport') ?? '—' }}@if($d['note']) <span class="flag">{{ $d['note'] }}</span>@endif</span></td>
            <td>{{ $f($i, 'Route') ?? '—' }}<span class="s"><span class="pnr">{{ $f($i, 'PNR') ?? '—' }}</span></span></td>
            <td>{{ $f($i, 'Flight') ?? '—' }}<span class="s">{{ $f($i, 'Departs') ?? '—' }}</span></td>
            <td>{{ $f($i, 'Category') ?? '—' }}<span class="s">{{ $f($i, 'Services') ?? '—' }}@if($f($i, 'Status')) · {{ $f($i, 'Status') }}@endif</span></td>
            <td class="r bb {{ $cr ? 'cr' : '' }}">{{ $cr ? '− ' . $m($r['credit']) : $m($r['debit']) }}</td></tr>
        @endforeach
    @elseif($g === 'ticket' || $g === 'visa')
        <tr class="h"><th style="width:12%">Ref · Sold</th><th style="width:25%">Passenger · Passport</th><th style="width:{{ $g === 'ticket' ? 17 : 25 }}%">{{ $g === 'ticket' ? 'Route · PNR' : 'Country · Visa type' }}</th><th style="width:{{ $g === 'ticket' ? 29 : 21 }}%">{{ $g === 'ticket' ? 'Airline · Travel date' : 'Travel date' }}</th><th class="r" style="width:17%">Amount</th></tr></table></div><table class="gt"><tr class="sz"><td style="width:12%"></td><td style="width:25%"></td><td style="width:{{ $g === 'ticket' ? 17 : 25 }}%"></td><td style="width:{{ $g === 'ticket' ? 29 : 21 }}%"></td><td style="width:17%"></td></tr>
        @foreach($ids as $i)
            @if(isset($extra[$i])){!! $otherRow($i, 5) !!}@continue @endif
            @php
                $r = $lines[$i]; $d = $details[$i]; $cr = $r['credit'] > 0 && $r['debit'] <= 0;
                // A sale of several people: one row each, with that person's own price.
                $ppl = $d['people'] ?: [array_merge(['Passenger' => $d['title']], collect($d['facts'])->mapWithKeys(fn ($p) => [$p[0] => $p[1]])->all())];
            @endphp
            @foreach($ppl as $k => $p)
            <tr><td>@if($k === 0)<span class="ref">{{ $r['ref'] }}</span><span class="s">{{ $r['posted']->format('d M Y') }}</span>@endif</td>
                <td><span class="bb">{{ $p['Passenger'] ?? '—' }}</span><span class="s">{{ $p['Passport'] ?? '—' }}</span></td>
                @if($g === 'ticket')
                <td>{{ $p['Route'] ?? '—' }}<span class="s"><span class="pnr">{{ $p['PNR'] ?? '—' }}</span></span></td><td>{{ $p['Airline'] ?? '—' }}<span class="s">{{ isset($p['Travel date']) ? 'Travels ' . $p['Travel date'] : '—' }}</span></td>
                @else
                <td>{{ $p['Country'] ?? '—' }}<span class="s">{{ $p['Visa type'] ?? '—' }}</span></td><td>{{ $p['Travel date'] ?? '—' }}</td>
                @endif
                <td class="r bb {{ $cr ? 'cr' : '' }}">{{ count($ppl) > 1 ? str_replace('BDT ', '', $p['Price'] ?? '') : ($cr ? '− ' . $m($r['credit']) : $m($r['debit'])) }}</td></tr>
            @endforeach
        @endforeach
    @elseif($g === 'file')
        <tr class="h"><th style="width:12%">Ref · Sold</th><th style="width:24%">Applicant · Passport</th><th style="width:22%">Country · Category</th><th style="width:16%">Submitted · Expected</th><th style="width:14%">Status</th><th class="r" style="width:12%">Amount</th></tr></table></div><table class="gt"><tr class="sz"><td style="width:12%"></td><td style="width:24%"></td><td style="width:22%"></td><td style="width:16%"></td><td style="width:14%"></td><td style="width:12%"></td></tr>
        @foreach($ids as $i)@if(isset($extra[$i])){!! $otherRow($i, 6) !!}@continue @endif @php $r = $lines[$i]; $d = $details[$i]; $cr = $r['credit'] > 0 && $r['debit'] <= 0; @endphp
        <tr><td><span class="ref">{{ $r['ref'] }}</span><span class="s">{{ $r['posted']->format('d M Y') }}</span></td>
            <td><span class="bb">{{ $d['title'] }}</span><span class="s">{{ $f($i, 'Passport') ?? '—' }}</span></td>
            <td>{{ $f($i, 'Country') ?? '—' }}<span class="s">File {{ $f($i, 'File no.') ?? '—' }}</span></td>
            <td>{{ $f($i, 'Submitted') ?? '—' }}<span class="s">{{ $f($i, 'Expected') ? 'exp. ' . $f($i, 'Expected') : '—' }}</span></td>
            <td>{{ $f($i, 'Status') ?? '—' }}</td>
            <td class="r bb {{ $cr ? 'cr' : '' }}">{{ $cr ? '− ' . $m($r['credit']) : $m($r['debit']) }}</td></tr>
        @endforeach
    @else
        <tr class="h"><th style="width:12%">Ref · Sold</th><th style="width:24%">Passenger</th><th style="width:50%">Description</th><th class="r" style="width:14%">Amount</th></tr></table></div><table class="gt"><tr class="sz"><td style="width:12%"></td><td style="width:24%"></td><td style="width:50%"></td><td style="width:14%"></td></tr>
        @foreach($ids as $i)@php $r = $lines[$i]; $d = $details[$i]; $cr = $r['credit'] > 0 && $r['debit'] <= 0; @endphp
        <tr><td><span class="ref">{{ $r['ref'] }}</span><span class="s">{{ $r['posted']->format('d M Y') }}</span></td>
            <td><span class="bb">{{ $d['title'] }}</span><span class="s">{{ $cr ? 'Less · ' : '' }}{{ $d['service'] }}</span></td>
            <td>{{ $f($i, 'Description') ?? collect($d['facts'])->map(fn ($p) => $p[0] . ': ' . $p[1])->implode(' · ') }}</td>
            <td class="r bb {{ $cr ? 'cr' : '' }}">{{ $cr ? '− ' . $m($r['credit']) : $m($r['debit']) }}</td></tr>
        @endforeach
    @endif
    </table>
@endforeach
@if($lines->isEmpty())<div class="sm" style="margin:24px 0;text-align:center">No sales in this selection.</div>@endif

<table class="svs"><tr>
    <td style="width:56%;padding-right:22px"><div class="sh">Amount In Words:</div><div class="nb">{{ $words }}{{ $total < 0 ? ' (credit)' : '' }}</div>
        @if($balanceWord)<div class="sh">Note:</div><div class="nb" style="color:#1e293b;padding:7px 9px;background:#fffbeb;border:1px dashed #f59e0b">Account balance today: <b>BDT {{ $m(abs($balance)) }} ({{ $balanceWord }})</b>. Please quote the reference with your payment.</div>@endif</td>
    <td><div class="sh">Summary:</div><table class="amt">
        @foreach($order as $g => $gname)@continue(empty($groups[$g]))<tr><td>{{ $gname }} · {{ count($groups[$g]) }}</td><td class="r">BDT {{ $sg($sumOf($groups[$g])) }}</td></tr>@endforeach
        <tr class="grand {{ $tone }}"><td>Invoice total</td><td class="r">BDT {{ $sg($total) }}</td></tr>
    </table>
    {{-- The signature set in the Invoice window (⚙), sitting on the line. --}}
    @if(! empty($signature))<div class="sigimg"><img src="{{ $signature }}" style="width:{{ $signatureSize[0] }}px;height:{{ $signatureSize[1] }}px"></div>@endif
    <div class="sign" @if(! empty($signature)) style="margin-top:2px" @endif>Authorized Signatory<span>For, {{ $companyName }}</span></div></td>
</tr></table>
</body></html>
