@if(auth()->user()->hasRole('employee'))
<div id="statusModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
        <div class="bg-indigo-600 px-6 py-4 flex justify-between items-center rounded-t-lg">
            <h3 class="text-white text-lg font-semibold" style="color:white">
                <i class="fas fa-check-circle mr-2"></i>Update My Status
            </h3>
            <button id="closeStatusModal" class="text-white hover:text-gray-200" style="color:white">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="statusForm" class="p-6 space-y-4">
            @csrf
            <input type="hidden" id="status_todo_id">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">My Status <span class="text-red-500">*</span></label>
                <select id="status_my_status" name="status" class="select2 w-full border border-gray-300 rounded-md px-3 py-2">
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Note (optional)</label>
                <textarea id="status_my_note" name="note" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Add a note..."></textarea>
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" id="cancelStatus" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Save</button>
            </div>
        </form>
    </div>
</div>
@endif
