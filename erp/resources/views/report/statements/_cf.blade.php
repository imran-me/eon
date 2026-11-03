{{-- Cash flow statement table — shared by the screen and the print page. Expects $cf. --}}
@php $fm = fn ($n) => number_format((float) $n, 2); @endphp
<table class="fr-st">
    <thead><tr><th>Account</th><th class="fr-r">Total</th></tr></thead>
    <tbody>
        <tr class="fr-key"><td>Beginning Cash Balance</td><td class="fr-r">{{ $fm($cf['beginning']) }}</td></tr>

        <tr class="fr-sec"><td colspan="2">Cash Flow from Operating Activities</td></tr>
        <tr class="fr-row"><td>Net Income</td><td class="fr-r {{ $cf['net_income'] < 0 ? 'fr-neg-n' : '' }}">{{ $fm($cf['net_income']) }}</td></tr>
        @foreach($cf['lines']['operating'] as $l)
        <tr class="fr-row"><td>{{ $l['label'] }}</td><td class="fr-r {{ $l['amount'] < 0 ? 'fr-neg-n' : '' }}">{{ $fm($l['amount']) }}</td></tr>
        @endforeach
        <tr class="fr-sub"><td>Net cash provided by Operating Activities</td><td class="fr-r {{ $cf['operating'] < 0 ? 'fr-neg-n' : '' }}">{{ $fm($cf['operating']) }}</td></tr>

        <tr class="fr-sec"><td colspan="2">Cash Flow from Investing Activities</td></tr>
        @forelse($cf['lines']['investing'] as $l)
        <tr class="fr-row"><td>{{ $l['label'] }}</td><td class="fr-r {{ $l['amount'] < 0 ? 'fr-neg-n' : '' }}">{{ $fm($l['amount']) }}</td></tr>
        @empty
        <tr class="fr-row"><td style="color:#94a3b8">No movement in this period</td><td class="fr-r">0.00</td></tr>
        @endforelse
        <tr class="fr-sub"><td>Net cash provided by Investing Activities</td><td class="fr-r {{ $cf['investing'] < 0 ? 'fr-neg-n' : '' }}">{{ $fm($cf['investing']) }}</td></tr>

        <tr class="fr-sec"><td colspan="2">Cash Flow from Financing Activities</td></tr>
        @forelse($cf['lines']['financing'] as $l)
        <tr class="fr-row"><td>{{ $l['label'] }}</td><td class="fr-r {{ $l['amount'] < 0 ? 'fr-neg-n' : '' }}">{{ $fm($l['amount']) }}</td></tr>
        @empty
        <tr class="fr-row"><td style="color:#94a3b8">No movement in this period</td><td class="fr-r">0.00</td></tr>
        @endforelse
        <tr class="fr-sub"><td>Net cash provided by Financing Activities</td><td class="fr-r {{ $cf['financing'] < 0 ? 'fr-neg-n' : '' }}">{{ $fm($cf['financing']) }}</td></tr>

        <tr class="fr-key {{ $cf['net_change'] < 0 ? 'fr-neg' : '' }}"><td>Net Change in cash</td><td class="fr-r">{{ $fm($cf['net_change']) }}</td></tr>
        <tr class="fr-grand"><td>Ending Cash Balance</td><td class="fr-r">{{ $fm($cf['ending']) }}</td></tr>
    </tbody>
</table>
