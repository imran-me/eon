{{-- Create Passport Category Modal --}}
<div id="createModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="modal-backdrop fixed inset-0 bg-black/50 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md z-10">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 bg-blue-600 rounded-t-2xl">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center">
                    <i class="fas fa-folder-plus text-white"></i>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-white">Add New Category</h3>
                    <p class="text-xs text-blue-100">Create a passport holder category</p>
                </div>
            </div>
            <button type="button" class="modal-close-create w-8 h-8 flex items-center justify-center rounded-lg bg-white/20 hover:bg-white/30 text-white transition">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        {{-- Body --}}
        <form id="createForm"
            action="{{ route('role.passport-holder-category.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
            method="POST" enctype="multipart/form-data">
            @csrf

            <div class="px-6 py-5 space-y-4">

                {{-- Name --}}
                <div>
                    <label for="create_name" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                        Category Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="create_name" name="name"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 transition"
                        placeholder="e.g. General, Hajj, Umrah, Student...">
                    <p id="create_name_error" class="text-red-500 text-xs mt-1 hidden">
                        <i class="fas fa-exclamation-circle mr-1"></i>Category name is required
                    </p>
                </div>

                {{-- Description --}}
                <div>
                    <label for="create_description" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                        Description <span class="text-gray-400 font-normal">(optional)</span>
                    </label>
                    <textarea id="create_description" name="description" rows="3"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 transition resize-none"
                        placeholder="Brief description about this category..."></textarea>
                </div>

            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button type="button" class="modal-close-create px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button id="createSubmit" type="button"
                    class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-sm">
                    <i class="fas fa-check text-xs"></i> Save Category
                </button>
            </div>
        </form>
    </div>
</div>
