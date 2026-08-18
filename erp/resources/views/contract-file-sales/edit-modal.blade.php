<div id="editModal" class="modalx fixed inset-0 z-[9000] hidden flex items-center justify-center bg-slate-950/50 p-4 modal-backdrop">
    <div class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl" onclick="event.stopPropagation()">
        <header class="flex items-center gap-3 bg-violet-600 px-5 py-4 text-white"><i class="fas fa-pen"></i>
            <div>
                <h2 class="text-sm font-bold">Edit Sale</h2>
                <p class="text-[11px] text-violet-100">Update bundled contract file invoice</p>
            </div><button class="modal-close-edit ml-auto h-8 w-8 rounded-lg bg-white/15"><i class="fas fa-times"></i></button>
        </header>
        <div class="overflow-y-auto p-5">
            <form id="editForm" method="POST">@csrf @method('PUT') @include('contract-file-sales.form-fields',['prefix'=>'edit'])</form>
        </div>
        <footer class="flex justify-end gap-2 bg-slate-50 px-5 py-3"><button class="modal-close-edit rounded-lg border px-4 py-2 text-xs font-semibold">Cancel</button><button id="editSubmit" class="rounded-lg bg-violet-600 px-4 py-2 text-xs font-semibold text-white"><i class="fas fa-save mr-1"></i>Update Sale</button></footer>
    </div>
</div>