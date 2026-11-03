<div id="createModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <div>
                    <h3 class="text-xl font-semibold">{{ Auth::user()->hasRole('employee') && !Auth::user()->can('view all loan') ? 'Request a Loan' : 'Disburse Loan' }}</h3>
                    @if(Auth::user()->hasRole('employee') && !Auth::user()->can('view all loan'))
                    <input type="hidden" id="employeeLoanRequestMode" value="1">
                        <p class="text-xs text-gray-500 mt-1">Tell Accounts how much you need and your preferred monthly instalment.</p>
                    @endif
                </div>
                <button class="modal-close-create z-50">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form class="closest" id="createForm" action="{{ route('role.loans.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST">
                    @csrf
                    @if(Auth::user()->hasRole('employee') && !Auth::user()->can('view all loan'))
                    <div class="rounded-xl border border-blue-100 bg-blue-50/60 p-4 mb-4 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center"><i class="fas fa-user"></i></div>
                        <div><p class="text-xs text-gray-500">Requesting for</p><p class="font-semibold text-gray-800">{{ Auth::user()->name }}</p></div>
                    </div>
                    <input type="hidden" name="user_id" value="{{ Auth::id() }}">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Requested Amount <span class="text-red-500">*</span></label>
                            <div class="relative"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-semibold">৳</span><input type="number" step="0.01" min="0.01" name="amount" required placeholder="How much do you need?" class="w-full pl-8 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"></div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div><label class="block text-sm font-semibold text-gray-700 mb-1">Preferred Monthly Instalment <span class="text-red-500">*</span></label><input type="number" step="0.01" min="0.01" name="monthly_deduction" required placeholder="e.g. 5,000" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"><p class="text-xs text-gray-400 mt-1">Deducted automatically from salary after disbursement.</p></div>
                            <div><label class="block text-sm font-semibold text-gray-700 mb-1">Needed By <span class="text-gray-400 font-normal">(optional)</span></label><input type="date" name="needed_by" min="{{ now()->toDateString() }}" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"></div>
                        </div>
                        <div><label class="block text-sm font-semibold text-gray-700 mb-1">Reason <span class="text-red-500">*</span></label><textarea name="reason" rows="3" required minlength="5" maxlength="1000" placeholder="Briefly explain why you need this loan" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm resize-none focus:ring-2 focus:ring-blue-500"></textarea></div>
                        <div class="rounded-lg border border-amber-100 bg-amber-50 px-3 py-2.5 text-xs text-amber-800"><i class="fas fa-circle-info mr-1"></i>Submitting this form only sends a request. Accounts will select the payment account and post the journal after approval.</div>
                    </div>
                    @else
                    <input type="hidden" name="payment_method" value="bank_transfer">
                    <input type="hidden" name="transaction_time" value="{{ now()->format('H:i') }}">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label for="create_user_id" class="block text-gray-700 text-sm font-bold mb-2">User</label>
                            @if(Auth::user()->hasRole('employee') && !Auth::user()->can('view all loan'))
                                <input type="hidden" id="create_user_id" name="user_id" value="{{ Auth::id() }}">
                                <input type="text" value="{{ Auth::user()->name }}" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-700">
                            @else
                                <select id="create_user_id" name="user_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                    <option value="">Select employee</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                            <p id="create_user_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select a user</p>
                        </div>                                                                                                
                        <div class="mb-4">
                            <label for="create_bank_id" class="block text-gray-700 text-sm font-bold mb-2">Bank</label>
                            <select id="create_bank_id" name="bank_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                <option value="">All</option>
                                @include('partials.bank-options', ['banks' => $banks, 'selected' => null])
                            </select>
                            <p id="create_bank_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select a bank</p>
                        </div>                                                                                                
                        <div class="mb-4">
                            <label for="create_start_date" class="block text-gray-700 text-sm font-bold mb-2">Start Date</label>
                            <input type="date" id="create_start_date" name="start_date" required class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please select a Start Date</p>
                        </div>	
                        <div class="mb-4">
                            <label for="create_end_date" class="block text-gray-700 text-sm font-bold mb-2">End Date</label>
                            <input type="date" id="create_end_date" name="end_date" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please select a End Date</p>
                        </div>	
                        <div class="mb-4">
                            <label for="create_amount" class="block text-gray-700 text-sm font-bold mb-2">Amount</label>
                            <input type="text" id="create_amount" name="amount" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter Amount">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a amount</p>
                        </div>
                        <div class="mb-4">
                            <label for="create_remaining_amount" class="block text-gray-700 text-sm font-bold mb-2">Remaining Amount</label>
                            <input type="text" id="create_remaining_amount" name="remaining_amount" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter Amount">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Remaining Amount</p>
                        </div>
                        <div class="mb-4">
                            <label for="create_monthly_deduction" class="block text-gray-700 text-sm font-bold mb-2">Monthly Deduction</label>
                            <input type="text" id="create_monthly_deduction" name="monthly_deduction" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter Amount">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Monthly Deduction</p>
                        </div>
                        <div class="mb-4">                        
                            <label for="create_status" class="block text-gray-700 text-sm font-bold mb-2">Status</label>
                            <select id="create_status" name="status" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                <option value="Running">Running</option>                            
                                <option value="Completed">Completed</option>                            
                            </select>
                        </div>
                    </div>
                    @endif
                </form>
            </div>
            <div class="modal-footer flex justify-end pt-2">
                <button type="button" class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-create">
                    Cancel
                </button>
                <button id="createSubmit" type="button" class="btn btn-primary px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200">
                    {{ Auth::user()->hasRole('employee') && !Auth::user()->can('view all loan') ? 'Submit Request' : 'Disburse Loan' }}
                </button>
            </div>
        </div>
    </div>
</div>
