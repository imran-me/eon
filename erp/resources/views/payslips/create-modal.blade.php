<div id="createModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">Add New </h3>
                <button class="modal-close-create z-50">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form class="closest" id="createForm"
                    action="{{ route('role.payslips.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    method="POST">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label for="create_user_id"
                                class="block text-gray-700 text-sm font-bold mb-2">Employee</label>
                            <select id="create_user_id" name="user_id"
                                onchange="getEmpSalaries(this, '#create_employee_salary_id')"
                                data-action="{{ route('role.get-employee-salary', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                                class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2"
                                style="width: 100%">
                                <option value="">All</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                            <p id="create_user_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select
                                a user</p>
                        </div>
                        <div class="mb-4">
                            <label for="create_employee_salary_id"
                                class="block text-gray-700 text-sm font-bold mb-2">Employee Salary</label>
                            <select id="create_employee_salary_id" name="employee_salary_id"
                                class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2"
                                style="width: 100%">
                                <option value="">All</option>
                            </select>
                            <p id="create_employee_salary_msg" class="text-red-500 text-xs mt-1 hidden error-message">
                                Please select a salary</p>
                        </div>
                        <div class="mb-4">
                            <label for="create_issue_date" class="block text-gray-700 text-sm font-bold mb-2">Issue
                                Date</label>
                            <input type="date" id="create_issue_date" name="issue_date"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please select a Date</p>
                        </div>
                        <div class="mb-4">
                            <label for="create_payslip_number"
                                class="block text-gray-700 text-sm font-bold mb-2">Payslip Number</label>
                            <input type="text" id="create_payslip_number" name="payslip_number"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter Payslip Number">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Payslip Number</p>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="create_pdf_path" class="block text-gray-700 text-sm font-bold mb-2">PDF File</label>
                        <input type="file" id="create_pdf_path" name="pdf_path"
                            class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Enter pdf link">
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a valid file</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label for="create_payment_method"
                                class="block text-gray-700 text-sm font-bold mb-2">Payment Account</label>
                            <select id="create_payment_method" name="bank_id" style="width: 100%"
                                class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2">
                                <option value="" disabled selected>Select Payment Account</option>
                                @foreach ($paymentMethods as $method)
                                    <option value="{{ $method->id }}">{{ $method->name }}</option>
                                @endforeach

                            </select>
                        </div>
                        <div class="mb-4">
                            <label for="create_status" class="block text-gray-700 text-sm font-bold mb-2">Payment
                                Status</label>
                            <select id="create_status" name="status"
                                class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2"
                                style="width: 100%">
                                <option value="Paid">Paid</option>
                                <option value="Pending">Pending</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer flex justify-end pt-2">
                <button type="button"
                    class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-create">
                    Cancel
                </button>
                <button id="createSubmit" type="button"
                    class="btn btn-primary px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200">
                    Submit
                </button>
            </div>
        </div>
    </div>
</div>
