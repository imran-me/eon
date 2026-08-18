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
                <form class="closest" id="createForm" action="{{ route('role.advance-salaries.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label for="create_user_id" class="block text-gray-700 text-sm font-bold mb-2">Users</label>
                            <select id="create_user_id" name="user_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                <option value="">All</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>                                            
                                @endforeach
                            </select>
                            <p id="create_user_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select a user</p>
                        </div>                                                                                                
                        <div class="mb-4">
                            <label for="create_month" class="block text-gray-700 text-sm font-bold mb-2">Date</label>
                            <input type="month" id="create_month" name="month" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please select a Date</p>
                        </div>	
                        <div class="mb-4">
                            <label for="create_amount" class="block text-gray-700 text-sm font-bold mb-2">Amount</label>
                            <input type="text" id="create_amount" name="amount" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter Amount">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a amount</p>
                        </div>
                        <div class="mb-4">                        
                            <label for="create_status" class="block text-gray-700 text-sm font-bold mb-2">Status</label>
                            <select id="create_status" name="status" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                <option value="Pending">Pending</option>
                                <option value="Approved">Approved</option>
                                <option value="Rejected">Rejected</option>                        
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label for="create_schedule_date" class="block text-gray-700 text-sm font-bold mb-2">Schedule Date</label>
                            <input type="date" id="create_schedule_date" name="schedule_date" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please select a schedule date</p>
                        </div>
                        <div class="mb-4">
                            <label for="create_reason" class="block text-gray-700 text-sm font-bold mb-2">Reason</label>
                            <textarea id="create_reason" name="reason" rows="2" placeholder="Write a Reason"
                                class="w-full rounded-md border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 p-3 text-sm text-gray-700 outline-none resize-none transition duration-150"></textarea>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Reason</p>
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
