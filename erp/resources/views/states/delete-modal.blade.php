<div id="deleteStateModal" role="dialog" class="fixed inset-0 z-[9000] hidden flex items-center justify-center bg-slate-950/50 p-4 state-overlay">
 <div class="w-full max-w-md overflow-hidden rounded-xl bg-white shadow-2xl">
  <header class="flex items-center gap-3 bg-red-600 px-5 py-4 text-white"><i class="fas fa-trash"></i><h2 class="text-sm font-bold">Delete State</h2><button type="button" class="modal-close-delete ml-auto h-8 w-8 rounded-lg bg-white/15"><i class="fas fa-times"></i></button></header>
  <div class="p-5"><p class="text-sm text-slate-700">Delete <strong id="deleteStateName"></strong>?</p><p class="mt-2 text-xs text-red-600">This action cannot be undone.</p></div>
  <footer class="flex justify-end gap-2 border-t bg-slate-50 px-5 py-3"><button class="modal-close-delete rounded-lg border px-4 py-2 text-xs font-semibold text-slate-600">Cancel</button><button id="confirmDeleteBtn" data-action="{{ route('role.states.destroy',['role'=>Str::slug(Auth::user()->getRoleNames()->first()),'state'=>1]) }}" class="rounded-lg bg-red-600 px-4 py-2 text-xs font-semibold text-white">Delete</button></footer>
 </div>
</div>
