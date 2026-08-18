<div id="editModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">Edit </h3>
                <button class="modal-close-edit z-50">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="editForm" method="POST" enctype="multipart/form-data"
                    action="{{ route('role.payments.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'payment' => 1]) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="editItemId" name="id">                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label for="edit_user_id" class="block text-gray-700 text-sm font-bold mb-2">Employee</label>
                            <select id="edit_user_id" name="user_id" onchange="getEmpSalaries(this, '#edit_employee_salary_id')" data-action="{{ route('role.get-employee-salary', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                <option value="">All</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>                                            
                                @endforeach
                            </select>
                            <p id="edit_user_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select a user</p>
                        </div>                                                                                                
                        <div class="mb-4">
                            <label for="edit_employee_salary_id" class="block text-gray-700 text-sm font-bold mb-2">Employee Salary</label>
                            <select id="edit_employee_salary_id" name="employee_salary_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                <option value="">All</option>
                            </select>
                            <p id="edit_employee_salary_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select a salary</p>
                        </div>                                                                                                                        
                        <div class="mb-4">
                            <label for="edit_payment_date" class="block text-gray-700 text-sm font-bold mb-2">Payment Date</label>
                            <input type="date" id="edit_payment_date" name="payment_date" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please select a Date</p>
                        </div>	                                                                        
                        <div class="mb-4">
                            <label for="edit_payment_method" class="block text-gray-700 text-sm font-bold mb-2">Payment Method</label>
                            <input type="text" id="edit_payment_method" name="payment_method" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter Payment Method">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Payment Method</p>
                        </div>                        
                        <div class="mb-4">
                            <label for="edit_transaction_no" class="block text-gray-700 text-sm font-bold mb-2">Transaction No</label>
                            <input type="text" id="edit_transaction_no" name="transaction_no" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter Transaction No">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Transaction No</p>
                        </div>                        
                        <div class="mb-4">
                            <label for="edit_amount" class="block text-gray-700 text-sm font-bold mb-2">Amount</label>
                            <input type="text" id="edit_amount" name="amount" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter Amount">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a amount</p>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="edit_notes" class="block text-gray-700 text-sm font-bold mb-2">Notes</label>                            
                        <textarea id="edit_notes" name="notes" rows="4" placeholder="Write a notes"
                            class="w-full rounded-md border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 p-3 text-sm text-gray-700 outline-none resize-none transition duration-150"></textarea>
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Notes</p>
                    </div>
                </form>
            </div>
            <div class="modal-footer flex justify-end pt-2">
                <button type="button" class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-edit">
                    Cancel
                </button>
                <button data-action="{{ route('role.payments.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'payment' => 1]) }}" id="editSubmit" type="button" class="btn btn-primary px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200">
                    Update
                </button>
            </div>
        </div>
    </div>
</div>