{{-- Page header + KPI cards, as Flights & Passengers draws them (same classes,
     same tones). Status shows every file's journey strip; hidden on load. --}}
<header class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm mb-4">
 <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-orange-50 text-orange-600 flex-shrink-0"><i class="fas fa-folder-open"></i></span>
 <div class="flex-1">
  <h1 class="text-lg font-bold text-slate-900">Files &amp; Passengers</h1>
  <p class="text-xs text-slate-500">Every contract file and its applicant — add, edit, track documents and bill</p>
 </div>
 <button type="button" id="cfp-status" class="mfp-status-btn rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600" aria-pressed="false">
  <i class="fas fa-route mr-1"></i> Status
 </button>
 <button type="button" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600" onclick="window.print()">
  <i class="fas fa-file-export mr-1"></i> Export
 </button>
 @can('create contract file')
 <button type="button" class="create-btn rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700">
  <i class="fas fa-plus mr-1"></i> Add File
 </button>
 @endcan
</header>
@php
 $overviewFiles = $allFiles;
 $kpis = [
 ['Active Files',$overviewFiles->filter(fn($f)=>!in_array($f->status,['delivered','case_closed']) && !in_array($f->exception_flag,['rejected','cancelled']))->count(),'Open files, not yet delivered','indigo','fa-users'],
 ['Documents Pending',$overviewFiles->filter(fn($f)=>$f->documents_received < $f->documents_total)->count(),'One or more required documents missing','amber','fa-hourglass-half'],
 ['Under Process',$overviewFiles->whereIn('status',['submitted_to_vendor','biometrics_appointment','under_process','additional_documents'])->count(),'Submitted and being processed','emerald','fa-file-alt'],
 ['Due Applicants',$overviewFiles->filter(fn($f)=>$f->client_due > 0)->count(),'Client charge not fully paid','rose','fa-money-bill'],
 ['Cancelled',$overviewFiles->where('exception_flag','cancelled')->count(),'Cancelled file cases','red','fa-ban'],
 ['Rejected',$overviewFiles->where('exception_flag','rejected')->count(),'Rejected application cases','slate','fa-times-circle'],
 ['Delivered',$overviewFiles->whereIn('status',['delivered','case_closed'])->count(),'Delivered or case closed','sky','fa-check-circle'],
 ['Returned / Resubmitted',$overviewFiles->whereIn('exception_flag',['returned','resubmitted'])->count(),'Correction or resubmission required','violet','fa-exchange-alt'],
 ];
@endphp
<div class="mfp-kpis mb-4">
@foreach($kpis as [$label,$count,$hint,$tone,$icon])
@php
 $key=['active','documents','processing','due','cancelled','rejected','delivered','returned'][$loop->index];
 $selected=request('stage_filter')===$key;
 $params=request()->except(['page','stage_filter','status']);
 if(!$selected)$params['stage_filter']=$key;
 $t=$mfpTones[$tone];
@endphp
 <a href="{{ route('role.contract-files.index',array_merge(['role'=>$role],$params)) }}#cfp-table" class="mfp-kpi{{ $selected ? ' is-on' : '' }}"
    style="--mfp-a:{{ $t[0] }};--mfp-b:{{ $t[1] }};--mfp-ia:{{ $t[2] }};--mfp-ib:{{ $t[3] }};--mfp-num:{{ $t[4] }};--mfp-lbl:{{ $t[5] }};--mfp-hint:{{ $t[6] }};--mfp-ring:{{ $t[7] }}"
    title="{{ $selected ? 'Click to clear this filter' : 'Show only ' . strtolower($label) }}" aria-pressed="{{ $selected ? 'true' : 'false' }}">
  <span class="mfp-kpi-icon"><i class="fas {{ $icon }}"></i></span>
  <span class="mfp-kpi-num">{{ $count }}</span>
  <span class="mfp-kpi-lbl">{{ $label }}</span>
  <span class="mfp-kpi-hint">{{ $selected ? 'Filtering · click to clear' : $hint }}</span>
 </a>
@endforeach
</div>
