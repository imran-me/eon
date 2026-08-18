{{-- Delete Passport Category Modal --}}
<div id="deleteModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="modal-backdrop fixed inset-0 bg-black/50 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm z-10">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 bg-red-600 rounded-t-2xl">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center">
                    <i class="fas fa-trash text-white"></i>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-white">Delete Category</h3>
                    <p class="text-xs text-red-100">This action cannot be undone</p>
                </div>
            </div>
            <button type="button" class="modal-close-delete w-8 h-8 flex items-center justify-center rounded-lg bg-white/20 hover:bg-white/30 text-white transition">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        {{-- Body --}}
        <div class="px-6 py-5">
            <p class="text-sm text-gray-600">
                Are you sure you want to delete the category
                <span id="deleteCategoryName" class="font-semibold text-gray-800"></span>?
            </p>

            {{-- Warning shown when category has holders --}}
            <div id="deleteHolderWarning" class="hidden mt-3">
                <div class="flex items-start gap-2.5 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
                    <i class="fas fa-exclamation-triangle text-amber-500 mt-0.5 text-sm flex-shrink-0"></i>
                    <p class="text-xs text-amber-700">
                        This category has <span id="deleteCategoryCount" class="font-bold"></span> passport holder(s) linked to it.
                        Deleting it may affect those records.
                    </p>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
            <button type="button" class="modal-close-delete px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition">
                Cancel
            </button>
            <button id="confirmDeleteBtn"
                data-action="{{ route('role.passport-holder-category.destroy', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'passport_holder_category' => 1]) }}"
                class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold text-white bg-red-600 rounded-xl hover:bg-red-700 transition shadow-sm">
                <i class="fas fa-trash text-xs"></i> Delete
            </button>
        </div>
    </div>
</div>
