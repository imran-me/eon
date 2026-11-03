{{-- One flight row of the Profit & Loss tab and, under it, the hidden
     passenger breakdown it opens. Used by the flight table and inside each
     month's drill-down, so month → flight → passenger is one behaviour.
     Needs $f (flight figures), $flightPax, $m, $pct, $flightsUrl, $oneAirline. --}}
@php
    $fk = (string) ($f['fk'] ?? $f['flight_id'] ?? '');
    $pax = $flightPax[$fk] ?? collect();
    $hay = strtolower(implode(' ', [$f['flight_number'], $f['route'], $f['airline'], $f['agents'], $pax->pluck('name')->implode(' '), $pax->pluck('passport_no')->implode(' ')]));
    $when = $f['departure_at'] ? \Illuminate\Support\Carbon::parse($f['departure_at']) : ($f['date'] ? \Illuminate\Support\Carbon::parse($f['date']) : null);
    $dueDays = ($f['agent_due'] > 0 && $f['earned'] && $when) ? (int) $when->diffInDays(now()) : null;
    $incomplete = (int) ($f['cost_incomplete'] ?? 0);
@endphp
<tr class="cfa-pl-row" tabindex="0" data-fk="{{ $fk }}" data-label="{{ $f['flight_id'] ? $f['flight_number'] : 'Off flight' . ($when ? ' · ' . $when->format('M Y') : '') }}" data-search="{{ $hay }}" data-date="{{ $f['date'] ?? '' }}"
    data-earned="{{ $f['earned'] ? 1 : 0 }}" data-incomplete="{{ $incomplete }}" data-profit="{{ $f['profit'] }}" data-due="{{ $f['agent_due'] }}">
    <td class="cfa-pl-tg"><i class="fas fa-chevron-right"></i></td>
    <td class="cfa-nw"><a href="{{ $flightsUrl }}?search={{ urlencode($f['flight_number']) }}">{{ $f['flight_number'] }}</a>
        <small>{{ $when ? $when->format($f['departure_at'] ? 'd M · H:i' : 'd M') : 'No date' }}@unless($oneAirline) · {{ $f['airline'] }}@endunless</small></td>
    <td class="b cfa-nw">{{ $f['route'] }}</td>
    <td>{{ $f['agents'] ?: 'Direct' }}</td>
    <td class="num">{{ $f['passengers'] }}</td>
    <td class="num cfa-in">{{ $m($f['revenue']) }}</td>
    <td class="num cfa-out">{{ $m($f['ticket_cost']) }}@if($incomplete)<small class="cfa-warn-t" title="{{ $incomplete }} passenger(s) not fully ticketed — ticket cost still to come, profit overstated">{{ $incomplete }} pending</small>@endif</td>
    <td class="num cfa-out">{{ $m($f['service_cost']) }}</td>
    <td class="num cfa-out" title="Changes we bear {{ $m($f['change_cost'] ?? 0) }} · billed to agents {{ $m($f['billed_changes'] ?? 0) }} (in revenue)">{{ $m($f['change_cost'] ?? 0) }}</td>
    <td class="num {{ $f['profit'] < 0 ? 'cfa-bad' : 'cfa-good' }}"><b>{{ $m($f['profit']) }}</b></td>
    <td class="num {{ $f['margin'] === null ? '' : ($f['margin'] < 0 ? 'cfa-bad' : ($incomplete ? 'cfa-warn-t' : 'cfa-good')) }}">{{ $incomplete ? '—' : $pct($f['margin']) }}</td>
    <td class="num {{ $f['agent_due'] > 0 ? 'cfa-due' : '' }}">{{ $m($f['agent_due']) }}@if($dueDays !== null)<small class="{{ $dueDays > 30 ? 'cfa-bad' : '' }}" title="Days since departure">{{ $dueDays }}d</small>@endif</td>
    <td><span title="{{ $f['earned'] ? 'Flown — profit earned' : 'Not flown — profit booked, not earned' }}" class="cfa-pill {{ ['departed' => 'ok', 'cancelled' => 'bad', 'open' => 'warn', 'boarding' => 'warn'][$f['status']] ?? 'muted' }}">{{ ucfirst($f['status'] ?? ($f['flight_id'] ? '—' : 'Off flight')) }}</span>
</td>
</tr>
<tr class="cfa-jr cfa-pl-detail" data-sort-attach hidden><td colspan="13">
    <table class="cfa-pl-pax">
        <thead><tr><th>Passenger</th><th>Agent</th><th>Stage</th><th class="num">Package</th><th class="num">+ Changes billed</th><th class="num">− Tickets</th><th class="num">− Services</th><th class="num">− Changes borne</th><th class="num">= Profit</th><th class="num">Received</th><th class="num">Due</th></tr></thead>
        <tbody>
        @foreach($pax as $r)
            <tr>
                <td class="b">{{ $r['name'] }}<small>{{ $r['passport_no'] ?: '—' }}</small></td>
                <td>{{ $r['agent_name'] ?: '—' }}</td>
                <td>{{ $r['stage'] }}@if($r['exception'])<small class="cfa-bad">{{ $r['exception'] }}{{ $r['chargeable'] ? '' : ' · charge voided' }}</small>@endif</td>
                <td class="num cfa-in">{{ $m($r['package']) }}</td>
                <td class="num cfa-in">{{ $r['billed'] ? $m($r['billed']) : '—' }}</td>
                <td class="num cfa-out">{{ $m($r['ticket_cost']) }}@if($r['cost_incomplete'])<small class="cfa-warn-t">pending</small>@endif</td>
                <td class="num cfa-out">{{ $r['service_cost'] ? $m($r['service_cost']) : '—' }}</td>
                <td class="num cfa-out">{{ $r['change_cost'] ? $m($r['change_cost']) : '—' }}</td>
                <td class="num {{ $r['profit'] < 0 ? 'cfa-bad' : 'cfa-good' }}"><b>{{ $m($r['profit']) }}</b></td>
                <td class="num cfa-good">{{ $m($r['agent_paid']) }}</td>
                <td class="num {{ $r['agent_due'] > 0 ? 'cfa-due' : '' }}">{{ $r['agent_due'] > 0 ? $m($r['agent_due']) : 'Clear' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</td></tr>
