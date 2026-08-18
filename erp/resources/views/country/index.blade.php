@extends('layout.app')
@section('meta-information')<title>Country Management</title>@endsection
@section('main-content')
@php($role = Str::slug(Auth::user()->getRoleNames()->first()))
<div class="space-y-4">
 <header class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
  <div class="flex items-center gap-3">
   <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-blue-50 text-blue-600"><i class="fas fa-globe"></i></span>
   <div><h1 class="text-lg font-bold text-slate-900">Countries</h1><p class="text-xs text-slate-500">Manage countries used across ticketing and business services.</p></div>
  </div>
  @can('create geography')<button type="button" class="create-country-btn rounded-lg bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"><i class="fas fa-plus mr-1"></i>Add Country</button>@endcan
 </header>
 <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
  <div class="rounded-xl bg-blue-50 p-4"><b class="text-2xl text-blue-700">{{ $totalCountries }}</b><p class="text-xs font-semibold uppercase text-blue-700">Total Countries</p></div>
  <div class="rounded-xl bg-emerald-50 p-4"><b class="text-2xl text-emerald-700">{{ $activeCountries }}</b><p class="text-xs font-semibold uppercase text-emerald-700">Active Countries</p></div>
  <div class="rounded-xl bg-amber-50 p-4"><b class="text-2xl text-amber-700">{{ $inactiveCountries }}</b><p class="text-xs font-semibold uppercase text-amber-700">Inactive Countries</p></div>
  <div class="rounded-xl bg-violet-50 p-4"><b class="text-2xl text-violet-700">{{ $trashedCountries }}</b><p class="text-xs font-semibold uppercase text-violet-700">Archived Countries</p></div>
 </section>
</div>
<section class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
 <form class="grid gap-3 border-b border-slate-200 bg-slate-50 p-4 md:grid-cols-[1fr_220px_auto]">
  <input name="country_name" value="{{ request('country_name') }}" class="h-10 rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" placeholder="Search country...">
  <select name="status" class="h-10 rounded-lg border border-slate-300 px-3 text-sm"><option value="all">All statuses</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></select>
  <div class="flex gap-2"><button class="rounded-lg bg-slate-900 px-4 text-xs font-semibold text-white">Filter</button><a href="{{ route('role.countries.index',['role'=>$role]) }}" class="flex items-center rounded-lg border border-slate-300 px-4 text-xs font-semibold text-slate-600">Reset</a></div>
 </form>
 <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200">
  <thead class="bg-slate-50"><tr>
   @foreach(['SL','Country','Code','Status','Created'] as $heading)<th class="px-5 py-3 text-left text-[11px] font-bold uppercase text-slate-500">{{ $heading }}</th>@endforeach
   <th class="px-5 py-3 text-right text-[11px] font-bold uppercase text-slate-500">Action</th>
  </tr></thead>
  <tbody class="divide-y divide-slate-100">
  @forelse($countries as $key => $country)
   <tr class="hover:bg-slate-50">
    <td class="px-5 py-3 text-xs text-slate-500">{{ ($countries->currentPage()-1)*$countries->perPage()+$key+1 }}</td>
    <td class="px-5 py-3 text-sm font-semibold text-slate-800">{{ $country->name }}</td>
    <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $country->code }}</td>
    <td class="px-5 py-3">@if($country->trashed())<span class="rounded-full bg-amber-100 px-2 py-1 text-xs text-amber-700">Inactive</span>@else<span class="rounded-full bg-emerald-100 px-2 py-1 text-xs text-emerald-700">Active</span>@endif</td>
    <td class="px-5 py-3 text-xs text-slate-500">{{ $country->created_at?->format('d M Y') }}</td>
    <td class="px-5 py-3"><div class="flex justify-end gap-2">@unless($country->trashed())
     @can('edit geography')<button class="edit-country-btn h-8 w-8 rounded-lg border text-blue-600" data-name="{{ $country->name }}" data-code="{{ $country->code }}" data-action="{{ route('role.countries.update',['role'=>$role,'country'=>$country->id]) }}"><i class="fas fa-pen text-xs"></i></button>@endcan
     @can('delete geography')<button class="delete-country-btn h-8 w-8 rounded-lg border text-red-600" data-name="{{ $country->name }}" data-action="{{ route('role.countries.destroy',['role'=>$role,'country'=>$country->id]) }}"><i class="fas fa-trash text-xs"></i></button>@endcan
    @endunless</div></td>
   </tr>
  @empty<tr><td colspan="6" class="p-10 text-center text-sm text-slate-500">No countries found.</td></tr>
  @endforelse
  </tbody>
 </table></div>
 <div class="border-t border-slate-100 p-4">{{ $countries->appends(request()->all())->links() }}</div>
</section>
@include('country.modals')
@endsection
@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function(){
 $.ajaxSetup({headers:{'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')}});
 const open=id=>$(id).removeClass('hidden').addClass('flex');
 const close=()=>$('.country-overlay').addClass('hidden').removeClass('flex');
 const done=r=>Swal.fire({icon:'success',title:'Done',text:r.message}).then(()=>location.reload());
 const fail=x=>Swal.fire({icon:'error',title:'Error',text:x.responseJSON?.message||Object.values(x.responseJSON?.errors||{})[0]?.[0]||'Something went wrong.'});
 $('.create-country-btn').click(()=>open('#createCountryModal'));
 $('.edit-country-btn').click(function(){$('#editCountryName').val($(this).data('name'));$('#editCountryCode').val($(this).data('code'));$('#editCountryForm').attr('action',$(this).data('action'));open('#editCountryModal')});
 $('.delete-country-btn').click(function(){$('#deleteCountryName').text($(this).data('name'));$('#deleteCountrySubmit').data('action',$(this).data('action'));open('#deleteCountryModal')});
 $('.country-close,.country-overlay').click(function(e){if($(this).hasClass('country-close')||e.target===this)close()});
 $('#createCountrySubmit').click(()=>$.post($('#createCountryForm').attr('action'),$('#createCountryForm').serialize()).done(done).fail(fail));
 $('#editCountrySubmit').click(()=>$.ajax({url:$('#editCountryForm').attr('action'),method:'PUT',data:$('#editCountryForm').serialize()}).done(done).fail(fail));
 $('#deleteCountrySubmit').click(function(){$.ajax({url:$(this).data('action'),method:'DELETE'}).done(done).fail(fail)});
});
</script>
@endsection
