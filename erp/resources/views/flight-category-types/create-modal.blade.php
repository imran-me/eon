<div id="createTypeModal" class="fixed inset-0 z-[9000] bg-slate-950/50 [&:not(.hidden)]:flex items-start justify-center p-6 overflow-auto hidden">
    <div class="w-full max-w-xl bg-white rounded-xl overflow-hidden shadow-2xl">
        <div class="flex items-center gap-3 bg-gradient-to-r from-indigo-600 to-blue-500 text-white px-5 py-4">
            <div class="h-9 w-9 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0"><i class="fas fa-tag"></i></div>
            <div class="flex-1">
                <h3 class="text-sm font-bold">Add Flight Category Type</h3>
                <p class="text-xs opacity-80">Create a reusable airline and ticket combination</p>
            </div><button type="button" class="ml-auto h-8 w-8 rounded-full bg-white/20 border-0 text-white cursor-pointer flex items-center justify-center modal-close-create"><i class="fas fa-times text-xs"></i></button>
        </div>
        <div class="p-5">
            <form id="createTypeForm" action="{{ route('role.flight-category-types.store',['role'=>$role]) }}" method="POST">@csrf<div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2"><label class="block text-xs font-semibold text-slate-500 mb-1">Type Name *</label><input id="create_type_name" name="name" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Turkish - 2 PNR Ticket">
                        <p id="create_type_name_msg" class="text-red-500 text-xs mt-1 hidden error-message">Type name is required.</p>
                    </div>
                    <div><label class="block text-xs font-semibold text-slate-500 mb-1">Code</label><input id="create_type_code" name="code" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. TR-2PNR"></div>
                    <div><label class="block text-xs font-semibold text-slate-500 mb-1">Base Fare (BDT)</label><input type="number" id="create_type_base_fare" name="base_fare" min="0" step="0.01" value="0" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                        <p id="create_type_base_fare_msg" class="text-red-500 text-xs mt-1 hidden error-message">Base fare cannot be negative.</p>
                    </div>
                    <div class="col-span-2"><label class="block text-xs font-semibold text-slate-500 mb-1">Status *</label><select name="status" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select></div>
                </div>
            </form>
        </div>
        <div class="flex justify-end gap-2 bg-slate-50 px-5 py-3"><button type="button" class="rounded-lg border px-4 py-2 text-xs font-semibold cursor-pointer modal-close-create">Cancel</button><button type="button" id="createTypeSubmit" class="rounded-lg bg-blue-600 text-white px-4 py-2 text-xs font-semibold cursor-pointer"><i class="fas fa-save mr-1"></i>Save Type</button></div>
    </div>
</div>
