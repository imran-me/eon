@php
 $isGroupLedger = (bool) $groupClient || request()->boolean('unassigned');
 $columnCount = $isGroupLedger ? 8 : 9;
 $cfpPassportUrl = \Illuminate\Support\Facades\Route::has('role.passport-holder.index') ? route('role.passport-holder.index', ['role' => $role]) : null;
 // Flights & Passengers' $mfpTones: [card a, b] [icon a, b] number / label / hint / ring.
 // The list's KPI cards and its row tint read the same table.
 $mfpTones = [
  'indigo'  => ['#eef2ff', '#e0e7ff', '#818cf8', '#4f46e5', '#3730a3', '#4f46e5', '#818cf8', '#6366f1'],
  'amber'   => ['#fffbeb', '#fef3c7', '#fbbf24', '#d97706', '#92400e', '#d97706', '#fbbf24', '#f59e0b'],
  'emerald' => ['#ecfdf5', '#d1fae5', '#34d399', '#059669', '#065f46', '#059669', '#34d399', '#10b981'],
  'rose'    => ['#fff1f2', '#ffe4e6', '#fb7185', '#e11d48', '#9f1239', '#e11d48', '#fb7185', '#f43f5e'],
  'red'     => ['#fef2f2', '#fee2e2', '#f87171', '#dc2626', '#991b1b', '#dc2626', '#f87171', '#ef4444'],
  'slate'   => ['#f8fafc', '#e2e8f0', '#94a3b8', '#475569', '#1e293b', '#475569', '#94a3b8', '#64748b'],
  'sky'     => ['#f0f9ff', '#e0f2fe', '#38bdf8', '#0284c7', '#075985', '#0284c7', '#38bdf8', '#0ea5e9'],
  'violet'  => ['#f5f3ff', '#ede9fe', '#a78bfa', '#7c3aed', '#4c1d95', '#7c3aed', '#a78bfa', '#8b5cf6'],
 ];
 // Which KPI card a file belongs to, in the flight's order: exceptions first,
 // then money owed, then where it stands (ContractFlightPassenger::kpiKey twin).
 $cfpRowTone = function ($file) {
  if ($file->exception_flag === 'cancelled') return ['red', 'Cancelled'];
  if ($file->exception_flag === 'rejected') return ['slate', 'Rejected'];
  if (in_array($file->exception_flag, ['returned', 'resubmitted'], true)) return ['violet', 'Returned / Resubmitted'];
  if ($file->client_due > 0) return ['rose', 'Due Applicants'];
  if (in_array($file->status, ['delivered', 'case_closed'], true)) return ['sky', 'Delivered'];
  if ($file->documents_received < $file->documents_total) return ['amber', 'Documents Pending'];
  if (in_array($file->status, ['submitted_to_vendor', 'biometrics_appointment', 'under_process', 'additional_documents'], true)) return ['emerald', 'Under Process'];
  return ['indigo', 'Active Files'];
 };
