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
                @php
                    // Only users who can approve/reject leave get to change the status here;
                    // LeaveController::update() enforces the same rule server-side (keeps existing status otherwise).
                    $canSetLeaveStatus = Auth::user()->canAny(['approve leave', 'reject leave']);
                @endphp
                <form id="editForm" method="POST" enctype="multipart/form-data"
                    action="{{ route('role.leaves.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'leaf' => 1]) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="editItemId" name="id">                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label for="edit_user_id" class="block text-gray-700 text-sm font-bold mb-2">Users</label>
                            <select id="edit_user_id" name="user_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">                                
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>                                            
                                @endforeach
                            </select>
                            <p id="edit_user_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select a user</p>
                        </div>
                        <div class="mb-4">
                            <label for="edit_company_id" class="block text-gray-700 text-sm font-bold mb-2">Company</label>
                            <select id="edit_company_id" name="company_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">                                                                        
                                @foreach ($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->name }}</option>                                            
                                @endforeach
                            </select>
                            <p id="edit_company_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a company</p>
                        </div>
                        <div class="mb-4">
                            <label for="edit_leave_type_id" class="block text-gray-700 text-sm font-bold mb-2">Leave Type</label>
                            <select id="edit_leave_type_id" name="leave_type_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                @foreach ($leave_types as $leave_type)
                                    <option value="{{ $leave_type->id }}" data-requires-time="{{ $leave_type->requires_time ? 1 : 0 }}">{{ $leave_type->name }}</option>
                                @endforeach
                            </select>
                            <p id="edit_leave_type_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a type</p>
                        </div>
                        <div class="mb-4 hidden" id="edit_leave_time_wrapper">
                            <label for="edit_leave_time" class="block text-gray-700 text-sm font-bold mb-2">Leave Time</label>
                            <input type="time" id="edit_leave_time" name="leave_time" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter the leave time</p>
                        </div>
                        @if ($canSetLeaveStatus)
                        <div class="mb-4">
                            <label for="edit_status" class="block text-gray-700 text-sm font-bold mb-2">Status</label>
                            <select id="edit_status" name="status" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                            <p id="edit_status_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a status</p>
                        </div>
                        @endif
                        <div class="mb-4 md:col-span-2">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Duration</label>
                            <div class="flex items-center gap-4">
                                <label class="inline-flex items-center text-sm text-gray-700">
                                    <input type="radio" name="edit_duration_mode" id="edit_duration_single" value="single">
                                    Single Day
                                </label>
                                <label class="inline-flex items-center text-sm text-gray-700">
                                    <input type="radio" name="edit_duration_mode" id="edit_duration_multiple" value="multiple">
                                    Multiple Days
                                </label>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="edit_start_date" id="edit_start_date_label" class="block text-gray-700 text-sm font-bold mb-2">Start Date</label>
                            <input type="date" id="edit_start_date" name="start_date" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Start Date</p>
                        </div>
                        <div class="mb-4" id="edit_end_date_wrapper">
                            <label for="edit_end_date" class="block text-gray-700 text-sm font-bold mb-2">End Date</label>
                            <input type="date" id="edit_end_date" name="end_date" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a End Date</p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <label for="edit_reason" class="block text-gray-700 text-sm font-medium mb-2">Reason</label>
                        <textarea id="edit_reason" name="reason" rows="4" placeholder="Enter Reason"
                            class="w-full rounded-md border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 p-3 text-sm text-gray-700 outline-none resize-none transition duration-150"></textarea>
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter an Reason</p>
                    </div>
                </form>
            </div>
            <div class="modal-footer flex justify-end pt-2">
                <button type="button" class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-edit">
                    Cancel
                </button>
                <button data-action="{{ route('role.leaves.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'leaf' => 1]) }}" id="editSubmit" type="button" class="btn btn-primary px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200">
                    Update
                </button>
            </div>
        </div>
    </div>
</div>
