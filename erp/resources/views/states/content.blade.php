@php($role = Str::slug(Auth::user()->getRoleNames()->first()))
<div class="space-y-4">
    <header class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-blue-50 text-blue-600"><i class="fas fa-map-location-dot"></i></span>
            <div><h1 class="text-lg font-bold text-slate-900">States</h1><p class="text-xs text-slate-500">Manage country-wise states used across ticketing and business services.</p></div>
        </div>
        @can('create geography')
            <button type="button" class="create-state-btn inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"><i class="fas fa-plus"></i>Add State</button>
        @endcan
    </header>
    <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl bg-blue-50 p-4"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-600 text-white"><i class="fas fa-map-pin"></i></span><b class="mt-3 block text-2xl text-blue-700">{{ $totalStates }}</b><p class="text-xs font-semibold uppercase text-blue-700">Total States</p></div>
        <div class="rounded-xl bg-emerald-50 p-4"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600 text-white"><i class="fas fa-circle-check"></i></span><b class="mt-3 block text-2xl text-emerald-700">{{ $activeStates }}</b><p class="text-xs font-semibold uppercase text-emerald-700">Active States</p></div>
        <div class="rounded-xl bg-amber-50 p-4"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500 text-white"><i class="fas fa-circle-pause"></i></span><b class="mt-3 block text-2xl text-amber-700">{{ $inactiveStates }}</b><p class="text-xs font-semibold uppercase text-amber-700">Inactive States</p></div>
        <div class="rounded-xl bg-violet-50 p-4"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-600 text-white"><i class="fas fa-earth-asia"></i></span><b class="mt-3 block text-2xl text-violet-700">{{ $countriesCount }}</b><p class="text-xs font-semibold uppercase text-violet-700">Countries Covered</p></div>
    </section>
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <form method="GET" class="grid gap-3 border-b border-slate-200 bg-slate-50 p-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_200px_auto]">
            <label><span class="mb-1 block text-[11px] font-bold uppercase text-slate-500">Country</span><select id="country_id" name="country_id" class="select2 w-full"><option value="">All Countries</option>@foreach($countries as $country)<option value="{{ $country->id }}" @selected($country->id == request('country_id'))>{{ $country->name }}</option>@endforeach</select></label>
            <label><span class="mb-1 block text-[11px] font-bold uppercase text-slate-500">State</span><input name="state_name" value="{{ request('state_name') }}" class="h-[42px] w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" placeholder="Search state..."></label>
            <label><span class="mb-1 block text-[11px] font-bold uppercase text-slate-500">Status</span><select id="status" name="status" class="select2 w-full"><option value="">All Statuses</option><option value="1" @selected(request('status') === '1')>Active</option><option value="0" @selected(request('status') === '0')>Inactive</option></select></label>
            <div class="flex items-end gap-2"><button class="h-[42px] rounded-lg bg-slate-900 px-4 text-xs font-semibold text-white hover:bg-slate-800"><i class="fas fa-filter mr-1"></i>Filter</button><a href="{{ route('role.states.index',['role'=>$role]) }}" class="flex h-[42px] items-center rounded-lg border border-slate-300 px-4 text-xs font-semibold text-slate-600 hover:bg-white">Reset</a></div>
        </form>
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50"><tr>
                @foreach(['SL','Country','State','Status','Created'] as $heading)<th class="px-5 py-3 text-left text-[11px] font-bold uppercase text-slate-500">{{ $heading }}</th>@endforeach
                @canany(['edit geography','delete geography'])<th class="px-5 py-3 text-right text-[11px] font-bold uppercase text-slate-500">Action</th>@endcanany
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($states as $key => $state)
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3 text-xs text-slate-500">{{ ($states->currentPage()-1)*$states->perPage()+$key+1 }}</td>
                    <td class="px-5 py-3 text-sm text-slate-600"><i class="fas fa-flag mr-2 text-slate-400"></i>{{ $state->country?->name ?? '-' }}</td>
                    <td class="px-5 py-3 text-sm font-semibold text-slate-800">{{ $state->name }}</td>
                    <td class="px-5 py-3">@if($state->status)<span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">Active</span>@else<span class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-700">Inactive</span>@endif</td>
                    <td class="px-5 py-3 text-xs text-slate-500">{{ $state->created_at?->format('d M Y') }}</td>
                    @canany(['edit geography','delete geography'])
                    <td class="px-5 py-3"><div class="flex justify-end gap-2">
                        @can('edit geography')<button type="button" class="edit-state-btn flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-blue-600 hover:bg-blue-50" data-country_id="{{ $state->country_id }}" data-country_name="{{ $state->country?->name }}" data-state_name="{{ $state->name }}" data-state_id="{{ $state->id }}" data-state_status="{{ $state->status }}" data-action="{{ route('role.states.update',['role'=>$role,'state'=>$state->id]) }}" title="Edit State"><i class="fas fa-pen text-xs"></i></button>@endcan
                        @can('delete geography')<button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-red-600 hover:bg-red-50" onclick="confirmDelete('{{ $state->id }}','{{ addslashes($state->name) }}')" title="Delete State"><i class="fas fa-trash text-xs"></i></button>@endcan
                    </div></td>
                    @endcanany
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-12 text-center"><i class="fas fa-map-location-dot mb-3 block text-3xl text-slate-300"></i><p class="text-sm font-medium text-slate-500">No states found.</p></td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="border-t border-slate-100 px-5 py-4">{{ $states->appends(request()->all())->links() }}</div>
    </section>
</div>
