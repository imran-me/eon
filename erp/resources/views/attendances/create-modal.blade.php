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
                <form class="closest" id="createForm" action="{{ route('role.attendances.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-2">
                            <label for="create_user_id" class="block text-gray-700 text-sm font-bold mb-2">Users</label>
                            <select id="create_user_id" name="user_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>                                            
                                @endforeach
                            </select>
                            <p id="create_user_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select a user</p>
                        </div>
                        {{-- <div class="mb-2">
                            <label for="create_company_id" class="block text-gray-700 text-sm font-bold mb-2">Company</label>
                            <select id="create_company_id" name="company_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">                                        
                                @foreach ($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->name }}</option>                                            
                                @endforeach
                            </select>
                            <p id="create_company_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a company</p>
                        </div> --}}
                        <div class="mb-2">
                            <label for="create_shift_id" class="block text-gray-700 text-sm font-bold mb-2">Shift</label>
                            <select id="create_shift_id" name="shift_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">                                        
                                @foreach ($shifts as $shift)
                                    <option value="{{ $shift->id }}">{{ $shift->name }}</option>                                            
                                @endforeach
                            </select>
                            <p id="create_shift_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Shift</p>
                        </div>   
                        {{-- <div class="mb-2">
                            <label for="create_attendence_setting_id" class="block text-gray-700 text-sm font-bold mb-2">Attendence Setting</label>
                            <select id="create_attendence_setting_id" name="attendence_setting_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">                                        
                                <option value="">All</option>
                                @foreach ($attendence_settings as $setting)
                                    <option value="{{ $setting->id }}">{{ $setting->name }}</option>                                            
                                @endforeach
                            </select>
                            <p id="create_attendence_setting_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Setting</p>
                        </div>    --}}
                        <div class="mb-2">
                            <label for="create_status" class="block text-gray-700 text-sm font-bold mb-2">Status</label>
                            <select id="create_status" name="status" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                <option value="present" selected>Present</option>
                                <option value="absent">Absent</option>
                                <option value="leave">Leave</option>
                                <option value="holiday">Holiday</option>
                            </select>
                            <p id="create_status_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a status</p>
                        </div>                                                                                           
                        <div class="mb-2">
                            <label for="create_date" class="block text-gray-700 text-sm font-bold mb-2">Date</label>
                            <input type="date" id="create_date" name="date" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Date</p>
                        </div>                                             
                        <div class="mb-2">
                            <label for="create_check_in" class="block text-gray-700 text-sm font-bold mb-2">Check In Time</label>
                            <input type="time" id="create_check_in" name="check_in" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Check In Time</p>
                        </div>                                             
                        <div class="mb-2">
                            <label for="create_check_out" class="block text-gray-700 text-sm font-bold mb-2">Check Out Time</label>
                            <input type="time" id="create_check_out" name="check_out" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Check Out Time</p>
                        </div>                                             
                    </div>
                    <div class="mt-2">
                        <label for="create_note" class="block text-gray-700 text-sm font-medium mb-2">Note</label>
                        <textarea id="create_note" name="note" rows="4" placeholder="Enter Note"
                            class="w-full rounded-md border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 p-3 text-sm text-gray-700 outline-none resize-none transition duration-150"></textarea>
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter an Note</p>
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
