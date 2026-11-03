<style>
    #editModal .salary-edit-shell { width:min(1040px,calc(100vw - 32px)); max-height:94vh; border-radius:16px; overflow:hidden; box-shadow:0 24px 70px rgba(15,23,42,.28); }
    #editModal .salary-edit-content { display:flex; flex-direction:column; max-height:94vh; padding:0; }
    #editModal .salary-edit-header { flex:none; padding:18px 22px; color:#fff; background:linear-gradient(135deg,#1d4ed8,#0284c7); }
    #editModal .salary-edit-body { overflow-y:auto; padding:20px 22px 12px; background:#f8fafc; }
    #editModal .salary-edit-footer { flex:none; padding:14px 22px; border-top:1px solid #e2e8f0; background:#fff; }
    #editModal .salary-header-icon,
    #editModal .salary-edit-close { background:#fff!important; color:#075bb8!important; }
    #editModal .salary-header-icon svg,
    #editModal .salary-edit-close svg { display:block!important; stroke:#075bb8!important; fill:none!important; visibility:visible!important; opacity:1!important; }
    #editModal .salary-edit-close:hover { background:#eaf4ff!important; }
    #editModal .salary-edit-main-grid { padding:16px; border:1px solid #dbe5f1; border-radius:12px; background:#fff; }
    #editModal label { color:#475569; font-size:12px; font-weight:700; margin-bottom:6px; }
    #editModal input:not([type="hidden"]), #editModal select, #editModal textarea { min-height:40px; border-color:#cbd5e1; border-radius:8px; background:#fff; }
    #editModal textarea { min-height:82px; }
    #editModal .select2-container .select2-selection--single { height:40px!important; border-color:#cbd5e1!important; border-radius:8px!important; }
    #editModal .select2-container .select2-selection--single .select2-selection__rendered { line-height:38px!important; }
    #editModal .select2-container .select2-selection--single .select2-selection__arrow { height:38px!important; }
    #editSubmitLoading.hidden { display:none!important; }
    #editSubmitLoading:not(.hidden) { display:inline-flex!important; }
    @media (max-width:767px) { #editModal .salary-edit-shell { width:calc(100vw - 16px); max-height:96vh; } #editModal .salary-edit-body { padding:14px; } }
</style>

<div id="editModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container salary-edit-shell bg-white mx-auto z-50">
        <div class="modal-content salary-edit-content text-left">
            <div class="modal-header salary-edit-header flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <span class="salary-header-icon inline-flex items-center justify-center w-10 h-10 rounded-xl" aria-hidden="true">
                        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 2h9l3 3v17H6z"></path><path d="M14 2v4h4"></path><path d="M9 13h6"></path><path d="M12 10v6"></path>
                        </svg>
                    </span>
                    <div><h3 class="text-lg font-bold leading-tight">Edit Employee Salary</h3><p class="text-xs text-blue-100 mt-1">Update payroll details and payment information</p></div>
                </div>
                <button type="button" class="salary-edit-close modal-close-edit z-50 inline-flex items-center justify-center w-9 h-9 rounded-lg" aria-label="Close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                        <path d="M6 6l12 12M18 6L6 18"></path>
                    </svg>
                </button>
            </div>
            <div class="modal-body salary-edit-body">
                <form id="editForm" method="POST" enctype="multipart/form-data"
                    action="{{ route('role.employee-salaries.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'employee_salary' => 1]) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="editItemId" name="id">                    
                    <div class="salary-edit-main-grid grid grid-cols-1 md:grid-cols-4 gap-4">
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
                                required
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
                            <label for="edit_payment_method" class="block text-gray-700 text-sm font-bold mb-2">Payment Method</label>
                            <select id="edit_payment_method" name="payment_method" style="width: 100%"
                                class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2">
                                <optgroup label="Current payment methods">
                                    <option value="cash" selected>Cash</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="mobile_banking">Mobile Banking</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="other">Other</option>
                                </optgroup>

                                <optgroup label="Legacy mobile banking methods">
                                    <option value="bkash">bKash</option>
                                    <option value="nagad">Nagad</option>
                                    <option value="rocket">Rocket</option>
                                    <option value="upay">upay</option>
                                </optgroup>

                                <optgroup label="Legacy methods">
                                    <option value="card">Debit / Credit Card</option>
                                    <option value="nexus_pay">DBBL NexusPay</option>
                                    <option value="others">Others (legacy)</option>
                                    <option value="legacy_backfill">Imported legacy payment</option>
                                    <option value="legacy_reconciliation">Legacy reconciliation</option>
                                </optgroup>
                            </select>
                            <p class="text-gray-500 text-xs mt-1">Shows the method used by the latest posted salary payment.</p>
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
                                @include('partials.bank-options', ['banks' => $banks, 'selected' => null])
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
                            <p class="text-gray-500 text-xs mt-1">Auto: Gross + Overtime + Bonus + Adjustment − Deductions</p>
                        </div>
                    </div>

                    {{-- The parts Net Salary is built from. They are not typed in
                         here — "Recalculate from Attendance" refills them — but they
                         must travel with the form, otherwise an edit would save a new
                         Total Deductions while leaving the absent/leave/late split on
                         the row describing the old one. Loan and advance instalments
                         are deliberately absent: changing those here would leave the
                         loan register holding a repayment this payslip no longer takes. --}}
                    <input type="hidden" id="edit_absent_deduction" name="absent_deduction">
                    <input type="hidden" id="edit_leave_deduction" name="leave_deduction">
                    <input type="hidden" id="edit_late_deduction" name="late_deduction">
                    <input type="hidden" id="edit_early_leave_deduction" name="early_leave_deduction">
                    <input type="hidden" id="edit_over_time" name="over_time">
                    <input type="hidden" id="edit_over_time_days" name="over_time_days">
                    <input type="hidden" id="edit_overtime_salary" name="overtime_salary">
                    <input type="hidden" id="edit_bonus_amount" name="bonus_amount">
                    <input type="hidden" id="edit_salary_adjustment" name="salary_adjustment">
                    {{-- Loan/advance are not editable here, but the recalculated
                         Total Deductions has to keep including them. --}}
                    <input type="hidden" id="edit_loan_deduction_ref">
                    <input type="hidden" id="edit_advance_salary_deduction_ref">

                    {{-- ── Attendance recalculation ──────────────────────────────
                         Correcting an absent day to present in the Attendance module
                         used to change nothing here: the modal kept whatever
                         deductions were worked out the day the payslip was generated.
                         This pulls the figures again for the selected employee and
                         month, so the fix actually reaches the salary. --}}
                    <div class="mb-4 flex flex-wrap items-center gap-3">
                        <button type="button" id="edit_recalculate_btn"
                            class="inline-flex items-center px-3 py-2 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-md text-sm font-semibold hover:bg-indigo-100 transition duration-200">
                            <i class="fas fa-sync-alt mr-2"></i>Recalculate from Attendance
                        </button>
                        <span id="edit_recalculate_status" class="text-xs text-gray-500"></span>
                    </div>

                    <div id="edit_fixed_salary_notice"
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
                    <div id="edit_deduction_breakdown_container"
                        class="mt-4 p-2 bg-red-50 rounded-lg border border-red-200 hidden">
                        <h4 class="text-sm font-bold text-red-800 mb-3 uppercase tracking-wider">Deduction Breakdown</h4>
                        <div class="grid grid-cols-2 md:grid-cols-6 gap-4 text-center">
                            <div class="bg-white p-2 rounded shadow-sm border border-red-100">
                                <p class="text-xs text-gray-500">Absent Ded.</p>
                                <p id="edit_breakdown_absent" class="font-bold text-red-600">0.00</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm border border-red-100">
                                <p class="text-xs text-gray-500">Leave Ded.</p>
                                <p id="edit_breakdown_leave" class="font-bold text-red-600">0.00</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm border border-red-100">
                                <p class="text-xs text-gray-500">Late Ded.</p>
                                <p id="edit_breakdown_late" class="font-bold text-red-600">0.00</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm border border-red-100">
                                <p class="text-xs text-gray-500">Early Out Ded.</p>
                                <p id="edit_breakdown_early_out" class="font-bold text-red-600">0.00</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm border border-red-100">
                                <p class="text-xs text-gray-500">Advance Salary</p>
                                <p id="edit_breakdown_advance" class="font-bold text-red-600">0.00</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm border border-red-100">
                                <p class="text-xs text-gray-500">Loan Ded.</p>
                                <p id="edit_breakdown_loan" class="font-bold text-red-600">0.00</p>
                            </div>
                        </div>
                        <p class="text-xs text-red-700 mt-3">
                            Note: Salary is deducted for late attendance only when total late time reaches 120 minutes or more.
                            Advance and loan instalments are shown for reference and are not changed by this edit.
                        </p>
                    </div>

                    <div id="edit_overtime_salary_container"
                        class="mt-3 p-2 bg-green-50 rounded-lg border border-green-200 hidden">
                        <h4 class="text-sm font-bold text-green-800 mb-3 uppercase tracking-wider">
                            <i class="fas fa-clock mr-1"></i>Overtime Addition
                        </h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-center">
                            <div class="bg-white p-2 rounded shadow-sm border border-green-100">
                                <p class="text-xs text-gray-500">Overtime Minutes</p>
                                <p id="edit_breakdown_overtime_minutes" class="font-bold text-green-600">0</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm border border-green-100">
                                <p class="text-xs text-gray-500">Overtime Days</p>
                                <p id="edit_breakdown_overtime_days" class="font-bold text-green-600">0</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm border border-green-100">
                                <p class="text-xs text-gray-500">Overtime Pay</p>
                                <p id="edit_breakdown_overtime_salary" class="font-bold text-green-600">0.00</p>
                            </div>
                        </div>
                    </div>

                    <div id="edit_attendance_summary_container"
                        class="mt-3 p-4 bg-gray-50 rounded-lg border border-gray-200 hidden">
                        <h4 class="text-sm font-bold text-gray-800 mb-3 uppercase tracking-wider">Attendance Summary</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                            <div class="bg-white p-2 rounded shadow-sm">
                                <p class="text-xs text-gray-500">Total Days</p>
                                <p id="edit_sum_total_days" class="font-bold text-gray-600">0</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm">
                                <p class="text-xs text-gray-500">Present</p>
                                <p id="edit_sum_present" class="font-bold text-green-600">0</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm">
                                <p class="text-xs text-gray-500">Absent</p>
                                <p id="edit_sum_absent" class="font-bold text-red-600">0</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm">
                                <p class="text-xs text-gray-500">Leave</p>
                                <p id="edit_sum_leave" class="font-bold text-yellow-600">0</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm">
                                <p class="text-xs text-gray-500">Late</p>
                                <p id="edit_sum_late" class="font-bold text-orange-600">0</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm">
                                <p class="text-xs text-gray-500">Early Out</p>
                                <p id="edit_sum_early_out" class="font-bold text-orange-600">0</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm">
                                <p class="text-xs text-gray-500">Holiday</p>
                                <p id="edit_sum_holiday" class="font-bold text-blue-600">0</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm">
                                <p class="text-xs text-gray-500">Overtime</p>
                                <p id="edit_sum_overtime" class="font-bold text-green-600">0</p>
                            </div>
                        </div>
                    </div>
                    {{-- ── END Attendance recalculation ────────────────────────── --}}

                    <div class="mb-4">
                        <label for="edit_notes" class="block text-gray-700 text-sm font-bold mb-2">Note</label>
                        <textarea name="notes" id="edit_notes" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" cols="30" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer salary-edit-footer flex justify-end items-center gap-2">
                <button type="button" class="btn btn-secondary px-4 py-2 bg-white text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition duration-200 modal-close-edit">
                    Cancel
                </button>
                <button data-url-template="{{ route('role.employee-salaries.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'employee_salary' => 'REPLACE_ID']) }}" id="editSubmit" type="button" class="btn btn-primary inline-flex items-center px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200">
                    <span id="editSubmitIdle" class="inline-flex items-center"><i class="fas fa-save mr-2"></i>Update Salary</span>
                    <span id="editSubmitLoading" class="hidden items-center"><i class="fas fa-circle-notch fa-spin mr-2"></i>Updating...</span>
                </button>
            </div>
        </div>
    </div>
</div>
