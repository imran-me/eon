<div id="editPresetModal" class="fixed inset-0 z-[9000] bg-slate-950/50 [&:not(.hidden)]:flex items-start justify-center p-6 overflow-auto hidden">
    <div class="w-full max-w-3xl bg-white rounded-xl overflow-hidden shadow-2xl">
        <div class="flex items-center gap-3 bg-violet-700 text-white px-5 py-4">
            <div class="h-9 w-9 rounded-lg bg-white/20 flex items-center justify-center flex-shrink-0"><i class="fas fa-edit"></i></div>
            <div class="flex-1">
                <h3 class="text-sm font-bold">Edit Price Preset</h3>
                <p class="text-xs opacity-80">Update automatic pricing configuration</p>
            </div><button type="button" class="ml-auto h-7 w-7 rounded-lg bg-white/20 border-0 text-white cursor-pointer modal-close-edit">&times;</button>
        </div>
        <div class="p-5">
            <form id="editPresetForm" method="POST">@csrf @method('PUT')
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-xs font-semibold text-slate-500 mb-1">Airline *</label><select id="edit_preset_airline_id" name="airline_id" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">Select airline</option>@foreach($airlines as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach
                        </select><p id="edit_preset_airline_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select an airline.</p></div>
                    <div><label class="block text-xs font-semibold text-slate-500 mb-1">Category</label><select id="edit_preset_flight_category_id" name="flight_category_id" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">Any category</option>@foreach($categories as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach
                        </select></div>
                    <div><label class="block text-xs font-semibold text-slate-500 mb-1">Category Type</label><select id="edit_preset_flight_category_type_id" name="flight_category_type_id" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">Any type</option>@foreach($types as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach
                        </select></div>
                    <div><label class="block text-xs font-semibold text-slate-500 mb-1">Ticket Class *</label><select id="edit_preset_ticket_class" name="ticket_class" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            <option value="economy">Economy</option>
                            <option value="business">Business</option>
                            <option value="first">First Class</option>
                        </select></div>
                    <div><label class="block text-xs font-semibold text-slate-500 mb-1">Handling Type *</label><select id="edit_preset_handling_type" name="handling_type" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            <option value="manpower_wise">Manpower-wise</option>
                            <option value="immigration_wise">Immigration-wise</option>
                        </select></div>
                    <div><label class="block text-xs font-semibold text-slate-500 mb-1">Status *</label><select id="edit_preset_status" name="status" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select></div>
                    @foreach(['ticket_cost'=>'Ticket Cost','manpower_cost'=>'Manpower Cost','boarding_cost'=>'Boarding Cost','immigration_cost'=>'Immigration Cost','sale_price'=>'Sale Price'] as $key=>$label)
                    <div><label class="block text-xs font-semibold text-slate-500 mb-1">{{ $label }} (BDT) *</label><input type="number" id="edit_preset_{{ $key }}" name="{{ $key }}" min="0" step="0.01" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"><p id="edit_preset_{{ $key }}_msg" class="text-red-500 text-xs mt-1 hidden error-message">{{ $label }} is required and cannot be negative.</p></div>
                    @endforeach
                </div>
            </form>
        </div>
        <div class="flex justify-end gap-2 bg-slate-50 px-5 py-3"><button type="button" class="rounded-lg border px-4 py-2 text-xs font-semibold cursor-pointer modal-close-edit">Cancel</button><button type="button" id="editPresetSubmit" class="rounded-lg bg-blue-600 text-white px-4 py-2 text-xs font-semibold cursor-pointer"><i class="fas fa-save mr-1"></i>Update Preset</button></div>
    </div>
</div>
