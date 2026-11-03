@extends('layout.app')
@php
 $cfsTitles = ['requirements' => 'Requirements', 'price-presets' => 'Price Presets', 'visa-types' => 'Visa Types'];
 $cfsTitle = $cfsTitles[$kind];
 $canCreate = auth()->user()?->can('create contract file category');
 $canEdit = auth()->user()?->can('edit contract file category');
 $canDelete = auth()->user()?->can('delete contract file category');
 $money = fn ($v) => $v === null ? '—' : '৳'.number_format((float) $v);
 $flag = fn ($country) => $country?->code ? asset('flags/'.strtolower($country->code).'.png') : null;
 // Every document already named anywhere in Setup — offered as one-click chips in the builder.
 $knownDocs = $kind === 'requirements' ? $rows->flatMap(fn ($r) => $r->documents_list)->merge($categories->flatMap(fn ($c) => $c->documents_list))->unique(fn ($d) => mb_strtolower($d))->sort()->values() : collect();
@endphp
@section('meta-information')
<title>{{ $cfsTitle }} — Contract File Setup</title>
@endsection
@section('main-content')
@include('layout.contract-file-tabs')
@include('layout.contract-file-setup-tabs')

{{-- Contract File → Setup (owner, 2026-10-25), laid out as Contract Flight's Flight Category pages:
     header card, four stat cards, the table card with its filter row, a modal that closes only on its
     cross. A rule's scope is country / visa type / job category — any mix; an empty part means "any". --}}
<header class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm mb-4">
 <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-blue-50 text-blue-600 flex-shrink-0"><i class="fas {{ ['requirements' => 'fa-list-check', 'price-presets' => 'fa-coins', 'visa-types' => 'fa-passport'][$kind] }}"></i></span>
 <div class="flex-1">
  <h1 class="text-lg font-bold text-slate-900">{{ $cfsTitle }}</h1>
  <p class="text-xs text-slate-500">@switch($kind)
   @case('requirements') Requirement builder — documents by country, visa type and job category; every matching rule adds to a new file's checklist @break
   @case('price-presets') Processing cost, training fee and expected days by country, visa type and job category; the most specific match fills a new file @break
   @default Contract File's own visa types — pick one on a file, scope requirements and prices to it
  @endswitch</p>
 </div>
 @if($installed && $canCreate)<button type="button" class="cfs-add rounded-lg bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700"><i class="fas fa-plus mr-1"></i> Add {{ ['requirements' => 'Requirement', 'price-presets' => 'Price Preset', 'visa-types' => 'Visa Type'][$kind] }}</button>@endif
</header>

@unless($installed)
<div class="mb-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800">
 <i class="fas fa-triangle-exclamation mt-0.5"></i>
 <div><b>Setup is not installed on this server yet.</b> Its database update has not run, so nothing can be saved here. File entry works as before (documents and visa rate from the job category).</div>
</div>
@else

@php
 $cards = match ($kind) {
  'requirements' => [['blue', 'fa-list-check', $stats['total'], 'Active rules', 'Document lists in use'], ['emerald', 'fa-earth-asia', $stats['countries'], 'Countries', 'with their own rules'], ['amber', 'fa-passport', $stats['visa_types'], 'Visa types', 'with their own rules'], ['violet', 'fa-file-lines', $stats['documents'], 'Documents', 'distinct names across rules']],
  'price-presets' => [['blue', 'fa-coins', $stats['total'], 'Active presets', 'Scopes with figures'], ['emerald', 'fa-file-invoice-dollar', $stats['with_cost'], 'Processing cost', 'presets that set it'], ['amber', 'fa-chalkboard-user', $stats['with_training'], 'Training fee', 'presets that set it'], ['violet', 'fa-calendar-day', $stats['with_days'], 'Expected days', 'presets that set it']],
  default => [['blue', 'fa-passport', $stats['total'], 'Active visa types', 'to choose on a file'], ['emerald', 'fa-folder-open', $stats['files'], 'Files', 'carry a visa type'], ['amber', 'fa-list-check', $stats['rules'], 'Requirement rules', 'scoped to a visa type'], ['violet', 'fa-coins', $stats['presets'], 'Price presets', 'scoped to a visa type']],
 };
@endphp
<section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
 @foreach($cards as [$tone, $icon, $value, $label, $hint])
 <div class="rounded-xl bg-{{ $tone }}-50 p-4">
  <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-{{ $tone }}-400 to-{{ $tone }}-600 flex items-center justify-center text-white mb-3 shadow-md"><i class="fas {{ $icon }}"></i></div>
  <b class="block text-2xl font-bold text-{{ $tone }}-900">{{ $value }}</b>
  <p class="text-[10px] font-bold uppercase text-{{ $tone }}-700 mt-1">{{ $label }}</p>
  <p class="text-xs text-slate-500 mt-1">{{ $hint }}</p>
 </div>
 @endforeach
</section>

@if($kind !== 'visa-types')
{{-- Try a file: what a new file with this country / visa type / job category gets — the same answer
     the file entry gets (ContractFileSetup::resolve() through the match endpoint). --}}
<section class="cfs-try mb-4 rounded-xl border border-slate-200 bg-white shadow-sm">
 <div class="cfs-try-head"><i class="fas fa-wand-magic-sparkles"></i><b>Try a file</b><small>pick what a new file would have — see the checklist and figures it gets</small></div>
 <div class="cfs-try-body">
  <div class="cfs-try-pick">
   <label><span>Country</span><select id="cfsTryCountry" data-no-s2><option value="">Any</option>@foreach($countries as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></label>
   <label><span>Visa type</span><select id="cfsTryVisa" data-no-s2><option value="">Any</option>@foreach($visaTypes->where('status', 'active') as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach</select></label>
   <label><span>Job category</span><select id="cfsTryCategory" data-no-s2><option value="">Any</option>@foreach($categories as $c)<option value="{{ $c->id }}" data-country="{{ $c->country_id }}">{{ $c->name }}{{ $c->country ? ' · '.$c->country->name : '' }}</option>@endforeach</select></label>
  </div>
  <div class="cfs-try-out" id="cfsTryOut"><p class="cfs-muted">Pick a country, visa type or job category.</p></div>
 </div>
</section>
@endif

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
 <form method="GET" class="flex items-center gap-3 bg-slate-50 px-4 py-3">
  <div class="flex flex-1 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2">
   <i class="fas fa-search text-xs text-slate-400"></i>
   <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ $kind === 'visa-types' ? 'Search visa type, code…' : 'Search country, visa type, category or document…' }}" class="flex-1 border-0 outline-none text-sm bg-transparent">
  </div>
  @if($kind !== 'visa-types')
  <div class="cfs-filter-select"><select name="country_id" aria-label="Country"><option value="">All countries</option>@foreach($countries as $c)<option value="{{ $c->id }}" @selected(request('country_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
  <div class="cfs-filter-select"><select name="visa_type_id" aria-label="Visa type"><option value="">All visa types</option>@foreach($visaTypes as $v)<option value="{{ $v->id }}" @selected(request('visa_type_id') == $v->id)>{{ $v->name }}</option>@endforeach</select></div>
  @endif
  <button class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white">Filter</button>
  <a href="{{ route('role.contract-file-setup.index', ['role' => $role, 'kind' => $kind]) }}" class="rounded-lg border px-4 py-2 text-xs font-semibold text-slate-600">Reset</a>
 </form>
 <div class="overflow-x-auto">
  <table class="min-w-full divide-y divide-slate-200">
   <thead class="bg-slate-50">
    <tr>
     @if($kind === 'visa-types')
      @foreach(['Visa type', 'Code', 'Description', 'Files', 'Rules', 'Status'] as $h)<th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ $h }}</th>@endforeach
     @else
      <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Scope</th>
      <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Applies to</th>
      @if($kind === 'requirements')
       <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Documents</th>
      @else
       @foreach(['Processing cost', 'Training fee', 'Expected days'] as $h)<th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ $h }}</th>@endforeach
      @endif
      <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Status</th>
     @endif
     <th class="px-2 py-3 text-right text-[10px] font-bold uppercase tracking-wide text-slate-500" style="width:1%;white-space:nowrap">Action</th>
    </tr>
   </thead>
   <tbody class="divide-y divide-slate-100">
   @forelse($rows as $row)
    @php
     $payload = $kind === 'visa-types'
      ? $row->only(['id', 'name', 'description', 'status'])
      : $row->only(['id', 'country_id', 'contract_file_visa_type_id', 'contract_file_category_id', 'status']) + ($kind === 'requirements' ? ['documents' => $row->documents_list, 'notes' => $row->notes] : $row->only(['processing_cost', 'training_fee', 'expected_days']));
    @endphp
    <tr class="cf-row" data-cfs-row='@json($payload)'>
     @if($kind === 'visa-types')
      <td class="px-4 py-3"><div class="flex items-center gap-2 text-sm font-semibold text-slate-900"><i class="fas fa-passport text-blue-500 w-4 text-center"></i>{{ $row->name }}</div></td>
      <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $row->code ?? '—' }}</td>
      <td class="px-4 py-3 text-xs text-slate-600">{{ $row->description ?: '—' }}</td>
      <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $row->files_count }}</td>
      <td class="px-4 py-3 text-xs text-slate-600">{{ $row->rules_count }} requirement{{ $row->rules_count === 1 ? '' : 's' }} · {{ $row->presets_count }} preset{{ $row->presets_count === 1 ? '' : 's' }}</td>
     @else
      <td class="px-4 py-3">
       <div class="cfs-scope">
        @if($row->country)<span class="cfs-chip is-country">@if($flag($row->country))<img src="{{ $flag($row->country) }}" alt="" onerror="this.remove()">@endif{{ $row->country->name }}</span>@endif
        @if($row->visaType)<span class="cfs-chip is-visa"><i class="fas fa-passport"></i>{{ $row->visaType->name }}</span>@endif
        @if($row->category)<span class="cfs-chip is-cat"><i class="fas fa-folder"></i>{{ $row->category->name }}</span>@endif
       </div>
      </td>
      <td class="px-4 py-3 text-xs text-slate-500">{{ collect([$row->country ? null : 'any country', $row->visaType ? null : 'any visa type', $row->category ? null : 'any category'])->filter()->implode(' · ') ?: 'exactly this scope' }}</td>
      @if($kind === 'requirements')
       <td class="px-4 py-3"><div class="cfs-docs">@foreach($row->documents_list as $doc)<span class="cfs-doc">{{ $doc }}</span>@endforeach</div>@if($row->notes)<p class="mt-1 text-[11px] text-slate-400">{{ $row->notes }}</p>@endif</td>
      @else
       <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-800">{{ $money($row->processing_cost) }}</td>
       <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-800">{{ $money($row->training_fee) }}</td>
       <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $row->expected_days === null ? '—' : $row->expected_days.' days' }}</td>
      @endif
     @endif
     <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $row->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">{{ ucfirst($row->status) }}</span></td>
     <td class="px-2 py-3">
      <div style="display:grid;grid-template-columns:repeat(2,24px);gap:4px;width:max-content;margin:0 auto;justify-content:center" data-row-actions>
       @if($canEdit)<button type="button" class="cfs-edit flex items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100" title="Edit" style="width:24px;height:24px;padding:0;font-size:11px;border-radius:6px"><i class="fas fa-edit" style="font-size:11px;margin:0"></i></button>@endif
       @if($canDelete)<button type="button" class="cfs-delete flex items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100" title="Delete" style="width:24px;height:24px;padding:0;font-size:11px;border-radius:6px"><i class="fas fa-trash" style="font-size:11px;margin:0"></i></button>@endif
      </div>
     </td>
    </tr>
   @empty
    <tr><td colspan="9" class="p-12 text-center">
     <i class="fas fa-inbox mb-3 block text-4xl text-slate-300"></i>
     <p class="text-sm font-semibold text-slate-500">Nothing here yet</p>
     <p class="mt-1 text-xs text-slate-400">@switch($kind)
      @case('requirements') Add a rule: choose a country, a visa type or a job category (or a mix) and list its documents. @break
      @case('price-presets') Add a preset: choose a scope and the figures a new file should start with. @break
      @default Add your visa types — Work Permit, Student, Family, Visit… @endswitch</p>
    </td></tr>
   @endforelse
   </tbody>
  </table>
 </div>
