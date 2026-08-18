<div id="payVendorModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">Pay Vendor</h3>
                <button class="modal-close-pay-vendor z-50">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body space-y-3">
                <p class="text-sm text-gray-600">
                    <span id="pv_application_id" class="font-mono font-semibold"></span> —
                    <span id="pv_vendor_name" class="font-semibold"></span>,
                    due ৳<span id="pv_due_amount" class="font-semibold text-red-600"></span>
                </p>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Bank</label>
                    <select id="pv_bank_id" class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50">
                        <option value="">— Select Bank —</option>
                        @foreach ($banks as $bank)
                            <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Payment Date</label>
                    <input type="date" id="pv_payment_date" value="{{ now()->toDateString() }}"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Payment Amount (৳)</label>
                    <input type="number" step="0.01" min="0.01" id="pv_payment_amount"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Reference No (optional)</label>
                    <input type="text" id="pv_reference_no"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50">
                </div>
                <p id="pv_error" class="text-red-500 text-xs hidden"></p>
            </div>
            <div class="modal-footer flex justify-end pt-3">
                <button class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-pay-vendor">
                    Cancel
                </button>
                <button id="pv_submit_btn" class="btn btn-primary px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-200">
                    Record Payment
                </button>
            </div>
        </div>
    </div>
</div>
