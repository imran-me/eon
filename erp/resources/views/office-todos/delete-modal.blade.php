@if(auth()->user()->hasRole('super admin') || auth()->user()->hasRole('admin'))
<div id="deleteModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
        <div class="bg-red-600 px-6 py-4 flex justify-between items-center rounded-t-lg">
            <h3 class="text-white text-lg font-semibold" style="color:white">
                <i class="fas fa-trash mr-2"></i>Delete Todo
            </h3>
            <button id="closeDeleteModal" class="text-white hover:text-gray-200" style="color:white">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-6">
            <p class="text-gray-700">Are you sure you want to delete this todo? This action cannot be undone.</p>
            <form id="deleteForm" class="mt-4">
                @csrf
                @method('DELETE')
                <input type="hidden" id="delete_item_id" name="item_id">
                <div class="flex justify-end gap-3">
                    <button type="button" id="cancelDelete" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        <i class="fas fa-trash mr-1"></i>Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
