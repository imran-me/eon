<div id="editModal" class="fixed inset-0 z-[9999] bg-black/50 hidden [&:not(.hidden)]:flex items-start justify-center p-6 overflow-y-auto">
    <div class="w-full max-w-xl bg-white rounded-2xl overflow-hidden shadow-2xl">
        <div class="flex items-center gap-3 bg-gradient-to-r from-violet-600 to-indigo-500 px-5 py-4">
            <div class="h-10 w-10 rounded-xl bg-white/20 flex items-center justify-center text-white flex-shrink-0">
                <i class="fas fa-edit"></i>
            </div>
            <div class="flex-1">
                <h2 class="text-sm font-bold text-white">Edit Visa Category</h2>
                <p class="text-xs text-violet-100 mt-0.5">Update the details for this category</p>
            </div>
            <button type="button" class="modal-close-edit ml-auto h-8 w-8 rounded-full bg-white/20 border-0 text-white cursor-pointer flex items-center justify-center">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>

        <form id="editForm" method="POST" enctype="multipart/form-data"
            action="{{ route('role.visa-category.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'visa_category' => 1]) }}">
            @csrf
            @method('PUT')
            <input type="hidden" id="editItemId" name="id">

            <div class="p-5 space-y-4 max-h-[calc(100vh-160px)] overflow-y-auto">

                {{-- Basic Info --}}
                <div class="mb-3 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                    <i class="fas fa-info-circle"></i> Basic Info
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Country</label>
                        <select id="edit_country_id" name="country_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500 select2-vc-edit" style="width:100%">
                            <option value="">— Select Country —</option>
                            @foreach($countries as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Category Name <sup class="text-red-500">*</sup></label>
                        <input type="text" id="edit_name" name="name" required placeholder="e.g. Tourist Visa"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Visa Type</label>
                        <select id="edit_visa_type" name="visa_type"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500 bg-white">
                            <option value="">— Select Type —</option>
                            <option>Single Entry</option>
                            <option>Multiple Entry</option>
                            <option>eVisa</option>
                            <option>Visa on Arrival</option>
                            <option>Umrah / Hajj</option>
                            <option>Transit</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Avg. Processing Days</label>
                        <input type="text" id="edit_avg_processing_days" name="avg_processing_days" placeholder="e.g. 5–7"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                        <select id="edit_is_active" name="is_active"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500 bg-white">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>

                {{-- Pricing --}}
                <div class="mb-3 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                    <i class="fas fa-tag"></i> Pricing
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Costing Price (৳) <sup class="text-red-500">*</sup></label>
                        <input type="number" step="0.01" min="0" id="edit_costing_price" name="costing_price" required
                            oninput="calcVcMargin('e')" placeholder="0.00"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Sale Price (৳) <sup class="text-red-500">*</sup></label>
                        <input type="number" step="0.01" min="0" id="edit_sale_price" name="sale_price" required
                            oninput="calcVcMargin('e')" placeholder="0.00"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
                    </div>
                    <div class="col-span-2">
                        <div id="e_margin_display" style="display:none" class="rounded-xl px-4 py-2 text-sm font-medium flex items-center gap-2">
                            <i class="fas fa-chart-line"></i>
                            <span id="e_margin_text"></span>
                        </div>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="mb-3 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                    <i class="fas fa-sticky-note"></i> Notes
                </div>
                <textarea id="edit_description" name="description" rows="2" placeholder="Requirements, restrictions, or additional info..."
                    class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500"></textarea>
            </div>

            <div class="flex justify-end gap-3 px-5 py-4 bg-slate-50">
                <button type="button" class="modal-close-edit rounded-xl border border-slate-200 bg-white text-slate-700 px-4 py-2 text-sm font-semibold">Cancel</button>
                <button data-action="{{ route('role.visa-category.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'visa_category' => 1]) }}"
                    id="editSubmit" type="button"
                    class="rounded-xl bg-violet-600 text-white px-5 py-2 text-sm font-semibold">
                    <i class="fas fa-check mr-1"></i> Update Category
                </button>
            </div>
        </form>
    </div>
</div>
