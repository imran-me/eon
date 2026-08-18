@php
$role = Illuminate\Support\Str::slug(Auth::user()->getRoleNames()->first());
$fmt = fn($n) => $n >= 100000 ? number_format($n / 100000, 1).'L' : number_format($n, 0);
@endphp
<div class="space-y-4">

 {{-- Page Header --}}
 <header class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
  <span class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 text-xl"><i class="fas fa-folder"></i></span>
  <div class="flex-1">
   <h1 class="text-lg font-bold text-slate-900">File Category</h1>
   <p class="text-xs text-slate-500">Work-permit job categories - set visa rate &amp; required documents per category</p>
  </div>
  <button type="button" class="create-btn rounded-lg bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700 border border-emerald-100"><i class="fas fa-plus mr-1"></i> Add Category</button>
 </header>

 {{-- Stat Cards --}}
 <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
  <div class="rounded-xl bg-blue-50 p-4">
   <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-blue-400 to-blue-600 text-white text-xl shadow-md"><i class="fas fa-folder"></i></div>
   <div class="text-3xl font-black leading-none text-blue-900 mb-1">{{ $stats['total_categories'] }}</div>
   <div class="text-[10px] font-bold uppercase tracking-wide text-blue-700">Job Categories</div>
   <div class="mt-1 text-xs text-slate-500">Active</div>
  </div>
  <div class="rounded-xl bg-emerald-50 p-4">
   <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 text-white text-xl shadow-md"><i class="fas fa-wrench"></i></div>
   <div class="text-xl font-black leading-none text-emerald-900 mb-1 truncate">{{ optional($stats['top_category'])->name ?? '-' }}</div>
   <div class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Top Category</div>
   <div class="mt-1 text-xs text-slate-500">{{ optional($stats['top_category'])->documents_count ?? 0 }} required docs</div>
  </div>
  <div class="rounded-xl bg-amber-50 p-4">
   <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-white text-xl shadow-md"><i class="fas fa-sack-dollar"></i></div>
   <div class="text-3xl font-black leading-none text-amber-900 mb-1">BDT {{ $fmt($stats['avg_visa_rate']) }}</div>
   <div class="text-[10px] font-bold uppercase tracking-wide text-amber-700">Avg Visa Rate</div>
   <div class="mt-1 text-xs text-slate-500">Per file</div>
  </div>
  <div class="rounded-xl bg-violet-50 p-4">
   <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-violet-400 to-violet-700 text-white text-xl shadow-md"><i class="fas fa-earth-asia"></i></div>
   <div class="text-3xl font-black leading-none text-violet-900 mb-1">{{ $stats['countries_served'] }}</div>
   <div class="text-[10px] font-bold uppercase tracking-wide text-violet-700">Countries Served</div>
   <div class="mt-1 text-xs text-slate-500">{{ $stats['country_names']->implode(', ') ?: '-' }}</div>
  </div>
 </section>

 {{-- Table Card --}}
 <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
  <form method="GET" action="{{ route('role.contract-file-categories.index',['role'=>$role]) }}" class="flex flex-wrap items-center gap-3 bg-slate-50 px-4 py-3">
   <div class="flex min-w-[200px] flex-1 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2">
    <i class="fas fa-search text-xs text-slate-400"></i>
    <input name="search" value="{{ request('search') }}" class="flex-1 border-0 bg-transparent text-sm outline-none" placeholder="Search category, country...">
   </div>
   <select name="country_id" class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm">
    <option value="">All Countries</option>
    @foreach($countries as $country)
     <option value="{{ $country->id }}" @selected(request('country_id')==$country->id)>{{ $country->name }}</option>
    @endforeach
   </select>
   <button class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white">Filter</button>
   <a href="{{ route('role.contract-file-categories.index',['role'=>$role]) }}" class="rounded-lg border px-4 py-2 text-xs font-semibold text-slate-600">Reset</a>
  </form>
  <div class="overflow-x-auto">
   <table class="min-w-full divide-y divide-slate-200">
    <thead class="bg-slate-50">
     <tr>
      <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">Category (Job)</th>
      <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">Code</th>
      <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">Country</th>
      <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">Visa Rate (BDT)</th>
      <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">Required Docs</th>
      <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">Status</th>
      <th class="px-5 py-3 text-right text-[11px] font-bold uppercase tracking-wide text-slate-500">Action</th>
     </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
     @forelse($datas as $value)
     <tr class="hover:bg-slate-50">
      <td class="px-5 py-3">
       <div class="flex items-center gap-2 text-sm font-semibold text-slate-800"><i class="fas fa-folder text-amber-500"></i> {{ $value->name }}</div>
      </td>
      <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $value->code }}</td>
      <td class="px-5 py-3 text-sm text-slate-600">{{ optional($value->country)->name ?? '-' }}</td>
      <td class="px-5 py-3 text-sm font-bold text-slate-800">{{ number_format($value->visa_rate, 0) }}</td>
      <td class="px-5 py-3"><span class="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700">{{ $value->documents_count }} docs</span></td>
      <td class="px-5 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $value->status==='active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ ucfirst($value->status) }}</span></td>
      <td class="px-5 py-3">
       <div class="flex justify-end gap-1.5">
        <button class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600" onclick="viewCategory({{ $value->id }})" title="View"><i class="fas fa-eye text-xs"></i></button>
        <button class="edit-btn flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100"
         data-item_id="{{ $value->id }}"
         data-action="{{ route('role.contract-file-categories.update',['role'=>$role,'contract_file_category'=>$value->id]) }}"
         data-name="{{ $value->name }}"
         data-country_id="{{ $value->country_id }}"
         data-visa_rate="{{ $value->visa_rate }}"
         data-required_documents="{{ $value->required_documents }}"
         data-status="{{ $value->status }}"
         title="Edit"><i class="fas fa-pen text-xs"></i></button>
        <button class="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100" onclick="confirmDelete('{{ $value->id }}','{{ addslashes($value->name) }}')" title="Delete"><i class="fas fa-trash text-xs"></i></button>
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
  <div class="p-4">{{ $datas->links() }}</div>
 </div>

</div>
