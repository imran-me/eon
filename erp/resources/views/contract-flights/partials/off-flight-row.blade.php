{{-- One passenger taken off his flight and waiting under Cancelled (owner,
     2026-09-26). He belongs to no flight, so the flight-wise passenger-row
     partial cannot draw him; this row keeps the table's columns so the table
     reads the same, and carries the two actions that matter here:
     Re-assign (onto another flight, with a charge) and Undo (straight back).
     Expects $p (with passportHolder, agent, flightCategory, ticketPurchases,
     lastCancellation.fromFlight.ticket.*), $sl, $role, $tone. --}}
@php
    // PNRs open that ticket on Ticket Purchase (owner, 2026-09-28). Guarded:
    // a missing route leaves them as plain text, never an error.
    $ofrTicketsUrl = \Illuminate\Support\Facades\Route::has('role.ticket-purchase.index')
        ? route('role.ticket-purchase.index', array_filter(['role' => $role, 'company' => request('company')])) : null;
    $ofrPnr = function ($t) use ($ofrTicketsUrl) {
        $no = $t->ticket_no ?: '#' . $t->id;
        return $ofrTicketsUrl && $t->ticket_no
            ? '<a class="mfp-pnr" href="' . e($ofrTicketsUrl . (str_contains($ofrTicketsUrl, '?') ? '&' : '?') . 'ticket_no=' . urlencode($t->ticket_no)) . '" title="Open ' . e($t->ticket_no) . ' in Ticket Purchase">' . e($no) . '</a>'
            : '<span class="mfp-pnr">' . e($no) . '</span>';
    };
    $holder  = $p->passportHolder;
    $cancel  = $p->lastCancellation;
    $from    = $cancel?->fromFlight;
    $fromA   = $from?->ticket?->from_airport;
    $toA     = $from?->ticket?->to_airport;
    $money   = fn ($v) => '৳' . number_format(abs((float) $v), 0);
    // Live = still his ticket (a reschedule takes it along); cancelled = cancelled
    // at 0 when he was taken off, still listed in Ticket Orders with its note.
    $held    = $p->ticketPurchases->where('status', '!=', 'cancelled');
    $gone    = $p->ticketPurchases->where('status', 'cancelled');
    $reason  = $cancel?->reason ? (\App\Models\ContractFlightPassengerChange::CANCEL_REASONS[$cancel->reason] ?? $cancel->reason) : null;
    // Everything the Re-assign / Undo dialogs say about him, in one place.
    $cfp = [
        'id' => $p->id,
        'name' => $holder?->name ?: 'Passenger',
        'agent' => $p->agent?->name,
        'agent_price' => (float) ($p->agent_price ?? 0),
        'from_flight' => $from?->flight_number,
        'from_open' => $from && ! $from->trashed(),
        // super admin: Undo removes the wrong cancellation completely (and restores a deleted flight)
        'undo_full' => \App\Support\UserRoleSetting\Access::allows('travels.contract-flight', 'e') && \App\Services\ContractFlightCancellationUndo::available()
            && \Illuminate\Support\Facades\Route::has('role.contract-flight-passengers.undo-preview') && $cancel?->from_flight_id,
        'reason' => $reason,
        'held' => $held->map(fn ($t) => ['id' => $t->id, 'no' => $t->ticket_no ?: ('#' . $t->id), 'status' => $t->status, 'fare' => (float) $t->amount])->values(),
        'cancelled' => $gone->map(fn ($t) => $t->ticket_no ?: ('#' . $t->id))->values(),
        // For "New flight" → the Add Flight modal, pre-filled (2026-09-28):
        // his passport (the builder then fills his card and the Re-booking
        // box), his agent as the batch agent, and the route he was on.
        'holder_id' => $p->passport_holder_id,
        'holder_label' => trim(($holder?->name ?? '') . ($holder?->passport_no ? ' — ' . $holder->passport_no : '')),
        'agent_id' => $p->agent_id,
        'route_id' => $from?->ticket_id,
    ];
