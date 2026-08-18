<div id="deleteModal" class="modal fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="fixed inset-0 flex items-center justify-center pointer-events-none">
    <div class="modal-container bg-white w-11/12 md:max-w-sm mx-auto rounded-xl shadow-2xl z-50 overflow-y-auto pointer-events-auto">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 bg-red-50 border-b border-red-100 rounded-t-xl">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-trash text-sm" style="color:#ef4444"></i>
                </div>
                <h3 class="text-gray-800 font-semibold text-lg">Confirm Delete</h3>
            </div>
            <button class="modal-close-delete w-8 h-8 flex items-center justify-center rounded-lg bg-red-100 hover:bg-red-200 transition duration-150" style="color:#ef4444">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        {{-- Body --}}
        <div class="px-6 py-6">
            <div class="flex flex-col items-center text-center gap-3">
                <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-xl" style="color:#ef4444"></i>
                </div>
                <p class="text-gray-700 text-sm">
                    Are you sure you want to delete
                    <span id="deleteName" class="font-bold text-gray-900"></span>?
                </p>
                <p class="text-red-500 text-xs font-medium">This action cannot be undone.</p>
            </div>
        </div>

        {{-- Footer --}}
        <div class="flex justify-end gap-3 px-6 py-4 bg-gray-50 rounded-b-xl border-t border-gray-100">
            <button class="modal-close-delete px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition duration-150">
                Cancel
            </button>
            <button id="confirmDeleteBtn"
                data-action="{{ route('role.expense-subcategories.destroy', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'expense_subcategory' => 1]) }}"
                class="px-5 py-2 text-sm font-semibold text-white bg-red-500 rounded-lg hover:bg-red-600 transition duration-150 inline-flex items-center gap-2">
                <i class="fas fa-trash"></i>Delete
            </button>
        </div>

    </div>
    </div>
</div>
