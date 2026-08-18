<div id="createModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden" style="margin-top: -50px">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">Add New Shift</h3>
                <button class="modal-close-create z-50">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form class="closest" id="createForm"
                    action="{{ route('role.shifts.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    method="POST" enctype="multipart/form-data">
                    @csrf            
                    <div class="mb-4">
                        <label for="create_name" class="block text-gray-700 text-sm font-medium mb-2">  Name</label>
                        <input type="text" id="create_name" name="name" placeholder="Shift Name"
                            class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a shift name</p>
                    </div>                       
                    <div class="mb-4">
                        <label for="create_start_time" class="block text-gray-700 text-sm font-medium mb-2">Start time</label>
                        <input type="time" id="create_start_time" name="start_time"
                            class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a time</p>
                    </div>                       
                    <div class="mb-4">
                        <label for="create_end_time" class="block text-gray-700 text-sm font-medium mb-2">End time</label>
                        <input type="time" id="create_end_time" name="end_time"
                            class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a time</p>
                    </div>  
                    <div class="mb-4">
                        <label for="create_holidays" class="block text-gray-700 text-sm font-bold mb-2">Weekly Holidays</label>
                        <select id="create_holidays" name="holidays[]" multiple class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%" data-placeholder="Select Weekdays">                         
                            <option value="saturday">Saturday</option>                            
                            <option value="sunday">Sunday</option>                            
                            <option value="monday">Monday</option>                            
                            <option value="tuesday">Tuesday</option>                            
                            <option value="wednesday">Wednesday</option>                            
                            <option value="thursday">Thursday</option>                            
                            <option value="friday">Friday</option>                            
                        </select>
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
