{{-- One P&L row — a Manage Sales invoice or a sold-ticket change — and the
     hidden ticket breakdown it opens. Used by the main table and inside each
     month, so month → invoice → ticket is one behaviour. Needs $r, $m, $pct,
     $saleUrl. Row data-* attributes are what accounts-pl-scripts filters on. --}}
@php
    $mg = $r['revenue'] > 0 ? $r['profit'] / $r['revenue'] * 100 : null;
    $dueDays = ($r['agent_due'] > 0 && $r['kind'] === 'invoice') ? (int) \Illuminate\Support\Carbon::parse($r['date'])->diffInDays(now()) : null;
    $link = $r['kind'] === 'invoice' ? $saleUrl($r['id']) : null;
@endphp
<tr class="cfa-pl-row" tabindex="0" data-fk="{{ $r['key'] }}" data-label="{{ $r['label'] }}" data-search="{{ $r['search'] }}" data-date="{{ $r['date'] }}"
    data-earned="{{ $r['earned'] ? 1 : 0 }}" data-incomplete="0" data-profit="{{ $r['profit'] }}" data-due="{{ $r['agent_due'] }}">
    <td class="cfa-pl-tg">@if($r['kind'] !== 'change')<i class="fas fa-chevron-right"></i>@endif</td>
    <td class="cfa-nw">@if($link)<a href="{{ $link }}">{{ $r['label'] }}</a>@else<span class="b">{{ $r['label'] }}</span>@endif
        <small>{{ \Illuminate\Support\Carbon::parse($r['date'])->format('d M') }}@if($r['kind'] === 'change' && $r['passenger']) · {{ $r['passenger'] }}@endif</small></td>
    <td>{{ $r['agent'] }}</td>
    <td>{{ $r['route'] ?: '—' }}</td>
    <td class="num">{{ $r['tickets'] }}</td>
    <td class="num cfa-in">{{ $m($r['revenue']) }}</td>
    <td class="num cfa-out" @if($r['kind'] === 'change') title="Seller fees on the change, less the airline's refund" @endif>{{ $m($r['cost']) }}</td>
    <td class="num {{ $r['profit'] < 0 ? 'cfa-bad' : 'cfa-good' }}"><b>{{ $m($r['profit']) }}</b></td>
    <td class="num cfa-mg {{ $mg === null ? '' : ($mg < 0 ? 'cfa-bad' : 'cfa-good') }}">{{ $pct($mg) }}</td>
    <td class="num {{ $r['agent_due'] > 0 ? 'cfa-due' : '' }}">{{ $m($r['agent_due']) }}@if($dueDays !== null)<small class="{{ $dueDays > 30 ? 'cfa-bad' : '' }}" title="Days since the invoice">{{ $dueDays }}d</small>@endif</td>
    <td>@if($r['kind'] === 'transfer')<span class="cfa-pill muted" title="Sold to Contract Flight at net price">At net</span>@elseif($r['kind'] === 'own')<span class="cfa-pill bad">Loss</span>@elseif($r['kind'] === 'change')<span class="cfa-pill muted">Change</span>@else<span class="cfa-pill {{ $r['earned'] ? 'ok' : 'warn' }}" title="{{ $r['earned'] ? 'Travelled — profit earned' : 'Not travelled yet — profit booked, not earned' }}">{{ $r['earned'] ? 'Flown' : 'Not flown' }}</span>@endif</td>
</tr>
<tr class="cfa-jr cfa-pl-detail" data-sort-attach hidden><td colspan="11">
    @if($r['kind'] === 'invoice')
    <table class="cfa-pl-pax">
        <thead><tr><th>PNR</th><th>Passenger</th><th>Route</th><th>Seller</th><th>Travel</th><th class="num">Sold for</th><th class="num">− Cost</th><th class="num">= Profit</th><th class="num">Paid to seller</th></tr></thead>
        <tbody>
        @forelse($r['items'] as $i)
            <tr>
                <td class="b">{{ $i['pnr'] ?: '—' }}@if($i['status'] === 'cancelled')<small class="cfa-bad">cancelled</small>@endif</td>
                <td>{{ $i['passenger'] ?: '—' }}</td>
                <td>{{ $i['route'] ?: '—' }}</td>
                <td>{{ $i['seller'] ?: '—' }}</td>
                <td class="cfa-nw">{{ $i['travel'] ? \Illuminate\Support\Carbon::parse($i['travel'])->format('d M · H:i') : '—' }}</td>
                <td class="num cfa-in">{{ $m($i['price']) }}</td>
                <td class="num cfa-out">{{ $m($i['cost']) }}</td>
                <td class="num {{ $i['price'] - $i['cost'] < 0 ? 'cfa-bad' : 'cfa-good' }}"><b>{{ $m($i['price'] - $i['cost']) }}</b></td>
                <td class="num {{ $i['paid'] < $i['cost'] ? 'cfa-due' : '' }}">{{ $m($i['paid']) }}</td>
            </tr>
        @empty
            <tr><td colspan="9" style="text-align:center;color:#94a3b8">No tickets on this invoice.</td></tr>
        @endforelse
        </tbody>
    </table>
    @elseif(in_array($r['kind'], ['transfer', 'own'], true))
    <table class="cfa-pl-pax">
        <thead><tr><th>PNR</th><th>Bought</th>@if($r['kind'] === 'own')<th>Why</th>@endif<th class="num">{{ $r['kind'] === 'transfer' ? 'Net price' : 'Loss' }}</th></tr></thead>
        <tbody>
        @foreach($r['lines'] as $l)
            <tr><td class="b">{{ $l['pnr'] ?: '—' }}</td><td>{{ $l['date'] ? \Illuminate\Support\Carbon::parse($l['date'])->format('d M Y') : '—' }}</td>@if($r['kind'] === 'own')<td>{{ $l['why'] ?? '' }}</td>@endif<td class="num {{ $r['kind'] === 'own' ? 'cfa-bad' : '' }}">{{ $m($l['amount']) }}</td></tr>
        @endforeach
        </tbody>
    </table>
    @endif
</td></tr>
