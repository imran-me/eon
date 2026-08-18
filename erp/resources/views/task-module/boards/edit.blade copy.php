<div id="editModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden" style="margin-top: -50px">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 mx-auto rounded shadow-lg z-50 overflow-y-auto"
        style="max-width: 600px; max-height: 90vh;">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">
                    Edit Board
                </h3>
                <button class="modal-close-edit z-50 text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="modal-body mt-4">
                <form id="editeForm" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="editItemId" name="id">

                    {{-- <div class="mb-4">
                        <label for="edit_workspace_id" class="block text-gray-700 text-sm font-semibold mb-2">Workspace<span class="text-red-500">*</span>
                        </label>
                        <select id="edit_workspace_id" name="workspace_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Workspaces</option>
                            @foreach ($workspaces as $workspace)
                                <option value="{{ $workspace->id }}">{{ $workspace->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Workspace is required.</p>
                    </div> --}}
                    <div class="mb-4">
                        <label for="edit_project_id" class="block text-gray-700 text-sm font-semibold mb-2">Project<span
                                class="text-red-500">*</span>
                        </label>
                        <select id="edit_project_id" name="project_id"
                            class="form-select w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Projects</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}">{{ $project->project_name }}</option>
                            @endforeach
                        </select>
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Project is required.</p>
                    </div>
                    <div class="mb-4">
                        <label for="edit_name" class="block text-gray-700 text-sm font-semibold mb-2">Name<span
                                class="text-red-500">*</span>
                        </label>
                        <input type="text" id="edit_name" name="name" placeholder="Enter board name"
                            class="form-input w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a board name</p>
                    </div>
                    {{-- multiple select column --}}
                    <div class="mb-4">
                        <label for="columns" class="block text-gray-700 text-sm font-semibold mb-2">Select
                            Columns</label>
                        <select id="columns" name="columns[]" multiple
                            class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            style="width: 100%">
                            @foreach ($columns as $column)
                                <option value="{{ $column->id }}">{{ $column->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-semibold mb-2">
                            Column Positions
                        </label>

                        <div id="editColumnPositionsContainer" class="space-y-2">

                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="edit_description"
                            class="block text-gray-700 text-sm font-semibold mb-2">Description</label>
                        <textarea type="text" id="edit_description" name="description" placeholder="Enter description"
                            class="form-input w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer flex justify-end pt-4">
                <button type="button"
                    class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-edit">
                    Cancel
                </button>
                <button
                    data-action="{{ route('role.boards.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'board' => 1]) }}"
                    id="editSubmit" type="button"
                    class="btn btn-primary px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-200 shadow">
                    Update
                </button>
            </div>
        </div>
    </div>
</div>
