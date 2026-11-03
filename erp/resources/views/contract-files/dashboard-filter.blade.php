{{-- The Flight Dashboard's master filter (.fd-master-filter), same order as
     there: search, agent, from, to, then the presets — its grid places them by
     nth-child, so the hidden inputs go LAST or they would shift every column.
     A GET form: the file groups are filtered server-side. No Tomorrow preset:
     the dates are SUBMISSION dates, and nothing is submitted tomorrow. --}}
<form method="GET" id="cfd-filter" class="fd-table-head fd-master-filter" role="search" aria-label="Filter dashboard files">
 <div class="fd-filter-field">
  <label for="cfdSearch">Search files</label>
  <div class="fd-filter-search"><i class="fas fa-search" aria-hidden="true"></i><input type="search" id="cfdSearch" name="search" value="{{ request('search') }}" placeholder="Search file, applicant, passport, agent or vendor…"></div>
 </div>
 <div class="fd-filter-field">
  <label for="cfdAgent">Agent / Client</label>
  <select id="cfdAgent" name="client_id"><option value="">All agents / clients</option>@foreach($clients as $client)<option value="{{ $client->id }}" @selected(request('client_id')==$client->id)>{{ $client->name }}</option>@endforeach</select>
 </div>
 <div class="fd-filter-field"><label for="cfdFrom">Submit from</label><input type="date" id="cfdFrom" name="from_date" value="{{ request('from_date') }}"></div>
 <div class="fd-filter-field"><label for="cfdTo">Submit to</label><input type="date" id="cfdTo" name="to_date" value="{{ request('to_date') }}"></div>
 @php
  $cfdToday = now()->startOfDay();
  $cfdPresetRanges = [
   'today' => [$cfdToday, $cfdToday],
   'yesterday' => [$cfdToday->copy()->subDay(), $cfdToday->copy()->subDay()],
   'week' => [$cfdToday->copy()->startOfWeek(), $cfdToday->copy()->endOfWeek()],
  ];
 @endphp
 <div class="fd-date-presets" aria-label="Quick submission date filters">
  @foreach(['today'=>'Today','yesterday'=>'Yesterday','week'=>'This week'] as $key=>$label)<button type="button" data-cfd-date="{{ $key }}" @class(['is-active' => request('from_date') === $cfdPresetRanges[$key][0]->toDateString() && request('to_date') === $cfdPresetRanges[$key][1]->toDateString()])>{{ $label }}</button>@endforeach
  <button type="button" data-cfd-clear>Clear</button>
 </div>
 @foreach(['contract_file_category_id','status'] as $preserved)
 @if(request($preserved))<input type="hidden" name="{{ $preserved }}" value="{{ request($preserved) }}">@endif
 @endforeach
 <button type="submit" class="sr-only">Filter files</button>
</form>
