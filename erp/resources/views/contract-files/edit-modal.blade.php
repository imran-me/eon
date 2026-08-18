<div id="editModal" class="fixed inset-0 z-[9000] hidden flex items-center justify-center bg-slate-950/50 p-4 modal-backdrop">
    <div class="flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl" onclick="event.stopPropagation()">
        <header class="flex items-center gap-3 bg-gradient-to-r from-violet-600 to-indigo-500 px-5 py-4 text-white"><span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white/20"><i class="fas fa-pen"></i></span><div><h2 class="text-sm font-bold">Edit Work Permit File</h2><p class="text-[11px] text-violet-100">Update applicant, status and document checklist</p></div><button type="button" class="modal-close-edit ml-auto flex h-8 w-8 items-center justify-center rounded-full bg-white/20"><i class="fas fa-times text-xs"></i></button></header>
        <div class="cfm-body overflow-y-auto p-5">
            <form id="editForm" method="POST">
                @csrf
                @method('PUT')
                @include('contract-files.form-fields', ['prefix' => 'edit'])
            </form>
        </div>
        <footer class="flex justify-end gap-2 bg-slate-50 px-5 py-3"><button type="button" class="modal-close-edit rounded-lg border px-4 py-2 text-xs font-semibold">Cancel</button><button id="editSubmit" type="button" class="rounded-lg bg-violet-600 px-4 py-2 text-xs font-semibold text-white"><i class="fas fa-save mr-1"></i>Update File</button></footer>
    </div>
</div>
