{{-- The due sheet under Visa Accounts' Agent / Vendor Ledger tab (Party
     Statement's ledger view includes this only when $vaLedger is set). --}}
@if(!empty($vaDue) && in_array($vaLedger ?? null, ['agents', 'vendors'], true))
@php extract($vaDue); $vaDueKind = $vaLedger; @endphp
@include('dashboard.visa_accounts.partials.due-sheet')
@include('contract-flights.partials.table-sort')
@endif
