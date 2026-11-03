{{-- One Profit & Loss section: header, optional group headers, account rows, section total. Expects $sx, $showEmpty, $fm, $ledgerUrl. --}}
@if(! empty($sx['groups']) || $showEmpty)
<tr class="fr-sec"><td colspan="2">{{ $sx['label'] }}</td></tr>
@foreach($sx['groups'] as $g)
    @if($g['label'] !== '')<tr class="fr-grp"><td colspan="2">{{ $g['label'] }}</td></tr>@endif
    @foreach($g['rows'] as $r)
    @php $u = $ledgerUrl($r['id']); @endphp
    <tr class="fr-row {{ $g['label'] !== '' ? 'fr-in' : '' }}">
        <td><span class="fr-code">{{ $r['code'] }}</span>@if($u)<a class="fr-link" href="{{ $u }}">{{ $r['name'] }}</a>@else{{ $r['name'] }}@endif</td>
        <td class="fr-r {{ $r['amount'] < 0 ? 'fr-neg-n' : '' }}">{{ $fm($r['amount']) }}</td>
    </tr>
    @endforeach
    @if($g['label'] !== '' && count($sx['groups']) > 0)<tr class="fr-sub"><td style="font-weight:700; padding-left:34px">Total for {{ $g['label'] }}</td><td class="fr-r">{{ $fm($g['total']) }}</td></tr>@endif
@endforeach
<tr class="fr-sub"><td>Total for {{ $sx['label'] }}</td><td class="fr-r {{ $sx['total'] < 0 ? 'fr-neg-n' : '' }}">{{ $fm($sx['total']) }}</td></tr>
@endif
