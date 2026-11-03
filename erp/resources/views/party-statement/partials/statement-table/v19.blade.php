<style>
    /* The party statement's ledger table (owner confirmed "19", 2026-10-14):
       every row the same two-line shape and every cell filled, payment or
       passenger service; one accent, colour only where it means money. */
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

    .lx .dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; margin-right: 5px; vertical-align: 1px; background: #6366f1; }
    .lx tr.pay .dot { background: #10b981; }
    .lx tr.rev .dot { background: #ef4444; }
    .lx .flag { color: #dc2626; font-weight: 600; }

    .lx .dr { color: #dc2626; }
    .lx .cr { color: #059669; }
    .lx .bal { font-size: 9.6px; font-weight: 600; color: #111827; }
    .lx .tag { display: inline-block; font-size: 7px; font-weight: 500; letter-spacing: .03em; padding: 0 5px; border-radius: 8px; line-height: 1.6; margin-top: 2px; }
    .lx .tag.dr { background: #fef2f2; } .lx .tag.cr { background: #ecfdf5; } .lx .tag.nil { background: #f1f5f9; color: #64748b; }

    .lx .nil { color: #cbd5e1; font-weight: 400; }
    .lx tr.open td { background: var(--tbg); }
</style>
@php
    // Row facts and the words in each cell: App\Support\StatementRows (shared
    // with the emailed PDF, statement-table/v19-email).
    $R = \App\Support\StatementRows::build($transactions, $flightChargePassengers ?? collect(), $ticketSaleItems ?? collect());
    // Print only (owner, 2026-10-28): a payment's small line carries the
    // banking details — method, account no., branch, reference — instead of
    // "Advance payment". Thee ledger screen and the emailed PDF are unchanged.
    $txById = $transactions->keyBy('id');
    // Columns picked in the Print designer (all seven by default).
    $allCols = \App\Http\Controllers\Dashboard\PartyStatementController::PRINT_COLUMNS;
    $show = array_values(array_intersect(array_keys($allCols), $sheet['cols'] ?? array_keys(array_filter($allCols, fn ($c) => $c[2]))));
    $weight = array_sum(array_map(fn ($k) => $allCols[$k][1], $show)) ?: 1;
    $on = fn ($k) => in_array($k, $show, true);
    try {
        $bankInfo = \App\Models\Bank::whereIn('id', $transactions->pluck('account_id')->filter()->unique())->get()->keyBy('id');
    } catch (\Throwable) {
        $bankInfo = collect();
    }
    // A refund row reads like a ticket sale: passenger, route · PNR.
    try {
        $refundPurchases = \App\Models\TicketPurchase::with(['passportHolder', 'ticket'])
            ->whereIn('id', $transactions->map(fn ($t) => $t->refund_summary['ticket_purchase_id'] ?? null)->filter()->unique())
            ->get()->keyBy('id');
    } catch (\Throwable) {
        $refundPurchases = collect();
    }
@endphp

<table class="lx">
    <colgroup>
        @foreach($show as $k)<col style="width:{{ round($allCols[$k][1] / $weight * 100, 2) }}%">@endforeach
    </colgroup>
    <thead>
        <tr>@foreach($show as $k)<th class="{{ in_array($k, ['debit', 'credit', 'balance'], true) ? 'r' : '' }}">{{ $allCols[$k][0] }}</th>@endforeach</tr>
    </thead>
    <tbody>
        @if($openingBalance != 0 && ! $selection && empty($catFiltered))
        <tr class="open">
            <td colspan="{{ max(1, count($show) - ($on('balance') ? 1 : 0)) }}"><span class="p">Opening balance{{ $dateFrom ? ' · '.\Carbon\Carbon::parse($dateFrom)->format('d M Y') : '' }}</span><span class="s">Balance carried in from before this period{{ $on('balance') ? '' : ': ৳'.number_format(abs($openingBalance), 2).' '.\App\Support\StatementRows::balanceWord((float) $openingBalance) }}</span></td>
            @if($on('balance'))<td class="r"><span class="p bal num">{{ number_format(abs($openingBalance), 2) }}</span><span class="tag {{ $openingBalance > 0 ? 'dr' : 'cr' }}">{{ \App\Support\StatementRows::balanceWord((float) $openingBalance) }}</span></td>@endif
        </tr>
        @endif
        @forelse($R as $r)
        @php
            $c = \App\Support\StatementRows::cell($r);
            $bal = $r['balance'];
            $tx = $txById[$r['id']] ?? null;
            // The Entry column already says "Payment": "Ticket Payment — Advance
            // payment received" prints as just the description.
            if ($r['is_payment']) {
                $desc = trim(preg_replace('/^[^—]*\bpayment\s*—\s*/iu', '', (string) $c['p1']));
                $c['p1'] = $desc !== '' ? \Illuminate\Support\Str::ucfirst($desc) : $c['p1'];
            }
            $rp = $tx && isset($tx->refund_summary['ticket_purchase_id']) ? ($refundPurchases[$tx->refund_summary['ticket_purchase_id']] ?? null) : null;
            if ($rp) {
                $c['p1'] = $rp->passportHolder?->name ?: $c['p1'];
                $c['p2'] = $rp->ticket?->title ?: '';
                $c['pnr'] = $rp->ticket_no ?: null;
            }
            if ($r['is_payment'] && $tx) {
                $bk = $tx->account_id ? ($bankInfo[$tx->account_id] ?? null) : null;
                $bankLine = collect([
                    $r['method'],
                    $bk && $bk->account_number ? 'A/C '.$bk->account_number : null,
                    $bk?->branch_name,
                    $tx->reference_no ? 'Ref '.$tx->reference_no : null,
                ])->filter()->unique()->implode('  ·  ');
                if ($bankLine !== '') {
                    $c['p2'] = $bankLine;
                } elseif (str_starts_with((string) $r['type'], 'customer_advance_received')) {
                    $c['p2'] = $r['payable'] ? 'Paid' : 'Received';
                }
            }
        @endphp
        <tr class="{{ $r['is_payment'] ? 'pay' : ($r['credit'] > 0 ? 'rev' : '') }}">
            @if($on('sl'))<td><span class="p num">{{ $r['sl'] }}</span><span class="s">&nbsp;</span></td>@endif
            @if($on('date'))<td><span class="p num">{{ $r['posted']->format('d M Y') }}</span><span class="s">{{ $r['posted']->format('l') }}</span></td>@endif
            @if($on('entry'))<td><span class="p"><i class="dot"></i>{{ $r['kind'] }}</span><span class="s">@if($r['flag'])<span class="flag">⚠ {{ $r['flag'] }}</span>@else{{ $c['kind2'] }}@endif</span></td>@endif
            @if($on('particulars'))<td><span class="p">{{ $c['p1'] }}</span><span class="s">{{ $c['p2'] }}@if($c['pnr'])  ·  <b class="pnr">{{ $c['pnr'] }}</b>@endif</span></td>@endif
            @if($on('flight'))<td><span class="p">{{ $c['c1'] }}</span><span class="s">{{ $c['c2'] }}</span></td>@endif
            @if($on('ref'))<td><span class="p num">{{ $r['ref'] }}</span><span class="s">{{ $r['time'] ?: ' ' }}</span></td>@endif
            @if($on('debit'))<td class="r">@if($r['debit'] > 0)<span class="p num dr">{{ number_format($r['debit'], 2) }}</span><span class="s">{{ $c['dr'] }}</span>@else<span class="p nil">—</span><span class="s">&nbsp;</span>@endif</td>@endif
            @if($on('credit'))<td class="r">@if($r['credit'] > 0)<span class="p num cr">{{ number_format($r['credit'], 2) }}</span><span class="s">{{ $c['cr'] }}</span>@else<span class="p nil">—</span><span class="s">&nbsp;</span>@endif</td>@endif
            @if($on('balance'))<td class="r"><span class="p bal num">{{ number_format(abs($bal), 2) }}</span><span class="tag {{ $bal > 0 ? 'dr' : ($bal < 0 ? 'cr' : 'nil') }}">{{ $c['tag'] }}</span></td>@endif
        </tr>
        @empty
        <tr><td colspan="{{ count($show) }}" style="text-align:center;height:auto;padding:20px;color:#94a3b8;">No transactions found for this period.</td></tr>
        @endforelse
    </tbody>
</table>
