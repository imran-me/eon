<div id="deleteModal" class="fixed inset-0 z-[9000] hidden flex items-center justify-center bg-slate-950/50 p-4 modal-backdrop">
    <div class="w-full max-w-md overflow-hidden rounded-xl bg-white shadow-2xl" onclick="event.stopPropagation()">
        <header class="flex items-center gap-3 bg-gradient-to-r from-red-600 to-rose-500 px-5 py-4 text-white"><span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white/20"><i class="fas fa-trash"></i></span>
            <div>
                <h2 class="text-sm font-bold">Delete Contract File</h2>
                <p class="text-[11px] text-red-100">Wrong-entry deletion, with accounting safety checks</p>
            </div><button class="modal-close-delete ml-auto flex h-8 w-8 items-center justify-center rounded-full bg-white/20"><i class="fas fa-times text-xs"></i></button>
        </header>
        <div class="p-5 text-sm text-slate-700">
            <p>Delete <strong id="deleteName"></strong>?</p>
            <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-800">Outstanding client and vendor postings will be reversed before the file moves to trash. A file with refund, cancellation, re-processing or other financial-change history cannot be deleted.</p>
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3">
                <label for="deleteReason" class="mb-2 block text-[10px] font-extrabold uppercase tracking-wide text-amber-900">Deletion reason *</label>
                <textarea id="deleteReason" class="min-h-20 w-full resize-y rounded-lg border border-amber-300 bg-white p-3 text-xs text-slate-800 outline-none focus:border-red-500 focus:ring-2 focus:ring-red-100" maxlength="500" placeholder="Explain why this contract file is a wrong entry"></textarea>
                <small class="mt-1 block text-[10px] text-amber-700">Saved permanently in the file's audit event.</small>
            </div>
            <div class="mt-4 rounded-lg border border-dashed border-red-300 bg-red-50 p-3">
                <label for="deleteConfirmation" class="mb-2 flex items-center justify-between gap-3 text-[10px] font-extrabold uppercase tracking-wide text-red-800"><span>Final confirmation *</span><span>Type <code class="rounded bg-red-100 px-2 py-1">CONFIRM</code></span></label>
                <input id="deleteConfirmation" class="w-full rounded-lg border border-red-200 bg-white p-3 text-xs text-slate-800 outline-none focus:border-red-500 focus:ring-2 focus:ring-red-100" autocomplete="off" placeholder="Type CONFIRM to delete">
            </div>
            <p id="deleteValidation" class="mt-3 hidden rounded-lg bg-red-50 p-2 text-xs text-red-700" role="alert"></p>
        </div>
        <footer class="flex justify-end gap-2 bg-slate-50 px-5 py-3"><button class="modal-close-delete rounded-lg border px-4 py-2 text-xs font-semibold">Cancel</button><button id="confirmDeleteBtn" class="rounded-lg bg-red-600 px-4 py-2 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40" disabled><i class="fas fa-trash mr-1"></i> Delete file</button></footer>
    </div>
</div>
