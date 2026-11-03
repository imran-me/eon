{{-- Ticket Accounts' Agent / Vendor Ledger tabs reuse Party Statement's own
     screens; on them (only when $tkLedger is set) this replaces Party
     Statement's tab band with the Ticketing band and the same KPI strip +
     switcher the Ticket Accounts page has. --}}
@include('layout.ticketing-tabs')
@if(!empty($tkaDue))
@php extract($tkaDue); @endphp
@include('contract-flights.partials.accounts-styles')
<div class="cfa-wrap" style="margin-bottom:14px">
@include('dashboard.ticket_accounts.partials.top')
</div>
@endif
