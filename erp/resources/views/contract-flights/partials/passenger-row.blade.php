{{-- One passenger row of the mfp-table. Expects $p (with passportHolder,
     agent, flightCategory.categoryType, ticketPurchases.ticket), $flight,
     $sl, and $role.
     $mode: 'list' (Flights & Passengers: actions column, KPI tint) or
     'due' (Flight Accounts: ledger received/due under the charge, no
     actions). $ledger = ['received' => , 'due' => ] in due mode.
     $rowAttrs: extra attributes for the <tr>. --}}
@php
    // Formatting helpers, defined here because an @include cannot hand
    // variables back to its caller.
        $mfpPartyUrl    = route('role.party-statement.index', ['role' => $role]);
        $mfpTicketsUrl  = route('role.ticket-direct-sale.index', ['role' => $role]);
        $mfpPassportUrl = route('role.passport-holder.index', ['role' => $role]);
        $mfpFlagBase    = asset('flags');
        $mfpMoney = fn ($v) => ((float) $v < 0 ? '−' : '') . '৳' . number_format(abs((float) $v), 0);
        $mfpFlag = function ($airport) use ($mfpFlagBase) {
            $code = strtolower((string) ($airport?->country?->code ?? ''));
            return $code ? '<img class="mfp-flag" src="' . e($mfpFlagBase . '/' . $code . '.png') . '" alt="" loading="lazy" onerror="this.style.display=\'none\'">' : '';
        };
        $mfpCostIcons = ['Tickets' => 'fa-ticket', 'Manpower' => 'fa-users', 'Boarding' => 'fa-plane-up', 'Immigration' => 'fa-stamp', 'Card fee' => 'fa-id-card', 'Reissue' => 'fa-rotate'];
