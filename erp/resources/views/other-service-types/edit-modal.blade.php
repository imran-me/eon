<div id="editModal" class="fixed inset-0 z-[9999] bg-black/50 hidden [&:not(.hidden)]:flex items-start justify-center p-6 overflow-y-auto">
    <div class="w-full max-w-lg bg-white rounded-2xl overflow-hidden shadow-2xl">
        <div class="flex items-center gap-3 bg-gradient-to-r from-violet-600 to-indigo-500 px-5 py-4">
            <div class="h-10 w-10 rounded-xl bg-white/20 flex items-center justify-center text-white flex-shrink-0">
                <i class="fas fa-edit"></i>
            </div>
            <div class="flex-1">
                <h2 class="text-sm font-bold text-white">Edit Service Type</h2>
                <p class="text-xs text-violet-100 mt-0.5">Update icon, colors and default fee</p>
            </div>
            <button type="button" class="modal-close-edit ml-auto h-8 w-8 rounded-full bg-white/20 border-0 text-white cursor-pointer flex items-center justify-center">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>

        <form id="editForm" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" id="editItemId" name="id" value="">
            <div class="p-5 space-y-4 max-h-[calc(100vh-160px)] overflow-y-auto">

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Type Name <sup class="text-red-500">*</sup></label>
                    <input type="text" id="edit_name" name="name" placeholder="e.g. Courier Delivery"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-violet-500">
                    <p id="edit_name_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please enter a type name</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Icon (FontAwesome class)</label>
                        <input type="text" id="edit_icon" name="icon" placeholder="fa-truck-fast"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-violet-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Default Fee (৳)</label>
                        <input type="number" id="edit_default_fee" name="default_fee" min="0" step="0.01" placeholder="0.00"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-violet-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Text Color</label>
                        <input type="color" id="edit_color" name="color" value="#64748b"
                            class="h-10 w-full rounded-xl border border-slate-300 px-2 py-1 cursor-pointer">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Background Color</label>
                        <input type="color" id="edit_bg_color" name="bg_color" value="#f1f5f9"
                            class="h-10 w-full rounded-xl border border-slate-300 px-2 py-1 cursor-pointer">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Preview</label>
                    <div class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3">
                        <span id="edit_preview" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold" style="background:#f1f5f9;color:#64748b;">
                            <i class="fas fa-circle-dot"></i> Preview
                        </span>
                    </div>
                </div>

                <label class="flex items-center gap-2 rounded-xl bg-slate-50 px-4 py-3 cursor-pointer">
                    <input type="checkbox" id="edit_is_active" name="is_active" class="h-4 w-4 accent-violet-600 cursor-pointer">
                    <span class="text-sm text-slate-700">Active — available when adding other services</span>
                </label>

            </div>

            <div class="flex justify-end gap-3 px-5 py-4 bg-slate-50">
                <button type="button" class="modal-close-edit rounded-xl border border-slate-200 bg-white text-slate-700 px-4 py-2 text-sm font-semibold">Cancel</button>
                <button type="button" id="editSubmit" data-action=""
                    class="rounded-xl bg-violet-600 text-white px-5 py-2 text-sm font-semibold">
                    <i class="fas fa-save mr-1"></i> Update Type
                </button>
            </div>
        </form>
    </div>
</div>
