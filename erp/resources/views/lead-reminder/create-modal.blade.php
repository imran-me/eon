<div id="createModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden" style="margin-top: -50px">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">Add Lead Reminder</h3>
                <button class="modal-close-create z-50">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form class="closest" id="createForm"
                    action="{{ route('role.lead-reminders.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="lead_id" class="block text-gray-700 text-sm font-medium mb-2">Leads</label>
                            <select id="lead_id" name="lead_id" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Lead</option>
                                @foreach($leads as $lead)
                                <option value="{{ $lead->id }}">{{ $lead->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Lead</p>
                        </div>
                        <div>
                            <label for="title" class="block text-gray-700 text-sm font-medium mb-2">Title</label>
                            <input type="text" id="title" name="title"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter title">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a title</p>
                        </div>
                        <div>
                            <label for="description" class="block text-gray-700 text-sm font-medium mb-2">Description</label>
                            <textarea id="description" name="description" placeholder="Enter your description"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Enter a description</p>
                        </div>
                        <div>
                            <label for="due_date" class="block text-gray-700 text-sm font-medium mb-2">Due Date</label>
                            <input type="date" id="due_date" name="due_date"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                value="{{ Carbon\Carbon::now()->format('Y-m-d') }}">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a due date</p>
                        </div>
                        <div>
                            <label for="assigned_to" class="block text-gray-700 text-sm font-medium mb-2">Assigned To</label>
                            <select id="assigned_to" name="assigned_to" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select User</option>
                                @foreach($employees as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a User</p>
                        </div>
                        <div>
                            <label for="status" class="block text-gray-700 text-sm font-medium mb-2">Status</label>
                            <select id="status" name="status" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Status</option>
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Status is required</p>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer flex justify-end pt-2">
                <button type="button" class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-create">
                    Cancel
                </button>
                <button id="createSubmit" type="button" class="btn btn-primary px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200">
                    Submit
                </button>
            </div>
        </div>
    </div>
</div>