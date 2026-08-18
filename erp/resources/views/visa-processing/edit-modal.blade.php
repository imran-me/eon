{{-- ══════════════════════════════════════
     VISA PROCESSING — EDIT MODAL
══════════════════════════════════════ --}}
<div id="editModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black/50 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl z-10 flex flex-col max-h-[92vh]">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 bg-violet-600 rounded-t-2xl flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                    <i class="fas fa-edit text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Edit Visa Application</h3>
                    <p class="text-xs text-violet-100">Update the application details below</p>
                </div>
            </div>
            <button class="modal-close-edit w-8 h-8 flex items-center justify-center rounded-lg bg-white/20 hover:bg-white/30 text-white transition">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        {{-- Scrollable Body --}}
        <div class="overflow-y-auto flex-1 min-h-0">
            <form id="editForm" method="POST" enctype="multipart/form-data"
                action="{{ route('role.visa.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'visa' => 1]) }}">
                @csrf
                @method('PUT')
                <input type="hidden" id="editItemId" name="id">

                <div class="px-6 py-5 space-y-5">

                    {{-- ── Section 1: Destination & Type ── --}}
                    <div>
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-6 h-6 rounded-lg bg-violet-100 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-globe text-violet-600 text-[10px]"></i>
                            </div>
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Destination &amp; Type</p>
                            <div class="flex-1 border-t border-gray-100"></div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                                    Destination Country <span class="text-red-500">*</span>
                                </label>
                                <select id="edit_country_id" name="country_id"
                                    class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 select2"
                                    style="width:100%">
                                    <option value="">— Select Country —</option>
                                    @foreach ($countries as $country)
                                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                                    Visa Category <span class="text-red-500">*</span>
                                </label>
                                <select id="edit_visa_category_id" name="visa_category_id"
                                    class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 select2"
                                    style="width:100%">
                                    <option value="">— Select Category —</option>
                                    @foreach ($visaCategories as $vc)
                                        <option value="{{ $vc->id }}" data-country="{{ $vc->country_id }}">{{ $vc->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Visa Type</label>
                                <input type="text" id="e_visa_type_display" readonly placeholder="Auto-filled from category"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-100 text-gray-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Avg. Processing Days</label>
                                <input type="text" id="e_avg_processing_days_display" readonly placeholder="—"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-100 text-gray-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Duration of Stay</label>
                                <input type="text" id="edit_duration" name="duration" placeholder="e.g. 30 days, 1 year"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Travel Date (planned)</label>
                                <input type="date" id="edit_travel_date" name="travel_date"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Status</label>
                                <select id="edit_status" name="status"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition">
                                    <option value="pending">NEW</option>
                                    <option value="received">DOC COLLECTION</option>
                                    <option value="in_embassy">IN EMBASSY</option>
                                    <option value="approved">APPROVED</option>
                                    <option value="delivered">DELIVERED</option>
                                    <option value="rejected">REJECTED</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Stage</label>
                                <select id="edit_stage" name="stage"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition">
                                    <option value="">— Select Stage —</option>
                                    @foreach (\App\Models\VisaProcess::$stages as $stage)
                                        <option value="{{ $stage }}">{{ $stage }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- ── Section 2: Applicant ── --}}
                    <div>
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-6 h-6 rounded-lg bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-user text-indigo-600 text-[10px]"></i>
                            </div>
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Applicant</p>
                            <div class="flex-1 border-t border-gray-100"></div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                                Passport Holder <span class="text-red-500">*</span>
                            </label>
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <select id="edit_passport_holder_id" name="passport_holder_id"
                                        class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 select2 vp-ph-select"
                                        style="width:100%">
                                        <option value="">— Select / Search Passport Holder —</option>
                                        @foreach ($passportHolders as $ph)
                                            <option value="{{ $ph->id }}">{{ $ph->name }}{{ $ph->phone ? ' · '.$ph->phone : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="button" onclick="openVpPhModal('edit_passport_holder_id')"
                                    class="vp-ph-add-btn inline-flex items-center gap-1.5 px-3 py-2.5 text-xs font-semibold text-white bg-violet-600 rounded-xl hover:bg-violet-700 transition whitespace-nowrap flex-shrink-0">
                                    <i class="fas fa-plus text-[10px]"></i> New
                                </button>
                            </div>

                            {{-- Holder Info Card --}}
                            <div id="e_holder_info" class="hidden mt-2">
                                <div class="p-3 bg-violet-50 border border-violet-100 rounded-xl grid grid-cols-3 gap-3 text-xs text-violet-800">
                                    <div><span class="font-semibold block text-violet-500 mb-0.5">Passport No</span><span id="e_holder_passport" class="font-mono">—</span></div>
                                    <div><span class="font-semibold block text-violet-500 mb-0.5">Phone</span><span id="e_holder_phone">—</span></div>
                                    <div><span class="font-semibold block text-violet-500 mb-0.5">Nationality</span><span id="e_holder_nationality">—</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ── Section 3: Pricing ── --}}
                    <div>
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-6 h-6 rounded-lg bg-emerald-100 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-money-bill-wave text-emerald-600 text-[10px]"></i>
                            </div>
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Pricing</p>
                            <div class="flex-1 border-t border-gray-100"></div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Costing Price (৳)</label>
                                <input type="number" step="0.01" min="0" id="e_costing_price" name="costing_price"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition"
                                    placeholder="0" oninput="calcEditTotal()">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Vendor (payable to)</label>
                                <select id="edit_vendor_id" name="vendor_id" onchange="visaOnVendorChange(this)"
                                    class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 select2"
                                    style="width:100%">
                                    <option value="">— No Vendor / Unassigned —</option>
                                    @foreach ($vendors as $vendor)
                                        <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Portal</label>
                                <select id="edit_portal_id" name="portal_id" onchange="visaOnPortalChange(this)"
                                    class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 select2"
                                    style="width:100%">
                                    <option value="">— No Portal —</option>
                                    @foreach ($portals as $portal)
                                        <option value="{{ $portal->id }}">{{ $portal->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Purchase Date</label>
                                <input type="date" id="edit_purchase_date" name="purchase_date"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Cost Paid (৳)</label>
                                <input type="number" step="0.01" min="0" id="edit_cost_paid_amount" name="cost_paid_amount"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition"
                                    placeholder="0">
                            </div>
                            <div id="edit_cost_bank_wrap">
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Cost Bank</label>
                                <select id="edit_cost_bank_id" name="cost_bank_id"
                                    class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 select2"
                                    style="width:100%">
                                    <option value="">— Select Bank —</option>
                                    @foreach ($banks as $bank)
                                        <option value="{{ $bank->id }}">{{ $bank->name }} — {{ $bank->account_number }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Sale Price (৳)</label>
                                <input type="number" step="0.01" min="0" id="e_sale_price" name="sale_price"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition"
                                    placeholder="0" oninput="calcEditTotal()">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Advance Received (৳)</label>
                                <input type="number" step="0.01" min="0" id="e_advance" name="advance_received"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition"
                                    placeholder="0" oninput="calcEditTotal()">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Due Amount (auto)</label>
                                <input type="text" id="e_due" readonly
                                    class="w-full px-4 py-2.5 text-sm border border-red-100 rounded-xl bg-red-50 font-bold text-red-600" value="৳ 0">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Assigned Officer</label>
                                <select id="edit_assigned_officer_id" name="assigned_officer_id"
                                    class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 select2"
                                    style="width:100%">
                                    <option value="">— Direct / Unassigned —</option>
                                    @foreach ($officers as $officer)
                                        <option value="{{ $officer->id }}">{{ $officer->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- ── Section 4: Payment Schedule ── --}}
                    <div>
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-6 h-6 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-calendar-check text-blue-600 text-[10px]"></i>
                            </div>
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Payment Schedule</p>
                            <div class="flex-1 border-t border-gray-100"></div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2" id="edit_cost_sched_wrap">
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                                    Cost Due Date <span class="normal-case font-normal text-gray-400 ml-1">(payable schedule)</span>
                                </label>
                                <input type="date" id="edit_payable_date" name="payable_date"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Payment Status</label>
                                <select id="edit_payment_status" name="payment_status"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition">
                                    <option value="pending">Pending</option>
                                    <option value="partial">Partial</option>
                                    <option value="paid">Paid</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- ── Section 5: Remarks & Attachments ── --}}
                    <div>
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-6 h-6 rounded-lg bg-amber-100 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-paperclip text-amber-600 text-[10px]"></i>
                            </div>
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Remarks &amp; Attachments</p>
                            <div class="flex-1 border-t border-gray-100"></div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Remarks</label>
                                <textarea id="edit_remarks" name="remarks" rows="3"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition resize-none"
                                    placeholder="Optional notes..."></textarea>
                            </div>

                            {{-- Existing Attachments --}}
                            <div>
                                <button type="button" id="edit_existing_attachments_toggle"
                                    class="flex items-center gap-1.5 text-xs font-semibold text-gray-500 hover:text-violet-600 transition mb-1.5">
                                    <i class="fas fa-chevron-down text-[10px]" id="edit_existing_attachments_icon"></i>
                                    Existing Attachments
                                </button>
                                <div id="edit_existing_attachments" class="space-y-1.5 hidden"></div>
                            </div>

                            {{-- New Attachments --}}
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Add New Files</label>
                                <div id="edit_attachments_wrapper" class="space-y-2"></div>
                                <button type="button" id="edit_add_attachment"
                                    class="mt-2 inline-flex items-center gap-1.5 text-xs text-violet-600 hover:text-violet-700 font-medium transition">
                                    <i class="fas fa-plus text-[10px]"></i> Add File
                                </button>
                                <p class="text-xs text-gray-400 mt-1">jpg, jpeg, png, pdf, doc, docx · max 10 MB each</p>
                            </div>
                        </div>
                    </div>

                </div>{{-- /px-6 py-5 --}}
            </form>
        </div>{{-- /overflow-y-auto --}}

        {{-- Footer --}}
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl flex-shrink-0">
            <button type="button" class="modal-close-edit px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition">
                Cancel
            </button>
            <button id="editSubmit" type="button"
                data-action="{{ route('role.visa.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'visa' => 1]) }}"
                class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold text-white bg-violet-600 rounded-xl hover:bg-violet-700 transition shadow-sm">
                <i class="fas fa-save text-xs"></i> Update Application
            </button>
        </div>

    </div>{{-- /modal card --}}
</div>

<script>
function calcEditTotal() {
    const sale    = parseFloat(document.getElementById('e_sale_price')?.value) || 0;
    const advance = parseFloat(document.getElementById('e_advance')?.value)    || 0;
    const due     = Math.max(0, sale - advance);
    document.getElementById('e_due').value = '৳ ' + due.toLocaleString('en-BD', { minimumFractionDigits: 0 });
}
</script>
