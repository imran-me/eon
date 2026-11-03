{{-- Profit & Loss statement table — shared by the screen and the print page. Expects $pl, $frFrom, $frTo, $frLedgerScope, $frRole, $frPrinting (bool). --}}
@php
    $fm = fn ($n) => number_format((float) $n, 2);
    $canLedger = ! ($frPrinting ?? false) && $frLedgerScope && auth()->user()->can('view account ledger report');
    $ledgerUrl = fn (int $id) => $canLedger
        ? route('role.report.account-ledger', ['role' => $frRole, 'account_id' => $id, 'date_from' => $frFrom, 'date_to' => $frTo, 'scope' => $frLedgerScope, 'from_report' => 'profit-loss'])
        : null;
    $S = $pl['sections'];
@endphp
<table class="fr-st">
    <thead><tr><th>Account</th><th class="fr-r">Total</th></tr></thead>
    <tbody>
        @include('report.statements._section', ['sx' => $S['operating_income'], 'showEmpty' => true])
        @include('report.statements._section', ['sx' => $S['cogs'], 'showEmpty' => true])
        <tr class="fr-key {{ $pl['gross_profit'] < 0 ? 'fr-neg' : '' }}"><td>Gross Profit @if($pl['gross_margin'] !== null)<span style="font-weight:600; opacity:.8"> · {{ number_format($pl['gross_margin'], 1) }}% margin</span>@endif</td><td class="fr-r">{{ $fm($pl['gross_profit']) }}</td></tr>
        @include('report.statements._section', ['sx' => $S['operating_expense'], 'showEmpty' => true])
        <tr class="fr-key {{ $pl['operating_profit'] < 0 ? 'fr-neg' : '' }}"><td>Operating Profit</td><td class="fr-r">{{ $fm($pl['operating_profit']) }}</td></tr>
        @include('report.statements._section', ['sx' => $S['nonop_income'], 'showEmpty' => false])
        @include('report.statements._section', ['sx' => $S['other_income'], 'showEmpty' => false])
        @include('report.statements._section', ['sx' => $S['nonop_expense'], 'showEmpty' => false])
        @include('report.statements._section', ['sx' => $S['tax_control'], 'showEmpty' => false])
        @include('report.statements._section', ['sx' => $S['other_expense'], 'showEmpty' => false])
        <tr class="fr-grand"><td>Net Profit/Loss @if($pl['net_margin'] !== null)<span style="font-weight:600; opacity:.8"> · {{ number_format($pl['net_margin'], 1) }}% of operating income</span>@endif</td><td class="fr-r">{{ $fm($pl['net_profit']) }}</td></tr>
    </tbody>
</table>
