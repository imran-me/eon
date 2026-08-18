<div id="editModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden" style="margin-top: -50px">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">Edit Support Ticket</h3>
                <button class="modal-close-edit z-50">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="editForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input type="hidden" id="editItemId" name="id">
                        <div>
                            <label for="edit_title" class="block text-gray-700 text-sm font-medium mb-2">Title</label>
                            <input type="text" id="edit_title" name="title" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Please enter a Title">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Title is required</p>
                        </div>
                        <div>
                            <label for="edit_description" class="block text-gray-700 text-sm font-medium mb-2">Description</label>
                            <textarea id="edit_description" name="description" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Please enter a Description"></textarea>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Description is required</p>
                        </div>           
                        <div>
                            <label for="edit_file_attachment" class="block text-gray-700 text-sm font-medium mb-2">File Attachment</label>
                            <input type="file" id="edit_file_attachment" name="file_attachment" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Please upload a file">
                            <img src="" alt="" class="mt-2 w-20 h-20 object-cover" id="existing_image">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">File attachment is required</p>
                        </div>
                        <div>
                            <label for="edit_ticket_department_id" class="block text-gray-700 text-sm font-medium mb-2">Ticket Department</label>
                            <select id="edit_ticket_department_id" name="ticket_department_id" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Ticket Department</option>
                                @foreach($ticketDepartments as $department)
                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Ticket Department</p>
                        </div>
                        <div>
                            <label for="edit_priority" class="block text-gray-700 text-sm font-medium mb-2">Priority</label>
                            <select id="edit_priority" name="priority" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Priority</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Priority</p>
                        </div>
                        <div>
                            <label for="edit_status" class="block text-gray-700 text-sm font-medium mb-2">Status</label>
                            <select id="edit_status" name="status" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Status</option>
                                <option value="open">Open</option>
                                <option value="in_progress">In Progress</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Status</p>
                        </div>
                        <div>
                            <label for="edit_assigned_to" class="block text-gray-700 text-sm font-medium mb-2">Assigned To</label>
                            <select id="edit_assigned_to" name="assigned_to" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Employee</option>
                                @foreach($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose an Employee</p>
                        </div>
                        <div>
                            <label for="edit_company_id" class="block text-gray-700 text-sm font-medium mb-2">Company</label>
                            <select id="edit_company_id" name="company_id" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Company</option>
                                @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Company</p>
                        </div>
                        <div>
                            <label for="edit_customer_id" class="block text-gray-700 text-sm font-medium mb-2">Customer</label>
                            <select id="edit_customer_id" name="customer_id" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Customer</option>
                                @foreach($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Customer</p>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer flex justify-end pt-2">
                <button type="button" class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-edit">
                    Cancel
                </button>
                <button data-action="{{ route('role.support-tickets.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'support_ticket' => 1]) }}" id="editSubmit" type="button" class="btn btn-primary px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200">
                    Update
                </button>
            </div>
        </div>
    </div>
</div>
