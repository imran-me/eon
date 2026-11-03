{{-- One Visa P&L row — a Manage Sales invoice — and the hidden breakdown of
     its applications / services. Used by the main table and inside each month.
     Row data-* attributes are what accounts-pl-scripts filters on. --}}
@php
    $mg = $r['revenue'] > 0 ? $r['profit'] / $r['revenue'] * 100 : null;
    $dueDays = $r['agent_due'] > 0 ? (int) \Illuminate\Support\Carbon::parse($r['date'])->diffInDays(now()) : null;
    $link = $saleUrl($r['id']);
@endphp
<tr class="cfa-pl-row" tabindex="0" data-fk="{{ $r['key'] }}" data-label="{{ $r['label'] }}" data-search="{{ $r['search'] }}" data-date="{{ $r['date'] }}"
    data-earned="{{ $r['earned'] ? 1 : 0 }}" data-incomplete="0" data-profit="{{ $r['profit'] }}" data-due="{{ $r['agent_due'] }}">
    <td class="cfa-pl-tg"><i class="fas fa-chevron-right"></i></td>
    <td class="cfa-nw">@if($link)<a href="{{ $link }}">{{ $r['label'] }}</a>@else<span class="b">{{ $r['label'] }}</span>@endif
        <small>{{ \Illuminate\Support\Carbon::parse($r['date'])->format('d M Y') }}</small></td>
    <td>{{ $r['agent'] }}</td>
    <td>{{ $r['what'] ?: '—' }}</td>
    <td class="num">{{ $r['tickets'] }}</td>
    <td class="num cfa-in">{{ $m($r['revenue']) }}</td>
    <td class="num cfa-out">{{ $m($r['cost']) }}</td>
    <td class="num {{ $r['profit'] < 0 ? 'cfa-bad' : 'cfa-good' }}"><b>{{ $m($r['profit']) }}</b></td>
    <td class="num cfa-mg {{ $mg === null ? '' : ($mg < 0 ? 'cfa-bad' : 'cfa-good') }}">{{ $pct($mg) }}</td>
    <td class="num {{ $r['agent_due'] > 0 ? 'cfa-due' : '' }}">{{ $m($r['agent_due']) }}@if($dueDays !== null)<small class="{{ $dueDays > 30 ? 'cfa-bad' : '' }}" title="Days since the invoice">{{ $dueDays }}d</small>@endif</td>
    <td><span class="cfa-pill {{ $r['earned'] ? 'ok' : 'warn' }}" title="{{ $r['earned'] ? 'Every application approved or rejected — profit earned' : 'Still being processed — profit booked, not earned' }}">{{ $r['earned'] ? 'Done' : 'In process' }}</span>@if($r['rejected'])<small class="cfa-bad">{{ $r['rejected'] }} rejected</small>@endif</td>
</tr>
<tr class="cfa-jr cfa-pl-detail" data-sort-attach hidden><td colspan="11">
    <table class="cfa-pl-pax">
        <thead><tr><th>Ref</th><th>Passenger</th><th>Visa / service</th><th>Vendor</th><th>Status</th><th class="num">Sold for</th><th class="num">− Cost</th><th class="num">= Profit</th><th class="num">Paid to vendor</th></tr></thead>
        <tbody>
        @forelse($r['items'] as $i)
            <tr>
                <td class="b">{{ $i['ref'] }}<small>{{ $i['kind'] === 'service' ? 'Other service' : 'Application' }}</small></td>
                <td>{{ $i['passenger'] ?: '—' }}<small>{{ $i['passport_no'] }}</small></td>
                <td>{{ $i['what'] ?: '—' }}</td>
                <td>{{ $i['seller'] ?: '—' }}</td>
                <td><span class="cfa-pill {{ $i['status'] === 'rejected' ? 'bad' : ($i['final'] ? 'ok' : 'muted') }}">{{ ucwords(str_replace('_', ' ', (string) $i['status'])) ?: '—' }}</span></td>
                <td class="num cfa-in">{{ $m($i['price']) }}</td>
                <td class="num cfa-out">{{ $m($i['cost']) }}</td>
                <td class="num {{ $i['price'] - $i['cost'] < 0 ? 'cfa-bad' : 'cfa-good' }}"><b>{{ $m($i['price'] - $i['cost']) }}</b></td>
                <td class="num {{ $i['kind'] === 'application' && $i['paid'] < $i['cost'] ? 'cfa-due' : '' }}">{{ $i['kind'] === 'application' ? $m($i['paid']) : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="9" style="text-align:center;color:#94a3b8">Nothing on this invoice.</td></tr>
        @endforelse
        </tbody>
    </table>
</td></tr>
