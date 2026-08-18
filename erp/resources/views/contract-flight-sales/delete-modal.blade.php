<div id="deleteModal" class="fixed inset-0 z-[9000] hidden items-center justify-center bg-slate-900/55 px-4 py-6 modal-backdrop flex">
    <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center gap-3 bg-gradient-to-r from-red-600 to-red-700 px-6 py-5 text-white">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/20 text-lg"><i class="fas fa-trash"></i></div>
            <div><div class="text-base font-extrabold">Delete Booking</div><div class="mt-0.5 text-xs text-red-100">Remove this flight sale</div></div>
            <button type="button" class="modal-close-delete ml-auto flex h-8 w-8 items-center justify-center rounded-lg bg-white/20 text-white hover:bg-white/30">x</button>
        </div>
        <div class="px-6 py-5 text-sm text-slate-600">
            Are you sure you want to delete <strong id="deleteName" class="text-slate-900"></strong>?
            <div class="mt-2 text-xs text-slate-400">This action cannot be undone.</div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4">
            <button type="button" class="modal-close-delete rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100">Cancel</button>
            <button id="confirmDeleteBtn" type="button" class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-5 py-2 text-sm font-bold text-white hover:bg-red-700"><i class="fas fa-trash"></i> Delete</button>
        </div>
    </div>
</div>
