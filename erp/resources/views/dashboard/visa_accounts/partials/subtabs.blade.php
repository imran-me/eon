{{-- Visa Accounts' Agent / Vendor Ledger tabs reuse Party Statement's own
     screens; on them (only when $vaLedger is set) this replaces Party
     Statement's tab band with the Visa band and Visa Accounts' KPI strip +
     switcher. --}}
@include('layout.visa-processing-tabs')
@if(!empty($vaDue))
@php extract($vaDue); @endphp
@include('contract-flights.partials.accounts-styles')
<div class="cfa-wrap" style="margin-bottom:14px">
@include('dashboard.visa_accounts.partials.top')
</div>
@endif
