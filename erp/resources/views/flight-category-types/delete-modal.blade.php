<div id="deleteTypeModal" class="fixed inset-0 z-[9000] bg-slate-950/50 [&:not(.hidden)]:flex items-start justify-center p-6 overflow-auto hidden">
    <div class="w-full max-w-md bg-white rounded-xl overflow-hidden shadow-2xl">
        <div class="flex items-center gap-3 bg-gradient-to-r from-red-600 to-rose-500 text-white px-5 py-4">
            <div class="h-9 w-9 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0"><i class="fas fa-trash"></i></div>
            <div class="flex-1">
                <h3 class="text-sm font-bold">Delete Category Type</h3>
                <p class="text-xs opacity-80">This action cannot be undone</p>
            </div><button type="button" class="ml-auto h-8 w-8 rounded-full bg-white/20 border-0 text-white cursor-pointer flex items-center justify-center modal-close-delete"><i class="fas fa-times text-xs"></i></button>
        </div>
        <div class="p-5">
            <p>Are you sure you want to delete <strong id="deleteTypeName"></strong>?</p>
        </div>
        <div class="flex justify-end gap-2 bg-slate-50 px-5 py-3"><button type="button" class="rounded-lg border px-4 py-2 text-xs font-semibold cursor-pointer modal-close-delete">Cancel</button><button type="button" id="confirmDeleteType" class="rounded-lg bg-red-600 text-white px-4 py-2 text-xs font-semibold cursor-pointer"><i class="fas fa-trash mr-1"></i>Delete</button></div>
    </div>
</div>
