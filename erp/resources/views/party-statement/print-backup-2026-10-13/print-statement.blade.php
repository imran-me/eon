<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Party Statement — {{ $party->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
    @page { size: A4 portrait; margin: 12mm; }

    :root {
        --tc:  #3730a3;
        --tbg: #f5f7ff;
        --thd: #4f46e5;
        --thd2: #564fc8;
        --bdr: #e2e8f0;
        --txt: #1e293b;
        --tfg: #837de4;
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
    .hdr { display: flex; justify-content: space-between; align-items: center; }
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

    /* ── Party info / Meta ── */
    .meta-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-bottom: 20px; }
    .bill-to h4 { font-family: var(--tf); color: var(--tc); font-size: 11px; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 5px; }
    .bill-to p { font-size: 13px; line-height: 1.7; margin: 1px 0; }
    .meta-tbl td { padding: 2px 0 2px 18px; font-weight: 600; font-size: 12px; }
    .meta-tbl .nf { font-family: var(--nf); font-size: 11px; }

    /* ── Summary strip ── */
    .summary-strip { display: flex; gap: 0; border: 1px solid var(--bdr); border-radius: 6px; overflow: hidden; margin-bottom: 20px; }
    .sum-card { flex: 1; padding: 10px 14px; border-right: 1px solid var(--bdr); }
    .sum-card-closing { border-right: none; position: relative; box-shadow: inset 0 0 0 9999px rgba(0,0,0,.02); }
    .sum-card-closing.tone-due      { background: linear-gradient(135deg, #d97706, #b45309); }
    .sum-card-closing.tone-advance  { background: linear-gradient(135deg, #2563eb, #1e40af); }
    .sum-card-closing.tone-settled  { background: linear-gradient(135deg, #475569, #1e293b); }
    .sum-label { font-size: 9px; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; font-weight: 600; margin-bottom: 3px; }
    .sum-card-closing .sum-label { color: rgba(255,255,255,.75); }
    .sum-value { font-family: var(--nf); font-size: 12px; font-weight: 700; color: var(--txt); }
    .sum-card-closing .sum-value { color: #fff; font-size: 14px; }
    .tone-tag { display: inline-block; margin-top: 5px; padding: 1px 8px; border-radius: 9px; font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    .sum-card-closing .tone-tag { background: rgba(255,255,255,.2); color: #fff; }
    .amt-tbl .grand.due .tone-tag     { background: #fef3c7; color: #b45309; }
    .amt-tbl .grand.advance .tone-tag { background: #dbeafe; color: #1e40af; }
    .amt-tbl .grand.settled .tone-tag { background: #f1f5f9; color: #475569; }

    /* ── Ledger table ── */
    .ledger { width: 100%; table-layout: fixed; border-collapse: collapse; margin-bottom: 24px; font-size: 11px; }
    .ledger th {
        background: var(--thd2); color: #fff;
        padding: 8px 10px; text-align: left;
        font-family: var(--tf); font-size: 9px;
        text-transform: uppercase; letter-spacing: .04em;
    }
    .ledger th.r { text-align: right; }
    .ledger td { padding: 6px 10px; border-bottom: 1px solid var(--bdr); vertical-align: middle; }
    .ledger td.r    { text-align: right; }
    .ledger td.date { white-space: nowrap; }
    .stage-badge { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 7.5px; font-weight: 600; background: #e0e7ff; color: #3730a3; }
    .exception-badge { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 7.5px; font-weight: 700; background: #fee2e2; color: #b91c1c; }
    .desc-flight { display: inline-block; font-size: 8px; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; }
    .desc-when { display: inline-block; font-size: 8px; color: #94a3b8; margin-left: 4px; }
    .desc-leg { display: flex; align-items: baseline; gap: 5px; line-height: 1.35; min-width: 0; }
    .desc-leg-route { font-weight: 700; color: #334155; font-size: 8.5px; flex-shrink: 0; }
    .desc-leg-meta { font-size: 8.5px; color: #64748b; flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 700; }
    .desc-tags { display: flex; align-items: center; flex-wrap: wrap; gap: 4px; margin-top: 2px; }
    .desc-category { display: inline-block; font-size: 8.5px; color: #b2910d; font-weight: 600; }
    .ledger td.mono { font-family: var(--nf); font-size: 10px; }
    .voucher-ben { font-family: var(--ff); font-size: 11px; line-height: 1.5; }
    .voucher-ben .vb-name { display: block; color: #3730a3; font-weight: 500; font-size: 11px; }
    .voucher-ben .vb-pass { display: block; font-family: var(--nf); font-size: 9.5px; color: #64748b; font-weight: 400; margin-top: 1px; }
    .ledger tbody tr:nth-child(even) td { background: #f8f9ff; }
    .ledger .opening-row td { font-style: italic; color: #64748b; }
    .ledger tr.sub-row td { background: #fff !important; border-bottom: 1px dashed var(--bdr); padding: 3px 10px 3px 22px; font-size: 10px; color: #64748b; }
    .ledger tfoot tr { color: #fff; box-shadow: 0 2px 10px rgba(49,46,129,.35); }
    .ledger tfoot td { padding: 12px 8px; font-weight: 700; border-bottom: none; font-family: var(--tf); font-size: 10px; text-transform: uppercase; letter-spacing: .04em; background: var(--tfg); overflow-wrap: anywhere; vertical-align: top; }
    /* overflow-wrap:anywhere on the row above still forces a break inside
       these cells even under white-space:nowrap (a long-standing Chrome
       quirk: overflow-wrap wins over nowrap once content would otherwise
       overflow) — that's why ৳700,000.00 kept splitting onto two lines
       despite nowrap being set right here. Resetting it back to normal on
       the numeric cells is what actually stops the wrap. */
    .ledger tfoot td.r { text-align: right; font-family: var(--nf); font-size: 9.5px; letter-spacing: normal; text-transform: none; white-space: nowrap; overflow-wrap: normal; }
    .ledger tfoot td.r.closing-cell { font-size: 10px; font-weight: 800; white-space: normal; overflow-wrap: anywhere; }

    /* ── Badges ── */
    .badge { display: inline-block; padding: 1px 7px; border-radius: 9px; font-size: 9px; font-weight: 600; }
    .badge-payment { background: #dcfce7; color: #166534; }
    .badge-invoice  { background: #dbeafe; color: #1e40af; }
    .badge-default  { background: #f1f5f9; color: #475569; }

    .debit      { color: #dc2626; font-weight: 600; }
    .credit     { color: #16a34a; font-weight: 600; }
    .balance-dr { color: #dc2626; font-weight: 700; }
    .balance-cr { color: #16a34a; font-weight: 700; }

    /* ── Sub-header bar ── */
    .sh { background: var(--thd2); color: #fff; padding: 5px 12px; font-family: var(--tf); font-size: 10px; font-weight: 600; margin-bottom: 8px; letter-spacing: .03em; }

    /* ── Bottom grid ── */
    .bot { display: grid; grid-template-columns: 1.3fr 0.7fr; gap: 30px; }
    .nb { font-size: 12px; line-height: 1.8; color: #475569; margin-bottom: 14px; }
    .amt-tbl { width: 100%; border-collapse: collapse; }
    .amt-tbl td { padding: 6px 0; border-bottom: 1px solid #eee; font-size: 12px; }
    .amt-tbl td.n { font-family: var(--nf); font-size: 11px; text-align: right; }
    .amt-tbl .grand td { font-weight: 700; color: var(--tc); font-size: 13px; border-bottom: 2px solid var(--thd); }
    .amt-tbl .grand.due td     { color: #b45309; }
    .amt-tbl .grand.advance td { color: #1e40af; }
    .amt-tbl .dr-row td { color: #dc2626; font-weight: 600; }
    .amt-tbl .cr-row td { color: #16a34a; font-weight: 600; }

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
            <img src="{{ $logoSrc }}" alt="" style="width:190px; height:auto; max-height:190px; object-fit:contain; margin-left:-16px; display:block;">
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

    @php
        $selection = $selection ?? 0;
        // Neutral wording — this statement is printed for the PARTY, so
        // "Receivable"/"Payable" (which side owes which) is our internal
        // accounting language, not theirs. "Due"/"Advance" states the fact
        // of the balance without taking a side.
        $closingLabel = $closingBalance > 0 ? 'Due' : ($closingBalance < 0 ? 'Advance' : 'Settled');
        $closingTone = $closingBalance > 0 ? 'due' : ($closingBalance < 0 ? 'advance' : 'settled');
    @endphp
    {{-- Label bar ── --}}
    <div class="label-bar">Party Statement of Account{{ $selection ? ' — Selected Entries' : '' }}</div>

    {{-- Party info / Meta ── --}}
    <div class="meta-row">
        <div class="bill-to">
            <h4>Party Details</h4>
            <p><strong>{{ $party->name }}</strong></p>
            @if($party->phone)<p style="font-family:'Space Mono',monospace;font-size:11px;">{{ $party->phone }}</p>@endif
            @if($party->email)<p>{{ $party->email }}</p>@endif
            @if($party->address)<p>{{ $party->address }}</p>@endif
        </div>
        <div>
            <table class="meta-tbl">
                <tr><td>Party ID</td><td class="nf">{{ $partyCode }}</td></tr>
                <tr>
                    <td>Period</td>
                    <td class="nf">
                        @if($selection)
                            {{ $selection }} selected entr{{ $selection === 1 ? 'y' : 'ies' }}
                        @elseif($dateFrom)
                            {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }} → {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}
                        @else
                            All Time
                        @endif
                    </td>
                </tr>
                <tr><td>Generated</td><td class="nf">{{ now()->format('d M Y') }}</td></tr>
                <tr><td>Printed By</td><td>{{ Auth::user()->name }}</td></tr>
            </table>
        </div>
    </div>

    {{-- Summary strip ── --}}
    <div class="summary-strip">
        <div class="sum-card">
            <div class="sum-label">{{ $selection ? 'Entries selected' : 'Opening Balance' }}</div>
            <div class="sum-value">{{ $selection ? $selection : '৳' . number_format($openingBalance, 2) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Total Debit (Dr)</div>
            <div class="sum-value" style="color:#dc2626;">৳{{ number_format($totalDebit, 2) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Total Credit (Cr)</div>
            <div class="sum-value" style="color:#16a34a;">৳{{ number_format($totalCredit, 2) }}</div>
        </div>
        <div class="sum-card sum-card-closing tone-{{ $closingTone }}">
            <div class="sum-label">{{ $selection ? 'Balance after last selected entry' : 'Closing Balance' }}</div>
            <div class="sum-value">
                ৳{{ number_format(abs($closingBalance), 2) }}
                {{ $closingBalance > 0 ? 'Dr' : ($closingBalance < 0 ? 'Cr' : '') }}
            </div>
            <div class="tone-tag">{{ $closingLabel }}</div>
        </div>
    </div>

    {{-- Ledger table ── --}}
    <table class="ledger">
        <colgroup>
            <col style="width:8%">
            <col style="width:15%">
            <col style="width:25%">
            <col style="width:9%">
            <col style="width:10%">
            <col style="width:10%">
            <col style="width:15%">
        </colgroup>
        <thead>
            <tr>
                <th>Date</th>
                <th>Passenger</th>
                <th>Description</th>
                <th>Type</th>
                <th class="r">Debit (৳)</th>
                <th class="r">Credit (৳)</th>
                <th class="r">Balance (৳)</th>
            </tr>
        </thead>
        <tbody>
            @if($openingBalance != 0 && ! $selection)
            <tr class="opening-row">
                <td class="date">{{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d M y') : '' }}</td>
                <td>—</td>
                <td colspan="2">Opening Balance Brought Forward</td>
                <td class="r">—</td>
                <td class="r">—</td>
                <td class="r mono">{{ number_format(abs($openingBalance), 2) }} Dr</td>
            </tr>
            @endif

            @forelse($transactions as $t)
            @php
                $badgeCls = match($t->type) {
                    'party_payment','party_payment_reversal','customer_advance_received_reversal','supplier_advance_paid_reversal','bank_transfer' => 'badge-payment',
                    'party_invoice','ticket_sale','sale' => 'badge-invoice',
                    default => 'badge-default'
                };
                $badgeLabel = match($t->type) {
                    'contract_file_client'=>'File process', 'contract_file'=>'File process cost', 'contract_file_service_cost'=>'File service cost',
                    'contract_file_client_adjustment'=>'File process adjustment', 'contract_file_vendor_adjustment'=>'File cost adjustment',
                    'party_payment' => 'Payment',
                    'party_payment_reversal' => 'Payment reversal',
                    'customer_advance_received_reversal' => 'Customer advance reversal',
                    'supplier_advance_paid_reversal' => 'Supplier advance reversal',
                    'contract_flight_service_cost' => 'Flight service cost',
                    'contract_flight_passenger_agent' => 'Flight process',
                    'contract_flight_passenger_reissue' => 'Reissue process',
                    'contract_flight_passenger_agent_reversal' => 'Package reversal',
                    'vendor_refund' => 'Refund received',
                    'contract_flight_airline_penalty' => 'Airline penalty',
                    'contract_flight_fare_difference' => 'Fare difference',
                    'party_invoice' => 'Invoice',
                    'bank_transfer' => 'Transfer',
                    'ticket_sale'   => 'Ticket',
                    default => ucfirst(str_replace('_',' ',$t->type))
                };
                $bal = (float)$t->balance;
            @endphp
            @php
                $chargePax   = (in_array($t->type, ['contract_flight_passenger_agent', 'contract_flight_passenger_reissue'], true) && $t->invoice_id)
                    ? ($flightChargePassengers[$t->invoice_id] ?? null)
                    : null;
                $beneficiary = $chargePax?->passportHolder;
                // Flight-process rows: show the first leg's travel date, not the
                // payment date — the agent cares when the passenger actually flies.
                $firstLegDate = $chargePax
                    ? optional($chargePax->ticketPurchases->sortBy('leg_sequence')->first()?->legs->first())->travel_date
                    : null;
                $rowDate = $firstLegDate ?: $t->payment_date;
            @endphp
            <tr>
                <td class="date">{{ \Carbon\Carbon::parse($rowDate)->format('d M y') }}</td>
                <td>
                    @if($beneficiary)
                    <div class="voucher-ben">
                        <span class="vb-name">{{ $beneficiary->name ?? '—' }}</span>
                        <span class="vb-pass">{{ $beneficiary->passport_no ?? '—' }}</span>
                    </div>
                    @endif
                </td>
                <td>
                    @if($chargePax)
                        @php
                            // One line per linked ticket (DAC→IST, IST→ECN, ...) — each
                            // leg is its own airline (Ticket::legsArray() is per-sector,
                            // not one airline for the whole route), matched to this
                            // purchase by leg_sequence. Falls back to the flight's own
                            // nominal route when no ticket is linked yet (e.g. mid-reissue).
                            $legLines = [];
                            foreach ($chargePax->ticketPurchases->sortBy('leg_sequence') as $tp) {
                                $legsArr = $tp->ticket?->legsArray() ?: [];
                                $legInfo = $legsArr[max(0, ($tp->leg_sequence ?: 1) - 1)] ?? null;
                                $airlineName = $legInfo['airline'] ?? $tp->ticket?->airline?->name ?? null;
                                // "Gulf Air (GF)" -> "GF" — the code alone is enough
                                // here and leaves room for the date not to get
                                // ellipsis-truncated out of the line entirely.
                                $airline = ($airlineName && preg_match('/\(([^)]+)\)\s*$/', $airlineName, $m)) ? $m[1] : $airlineName;
                                $date = optional($tp->legs->first())->travel_date;

                                // Flight numbers are stored per-leg, same order as the
                                // route's legs — [GF251, GF45, PC1982] for a 3-leg route.
                                // Match this ticket's own leg_sequence, not always index 0,
                                // else every ticket line shows the first flight's number.
                                $flightNos = $chargePax->contractFlight?->airline_flight_no ?: [];
                                $flightNos = is_array($flightNos) ? $flightNos : explode(',', $flightNos);
                                $legFlightNo = trim($flightNos[max(0, ($tp->leg_sequence ?: 1) - 1)] ?? ($flightNos[0] ?? ''));

                                $meta = [$tp->ticket_no ?: '—'];
                                if ($airline) $meta[] = $airline;
                                if ($legFlightNo) $meta[] = $legFlightNo;

                                $legLines[] = [
                                    'from' => $legInfo['from_code'] ?? $tp->ticket?->from_airport?->code ?? '—',
                                    'to' => $legInfo['to_code'] ?? $tp->ticket?->to_airport?->code ?? '—',
                                    'meta' => implode(' · ', $meta),
                                ];

                            }
                            @endphp
                            @if(count($legLines))
                            @foreach($legLines as $leg)
                            <div class="desc-leg">
                                <span class="desc-leg-route">{{ $leg['from'] }}→{{ $leg['to'] }}</span>
                                <span class="desc-leg-meta">{{ $leg['meta'] }}</span>
                            </div>
                            @endforeach
                        @else
                        <span class="desc-flight">{{ $chargePax->contractFlight?->flight_number ?: 'Contract flight' }}</span>
                        @if($chargePax->contractFlight?->departure_at)
                        <span class="desc-when">{{ $chargePax->contractFlight->departure_at->format('d M Y, h:i A') }}</span>
                        @endif
                        @endif
                        <div class="desc-tags">
                            @if($chargePax->flightCategory?->name)
                            <span class="desc-category">{{ $chargePax->flightCategory->name }}</span>
                            @endif
                            <span class="stage-badge">{{ \App\Models\ContractFlightPassenger::stageLabel($chargePax->stage) }}</span>
                            @if($chargePax->exception_flag)
                            <span class="exception-badge">&#9888; {{ \App\Models\ContractFlightPassenger::EXCEPTION_FLAGS[$chargePax->exception_flag] ?? ucfirst(str_replace('_',' ',$chargePax->exception_flag)) }}</span>
                            @endif
                        </div>
                    @else
                    {{ Str::limit($t->display_remarks, 60) }}
                    @endif
                </td>
                <td><span class="badge {{ $badgeCls }}">{{ $badgeLabel }}</span></td>
                <td class="r {{ $t->debit  > 0 ? 'debit'  : '' }}">{{ $t->debit  > 0 ? number_format($t->debit,  2) : '—' }}</td>
                <td class="r {{ $t->credit > 0 ? 'credit' : '' }}">{{ $t->credit > 0 ? number_format($t->credit, 2) : '—' }}</td>
                <td class="r {{ $bal > 0 ? 'balance-dr' : ($bal < 0 ? 'balance-cr' : '') }}">
                    {{ number_format(abs($bal), 2) }} {{ $bal > 0 ? 'Dr' : ($bal < 0 ? 'Cr' : '') }}
                </td>
            </tr>
            @if($t->type === 'ticket_sale' && $t->invoice_id && isset($ticketSaleItems[$t->invoice_id]))
                @foreach($ticketSaleItems[$t->invoice_id]->items as $item)
                @php
                    $tp    = $item->ticketPurchase;
                    $from  = $tp?->ticket?->from_airport?->code;
                    $to    = $tp?->ticket?->to_airport?->code;
                    $route = ($from || $to) ? (($from ?: '—') . ' → ' . ($to ?: '—')) : '—';
                @endphp
                <tr class="sub-row">
                    <td colspan="3">
                        &#8618; <span class="mono">{{ $tp?->ticket_no ?? '—' }}</span>
                        &middot; {{ $tp?->passportHolder?->name ?? '—' }}
                        &middot; {{ $tp?->ticket?->title ?? '—' }}
                        &middot; {{ $route }}
                    </td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="r mono">{{ number_format((float) $item->price, 2) }}</td>
                </tr>
                @endforeach
            @endif
            @empty
            <tr>
                <td colspan="7" style="text-align:center;padding:20px;color:#94a3b8;">No transactions found for this period.</td>
            </tr>
            @endforelse
        </tbody>
        <!-- <tfoot>
            <tr>
                <td colspan="4">
                    Closing Balance — {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d M Y') : now()->format('d M Y') }}
                </td>
                <td class="r">৳{{ number_format($totalDebit, 2) }}</td>
                <td class="r">৳{{ number_format($totalCredit, 2) }}</td>
                <td class="r closing-cell">
                    ৳{{ number_format(abs($closingBalance), 2) }}
                    {{ $closingBalance > 0 ? 'Dr' : ($closingBalance < 0 ? 'Cr' : '') }}
                    <span style="display:block; font-size:8px; font-weight:600; text-transform:uppercase; letter-spacing:.05em; opacity:.8; margin-top:2px;">{{ $closingLabel }}</span>
                </td>
            </tr>
        </tfoot> -->
    </table>

    {{-- Bottom grid ── --}}
    <div class="bot">
        <div>
            <div class="sh">Closing Balance In Words:</div>
            <div class="nb">
                @php
                    try { $words = ucfirst((new \NumberToWords\NumberToWords())->getNumberTransformer('en')->toWords((int) abs($closingBalance))) . ' Taka'; }
                    catch (\Throwable) { $words = '৳' . number_format(abs($closingBalance), 2); }
                @endphp
                {{ $words }}
                ({{ $closingLabel }})
            </div>

            <div class="sh">Note:</div>
            <div class="nb" style="font-size:12px; color:#1e293b; padding:8px 10px; background:#fffbeb; border:1px dashed #f59e0b; border-radius:4px;">
                {{ $note }}
            </div>
        </div>
        <div>
            <div class="sh">Summary:</div>
            <table class="amt-tbl">
                <tr><td>Opening Balance</td><td class="n">৳{{ number_format($openingBalance, 2) }}</td></tr>
                <tr class="dr-row"><td>Total Debit (Dr)</td><td class="n">৳{{ number_format($totalDebit, 2) }}</td></tr>
                <tr class="cr-row"><td>Total Credit (Cr)</td><td class="n">৳{{ number_format($totalCredit, 2) }}</td></tr>
                <tr class="grand {{ $closingTone }}">
                    <td>Closing Balance <span class="tone-tag">{{ $closingLabel }}</span></td>
                    <td class="n">
                        ৳{{ number_format(abs($closingBalance), 2) }}
                        {{ $closingBalance > 0 ? 'Dr' : ($closingBalance < 0 ? 'Cr' : '') }}
                    </td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Signature ── --}}
    <div class="sig">
        <div class="sig-c">
            <x-signature :company="$company" />
            <div class="sig-l">Authorized Signatory</div>
            <div style="font-size:10px;margin-top:3px;">For, {{ optional($company)->name ?? config('app.name') }}</div>
        </div>
    </div>

</div>

<script>window.onload = function () { window.print(); };</script>
</body>
</html>
