<style>
    #createModal .salary-create-shell{width:min(1040px,calc(100vw - 32px));max-height:94vh;border-radius:16px;box-shadow:0 28px 70px rgba(15,23,42,.3);overflow:hidden;background:#f8fafc}
    #createModal .salary-create-content{padding:0}
    #createModal .salary-create-header{padding:15px 20px;background:linear-gradient(120deg,#0759b8,#0b74ed);color:#fff;display:flex;align-items:center;gap:12px}
    #createModal .salary-create-icon{width:38px;height:38px;border-radius:10px;background:rgba(255,255,255,.16);display:grid;place-items:center}
    #createModal .salary-create-heading{font-size:1rem;font-weight:800;line-height:1.2}
    #createModal .salary-create-subtitle{margin-top:2px;font-size:.7rem;color:#dbeafe}
    #createModal .modal-close-create{margin-left:auto;width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,.14);display:grid;place-items:center;color:#fff}
    #createModal .modal-close-create:hover{background:rgba(255,255,255,.25)}
    #createModal .salary-create-body{max-height:calc(94vh - 132px);padding:14px 18px;overflow-y:auto}
    #createModal #createForm>.grid{padding:14px;border:1px solid #dbe4ef;border-radius:11px;background:#fff;gap:12px}
    #createModal #createForm>.grid>div{margin-bottom:0}
    #createModal label{font-size:.7rem;margin-bottom:5px;color:#475569}
    #createModal .form-input,#createModal .form-select{min-height:36px;border-radius:8px;font-size:.78rem;border-color:#cbd5e1}
    #createModal #attendance_summary_container,#createModal #fixed_salary_notice,#createModal #deduction_breakdown_container,#createModal #overtime_salary_container{margin-top:12px;border-radius:10px;padding:12px}
    #createModal #create_notes{min-height:66px;resize:vertical}
    #createModal .salary-create-footer{padding:11px 18px;border-top:1px solid #e2e8f0;background:#fff;display:flex;justify-content:flex-end;gap:8px}
    #createModal #createSubmit{min-width:150px;background:#0874e6;border-radius:8px;font-size:.75rem;font-weight:750}
    #createModal #createSubmitLoading.hidden{display:none!important}
    #createModal #createSubmitLoading:not(.hidden){display:inline-flex;align-items:center}
    #createModal #createSubmitText.hidden{display:none!important}
    #createModal #createProcessingNotice{margin:12px 18px 0}
    @media(max-width:767px){#createModal .salary-create-shell{width:calc(100vw - 14px)}#createModal .salary-create-body{padding:10px}#createModal #createForm>.grid{grid-template-columns:1fr 1fr}}
    @media(max-width:520px){#createModal #createForm>.grid{grid-template-columns:1fr}}
</style>
<div id="createModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container salary-create-shell z-50">
        <div class="modal-content salary-create-content text-left">
            <div class="modal-header salary-create-header">
                <div class="salary-create-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                <div>
                    <h3 class="salary-create-heading">Generate Employee Salary</h3>
                    <p class="salary-create-subtitle">Review attendance, deductions and additions before creating the monthly payslip.</p>
                </div>
                <button type="button" class="modal-close-create z-50" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="createProcessingNotice" class="hidden mb-4 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-semibold text-blue-700">
                <i class="fas fa-spinner fa-spin mr-2"></i>
                Super admin, your request is being processed. Please wait while the payslip and reports are generated.
            </div>
            <div class="modal-body salary-create-body">
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
                                value="{{ date('Y-m-d') }}" required
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
                    <div id="fixed_salary_notice"
                        class="mt-4 p-3 bg-amber-50 rounded-lg border border-amber-200 hidden">
                        <p class="text-sm font-bold text-amber-800 uppercase tracking-wider">Fixed Salary Employee</p>
                        <p class="text-xs text-amber-700 mt-1 leading-relaxed">
                            Attendance never changes this employee's pay, so the absent, leave, late and
                            early-out figures below stay at 0.00 and no leave encashment accrues. Those
                            zeros are correct &mdash; do not type deductions in by hand, they are cleared
                            again on save. Loan and advance-salary instalments are still recovered, and
                            overtime is still paid when the employee is overtime eligible.
                        </p>
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
            <div class="modal-footer salary-create-footer">
                <button type="button"
                    class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-create">
                    Cancel
                </button>
                <button id="createSubmit" type="button"
                    class="btn btn-primary inline-flex items-center justify-center px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200">
                    <span id="createSubmitText"><i class="fas fa-check mr-1"></i> Generate Salary</span>
                    <span id="createSubmitLoading" class="hidden items-center gap-2">
                        <i class="fas fa-spinner fa-spin"></i> Processing...
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
