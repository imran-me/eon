@php $role=Illuminate\Support\Str::slug(Auth::user()->getRoleNames()->first()); $fmt=fn($n)=>$n>=10000000?number_format($n/10000000,2).'Cr':($n>=100000?number_format($n/100000,1).'L':number_format($n,0)); @endphp
<div class="space-y-4">

 {{-- Page Header --}}
 <header class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
  <span class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 text-xl"><i class="fas fa-briefcase"></i></span>
  <div class="flex-1">
   <h1 class="text-lg font-bold text-slate-900">Manage Sales</h1>
   <p class="text-xs text-slate-500">Client-wise billing and vouchers for work-permit files.</p>
  </div>
  <button type="button" class="create-btn rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700"><i class="fas fa-plus mr-1"></i>New Sale</button>
 </header>

 {{-- Stat Cards --}}
 <section class="grid gap-3" style="grid-template-columns:repeat(4,1fr)">
  <div class="rounded-xl bg-blue-50 p-4">
   <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-blue-400 to-blue-600 text-white text-xl shadow-md"><i class="fas fa-scroll"></i></div>
   <div class="text-3xl font-black leading-none text-blue-900 mb-1">{{ $stats['invoices_mtd'] }}</div>
   <div class="text-[10px] font-bold uppercase tracking-wide text-blue-700">Invoices MTD</div>
   <div class="mt-1 text-xs text-slate-500">This month</div>
  </div>
  <div class="rounded-xl bg-emerald-50 p-4">
   <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 text-white text-xl shadow-md"><i class="fas fa-sack-dollar"></i></div>
   <div class="text-3xl font-black leading-none text-emerald-900 mb-1">BDT {{ $fmt($stats['revenue']) }}</div>
   <div class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Revenue</div>
   <div class="mt-1 text-xs text-slate-500">All invoices</div>
  </div>
  <div class="rounded-xl bg-amber-50 p-4">
   <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-white text-xl shadow-md"><i class="fas fa-hourglass-half"></i></div>
   <div class="text-3xl font-black leading-none text-amber-900 mb-1">BDT {{ $fmt($stats['due_amount']) }}</div>
   <div class="text-[10px] font-bold uppercase tracking-wide text-amber-700">Due / Pending</div>
   <div class="mt-1 text-xs text-slate-500">Outstanding</div>
  </div>
  <div class="rounded-xl bg-rose-50 p-4">
   <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-rose-400 to-rose-600 text-white text-xl shadow-md"><i class="fas fa-chart-line"></i></div>
   <div class="text-3xl font-black leading-none text-rose-900 mb-1">BDT {{ $fmt($stats['net_profit']) }}</div>
   <div class="text-[10px] font-bold uppercase tracking-wide text-rose-700">Net Profit</div>
   <div class="mt-1 text-xs text-slate-500">Net earnings</div>
  </div>
 </section>

 {{-- Table Card --}}
 <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
  <form method="GET" action="{{ route('role.contract-file-sales.index',['role'=>$role]) }}" class="flex flex-wrap items-center gap-3 border-b bg-slate-50 px-4 py-3">
   <div class="flex min-w-[200px] flex-1 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2">
    <i class="fas fa-search text-xs text-slate-400"></i>
    <input name="search" value="{{ request('search') }}" class="flex-1 border-0 bg-transparent text-sm outline-none" placeholder="Search invoice or client...">
   </div>
   <select name="payment_status" class="select2-filter h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm">
    <option value="">All Payment Status</option>
    <option value="paid" @selected(request('payment_status')==='paid')>Paid</option>
    <option value="partial" @selected(request('payment_status')==='partial')>Partial</option>
    <option value="due" @selected(request('payment_status')==='due')>Due</option>
   </select>
   <button class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white">Filter</button>
   <a href="{{ route('role.contract-file-sales.index',['role'=>$role]) }}" class="rounded-lg border px-4 py-2 text-xs font-semibold text-slate-600">Reset</a>
  </form>
  <div class="overflow-x-auto">
   <table class="min-w-full divide-y divide-slate-200">
    <thead class="bg-slate-50">
     <tr>
      @foreach(['Invoice #','Client','Files','Country','Date','Total','Paid','Due','Status'] as $h)
      <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ $h }}</th>
      @endforeach
      <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wide text-slate-500">Action</th>
     </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
     @forelse($datas as $sale)
     @php $payColors=['paid'=>'bg-emerald-100 text-emerald-700','partial'=>'bg-amber-100 text-amber-700','due'=>'bg-red-100 text-red-700']; @endphp
     <tr class="hover:bg-slate-50">
      <td class="px-4 py-3 font-mono text-xs text-blue-700">{{ $sale->invoice_number }}</td>
      <td class="px-4 py-3">
       <b class="block text-xs font-semibold text-slate-800">{{ $sale->client?->name ?? '—' }}</b>
       <span class="text-[10px] text-slate-400">{{ $sale->client?->phone ?: '-' }}</span>
      </td>
      <td class="px-4 py-3"><span class="rounded-full bg-violet-50 px-2 py-1 text-[10px] font-semibold text-violet-700">{{ $sale->files_count }} files</span></td>
      <td class="px-4 py-3 text-xs text-slate-600">{{ $sale->country?->name ?? '-' }}</td>
      <td class="px-4 py-3 text-xs text-slate-600">{{ $sale->sale_date?->format('d M Y') }}</td>
      <td class="px-4 py-3 text-xs font-bold text-slate-800">{{ number_format($sale->total_amount, 0) }}</td>
      <td class="px-4 py-3 text-xs text-slate-600">{{ number_format($sale->paid_amount, 0) }}</td>
      <td class="px-4 py-3 text-xs text-slate-600">{{ number_format($sale->due_amount, 0) }}</td>
      <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-[10px] font-semibold {{ $payColors[$sale->payment_status] ?? 'bg-slate-100 text-slate-600' }}">{{ ucfirst($sale->payment_status) }}</span></td>
      <td class="px-4 py-3">
       <div class="flex justify-end gap-1.5">
        <button class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600" onclick="viewSale({{ $sale->id }})" title="View"><i class="fas fa-eye text-xs"></i></button>
        <button class="edit-btn flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100" onclick="editSale({{ $sale->id }})" title="Edit"><i class="fas fa-pen text-xs"></i></button>
        <a class="flex h-8 w-8 items-center justify-center rounded-lg border border-violet-200 bg-violet-50 text-violet-600 hover:bg-violet-100" href="{{ route('role.contract-file-sales.voucher',['role'=>$role,'contractFileSale'=>$sale->id]) }}" target="_blank" title="Invoice"><i class="fas fa-file-invoice text-xs"></i></a>
        <button class="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100" onclick="confirmDelete({{ $sale->id }},'{{ addslashes($sale->invoice_number) }}')" title="Delete"><i class="fas fa-trash text-xs"></i></button>
       </div>
      </td>
     </tr>
     @empty
     <tr>
      <td colspan="10" class="p-12 text-center">
       <i class="fas fa-inbox mb-3 block text-4xl text-slate-300"></i>
       <p class="text-sm font-semibold text-slate-500">No sales invoices found</p>
       <p class="mt-1 text-xs text-slate-400">Create a new sale to get started.</p>
      </td>
     </tr>
     @endforelse
    </tbody>
   </table>
  </div>
  <div class="p-4">{{ $datas->links() }}</div>
 </div>

</div>
