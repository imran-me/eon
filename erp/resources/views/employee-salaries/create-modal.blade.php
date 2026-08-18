<div id="createModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto"
        style="min-width: 1100px !important;">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">Add New </h3>
                <button class="modal-close-create z-50">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body overflow-y-auto px-1" style="max-height: 70vh;">
                <form class="closest" id="createForm"
                    action="{{ route('role.employee-salaries.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    method="POST">
                    @csrf
                    <input type="hidden" name="loan_id" id="hidden_loan_id">
                    <input type="hidden" name="advance_salary_id" id="hidden_advance_salary_id">

                    <input type="hidden" name="month" id="hidden_month">
                    <input type="hidden" name="year" id="hidden_year">

                    <input type="hidden" name="loan_deduction" id="hidden_loan_deduction">
                    <input type="hidden" name="advance_salary_deduction" id="hidden_advance_salary_deduction">
                    <input type="hidden" name="absent_deduction" id="hidden_absent_deduction">
                    <input type="hidden" name="leave_deduction" id="hidden_leave_deduction">
                    <input type="hidden" name="late_deduction" id="hidden_late_deduction">
                    <input type="hidden" name="salary_adjustment" id="hidden_salary_adjustment">
                    <input type="hidden" name="early_leave_deduction" id="hidden_early_leave_deduction">
                    <input type="hidden" name="over_time" id="hidden_over_time">
                    <input type="hidden" name="over_time_days" id="hidden_over_time_days">
                    <input type="hidden" name="overtime_salary" id="hidden_overtime_salary">
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="mb-4">
                            <label for="create_user_id" class="block text-gray-700 text-sm font-bold mb-2">Users</label>
                            <select id="create_user_id" name="user_id"
                                class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2"
                                style="width: 100%">
                                <option value="">All</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}"
                                        data-salary="{{ $user->profile->salary ?? '' }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                            <p id="create_user_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select
                                a user</p>
                        </div>

                        {{-- <div class="mb-4">
                            <label for="create_salary_template_id"
                                class="block text-gray-700 text-sm font-bold mb-2">Salary Templates</label>
                            <select id="create_salary_template_id" name="salary_template_id"
                                class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2"
                                style="width: 100%">
                                <option value="">All</option>
                                @foreach ($templates as $template)
                                    <option value="{{ $template->id }}" data-salary="{{ $template->total_salary }}">
                                        {{ $template->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p id="create_salary_template_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a template</p>
                        </div> --}}
                        <div class="mb-4">
                            <label for="create_date" class="block text-gray-700 text-sm font-bold mb-2">Salary
                                Month</label>
                            <input type="month" id="create_date" name="date"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please select a Date</p>
                        </div>
                        <div class="mb-4">
                            <label for="create_payment_date" class="block text-gray-700 text-sm font-bold mb-2">Salary generation
                                Date</label>
                            <input type="date" id="create_payment_date" name="salary_generation_date"
                                value="{{ date('Y-m-d') }}"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please select salary generation Date</p>
                        </div>
                        <div class="mb-4">
                            <label for="create_scheduled_date" class="block text-gray-700 text-sm font-bold mb-2">Scheduled
                                Date</label>
                            <input type="date" id="create_scheduled_date" name="scheduled_date"
                                value="{{ date('Y-m-d') }}"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please select scheduled Date</p>
                        </div>
                        <div class="mb-4">
                            <label for="create_payment_method"
                                class="block text-gray-700 text-sm font-bold mb-2">Payment Methods</label>
                            <select id="create_payment_method" name="payment_method" style="width: 100%"
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
                        </div>
                        <div class="mb-4">
                            <label for="create_status" class="block text-gray-700 text-sm font-bold mb-2">Payment
                                Status</label>
                            <select id="create_status" name="status"
                                class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2"
                                style="width: 100%">
                                <option value="Pending">Pending</option>
                                {{-- <option value="Paid">Paid</option> --}}
                            </select>
                        </div>
                        {{-- <div class="mb-4">
                            <label for="create_transaction_no"
                                class="block text-gray-700 text-sm font-bold mb-2">Transaction No</label>
                            <input type="number" id="create_transaction_no" name="transaction_no"
                                placeholder="Enter Trnx No"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div> --}}
                        <div class="mb-4">
                            <label for="create_gross_salary" class="block text-gray-700 text-sm font-bold mb-2">Gross
                                Salary</label>
                            <input type="number" id="create_gross_salary" name="gross_salary"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter Amount">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Gross Salary</p>
                        </div>
                        <div class="mb-4">
                            <label for="create_total_deductions"
                                class="block text-gray-700 text-sm font-bold mb-2">Total Deductions</label>
                            <input type="number" id="create_total_deductions" name="total_deductions"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter Amount" value="0" min="0">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Total Deductions
                            </p>
                        </div>
                        <div class="mb-4">
                            <label for="create_total_additions"
                                class="block text-gray-700 text-sm font-bold mb-2">Total Additions</label>
                            <input type="number" id="create_total_additions" name="total_additions"
                                class="form-input w-full px-3 py-2 border border-green-300 rounded-md bg-green-50 focus:outline-none focus:ring-2 focus:ring-green-400"
                                placeholder="0.00" value="0" readonly>
                            <p class="text-green-600 text-xs mt-1">Auto: Overtime + Bonus + Adjustment</p>
                        </div>
                        <div class="mb-4">
                            <label for="create_bonus_label" class="block text-gray-700 text-sm font-bold mb-2">Bonus
                                Label</label>
                            <input type="text" id="create_bonus_label" name="bonus_label"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="e.g. Performance Bonus">
                        </div>

                        

                        <div class="mb-4">
                            <label for="create_bonus_amount" class="block text-gray-700 text-sm font-bold mb-2">Bonus
                                Amount</label>
                            <input type="number" id="create_bonus_amount" name="bonus_amount"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="0.00" value="0" min="0">
                        </div>
                        <div class="mb-4">
                            <label for="create_salary_adjustment" class="block text-gray-700 text-sm font-bold mb-2">Salary Adjustment</label>
                            <input type="number" id="create_salary_adjustment" name="salary_adjustment"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter Amount" value="0" min="0">
                            <span style="color:red">Value can be positive or negative</span>
                        </div>
                        <div class="mb-4">
                            <label for="create_net_salary" class="block text-gray-700 text-sm font-bold mb-2">Net
                                Salary</label>
                            <input type="number" id="create_net_salary" name="net_salary"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter Amount" value="0" min="0">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Net Salary</p>
                        </div>
                    </div>
                    <div id="attendance_summary_container"
                        class="mt-6 p-4 bg-gray-50 rounded-lg border border-gray-200 hidden">
                        <h4 class="text-sm font-bold text-gray-800 mb-3 uppercase tracking-wider">Attendance Summary
                        </h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                            <div class="bg-white p-2 rounded shadow-sm">
                                <p class="text-xs text-gray-500">Total Days</p>
                                <p id="sum_total_days" class="font-bold text-gray-600">0</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm">
                                <p class="text-xs text-gray-500">Present</p>
                                <p id="sum_present" class="font-bold text-green-600">0</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm">
                                <p class="text-xs text-gray-500">Absent</p>
                                <p id="sum_absent" class="font-bold text-red-600">0</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm">
                                <p class="text-xs text-gray-500">Leave</p>
                                <p id="sum_leave" class="font-bold text-red-600">0</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm">
                                <p class="text-xs text-gray-500">Late (Min)</p>
                                <p id="sum_late" class="font-bold text-orange-600">0</p>
                            </div>
                             <div class="bg-white p-2 rounded shadow-sm">
                                <p class="text-xs text-gray-500">Early Out (Min)</p>
                                <p id="sum_early_out" class="font-bold text-yellow-600">0</p>
                            </div>
                            
                            <div class="bg-white p-2 rounded shadow-sm">
                                <p class="text-xs text-gray-500">Overtime (Min)</p>
                                <p id="sum_overtime" class="font-bold text-purple-600">0</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm">
                                <p class="text-xs text-gray-500">Holidays</p>
                                <p id="sum_holiday" class="font-bold text-blue-600">0</p>
                            </div>
                        </div>
                    </div>
                    <div id="deduction_breakdown_container"
                        class="mt-4 p-2 bg-red-50 rounded-lg border border-red-200 hidden">
                        <h4 class="text-sm font-bold text-red-800 mb-3 uppercase tracking-wider">Deduction Breakdown
                        </h4>
                        <div class="grid grid-cols-2 md:grid-cols-6 gap-4 text-center">
                            <div class="bg-white p-2 rounded shadow-sm border border-red-100">
                                <p class="text-xs text-gray-500">Absent Ded.</p>
                                <p id="breakdown_absent" class="font-bold text-red-600">0.00</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm border border-red-100">
                                <p class="text-xs text-gray-500">Leave Ded.</p>
                                <p id="breakdown_leave" class="font-bold text-red-600">0.00</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm border border-red-100">
                                <p class="text-xs text-gray-500">Late Ded.</p>
                                <p id="breakdown_late" class="font-bold text-red-600">0.00</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm border border-red-100">
                                <p class="text-xs text-gray-500">Early Out Ded.</p>
                                <p id="breakdown_early_out" class="font-bold text-red-600">0.00</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm border border-red-100">
                                <p class="text-xs text-gray-500">Advance Salary</p>
                                <p id="breakdown_advance" class="font-bold text-red-600">0.00</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm border border-red-100">
                                <p class="text-xs text-gray-500">Loan Ded.</p>
                                <p id="breakdown_loan" class="font-bold text-red-600">0.00</p>
                            </div>
                        </div>
                        <p class="text-xs text-red-700 mt-3">
                            Note: Salary is deducted for late attendance only when total late time reaches 120 minutes or more.
                        </p>
                    </div>

                    <div id="overtime_salary_container"
                        class="mt-3 p-2 bg-green-50 rounded-lg border border-green-200 hidden">
                        <h4 class="text-sm font-bold text-green-800 mb-3 uppercase tracking-wider">
                            <i class="fas fa-clock mr-1"></i>Overtime Addition
                        </h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-center">
                            <div class="bg-white p-2 rounded shadow-sm border border-green-100">
                                <p class="text-xs text-gray-500">Overtime Minutes</p>
                                <p id="breakdown_overtime_minutes" class="font-bold text-green-600">0</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm border border-green-100">
                                <p class="text-xs text-gray-500">Overtime Days</p>
                                <p id="breakdown_overtime_days" class="font-bold text-green-600">0</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm border border-green-100">
                                <p class="text-xs text-gray-500">Overtime Salary</p>
                                <p id="breakdown_overtime_salary" class="font-bold text-green-700 text-base">৳ 0</p>
                            </div>
                        </div>
                        <p class="text-xs text-green-700 mt-3">
                            Overtime salary is automatically added to gross salary.
                        </p>
                    </div>
                    <div class="mb-4">
                        <label for="create_notes" class="block text-gray-700 text-sm font-bold mb-2">Note</label>
                        <textarea name="notes" id="create_notes"
                            class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            cols="30" rows="3"></textarea>
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
