<div id="editModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden" style="margin-top: -50px">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">Edit Designation</h3>
                <button class="modal-close-edit z-50">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="editForm" method="POST" enctype="multipart/form-data"
                    action="{{ route('role.leave-types.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'leave_type' => 1]) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="editItemId" name="id">
                    <div>
                        <label for="edit_name" class="block text-gray-700 text-sm font-medium mb-2">Name</label>
                        <input type="text" id="edit_name" name="name"
                            class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a name</p>
                    </div>                       
                    <div>
                        <label for="edit_max_leaves_count" class="block text-gray-700 text-sm font-medium mb-2">Max Leave <small class="text-muted">(count)</small></label>
                        <input type="number" id="edit_max_leaves_count" name="max_leaves_count"
                            class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter max leave count</p>
                    </div>
                    <div class="mt-3 flex items-center">
                        <input type="checkbox" id="edit_requires_time" name="requires_time" value="1"
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <label for="edit_requires_time" class="ml-2 block text-sm text-gray-700">Requires a time field on request</label>
                    </div>
                    <div class="mt-2 flex items-center">
                        <input type="checkbox" id="edit_exempts_early_out_deduction" name="exempts_early_out_deduction" value="1"
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <label for="edit_exempts_early_out_deduction" class="ml-2 block text-sm text-gray-700">Waives early-checkout salary deduction</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer flex justify-end pt-2">
                <button type="button" class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-edit">
                    Cancel
                </button>
                <button data-action="{{ route('role.leave-types.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'leave_type' => 1]) }}" id="editSubmit" type="button" class="btn btn-primary px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200">
                    Update
                </button>
            </div>
        </div>
    </div>
</div>
