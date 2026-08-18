@php($role = Str::slug(Auth::user()->getRoleNames()->first()))
<div class="space-y-4">
 <header class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
  <div class="flex items-center gap-3"><span class="flex h-11 w-11 items-center justify-center rounded-lg bg-blue-50 text-blue-600"><i class="fas fa-plane-departure"></i></span><div><h1 class="text-lg font-bold text-slate-900">Airport Management</h1><p class="text-xs text-slate-500">Manage airport names, IATA codes and geographic coverage.</p></div></div>
  @can('create geography')<button type="button" class="create-state-btn rounded-lg bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"><i class="fas fa-plus mr-1"></i>Add Airport</button>@endcan
 </header>
 <section class="grid gap-3 sm:grid-cols-3">
  <div class="rounded-xl bg-blue-50 p-4"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-600 text-white"><i class="fas fa-plane"></i></span><b class="mt-3 block text-2xl text-blue-700">{{ $totalAirports }}</b><p class="text-xs font-semibold uppercase text-blue-700">Total Airports</p></div>
  <div class="rounded-xl bg-emerald-50 p-4"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600 text-white"><i class="fas fa-earth-asia"></i></span><b class="mt-3 block text-2xl text-emerald-700">{{ $countriesCount }}</b><p class="text-xs font-semibold uppercase text-emerald-700">Countries Covered</p></div>
  <div class="rounded-xl bg-violet-50 p-4"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-600 text-white"><i class="fas fa-map-location-dot"></i></span><b class="mt-3 block text-2xl text-violet-700">{{ $statesCount }}</b><p class="text-xs font-semibold uppercase text-violet-700">States Covered</p></div>
 </section>
 <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
  <form method="GET" class="grid gap-3 border-b border-slate-200 bg-slate-50 p-4 lg:grid-cols-[1fr_1fr_1fr_auto]">
   <label><span class="mb-1 block text-[11px] font-bold uppercase text-slate-500">Country</span><select id="country_id" name="country_id" class="select2 w-full" onchange="getStates(this,'#state_id')" data-action="{{ route('role.get-states-by-country',['role'=>$role]) }}"><option value="">All Countries</option>@foreach($countries as $country)<option value="{{ $country->id }}" @selected($country->id==request('country_id'))>{{ $country->name }}</option>@endforeach</select></label>
   <label><span class="mb-1 block text-[11px] font-bold uppercase text-slate-500">State</span><select id="state_id" name="state_id" class="select2 w-full"><option value="">All States</option>@foreach($request_states ?? [] as $state)<option value="{{ $state->id }}" @selected($state->id==request('state_id'))>{{ $state->name }}</option>@endforeach</select></label>
   <label><span class="mb-1 block text-[11px] font-bold uppercase text-slate-500">Airport</span><input name="name_code" value="{{ request('name_code') }}" class="h-[42px] w-full rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" placeholder="Search name or IATA code..."></label>
   <div class="flex items-end gap-2"><button class="h-[42px] rounded-lg bg-slate-900 px-4 text-xs font-semibold text-white"><i class="fas fa-filter mr-1"></i>Filter</button><a href="{{ route('role.airport.index',['role'=>$role]) }}" class="flex h-[42px] items-center rounded-lg border border-slate-300 px-4 text-xs font-semibold text-slate-600">Reset</a></div>
  </form>
  <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200">
   <thead class="bg-slate-50"><tr>@foreach(['SL','Airport','IATA Code','Country','State'] as $heading)<th class="px-5 py-3 text-left text-[11px] font-bold uppercase text-slate-500">{{ $heading }}</th>@endforeach @canany(['edit geography','delete geography'])<th class="px-5 py-3 text-right text-[11px] font-bold uppercase text-slate-500">Action</th>@endcanany</tr></thead>
   <tbody class="divide-y divide-slate-100">
   @forelse($datas as $key => $airport)
    <tr class="hover:bg-slate-50">
     <td class="px-5 py-3 text-xs text-slate-500">{{ ($datas->currentPage()-1)*$datas->perPage()+$key+1 }}</td>
     <td class="px-5 py-3 text-sm font-semibold text-slate-800"><i class="fas fa-plane-departure mr-2 text-blue-500"></i>{{ $airport->name }}</td>
     <td class="px-5 py-3"><span class="rounded-md bg-slate-100 px-2 py-1 font-mono text-xs font-bold text-slate-700">{{ strtoupper($airport->code) }}</span></td>
     <td class="px-5 py-3 text-sm text-slate-600">{{ $airport->country?->name ?? '-' }}</td>
     <td class="px-5 py-3 text-sm text-slate-600">{{ $airport->state?->name ?? '-' }}</td>
     @canany(['edit geography','delete geography'])<td class="px-5 py-3"><div class="flex justify-end gap-2">
      @can('edit geography')<button type="button" class="edit-state-btn flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-blue-600 hover:bg-blue-50" data-country_id="{{ $airport->country_id }}" data-state_id="{{ $airport->state_id }}" data-item_name="{{ $airport->name }}" data-item_code="{{ $airport->code }}" data-item_id="{{ $airport->id }}" data-get_states_action="{{ route('role.get-states-by-country',['role'=>$role]) }}" data-action="{{ route('role.airport.update',['role'=>$role,'airport'=>$airport->id]) }}" title="Edit Airport"><i class="fas fa-pen text-xs"></i></button>@endcan
      @can('delete geography')<button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-red-600 hover:bg-red-50" onclick="confirmDelete('{{ $airport->id }}','{{ addslashes($airport->name) }}')" title="Delete Airport"><i class="fas fa-trash text-xs"></i></button>@endcan
     </div></td>@endcanany
    </tr>
   @empty<tr><td colspan="6" class="px-5 py-12 text-center"><i class="fas fa-plane mb-3 block text-3xl text-slate-300"></i><p class="text-sm text-slate-500">No airports found.</p></td></tr>@endforelse
   </tbody>
  </table></div>
  <div class="border-t border-slate-100 px-5 py-4">{{ $datas->appends(request()->all())->links() }}</div>
 </section>
</div>
