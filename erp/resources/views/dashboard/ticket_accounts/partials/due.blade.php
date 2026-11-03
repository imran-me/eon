{{-- The due sheet under Ticket Accounts' Agent / Vendor Ledger tab (Party
     Statement's ledger view includes this only when $tkLedger is set). --}}
@if(!empty($tkaDue) && in_array($tkLedger ?? null, ['agents', 'vendors'], true))
@php extract($tkaDue); $tkDueKind = $tkLedger; @endphp
@include('dashboard.ticket_accounts.partials.due-sheet')
@include('contract-flights.partials.table-sort')
@endif
