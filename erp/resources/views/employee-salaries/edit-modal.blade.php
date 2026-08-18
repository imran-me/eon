<div id="editModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto" style="min-width: 1100px !important;">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">Edit </h3>
                <button class="modal-close-edit z-50">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="editForm" method="POST" enctype="multipart/form-data"
                    action="{{ route('role.employee-salaries.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'employee_salary' => 1]) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="editItemId" name="id">                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="mb-4">
                            <label for="edit_user_id" class="block text-gray-700 text-sm font-bold mb-2">Users</label>
                            <select id="edit_user_id" name="user_id"
                                class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2"
                                style="width: 100%">
                                <option value="">All</option>
                                {{-- $salaryUsers, not $users: an employee who has since resigned still
                                     owns the row being edited, and without them here the field renders
                                     blank and the update would post an empty user_id. --}}
                                @foreach ($salaryUsers as $user)
                                    <option value="{{ $user->id }}">
                                        {{ $user->name }}@if($user->status !== 'active') ({{ $user->status ?: 'inactive' }})@endif
                                    </option>
                                @endforeach
                            </select>
                            <p id="edit_user_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select a user</p>
                        </div>
                        <div class="mb-4">
                            <label for="edit_salary_template_id"
                                class="block text-gray-700 text-sm font-bold mb-2">Salary Templates</label>
                            <select id="edit_salary_template_id" name="salary_template_id"
                                class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2"
                                style="width: 100%">
                                <option value="">All</option>
                                @foreach ($templates as $template)
                                    <option value="{{ $template->id }}" data-salary="{{ $template->total_salary }}">
                                        {{ $template->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p id="edit_salary_template_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a template</p>
                        </div>
                        <div class="mb-4">
                            <label for="edit_date" class="block text-gray-700 text-sm font-bold mb-2">Salary Month</label>
                            <input type="month" id="edit_date" name="date"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please select a Date</p>
                        </div>
                        <div class="mb-4">
                            <label for="edit_payment_date" class="block text-gray-700 text-sm font-bold mb-2">Payment
                                Date</label>
                            <input type="date" id="edit_payment_date" name="payment_date" value="{{ date('Y-m-d') }}"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please select a Payment Date</p>
                        </div>                                                
                        <div class="mb-4">
                            <label for="edit_scheduled_date" class="block text-gray-700 text-sm font-bold mb-2">Scheduled
                                Date</label>
                            <input type="date" id="edit_scheduled_date" name="scheduled_date" value="{{ date('Y-m-d') }}"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please select a Scheduled Date</p>
                        </div>
                        <div class="mb-4">
                            <label for="edit_payment_method" class="block text-gray-700 text-sm font-bold mb-2">Payment Methods</label>
                            <select id="edit_payment_method" name="payment_method" style="width: 100%"
                                class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2">
                                <optgroup label="Mobile Banking (MFS)">
                                    <option value="bkash">bKash</option>
                                    <option value="nagad">Nagad</option>
                                    <option value="rocket">Rocket</option>
                                    <option value="upay">upay</option>
                                </optgroup>

                                <optgroup label="Traditional">
                                    <option value="cash" selected>Cash</option>
                                    <option value="card">Debit / Credit Card</option>
                                    <option value="bank_transfer">Bank Transfer / EFT</option>
                                </optgroup>

                                <option value="nexus_pay">DBBL NexusPay</option>
                                <option value="others">Others</option>
                            </select>
                            <p id="edit_payment_method_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select a payment method</p>
                        </div>
                        <div class="mb-4">
                            <label for="edit_status" class="block text-gray-700 text-sm font-bold mb-2">Payment
                                Status</label>
                            <select id="edit_status" name="status"
                                class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2"
                                style="width: 100%">
                                <option value="Pending">Pending</option>
                                <option value="Paid">Paid</option>
                            </select>
                            <p id="edit_status_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select a status</p>
                        </div>
                        <div class="mb-4" id="edit_bank_id_wrapper" style="display:none">
                            <label for="edit_bank_id" class="block text-gray-700 text-sm font-bold mb-2">Bank
                                Account</label>
                            <select id="edit_bank_id" name="bank_id"
                                class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2"
                                style="width: 100%">
                                <option value="">Select Bank Account</option>
                                @foreach ($banks as $bank)
                                    <option value="{{ $bank->id }}">{{ $bank->name }} - {{ $bank->account_number }}</option>
                                @endforeach
                            </select>
                            <p id="edit_bank_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select a bank account (required when status is Paid, unless payment method is Cash)</p>
                        </div>
                        <div class="mb-4">
                            <label for="edit_transaction_no" class="block text-gray-700 text-sm font-bold mb-2">Transaction No</label>
                            <input type="number" id="edit_transaction_no" name="transaction_no" placeholder="Enter Trnx No"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>  
                        <div class="mb-4">
                            <label for="edit_gross_salary" class="block text-gray-700 text-sm font-bold mb-2">Gross Salary</label>
                            <input type="number" id="edit_gross_salary" name="gross_salary"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter Amount">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Gross Salary</p>
                        </div>
                        <div class="mb-4">
                            <label for="edit_total_deductions"
                                class="block text-gray-700 text-sm font-bold mb-2">Total Deductions</label>
                            <input type="number" id="edit_total_deductions" name="total_deductions"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter Amount" value="0" min="0">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Total Deductions
                            </p>
                        </div>
                        <div class="mb-4">
                            <label for="edit_net_salary" class="block text-gray-700 text-sm font-bold mb-2">Net Salary</label>
                            <input type="number" id="edit_net_salary" name="net_salary"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter Amount" value="0" min="0">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Net Salary</p>
                        </div>                                                                                                                    
                    </div>
                    <div class="mb-4">
                        <label for="edit_notes" class="block text-gray-700 text-sm font-bold mb-2">Note</label>                        
                        <textarea name="notes" id="edit_notes" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" cols="30" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer flex justify-end pt-2">
                <button type="button" class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-edit">
                    Cancel
                </button>
                <button data-url-template="{{ route('role.employee-salaries.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'employee_salary' => 'REPLACE_ID']) }}" id="editSubmit" type="button" class="btn btn-primary px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200">
                    Update
                </button>
            </div>
        </div>
    </div>
</div>
