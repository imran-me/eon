<div id="viewModal" class="fixed inset-0 z-[9000] hidden items-start justify-center overflow-y-auto bg-slate-900/55 px-4 py-6 modal-backdrop flex">
    <div class="w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center gap-3 bg-gradient-to-r from-blue-700 to-blue-600 px-6 py-5 text-white">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/20 text-lg"><i class="fas fa-receipt"></i></div>
            <div><div class="text-base font-extrabold" id="view_booking_number">Voucher</div><div class="mt-0.5 text-xs text-blue-100">Multi-flight sales voucher details</div></div>
            <button type="button" class="modal-close-view ml-auto flex h-8 w-8 items-center justify-center rounded-lg bg-white/20 text-white hover:bg-white/30" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="max-h-[calc(100vh-220px)] overflow-y-auto px-6 py-5">
            <div class="mb-5 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded-xl bg-slate-50 p-4"><div class="mb-1 text-[10px] font-extrabold uppercase text-slate-400">Client / Agent</div><div class="text-sm font-extrabold text-slate-800" id="view_client">-</div></div>
                <div class="rounded-xl bg-slate-50 p-4"><div class="mb-1 text-[10px] font-extrabold uppercase text-slate-400">Phone</div><div class="text-sm font-extrabold text-slate-800" id="view_phone">-</div></div>
                <div class="rounded-xl bg-slate-50 p-4"><div class="mb-1 text-[10px] font-extrabold uppercase text-slate-400">Total Seats</div><div class="text-sm font-extrabold text-slate-800" id="view_seats">-</div></div>
            </div>
            <div class="mb-3 flex items-center gap-2 border-b border-slate-100 pb-2 text-[11px] font-extrabold uppercase tracking-[0.12em] text-slate-500"><i class="fas fa-plane"></i> Voucher Flights</div>
            <div class="mb-5 overflow-hidden rounded-xl border border-slate-200 bg-white">
                <table class="w-full border-collapse text-xs">
                    <thead class="bg-blue-700 text-white"><tr><th class="px-3 py-3 text-left font-extrabold uppercase">#</th><th class="px-3 py-3 text-left font-extrabold uppercase">Flight</th><th class="px-3 py-3 text-left font-extrabold uppercase">Route / Date</th><th class="px-3 py-3 text-left font-extrabold uppercase">Seats</th><th class="px-3 py-3 text-left font-extrabold uppercase">Unit Price</th><th class="px-3 py-3 text-left font-extrabold uppercase">Amount</th></tr></thead>
                    <tbody id="view_flight_items"></tbody>
                </table>
            </div>
            <div class="my-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="rounded-xl bg-blue-50 p-4 text-center"><div class="text-xl font-black text-blue-700" id="view_flights_count">0</div><div class="mt-1 text-[10px] font-extrabold uppercase text-blue-400">Flights</div></div>
                <div class="rounded-xl bg-emerald-50 p-4 text-center"><div class="text-xl font-black text-emerald-700" id="view_total">-</div><div class="mt-1 text-[10px] font-extrabold uppercase text-emerald-400">Grand Total</div></div>
                <div class="rounded-xl bg-teal-50 p-4 text-center"><div class="text-xl font-black text-teal-700" id="view_paid">-</div><div class="mt-1 text-[10px] font-extrabold uppercase text-teal-400">Paid</div></div>
                <div class="rounded-xl bg-rose-50 p-4 text-center"><div class="text-xl font-black text-rose-700" id="view_due">-</div><div class="mt-1 text-[10px] font-extrabold uppercase text-rose-400">Due</div></div>
            </div>
            <div class="mb-4 border-b border-slate-100 pb-4 text-center"><span id="view_status">-</span></div>
            <div><span class="block text-xs text-slate-500">Notes</span><span id="view_notes" class="font-medium text-slate-800">-</span></div>
            <div id="cfs-cmt-placeholder" style="margin-top:8px;"></div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4"><button type="button" class="modal-close-view rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100">Close</button><a id="view_invoice_link" href="#" target="_blank" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2 text-sm font-bold text-white no-underline hover:bg-blue-700"><i class="fas fa-print"></i> Print Voucher</a></div>
    </div>
</div>
