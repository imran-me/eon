<div id="editModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden" style="margin-top: -50px">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 mx-auto rounded shadow-lg z-50 overflow-y-auto" style="max-width: 700px; max-height: 90vh;">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">
                    Edit Workspace Users & Roles
                </h3>
                <button class="modal-close-edit z-50 text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="modal-body mt-4">
                <form id="editForm" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="edit_project_id" name="project_id">

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-semibold mb-2">Project</label>
                        <div class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-lg text-gray-700">
                            <strong id="edit_project_name"></strong>
                        </div>
                    </div>

                    <div id="editUserRolesContainer">
                        <label class="block text-gray-700 text-sm font-semibold mb-3">Users & Roles<span class="text-red-500">*</span>
                        </label>
                        
                        <div id="editUserRolesList" class="space-y-3 max-h-96 overflow-y-auto pr-2">
                            <div class="text-center py-4">
                                <i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i>
                                <p class="text-gray-500 mt-2">Loading users...</p>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer flex justify-end pt-4 mt-4">
                <button type="button" class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-edit">
                    Cancel
                </button>
                <button id="editSubmit" type="button" class="btn btn-primary px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-200 shadow">
                    Update All Roles
                </button>
            </div>
        </div>
    </div>
</div>
