{{-- AR / AP aging table — shared by the screen and the print page. Expects $ag, $frSide, $frRole, $frCompanyId, $frPrinting. --}}
@php
    $fm = fn ($n) => $n > 0.004 ? number_format((float) $n, 2) : '—';
    $isAr = $frSide === 'receivable';
    $partyUrl = fn (int $id) => (! ($frPrinting ?? false) && \Illuminate\Support\Facades\Route::has('role.party-statement.index'))
        ? route('role.party-statement.index', array_filter(['role' => $frRole, 'party_id' => $id, 'company' => $frCompanyId > 0 ? $frCompanyId : null]))
        : null;
    $buckets = \App\Services\Reports\AgingReport::BUCKETS;
@endphp
<table class="fr-st">
    <thead><tr>
        <th>{{ $isAr ? 'Customer / Agent' : 'Vendor' }}</th>
        @foreach($buckets as $label)<th class="fr-r">{{ $label }}</th>@endforeach
        <th class="fr-r">Total</th>
    </tr></thead>
    <tbody>
    @forelse($ag['rows'] as $r)
        @php $u = $partyUrl($r['id']); @endphp
        <tr class="fr-row">
            <td style="padding-left:14px">@if($u)<a class="fr-link" href="{{ $u }}">{{ $r['name'] }}</a>@else{{ $r['name'] }}@endif
                @if($r['last'])<div style="font-size:11px; color:#94a3b8">last entry {{ \Carbon\Carbon::parse($r['last'])->format('d M Y') }}</div>@endif</td>
            @foreach($buckets as $k => $label)<td class="fr-r {{ in_array($k, ['b45', 'over'], true) && $r['buckets'][$k] > 0.004 ? 'fr-up' : '' }}">{{ $fm($r['buckets'][$k]) }}</td>@endforeach
            <td class="fr-r" style="font-weight:800">{{ number_format($r['total'], 2) }}</td>
        </tr>
    @empty
        <tr><td colspan="{{ count($buckets) + 2 }}" class="fr-empty">Nothing is outstanding on this date.</td></tr>
    @endforelse
    @if(count($ag['rows']))
        <tr class="fr-grand"><td>TOTAL</td>@foreach($buckets as $k => $label)<td class="fr-r">{{ $fm($ag['totals'][$k]) }}</td>@endforeach<td class="fr-r">{{ number_format($ag['totals']['total'], 2) }}</td></tr>
    @endif
    </tbody>
</table>

@if(count($ag['advances']))
<table class="fr-st" style="margin-top:18px">
    <thead><tr><th>{{ $isAr ? 'Advances received — customers / agents who have paid ahead' : 'Advances paid — vendors we have paid ahead' }}</th><th class="fr-r">Credit balance</th></tr></thead>
    <tbody>
    @foreach($ag['advances'] as $a)
        @php $u = $partyUrl($a['id']); @endphp
        <tr class="fr-row"><td style="padding-left:14px">@if($u)<a class="fr-link" href="{{ $u }}">{{ $a['name'] }}</a>@else{{ $a['name'] }}@endif</td><td class="fr-r">{{ number_format($a['amount'], 2) }}</td></tr>
    @endforeach
    <tr class="fr-sub"><td>Total advances (not netted against anyone's due)</td><td class="fr-r">{{ number_format($ag['advance_total'], 2) }}</td></tr>
    </tbody>
</table>
@endif