</div>

{{-- The one modal: Add and Edit. Closes on its cross or Cancel only (never on the backdrop). --}}
<div id="cfsModal" class="fixed inset-0 z-[9000] hidden items-start justify-center overflow-y-auto bg-slate-950/50 p-4" role="dialog" aria-modal="true" aria-labelledby="cfsModalTitle">
 <div class="my-6 w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
  <header class="flex items-center gap-3 bg-gradient-to-r from-indigo-600 to-blue-500 px-5 py-4 text-white">
   <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-white/20 text-lg"><i class="fas {{ ['requirements' => 'fa-list-check', 'price-presets' => 'fa-coins', 'visa-types' => 'fa-passport'][$kind] }}"></i></span>
   <div><h2 class="text-sm font-bold" id="cfsModalTitle">{{ $cfsTitle }}</h2><p class="text-[11px] text-blue-100">{{ $kind === 'visa-types' ? 'Name it once; choose it on files and rules' : 'Leave a part empty to mean "any"' }}</p></div>
   <button type="button" class="cfs-close ml-auto flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-white" aria-label="Close"><i class="fas fa-times text-xs"></i></button>
  </header>
  <form id="cfsForm" class="grid gap-4 p-6 sm:grid-cols-2" novalidate>
   @if($kind === 'visa-types')
    <label class="cfs-field"><span>Visa type name <b>*</b></span><input name="name" maxlength="120" placeholder="e.g. Work Permit"></label>
    <label class="cfs-field"><span>Status</span><select name="status" data-no-s2><option value="active">Active</option><option value="inactive">Inactive</option></select></label>
    <label class="cfs-field sm:col-span-2"><span>Description</span><textarea name="description" rows="3" maxlength="2000" placeholder="What this visa type covers (optional)"></textarea></label>
   @else
    <label class="cfs-field"><span>Country</span><select name="country_id" data-no-s2><option value="">Any country</option>@foreach($countries as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></label>
    <label class="cfs-field"><span>Visa type</span><select name="contract_file_visa_type_id" data-no-s2><option value="">Any visa type</option>@foreach($visaTypes as $v)<option value="{{ $v->id }}">{{ $v->name }}{{ $v->status === 'active' ? '' : ' (inactive)' }}</option>@endforeach</select></label>
    <label class="cfs-field"><span>Job category</span><select name="contract_file_category_id" data-no-s2><option value="">Any job category</option>@foreach($categories as $c)<option value="{{ $c->id }}" data-country="{{ $c->country_id }}">{{ $c->name }}{{ $c->country ? ' · '.$c->country->name : '' }}</option>@endforeach</select></label>
    <label class="cfs-field"><span>Status</span><select name="status" data-no-s2><option value="active">Active</option><option value="inactive">Inactive</option></select></label>
    <p class="cfs-scope-say sm:col-span-2" id="cfsScopeSay"></p>
    @if($kind === 'requirements')
     <div class="cfs-field sm:col-span-2">
      <span>Documents <b>*</b></span>
      <div class="cfs-builder">
       <div class="cfs-chips" id="cfsChips"></div>
       <div class="cfs-add-row"><input id="cfsDocInput" maxlength="160" placeholder="Type a document and press Enter — e.g. Police Clearance (PCC)"><button type="button" id="cfsDocAdd">Add</button></div>
       @if($knownDocs->isNotEmpty())<div class="cfs-known"><small>Used elsewhere — click to add:</small>@foreach($knownDocs as $doc)<button type="button" class="cfs-known-doc" data-doc="{{ $doc }}">+ {{ $doc }}</button>@endforeach</div>@endif
      </div>
      <textarea name="documents" hidden></textarea>
     </div>
     <label class="cfs-field sm:col-span-2"><span>Notes</span><textarea name="notes" rows="2" maxlength="2000" placeholder="Anything staff should know about these documents (optional)"></textarea></label>
    @else
     <label class="cfs-field"><span>Processing cost (BDT)</span><input name="processing_cost" type="number" min="0" step="0.01" placeholder="leave empty = not set here"></label>
     <label class="cfs-field"><span>Training fee (BDT)</span><input name="training_fee" type="number" min="0" step="0.01" placeholder="leave empty = not set here"></label>
     <label class="cfs-field"><span>Expected days</span><input name="expected_days" type="number" min="0" max="3650" step="1" placeholder="submit date + N days"></label>
     <p class="cfs-scope-say sm:col-span-2" style="background:transparent;padding:0;color:#64748b">Each figure is taken from the most specific matching preset that has it. The client price is always typed on the file.</p>
    @endif
   @endif
   <p id="cfsError" class="sm:col-span-2 hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-600"></p>
  </form>
  <footer class="flex justify-end gap-2 bg-slate-50 px-5 py-3">
   <button type="button" class="cfs-close rounded-xl border px-5 py-2 text-xs font-semibold text-slate-600">Cancel</button>
   <button type="button" id="cfsSave" class="rounded-xl bg-blue-600 px-5 py-2 text-xs font-semibold text-white hover:bg-blue-700"><i class="fas fa-save mr-1.5"></i>Save</button>
  </footer>
 </div>
</div>
@endunless

<style>
.cfs-filter-select { flex: 0 0 190px; }
.cfs-filter-select select { width: 100%; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 10px; font-size: 14px; background: #fff; }
.cfs-filter-select .select2-container .select2-selection--single { height: 38px !important; border: 1px solid #cbd5e1 !important; border-radius: 8px !important; }
.cfs-filter-select .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; font-size: 14px; color: #334155; padding-left: 12px; }
.cfs-filter-select .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
.cfs-scope { display: flex; flex-wrap: wrap; gap: 4px; }
.cfs-chip { display: inline-flex; align-items: center; gap: 5px; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.cfs-chip img { width: 15px; height: 10px; border-radius: 2px; object-fit: cover; box-shadow: 0 0 0 1px rgba(15,23,42,.12); }
.cfs-chip i { font-size: 9px; }
.cfs-chip.is-country { background: #ecfdf5; color: #047857; }
.cfs-chip.is-visa { background: #eff6ff; color: #1d4ed8; }
.cfs-chip.is-cat { background: #fffbeb; color: #b45309; }
.cfs-docs { display: flex; flex-wrap: wrap; gap: 4px; max-width: 520px; }
.cfs-doc { display: inline-block; padding: 2px 8px; border-radius: 6px; background: #f1f5f9; color: #334155; font-size: 11px; }
.cfs-try-head { display: flex; align-items: baseline; gap: 8px; padding: 12px 16px; border-bottom: 1px solid #e2e8f0; }
.cfs-try-head i { color: #6366f1; }
.cfs-try-head b { font-size: 13.5px; color: #1e293b; }
.cfs-try-head small { color: #64748b; font-size: 11.5px; }
.cfs-try-body { display: grid; grid-template-columns: 300px 1fr; gap: 16px; padding: 14px 16px; }
.cfs-try-pick { display: grid; gap: 10px; }
.cfs-try-pick label { display: grid; gap: 4px; font-size: 11px; font-weight: 600; color: #475569; }
.cfs-try-pick select { height: 34px; border: 1px solid #dce2eb; border-radius: 8px; padding: 0 8px; font-size: 12.5px; background: #fff; color: #334155; }
.cfs-try-out { min-height: 120px; border-left: 2px dashed #e2e8f0; padding-left: 16px; font-size: 12.5px; color: #334155; }
.cfs-try-out h4 { margin: 0 0 6px; font-size: 10px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #6366f1; }
.cfs-try-out .cfs-src { margin: 0 0 8px; }
.cfs-try-out .cfs-src small { display: block; color: #94a3b8; font-size: 10.5px; margin-bottom: 3px; }
.cfs-try-figs { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-top: 12px; }
.cfs-try-figs div { padding: 8px 10px; border-radius: 10px; background: #f8fafc; border: 1px solid #e2e8f0; }
.cfs-try-figs b { display: block; font-size: 15px; color: #0f172a; }
.cfs-try-figs span { display: block; font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }
.cfs-try-figs small { display: block; font-size: 10px; color: #94a3b8; margin-top: 2px; }
.cfs-muted { color: #94a3b8; }
#cfsModal.is-open { display: flex; }
.cfs-field { display: grid; gap: 6px; }
.cfs-field > span { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #64748b; }
.cfs-field > span b { color: #ef4444; }
.cfs-field input, .cfs-field select, .cfs-field textarea { width: 100%; box-sizing: border-box; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0 14px; font-size: 14px; background: #fff; color: #1e293b; }
.cfs-field input, .cfs-field select { height: 44px; }
.cfs-field textarea { padding: 10px 14px; }
.cfs-field input:focus, .cfs-field select:focus, .cfs-field textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px #dbeafe; }
.cfs-scope-say { margin: 0; padding: 8px 12px; border-radius: 10px; background: #eef2ff; color: #3730a3; font-size: 12px; }
.cfs-builder { border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px; display: grid; gap: 8px; }
.cfs-chips { display: flex; flex-wrap: wrap; gap: 6px; min-height: 28px; }
.cfs-chips:empty::before { content: 'No documents yet'; color: #94a3b8; font-size: 12px; line-height: 28px; }
.cfs-chips .cfs-doc-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 6px 4px 10px; border-radius: 999px; background: #eef2ff; color: #3730a3; font-size: 12px; font-weight: 600; }
.cfs-chips .cfs-doc-chip button { width: 18px; height: 18px; border: 0; border-radius: 50%; background: #c7d2fe; color: #3730a3; font-size: 10px; line-height: 18px; cursor: pointer; }
.cfs-add-row { display: flex; gap: 6px; }
.cfs-add-row input { flex: 1; height: 38px; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0 12px; font-size: 13px; }
.cfs-add-row button { height: 38px; padding: 0 14px; border: 0; border-radius: 10px; background: #4f46e5; color: #fff; font-size: 12px; font-weight: 700; cursor: pointer; }
.cfs-known { display: flex; flex-wrap: wrap; gap: 4px; align-items: center; }
.cfs-known small { color: #94a3b8; font-size: 11px; margin-right: 2px; }
.cfs-known-doc { border: 1px dashed #cbd5e1; background: #fff; color: #475569; border-radius: 999px; padding: 2px 8px; font-size: 11px; cursor: pointer; }
.cfs-known-doc:hover { border-color: #818cf8; color: #4338ca; background: #eef2ff; }
@media (max-width: 900px) { .cfs-try-body { grid-template-columns: 1fr; } .cfs-try-out { border-left: 0; padding-left: 0; } }
</style>
@endsection
@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@if($installed)
<script>
(() => {
 const KIND = @json($kind);
 const URLS = {
  store: @json(route('role.contract-file-setup.store', ['role' => $role, 'kind' => $kind])),
  update: @json(route('role.contract-file-setup.update', ['role' => $role, 'kind' => $kind, 'id' => '__ID__'])),
  match: @json(\Illuminate\Support\Facades\Route::has('role.contract-file-setup.match') ? route('role.contract-file-setup.match', ['role' => $role]) : null),
 };
 const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
 const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
 const taka = v => v === null || v === undefined ? '—' : '৳' + Math.round(Number(v)).toLocaleString('en-US');
 const modal = document.getElementById('cfsModal'), form = document.getElementById('cfsForm'), error = document.getElementById('cfsError');
 let editingId = null, docs = [];

 // ── The document builder: chips, Enter to add, the "used elsewhere" shortcuts ──
 const chips = document.getElementById('cfsChips');
 function paintDocs() {
  if (!chips) return;
  chips.replaceChildren(...docs.map((doc, i) => { const chip = document.createElement('span'); chip.className = 'cfs-doc-chip'; chip.textContent = doc; const x = document.createElement('button'); x.type = 'button'; x.textContent = '✕'; x.title = 'Remove ' + doc; x.addEventListener('click', () => { docs.splice(i, 1); paintDocs(); }); chip.append(x); return chip; }));
  form.elements.documents.value = docs.join('\n');
 }
 function addDoc(name) { name = String(name || '').trim(); if (!name || docs.some(d => d.toLowerCase() === name.toLowerCase())) return; docs.push(name); paintDocs(); }
 if (chips) {
  const input = document.getElementById('cfsDocInput');
  const take = () => { input.value.split(/[\n,]+/).forEach(addDoc); input.value = ''; input.focus(); };
  input.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); take(); } });
  document.getElementById('cfsDocAdd').addEventListener('click', take);
  document.querySelectorAll('.cfs-known-doc').forEach(b => b.addEventListener('click', () => addDoc(b.dataset.doc)));
 }

 // "This rule applies to: …" — the scope read back in words, under the three pickers.
 const say = document.getElementById('cfsScopeSay');
 function paintScope() {
  if (!say) return;
  const pick = n => { const s = form.elements[n]; return s && s.value ? s.selectedOptions[0].textContent.replace(/ \(inactive\)$/, '') : null; };
  const c = pick('country_id'), v = pick('contract_file_visa_type_id'), g = pick('contract_file_category_id');
  say.textContent = !c && !v && !g ? 'Choose at least one: a country, a visa type or a job category.' : 'Applies to every file' + (c ? ' for ' + c : '') + (v ? ' on a ' + v + ' visa' : '') + (g ? ' in ' + g.split(' · ')[0] : '') + '.';
 }
 ['country_id', 'contract_file_visa_type_id', 'contract_file_category_id'].forEach(n => form.elements[n]?.addEventListener('change', paintScope));

 function open(row) {
  editingId = row?.id ?? null; error.classList.add('hidden'); form.reset();
  document.getElementById('cfsModalTitle').textContent = (editingId ? 'Edit ' : 'Add ') + @json(['requirements' => 'requirement', 'price-presets' => 'price preset', 'visa-types' => 'visa type'][$kind]);
  if (row) Object.entries(row).forEach(([k, v]) => { const el = form.elements[k]; if (el && k !== 'documents') el.value = v ?? ''; });
  docs = Array.isArray(row?.documents) ? [...row.documents] : []; paintDocs(); paintScope();
  modal.classList.add('is-open'); modal.classList.remove('hidden');
  form.querySelector('input:not([type=hidden]),select')?.focus();
 }
 function close() { modal.classList.remove('is-open'); modal.classList.add('hidden'); }
 document.querySelectorAll('.cfs-add').forEach(b => b.addEventListener('click', () => open(null)));
 document.querySelectorAll('.cfs-edit').forEach(b => b.addEventListener('click', () => open(JSON.parse(b.closest('tr').dataset.cfsRow))));
 modal.querySelectorAll('.cfs-close').forEach(b => b.addEventListener('click', close));
 document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('is-open')) close(); });

 document.getElementById('cfsSave').addEventListener('click', async () => {
  if (chips) paintDocs();
  const body = new FormData(form);
  if (editingId) body.append('_method', 'PUT');
  const btn = document.getElementById('cfsSave'); btn.disabled = true;
  try {
   const res = await fetch(editingId ? URLS.update.replace('__ID__', editingId) : URLS.store, { method: 'POST', body, headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, credentials: 'same-origin' });
   const data = await res.json().catch(() => ({}));
   if (!res.ok || !data.success) throw new Error(data.message || 'Could not save. Please check the fields.');
   close();
   Swal.fire({ icon: 'success', title: data.message, timer: 1100, showConfirmButton: false }).then(() => location.reload());
  } catch (e) { error.textContent = e.message; error.classList.remove('hidden'); }
  finally { btn.disabled = false; }
 });

 document.querySelectorAll('.cfs-delete').forEach(b => b.addEventListener('click', async () => {
  const row = JSON.parse(b.closest('tr').dataset.cfsRow);
  const ok = await Swal.fire({ icon: 'warning', title: 'Delete this ' + @json(['requirements' => 'requirement', 'price-presets' => 'price preset', 'visa-types' => 'visa type'][$kind]) + '?', text: 'Files already made keep their documents and figures.', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#dc2626' });
  if (!ok.isConfirmed) return;
  const res = await fetch(URLS.update.replace('__ID__', row.id), { method: 'POST', body: new URLSearchParams({ _method: 'DELETE' }), headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, credentials: 'same-origin' });
  const data = await res.json().catch(() => ({}));
  if (data.success) location.reload(); else Swal.fire('Not deleted', data.message || 'Please try again.', 'error');
 }));

 // ── Try a file ──
 const tryOut = document.getElementById('cfsTryOut');
 if (tryOut && URLS.match) {
  const sel = { country: document.getElementById('cfsTryCountry'), visa: document.getElementById('cfsTryVisa'), category: document.getElementById('cfsTryCategory') };
  let pending;
  async function run() {
   if (!sel.country.value && !sel.visa.value && !sel.category.value) { tryOut.innerHTML = '<p class="cfs-muted">Pick a country, visa type or job category.</p>'; return; }
   pending?.abort(); pending = new AbortController();
   const q = new URLSearchParams({ country_id: sel.country.value, visa_type_id: sel.visa.value, category_id: sel.category.value });
   try {
    const res = await fetch(URLS.match + '?' + q, { headers: { Accept: 'application/json' }, signal: pending.signal, credentials: 'same-origin' });
    const r = await res.json();
    const sources = (r.sources || []).filter(s => s.documents.length).map(s => `<div class="cfs-src"><small>from ${esc(s.label)}</small><div class="cfs-docs">${s.documents.map(d => `<span class="cfs-doc">${esc(d)}</span>`).join('')}</div></div>`).join('');
    const fig = (key, label, fmt) => `<div><span>${label}</span><b>${r.figures[key] === null ? '—' : fmt(r.figures[key])}</b><small>${r.figure_sources[key] ? 'from ' + esc(r.figure_sources[key]) : 'nothing set'}</small></div>`;
    tryOut.innerHTML = `<h4>Checklist · ${r.documents.length} document${r.documents.length === 1 ? '' : 's'}</h4>${sources || '<p class="cfs-muted">No document rule matches.</p>'}`
     + `<div class="cfs-try-figs">${fig('processing_cost', 'Processing cost', taka)}${fig('training_fee', 'Training fee', taka)}${fig('expected_days', 'Expected', v => v + ' days')}</div>`;
   } catch (e) { if (e.name !== 'AbortError') tryOut.innerHTML = '<p class="cfs-muted">Could not load the preview.</p>'; }
  }
  // A job category belongs to one country: picking it picks that country.
  sel.category.addEventListener('change', () => { const c = sel.category.selectedOptions[0]?.dataset.country; if (c) sel.country.value = c; run(); });
  sel.country.addEventListener('change', () => { const c = sel.category.selectedOptions[0]?.dataset.country; if (c && c !== sel.country.value) sel.category.value = ''; run(); });
  sel.visa.addEventListener('change', run);
 }
})();
</script>
@endif
@endsection
