<div id="editTypeModal" class="fixed inset-0 z-[9000] bg-slate-950/50 [&:not(.hidden)]:flex items-start justify-center p-6 overflow-auto hidden">
    <div class="w-full max-w-xl bg-white rounded-xl overflow-hidden shadow-2xl">
        <div class="flex items-center gap-3 bg-gradient-to-r from-violet-600 to-indigo-500 text-white px-5 py-4">
            <div class="h-9 w-9 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0"><i class="fas fa-edit"></i></div>
            <div class="flex-1">
                <h3 class="text-sm font-bold">Edit Flight Category Type</h3>
                <p class="text-xs opacity-80">Update the reusable type configuration</p>
            </div><button type="button" class="ml-auto h-8 w-8 rounded-full bg-white/20 border-0 text-white cursor-pointer flex items-center justify-center modal-close-edit"><i class="fas fa-times text-xs"></i></button>
        </div>
        <div class="p-5">
            <form id="editTypeForm" method="POST">@csrf @method('PUT')<div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2"><label class="block text-xs font-semibold text-slate-500 mb-1">Type Name *</label><input id="edit_type_name" name="name" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                        <p id="edit_type_name_msg" class="text-red-500 text-xs mt-1 hidden error-message">Type name is required.</p>
                    </div>
                    <div><label class="block text-xs font-semibold text-slate-500 mb-1">Code</label><input id="edit_type_code" name="code" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"></div>
                    <div><label class="block text-xs font-semibold text-slate-500 mb-1">Base Fare (BDT)</label><input type="number" id="edit_type_base_fare" name="base_fare" min="0" step="0.01" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                        <p id="edit_type_base_fare_msg" class="text-red-500 text-xs mt-1 hidden error-message">Base fare cannot be negative.</p>
                    </div>
                    <div class="col-span-2"><label class="block text-xs font-semibold text-slate-500 mb-1">Status *</label><select id="edit_type_status" name="status" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select></div>
                </div>
            </form>
        </div>
        <div class="flex justify-end gap-2 bg-slate-50 px-5 py-3"><button type="button" class="rounded-lg border px-4 py-2 text-xs font-semibold cursor-pointer modal-close-edit">Cancel</button><button type="button" id="editTypeSubmit" class="rounded-lg bg-violet-600 text-white px-4 py-2 text-xs font-semibold cursor-pointer"><i class="fas fa-save mr-1"></i>Update Type</button></div>
    </div>
</div>
