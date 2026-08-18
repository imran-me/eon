<div id="deleteModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">Confirm Delete</h3>
                <button class="modal-close-delete z-50">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <span id="deleteName" class="font-semibold"></span>?</p>
                <p class="text-red-500 mt-2">This action cannot be undone.</p>
            </div>
            <div class="modal-footer flex justify-end pt-2">
                <button class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-delete">
                    Cancel
                </button>
                <button id="confirmDeleteBtn" data-action="{{ route('role.payments.destroy', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'payment' => 1]) }}" class="btn btn-danger px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition duration-200">
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>