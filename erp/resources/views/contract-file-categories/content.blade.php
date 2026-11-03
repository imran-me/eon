@php
$role = Illuminate\Support\Str::slug(Auth::user()->getRoleNames()->first());
$fmt = fn($n) => $n >= 100000 ? number_format($n / 100000, 1).'L' : number_format($n, 0);
@endphp
{{-- Laid out exactly as Flight Category (flight-categories/index.blade.php):
     same header, stat cards, filter row and table classes (owner, 2026-10-25). --}}
<header class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm mb-4">
 <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-blue-50 text-blue-600 flex-shrink-0"><i class="fas fa-folder"></i></span>
 <div class="flex-1">
  <h1 class="text-lg font-bold text-slate-900">Job Categories</h1>
  <p class="text-xs text-slate-500">Work-permit job categories — their visa rate &amp; documents; more rules under Requirements and Price Presets</p>
 </div>
 <button type="button" class="create-btn rounded-lg bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700"><i class="fas fa-plus mr-1"></i> Add Category</button>
</header>

<section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
 <div class="rounded-xl bg-blue-50 p-4">
  <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white mb-3 shadow-md"><i class="fas fa-folder"></i></div>
  <b class="block text-2xl font-bold text-blue-900">{{ $stats['total_categories'] }}</b>
  <p class="text-[10px] font-bold uppercase text-blue-700 mt-1">Job Categories</p>
  <p class="text-xs text-slate-500 mt-1">Active</p>
 </div>
 <div class="rounded-xl bg-emerald-50 p-4">
  <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center text-white mb-3 shadow-md"><i class="fas fa-wrench"></i></div>
  <b class="block text-2xl font-bold text-emerald-900">{{ optional($stats['top_category'])->name ?? '—' }}</b>
  <p class="text-[10px] font-bold uppercase text-emerald-700 mt-1">Top Category</p>
  <p class="text-xs text-slate-500 mt-1">{{ optional($stats['top_category'])->documents_count ?? 0 }} required docs</p>
 </div>
 <div class="rounded-xl bg-amber-50 p-4">
  <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center text-white mb-3 shadow-md"><i class="fas fa-sack-dollar"></i></div>
  <b class="block text-2xl font-bold text-amber-900">BDT {{ $fmt($stats['avg_visa_rate']) }}</b>
  <p class="text-[10px] font-bold uppercase text-amber-700 mt-1">Avg Visa Rate</p>
  <p class="text-xs text-slate-500 mt-1">Per file</p>
 </div>
 <div class="rounded-xl bg-violet-50 p-4">
  <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-violet-400 to-violet-600 flex items-center justify-center text-white mb-3 shadow-md"><i class="fas fa-earth-asia"></i></div>
  <b class="block text-2xl font-bold text-violet-900">{{ $stats['countries_served'] }}</b>
  <p class="text-[10px] font-bold uppercase text-violet-700 mt-1">Countries Served</p>
  <p class="text-xs text-slate-500 mt-1">{{ $stats['country_names']->implode(', ') ?: '—' }}</p>
 </div>
</section>

 {{-- Table Card --}}
 <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
  <form method="GET" action="{{ route('role.contract-file-categories.index',['role'=>$role]) }}" class="flex items-center gap-3 bg-slate-50 px-4 py-3">
   <div class="flex flex-1 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2">
    <i class="fas fa-search text-xs text-slate-400"></i>
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search category, country..." class="flex-1 border-0 outline-none text-sm bg-transparent">
   </div>
   <div class="cfc-country"><select name="country_id" aria-label="Country">
    <option value="">All Countries</option>
    @foreach($countries as $country)
     <option value="{{ $country->id }}" @selected(request('country_id')==$country->id)>{{ $country->name }}</option>
    @endforeach
   </select></div>
   <button class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white">Filter</button>
   <a href="{{ route('role.contract-file-categories.index',['role'=>$role]) }}" class="rounded-lg border px-4 py-2 text-xs font-semibold text-slate-600">Reset</a>
  </form>
  <div class="overflow-x-auto">
   <table class="min-w-full divide-y divide-slate-200">
    <thead class="bg-slate-50">
     <tr>
      <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Category (Job)</th>
      <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Code</th>
      <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Country</th>
      <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Visa Rate (BDT)</th>
      <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Required Docs</th>
      <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Status</th>
      <th class="px-2 py-3 text-right text-[10px] font-bold uppercase tracking-wide text-slate-500" style="width:1%;white-space:nowrap">Action</th>
     </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
     @forelse($datas as $value)
     <tr class="cf-row">
      <td class="px-4 py-3">
       <div class="flex items-center gap-2 text-sm font-semibold text-slate-900"><i class="fas fa-folder text-blue-500 w-4 text-center"></i>{{ $value->name }}</div>
      </td>
      <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $value->code ?? '—' }}</td>
      <td class="px-4 py-3 text-xs text-slate-600">{{ optional($value->country)->name ?? '—' }}</td>
      <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-800">{{ number_format($value->visa_rate, 0) }}</td>
      <td class="px-4 py-3"><span class="rounded-full bg-violet-50 px-2.5 py-1 text-[10px] font-semibold text-violet-700">{{ $value->documents_count }} docs</span></td>
      <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $value->status==='active' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">{{ ucfirst($value->status) }}</span></td>
      <td class="px-2 py-3">
       <div style="display:grid;grid-template-columns:repeat(2,24px);gap:4px;width:max-content;margin:0 auto;justify-content:center" data-row-actions>
        <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600" onclick="viewCategory({{ $value->id }})" title="View" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px"><i class="fas fa-eye text-xs" style="font-size:11px;margin:0"></i></button>
        <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100 edit-btn"
         data-item_id="{{ $value->id }}"
         data-action="{{ route('role.contract-file-categories.update',['role'=>$role,'contract_file_category'=>$value->id]) }}"
         data-name="{{ $value->name }}"
         data-country_id="{{ $value->country_id }}"
         data-visa_rate="{{ $value->visa_rate }}"
         data-required_documents="{{ $value->required_documents }}"
         data-status="{{ $value->status }}"
         title="Edit" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px"><i class="fas fa-edit text-xs" style="font-size:11px;margin:0"></i></button>
        <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100" onclick="confirmDelete('{{ $value->id }}','{{ addslashes($value->name) }}')" title="Delete" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px"><i class="fas fa-trash text-xs" style="font-size:11px;margin:0"></i></button>
       </div>
      </td>
     </tr>
     @empty
     <tr>
      <td colspan="7" class="p-12 text-center">
       <i class="fas fa-inbox mb-3 block text-4xl text-slate-300"></i>
       <p class="text-sm font-semibold text-slate-500">No file categories found</p>
       <p class="mt-1 text-xs text-slate-400">Add a new contract file category to start.</p>
      </td>
     </tr>
     @endforelse
    </tbody>
   </table>
  </div>
  <div class="p-4">{{ $datas->appends(request()->all())->links() }}</div>
 </div>
<style>
/* The country picker (the Travels select2 from layout → ticketing-dropdowns)
   beside the search box at its height — Flight Category has no second field. */
.cfc-country { flex: 0 0 200px; }
.cfc-country .select2-container .select2-selection--single { height: 38px !important; border: 1px solid #cbd5e1 !important; border-radius: 8px !important; }
.cfc-country .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; font-size: 14px; color: #334155; padding-left: 12px; }
.cfc-country .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
.cfc-country select { width: 100%; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 10px; font-size: 14px; background: #fff; }
</style>
