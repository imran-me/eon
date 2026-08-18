<div id="deleteModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="bg-white w-11/12 md:max-w-md mx-auto rounded-2xl shadow-lg z-50">
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-bold text-gray-800"><i class="fas fa-triangle-exclamation mr-2 text-red-500"></i>Confirm Delete</h3>
            <button class="modal-close-delete text-gray-400 hover:text-gray-700"><i class="fas fa-times"></i></button>
        </div>
        <div class="px-6 py-5">
            <p class="text-sm text-gray-700">Delete <span id="deleteName" class="font-semibold"></span>?</p>
            {{-- A department already used by expenses is refused server-side with
                 a reason, rather than being removed out from under them. --}}
            <p class="text-xs text-gray-500 mt-2">
                If any expenses are filed against it, it will be kept and you'll be asked to set it inactive instead.
            </p>
        </div>
        <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100">
            <button class="modal-close-delete px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-200">Cancel</button>
            <button id="confirmDeleteBtn" data-action="{{ route('role.expense-departments.destroy', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'expense_department' => 0]) }}"
                class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700">Delete</button>
        </div>
    </div>
</div>
