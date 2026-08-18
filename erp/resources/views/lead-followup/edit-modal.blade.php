<div id="editModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden" style="margin-top: -50px">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">Edit Lead Followup</h3>
                <button class="modal-close-edit z-50">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="editForm" method="POST" enctype="multipart/form-data"
                    action="{{ route('role.lead-followup.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'lead_followup' => 1]) }}">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input type="hidden" id="editItemId" name="id">
                        <div>
                            <label for="edit_lead_id" class="block text-gray-700 text-sm font-medium mb-2">Lead</label>
                            <select id="edit_lead_id" name="lead_id" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Leads</option>
                                @foreach($leads as $lead)
                                    <option value="{{ $lead->id }}">{{ $lead->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Lead</p>
                        </div>
                        <div>
                            <label for="edit_user_id" class="block text-gray-700 text-sm font-medium mb-2">User</label>
                            <select id="edit_user_id" name="user_id" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Users</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a user</p>
                        </div>
                         <div>
                            <label for="edit_type" class="block text-gray-700 text-sm font-medium mb-2">Type</label>
                            <select id="edit_type" name="type" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Type</option>
                                <option value="call">Call</option>
                                <option value="meeting">Meeting</option>
                                <option value="email">Email</option>
                                <option value="whatsapp">WhatsApp</option>
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Type</p>
                        </div>
                        <div>
                            <label for="edit_description" class="block text-gray-700 text-sm font-medium mb-2">Description</label>
                            <textarea id="edit_description" name="description" placeholder="Enter your description"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Enter a description</p>
                        </div>
                        <div>
                            <label for="edit_followup_date" class="block text-gray-700 text-sm font-medium mb-2">Followup Date</label>
                            <input type="date" id="edit_followup_date" name="followup_date"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                value="{{ Carbon\Carbon::now()->format('Y-m-d') }}">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a followup date</p>
                        </div>
                        <div>
                            <label for="edit_status" class="block text-gray-700 text-sm font-medium mb-2">Status</label>
                            <select id="edit_status" name="status" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Status</option>
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Status is required</p>
                        </div>
                    </div>  
                </form>
            </div>
            <div class="modal-footer flex justify-end pt-2">
                <button type="button" class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-edit">
                    Cancel
                </button>
                <button data-action="{{ route('role.lead-followup.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'lead_followup' => 1]) }}" id="editSubmit" type="button" class="btn btn-primary px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200">
                    Update
                </button>
            </div>
        </div>
    </div>
</div>
