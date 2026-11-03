{{-- The confirmed statement table (design 19) for the EMAILED / WhatsApp-link
     PDF — pdf-ledger-statement, rendered by dompdf, not Chrome. Same columns,
     same words (App\Support\StatementRows::cell) as the print's v19; only the
     drawing differs, because dompdf has DejaVu Sans alone (no Inter, no ৳
     glyph) and no text-overflow — and a line that overflows its cell is
     broken onto a third line instead of clipped. So each text is measured
     with dompdf's own font metrics and cut, with "…", to its column's width
     (App\Support\PdfText): every row keeps the same two lines and height.
     Needs: $rows (Transaction models with running balance), $opening,
     $highlight (rows announced as new — tinted, tagged NEW). --}}
@php
    $SR = \App\Support\StatementRows::class;
    [$flightChargePassengers, $ticketSaleItems] = $SR::related($rows);
    $R = $SR::build($rows, $flightChargePassengers, $ticketSaleItems);
    $newIds = ($highlight ?? collect())->pluck('id')->flip();
    $ids = $rows->values()->pluck('id');
    // Each text measured and cut to its column (App\Support\PdfText); the sheet has 14mm side margins.
    $pt = \App\Support\PdfText::a4(14);
    $wid = fn ($s, $px, $bold = false) => $pt->width($s, $px, $bold);
    $fit = fn ($s, $pct, $px, $reserve = 0.0, $bold = false) => $pt->fit($s, $pct, $px, $reserve, $bold);
@endphp
<style>
.lx{width:100%;border-collapse:collapse;table-layout:fixed;margin-bottom:4px}
.lx th{background:#4057df;color:#fff;font-size:6.6px;font-weight:bold;text-transform:uppercase;letter-spacing:.6px;padding:6px 6px;text-align:left}
.lx th.r,.lx td.r{text-align:right}
.lx td{height:30px;padding:0 6px;border-bottom:1px solid #eef1f6;vertical-align:middle;background:#fff}
.lx tr{page-break-inside:avoid}
.lx .p{font-size:8px;color:#111827;white-space:nowrap;overflow:hidden;line-height:1.35}
.lx .s{font-size:6.6px;color:#6b7280;white-space:nowrap;overflow:hidden;line-height:1.35;margin-top:1px}
.lx .dot{color:#6366f1;font-size:6px}
.lx tr.pay .dot{color:#10b981}.lx tr.rev .dot{color:#ef4444}
.lx .pnr{font-weight:bold;color:#713f12;background:#fde047;padding:0 2px}
.lx .flag{color:#dc2626}
.lx .dr{color:#dc2626}.lx .cr{color:#059669}.lx .nil{color:#cbd5e1}
.lx .bal{font-size:8.2px;font-weight:bold;color:#111827}
.lx .tag{display:inline-block;font-size:6px;padding:0 4px;border-radius:6px;margin-top:2px}
.lx .tag.dr{background:#fef2f2}.lx .tag.cr{background:#ecfdf5}.lx .tag.nil{background:#f1f5f9;color:#64748b}
.lx tr.open td{background:#f5f7ff}
.lx tr.new td{background:#fffbeb}
.lx .new-tag{font-size:5.6px;font-weight:bold;color:#92400e;background:#fef3c7;padding:0 2px;margin-left:3px}
</style>
<table class="lx">
    <thead>
        {{-- dompdf sizes a fixed table from its first row; it ignores <col> widths. --}}
        <tr><th style="width:11.5%">Date</th><th style="width:13.5%">Entry</th><th style="width:27%">Particulars</th><th style="width:13%">Flight / Bank</th><th class="r" style="width:11.5%">Debit</th><th class="r" style="width:11.5%">Credit</th><th class="r" style="width:12%">Balance</th></tr>
    </thead>
    <tbody>
        @if($opening != 0)
        <tr class="open">
            <td><div class="p">{{ $date_from ? \Carbon\Carbon::parse($date_from)->format('d M Y') : '' }}</div><div class="s">Period start</div></td>
            <td colspan="5"><div class="p">Opening balance</div><div class="s">Balance carried in from before this period</div></td>
            <td class="r"><div class="p bal">{{ number_format(abs($opening), 2) }}</div><span class="tag {{ $opening > 0 ? 'dr' : 'cr' }}">{{ $SR::balanceWord((float) $opening) }}</span></td>
        </tr>
        @endif
        @forelse($R as $i => $r)
        @php
            $c = $SR::cell($r);
            $bal = $r['balance'];
            $isNew = isset($newIds[$ids[$i] ?? null]);
        @endphp
        <tr class="{{ $r['is_payment'] ? 'pay' : ($r['credit'] > 0 ? 'rev' : '') }} {{ $isNew ? 'new' : '' }}">
            <td><div class="p">{{ $r['posted']->format('d M Y') }}</div><div class="s">{{ $r['posted']->format('l') }}</div></td>
            <td><div class="p"><span class="dot">●</span>&nbsp;{{ $fit($r['kind'], 13.5, 8, $wid('●', 6) + $wid(' ', 8) + ($isNew ? $wid('NEW', 5.6, true) + 5 : 0)) }}@if($isNew)<span class="new-tag">NEW</span>@endif</div><div class="s">@if($r['flag'])<span class="flag">⚠ {{ $fit($r['flag'], 13.5, 6.6, $wid('⚠ ', 6.6)) }}</span>@else{{ $fit($c['kind2'], 13.5, 6.6) }}@endif</div></td>
            @php $pnr = $c['pnr'] ? $fit($c['pnr'], 12, 6.6, 0, true) : null; @endphp
            <td><div class="p">{{ $fit($c['p1'], 27, 8) }}</div><div class="s">{{ $fit($c['p2'], 27, 6.6, $pnr ? $wid(' · ', 6.6) + $wid($pnr, 6.6, true) + 3 : 0) }}@if($pnr)&nbsp;·&nbsp;<span class="pnr">{{ $pnr }}</span>@endif</div></td>
            <td><div class="p">{{ $fit($c['c1'], 13, 8) }}</div><div class="s">{{ $fit($c['c2'], 13, 6.6) }}</div></td>
            <td class="r">@if($r['debit'] > 0)<div class="p dr">{{ number_format($r['debit'], 2) }}</div><div class="s">{{ $c['dr'] }}</div>@else<div class="p nil">—</div><div class="s">&nbsp;</div>@endif</td>
            <td class="r">@if($r['credit'] > 0)<div class="p cr">{{ number_format($r['credit'], 2) }}</div><div class="s">{{ $c['cr'] }}</div>@else<div class="p nil">—</div><div class="s">&nbsp;</div>@endif</td>
            <td class="r"><div class="p bal">{{ number_format(abs($bal), 2) }}</div><span class="tag {{ $bal > 0 ? 'dr' : ($bal < 0 ? 'cr' : 'nil') }}">{{ $c['tag'] }}</span></td>
        </tr>
        @empty
        <tr><td colspan="7" style="height:auto;padding:18px;text-align:center;color:#94a3b8">No transactions found for this period.</td></tr>
        @endforelse
    </tbody>
</table>
