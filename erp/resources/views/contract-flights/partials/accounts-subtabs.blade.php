{{-- Flight Accounts' Agent / Vendor / Company Ledger tabs reuse Party
     Statement's own screens; on them (only when $cfLedger is set) this
     replaces Party Statement's tab band with the Contract Flight band and the
     same Flight Accounts header + switcher the accounts page has. --}}
@include('layout.contract-flight-tabs')
@if(!empty($cfaDue))
@php extract($cfaDue); @endphp
@include('contract-flights.partials.passenger-table-styles')
@include('contract-flights.partials.accounts-styles')
<div class="cfa-wrap" style="margin-bottom:14px">
@include('contract-flights.partials.accounts-top')
</div>
@endif
