<div id="deleteModal" class="fixed inset-0 z-[9000] hidden flex items-center justify-center bg-slate-950/50 p-4 modal-backdrop">
    <div class="w-full max-w-md overflow-hidden rounded-xl bg-white shadow-2xl" onclick="event.stopPropagation()">
        <header class="flex items-center gap-3 bg-gradient-to-r from-red-600 to-rose-500 px-5 py-4 text-white"><span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white/20"><i class="fas fa-trash"></i></span>
            <div>
                <h2 class="text-sm font-bold">Delete Contract File</h2>
                <p class="text-[11px] text-red-100">This file will be moved to trash</p>
            </div><button class="modal-close-delete ml-auto flex h-8 w-8 items-center justify-center rounded-full bg-white/20"><i class="fas fa-times text-xs"></i></button>
        </header>
        <div class="p-5 text-sm text-slate-700">Delete <strong id="deleteName"></strong>?</div>
        <footer class="flex justify-end gap-2 bg-slate-50 px-5 py-3"><button class="modal-close-delete rounded-lg border px-4 py-2 text-xs font-semibold">Cancel</button><button id="confirmDeleteBtn" class="rounded-lg bg-red-600 px-4 py-2 text-xs font-semibold text-white">Delete</button></footer>
    </div>
</div>