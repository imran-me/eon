<div id="editModal" class="fixed inset-0 z-[9000] bg-black/50 [&:not(.hidden)]:flex items-start justify-center p-6 overflow-y-auto hidden modal-backdrop">
    <div class="w-full max-w-2xl bg-white rounded-2xl overflow-hidden shadow-2xl">
        <div class="flex items-center gap-3 bg-gradient-to-br from-violet-600 to-violet-700 px-5 py-4">
            <div class="h-10 w-10 rounded-xl bg-white/20 flex items-center justify-center text-white text-base flex-shrink-0"><i class="fas fa-pencil-alt"></i></div>
            <div>
                <div class="text-sm font-bold text-white">Edit Flight Category</div>
                <div class="text-xs text-blue-100 mt-0.5">Update seat config, base fare & airline tie-ups</div>
            </div>
            <button type="button" class="ml-auto h-8 w-8 rounded-xl bg-white/20 border-0 text-white cursor-pointer flex items-center justify-center modal-close-edit">&#10005;</button>
        </div>
        <div class="p-5 max-h-[calc(100vh-220px)] overflow-y-auto">
            <form id="editeForm" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" id="editItemId" name="id">

                {{-- Category Info --}}
                <div class="mb-5 last:mb-0">
                    <div class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-3 pb-2"><i class="fas fa-tag mr-1.5"></i> Category Info</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-full">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Category Name <sup>*</sup></label>
                            <input type="text" id="edit_name" name="name" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:border-blue-500 outline-none" placeholder="e.g. Umrah Group">
                            <p id="edit_name_msg" class="text-red-500 text-xs mt-1 hidden error-message">Name is required.</p>
                        </div>
                        <div class="col-span-full">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Category Type</label>
                            <div style="display:grid;grid-template-columns:1fr 82px;gap:8px;">
                                <select id="edit_flight_category_type_id" name="flight_category_type_id" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:border-blue-500 outline-none category-type-select">
                                    <option value="">Select category type</option>
                                    @foreach($categoryTypes as $type)<option value="{{ $type->id }}" data-fare="{{ $type->base_fare }}">{{ $type->name }}</option>@endforeach
                                </select>
                                <button type="button" class="rounded-xl border bg-slate-100 text-slate-700 px-4 py-2 text-sm font-semibold cursor-pointer inline-flex items-center gap-1.5 quick-type-btn" style="padding:0;justify-content:center;"><i class="fas fa-plus"></i> New</button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Code</label>
                            <input type="text" id="edit_code" name="code" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:border-blue-500 outline-none" placeholder="e.g. CAT-UMR">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Icon <span class="text-gray-400 font-normal">(FontAwesome class)</span></label>
                            <input type="text" id="edit_icon" name="icon" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:border-blue-500 outline-none" placeholder="fa-kaaba">
                        </div>
                    </div>
                </div>

                {{-- Route & Capacity --}}
                <div class="mb-5 last:mb-0">
                    <div class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-3 pb-2"><i class="fas fa-route mr-1.5"></i> Route &amp; Capacity</div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-full">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Typical Route(s)</label>
                            <input type="text" id="edit_typical_routes" name="typical_routes" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:border-blue-500 outline-none" placeholder="e.g. DAC → JED / MED">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Default Seats</label>
                            <input type="number" id="edit_default_seats" name="default_seats" min="0" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:border-blue-500 outline-none" placeholder="0">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Base Fare (৳)</label>
                            <input type="number" id="edit_base_fare" name="base_fare" min="0" step="0.01" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:border-blue-500 outline-none" placeholder="0.00">
                        </div>
                    </div>
                </div>

                {{-- Airlines & Status --}}
                <div class="mb-5 last:mb-0">
                    <div class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-3 pb-2"><i class="fas fa-plane mr-1.5"></i> Airlines &amp; Status</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-full">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Airlines</label>
                            <select id="edit_airline_ids" name="airline_ids[]" multiple style="width:100%">
                                @foreach ($airlines as $airline)
                                    <option value="{{ $airline->id }}">{{ $airline->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-full">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                            <select id="edit_status" name="status" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:border-blue-500 outline-none">
                                <option value="active">Active</option>
                                <option value="seasonal">Seasonal</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="flex justify-end gap-2 bg-slate-50 px-5 py-3">
            <button type="button" class="rounded-xl border bg-slate-100 text-slate-700 px-4 py-2 text-sm font-semibold cursor-pointer inline-flex items-center gap-1.5 modal-close-edit">&#10005; Cancel</button>
            <button id="editSubmit" type="button" class="rounded-xl bg-violet-600 text-white px-4 py-2 text-sm font-semibold cursor-pointer inline-flex items-center gap-1.5"><i class="fas fa-save"></i> Update Category</button>
        </div>
    </div>
</div>
