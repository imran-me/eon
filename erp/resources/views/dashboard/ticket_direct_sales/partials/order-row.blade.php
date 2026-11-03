{{--
    One Ticket Orders row (redesigned 2026-10-05: "more informational, good
    looking, noise free … remove Created By, merge agent and vendor, add
    clickable id"). Same discipline as the Flights & Passengers cells: a bold
    first line, then short grey lines with no "Label:" — the tooltip names
    the field; colour on plain text, a pill only for profit; the Ticket cell
    is the contract-flight ticket cell (ticket-legs-styles + airline logo).
    Expects: $order, $i, $orders, $role, $partyUrl, $cfListUrl, $changeOn.
--}}
@php
    $isBooking = $order->order_type === 'booking';
    // "same passenger" only when it IS the same passenger (2026-10-18: UFUMMB, MD RIDOY MIA, showed
    // under SALA UDDIN — moved to him on the edit page, it had kept Sala's order until re-saved)
    $sameGroup = $i > 0 && $orders[$i - 1]->order_group === $order->order_group
        && (string) $orders[$i - 1]->passenger_name === (string) $order->passenger_name;
    [$pnrMain, $pnrSfx] = preg_match('/^(.*?)\s*(\([a-z]+\))$/i', (string) $order->secondary_reference, $m) ? [$m[1], $m[2]] : [(string) $order->secondary_reference, ''];
    $pnrHref = $isBooking
        ? route('role.ticket-bookings.show', ['role' => $role, 'ticket_booking' => $order->record_id])
        : route('role.ticket-purchase.show', ['role' => $role, 'ticket_purchase' => $order->record_id]);
    $airCode = preg_match('/\(([A-Z0-9]{2,3})\)\s*$/i', (string) $order->airline, $am) ? strtoupper($am[1]) : '';
    $dep = $order->departure_at ? \Carbon\Carbon::parse($order->departure_at) : null;
    $depTxt = $dep ? $dep->format($dep->year === now()->year ? 'd M' : 'd M Y') . ($dep->format('H:i') !== '00:00' ? ', ' . $dep->format('H:i') : '') : null;
    [$secFrom, $secTo] = array_pad(array_map('trim', preg_split('/\s*(?:→|->|-)\s*/u', (string) $order->sector, 2)), 2, '');
    $st = $order->status === 'booked' ? 'Reserved' : ucfirst((string) $order->status);
    $stCls = ['booked' => 'to-st-hold', 'purchased' => 'to-st-ok', 'expired' => 'to-st-warn', 'cancelled' => 'to-st-bad', 'pending' => 'to-st-warn', 'draft' => 'to-st-mute'][$order->status] ?? 'to-st-mute';
    $created = \Carbon\Carbon::parse($order->order_date);
    $code = fn ($prefix, $id) => $prefix . str_pad((string) $id, 3, '0', STR_PAD_LEFT);
    $was = ! $isBooking ? \App\Models\ContractFlightPassengerChange::wasDatesLine($order->reference) : null;
    // Earlier dates from Ticket Orders' own Re-issue / Reschedule (2026-10-06).
    if (! empty($order->chg_was)) $was = $was ? $was . ' · ' . $order->chg_was : 'was ' . $order->chg_was;
    // money — a change adds postings beside the ticket, never edits it
    $refund = (! $isBooking && $order->status === 'cancelled' && isset($order->refund_amount) && $order->refund_amount !== null) ? (float) $order->refund_amount : null;
    $chgCost = (float) ($order->chg_cost ?? 0);        // re-issue fees owed to the seller
    $chgCharged = (float) ($order->chg_charged ?? 0);  // billed to the customer for changes
    $chgReversed = (float) ($order->chg_reversed ?? 0); // agent price taken back on a refund / void
    // a live round trip whose return half was cancelled (2026-10-14): that refund comes off too
    $retRefund = (! $isBooking && $refund === null && isset($order->ret_refund)) ? $order->ret_refund : null;
    $baggageCost = (float) ($order->extra_baggage_cost ?? 0);
    $baggageCharge = ($order->extra_baggage_bearer ?? null) === 'customer' ? (float) ($order->extra_baggage_agent_charge ?? 0) : 0.0;
    $cost = $baggageCost + (float) $order->amount - ($refund ?? $retRefund ?? 0) + $chgCost;
    $saleNet = $order->sale_price !== null ? (float) $order->sale_price + $chgCharged - $chgReversed + $baggageCharge : null;
    $profit = (! $isBooking && empty($order->cf_flight) && $saleNet !== null) ? $saleNet - $cost : null;
    // a refunded / voided ticket keeps the button: its Refund tab makes the new ticket (2026-10-07)
    $canChange = ! empty($changeOn) && ! $isBooking && in_array($order->status, ['purchased', 'cancelled'], true);
    $money = fn ($v) => number_format((float) $v, 2);
@endphp
<tr @class(['to-sibling' => $sameGroup]) data-order="{{ $order->order_type }}-{{ $order->record_id }}">
    <td class="to-sl">{{ $sameGroup ? '' : $orders->firstItem() + $i }}</td>

    {{-- Ticket: PNR + airline / sector · date --}}
    <td class="to-c-ticket">
        <div class="mfp-tk {{ $order->status === 'purchased' ? 'done' : '' }}" title="{{ $order->route_name }}{{ $order->eticket ? ' · e-ticket ' . $order->eticket : '' }}">
            <span class="mfp-tk-dot">@if($order->status === 'purchased')<i class="fas fa-check"></i>@endif</span>
            <div class="mfp-tk-l1">
                <a class="mfp-pnr" href="{{ $pnrHref }}" title="Open {{ $order->secondary_reference ?: $order->reference }}">{{ $pnrMain !== '' ? $pnrMain : $order->reference }}@if($pnrSfx)<span class="mfp-tk-sfx">{{ $pnrSfx }}</span>@endif</a>
                <span class="mfp-tk-air" @if($order->airline) data-airline="{{ $order->airline }}" title="{{ $order->airline }}" @endif>{{ $airCode }}</span>
            </div>
            <div class="mfp-tk-l2">@if($secFrom && $secTo){{ $secFrom }}<i class="fas fa-plane mfp-tk-plane"></i>{{ $secTo }}@else{{ $order->route_name ?? '—' }}@endif @if($depTxt)<span class="mfp-tk-sep">·</span><b>{{ $depTxt }}</b>@endif</div>
            @if($order->flight_no)<div class="mfp-tk-l2" title="Flight">{{ $order->flight_no }}</div>@endif
            @if(isset($order->free_baggage_kg) || (float) ($order->extra_baggage_kg ?? 0) > 0)
                <div class="mfp-tk-l2" title="{{ $order->baggage_conditions ?? 'Baggage allowance' }}">
                    @if(isset($order->free_baggage_kg))
                        {{ (float) $order->free_baggage_kg }} KG free
                    @endif
                    @if((float) ($order->extra_baggage_kg ?? 0) > 0)
                        · {{ (float) $order->extra_baggage_kg }} KG extra
                    @endif
                </div>
            @endif
        </div>
    </td>

    {{-- Passenger --}}
    <td class="to-c-pax">
        @if($sameGroup)
            <span class="mfp-ln"><i class="fas fa-level-up-alt fa-rotate-90 mfp-ico"></i>same passenger</span>
        @else
            <div class="mfp-name to-strong">@if($order->passport_no)<a href="{{ route('role.passport-holder.index', ['role' => $role, 'name' => $order->passport_no]) }}" title="Open passport holder">{{ $order->passenger_name ?? '—' }}</a>@else{{ $order->passenger_name ?? '—' }}@endif</div>
            @if($order->passport_no)<span class="mfp-ln mfp-mono" title="Passport">{{ $order->passport_no }}</span>@endif
            @if($order->eticket)<span class="mfp-ln mfp-mono" title="E-ticket">{{ $order->eticket }}</span>@endif
        @endif
    </td>

    {{-- Route --}}
    <td class="to-c-route">
        <div class="to-strong to-clip" title="{{ $order->route_name }}">{{ $order->route_name ?? '—' }}</div>
        @if(($order->leg_sequence && ($order->leg_total ?? 1) > 1) || ! empty($order->cf_flight))
            <span class="mfp-ln">@if($order->leg_sequence && ($order->leg_total ?? 1) > 1)<span class="to-leg">Ticket {{ $order->leg_sequence }} of {{ $order->leg_total }}</span>@endif @if(! empty($order->cf_flight))@if($order->leg_sequence && ($order->leg_total ?? 1) > 1)<span class="mfp-tk-sep">·</span>@endif<i class="fas fa-plane-departure mfp-ico"></i><a class="mfp-rt-flight" href="{{ $cfListUrl ? $cfListUrl . '?search=' . urlencode($order->passport_no ?: ($order->passenger_name ?: $order->reference)) : '#' }}" title="Open him on Flights &amp; Passengers">{{ $order->cf_flight }}</a>@endif</span>
        @endif
        @if($was)<span class="mfp-ln mfp-rt-flag" title="Earlier travel date(s) — re-assigned"><i class="fas fa-clock-rotate-left mfp-ico"></i>Rescheduled · {{ $was }}</span>@endif
    </td>

    {{-- Status (type, expiry and created folded in) --}}
    <td class="to-c-status" @if($isBooking) data-expiry="{{ $order->expires_at }}" data-active="{{ $order->status === 'booked' ? '1' : '0' }}" @endif>
        <div class="to-st {{ $stCls }}">{{ $st }}</div>
        @if(! empty($order->chg_reissue) && $order->status !== 'cancelled')<span class="mfp-ln to-chg" title="This ticket has been re-issued — fees and charges are on its seller's and customer's statements"><i class="fas fa-arrows-rotate mfp-ico"></i>Re-issued</span>@endif
        @if(! empty($order->ret_badge))<span class="mfp-ln to-ret {{ $order->ret_badge['tone'] }}" title="{{ $order->ret_badge['title'] ?? 'Cancel return — the airline\'s window for the unused return' }}"><i class="fas fa-plane-arrival mfp-ico"></i>{{ $order->ret_badge['text'] }}</span>@endif
        @if(! empty($order->cancel_note))<span class="mfp-ln to-st-bad-ln" title="{{ $order->cancel_note }}">{{ \Illuminate\Support\Str::limit($order->cancel_note, 46) }}</span>@endif
        @if($isBooking && $order->expires_at)<span class="mfp-ln" title="Hold expires">until {{ \Carbon\Carbon::parse($order->expires_at)->format('d M, H:i') }} <span class="countdown"></span></span>@endif
        @if(! ($isBooking && $order->expires_at && ! empty($order->cancel_note)))<span class="mfp-ln" title="Entered {{ $created->format('d M Y, h:i A') }} by {{ $order->created_by_name ?? '—' }}">{{ $created->format($created->year === now()->year ? 'd M, h:i A' : 'd M Y') }}</span>@endif
    </td>

    {{-- Vendor & Agent: who we bought from, who it is sold to --}}
    <td class="to-c-party">
        <span class="mfp-ln to-party" title="Bought from">
            <i class="fas fa-store mfp-ico"></i><span class="to-party-name" title="{{ $order->supplier_name }}">{{ $order->supplier_name ?? '—' }}</span>
            @if($order->vendor_id)<a class="mfp-mono to-code" href="{{ $partyUrl }}?party_id={{ $order->vendor_id }}" title="Vendor statement">{{ $code('EP-VN-', $order->vendor_id) }}</a>@elseif($order->portal_id)<span class="to-code">Portal</span>@endif
        </span>
        @if(! empty($order->cf_flight))
            <span class="mfp-ln to-party" title="Billed per head on the contract flight">
                <i class="fas fa-user-tie mfp-ico"></i><span class="to-party-name" title="{{ $order->cf_agent }}">{{ $order->cf_agent ?? 'Agent' }}</span>
                @if($order->cf_agent_id)<a class="mfp-mono to-code" href="{{ $partyUrl }}?party_id={{ $order->cf_agent_id }}" title="Agent statement">{{ $code('EP-AG-', $order->cf_agent_id) }}</a>@endif
            </span>
        @elseif($order->sale_buyer)
            <span class="mfp-ln to-party" title="Sold to">
                <i class="fas fa-user-tie mfp-ico"></i><span class="to-party-name" title="{{ $order->sale_buyer }}">{{ $order->sale_buyer }}</span>
                @if($order->sale_buyer_id)<a class="mfp-mono to-code" href="{{ $partyUrl }}?party_id={{ $order->sale_buyer_id }}" title="Statement">{{ $code('EP-AG-', $order->sale_buyer_id) }}</a>@endif
            </span>
        @elseif(! $isBooking && $order->status !== 'cancelled')
            <span class="mfp-ln to-mute" title="Not sold yet — sell it from Manage Sales"><i class="fas fa-user-tie mfp-ico"></i>not sold</span>
        @endif
    </td>

    {{-- Cost --}}
    <td class="to-c-num">
        <span class="mfp-cost-total" title="{{ $isBooking ? 'Quoted' : 'Net cost' }}">{{ $money($order->amount) }}</span>
        @if($refund !== null)
            <span class="mfp-ln to-ok" title="Airline refund"><i class="fas fa-rotate-left mfp-ico"></i>{{ $money($refund) }}</span>
            @if((float) $order->amount > $refund)<span class="mfp-ln to-bad" title="Penalty kept by the airline">− {{ $money((float) $order->amount - $refund) }}</span>@endif
        @elseif(! $isBooking)
            @if($retRefund !== null)<span class="mfp-ln to-ok" title="Airline refund for the cancelled return — net cost {{ $money($cost) }}"><i class="fas fa-rotate-left mfp-ico"></i>{{ $money($retRefund) }} return</span>@endif
            @if((float) $order->due_amount > 0)<span class="mfp-ln to-bad" title="Due to the seller">due {{ $money($order->due_amount) }}</span>@else<span class="mfp-ln to-ok" title="Paid to the seller">paid</span>@endif
        @else
            <span class="mfp-ln to-mute">quoted</span>
        @endif
        @if($baggageCost > 0)<span class="mfp-ln to-bad" title="Extra baggage {{ $order->extra_baggage_kg }} KG">+ {{ $money($baggageCost) }} baggage</span>@endif
        {{-- Re-issue / reschedule fees owed to the seller (2026-10-06) --}}
        @if($chgCost > 0)
            <span class="mfp-ln to-bad" title="Re-issue / reschedule fees owed to the seller — cost is {{ $money($cost) }} with them">+ {{ $money($chgCost) }} fee</span>
        @endif
    </td>

    {{-- Sale / Profit --}}
    <td class="to-c-num">
        @if($baggageCost > 0 || $baggageCharge > 0)<span class="mfp-ln" title="Baggage charge minus cost">Baggage {{ $money($baggageCharge) }} / profit {{ $money($baggageCharge - $baggageCost) }}</span>@endif
        @if(! empty($order->cf_flight))
            <span class="mfp-ln" title="Agent price and profit are on the contract flight"><i class="fas fa-plane mfp-ico"></i>on flight</span>
        @elseif($order->sale_price !== null)
            <span class="mfp-cost-total" title="Agent price {{ $money($order->sale_price) }}@if($chgCharged > 0) + change charges {{ $money($chgCharged) }}@endif @if($chgReversed > 0) − reversed {{ $money($chgReversed) }}@endif">{{ $money($saleNet) }}</span>
            @if($chgReversed > 0)<span class="mfp-ln to-mute" title="Agent price taken back on the refund / void / cancelled return (of {{ $money($order->sale_price) }})">{{ $money($chgReversed) }} reversed</span>@endif
            <span class="mfp-profit {{ $profit < 0 ? 'neg' : '' }}" title="Agent charge − net cost">{{ $profit < 0 ? '−' : '+' }}{{ $money(abs($profit)) }}</span>
        @else
            <span class="to-mute">—</span>
        @endif
    </td>

    {{-- Actions (unchanged buttons, compact 24px) --}}
    <td class="px-2 py-3">
        <div style="display:grid;grid-template-columns:repeat(2,24px);gap:4px;width:max-content;margin:0 auto;justify-content:center" data-row-actions>
            @if($isBooking)
                <a href="{{ route('role.ticket-bookings.show', ['role' => $role, 'ticket_booking' => $order->record_id]) }}" class="to-act text-indigo-600" title="View booking"><i class="fas fa-eye"></i></a>
            @else
                <a href="{{ route('role.ticket-purchase.show', ['role' => $role, 'ticket_purchase' => $order->record_id]) }}" class="to-act text-slate-600" title="View purchase"><i class="fas fa-file-invoice"></i></a>
                <a href="{{ route('role.ticket-direct-sale.edit', ['role' => $role, 'ticket_direct_sale' => $order->record_id]) }}" class="to-act to-act-blue" title="Edit purchase"><i class="fas fa-pencil-alt"></i></a>
                @if($canChange)
                    {{-- Re-issue / Reschedule / Refund / Void (2026-10-06); a contract-flight ticket goes through the flight's own code --}}
                    <button type="button" class="to-act-wide" data-ticket-change="{{ $order->record_id }}" title="{{ $order->status === 'cancelled' ? 'Refund history, and a new ticket for this passenger' : 'Re-issue, reschedule, refund or void this ticket' }}{{ ! empty($order->cf_pax_id) ? ' — posted through the contract flight' : '' }}"><i class="fas fa-right-left"></i><span>Change</span></button>
                @endif
            @endif
        </div>
    </td>
</tr>
