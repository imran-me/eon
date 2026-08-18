<div id="deleteModal" class="fixed inset-0 z-[9000] hidden flex items-center justify-center bg-slate-950/50 p-4 modal-backdrop">
 <div class="w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-2xl" onclick="event.stopPropagation()">
  <header class="flex items-center gap-3 bg-gradient-to-r from-red-600 to-rose-500 px-5 py-4 text-white">
   <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-white/20 text-lg"><i class="fas fa-trash"></i></span>
   <div>
    <h2 class="text-sm font-bold">Delete File Category</h2>
    <p class="text-[11px] text-red-100">This action cannot be undone</p>
   </div>
   <button class="modal-close-delete ml-auto flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-white"><i class="fas fa-times text-xs"></i></button>
  </header>
  <div class="p-5">
   <p class="text-sm text-slate-700">Are you sure you want to delete <strong id="deleteName"></strong>?</p>
   <p class="mt-1.5 text-xs text-red-500">All associated data will be permanently removed.</p>
  </div>
  <footer class="flex justify-end gap-2 border-t bg-slate-50 px-5 py-3">
   <button class="modal-close-delete rounded-xl border px-5 py-2 text-xs font-semibold text-slate-600">Cancel</button>
   <button id="confirmDeleteBtn" class="rounded-xl bg-red-600 px-5 py-2 text-xs font-semibold text-white hover:bg-red-700"><i class="fas fa-trash mr-1.5"></i>Delete</button>
  </footer>
 </div>
</div>
