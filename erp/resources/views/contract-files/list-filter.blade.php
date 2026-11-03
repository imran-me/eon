{{-- Flights & Passengers' filter bar (.mfp-filter), ONE row (owner, 2026-10-25:
     "why like this?" at a two-row bar). Same seven slots as the flight, in the
     flight's grid: Search, Agent / Client, Stage, presets, the submission-date
     range, Filter / Reset. No Category select — the search already matches the
     category name and every row shows it; no Tomorrow — nothing is submitted
     tomorrow. Presets fill the two dates and submit; paginated server-side. --}}
@php
 $cfpToday = now()->startOfDay();
 $cfpPresetRanges = [
  'today' => [$cfpToday, $cfpToday],
  'yesterday' => [$cfpToday->copy()->subDay(), $cfpToday->copy()->subDay()],
  'week' => [$cfpToday->copy()->startOfWeek(), $cfpToday->copy()->endOfWeek()],
 ];
@endphp
<form method="GET" action="{{ route('role.contract-files.index', ['role' => $role]) }}" class="mfp-filter print:hidden" role="search" aria-label="Filter files" id="cfp-filter">
 @if(request('stage_filter'))<input type="hidden" name="stage_filter" value="{{ request('stage_filter') }}">@endif
 <div class="mfp-filter-field mfp-filter-search-field">
  <label for="cfpSearch">Search</label>
  <div class="mfp-filter-search"><i class="fas fa-search" aria-hidden="true"></i><input type="search" id="cfpSearch" name="search" value="{{ request('search') }}" placeholder="Search file, applicant, passport, agent or vendor…"></div>
 </div>
 <div class="mfp-filter-field">
  <label for="cfpAgent">Agent / Client</label>
  <select id="cfpAgent" name="client_id"><option value="">All agents / clients</option>@foreach($clients as $client)<option value="{{ $client->id }}" @selected(request('client_id')==$client->id)>{{ $client->name }}</option>@endforeach</select>
 </div>
 <div class="mfp-filter-field">
  <label for="cfpStage">Stage</label>
  <select id="cfpStage" name="status"><option value="">All stages</option>@foreach($statuses as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select>
 </div>
 <div class="mfp-date-presets" aria-label="Quick submission date filters">
  @foreach(['today'=>'Today','yesterday'=>'Yesterday','week'=>'This week'] as $key=>$label)<button type="button" data-cfp-date="{{ $key }}" @class(['is-active' => request('from_date') === $cfpPresetRanges[$key][0]->toDateString() && request('to_date') === $cfpPresetRanges[$key][1]->toDateString()])>{{ $label }}</button>@endforeach
  <button type="button" data-cfp-date="clear">Clear</button>
 </div>
 <div class="mfp-filter-field"><label for="cfpFrom">Submit from</label><input type="date" id="cfpFrom" name="from_date" value="{{ request('from_date') }}"></div>
 <div class="mfp-filter-field"><label for="cfpTo">Submit to</label><input type="date" id="cfpTo" name="to_date" value="{{ request('to_date') }}"></div>
 <div class="mfp-filter-actions">
  <button type="submit" class="is-primary"><i class="fas fa-filter"></i>Filter</button>
  <a href="{{ route('role.contract-files.index', array_filter(['role' => $role, 'company' => request('company')])) }}" class="mfp-filter-reset">Reset</a>
 </div>
</form>
