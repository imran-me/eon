{{-- Edit Passport Category Modal --}}
<div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="modal-backdrop fixed inset-0 bg-black/50 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md z-10">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 bg-violet-600 rounded-t-2xl">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center">
                    <i class="fas fa-folder-open text-white"></i>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-white">Edit Category</h3>
                    <p class="text-xs text-violet-100">Update category information</p>
                </div>
            </div>
            <button type="button" class="modal-close-edit w-8 h-8 flex items-center justify-center rounded-lg bg-white/20 hover:bg-white/30 text-white transition">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        {{-- Body --}}
        <form id="editForm" method="POST" enctype="multipart/form-data"
            action="{{ route('role.passport-holder-category.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'passport_holder_category' => 1]) }}">
            @csrf
            @method('PUT')
            <input type="hidden" id="editItemId" name="id">

            <div class="px-6 py-5 space-y-4">

                {{-- Name --}}
                <div>
                    <label for="edit_name" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                        Category Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="edit_name" name="name"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition"
                        placeholder="Enter category name">
                    <p id="edit_name_error" class="text-red-500 text-xs mt-1 hidden">
                        <i class="fas fa-exclamation-circle mr-1"></i>Category name is required
                    </p>
                </div>

                {{-- Description --}}
                <div>
                    <label for="edit_description" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                        Description <span class="text-gray-400 font-normal">(optional)</span>
                    </label>
                    <textarea id="edit_description" name="description" rows="3"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 transition resize-none"
                        placeholder="Brief description about this category..."></textarea>
                </div>

            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button type="button" class="modal-close-edit px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button id="editSubmit" type="button"
                    data-action="{{ route('role.passport-holder-category.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'passport_holder_category' => 1]) }}"
                    class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold text-white bg-violet-600 rounded-xl hover:bg-violet-700 transition shadow-sm">
                    <i class="fas fa-save text-xs"></i> Update Category
                </button>
            </div>
        </form>
    </div>
</div>
