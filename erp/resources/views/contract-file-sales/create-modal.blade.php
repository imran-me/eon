<div id="createModal" class="modalx fixed inset-0 z-[9000] hidden flex items-center justify-center bg-slate-950/50 p-4 modal-backdrop">
    <div class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl" onclick="event.stopPropagation()">
        <header class="flex items-center gap-3 bg-teal-600 px-5 py-4 text-white"><i class="fas fa-file-invoice"></i>
            <div>
                <h2 class="text-sm font-bold">Create Sale (Client-wise Voucher)</h2>
                <p class="text-[11px] text-teal-100">Bundle multiple contract files into one invoice</p>
            </div><button class="modal-close-create ml-auto h-8 w-8 rounded-lg bg-white/15"><i class="fas fa-times"></i></button>
        </header>
        <div class="overflow-y-auto p-5">
            <form id="createForm" method="POST" action="{{ route('role.contract-file-sales.store',['role'=>$role]) }}">@csrf @include('contract-file-sales.form-fields',['prefix'=>'create'])</form>
        </div>
        <footer class="flex justify-end gap-2 bg-slate-50 px-5 py-3"><button class="modal-close-create rounded-lg border px-4 py-2 text-xs font-semibold">Cancel</button><button id="createSubmit" class="rounded-lg bg-teal-600 px-4 py-2 text-xs font-semibold text-white"><i class="fas fa-save mr-1"></i>Save Sale</button></footer>
    </div>
</div>