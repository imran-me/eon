<div id="deleteModal" class="fixed inset-0 z-[9000] bg-black/50 [&:not(.hidden)]:flex items-start justify-center p-6 overflow-y-auto hidden modal-backdrop">
    <div class="w-full max-w-md bg-white rounded-2xl overflow-hidden shadow-2xl">
        <div class="flex items-center gap-3 bg-gradient-to-br from-red-600 to-red-700 px-5 py-4">
            <div class="h-10 w-10 rounded-xl bg-white/20 flex items-center justify-center text-white text-base flex-shrink-0"><i class="fas fa-trash"></i></div>
            <div>
                <div class="text-sm font-bold text-white">Confirm Delete</div>
                <div class="text-xs text-red-100 mt-0.5">This action cannot be undone</div>
            </div>
            <button type="button" class="ml-auto h-8 w-8 rounded-xl bg-white/20 border-0 text-white cursor-pointer flex items-center justify-center modal-close-delete"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-5">
            <p>Are you sure you want to delete <span id="deleteName" class="font-semibold"></span>?</p>
            <p class="text-red-500 mt-2 text-sm">This action cannot be undone.</p>
        </div>
        <div class="flex justify-end gap-2 bg-slate-50 px-5 py-3">
            <button class="rounded-xl border bg-slate-100 text-slate-700 px-4 py-2 text-sm font-semibold cursor-pointer inline-flex items-center gap-1.5 modal-close-delete">Cancel</button>
            <button id="confirmDeleteBtn" data-action="" class="rounded-xl bg-red-600 text-white px-4 py-2 text-sm font-semibold cursor-pointer inline-flex items-center gap-1.5"><i class="fas fa-trash"></i> Delete</button>
        </div>
    </div>
</div>
