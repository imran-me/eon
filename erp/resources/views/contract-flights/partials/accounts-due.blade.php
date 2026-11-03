{{-- Flight Accounts: the due sheet under the Agent / Vendor Ledger tab
     (Party Statement's ledger view includes this only when $cfLedger is set). --}}
@if(!empty($cfaDue) && in_array($cfLedger ?? null, ['agents', 'vendors'], true))
@php extract($cfaDue); $cfaDueKind = $cfLedger; @endphp
@include('contract-flights.partials.accounts-due-sheet')
@include('contract-flights.partials.table-sort')
@include('contract-flights.partials.accounts-due-scripts')
@endif
