<div id="createModal" class="fixed inset-0 z-[9999] bg-black/50 hidden [&:not(.hidden)]:flex items-start justify-center p-6 overflow-y-auto">
    <div class="w-full max-w-2xl bg-white rounded-2xl overflow-hidden shadow-2xl">
        <div class="flex items-center gap-3 bg-gradient-to-r from-indigo-600 to-blue-500 px-5 py-4">
            <div class="h-10 w-10 rounded-xl bg-white/20 flex items-center justify-center text-white flex-shrink-0">
                <i class="fas fa-plus"></i>
            </div>
            <div class="flex-1">
                <h2 class="text-sm font-bold text-white">Add Other Service</h2>
                <p class="text-xs text-indigo-100 mt-0.5">Link a new additional service to a passenger</p>
            </div>
            <button type="button" class="modal-close-create ml-auto h-8 w-8 rounded-full bg-white/20 border-0 text-white cursor-pointer flex items-center justify-center">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>

        <div class="p-5 max-h-[calc(100vh-140px)] overflow-y-auto">
            <div id="createModalErr" class="hidden rounded-xl bg-red-50 px-3 py-2 text-xs text-red-600 mb-4"></div>
            <div class="rounded-xl bg-blue-50 px-3 py-2.5 text-xs text-blue-700 mb-4">
                <i class="fas fa-lightbulb mr-1"></i>
                কোন Passenger-এর জন্য এই সার্ভিস সেটা সিলেক্ট করুন — voucher / invoice বানানোর সময় এটি অটোমেটিক লিংক হবে।
            </div>
            <form id="createForm" action="{{ route('role.other-visa-services.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST">
                @csrf
                <div class="grid grid-cols-2 gap-3">

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Passenger / Applicant <sup class="text-red-500">*</sup></label>
                        <select id="create_passport_holder_id" name="passport_holder_id" class="select2" style="width:100%">
                            <option value="">— Select Passenger —</option>
                            @foreach ($passportHolders as $ph)
                                <option value="{{ $ph->id }}">{{ $ph->name }}</option>
                            @endforeach
                        </select>
                        <p id="create_passport_holder_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select a passenger</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Destination Country</label>
                        <select id="create_country_id" name="country_id" class="select2" style="width:100%">
                            <option value="">— Select Country —</option>
                            @foreach ($countries as $country)
                                <option value="{{ $country->id }}">{{ $country->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Service Type <sup class="text-red-500">*</sup></label>
                        <select id="create_other_service_type_id" name="other_service_type_id" class="select2" style="width:100%">
                            <option value="">— Select Service Type —</option>
                            @foreach ($serviceTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                        <p id="create_other_service_type_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select a service type</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Assigned Officer</label>
                        <select id="create_assigned_officer_id" name="assigned_officer_id" class="select2" style="width:100%">
                            <option value="">— Select Officer —</option>
                            @foreach ($officers as $officer)
                                <option value="{{ $officer->id }}">{{ $officer->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Cost Price (৳) <sup class="text-red-500">*</sup></label>
                        <input type="number" id="create_cost_price" name="cost_price" min="0" step="0.01" value="0" placeholder="0.00"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
                        <p id="create_cost_price_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please enter the cost price</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Sales Price (৳) <sup class="text-red-500">*</sup></label>
                        <input type="number" id="create_sale_price" name="sale_price" min="0" step="0.01" placeholder="0.00"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
                        <p id="create_sale_price_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please enter the sales price</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Deadline</label>
                        <input type="date" id="create_deadline" name="deadline"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                        <select id="create_status" name="status" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
                            <option value="pending" selected>Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="done">Done</option>
                            <option value="overdue">Overdue</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div class="col-span-2">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
                        <textarea id="create_notes" name="notes" rows="2" placeholder="Appointment date, embassy, document list..."
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500"></textarea>
                    </div>

                    <div class="col-span-2">
                        <label class="flex items-center gap-2 rounded-xl bg-slate-50 px-4 py-3 cursor-pointer">
                            <input type="checkbox" id="create_is_billable" name="is_billable" checked class="h-4 w-4 accent-blue-600 cursor-pointer">
                            <span class="text-sm text-slate-700">Billable — add this service to passenger's voucher / invoice</span>
                        </label>
                    </div>

                </div>
            </form>
        </div>

        <div class="flex justify-end gap-2 bg-slate-50 px-5 py-3">
            <button type="button" class="modal-close-create rounded-xl border border-slate-200 bg-white text-slate-700 px-4 py-2 text-sm font-semibold">Cancel</button>
            <button id="createSubmit" type="button" class="rounded-xl bg-blue-600 text-white px-5 py-2 text-sm font-semibold flex items-center gap-1.5">
                <i class="fas fa-save text-xs"></i> Save Service
            </button>
        </div>
    </div>
</div>
