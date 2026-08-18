<div id="viewPresetModal" class="fixed inset-0 z-[9000] bg-slate-950/50 [&:not(.hidden)]:flex items-start justify-center p-6 overflow-auto hidden">
    <div class="w-full max-w-2xl bg-white rounded-xl overflow-hidden shadow-2xl">
        <div class="flex items-center gap-3 bg-blue-600 text-white px-5 py-4">
            <div class="h-9 w-9 rounded-lg bg-white/20 flex items-center justify-center flex-shrink-0"><i class="fas fa-eye"></i></div>
            <div class="flex-1">
                <h3 class="text-sm font-bold">Price Preset Details</h3>
                <p class="text-xs opacity-80">Automatic cost and selling configuration</p>
            </div><button type="button" class="ml-auto h-7 w-7 rounded-lg bg-white/20 border-0 text-white cursor-pointer modal-close-view">&times;</button>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-lg border border-slate-200 p-3"><span class="block text-[10px] uppercase text-slate-500 mb-1">Airline</span><strong id="view_preset_airline">-</strong></div>
                <div class="rounded-lg border border-slate-200 p-3"><span class="block text-[10px] uppercase text-slate-500 mb-1">Status</span><strong id="view_preset_status">-</strong></div>
                <div class="rounded-lg border border-slate-200 p-3"><span class="block text-[10px] uppercase text-slate-500 mb-1">Category</span><strong id="view_preset_category">-</strong></div>
                <div class="rounded-lg border border-slate-200 p-3"><span class="block text-[10px] uppercase text-slate-500 mb-1">Category Type</span><strong id="view_preset_type">-</strong></div>
                <div class="rounded-lg border border-slate-200 p-3"><span class="block text-[10px] uppercase text-slate-500 mb-1">Ticket Class</span><strong id="view_preset_class">-</strong></div>
                <div class="rounded-lg border border-slate-200 p-3"><span class="block text-[10px] uppercase text-slate-500 mb-1">Handling</span><strong id="view_preset_handling">-</strong></div>
                <div class="col-span-2 rounded-lg border border-slate-200 p-3"><span class="block text-[10px] uppercase text-slate-500 mb-1">Cost Breakdown</span><strong id="view_preset_costs">-</strong></div>
                <div class="col-span-2 rounded-lg border border-slate-200 p-3"><span class="block text-[10px] uppercase text-slate-500 mb-1">Sale Price / Pax</span><strong id="view_preset_sale">-</strong></div>
            </div>
        </div>
        <div class="flex justify-end gap-2 bg-slate-50 px-5 py-3"><button type="button" class="rounded-lg border px-4 py-2 text-xs font-semibold cursor-pointer modal-close-view">Close</button></div>
    </div>
</div>