@endphp
                        @php
                            $holder     = $p->passportHolder;
                            $tickets    = $p->ticketPurchases;
                            $ticketCost = $tickets->where('status', '!=', 'cancelled')->sum('amount');
                            $costs = array_filter([
                                ['Tickets', $ticketCost], ['Extra baggage', $p->baggageTotals()['cost'], 'fa-suitcase'], ['Manpower', $p->manpower_cost], ['Boarding', $p->boarding_cost],
                                ['Immigration', $p->immigration_cost], ['Card fee', $p->manpower_card_fee],
                                [$p->other_title ?: 'Other', $p->other_cost, 'fa-receipt'],
                                ['Suspend', $p->suspendWithdrawTotals()['cost'], 'fa-unlock', 'Suspend withdraw — paid to the vendor who lifted the ticket suspension'],
                                ['Reissue', $p->reissue_cost, 'fa-rotate', 'Recorded separately — not part of travel cost'],
                                // Charges for his moves between flights (2026-09-27): the agent's
                                // part is added to Agent Charge, ours to the cost.
                                ['Penalty', ($mfpPen = $p->penaltyTotals())['billed'], 'fa-gavel', 'Cancellation / reissue / reschedule charge billed to the agent — in Agent Charge'],
                                ['Penalty (ours)', $mfpPen['borne'], 'fa-gavel', 'Cancellation / reissue / reschedule charge we absorb — in the cost'],
                            ], fn ($c) => is_numeric($c[1]) && (float) $c[1] != 0);
                            $profit = $p->profit;
                        @endphp
                        @php
                            $p->setRelation('contractFlight', $flight);
                            // The KPI tint only exists where the cards do (Flights & Passengers).
                            $kpiKey = (isset($paxKpis) && isset($mfpTones)) ? $p->kpiKey() : null;
                            $kpiTone = $kpiKey ? $mfpTones[$paxKpis[$kpiKey]['tone']] : null;
                            $ledger = $ledger ?? null;   // ['received' => , 'due' => ] in 'due' mode
                            $rowAttrs = $rowAttrs ?? '';
    // Per-flight bits, derived here so the partial needs nothing but $p and $flight.
    $fromAirport = $flight?->ticket?->from_airport;
    $toAirport   = $flight?->ticket?->to_airport;
    $stripCode   = fn ($name, $fallback) => preg_replace('/\s*\([A-Z0-9]{3,4}\)\s*$/', '', $name ?: $fallback);
    $fromName    = $stripCode($fromAirport?->name, 'Origin pending');
    $toName      = $stripCode($toAirport?->name, 'Destination pending');
    $flightLine  = ($flight?->flight_number ?? '—') . ' · ' . (optional($flight?->airline)->name ?? 'Airline pending') . ' · ' . ($flight?->departure_at ? $flight->departure_at->format('d M Y, h:i A') : 'Date not set');
    // The flight link opens the View modal where it exists (Flights &
    // Passengers); elsewhere it goes to that page filtered to the flight.
    $flightHref  = ($mode ?? 'list') === 'list' ? '#' : route('role.contract-flights.index', ['role' => $role, 'search' => $flight?->flight_number]);
    $flightClick = ($mode ?? 'list') === 'list' ? 'viewFlight(' . (int) $flight?->id . ');return false;' : '';
                        @endphp
                        <tr class="cf-row mfp-row" id="contract-flight-passenger-{{ $p->id }}" data-print-date="{{ $flight?->departure_at?->format('Y-m-d') }}" data-print-category="{{ $p->flightCategory?->name ?? 'Uncategorized' }}"{!! $kpiTone ? ' style="--mfp-a:' . $kpiTone[0] . ';--mfp-b:' . $kpiTone[1] . ';--mfp-ring:' . $kpiTone[7] . '" title="' . e($paxKpis[$kpiKey]['label']) . '"' : '' !!}{!! $rowAttrs ? ' ' . $rowAttrs : '' !!}>
                            <td class="mfp-sl">{{ $sl }}</td>
                            {{-- Passenger and Agent, the Tickets column's way (owner, 2026-10-03:
                                 "minimal yet informational, noise free"): no "Passport:" /
                                 "Category:" labels — the value says what it is, the tooltip
                                 names it; an empty value ("Type: —") is not drawn at all. The
                                 flight moved to the Route cell, beside the date it flies.
                                 Twin: ledgerPassengerCells() in dashboard-ledger-script. --}}
                            @php
                                $mfpCat = $p->flightCategory?->name;
                                $mfpHandling = \App\Models\ContractFlightPassenger::HANDLING_TYPES[$p->handling_type] ?? ($p->handling_type ?: null);
                                $mfpClassTxt = \App\Models\ContractFlightPassenger::TICKET_CLASSES[$p->ticket_class] ?? ucfirst($p->ticket_class ?: 'economy');
                                $mfpType = $p->flightCategory?->categoryType?->name;
                                // Who sold each live ticket, in leg order, once per seller
                                // (owner, 2026-10-03: "two vendor here, in small, with their
                                // code"). A vendor carries EP-VN-###; a portal has no code.
                                $mfpSellers = $p->ticketPurchases->where('status', '!=', 'cancelled')
                                    ->sortBy(fn ($t) => [$t->leg_sequence ?: 99, $t->id])
                                    ->map(fn ($t) => $t->vendor_id
                                        ? ['key' => 'v' . $t->vendor_id, 'id' => $t->vendor_id, 'name' => $t->vendor?->name ?? 'Vendor', 'code' => 'EP-VN-' . str_pad($t->vendor_id, 3, '0', STR_PAD_LEFT), 'pnr' => $t->ticket_no]
                                        : ($t->portal_id ? ['key' => 'p' . $t->portal_id, 'id' => null, 'name' => $t->portal?->name ?? 'Portal', 'code' => 'Portal', 'pnr' => $t->ticket_no] : null))
                                    ->filter()->groupBy('key')
                                    ->map(fn ($g) => $g->first() + ['pnrs' => $g->pluck('pnr')->filter()->implode(', ')])->values();
                            @endphp
                            <td>
                                @if($holder?->name)
                                    <a class="mfp-passenger mfp-name" href="{{ $mfpPassportUrl }}?name={{ urlencode($holder->name) }}" title="Open {{ $holder->name }}'s passport record">{{ $holder->name }}</a>
                                @else
                                    <strong class="mfp-name">Passenger not assigned</strong>
                                @endif
                                <div class="mfp-ln mfp-mono" title="Passport"><i class="fas fa-passport mfp-ico"></i>{{ $holder?->passport_no ?? '—' }}</div>
                                @if($mfpCat || $mfpHandling)
                                    <div class="mfp-ln" title="Category: {{ $mfpCat ?? '—' }} · Handling: {{ $mfpHandling ?? '—' }}">{{ implode(' · ', array_filter([$mfpCat, $mfpHandling])) }}</div>
                                @endif
                            </td>
                            <td>
                                @if($p->agent)
                                    <a class="mfp-party mfp-name" href="{{ $mfpPartyUrl }}?party_id={{ $p->agent->id }}" title="Open {{ $p->agent->name }}'s statement"><i class="fas fa-user-tie mfp-agent-ico" aria-hidden="true"></i>{{ $p->agent->name }}</a>
                                @else
                                    <strong class="mfp-party mfp-name"><i class="fas fa-user-tie mfp-agent-ico" aria-hidden="true"></i>Direct</strong>
                                @endif
                                <div class="mfp-ln" title="{{ $p->agent ? 'Agent ID · ' : '' }}Class{{ $mfpType ? ' · Type' : '' }}">@if($p->agent)<a class="mfp-mono" href="{{ $mfpPartyUrl }}?party_id={{ $p->agent->id }}">EP-AG-{{ str_pad($p->agent->id, 3, '0', STR_PAD_LEFT) }}</a><span class="mfp-tk-sep">·</span>@endif{{ $mfpClassTxt }}@if($mfpType)<span class="mfp-tk-sep">·</span>{{ $mfpType }}@endif</div>
                                @foreach($mfpSellers->take(2) as $v)
                                    <div class="mfp-ln mfp-seller" title="Ticket seller{{ $v['pnrs'] ? ' · ' . $v['pnrs'] : '' }}"><i class="fas fa-store mfp-ico"></i>@if($v['id'])<a class="mfp-seller-name" href="{{ $mfpPartyUrl }}?party_id={{ $v['id'] }}">{{ $v['name'] }}</a>@else<span class="mfp-seller-name">{{ $v['name'] }}</span>@endif<span class="mfp-seller-code">{{ $v['code'] }}</span></div>
                                @endforeach
                                @if($mfpSellers->count() > 2)<div class="mfp-ln" title="{{ $mfpSellers->slice(2)->pluck('name')->implode(', ') }}">… +{{ $mfpSellers->count() - 2 }} more</div>@endif
                            </td>
                            @php
                                $legsNeeded = $p->ticketsRequired();
                                $legsDone = $p->confirmedTicketCount();
                                // Cancelled PNRs fill no slot (ticketSlots()); on a via route they are
                                // listed under the slots instead, so an old ticket cancelled at 0 by a
                                // re-booking (2026-09-26) is still visible on his row.
                                // Cancelled / refunded tickets: those still linked, plus those a change row
                                // names — a cancelled PNR is unlinked since 2026-09-30, and without this an
                                // offloaded passenger's column said only "No PNR yet" (owner, 2026-10-10).
                                $mfpCancelled = $tickets->where('status', 'cancelled');
                                try {
                                    $mfpHistIds = $p->relationLoaded('flightChanges') || \App\Models\ContractFlightPassengerChange::installed()
                                        ? $p->flightChanges->flatMap(fn ($c) => array_map('intval', (array) ($c->previous_ticket_purchase_ids ?? [])))->unique()->diff($tickets->pluck('id'))->values()
                                        : collect();
                                    if ($mfpHistIds->isNotEmpty()) {
                                        $mfpCancelled = $mfpCancelled->concat(\App\Models\TicketPurchase::whereIn('id', $mfpHistIds)->where('status', 'cancelled')->get());
                                    }
                                } catch (\Throwable) {}
                                // Two tickets (a via route): each ticket and each leg is drawn on its
                                // own line and the per-PNR class / money lines go — the cost column
                                // has the total (owner, 2026-09-21). One ticket: the full detail.
                                $mfpSlots = $legsNeeded > 1 ? $p->ticketSlots() : [];
                                $mfpSlotState = function ($slot) {
                                    if ($slot['done']) return ['confirm', 'purchased'];
                                    if ($slot['purchase_id']) return [$slot['status'] ?? 'pending', $slot['status'] ?? 'pending'];
                                    $h = $slot['hold'] ?? null;
                                    if ($h) return [$h['status'] === 'booked' ? 'hold' : ($h['status'] === 'expired' ? 'cancelled' : 'pending'),
                                        $h['status'] === 'booked' ? 'booked · till ' . ($h['expires_at'] ?: '—') : ($h['status'] === 'expired' ? 'hold expired' : $h['status'])];
                                    return ['', 'not ticketed'];
                                };
                            @endphp
                            <td>
                                @if($mfpSlots)
                                    @foreach($mfpSlots as $slot)
                                        @php [$stCls, $stTxt] = $mfpSlotState($slot); @endphp
                                        @php
                                            // A ticket in TWO lines, the legs joined by a journey line
                                            // (owner, 2026-10-03: "reduce lines"). The dot replaces the
                                            // "1 / 2 ●" pill: green tick = purchased, empty ring = not; the airline
                                            // shrinks to its code, full name in the tooltip; the year
                                            // only shows when it is not this year.
                                            $mfpAirCode = $slot['airline'] && preg_match('/\(([A-Z0-9]{2,3})\)\s*$/', $slot['airline'], $mfpM) ? $mfpM[1] : $slot['airline'];
                                            $mfpSector = ($slot['from_code'] && $slot['to_code']) ? $slot['from_code'] . ' → ' . $slot['to_code'] : ($slot['sector'] ?: $slot['label'] . ' route');
                                            $legDate = $slot['travel_date'] ? \Carbon\Carbon::parse($slot['travel_date']) : ($slot['index'] === 1 ? $flight?->departure_at : null);
                                            $mfpHasTime = $legDate && (! $slot['travel_date'] || (strlen((string) $slot['travel_date']) > 10 && $legDate->format('H:i') !== '00:00'));
                                            $mfpDateTxt = $legDate ? $legDate->format(($legDate->isCurrentYear() ? 'd M' : 'd M Y') . ($mfpHasTime ? ', h:i A' : '')) : null;
                                            // The group suffix " (a)" (pnr-is-shared-by-the-group) is kept but
                                            // drawn small and grey, so the airline PNR itself reads first.
                                            [$mfpPnrMain, $mfpPnrSfx] = preg_match('/^(.*?)\s*(\([a-z]{1,2}\))$/', (string) $slot['ticket_no'], $mfpPm) ? [$mfpPm[1], $mfpPm[2]] : [$slot['ticket_no'], null];
                                            $mfpTip = 'Ticket ' . $slot['index'] . ' of ' . $legsNeeded . ' · ' . $stTxt . ' · ' . $mfpSector . ($slot['airline'] ? ' · ' . $slot['airline'] : '') . ($legDate ? ' · ' . $legDate->format($mfpHasTime ? 'd M Y, h:i A' : 'd M Y') : '');
                                        @endphp
                                        <div class="mfp-tk{{ $slot['done'] ? ' done' : ' mfp-ticket-missing' }}" title="{{ $mfpTip }}">
                                            <span class="mfp-tk-dot">@if($slot['done'])<i class="fas fa-check"></i>@endif</span>
                                            <div class="mfp-tk-l1">
                                                @if($slot['ticket_no'])
                                                    <a class="mfp-pnr" href="{{ $mfpTicketsUrl }}?ticket_no={{ urlencode($slot['ticket_no']) }}" title="Open {{ $slot['ticket_no'] }} in Ticketing">{{ $mfpPnrMain }}@if($mfpPnrSfx)<span class="mfp-tk-sfx">{{ $mfpPnrSfx }}</span>@endif</a>
                                                @elseif(($slot['hold']['reference'] ?? null))
                                                    <span class="mfp-pnr">{{ $slot['hold']['pnr'] ?: $slot['hold']['reference'] }}</span>
                                                @else
                                                    <span class="mfp-pnr">No PNR yet</span>
                                                @endif
                                                {{-- PNR sits in a fixed-width track so every airline pill starts
                                                     at the same x down the column (owner, 2026-10-03). The logo
                                                     is put in by passenger-sheet-list's airlineBrand() lookup —
                                                     no logo found = the code alone. --}}
                                                <span class="mfp-tk-air"@if($slot['airline']) data-airline="{{ $slot['airline'] }}" title="{{ $slot['airline'] }}"@endif>{{ $mfpAirCode }}</span>
                                            </div>
                                            <div class="mfp-tk-l2">@if($slot['from_code'] && $slot['to_code']){{ $slot['from_code'] }}<i class="fas fa-plane mfp-tk-plane"></i>{{ $slot['to_code'] }}@else{{ $mfpSector }}@endif @if($mfpDateTxt)<span class="mfp-tk-sep">·</span><b>{{ $mfpDateTxt }}</b>@endif</div>
                                            {{-- Not bought yet: its state on its own line, so a long tag
                                                 ("booked · till 05 Oct") never squeezes the airline pill.
                                                 Plain "not ticketed" is not drawn — "No PNR yet" already
                                                 says it (owner, 2026-10-03). --}}
                                            @unless($slot['done'] || $stTxt === 'not ticketed')<div class="mfp-tk-l2"><span class="mfp-tk-tag {{ $stCls }}">{{ $stTxt }}</span></div>@endunless
                                            @if($mfpWas = \App\Models\ContractFlightPassengerChange::wasDatesLine($slot['ticket_no']))
                                                <div class="mfp-tk-l2 mfp-tk-was" title="Earlier date(s) of this ticket"><i class="fas fa-clock-rotate-left"></i> {{ $mfpWas }}</div>
                                            @endif
                                        </div>
                                    @endforeach
                                    @foreach($mfpCancelled as $t)
                                        <small class="mfp-clip" title="{{ $t->cancel_note ?: 'Cancelled in Ticketing' }}"><i class="fas fa-ban mfp-ico"></i>Cancelled: {{ $t->ticket_no ?: '#' . $t->id }}@if($t->refund_amount !== null) · refund ৳{{ number_format((float) $t->refund_amount) }}@if((float) $t->amount > (float) $t->refund_amount) · penalty ৳{{ number_format((float) $t->amount - (float) $t->refund_amount) }}@endif @else at ৳0 @endif</small>
                                    @endforeach
                                @else
                                @forelse($tickets as $t)
                                    <div class="mfp-ticket">
                                        @if($t->ticket_no)
                                            <a class="mfp-pnr" href="{{ $mfpTicketsUrl }}?ticket_no={{ urlencode($t->ticket_no) }}" title="Open {{ $t->ticket_no }} in Ticketing">{{ $t->ticket_no }}</a>
                                        @else
                                            <span class="mfp-pnr">PNR pending</span>
                                        @endif
                                        <span class="mfp-status{{ $t->status === 'cancelled' ? ' cancelled' : ($t->status === 'confirm' ? ' confirm' : '') }}">{{ $t->status ?? 'pending' }}</span>
                                        @if($t->status === 'cancelled' && $t->cancel_note)
                                            <small class="mfp-clip" title="{{ $t->cancel_note }}"><i class="fas fa-ban mfp-ico"></i>{{ $t->cancel_note }}</small>
                                        @endif
                                        {{-- Route line dropped (owner, 2026-09-22): the Route column
                                             beside this one already carries it, and the same line was
                                             removed from the dashboard ledger for the same reason. --}}
                                        {{-- One line each, ellipsised, full text in the tooltip —
                                             the same treatment the Passenger Ledger gives them.
                                             Without it the money line wrapped to three. --}}
                                        @php $mfpClass = implode(' · ', array_filter([$t->ticket_class, $t->trip_type])) ?: '—'; @endphp
                                        @if($mfpWas = \App\Models\ContractFlightPassengerChange::wasDatesLine($t->ticket_no))
                                            <small class="mfp-clip" style="color:#94a3b8;" title="Earlier date(s) of this ticket"><i class="fas fa-clock-rotate-left mfp-ico"></i>{{ $mfpWas }}</small>
                                        @endif
                                        <small class="mfp-clip" title="Class / Trip: {{ $mfpClass }}"><i class="fas fa-chair mfp-ico"></i>Class / Trip: {{ $mfpClass }}</small>
                                        <small class="mfp-clip" title="Cost: {{ $mfpMoney($t->amount) }} · Paid: {{ $mfpMoney($t->paid_amount) }} · Due: {{ $mfpMoney($t->due_amount) }}"><i class="fas fa-coins mfp-ico"></i>Cost: <span class="mfp-cost">{{ $mfpMoney($t->amount) }}</span> · Paid: <span class="mfp-clear">{{ $mfpMoney($t->paid_amount) }}</span> · Due: <span class="mfp-due">{{ $mfpMoney($t->due_amount) }}</span></small>
                                    </div>
                                @empty
                                    <small>No tickets linked</small>
                                @endforelse
                                @endif
                            </td>
                            @if(($mode ?? 'list') === 'list')
                            <td>
                                <span class="mfp-stage">{{ \App\Models\ContractFlightPassenger::stageLabel($p->stage) }}</span>
                                @if($p->exception_flag)
                                    <small class="mfp-flag-tag"><i class="fas fa-triangle-exclamation"></i>{{ \App\Models\ContractFlightPassenger::EXCEPTION_FLAGS[$p->exception_flag] ?? $p->exception_flag }}</small>
                                @endif
                                @if($p->contract_confirmed_at)
                                    <small class="mfp-clear"><i class="fas fa-file-signature mfp-ico"></i>Contract confirmed</small>
                                @endif
                            </td>
                            @endif
                            {{-- Route in three short lines (owner, 2026-10-03): flags and codes,
                                 the date, then the flight (moved here from Passenger — it is
                                 the batch that flies on that date) and a "via" chip. Airport
                                 names and the "was CF-…" history live in the tooltips.
                                 Twin: routeCell in dashboard-ledger-script. --}}
                            @php
                                // routePath() is null on a direct route. Guarded: it reads the
                                // legs table, which a lagging server may not have.
                                try { $mfpRoutePath = $flight?->routePath(); } catch (\Throwable) { $mfpRoutePath = null; }
                                $mfpVia = $mfpRoutePath ? array_slice(array_map('trim', preg_split('/→|->/u', $mfpRoutePath)), 1, -1) : [];
                                $mfpDep = $flight?->departure_at;
                                $mfpWasTip = collect($p->previousFlights())->map(fn ($w) => 'was ' . ($w['flight'] ?? '—') . ' · ' . ($w['departure'] ?? 'no date'))->implode("\n");
                                $mfpRouteTip = $fromName . ' → ' . $toName . ($mfpRoutePath ? "\nFull route: " . $mfpRoutePath : '');
                                $mfpDateTip = ($mfpDep ? $mfpDep->format('d M Y, h:i A') : 'Date pending') . ($mfpWasTip ? "\n" . $mfpWasTip : '');
                            @endphp
                            <td>
                                <div class="mfp-rt" title="{{ $mfpRouteTip }}">{!! $mfpFlag($fromAirport) !!}{{ $fromAirport?->code ?? '—' }}<i class="fas fa-plane mfp-tk-plane"></i>{{ $toAirport?->code ?? '—' }}{!! $mfpFlag($toAirport) !!}</div>
                                <div class="mfp-ln" title="{{ $mfpDateTip }}"><i class="fas fa-calendar-day mfp-ico"></i><b>{{ $mfpDep ? $mfpDep->format(($mfpDep->isCurrentYear() ? 'd M' : 'd M Y') . ', h:i A') : 'Date pending' }}</b></div>
                                {{-- Plain lines, not pills (owner, 2026-10-03: three labels looked odd). --}}
                                <div class="mfp-ln"><i class="fas fa-plane-departure mfp-ico"></i><a class="mfp-rt-flight" href="{{ $flightHref }}"{!! $flightClick ? ' onclick="' . $flightClick . '"' : '' !!} title="{{ $flightLine }}">{{ $flight?->flight_number ?? '—' }}</a>@if($mfpVia)<span class="mfp-tk-sep">·</span><span class="mfp-rt-via" title="{{ $mfpRoutePath }}">via {{ implode(', ', $mfpVia) }}</span>@endif</div>
                            </td>
                            @if(($mode ?? 'list') !== 'list')
                            <td>
                                <span class="mfp-stage">{{ \App\Models\ContractFlightPassenger::stageLabel($p->stage) }}</span>
                                @if($p->exception_flag)
                                    <small class="mfp-flag-tag"><i class="fas fa-triangle-exclamation"></i>{{ \App\Models\ContractFlightPassenger::EXCEPTION_FLAGS[$p->exception_flag] ?? $p->exception_flag }}</small>
                                @endif
                                @if($p->contract_confirmed_at)
                                    <small class="mfp-clear"><i class="fas fa-file-signature mfp-ico"></i>Contract confirmed</small>
                                @endif
                            </td>
                            @endif
                            @if(($mode ?? 'list') === 'list')
                            {{-- Cost Breakdown + Agent Charge as ONE cell (owner, 2026-10-01):
                                 what the agent is charged on top, with Paid / Due, then each
                                 cost line under a rule as "− amount". The lines add up to the
                                 Cost in the Cost / Profit column beside it, so the row reads
                                 left to right: charge, what it cost, what we made. --}}
                            <td data-sort="{{ $p->agent_total }}">
                                <div class="mfp-cb">
                                    <span class="k">Charge</span>
                                    <span class="v mfp-detail" title="Billed to the agent: package {{ $p->package_charged > 0 ? $mfpMoney($p->agent_price) : $mfpMoney($p->agent_price) . ' (reversed)' }}{{ $mfpPen['billed'] > 0 ? ' + penalty ' . $mfpMoney($mfpPen['billed']) : '' }}">{{ $mfpMoney($p->agent_total) }}</span>
                                    @php
                                        // Every change line per ticket, with who bears it (owner, 2026-10-10:
                                        // "which ticket, who bears — reissue 1 · x, reissue 2 · y").
                                        $mfpLines = $p->changeLines();
                                        $mfpAgentPays = array_sum(array_column($mfpLines, 'agent'));
                                        $mfpWeBear = array_sum(array_map(fn ($l) => $l['bearer'] === 'company' ? $l['amount'] : 0, $mfpLines));
                                    @endphp
                                    @if($mfpPen['billed'] != 0)
                                        <span class="k sub">Package</span><span class="v sub">{{ $mfpMoney($p->package_charged) }}</span>
                                        <span class="k sub mfp-due">+ Changes</span><span class="v sub mfp-due">{{ $mfpMoney($mfpPen['billed']) }}</span>
                                    @endif
                                    @if($p->baggageTotals()['charge'] > 0)
                                        <span class="k sub mfp-due">+ Extra baggage</span><span class="v sub mfp-due">{{ $mfpMoney($p->baggageTotals()['charge']) }}</span>
                                    @endif
                                    @if($p->suspendWithdrawTotals()['charge'] > 0)
                                        <span class="k sub mfp-due" title="Suspend withdraw billed to the agent — cost he bears + our charge">+ Suspend</span><span class="v sub mfp-due">{{ $mfpMoney($p->suspendWithdrawTotals()['charge']) }}</span>
                                    @endif
                                    @if($p->agent_due > 0)
                                        <span class="k sub mfp-due">Due</span><span class="v sub mfp-due" title="Still owed by the agent">{{ $mfpMoney($p->agent_due) }}</span>
                                    @else
                                        <span class="k sub mfp-clear">Paid</span><span class="v sub mfp-clear"><i class="fas fa-check"></i></span>
                                    @endif
                                    <span class="rule"></span>
                                    {{-- The penalty billed to the agent is income, already shown above as
                                         "incl. penalty" in the Charge — listing it here as "− penalty" read as a
                                         second cost (owner, 2026-10-10: "aren't those error?"). Only what we bear
                                         is a cost line, so the lines add up to the Cost column. --}}
                                    {{-- Plain costs first; the change costs are itemised below per ticket
                                         (when the lines exist), each with Agent / Ours. --}}
                                    @php $mfpPlain = array_filter($costs, fn ($c) => ! str_starts_with($c[0], 'Penalty') || ! $mfpLines); @endphp
                                    @forelse(array_filter($mfpPlain, fn ($c) => $c[0] !== 'Penalty') as $c)
                                        <span class="k{{ str_starts_with($c[0], 'Penalty') ? ' mfp-due' : '' }}" title="{{ $c[3] ?? $c[0] }}"><i class="fas {{ $c[2] ?? ($mfpCostIcons[$c[0]] ?? 'fa-receipt') }} mfp-ico"></i>{{ $c[0] === 'Penalty (ours)' ? 'Penalty (we bear)' : $c[0] }}</span>
                                        <span class="v{{ str_starts_with($c[0], 'Penalty') ? ' mfp-due' : '' }}">− {{ $mfpMoney($c[1]) }}</span>
                                    @empty
                                        @if(! $mfpLines)<span class="k">No cost yet</span><span class="v">—</span>@endif
                                    @endforelse
                                    @if($mfpLines)
                                        {{-- Collapsed: the two sums. Clicking them opens each ticket's lines (owner, 2026-10-10). --}}
                                        <span class="k sub mfp-chg-sum agent" title="Click to see each ticket"><i class="fas fa-caret-right mfp-chg-caret"></i>Agent pays</span><span class="v sub mfp-chg-sum">{{ $mfpMoney($mfpAgentPays) }}</span>
                                        <span class="k sub mfp-chg-sum company">We bear</span><span class="v sub mfp-chg-sum">{{ $mfpMoney($mfpWeBear) }}</span>
                                        {{-- Grouped by ticket: the PNR once, then each event under it — "J3P97Q2 / Reissue 1 ৳x /
                                             Reissue 2 ৳y". Left edge: indigo = agent pays, amber = we bear. --}}
                                        @foreach(collect($mfpLines)->groupBy('pnr') as $mfpPnr => $mfpGroup)
                                            <span class="k mfp-chg-pnr mfp-chg-d" title="{{ $mfpPnr }}">{{ $mfpPnr }}</span>
                                            @foreach($mfpGroup as $l)
                                                <span class="k mfp-chg mfp-chg-d {{ $l['bearer'] }}" title="{{ $mfpPnr }} · {{ $l['label'] }} · {{ $l['bearer'] === 'agent' ? 'agent pays' : 'we bear' }}{{ $l['cost'] ? '' : ' · inside the new ticket price' }}">{{ str_replace([' · airline kept', ' · airline charge', ' · fare difference'], [' · kept', ' · charge', ' · fare'], $l['label']) }}</span>
                                                <span class="v mfp-chg-v mfp-chg-d{{ $l['cost'] ? '' : ' info' }}">{{ $l['cost'] ? '− ' : '' }}{{ $mfpMoney($l['amount']) }}</span>
                                            @endforeach
                                        @endforeach
                                    @endif
                                </div>
                            </td>
                            @else
                            <td>
                                @forelse($costs as $c)
                                    <small class="mfp-clip{{ str_starts_with($c[0], 'Penalty') ? ' mfp-due' : '' }}"{!! isset($c[3]) ? ' title="' . e($c[3]) . '"' : '' !!}><i class="fas {{ $c[2] ?? ($mfpCostIcons[$c[0]] ?? 'fa-receipt') }} mfp-ico"></i>{{ $c[0] }}: {{ $mfpMoney($c[1]) }}</small>
                                @empty
                                    —
                                @endforelse
                            </td>
                            <td class="mfp-num mfp-detail">{{ $mfpMoney($p->agent_total) }}
                                @if($mfpPen['billed'] > 0)<small class="mfp-clip mfp-due" title="Package {{ $mfpMoney($p->package_charged) }} + penalty {{ $mfpMoney($mfpPen['billed']) }}">incl. penalty {{ $mfpMoney($mfpPen['billed']) }}</small>@endif
                                @if($ledger)
                                    <small class="mfp-clear">Received: {{ $mfpMoney($ledger['received']) }}</small>
                                    <small class="mfp-due">Due: {{ $mfpMoney($ledger['due']) }}</small>
                                @elseif($p->agent_due > 0)<small class="mfp-due">Due: {{ $mfpMoney($p->agent_due) }}</small>@else<small class="mfp-clear">Paid</small>@endif
                            </td>
                            @endif
                            <td class="mfp-num"><span class="mfp-cost-total" title="Travel cost">{{ $mfpMoney($p->total_travel_cost + $mfpPen['borne']) }}</span><span class="mfp-profit{{ $profit < 0 ? ' neg' : '' }}" title="Profit = Charge − Cost">Profit {{ $mfpMoney($profit) }}</span></td>
                            @if(($mode ?? 'list') === 'list')
                            <td>
                                <div class="mfp-actions">
                                    <button type="button" class="mfp-btn amber" onclick="editPassengerFromList({{ $flight->id }}, {{ $p->id }})" title="Edit passenger"><i class="fas fa-user-pen"></i></button>
                                    {{-- Edit flight and Print record (owner, 2026-09-30). Edit clicks this
                                         row's own (hidden) Edit flight button, so it runs the same handler. --}}
                                    <button type="button" class="mfp-btn amber" onclick="this.closest('tr').querySelector('.mfp-actions .edit-btn')?.click()" title="Edit flight {{ $flight->flight_number }}"><i class="fas fa-pen"></i></button>
                                    <button type="button" class="mfp-btn blue" onclick="if (window.cfpPrintPassengerSheet) cfpPrintPassengerSheet({{ $flight->id }}, {{ $p->id }}, this)" title="Print {{ $holder?->name ?? 'this passenger' }}'s record"><i class="fas fa-print"></i></button>
                                    <button type="button" class="mfp-btn blue" onclick="cfpOpenPassengerSheet({{ $flight->id }}, {{ $p->id }}, this)" title="Open the full record for {{ $holder?->name ?? 'this passenger' }}"><i class="fas fa-eye"></i></button>
                                    @include('contract-flights.partials.flight-row-actions', ['flight' => $flight, 'role' => $role])
                                    @if(\App\Models\ContractFlightPassengerChange::available())
                                    @php
                                        // Each live ticket with its sector, flags and date, for the
                                        // Re-assign dialog's per-ticket cards (owner, 2026-09-30).
                                        try { $mfpSlotsById = collect($p->ticketSlots())->whereNotNull('purchase_id')->keyBy('purchase_id'); } catch (\Throwable) { $mfpSlotsById = collect(); }
                                        // Earlier reissue charges per ticket, oldest first, read from the
                                        // "Charge per ticket — … PNR ৳amount" lines Re-assign writes.
                                        try { $mfpMoves = $p->flightChanges->whereIn('kind', \App\Models\ContractFlightPassengerChange::MOVE_KINDS)->sortBy('id'); } catch (\Throwable) { $mfpMoves = collect(); }
                                        $mfpPenaltiesFor = function ($no) use ($mfpMoves) {
                                            $out = [];
                                            foreach ($mfpMoves as $c) {
                                                if (! str_contains((string) $c->note, 'Charge per ticket')) continue;
                                                if (preg_match_all('/' . preg_quote($no, '/') . ' ৳([\d,]+(?:\.\d+)?)/u', (string) $c->note, $m)) {
                                                    foreach ($m[1] as $amt) $out[] = (float) str_replace(',', '', $amt);
                                                }
                                            }
                                            return $out;
                                        };
                                        $mfpHeld = $tickets->where('status', '!=', 'cancelled')->map(function ($t) use ($mfpSlotsById, $mfpPenaltiesFor) {
                                            $slot = $mfpSlotsById->get($t->id);
                                            $leg = $t->relationLoaded('legs') ? $t->legs->first() : $t->legs()->first();
                                            return ['id' => $t->id, 'no' => $t->ticket_no ?: ('#' . $t->id), 'status' => $t->status,
                                                'from' => $slot['from_code'] ?? $leg?->from_location, 'to' => $slot['to_code'] ?? $leg?->to_location,
                                                'from_flag' => $slot['from_flag'] ?? null, 'to_flag' => $slot['to_flag'] ?? null,
                                                'date' => $leg?->travel_date ? substr((string) $leg->travel_date, 0, 10) : null,
                                                'seq' => $slot['index'] ?? ($t->leg_sequence ?: 99),
                                                'fare' => (float) $t->amount,
                                                'penalties' => $mfpPenaltiesFor($t->ticket_no ?: ('#' . $t->id))];
                                        })->sortBy('seq')->values();
                                    @endphp
                                    {{-- Re-assign (reschedule / reissue) — the Cancelled rows' own feature. Opens the
                                         Re-assign dialog directly — he moves from this flight (reschedule keeps his ticket, reissue cancels it). --}}
                                    <button type="button" class="mfp-btn green" style="grid-column:span 2;width:100%;height:34px;flex-direction:column;gap:2px;padding:0;line-height:1;white-space:nowrap" onclick="if (window.cfpOpenReassign) cfpOpenReassign(this)" data-cfp="{{ json_encode(['id' => $p->id, 'name' => $holder?->name ?: 'Passenger', 'agent' => $p->agent?->name, 'agent_price' => (float) ($p->agent_price ?? 0), 'on_flight' => true, 'flight_id' => $flight->id, 'from_flight' => $flight->flight_number, 'from_date' => $flight->departure_at?->format('d M Y, h:i A'), 'from_route' => $flight->route, 'from_open' => true, 'reason' => null, 'held' => $mfpHeld, 'cancelled' => $tickets->where('status', 'cancelled')->map(fn ($t) => $t->ticket_no ?: ('#' . $t->id))->values(), 'holder_id' => $p->passport_holder_id, 'agent_id' => $p->agent_id, 'route_id' => $flight->ticket_id]) }}" title="Re-assign {{ $holder?->name ?? 'this passenger' }} (reschedule / reissue) — from {{ $flight->flight_number }}"><i class="fas fa-plane-circle-check" style="font-size:13px"></i><span style="font-size:8.5px;font-weight:700">Re-assign</span></button>
                                    @endif
                                    {{-- This one person off this flight, into Cancelled (reassign-script).
                                         Only once the migration has run and the routes are registered. --}}
                                    @if(\App\Models\ContractFlightPassengerChange::available())
                                        <button type="button" class="mfp-btn red" onclick="if (window.cfpCancelFromFlight) cfpCancelFromFlight(this)"
                                            data-cfp="{{ json_encode(['id' => $p->id, 'name' => $holder?->name ?: 'Passenger', 'flight' => $flight->flight_number, 'agent' => $p->agent?->name, 'agent_price' => (float) ($p->agent_price ?? 0), 'pnrs' => $tickets->where('status', '!=', 'cancelled')->map(fn ($t) => $t->ticket_no ?: ('#' . $t->id))->values(), 'tickets' => $tickets->where('status', '!=', 'cancelled')->map(fn ($t) => ['id' => $t->id, 'no' => $t->ticket_no ?: ('#' . $t->id), 'amount' => (float) $t->amount])->values()]) }}"
                                            title="Cancel {{ $holder?->name ?? 'this passenger' }} from {{ $flight->flight_number }} — move to Cancelled"><i class="fas fa-user-xmark"></i></button>
                                    @endif
                                </div>
                            </td>
                            @endif
                        </tr>
