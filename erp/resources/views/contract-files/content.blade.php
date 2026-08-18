@php $role = Illuminate\Support\Str::slug(Auth::user()->getRoleNames()->first()); @endphp
<div class="space-y-4">

 {{-- Page Header --}}
 <header class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
  <span class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 text-xl"><i class="fas fa-clipboard-list"></i></span>
  <div class="flex-1">
   <h1 class="text-lg font-bold text-slate-900">Applications Board</h1>
   <p class="text-xs text-slate-500">All work-permit files with submit and expected release dates.</p>
  </div>
  <button type="button" class="create-btn rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700"><i class="fas fa-plus mr-1"></i>New File</button>
 </header>

 {{-- Stat Cards --}}
 <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
  <div class="rounded-xl bg-blue-50 p-4">
   <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-blue-400 to-blue-600 text-white text-xl shadow-md"><i class="fas fa-clipboard-list"></i></div>
   <div class="text-3xl font-black leading-none text-blue-900 mb-1">{{ $stats['total_files'] }}</div>
   <div class="text-[10px] font-bold uppercase tracking-wide text-blue-700">Total Files</div>
   <div class="mt-1 text-xs text-slate-500">All files</div>
  </div>
  <div class="rounded-xl bg-amber-50 p-4">
   <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-white text-xl shadow-md"><i class="fas fa-paper-plane"></i></div>
   <div class="text-3xl font-black leading-none text-amber-900 mb-1">{{ $stats['submitted_to_vendor'] }}</div>
   <div class="text-[10px] font-bold uppercase tracking-wide text-amber-700">Submitted</div>
   <div class="mt-1 text-xs text-slate-500">To vendor</div>
  </div>
  <div class="rounded-xl bg-emerald-50 p-4">
   <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 text-white text-xl shadow-md"><i class="fas fa-circle-check"></i></div>
   <div class="text-3xl font-black leading-none text-emerald-900 mb-1">{{ $stats['approved_mtd'] }}</div>
   <div class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Approved MTD</div>
   <div class="mt-1 text-xs text-slate-500">This month</div>
  </div>
  <div class="rounded-xl bg-violet-50 p-4">
   <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-violet-400 to-violet-700 text-white text-xl shadow-md"><i class="fas fa-box-open"></i></div>
   <div class="text-3xl font-black leading-none text-violet-900 mb-1">{{ $stats['delivered'] }}</div>
   <div class="text-[10px] font-bold uppercase tracking-wide text-violet-700">Delivered</div>
   <div class="mt-1 text-xs text-slate-500">Completed</div>
  </div>
 </section>

 {{-- Summary Tables --}}
 <section class="grid gap-4 lg:grid-cols-2">
  <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
   <h2 class="flex items-center gap-2 border-b px-4 py-3 text-sm font-bold text-slate-800"><i class="fas fa-users text-blue-500"></i>Files by Client</h2>
   <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-100">
     <thead class="bg-slate-50"><tr>@foreach(['Client','Total','In Process','Approved'] as $h)<th class="px-4 py-2.5 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ $h }}</th>@endforeach</tr></thead>
     <tbody class="divide-y divide-slate-100">
      @forelse($clientSummary as $row)
      <tr class="hover:bg-slate-50">
       <td class="px-4 py-2.5 text-xs font-semibold text-slate-800">{{ $row['name'] }}</td>
       <td class="px-4 py-2.5 text-xs font-bold text-blue-700">{{ $row['total'] }}</td>
       <td class="px-4 py-2.5 text-xs text-amber-700">{{ $row['in_process'] }}</td>
       <td class="px-4 py-2.5 text-xs text-emerald-700">{{ $row['approved'] }}</td>
      </tr>
      @empty
      <tr><td colspan="4" class="p-6 text-center text-xs text-slate-400"><i class="fas fa-inbox mb-2 block text-2xl text-slate-300"></i>No client files yet.</td></tr>
      @endforelse
     </tbody>
    </table>
   </div>
  </div>
  <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
   <h2 class="flex items-center gap-2 border-b px-4 py-3 text-sm font-bold text-slate-800"><i class="fas fa-building text-violet-500"></i>Files by Vendor</h2>
   <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-100">
     <thead class="bg-slate-50"><tr>@foreach(['Vendor','Submitted','Pending','Approved'] as $h)<th class="px-4 py-2.5 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ $h }}</th>@endforeach</tr></thead>
     <tbody class="divide-y divide-slate-100">
      @forelse($vendorSummary as $row)
      <tr class="hover:bg-slate-50">
       <td class="px-4 py-2.5 text-xs font-semibold text-slate-800">{{ $row['name'] }}</td>
       <td class="px-4 py-2.5 text-xs font-bold text-amber-700">{{ $row['submitted'] }}</td>
       <td class="px-4 py-2.5 text-xs text-slate-500">{{ $row['pending'] }}</td>
       <td class="px-4 py-2.5 text-xs text-emerald-700">{{ $row['approved'] }}</td>
      </tr>
      @empty
      <tr><td colspan="4" class="p-6 text-center text-xs text-slate-400"><i class="fas fa-inbox mb-2 block text-2xl text-slate-300"></i>No vendor files yet.</td></tr>
      @endforelse
     </tbody>
    </table>
   </div>
  </div>
 </section>

 {{-- Main Table --}}
 <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
  <form method="GET" action="{{ route('role.contract-files.index',['role'=>$role]) }}" class="flex flex-wrap items-center gap-3 border-b bg-slate-50 px-4 py-3">
   <div class="flex min-w-[200px] flex-1 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2">
    <i class="fas fa-search text-xs text-slate-400"></i>
    <input name="search" value="{{ request('search') }}" class="flex-1 border-0 bg-transparent text-sm outline-none" placeholder="Search applicant, passport, file #...">
   </div>
   <div class="w-40"><select name="country_id" class="cf-select2"><option value="">All Countries</option>@foreach($countries as $country)<option value="{{ $country->id }}" @selected(request('country_id')==$country->id)>{{ $country->name }}</option>@endforeach</select></div>
   <div class="w-44"><select name="contract_file_category_id" class="cf-select2"><option value="">All Categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('contract_file_category_id')==$category->id)>{{ $category->name }}</option>@endforeach</select></div>
   <div class="w-40"><select name="vendor_id" class="cf-select2"><option value="">All Vendors</option>@foreach($vendors as $vendor)<option value="{{ $vendor->id }}" @selected(request('vendor_id')==$vendor->id)>{{ $vendor->name }}</option>@endforeach</select></div>
   <div class="w-36"><select name="status" class="cf-select2"><option value="">All Status</option>@foreach($statuses as $key=>$label)<option value="{{ $key }}" @selected(request('status')==$key)>{{ $label }}</option>@endforeach</select></div>
   <button class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white">Filter</button>
   <a href="{{ route('role.contract-files.index',['role'=>$role]) }}" class="rounded-lg border px-4 py-2 text-xs font-semibold text-slate-600">Reset</a>
  </form>
  <div class="overflow-x-auto">
   <table class="min-w-full divide-y divide-slate-200">
    <thead class="bg-slate-50">
     <tr>
      @foreach(['File #','Applicant','Category','Country','Vendor','Submit Date','Expected Out','Status'] as $h)
      <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ $h }}</th>
      @endforeach
      <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wide text-slate-500">Action</th>
     </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
     @forelse($datas as $file)
     @php $statusColors=['doc_collection'=>'bg-red-100 text-red-700','submitted'=>'bg-amber-100 text-amber-700','under_process'=>'bg-blue-100 text-blue-700','approved'=>'bg-emerald-100 text-emerald-700','delivered'=>'bg-violet-100 text-violet-700']; @endphp
     <tr class="hover:bg-slate-50">
      <td class="px-4 py-3 font-mono text-xs text-blue-700">{{ $file->file_number }}</td>
      <td class="px-4 py-3">
       <b class="block text-xs font-semibold text-slate-800">{{ $file->applicant_name }}</b>
       <span class="text-[10px] text-slate-400">{{ $file->passport_no }}</span>
      </td>
      <td class="px-4 py-3 text-xs text-slate-600">{{ optional($file->category)->name ?? '-' }}</td>
      <td class="px-4 py-3 text-xs text-slate-600">{{ optional($file->country)->name ?? '-' }}</td>
      <td class="px-4 py-3 text-xs text-slate-600">{{ optional($file->vendor)->name ?? '-' }}</td>
      <td class="px-4 py-3 text-xs text-slate-600">{{ optional($file->submit_date)->format('d M Y') ?? '-' }}</td>
      <td class="px-4 py-3 text-xs text-slate-600">{{ optional($file->expected_out_date)->format('d M Y') ?? '-' }}</td>
      <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-[10px] font-semibold {{ $statusColors[$file->status] ?? 'bg-slate-100 text-slate-600' }}">{{ $file->status_label }}</span></td>
      <td class="px-4 py-3">
       <div class="flex justify-end gap-1.5">
        <button class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600" onclick="viewFile({{ $file->id }})" title="View"><i class="fas fa-eye text-xs"></i></button>
        <button class="flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100" onclick="editFile({{ $file->id }})" title="Edit"><i class="fas fa-pen text-xs"></i></button>
        @if($file->due_amount > 0)
        <button class="flex h-8 w-8 items-center justify-center rounded-lg border border-amber-200 bg-amber-50 text-amber-600 hover:bg-amber-100" onclick="openPayVendorModal({{ $file->id }}, '{{ addslashes($file->file_number) }}', '{{ addslashes($file->vendor?->name ?? 'Vendor (unassigned)') }}', {{ $file->due_amount }})" title="Pay Vendor"><i class="fas fa-money-bill-wave text-xs"></i></button>
        @endif
        <button class="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100" onclick="confirmDelete({{ $file->id }},'{{ addslashes($file->file_number) }}')" title="Delete"><i class="fas fa-trash text-xs"></i></button>
       </div>
      </td>
     </tr>
     @empty
     <tr>
      <td colspan="9" class="p-12 text-center">
       <i class="fas fa-inbox mb-3 block text-4xl text-slate-300"></i>
       <p class="text-sm font-semibold text-slate-500">No contract files found</p>
       <p class="mt-1 text-xs text-slate-400">Create a new file to get started.</p>
      </td>
     </tr>
     @endforelse
    </tbody>
   </table>
  </div>
  <div class="p-4">{{ $datas->links() }}</div>
 </div>

</div>
