<div id="deleteModal" class="modalx fixed inset-0 z-[9000] hidden flex items-center justify-center bg-slate-950/50 p-4 modal-backdrop">
    <div class="w-full max-w-md overflow-hidden rounded-xl bg-white shadow-2xl" onclick="event.stopPropagation()">
        <header class="flex items-center gap-3 bg-red-600 px-5 py-4 text-white"><i class="fas fa-trash"></i>
            <h2 class="text-sm font-bold">Delete Sale</h2><button class="modal-close-delete ml-auto h-8 w-8 rounded-lg bg-white/15"><i class="fas fa-times"></i></button>
        </header>
        <div class="p-5 text-sm text-slate-700">Delete <strong id="deleteName"></strong>?</div>
        <footer class="flex justify-end gap-2 bg-slate-50 px-5 py-3"><button class="modal-close-delete rounded-lg border px-4 py-2 text-xs font-semibold">Cancel</button><button id="confirmDeleteBtn" class="rounded-lg bg-red-600 px-4 py-2 text-xs font-semibold text-white">Delete</button></footer>
    </div>
</div>