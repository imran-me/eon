<div id="detailsModal" class="modalx fixed inset-0 z-[9000] hidden flex items-center justify-center bg-slate-950/50 p-4 modal-backdrop">
    <div class="flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl" onclick="event.stopPropagation()">
        <header class="flex items-center gap-3 bg-blue-600 px-5 py-4 text-white"><i class="fas fa-receipt"></i>
            <div>
                <h2 id="v_invoice" class="text-sm font-bold">Voucher Details</h2>
                <p id="v_subtitle" class="text-[11px] text-blue-100">Contract File Sale Invoice</p>
            </div><button class="modal-close-details ml-auto h-8 w-8 rounded-lg bg-white/15"><i class="fas fa-times"></i></button>
        </header>
        <div class="voucher-detail-body overflow-y-auto p-5">
            <div id="v_body">
                <div class="p-10 text-center text-sm text-slate-500">Loading...</div>
            </div>
        </div>
        <footer class="voucher-detail-foot flex flex-wrap justify-end gap-2 bg-slate-50 px-5 py-3"><button class="modal-close-details rounded-lg border px-4 py-2 text-xs font-semibold">Close</button><button id="v_email_btn" class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white"><i class="fas fa-paper-plane mr-1"></i>Email Voucher</button><a id="v_print_btn" class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white" href="#" target="_blank"><i class="fas fa-print mr-1"></i>Print Voucher</a></footer>
    </div>
</div>