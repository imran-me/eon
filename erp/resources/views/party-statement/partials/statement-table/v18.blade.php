<style>
    /* v18 · clean ledger: the structure of v17 (every cell filled, one shape for
       every row), redrawn quiet — one accent, colour only where it means money. */
    .lx { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 14px; font-family: var(--ff); }
    .lx th, .lx td { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; box-sizing: border-box; vertical-align: middle; }
    .lx thead th { background: var(--thd2); color: #fff; font-family: var(--tf); font-size: 7.6px; font-weight: 700;
        text-transform: uppercase; letter-spacing: .07em; padding: 7px 7px; text-align: left;
        border: none; }
    .lx th.r, .lx td.r { text-align: right; }
    .lx tbody td { height: 11.5mm; padding: 0 7px; border-bottom: 1px solid #eef1f6; background: #fff; }
    .lx tr { page-break-inside: avoid; }

    .lx .p { display: block; font-size: 9.4px; font-weight: 500; color: #111827; letter-spacing: .012em; line-height: 1.4; -webkit-font-smoothing: antialiased; text-rendering: geometricPrecision; overflow: hidden; text-overflow: ellipsis; }
    .lx .s { display: block; font-size: 7.8px; color: #6b7280; letter-spacing: .01em; line-height: 1.35; margin-top: 1px; overflow: hidden; text-overflow: ellipsis; }
    .lx .pnr { font-weight: 700; color: #713f12; background: #fde047; padding: 0 3px; border-radius: 2px; letter-spacing: .03em; }
    .lx .num { font-variant-numeric: tabular-nums; }

    .lx .vch { color: var(--tc); font-weight: 600; font-size: 8.8px; letter-spacing: .01em; }
    .lx .dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; margin-right: 5px; vertical-align: 1px; background: #6366f1; }
    .lx tr.pay .dot { background: #10b981; }
    .lx tr.rev .dot { background: #ef4444; }
    .lx .flag { color: #dc2626; font-weight: 600; }

    .lx .dr { color: #dc2626; }
    .lx .cr { color: #059669; }
    .lx .bal { font-size: 9.6px; font-weight: 600; color: #111827; }
    .lx .tag { display: inline-block; font-size: 7px; font-weight: 500; letter-spacing: .03em; padding: 0 5px; border-radius: 8px; line-height: 1.6; margin-top: 2px; }
    .lx .tag.dr { background: #fef2f2; } .lx .tag.cr { background: #ecfdf5; } .lx .tag.nil { background: #f1f5f9; color: #64748b; }

    .lx tr.open td { background: var(--tbg); }
</style>
@php
    $R = \App\Support\StatementRows::build($transactions, $flightChargePassengers ?? collect(), $ticketSaleItems ?? collect());
    $clean = fn ($s) => trim(preg_replace('/\s*[(-]\s*Travels\)?\s*$/i', '', (string) $s));
    $title = fn ($s) => \Illuminate\Support\Str::title(\Illuminate\Support\Str::lower($clean($s)));
    $cell = function ($r) use ($clean, $title) {
        $pax = (bool) $r['passenger'];
        $remark = $title($r['remarks']);
        // A one-word remark ("Flight", "Ticket") says what the money was for, not what happened.
        $thin = mb_strlen($remark) <= 12;
        $bank = $r['bank'] ? $clean($r['bank']) : null;
        $pnr = collect(explode(', ', (string) $r['pnr']))->filter()->unique()->first();
        // "BANK-DEPOSIT-20260922130910" is too long for one line: the name on
        // top, its number under it beside the posting time.
        $ref = $r['ref'] !== '—' ? $r['ref'] : 'SL-' . str_pad($r['sl'], 4, '0', STR_PAD_LEFT);
        $time = $r['time'] ? 'Posted ' . $r['time'] : 'Posted';
        [$v1, $v2] = preg_match('/^(.*\D)-?(\d{8,})$/', $ref, $m) ? [rtrim($m[1], '-'), '#' . $m[2] . '  ·  ' . ($r['time'] ?: '')] : [$ref, $time];
        // The bank is in its own column, so "Bank Deposit Received — Dutch Bangla" loses the tail.
        if ($bank && ! $thin) $remark = trim(preg_replace('/\s+—\s+[^—]+$/u', '', $remark));
        return [
            'v1' => $v1, 'v2' => rtrim($v2, ' ·'),
            'kind2' => $pax ? ($r['category'] ?: 'Passenger service') : ($r['method'] ?: ($r['credit'] > 0 ? 'Receipt' : 'Charge')),
            'p1' => $pax ? $r['passenger'] : ($thin ? ($r['credit'] > 0 ? 'Payment received' : $r['kind']) : $remark),
            'pnr' => $pax ? $pnr : null,
            'p2' => $pax ? collect([$r['passport'], $r['route']])->filter()->implode('  ·  ')
                         : ($thin ? 'For ' . ($remark !== '' ? \Illuminate\Support\Str::lower($remark) : 'account') . ($bank ? '  ·  ' . $bank : '') : ($bank ? 'Via ' . $bank : 'Party ledger entry')),
            'c1' => $pax ? ($r['flight'] ?: 'Contract flight') : ($bank ?: ($r['method'] ?: 'Ledger')),
            'c2' => $pax ? ($r['flight_date'] ? 'Flies ' . $r['flight_date']->format('d M Y') : ($r['stage'] ?: 'Date pending'))
                         : ($bank ? 'Bank account' : ($r['credit'] > 0 ? 'Received' : 'Posted')),
        ];
    };
@endphp

<table class="lx">
    <colgroup>
        <col style="width:12%"><col style="width:14%"><col style="width:31%">
        <col style="width:15%"><col style="width:13%"><col style="width:15%">
    </colgroup>
    <thead>
        <tr><th>Date</th><th>Entry</th><th>Particulars</th><th>Flight / Bank</th><th class="r">Amount (৳)</th><th class="r">Balance (৳)</th></tr>
    </thead>
    <tbody>
        @if($openingBalance != 0 && ! $selection)
        <tr class="open">
            <td><span class="p">{{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d M Y') : '' }}</span><span class="s">Period start</span></td>
            <td colspan="4"><span class="p">Opening balance</span><span class="s">Balance carried in from before this period</span></td>
            <td class="r"><span class="p bal num">{{ number_format(abs($openingBalance), 2) }}</span><span class="tag {{ $openingBalance > 0 ? 'dr' : 'cr' }}">{{ $openingBalance > 0 ? 'Receivable' : 'Advance' }}</span></td>
        </tr>
        @endif
        @forelse($R as $r)
        @php
            $c = $cell($r);
            $amt = $r['debit'] - $r['credit'];
            $bal = $r['balance'];
        @endphp
        <tr class="{{ $r['is_payment'] ? 'pay' : ($r['credit'] > 0 ? 'rev' : '') }}">
            <td><span class="p num">{{ $r['posted']->format('d M Y') }}</span><span class="s">{{ $r['posted']->format('l') }}</span></td>
            <td><span class="p"><i class="dot"></i>{{ $r['kind'] }}</span><span class="s">@if($r['flag'])<span class="flag">⚠ {{ $r['flag'] }}</span>@else{{ $c['kind2'] }}@endif</span></td>
            <td><span class="p">{{ $c['p1'] }}</span><span class="s">{{ $c['p2'] }}@if($c['pnr'])  ·  <b class="pnr">{{ $c['pnr'] }}</b>@endif</span></td>
            <td><span class="p">{{ $c['c1'] }}</span><span class="s">{{ $c['c2'] }}</span></td>
            <td class="r"><span class="p num {{ $amt > 0 ? 'dr' : ($amt < 0 ? 'cr' : '') }}">{{ number_format(abs($amt), 2) }}</span><span class="s">{{ $amt > 0 ? 'Debit' : ($amt < 0 ? 'Credit' : 'Nil') }}</span></td>
            <td class="r"><span class="p bal num">{{ number_format(abs($bal), 2) }}</span><span class="tag {{ $bal > 0 ? 'dr' : ($bal < 0 ? 'cr' : 'nil') }}">{{ $bal > 0 ? 'Receivable' : ($bal < 0 ? 'Advance' : 'Settled') }}</span></td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;height:auto;padding:20px;color:#94a3b8;">No transactions found for this period.</td></tr>
        @endforelse
    </tbody>
</table>
