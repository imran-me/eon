<div id="editModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black/50 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl z-10 flex flex-col max-h-[92vh]">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 bg-violet-600 rounded-t-2xl flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                    <i class="fas fa-pencil-alt text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Edit Bank</h3>
                    <p class="text-xs text-violet-100">Update account details</p>
                </div>
            </div>
            <button class="modal-close-edit w-8 h-8 flex items-center justify-center rounded-lg bg-white/20 hover:bg-white/30 text-white transition">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        {{-- Scrollable Body --}}
        <div class="overflow-y-auto flex-1 min-h-0">
            <form id="editForm" method="POST" enctype="multipart/form-data"
                action="{{ route('role.banks.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'bank' => 1]) }}">
                @csrf
                @method('PUT')
                <input type="hidden" id="editItemId" name="id">

                <div class="px-6 py-5 space-y-5">

                    {{-- Section: Bank Details --}}
                    <div>
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-6 h-6 rounded-lg bg-violet-100 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-university text-violet-600 text-[10px]"></i>
                            </div>
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Bank Details</p>
                            <div class="flex-1 border-t border-gray-100"></div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label for="edit_name" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                                    Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="edit_name" name="name" placeholder="Enter Name"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition">
                                <p id="edit_name_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Name</p>
                            </div>
                            <div>
                                <label for="edit_company_id" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                                    Company <span class="text-red-500">*</span>
                                </label>
                                <select id="edit_company_id" name="company_id" required
                                    class="w-full text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 select2" style="width:100%">
                                    <option value="">-- Select Company --</option>
                                    @foreach ($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                                    @endforeach
                                </select>
                                <p id="edit_company_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select a company</p>
                            </div>
                            <div>
                                <label for="edit_type" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                                    Payment Type <span class="text-red-500">*</span>
                                </label>
                                <select id="edit_type" name="type" required
                                    class="w-full text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 select2" style="width:100%">
                                    <option value="">-- Select Type --</option>
                                    <option value="bank">Bank</option>
                                    <option value="cash">Cash</option>
                                    <option value="mobile_banking">Mobile Banking</option>
                                    <option value="digital_wallet">Digital Wallet</option>
                                </select>
                                <p id="edit_type_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select a payment type</p>
                            </div>
                            <div id="edit_mobile_providers" class="hidden sm:col-span-2">
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Quick Pick</label>
                                <div class="flex gap-2">
                                    <button type="button" data-provider="bKash" class="mobile-provider-btn px-4 py-2 text-sm font-semibold rounded-xl border border-gray-200 bg-gray-50 hover:bg-pink-50 hover:border-pink-300 transition">bKash</button>
                                    <button type="button" data-provider="Nagad" class="mobile-provider-btn px-4 py-2 text-sm font-semibold rounded-xl border border-gray-200 bg-gray-50 hover:bg-orange-50 hover:border-orange-300 transition">Nagad</button>
                                    <button type="button" data-provider="Rocket" class="mobile-provider-btn px-4 py-2 text-sm font-semibold rounded-xl border border-gray-200 bg-gray-50 hover:bg-purple-50 hover:border-purple-300 transition">Rocket</button>
                                </div>
                            </div>
                            <div id="edit_field_branch_name">
                                <label for="edit_branch_name" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Branch Name</label>
                                <input type="text" id="edit_branch_name" name="branch_name" placeholder="Enter branch name"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition">
                            </div>
                            <div id="edit_field_bank_type">
                                <label for="edit_bank_type" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                                    Bank Type <span class="text-red-500">*</span>
                                </label>
                                <select id="edit_bank_type" name="bank_type"
                                    class="w-full text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 select2" style="width:100%">
                                    <option value="">-- Select Type --</option>
                                    <option value="national">National</option>
                                    <option value="international">International</option>
                                </select>
                                <p id="edit_bank_type_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select a bank type</p>
                            </div>
                            <div class="flex items-end pb-2.5">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="status" id="edit_status" class="h-4 w-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500" checked>
                                    <span class="text-sm font-medium text-gray-700">Active</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Section: Account Info --}}
                    <div>
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-6 h-6 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-book text-blue-600 text-[10px]"></i>
                            </div>
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Account Info</p>
                            <div class="flex-1 border-t border-gray-100"></div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label for="edit_account_id" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                                    Ledger Account <span class="text-red-500">*</span>
                                </label>
                                <select id="edit_account_id" name="account_id" required
                                    class="w-full text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 select2" style="width:100%">
                                    <option value="">-- Select --</option>
                                    @foreach ($accounts as $account)
                                    {{-- Code first: several accounts share a name (Office Cash / OFFICE CASH),
                                         and the code is the only thing that tells them apart. --}}
                                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }} ({{ $account->type }})</option>
                                    @endforeach
                                </select>
                                <p class="text-amber-600 text-[11px] mt-1">Changing this relinks the bank directly to that account and moves its whole transaction history over — use only to fix a wrong/broken link.</p>
                                <p id="edit_account_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select a ledger account</p>
                            </div>
                            <div>
                                <label for="edit_account_name" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                                    Account Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="edit_account_name" name="account_name" placeholder="Enter account name"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition">
                                <p id="edit_account_name_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please enter your account name</p>
                            </div>
                            <div id="edit_field_account_type">
                                <label for="edit_account_type" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                                    Account Type <span class="text-red-500">*</span>
                                </label>
                                <select id="edit_account_type" name="account_type"
                                    class="w-full text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 select2" style="width:100%">
                                    <option value="">-- Select Type --</option>
                                    <option value="savings">Savings</option>
                                    <option value="current">Current</option>
                                    <option value="fixed">Fixed</option>
                                </select>
                                <p id="edit_account_type_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select an account type</p>
                            </div>
                            <div id="edit_field_account_number">
                                <label for="edit_account_number" id="edit_account_number_label" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                                    Account Number <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="edit_account_number" name="account_number" placeholder="Enter Account number"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition">
                                <p id="edit_account_number_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please enter your Account number</p>
                            </div>
                            <div id="edit_field_routing_number">
                                <label for="edit_routing_number" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Routing Number</label>
                                <input type="text" id="edit_routing_number" name="routing_number" placeholder="Enter Routing Number"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition">
                            </div>
                            <div>
                                <label for="edit_balance" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                                    Balance <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="edit_balance" name="balance" placeholder="Enter an amount"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition">
                                <p id="edit_balance_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please enter an amount</p>
                            </div>
                        </div>
                    </div>

                    {{-- Section: Additional Details --}}
                    <div>
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-6 h-6 rounded-lg bg-amber-100 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-ellipsis text-amber-600 text-[10px]"></i>
                            </div>
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Additional Details</p>
                            <div class="flex-1 border-t border-gray-100"></div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div id="edit_field_iban">
                                <label for="edit_iban" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">IBAN</label>
                                <input type="text" id="edit_iban" name="iban" placeholder="Enter IBAN"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition">
                            </div>
                            <div id="edit_field_swift_code">
                                <label for="edit_swift_code" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Swift Code</label>
                                <input type="text" id="edit_swift_code" name="swift_code" placeholder="Enter swift code"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition">
                            </div>
                            <div>
                                <label for="edit_currency" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Currency</label>
                                <input type="text" id="edit_currency" name="currency" placeholder="Enter currency"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition">
                            </div>
                            <div class="sm:col-span-3">
                                <label for="edit_address" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Address</label>
                                <input type="text" id="edit_address" name="address" placeholder="Enter address"
                                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition">
                            </div>
                        </div>
                    </div>

                </div>
            </form>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl flex-shrink-0">
            <button type="button" class="modal-close-edit px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition">
                Cancel
            </button>
            <button data-action="{{ route('role.banks.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'bank' => 1]) }}" id="editSubmit" type="button"
                class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold text-white bg-violet-600 rounded-xl hover:bg-violet-700 transition shadow-sm">
                <i class="fas fa-save text-xs"></i> Update Bank
            </button>
        </div>

    </div>
</div>
