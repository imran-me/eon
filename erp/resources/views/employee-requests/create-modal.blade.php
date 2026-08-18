{{-- Create Request / Requirement Modal --}}
<div id="createModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="$('#createModal').addClass('hidden')"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4 pointer-events-none">
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col pointer-events-auto">

        {{-- Modal Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center">
                    <i class="fas fa-plus text-sm"></i>
                </div>
                <h3 class="text-base font-bold text-gray-800">Add New Request / Requirement</h3>
            </div>
            <button class="modal-close-create w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-500 transition">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        {{-- Modal Body --}}
        <div class="flex-1 overflow-y-auto px-6 py-5">
            <form id="createForm" action="{{ route('role.employee-requests.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST">
                @csrf

                {{-- Row 1: Category + Employee --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Category <span class="text-red-500">*</span></label>
                        <select id="create_category" name="category" class="select2 w-full" style="width:100%" required>
                            <option value="request">📤 Request (Employee asks)</option>
                            <option value="require">📥 Require (Company assigns)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Employee <span class="text-red-500">*</span></label>
                        @can('view all employee request')
                        <select id="create_employee_id" name="employee_id" class="select2 w-full" style="width:100%" required>
                            <option value="">Select Employee</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                        @else
                        <input type="hidden" name="employee_id" value="{{ Auth::id() }}">
                        <div class="px-3 py-2.5 border border-gray-200 rounded-lg text-sm bg-gray-50 text-gray-500">
                            {{ Auth::user()->name }}
                        </div>
                        @endcan
                    </div>
                </div>

                {{-- Row 2: Type + Amount/Deadline --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Type <span class="text-red-500">*</span></label>
                        <select id="create_request_type" name="request_type"
                            class="select2-type w-full" style="width:100%" required>
                            @foreach (\App\Models\EmployeeRequest::REQUEST_TYPES['request'] as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div id="create_amount_wrap">
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Amount</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-semibold">৳</span>
                            <input type="number" id="create_amount" name="amount" step="0.01" min="0"
                                class="w-full pl-7 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="0.00">
                        </div>
                    </div>

                    <div id="create_deadline_wrap" class="hidden">
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Due Date <span class="text-red-500">*</span></label>
                        <input type="date" id="create_deadline" name="deadline"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>

                {{-- Reason --}}
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Reason / Description</label>
                    <textarea name="reason" rows="3"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                        placeholder="Reason or description..."></textarea>
                </div>

                {{-- Image Upload (require only) --}}
                <div id="create_image_wrap" class="hidden mb-4">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Attachment / Image</label>
                    <div class="relative border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-blue-400 transition cursor-pointer" onclick="document.getElementById('create_image_input').click()">
                        <input type="file" id="create_image_input" name="image" accept="image/*,.pdf,.doc,.docx"
                            class="hidden" onchange="previewCreateImage(this)">
                        <div id="create_image_preview" class="hidden mb-2">
                            <img id="create_image_thumb" src="" alt="preview" class="max-h-28 mx-auto rounded-lg object-contain">
                        </div>
                        <div id="create_image_placeholder">
                            <i class="fas fa-cloud-upload-alt text-2xl text-gray-400 mb-1"></i>
                            <p class="text-xs text-gray-500">Click to upload image or document</p>
                            <p class="text-xs text-gray-400">PNG, JPG, PDF, DOC (max 5MB)</p>
                        </div>
                        <p id="create_image_filename" class="text-xs text-blue-600 font-medium hidden mt-1"></p>
                    </div>
                </div>

                {{-- ── Meta Panels ── --}}

                {{-- Project Payment --}}
                <div id="meta_project_payment_wrap" class="hidden rounded-xl border border-blue-100 bg-blue-50 p-4 mb-4">
                    <p class="text-xs font-bold text-blue-700 mb-3">📁 Project Payment Details</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Project</label>
                            <select name="meta_project_id" class="select2-project w-full" style="width:100%">
                                <option value="">Select Project</option>
                                @foreach (\App\Models\Project::orderBy('project_name')->get() as $proj)
                                    <option value="{{ $proj->id }}">{{ $proj->project_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Expense Type</label>
                            <select name="meta_expense_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="travel">Travel</option>
                                <option value="procurement">Procurement</option>
                                <option value="misc">Miscellaneous</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Travel & Allowance --}}
                <div id="meta_travel_wrap" class="hidden rounded-xl border border-sky-100 bg-sky-50 p-4 mb-4">
                    <p class="text-xs font-bold text-sky-700 mb-3">✈️ Travel Details</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Destination</label>
                            <input type="text" name="meta_destination" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="City / Country">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Purpose</label>
                            <input type="text" name="meta_purpose" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Trip purpose">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Trip From</label>
                            <input type="date" name="meta_trip_from" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Trip To</label>
                            <input type="date" name="meta_trip_to" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                {{-- Policy Acknowledgment --}}
                <div id="meta_policy_wrap" class="hidden rounded-xl border border-purple-100 bg-purple-50 p-4 mb-4">
                    <p class="text-xs font-bold text-purple-700 mb-3">📜 Policy Details</p>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Policy Title</label>
                    <input type="text" name="meta_policy_title" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="e.g. Remote Work Policy 2026">
                </div>

                {{-- Training Completion --}}
                <div id="meta_training_wrap" class="hidden rounded-xl border border-violet-100 bg-violet-50 p-4 mb-4">
                    <p class="text-xs font-bold text-violet-700 mb-3">🎓 Training Details</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Training Name</label>
                            <input type="text" name="meta_training_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" placeholder="Course / Training title">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Provider / Platform</label>
                            <input type="text" name="meta_provider" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" placeholder="e.g. Coursera, Internal">
                        </div>
                    </div>
                </div>

                {{-- Emergency Fund --}}
                <div id="meta_emergency_wrap" class="hidden rounded-xl border border-red-200 bg-red-50 p-4 mb-4">
                    <p class="text-xs font-bold text-red-700 mb-3">🆘 Emergency Fund Details</p>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Emergency Type</label>
                    <select name="meta_emergency_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                        <option value="medical">Medical Emergency</option>
                        <option value="family">Family Emergency</option>
                        <option value="other">Other</option>
                    </select>
                    <p class="text-xs text-red-600 mt-2">⚡ Fast-track approval (≤24h). One-time or deductible based on HR decision.</p>
                </div>

                {{-- NOC / Certificate --}}
                <div id="meta_noc_wrap" class="hidden rounded-xl border border-gray-200 bg-gray-50 p-4 mb-4">
                    <p class="text-xs font-bold text-gray-700 mb-3">📄 NOC / Certificate Details</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Document Type</label>
                            <select name="meta_document_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="experience_letter">Experience Letter</option>
                                <option value="salary_certificate">Salary Certificate</option>
                                <option value="noc_visa">NOC — Visa</option>
                                <option value="noc_bank">NOC — Bank</option>
                                <option value="noc_loan">NOC — Loan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Purpose / Addressed To</label>
                            <input type="text" name="meta_noc_purpose" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g. Embassy, Bank Name">
                        </div>
                    </div>
                </div>

                {{-- Bonus / Incentive --}}
                <div id="meta_bonus_wrap" class="hidden rounded-xl border border-purple-100 bg-purple-50 p-4 mb-4">
                    <p class="text-xs font-bold text-purple-700 mb-3">🎁 Bonus / Incentive Details</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Bonus Type</label>
                            <select name="meta_bonus_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                                <option value="performance">Performance Bonus</option>
                                <option value="festival">Festival Bonus</option>
                                <option value="merit">Merit / Spot Award</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2 mt-5">
                            <input type="checkbox" name="meta_tax_flag" value="1" id="meta_tax_flag" class="w-4 h-4 accent-purple-600">
                            <label for="meta_tax_flag" class="text-sm text-gray-700">Tax applicable</label>
                        </div>
                    </div>
                </div>

                {{-- Equipment / Resource --}}
                <div id="meta_equipment_wrap" class="hidden rounded-xl border border-blue-100 bg-blue-50 p-4 mb-4">
                    <p class="text-xs font-bold text-blue-700 mb-3">🖥️ Equipment / Resource Details</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Item / Resource Name</label>
                            <input type="text" name="meta_item_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g. Laptop, Desk Chair">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Quantity</label>
                            <input type="number" name="meta_quantity" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" min="1" value="1">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Urgency</label>
                            <select name="meta_urgency" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="normal">Normal</option>
                                <option value="urgent">Urgent</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Asset Return --}}
                <div id="meta_asset_return_wrap" class="hidden rounded-xl border border-cyan-100 bg-cyan-50 p-4 mb-4">
                    <p class="text-xs font-bold text-cyan-700 mb-2">🔄 Asset Return Checklist</p>
                    <p class="text-xs text-gray-500 mb-3">Select items employee must return:</p>
                    <div class="flex gap-5 flex-wrap mb-3">
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" name="meta_asset_laptop"  value="1" class="w-4 h-4 accent-cyan-600"> Laptop
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" name="meta_asset_id_card" value="1" class="w-4 h-4 accent-cyan-600"> ID Card
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" name="meta_asset_keys"    value="1" class="w-4 h-4 accent-cyan-600"> Keys
                        </label>
                    </div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Other Items</label>
                    <input type="text" name="meta_asset_other" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500" placeholder="Any other items to return">
                </div>

                {{-- Performance Review --}}
                <div id="meta_performance_wrap" class="hidden rounded-xl border border-green-100 bg-green-50 p-4 mb-4">
                    <p class="text-xs font-bold text-green-700 mb-3">📊 Performance Review Details</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Review Period</label>
                            <input type="text" name="meta_review_period" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="e.g. Q1 2026, Jan–Mar 2026">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Cycle</label>
                            <select name="meta_review_cycle" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                                <option value="quarterly">Quarterly</option>
                                <option value="biannual">Bi-Annual</option>
                                <option value="annual">Annual</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Timesheet / Work Log --}}
                <div id="meta_timesheet_wrap" class="hidden rounded-xl border border-indigo-100 bg-indigo-50 p-4 mb-4">
                    <p class="text-xs font-bold text-indigo-700 mb-3">⏱️ Timesheet / Work Log Details</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Period Type</label>
                            <select name="meta_period_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        <div></div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Period Start</label>
                            <input type="date" name="meta_period_start" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Period End</label>
                            <input type="date" name="meta_period_end" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>

                {{-- Office Requisition --}}
                <div id="meta_office_req_wrap" class="hidden rounded-xl border border-blue-100 bg-blue-50 p-4 mb-4">
                    <p class="text-xs font-bold text-blue-700 mb-3">🏢 Office Requisition Details</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Category</label>
                            <select name="meta_req_category" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="supplies">Supplies / Stationery</option>
                                <option value="equipment">Equipment / Furniture</option>
                                <option value="repair">Repair / Maintenance</option>
                                <option value="service">Service</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Estimated Cost (৳)</label>
                            <input type="number" name="meta_req_cost" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Vendor Suggestion</label>
                            <input type="text" name="meta_req_vendor" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Suggested vendor / supplier (optional)">
                        </div>
                    </div>
                </div>

                {{-- Interior Project Requirement --}}
                <div id="meta_interior_wrap" class="hidden rounded-xl border border-purple-100 bg-purple-50 p-4 mb-4">
                    <p class="text-xs font-bold text-purple-700 mb-3">🛋️ Interior Project Material Requirement</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Interior Project</label>
                            <select name="meta_interior_project_id" class="select2-project w-full" style="width:100%">
                                <option value="">Select Project</option>
                                @foreach (\App\Models\Project::orderBy('project_name')->get() as $proj)
                                    <option value="{{ $proj->id }}">{{ $proj->project_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Material / Item Spec</label>
                            <input type="text" name="meta_material_spec" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="e.g. Ceramic tiles 60×60cm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Quantity</label>
                            <input type="number" name="meta_material_qty" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500" min="1" placeholder="Qty">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Unit</label>
                            <input type="text" name="meta_material_unit" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="pcs / sqft / kg / m">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Estimated Cost (৳)</label>
                            <input type="number" name="meta_interior_cost" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500" step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>
                </div>

                {{-- Require Type Dynamic Fields --}}
                <input type="hidden" id="create_require_type_id" name="require_type_id" value="">
                <div id="create_require_dynamic_section" class="hidden rounded-xl border border-indigo-100 bg-indigo-50 p-4 mb-4">
                    <p class="text-xs font-bold text-indigo-700 mb-3" id="create_require_type_label"></p>
                    <div id="create_require_dynamic_fields" class="grid grid-cols-1 md:grid-cols-2 gap-3"></div>
                </div>

            </form>
        </div>

        {{-- Modal Footer --}}
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
            <button type="button" class="modal-close-create px-4 py-2 text-sm font-semibold text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 transition">
                Cancel
            </button>
            <button id="createSubmit" type="button" class="px-5 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition flex items-center gap-2">
                <i class="fas fa-paper-plane text-xs"></i> Submit
            </button>
        </div>

    </div>
    </div>
</div>
