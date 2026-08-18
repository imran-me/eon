<div id="viewTypeModal" class="fixed inset-0 z-[9000] bg-slate-950/50 [&:not(.hidden)]:flex items-start justify-center p-6 overflow-auto hidden">
    <div class="w-full max-w-xl bg-white rounded-xl overflow-hidden shadow-2xl">
        <div class="flex items-center gap-3 bg-gradient-to-r from-slate-700 to-slate-600 text-white px-5 py-4">
            <div class="h-9 w-9 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0"><i class="fas fa-eye"></i></div>
            <div class="flex-1">
                <h3 class="text-sm font-bold">Category Type Details</h3>
                <p class="text-xs opacity-80">Fare and category usage information</p>
            </div><button type="button" class="ml-auto h-8 w-8 rounded-full bg-white/20 border-0 text-white cursor-pointer flex items-center justify-center modal-close-view"><i class="fas fa-times text-xs"></i></button>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2 rounded-lg border border-slate-200 p-3"><span class="block text-[10px] uppercase text-slate-500 mb-1">Type Name</span><strong id="view_type_name">-</strong></div>
                <div class="rounded-lg border border-slate-200 p-3"><span class="block text-[10px] uppercase text-slate-500 mb-1">Code</span><strong id="view_type_code">-</strong></div>
                <div class="rounded-lg border border-slate-200 p-3"><span class="block text-[10px] uppercase text-slate-500 mb-1">Status</span><strong id="view_type_status">-</strong></div>
                <div class="rounded-lg border border-slate-200 p-3"><span class="block text-[10px] uppercase text-slate-500 mb-1">Base Fare</span><strong id="view_type_fare">-</strong></div>
                <div class="rounded-lg border border-slate-200 p-3"><span class="block text-[10px] uppercase text-slate-500 mb-1">Categories Using Type</span><strong id="view_type_categories">0</strong></div>
            </div>
        </div>
        <div class="flex justify-end gap-2 bg-slate-50 px-5 py-3"><button type="button" class="rounded-lg border px-4 py-2 text-xs font-semibold cursor-pointer modal-close-view">Close</button></div>
    </div>
</div>
