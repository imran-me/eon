{{-- Balance sheet table — shared by the screen and the print page. Expects $bs, $frAsOf, $frRole, $frLedgerScope, $frPrinting. --}}
@php
    $fm = fn ($n) => number_format((float) $n, 2);
    $canLedger = ! ($frPrinting ?? false) && $frLedgerScope && auth()->user()->can('view account ledger report');
    $ledgerUrl = fn (int $id) => $canLedger
        ? route('role.report.account-ledger', ['role' => $frRole, 'account_id' => $id, 'date_to' => $frAsOf, 'scope' => $frLedgerScope, 'from_report' => 'balance-sheet'])
        : null;
    $S = $bs['sections'];
@endphp
<table class="fr-st">
    <thead><tr><th>Account</th><th class="fr-r">Total</th></tr></thead>
    <tbody>
    @foreach(['current_assets', 'fixed_assets', 'other_assets'] as $k)
        <tr class="fr-sec"><td colspan="2">@if($k === 'current_assets')Assets · @endif{{ $S[$k]['label'] }}</td></tr>
        @foreach($S[$k]['lines'] as $line)
            <tr class="fr-grp"><td colspan="2">{{ $line['label'] }}</td></tr>
            @foreach($line['rows'] as $r)
            @php $u = $ledgerUrl($r['id']); @endphp
            <tr class="fr-row"><td><span class="fr-code">{{ $r['code'] }}</span>@if($u)<a class="fr-link" href="{{ $u }}">{{ $r['name'] }}</a>@else{{ $r['name'] }}@endif</td><td class="fr-r {{ $r['amount'] < 0 ? 'fr-neg-n' : '' }}">{{ $fm($r['amount']) }}</td></tr>
            @endforeach
            <tr class="fr-sub"><td style="padding-left:34px">Total for {{ $line['label'] }}</td><td class="fr-r">{{ $fm($line['total']) }}</td></tr>
        @endforeach
        <tr class="fr-sub"><td>Total for {{ $S[$k]['label'] }}</td><td class="fr-r">{{ $fm($S[$k]['total']) }}</td></tr>
    @endforeach
    <tr class="fr-key"><td>Total for Assets</td><td class="fr-r">{{ $fm($bs['total_assets']) }}</td></tr>

    @foreach(['current_liabilities', 'noncurrent_liabilities'] as $k)
        <tr class="fr-sec"><td colspan="2">@if($k === 'current_liabilities')Liabilities &amp; Equities · @endif{{ $S[$k]['label'] }}</td></tr>
        @foreach($S[$k]['lines'] as $line)
            <tr class="fr-grp"><td colspan="2">{{ $line['label'] }}</td></tr>
            @foreach($line['rows'] as $r)
            @php $u = $ledgerUrl($r['id']); @endphp
            <tr class="fr-row"><td><span class="fr-code">{{ $r['code'] }}</span>@if($u)<a class="fr-link" href="{{ $u }}">{{ $r['name'] }}</a>@else{{ $r['name'] }}@endif</td><td class="fr-r {{ $r['amount'] < 0 ? 'fr-neg-n' : '' }}">{{ $fm($r['amount']) }}</td></tr>
            @endforeach
            <tr class="fr-sub"><td style="padding-left:34px">Total for {{ $line['label'] }}</td><td class="fr-r">{{ $fm($line['total']) }}</td></tr>
        @endforeach
        <tr class="fr-sub"><td>Total for {{ $S[$k]['label'] }}</td><td class="fr-r">{{ $fm($S[$k]['total']) }}</td></tr>
    @endforeach
    <tr class="fr-key"><td>Total for Liabilities</td><td class="fr-r">{{ $fm($bs['total_liabilities']) }}</td></tr>

    <tr class="fr-sec"><td colspan="2">Equities</td></tr>
    @foreach($S['equity']['lines'] as $line)
        @foreach($line['rows'] as $r)
        @php $u = $ledgerUrl($r['id']); @endphp
        <tr class="fr-row"><td><span class="fr-code">{{ $r['code'] }}</span>@if($u)<a class="fr-link" href="{{ $u }}">{{ $r['name'] }}</a>@else{{ $r['name'] }}@endif</td><td class="fr-r {{ $r['amount'] < 0 ? 'fr-neg-n' : '' }}">{{ $fm($r['amount']) }}</td></tr>
        @endforeach
    @endforeach
    <tr class="fr-row"><td>Current Year Earnings <span class="fr-code" style="margin-left:6px">since {{ \Carbon\Carbon::parse($bs['fy_start'])->format('d M Y') }}</span></td><td class="fr-r {{ $bs['current_year_earnings'] < 0 ? 'fr-neg-n' : '' }}">{{ $fm($bs['current_year_earnings']) }}</td></tr>
    <tr class="fr-row"><td>Retained Earnings (profit of earlier periods)</td><td class="fr-r {{ $bs['prior_earnings'] < 0 ? 'fr-neg-n' : '' }}">{{ $fm($bs['prior_earnings']) }}</td></tr>
    <tr class="fr-key"><td>Total for Equities</td><td class="fr-r">{{ $fm($bs['total_equity']) }}</td></tr>
    <tr class="fr-grand"><td>Total for Liabilities &amp; Equities</td><td class="fr-r">{{ $fm($bs['total_liab_equity']) }}</td></tr>
    </tbody>
</table>