@endphp
<tr class="cf-row mfp-row mfp-offflight-row" id="contract-flight-passenger-{{ $p->id }}" data-print-date="{{ $from?->departure_at?->format('Y-m-d') }}" data-print-category="{{ $p->flightCategory?->name ?? 'Uncategorized' }}" style="--mfp-a:{{ $tone[0] }};--mfp-b:{{ $tone[1] }};--mfp-ring:{{ $tone[7] }}" title="Cancelled — off flight">
    <td class="mfp-sl">{{ $sl }}</td>
    {{-- Same cells as passenger-row (owner, 2026-10-03): no "Label:" prefixes.
         "Off flight · was CF-…" went — the Route cell's chip and the Stage tag
         already say it. --}}
    <td>
        <strong class="mfp-passenger mfp-name">{{ $holder?->name ?? 'Passenger' }}</strong>
        <div class="mfp-ln mfp-mono" title="Passport"><i class="fas fa-passport mfp-ico"></i>{{ $holder?->passport_no ?? '—' }}</div>
        @if($p->flightCategory?->name)<div class="mfp-ln" title="Category">{{ $p->flightCategory->name }}</div>@endif
    </td>
    <td>
        @if($p->agent)
            <a class="mfp-party mfp-name" href="{{ route('role.party-statement.index', ['role' => $role]) }}?party_id={{ $p->agent->id }}" title="Open {{ $p->agent->name }}'s statement"><i class="fas fa-user-tie mfp-agent-ico" aria-hidden="true"></i>{{ $p->agent->name }}</a>
        @else
            <strong class="mfp-party mfp-name"><i class="fas fa-user-tie mfp-agent-ico" aria-hidden="true"></i>Direct</strong>
        @endif
        <div class="mfp-ln" title="{{ $p->agent ? 'Agent ID · ' : '' }}Class">@if($p->agent)<span class="mfp-mono">EP-AG-{{ str_pad($p->agent->id, 3, '0', STR_PAD_LEFT) }}</span><span class="mfp-tk-sep">·</span>@endif{{ \App\Models\ContractFlightPassenger::TICKET_CLASSES[$p->ticket_class] ?? ucfirst($p->ticket_class ?: 'economy') }}</div>
    </td>
    <td>
        @forelse($held as $t)
            <div class="mfp-ticket">
                @if($t->ticket_no){!! $ofrPnr($t) !!}@else<span class="mfp-pnr">PNR pending</span>@endif
                <span class="mfp-status{{ $t->status === 'confirm' ? ' confirm' : '' }}">{{ $t->status ?? 'pending' }}</span>
                <small class="mfp-clip">Goes with him on a reschedule</small>
            </div>
        @empty
            @if($gone->isEmpty())<small>No ticket held</small>@endif
        @endforelse
        @foreach($gone as $t)
            <div class="mfp-ticket">
                {!! $ofrPnr($t) !!}
                {{-- Since 2026-09-28 a cancelled ticket keeps its amount and records
                     the airline refund; older ones were zeroed and still read ৳0. --}}
                <span class="mfp-status cancelled">cancelled · @if($t->refund_amount !== null)refund ৳{{ number_format((float) $t->refund_amount) }}@else৳{{ number_format((float) $t->amount) }}@endif</span>
                <small class="mfp-clip" title="{{ $t->cancel_note ?: 'Cancelled in Ticketing' }}">{{ $t->cancel_note ?: 'Cancelled in Ticketing' }}</small>
            </div>
        @endforeach
    </td>
    <td>
        <span class="mfp-stage">{{ \App\Models\ContractFlightPassenger::stageLabel($p->stage) }}</span>
        <small class="mfp-flag-tag"><i class="fas fa-plane-slash"></i>Off flight</small>
    </td>
    <td>
        <div class="mfp-rt">{{ $fromA?->code ?? '—' }}<i class="fas fa-plane mfp-tk-plane"></i>{{ $toA?->code ?? '—' }}</div>
        <div class="mfp-ln" title="{{ $from?->departure_at ? $from->departure_at->format('d M Y, h:i A') : 'Date not set' }}"><i class="fas fa-calendar-day mfp-ico"></i><b>{{ $from?->departure_at ? $from->departure_at->format(($from->departure_at->isCurrentYear() ? 'd M' : 'd M Y') . ', h:i A') : 'Date not set' }}</b></div>
        <div class="mfp-ln mfp-rt-flag" title="Taken off {{ $from?->flight_number ?? 'his flight' }}"><i class="fas fa-plane-slash mfp-ico"></i>Off {{ $from?->flight_number ?? 'his flight' }}</div>
    </td>
    <td>
        <div class="mfp-cb">
            <span class="k">Charge</span>
            <span class="v mfp-detail" title="Posted as zero on the agent's statement until he is re-assigned">@if((float) $p->agent_price > 0)<s>{{ $money($p->agent_price) }}</s>@else — @endif</span>
            <span class="k sub mfp-due">Voided</span><span class="v sub"></span>
            <span class="rule"></span>
        </div>
        <small class="mfp-clip" title="{{ $reason }}"><i class="fas fa-circle-info mfp-ico"></i>{{ $reason ?? 'No reason recorded' }}</small>
        @if($cancel?->note)
            <small class="mfp-clip" title="{{ $cancel->note }}"><i class="fas fa-note-sticky mfp-ico"></i>{{ $cancel->note }}</small>
        @endif
        <small class="mfp-clip"><i class="fas fa-clock mfp-ico"></i>{{ $cancel?->created_at?->format('d M Y') }}{{ $cancel?->creator ? ' · ' . $cancel->creator->name : '' }}</small>
    </td>
    <td class="mfp-num"><span class="mfp-cost-total" title="Tickets he holds + services already recorded">{{ $money($p->total_travel_cost) }}</span><small class="mfp-clip">Cost so far</small></td>
    <td>
        <div class="mfp-actions">
            @php $closed = $cancel?->reason === 'will_not_fly'; @endphp
            @if($closed)
                {{-- Case closed (owner, 2026-09-30) — and reversible (owner, 2026-10-26: "reverse option
                     needed"): Re-assign or Undo reopens it exactly as for any cancelled passenger; the
                     package is billed again as a new line, nothing posted for the close is removed. --}}
                <span class="mfp-flag-tag" style="grid-column:span 2;white-space:nowrap;" title="Will not fly — package reversed, refund and penalty posted. Re-assign or Undo reopens the case."><i class="fas fa-lock"></i> Closed</span>
            @endif
            <button type="button" class="mfp-btn green" onclick="cfpOpenReassign(this)" data-cfp="{{ json_encode($cfp) }}" title="{{ $closed ? 'Reopen — ' : '' }}Put {{ $holder?->name ?? 'this passenger' }} on another flight"><i class="fas fa-plane-circle-check"></i></button>
            @if($cfp['undo_full'])
                <button type="button" class="mfp-btn blue" onclick="cfpRestore(this)" data-cfp="{{ json_encode($cfp) }}" title="Undo — the cancellation was wrong: back as before, its ledger lines removed"><i class="fas fa-rotate-left"></i></button>
            @elseif($cfp['from_open'])
                <button type="button" class="mfp-btn blue" onclick="cfpRestore(this)" data-cfp="{{ json_encode($cfp) }}" title="{{ $closed ? 'Reopen — ' : 'Undo — ' }}put him back on {{ $from->flight_number }}"><i class="fas fa-rotate-left"></i></button>
            @else
                {{-- His old flight is gone: Undo shown greyed and, clicked, says why and offers Re-assign — an empty
                     slot read as "no reverse", a dead button as "not working" (owner, 2026-10-26). --}}
                @php $gone = $from ?? ($cancel?->from_flight_id ? \App\Models\ContractFlight::withTrashed()->find($cancel->from_flight_id) : null); @endphp
                <button type="button" class="mfp-btn blue" style="opacity:.45;" onclick="cfpUndoGone(this)" data-flight="{{ $gone?->flight_number }}" data-name="{{ $holder?->name ?? 'this passenger' }}" title="{{ $gone ? $gone->flight_number.' was deleted' : 'No record of the flight he left' }} — nothing to put him back on. Use Re-assign to put him on a flight."><i class="fas fa-rotate-left"></i></button>
            @endif
        </div>
    </td>
</tr>
