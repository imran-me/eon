<div id="detailsModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-full mx-3 sm:mx-auto sm:w-11/12 md:max-w-2xl rounded-lg shadow-lg z-50 flex flex-col overflow-hidden" style="max-height:92vh;">
        <div class="modal-content py-4 text-left px-4 sm:px-6 flex flex-col flex-1 min-h-0">
            <div class="modal-header flex justify-between items-center pb-3 border-b">
                <h3 class="text-lg font-semibold flex items-center gap-2">
                    <i class="fas fa-eye text-blue-600"></i> Visa Application Details
                    <span id="d_application_id" class="text-xs font-mono font-normal text-gray-400"></span>
                </h3>
                <button class="modal-close-details z-50 text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>

            <div class="modal-body overflow-y-auto flex-1 min-h-0 pt-4">
                <div id="d_loading" class="text-center text-sm text-gray-400 py-6">
                    <i class="fas fa-spinner fa-spin"></i> Loading...
                </div>

                <div id="d_content" class="hidden">
                    {{-- Section 1: Destination & Type --}}
                    <div class="mb-1 text-xs font-bold text-blue-700 uppercase tracking-wide border-b border-blue-100 pb-1">
                        🌍 Destination &amp; Type
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4 mt-2 text-sm">
                        <div><span class="text-gray-500 text-xs block">Country</span><span id="d_country" class="font-medium">—</span></div>
                        <div><span class="text-gray-500 text-xs block">Visa Category</span><span id="d_visa_category" class="font-medium">—</span></div>
                        <div><span class="text-gray-500 text-xs block">Visa Type</span><span id="d_visa_type" class="font-medium">—</span></div>
                        <div><span class="text-gray-500 text-xs block">Duration</span><span id="d_duration" class="font-medium">—</span></div>
                        <div><span class="text-gray-500 text-xs block">Travel Date</span><span id="d_travel_date" class="font-medium">—</span></div>
                        <div><span class="text-gray-500 text-xs block">Status</span><span id="d_status" class="font-medium">—</span></div>
                        <div><span class="text-gray-500 text-xs block">Stage</span><span id="d_stage" class="font-medium">—</span></div>
                    </div>

                    {{-- Section 2: Applicant --}}
                    <div class="mb-1 text-xs font-bold text-blue-700 uppercase tracking-wide border-b border-blue-100 pb-1">
                        👤 Applicant
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4 mt-2 text-sm">
                        <div><span class="text-gray-500 text-xs block">Name</span><span id="d_holder_name" class="font-medium">—</span></div>
                        <div><span class="text-gray-500 text-xs block">Passport No</span><span id="d_holder_passport" class="font-medium font-mono">—</span></div>
                        <div><span class="text-gray-500 text-xs block">Phone</span><span id="d_holder_phone" class="font-medium">—</span></div>
                        <div><span class="text-gray-500 text-xs block">Nationality</span><span id="d_holder_nationality" class="font-medium">—</span></div>
                    </div>

                    {{-- Section 3: Pricing --}}
                    <div class="mb-1 text-xs font-bold text-blue-700 uppercase tracking-wide border-b border-blue-100 pb-1">
                        💰 Pricing
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4 mt-2 text-sm">
                        <div><span class="text-gray-500 text-xs block">Costing Price</span><span id="d_costing_price" class="font-medium">৳ 0</span></div>
                        <div><span class="text-gray-500 text-xs block">Sale Price</span><span id="d_sale_price" class="font-semibold text-blue-700">৳ 0</span></div>
                        <div><span class="text-gray-500 text-xs block">Advance Received</span><span id="d_advance" class="font-medium">৳ 0</span></div>
                        <div><span class="text-gray-500 text-xs block">Due</span><span id="d_due" class="font-semibold text-red-600">৳ 0</span></div>
                        <div class="col-span-1 sm:col-span-2"><span class="text-gray-500 text-xs block">Assigned Officer</span><span id="d_officer" class="font-medium">—</span></div>
                    </div>

                    {{-- Section 3b: Payment Schedule --}}
                    <div class="mb-1 text-xs font-bold text-blue-700 uppercase tracking-wide border-b border-blue-100 pb-1">
                        💳 Payment Schedule
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4 mt-2 text-sm">
                        <div><span class="text-gray-500 text-xs block">Payable Date</span><span id="d_payable_date" class="font-medium">—</span></div>
                        <div><span class="text-gray-500 text-xs block">Payment Status</span><span id="d_payment_status">—</span></div>
                    </div>

                    {{-- Remarks --}}
                    <div class="mb-4">
                        <span class="text-gray-500 text-xs block mb-1">Remarks</span>
                        <p id="d_remarks" class="text-sm text-gray-700">—</p>
                    </div>

                    {{-- Documents Checklist --}}
                    <div class="mb-2">
                        <div class="mb-1 text-xs font-bold text-blue-700 uppercase tracking-wide border-b border-blue-100 pb-1">
                            📋 Document Checklist
                        </div>
                        <div id="d_documents" class="mt-2 flex flex-wrap gap-1"></div>
                    </div>

                    {{-- Attachments --}}
                    <div class="mb-2">
                        <div class="mb-1 text-xs font-bold text-blue-700 uppercase tracking-wide border-b border-blue-100 pb-1">
                            📎 Attachments
                        </div>
                        <div id="d_attachments" class="space-y-1 mt-2"></div>
                    </div>

                    <div class="text-right text-xs text-gray-400 mt-2" id="d_created_at"></div>
                </div>
            </div>

            <div class="modal-footer flex flex-col-reverse sm:flex-row justify-end pt-3 border-t gap-2">
                <button type="button" class="w-full sm:w-auto px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 text-sm modal-close-details">Close</button>
            </div>
        </div>
    </div>
</div>
