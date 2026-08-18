@if(auth()->user()->hasRole('super admin') || auth()->user()->hasRole('admin'))
<div id="editModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="bg-blue-600 px-6 py-4 flex justify-between items-center rounded-t-lg">
            <h3 class="text-white text-lg font-semibold" style="color:white">
                <i class="fas fa-edit mr-2"></i>Edit Todo
            </h3>
            <button id="closeEditModal" class="text-white hover:text-gray-200" style="color:white">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="editForm" enctype="multipart/form-data" class="p-6 space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" id="edit_id" name="id">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
                    <input type="text" id="edit_title" name="title" required class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="edit_description" name="description" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                    <select id="edit_department_id" name="department_id" class="select2 w-full border border-gray-300 rounded-md">
                        <option value="">— None —</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                    <select id="edit_priority" name="priority" class="select2 w-full border border-gray-300 rounded-md">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" id="edit_start_date" name="start_date" class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                    <input type="date" id="edit_due_date" name="due_date" class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="edit_status" name="status" class="select2 w-full border border-gray-300 rounded-md">
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>

                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                        <input type="checkbox" id="edit_is_self" name="is_self" value="1">
                        My own todo (no assignees)
                    </label>
                </div>

                <div class="md:col-span-2" id="edit_assignee_block">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Assign To</label>
                    <select id="edit_assigned_to" name="assigned_to[]" class="select2 w-full border border-gray-300 rounded-md" multiple>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <i class="fas fa-list-check mr-1 text-blue-500"></i>Checklist Items
                    </label>
                    <div id="edit_checklist_list" class="space-y-2 mb-2"></div>
                    <div class="flex gap-2">
                        <input type="text" id="edit_checklist_input"
                            class="flex-1 border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                            placeholder="Add a checklist item...">
                        <button type="button" id="edit_add_checklist_btn"
                            class="px-3 py-2 bg-blue-100 text-blue-700 rounded-md text-sm font-medium hover:bg-blue-200 whitespace-nowrap">
                            + Add
                        </button>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Replace Attachment</label>
                    <input type="file" name="attachment" class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" id="cancelEdit" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Update</button>
            </div>
        </form>
    </div>
</div>
@endif
