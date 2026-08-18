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
                <form class="closest" id="createForm" action="{{ route('role.sms_templates.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-1 gap-0">                        
                        <div class="mb-4">
                            <label for="create_name" class="block text-gray-700 text-sm font-bold mb-2">Name</label>
                            <input type="text" id="create_name" name="name" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter Name">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Name</p>
                        </div>                        
                        <div class="mb-4">
                            <label for="create_title" class="block text-gray-700 text-sm font-bold mb-2">Title</label>
                            <input type="text" id="create_title" name="title" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter title">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a title</p>
                        </div> 
                        <div class="mb-4">
                            <label for="create_template" class="block text-gray-700 text-sm font-bold mb-2">Template</label>
                            <input type="text" id="create_template" name="template" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter template">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a template</p>
                        </div>                         
                        <div class="mb-4">
                            <label for="create_variables" class="block text-gray-700 text-sm font-bold mb-2">Variables</label>
                            <input type="text" id="create_variables" name="variables" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Write variables with comma">
                            <p class="text-xs italic">Separate with commas. No spaces or any special characters except underscores.</p>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a variables</p>
                        </div> 
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="status" id="create_status" class="form-checkbox h-5 w-5 text-blue-600" checked>
                                <span class="ml-2 text-gray-700">Active</span>
                            </label>
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
