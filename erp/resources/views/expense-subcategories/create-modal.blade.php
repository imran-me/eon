<div id="createModal" class="modal fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="fixed inset-0 flex items-center justify-center pointer-events-none">
    <div class="modal-container bg-white w-11/12 md:max-w-lg mx-auto rounded-xl shadow-2xl z-50 overflow-y-auto pointer-events-auto">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 rounded-t-xl" style="background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%)">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(255,255,255,0.2)">
                    <i class="fas fa-sitemap text-sm" style="color:#fff"></i>
                </div>
                <h3 class="font-semibold text-lg" style="color:#fff">Add New Sub-Category</h3>
            </div>
            <button class="modal-close-create w-8 h-8 flex items-center justify-center rounded-lg transition duration-150" style="background:rgba(255,255,255,0.2); color:#fff">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        {{-- Body --}}
        <div class="px-6 py-5">
            <form class="closest" id="createForm" action="{{ route('role.expense-subcategories.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST">
                @csrf
                <div class="space-y-4">

                    <div>
                        <label for="create_company_id" class="block text-sm font-semibold text-gray-700 mb-1.5">Company</label>
                        <select id="create_company_id" name="company_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 select2 text-sm" style="width:100%">
                            <option value="">— Select Company —</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                        <p id="create_company_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a company</p>
                    </div>

                    <div>
                        <label for="create_expense_category_id" class="block text-sm font-semibold text-gray-700 mb-1.5">
                            Parent Category <span class="text-red-500">*</span>
                        </label>
                        <select id="create_expense_category_id" name="expense_category_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 select2 text-sm" style="width:100%">
                            <option value="">— Select Category —</option>
                            @foreach ($expense_categories as $expense_category)
                                <option value="{{ $expense_category->id }}">{{ $expense_category->name }}</option>
                            @endforeach
                        </select>
                        <p id="create_expense_category_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a parent category</p>
                    </div>

                    <div>
                        <label for="create_name" class="block text-sm font-semibold text-gray-700 mb-1.5">
                            Sub-Category Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="create_name" name="name"
                            class="form-input w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-sm"
                            placeholder="e.g. Stationery">
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a name</p>
                    </div>

                    <div>
                        <label for="create_description" class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                        <textarea id="create_description" name="description" rows="3" placeholder="Optional description…"
                            class="w-full rounded-lg border border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-100 px-3 py-2 text-sm text-gray-700 outline-none resize-none transition duration-150"></textarea>
                    </div>

                    <div class="flex items-center gap-3 py-1">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="status" id="create_status" class="sr-only peer" checked>
                            <div class="w-10 h-5 bg-gray-300 peer-checked:bg-purple-600 rounded-full transition-colors duration-200 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-5"></div>
                        </label>
                        <span class="text-sm font-medium text-gray-700">Active</span>
                    </div>

                </div>
            </form>
        </div>

        {{-- Footer --}}
        <div class="flex justify-end gap-3 px-6 py-4 bg-gray-50 rounded-b-xl border-t border-gray-100">
            <button type="button" class="modal-close-create px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition duration-150">
                Cancel
            </button>
            <button id="createSubmit" type="button" class="px-5 py-2 text-sm font-semibold text-white rounded-lg transition duration-150 inline-flex items-center gap-2" style="background:#7c3aed">
                <i class="fas fa-save"></i>Save Sub-Category
            </button>
        </div>

    </div>
    </div>
</div>
