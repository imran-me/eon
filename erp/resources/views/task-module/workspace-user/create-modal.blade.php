<div id="createModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden" style="margin-top: -50px">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 mx-auto rounded shadow-lg z-50 overflow-y-auto" style="max-width: 700px; max-height: 90vh;">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">
                    Add Workspace Users & Roles
                </h3>
                <button class="modal-close-create z-50 text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="modal-body mt-4">
                <form id="createForm" action="{{ route('role.workspace-users.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label for="workspace_id" class="block text-gray-700 text-sm font-semibold mb-2">Select Project<span class="text-red-500">*</span>
                        </label>
                        <select id="workspace_id" name="project_id" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" style="width: 100%">
                            <option value="">Select Project</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}">{{ $project->project_name }}</option>
                            @endforeach
                        </select>
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a project</p>
                    </div>

                    <div class="mb-4 hidden" id="userSelectContainer">
                        <label for="user_ids" class="block text-gray-700 text-sm font-semibold mb-2">Select Users (Multiple)<span class="text-red-500">*</span>
                        </label>
                        <select id="user_ids" name="user_ids[]" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" style="width: 100%" multiple disabled>
                            <option value="">Select workspace first</option>
                        </select>
                        <p class="text-xs mt-1 hidden error-message" style="color: #ef4444;">Please select at least one user</p>
                        <p class="text-xs mt-1 hidden no-users-message" style="color: #f97316;">No users found for the selected workspace.</p>
                    </div>

                    <div id="userRolesContainer" class="hidden">
                        <label class="block text-gray-700 text-sm font-semibold mb-3">Assign Roles for Each User<span class="text-red-500">*</span>
                        </label>
                        
                        <div id="userRolesList" class="space-y-3 max-h-96 overflow-y-auto pr-2">
                            <!-- Dynamic user role selections will appear here -->
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer flex justify-end pt-4 mt-4">
                <button type="button" class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-create">
                    Cancel
                </button>
                <button id="createSubmit" type="button" class="btn btn-primary px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-200 shadow">
                    Submit
                </button>
            </div>
        </div>
    </div>
</div>
