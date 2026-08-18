@foreach(['create' => 'Add Country', 'edit' => 'Edit Country'] as $mode => $title)
<div id="{{ $mode }}CountryModal" role="dialog" class="fixed inset-0 z-[9000] hidden items-center justify-center bg-slate-950/50 p-4 country-overlay">
 <div class="w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-2xl">
  <header class="flex items-center gap-3 bg-gradient-to-r from-blue-700 to-indigo-600 px-5 py-4 text-white"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/15"><i class="fas fa-flag"></i></span><div><h2 class="text-sm font-bold">{{ $title }}</h2><p class="text-[11px] text-blue-100">Country information for all service modules</p></div><button type="button" class="country-close ml-auto h-8 w-8 rounded-lg bg-white/15"><i class="fas fa-times"></i></button></header>
  <form id="{{ $mode }}CountryForm" @if($mode === 'create') action="{{ route('role.countries.store',['role'=>$role]) }}" @endif class="grid gap-4 p-5">@csrf
   <label><span class="mb-1 block text-xs font-semibold text-slate-600">Country Name <b class="text-red-500">*</b></span><input id="{{ $mode }}CountryName" name="name" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" placeholder="e.g. Bangladesh"></label>
   <label><span class="mb-1 block text-xs font-semibold text-slate-600">Country Code <b class="text-red-500">*</b></span><input id="{{ $mode }}CountryCode" name="code" maxlength="20" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm uppercase focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" placeholder="e.g. BD"></label>
  </form>
  <footer class="flex justify-end gap-2 border-t bg-slate-50 px-5 py-3"><button type="button" class="country-close rounded-lg border px-4 py-2 text-xs font-semibold text-slate-600">Cancel</button><button id="{{ $mode }}CountrySubmit" type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white"><i class="fas fa-floppy-disk mr-1"></i>Save Country</button></footer>
 </div>
</div>
@endforeach
<div id="deleteCountryModal" role="dialog" class="fixed inset-0 z-[9000] hidden items-center justify-center bg-slate-950/50 p-4 country-overlay">
 <div class="w-full max-w-md overflow-hidden rounded-xl bg-white shadow-2xl">
  <header class="flex items-center gap-3 bg-red-600 px-5 py-4 text-white"><i class="fas fa-trash"></i><h2 class="text-sm font-bold">Delete Country</h2><button type="button" class="country-close ml-auto h-8 w-8 rounded-lg bg-white/15"><i class="fas fa-times"></i></button></header>
  <div class="p-5"><p class="text-sm text-slate-700">Delete <strong id="deleteCountryName"></strong>?</p><p class="mt-2 text-xs text-red-600">The country will be archived from active lists.</p></div>
  <footer class="flex justify-end gap-2 border-t bg-slate-50 px-5 py-3"><button class="country-close rounded-lg border px-4 py-2 text-xs font-semibold">Cancel</button><button id="deleteCountrySubmit" class="rounded-lg bg-red-600 px-4 py-2 text-xs font-semibold text-white">Delete</button></footer>
 </div>
</div>