@endphp
<div class="{{ $isGroupLedger ? 'cfp-ledger' : 'cfp-list' }}" @unless($isGroupLedger) id="cfp-list" @endunless data-file-group-ledger>
 <script type="application/json" data-file-group-records>@json($filesJson)</script>
 @if($groupClient || request()->boolean('unassigned'))
 @include('contract-files.ledger-header')
 @else
 @include('contract-files.list-overview')
 @endif
 {{-- The table card: Flights & Passengers' (.mfp-table, its row dress, its
      action buttons, its journey card under each row). --}}
 <div class="cfp-table-card {{ $isGroupLedger ? 'rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden' : 'overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm' }}" @unless($isGroupLedger) id="cfp-table" @endunless>
 @unless($isGroupLedger)
 @include('contract-files.list-filter')
 @else
 <form method="GET" class="fdl-bar fdl-group-filter" data-group-filter role="search" aria-label="Filter this group's files">
  <div class="fdl-search"><i class="fas fa-magnifying-glass"></i><input type="search" name="search" value="{{ request('search') }}" placeholder="Search applicant, passport, agent or file…" aria-label="Search files"></div>
  @if(request()->boolean('unassigned'))<input type="hidden" name="unassigned" value="1">@endif
  <select name="status" aria-label="Stage"><option value="">All stages</option>@foreach($statuses as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select>
  <div class="fdl-dates">
   <input type="date" name="from_date" value="{{ request('from_date') }}" aria-label="Submitted from"><span>to</span><input type="date" name="to_date" value="{{ request('to_date') }}" aria-label="Submitted to">
   <button type="submit" class="fdl-go">Apply</button>
   <a class="fdl-print" href="{{ $groupClient ? route('role.contract-files.agent-files',['role'=>$role,'client'=>$groupClient]) : route('role.contract-files.index',['role'=>$role,'unassigned'=>request()->boolean('unassigned') ? 1 : null]) }}"><i class="fas fa-rotate-left"></i> Reset</a>
  </div>
 </form>
 @endunless
 <div class="overflow-x-auto"><table class="mfp-table" data-cf-sort>
  @if($isGroupLedger)
  <colgroup><col style="width:15%"><col style="width:17%"><col style="width:13%"><col style="width:12%"><col style="width:10%"><col style="width:16%"><col style="width:10%"><col style="width:7%"></colgroup>
  @else
  <colgroup><col style="width:3%"><col style="width:14%"><col style="width:16%"><col style="width:13%"><col style="width:12%"><col style="width:10%"><col style="width:15%"><col style="width:10%"><col style="width:7%"></colgroup>
  @endif
  <thead><tr>@unless($isGroupLedger)<th>SL</th>@endunless<th>Applicant / Passport</th><th>Agent / Vendor</th><th>Documents</th><th>Country / Dates</th><th>Stage</th><th title="What the client is charged, then each cost line">Charge &amp; Cost Breakdown</th><th class="mfp-num">Cost / Profit</th><th class="mfp-center" data-nosort>Actions</th></tr></thead>
  <tbody>
 @forelse($datas as $file)
  @php
   [$cfpTone, $cfpToneLabel] = $isGroupLedger ? [null, null] : $cfpRowTone($file);
   $cfpT = $cfpTone ? $mfpTones[$cfpTone] : null;
  @endphp
  <tr class="cf-row mfp-row cfp-file-row" data-file-row="{{ $file->id }}"@if($cfpT) style="--mfp-a:{{ $cfpT[0] }};--mfp-b:{{ $cfpT[1] }};--mfp-ring:{{ $cfpT[7] }}" title="{{ $cfpToneLabel }}"@endif>
   @unless($isGroupLedger)<td class="mfp-sl">{{ $datas->firstItem() + $loop->index }}</td>@endunless
   <td>
    @if($cfpPassportUrl && $file->applicant_name)<a class="mfp-passenger mfp-name" href="{{ $cfpPassportUrl }}?name={{ urlencode($file->applicant_name) }}" title="Open {{ $file->applicant_name }}'s passport record">{{ $file->applicant_name }}</a>@else<strong class="mfp-passenger mfp-name">{{ $file->applicant_name ?: 'Applicant not assigned' }}</strong>@endif
    <div class="mfp-ln mfp-mono" title="Passport"><i class="fas fa-passport mfp-ico"></i>{{ $file->passport_no ?? '—' }}</div>
    <div class="mfp-ln" title="File number"><i class="fas fa-folder-open mfp-ico"></i>{{ $file->file_number }}</div>
    @if($file->phone)<div class="mfp-ln mfp-mono" title="Phone"><i class="fas fa-phone mfp-ico"></i>{{ $file->phone }}</div>@endif
   </td>
   <td>@include('contract-files.party-cell')</td>
   <td data-sort="{{ $file->documents_received }}">
    @foreach(collect($file->required_documents)->take(3) as $doc)
    <div class="mfp-ln" title="{{ ($doc['checked'] ?? false) ? 'Received' : 'Pending' }}"><i class="fas {{ ($doc['checked'] ?? false) ? 'fa-check mfp-clear' : 'fa-clock' }} mfp-ico"></i>{{ $doc['name'] ?? '' }}</div>
    @endforeach
   </td>
   <td data-sort="{{ $file->submit_date?->toDateString() ?? '' }}">
    <div class="mfp-nowrap">{{ $file->country?->name ?? '—' }}</div>
    @if($file->relationLoaded('visaType') && $file->visaType)<div class="mfp-ln" title="Visa type"><i class="fas fa-passport mfp-ico"></i>{{ $file->visaType->name }}</div>@endif
    <div class="mfp-ln" title="Submitted"><i class="fas fa-calendar-day mfp-ico"></i>{{ $file->submit_date?->format('d M Y') ?? '—' }}</div>
    <div class="mfp-ln" title="Expected out"><i class="fas fa-flag-checkered mfp-ico"></i>{{ $file->expected_out_date?->format('d M Y') ?? 'Expected —' }}</div>
   </td>
   <td>
    <span class="mfp-stage">{{ $file->status_label }}</span>
    @if($file->exception_flag)<small class="mfp-flag-tag"><i class="fas fa-triangle-exclamation"></i>{{ \App\Models\ContractFile::EXCEPTION_FLAGS[$file->exception_flag] ?? $file->exception_flag }}</small>@endif
   </td>
   @include('contract-files.list-money-cells')
   <td>
    <div class="mfp-actions">
     <button type="button" class="mfp-btn blue" data-file-print="{{ $file->id }}" title="Print file record" aria-label="Print {{ $file->file_number }}"><i class="fas fa-print"></i></button>
     <a class="mfp-btn blue" href="{{ route('role.contract-files.show',['role'=>$role,'contractFile'=>$file]) }}" data-file-sheet="{{ $file->id }}" title="View file record"><i class="fas fa-eye"></i></a>
     @can('edit contract file')<button type="button" class="mfp-btn amber" onclick="editFile({{ $file->id }})" title="Edit file"><i class="fas fa-pen"></i></button>@endcan
     @include('contract-files.money-action-buttons')
     @can('delete contract file')<button type="button" class="mfp-btn red cfw-delete" onclick="confirmDelete({{ $file->id }}, @js($file->file_number.' · '.$file->applicant_name))" title="Delete wrong file entry" aria-label="Delete {{ $file->file_number }}"><i class="fas fa-trash"></i></button>@endcan
    </div>
   </td>
  </tr>
  @include('contract-files.journey-strip',['file'=>$file,'colspan'=>$columnCount,'openStrip'=>$isGroupLedger,'ringColor'=>$cfpT[7] ?? null])
 @empty
  <tr><td colspan="{{ $columnCount }}" class="mfp-empty">No files found.</td></tr>
 @endforelse
  </tbody>
 </table></div>
 @if($datas->hasPages())<div class="p-3">{{ $datas->links() }}</div>@endif
 </div>
</div>
@unless($isGroupLedger) @include('contract-files.list-behavior') @endunless
