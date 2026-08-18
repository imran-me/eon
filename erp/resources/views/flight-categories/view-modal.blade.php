<div id="viewModal" class="fixed inset-0 z-[9000] bg-black/50 [&:not(.hidden)]:flex items-start justify-center p-6 overflow-y-auto hidden modal-backdrop">
    <div class="w-full max-w-2xl bg-white rounded-2xl overflow-hidden shadow-2xl">
        <div class="flex items-center gap-3 bg-gradient-to-br from-blue-600 to-blue-700 px-5 py-4">
            <div class="h-10 w-10 rounded-xl bg-white/20 flex items-center justify-center text-white text-base flex-shrink-0"><i class="fas fa-folder-open"></i></div>
            <div>
                <div class="text-sm font-bold text-white" id="view_name">Flight Category</div>
                <div class="text-xs text-blue-100 mt-0.5">Category configuration, fare and airline partners</div>
            </div>
            <button type="button" class="ml-auto h-8 w-8 rounded-xl bg-white/20 border-0 text-white cursor-pointer flex items-center justify-center modal-close-view">x</button>
        </div>

        <div class="p-5 max-h-[calc(100vh-220px)] overflow-y-auto">
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-3">
                    <div class="text-[10px] font-bold text-blue-700 uppercase tracking-wide mb-1.5">Code</div>
                    <div id="view_code" class="font-mono text-base font-bold text-blue-900">-</div>
                </div>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-1.5">Status</div>
                    <div id="view_status">-</div>
                </div>
            </div>

            <div class="mb-5 last:mb-0">
                <div class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-3 pb-2"><i class="fas fa-tag"></i> Category Type</div>
                <div id="view_type" class="border border-slate-200 rounded-xl p-3 bg-slate-50 font-bold">-</div>
            </div>

            <div class="mb-5 last:mb-0">
                <div class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-3 pb-2"><i class="fas fa-route"></i> Route &amp; Capacity</div>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="col-span-full border border-slate-200 rounded-xl p-3 bg-white">
                        <span class="text-slate-500 text-xs block font-bold mb-1">Typical Route(s)</span>
                        <span id="view_routes" class="font-medium text-slate-900">-</span>
                    </div>
                    <div class="border border-slate-200 rounded-xl p-3 bg-white">
                        <span class="text-slate-500 text-xs block font-bold mb-1">Default Seats</span>
                        <span id="view_seats" class="font-medium font-mono text-slate-900">-</span>
                    </div>
                    <div class="border border-slate-200 rounded-xl p-3 bg-white">
                        <span class="text-slate-500 text-xs block font-bold mb-1">Base Fare</span>
                        <span id="view_fare" class="font-medium font-mono text-emerald-700">-</span>
                    </div>
                </div>
            </div>

            <div class="mb-5 last:mb-0">
                <div class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-3 pb-2"><i class="fas fa-plane"></i> Airline Partners</div>
                <div class="border border-slate-200 rounded-xl p-3 bg-slate-50 text-sm leading-relaxed">
                    <span id="view_airlines" class="font-medium text-slate-900">-</span>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2 bg-slate-50 px-5 py-3">
            <button type="button" class="rounded-xl border bg-slate-100 text-slate-700 px-4 py-2 text-sm font-semibold cursor-pointer inline-flex items-center gap-1.5 modal-close-view">Close</button>
        </div>
    </div>
</div>
