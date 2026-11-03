{{-- The Departed Flights bar (.fdl-bar), same controls in the same order. The
     date range reads the SUBMISSION date. Wired in dashboard-overview. --}}
<div class="fdl-bar" id="cfd-closed-filter" role="search" aria-label="Filter delivered and closed files">
 <div class="fdl-search" id="cfdcSearchWrap">
  <i class="fas fa-magnifying-glass"></i>
  <input type="text" id="cfdcSearch" autocomplete="off" placeholder="Search file, applicant, passport, agent or vendor…" aria-label="Search completed files">
  <button type="button" class="fdl-clear" id="cfdcClear" title="Clear search"><i class="fas fa-xmark"></i></button>
 </div>
 <div class="fdl-seg">
  @foreach(['case_closed'=>['Case Closed','Every file at Case Closed'],'delivered'=>['Delivered','Delivered, not closed yet'],'vendor_due'=>['Vendor Due','Vendor still owed money'],'all'=>['All','Every completed file']] as $key=>[$label,$hint])
  <button type="button" data-cfd-closed-state="{{ $key }}" title="{{ $hint }}" aria-pressed="{{ $key==='all'?'true':'false' }}" @class(['is-on' => $key==='all'])>{{ $label }}</button>
  @endforeach
 </div>
 <div class="fdl-dates">
  <span class="fdl-date">
   <input type="text" id="cfdcFromText" class="fdl-date-text" placeholder="dd/mm/yyyy" inputmode="numeric" maxlength="10" autocomplete="off" aria-label="Submission date from" title="Submission date — from">
   <input type="date" id="cfdcFrom" class="fdl-date-native" aria-label="Pick the from date" title="Pick a date">
  </span>
  <span>to</span>
  <span class="fdl-date">
   <input type="text" id="cfdcToText" class="fdl-date-text" placeholder="dd/mm/yyyy" inputmode="numeric" maxlength="10" autocomplete="off" aria-label="Submission date to" title="Submission date — to">
   <input type="date" id="cfdcTo" class="fdl-date-native" aria-label="Pick the to date" title="Pick a date">
  </span>
  <button type="button" class="fdl-go" id="cfdcApply">Apply</button>
  <button type="button" class="fdl-print" id="cfdcPrint" title="Print the completed files on screen"><i class="fas fa-print"></i> Print</button>
 </div>
</div>
