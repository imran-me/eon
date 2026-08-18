<div id="deletePresetModal" class="fixed inset-0 z-[9000] bg-slate-950/50 [&:not(.hidden)]:flex items-start justify-center p-6 overflow-auto hidden">
    <div class="w-full max-w-md bg-white rounded-xl overflow-hidden shadow-2xl">
        <div class="flex items-center gap-3 bg-red-600 text-white px-5 py-4">
            <div class="h-9 w-9 rounded-lg bg-white/20 flex items-center justify-center flex-shrink-0"><i class="fas fa-trash"></i></div>
            <div class="flex-1">
                <h3 class="text-sm font-bold">Delete Price Preset</h3>
                <p class="text-xs opacity-80">This action cannot be undone</p>
            </div><button type="button" class="ml-auto h-7 w-7 rounded-lg bg-white/20 border-0 text-white cursor-pointer modal-close-delete">&times;</button>
        </div>
        <div class="p-5">
            <p>Are you sure you want to delete the pricing preset for <strong id="deletePresetName"></strong>?</p>
        </div>
        <div class="flex justify-end gap-2 bg-slate-50 px-5 py-3"><button type="button" class="rounded-lg border px-4 py-2 text-xs font-semibold cursor-pointer modal-close-delete">Cancel</button><button type="button" id="confirmDeletePreset" class="rounded-lg bg-red-600 text-white px-4 py-2 text-xs font-semibold cursor-pointer"><i class="fas fa-trash mr-1"></i>Delete</button></div>
    </div>
</div>
