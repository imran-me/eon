{{--
    The six forms behind the three Add / Edit buttons, plus one shared delete
    confirmation.

    Every id is prefixed dept_ / cat_ / sub_. The standalone pages each used
    #createModal, #editForm, #createSubmit and so on, so putting all three on one
    page verbatim would have wired every Save button to whichever form the browser
    found first.

    Each form posts to its own existing controller — same URLs, same validation,
    same company rules as before. Edits carry BOTH `id` and `item_id` because the
    three update() methods disagree about which one they read.
--}}

{{-- ══════════ 1. Department ══════════ --}}

<div id="deptCreateModal" class="cls-modal modal fixed inset-0 z-50 hidden">
    <div class="cls-modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4 pointer-events-none">
        <div class="bg-white w-full md:max-w-lg rounded-2xl shadow-2xl z-50 max-h-[90vh] overflow-y-auto pointer-events-auto">
            <div class="flex justify-between items-center px-6 py-4 border-b border-gray-100">
                <h3 class="text-base font-bold text-gray-800"><i class="fas fa-sitemap mr-2 text-indigo-500"></i>New Department</h3>
                <button type="button" data-close="deptCreateModal" class="text-gray-400 hover:text-gray-700"><i class="fas fa-times"></i></button>
            </div>
            <div class="px-6 py-5">
                <form id="deptCreateForm" action="{{ route('role.expense-departments.store', ['role' => $role]) }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label for="dept_create_company_id" class="block text-sm font-semibold text-gray-700 mb-1.5">Company <span class="text-red-500">*</span></label>
                        <select id="dept_create_company_id" name="company_id" class="cls-select2 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->short_name ?: $company->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-gray-500 mt-1">A department belongs to one company — that is what fills the company in on the expense form.</p>
                    </div>
                    <div class="mb-4">
                        <label for="dept_create_name" class="block text-sm font-semibold text-gray-700 mb-1.5">Department Name <span class="text-red-500">*</span></label>
                        <input type="text" id="dept_create_name" name="name" maxlength="255" placeholder="e.g. Site, Procurement, Marketing"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <p id="dept_create_name_msg" class="text-red-500 text-xs mt-1 hidden">Please give the department a name</p>
                    </div>
                    <div class="mb-4">
                        <label for="dept_create_description" class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                        <input type="text" id="dept_create_description" name="description" maxlength="255" placeholder="Optional"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="dept_create_status" class="block text-sm font-semibold text-gray-700 mb-1.5">Status</label>
                        <select id="dept_create_status" name="status" class="cls-select2 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button type="button" data-close="deptCreateModal" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50">Cancel</button>
                <button type="button" id="deptCreateSubmit" class="px-4 py-2 text-white rounded-lg text-sm font-semibold" style="background:#4f46e5">Create</button>
            </div>
        </div>
    </div>
</div>

<div id="deptEditModal" class="cls-modal modal fixed inset-0 z-50 hidden">
    <div class="cls-modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4 pointer-events-none">
        <div class="bg-white w-full md:max-w-lg rounded-2xl shadow-2xl z-50 max-h-[90vh] overflow-y-auto pointer-events-auto">
            <div class="flex justify-between items-center px-6 py-4 border-b border-gray-100">
                <h3 class="text-base font-bold text-gray-800"><i class="fas fa-pen mr-2 text-indigo-500"></i>Edit Department</h3>
                <button type="button" data-close="deptEditModal" class="text-gray-400 hover:text-gray-700"><i class="fas fa-times"></i></button>
            </div>
            <div class="px-6 py-5">
                <form id="deptEditForm" action="" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="dept_edit_id" name="id">
                    <input type="hidden" id="dept_edit_item_id" name="item_id">
                    <div class="mb-4">
                        <label for="dept_edit_company_id" class="block text-sm font-semibold text-gray-700 mb-1.5">Company <span class="text-red-500">*</span></label>
                        <select id="dept_edit_company_id" name="company_id" class="cls-select2 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->short_name ?: $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="dept_edit_name" class="block text-sm font-semibold text-gray-700 mb-1.5">Department Name <span class="text-red-500">*</span></label>
                        <input type="text" id="dept_edit_name" name="name" maxlength="255"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <p id="dept_edit_name_msg" class="text-red-500 text-xs mt-1 hidden">Please give the department a name</p>
                    </div>
                    <div class="mb-4">
                        <label for="dept_edit_description" class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                        <input type="text" id="dept_edit_description" name="description" maxlength="255"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="dept_edit_status" class="block text-sm font-semibold text-gray-700 mb-1.5">Status</label>
                        <select id="dept_edit_status" name="status" class="cls-select2 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button type="button" data-close="deptEditModal" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50">Cancel</button>
                <button type="button" id="deptEditSubmit" class="px-4 py-2 text-white rounded-lg text-sm font-semibold" style="background:#4f46e5">Save changes</button>
            </div>
        </div>
    </div>
</div>

{{-- ══════════ 2. Category ══════════ --}}

<div id="catCreateModal" class="cls-modal modal fixed inset-0 z-50 hidden">
    <div class="cls-modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4 pointer-events-none">
        <div class="bg-white w-full md:max-w-lg rounded-2xl shadow-2xl z-50 max-h-[90vh] overflow-y-auto pointer-events-auto">
            <div class="flex justify-between items-center px-6 py-4 border-b border-gray-100">
                <h3 class="text-base font-bold text-gray-800"><i class="fas fa-folder mr-2 text-teal-600"></i>New Category</h3>
                <button type="button" data-close="catCreateModal" class="text-gray-400 hover:text-gray-700"><i class="fas fa-times"></i></button>
            </div>
            <div class="px-6 py-5">
                <form id="catCreateForm" action="{{ route('role.expense-categories.store', ['role' => $role]) }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label for="cat_create_company_id" class="block text-sm font-semibold text-gray-700 mb-1.5">Company</label>
                        {{-- Blank is not "unfilled" here, it is SHARED — the normal case,
                             so it is named and selected rather than hiding behind a
                             "— Select Company —" that reads as an omission. --}}
                        <select id="cat_create_company_id" name="company_id" class="cls-select2 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                            <option value="">All companies — shared</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->short_name ?: $company->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-gray-500 mt-1">Leave as <span class="font-semibold">All companies</span> unless only one company ever spends on it.</p>
                    </div>
                    <div class="mb-4">
                        <label for="cat_create_name" class="block text-sm font-semibold text-gray-700 mb-1.5">Category Name <span class="text-red-500">*</span></label>
                        <input type="text" id="cat_create_name" name="name" placeholder="e.g. Office Supplies"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                        <p id="cat_create_name_msg" class="text-red-500 text-xs mt-1 hidden">Please enter a name</p>
                    </div>
                    <div class="mb-4">
                        <label for="cat_create_account_id" class="block text-sm font-semibold text-gray-700 mb-1.5">Posts To Account</label>
                        <select id="cat_create_account_id" name="account_id" class="cls-select2 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                            <option value="">— Not mapped —</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">[{{ $account->code }}] {{ $account->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-gray-500 mt-1">
                            <i class="fas fa-circle-info text-gray-400"></i> Every expense in this category is debited here.
                            @if($fallbackAccount)
                                Left unmapped it falls back to <span class="font-semibold">[{{ $fallbackAccount->code }}] {{ $fallbackAccount->name }}</span>.
                            @endif
                        </p>
                    </div>
                    <div class="mb-4">
                        <label for="cat_create_description" class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                        <textarea id="cat_create_description" name="description" rows="2" placeholder="Optional description…"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm resize-none focus:outline-none focus:ring-2 focus:ring-teal-500"></textarea>
                    </div>
                    <div>
                        <label for="cat_create_status" class="block text-sm font-semibold text-gray-700 mb-1.5">Status</label>
                        <select id="cat_create_status" name="status" class="cls-select2 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button type="button" data-close="catCreateModal" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50">Cancel</button>
                <button type="button" id="catCreateSubmit" class="px-4 py-2 text-white rounded-lg text-sm font-semibold" style="background:#0d9488">Create</button>
            </div>
        </div>
    </div>
</div>

<div id="catEditModal" class="cls-modal modal fixed inset-0 z-50 hidden">
    <div class="cls-modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4 pointer-events-none">
        <div class="bg-white w-full md:max-w-lg rounded-2xl shadow-2xl z-50 max-h-[90vh] overflow-y-auto pointer-events-auto">
            <div class="flex justify-between items-center px-6 py-4 border-b border-gray-100">
                <h3 class="text-base font-bold text-gray-800"><i class="fas fa-pen mr-2 text-teal-600"></i>Edit Category</h3>
                <button type="button" data-close="catEditModal" class="text-gray-400 hover:text-gray-700"><i class="fas fa-times"></i></button>
            </div>
            <div class="px-6 py-5">
                <form id="catEditForm" action="" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="cat_edit_id" name="id">
                    <input type="hidden" id="cat_edit_item_id" name="item_id">
                    <div class="mb-4">
                        <label for="cat_edit_company_id" class="block text-sm font-semibold text-gray-700 mb-1.5">Company</label>
                        <select id="cat_edit_company_id" name="company_id" class="cls-select2 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                            <option value="">All companies — shared</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->short_name ?: $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="cat_edit_name" class="block text-sm font-semibold text-gray-700 mb-1.5">Category Name <span class="text-red-500">*</span></label>
                        <input type="text" id="cat_edit_name" name="name"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                        <p id="cat_edit_name_msg" class="text-red-500 text-xs mt-1 hidden">Please enter a name</p>
                    </div>
                    <div class="mb-4">
                        <label for="cat_edit_account_id" class="block text-sm font-semibold text-gray-700 mb-1.5">Posts To Account</label>
                        <select id="cat_edit_account_id" name="account_id" class="cls-select2 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                            <option value="">— Not mapped —</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">[{{ $account->code }}] {{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="cat_edit_description" class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                        <textarea id="cat_edit_description" name="description" rows="2"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm resize-none focus:outline-none focus:ring-2 focus:ring-teal-500"></textarea>
                    </div>
                    <div>
                        <label for="cat_edit_status" class="block text-sm font-semibold text-gray-700 mb-1.5">Status</label>
                        <select id="cat_edit_status" name="status" class="cls-select2 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button type="button" data-close="catEditModal" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50">Cancel</button>
                <button type="button" id="catEditSubmit" class="px-4 py-2 text-white rounded-lg text-sm font-semibold" style="background:#0d9488">Save changes</button>
            </div>
        </div>
    </div>
</div>

{{-- ══════════ 3. Sub-category ══════════ --}}

<div id="subCreateModal" class="cls-modal modal fixed inset-0 z-50 hidden">
    <div class="cls-modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4 pointer-events-none">
        <div class="bg-white w-full md:max-w-lg rounded-2xl shadow-2xl z-50 max-h-[90vh] overflow-y-auto pointer-events-auto">
            <div class="flex justify-between items-center px-6 py-4 border-b border-gray-100">
                <h3 class="text-base font-bold text-gray-800"><i class="fas fa-folder-tree mr-2 text-purple-600"></i>New Sub-category</h3>
                <button type="button" data-close="subCreateModal" class="text-gray-400 hover:text-gray-700"><i class="fas fa-times"></i></button>
            </div>
            <div class="px-6 py-5">
                <form id="subCreateForm" action="{{ route('role.expense-subcategories.store', ['role' => $role]) }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label for="sub_create_company_id" class="block text-sm font-semibold text-gray-700 mb-1.5">Company</label>
                        <select id="sub_create_company_id" name="company_id" class="cls-select2 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">— Select Company —</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->short_name ?: $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="sub_create_expense_category_id" class="block text-sm font-semibold text-gray-700 mb-1.5">Parent Category <span class="text-red-500">*</span></label>
                        {{-- The full active list, not the filtered page above: you must be
                             able to attach to a category the current filter hides. --}}
                        <select id="sub_create_expense_category_id" name="expense_category_id" class="cls-select2 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">— Select Category —</option>
                            @foreach($parentCategories as $parent)
                                <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                            @endforeach
                        </select>
                        <p id="sub_create_category_msg" class="text-red-500 text-xs mt-1 hidden">Please choose a parent category</p>
                    </div>
                    <div class="mb-4">
                        <label for="sub_create_name" class="block text-sm font-semibold text-gray-700 mb-1.5">Sub-category Name <span class="text-red-500">*</span></label>
                        <input type="text" id="sub_create_name" name="name" placeholder="e.g. Stationery"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <p id="sub_create_name_msg" class="text-red-500 text-xs mt-1 hidden">Please enter a name</p>
                    </div>
                    <div class="mb-4">
                        <label for="sub_create_account_id" class="block text-sm font-semibold text-gray-700 mb-1.5">Posts To Account</label>
                        <select id="sub_create_account_id" name="account_id" class="cls-select2 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">— Use the category's account —</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">[{{ $account->code }}] {{ $account->name }}</option>
                            @endforeach
                        </select>
                        {{-- The narrower answer wins: a phone bill and a broadband bill are
                             both Communication but are not the same ledger line. Left blank
                             the category's account is used, which is right where the chart
                             has no finer line — see ExpenseClassificationService::accountFor(). --}}
                        <p class="text-[11px] text-gray-500 mt-1">
                            <i class="fas fa-circle-info text-gray-400"></i> Overrides the category's account for this sub-category only.
                            Leave blank to follow the category.
                        </p>
                    </div>
                    <div class="mb-4">
                        <label for="sub_create_description" class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                        <textarea id="sub_create_description" name="description" rows="2" placeholder="Optional description…"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm resize-none focus:outline-none focus:ring-2 focus:ring-purple-500"></textarea>
                    </div>
                    <div>
                        <label for="sub_create_status" class="block text-sm font-semibold text-gray-700 mb-1.5">Status</label>
                        <select id="sub_create_status" name="status" class="cls-select2 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button type="button" data-close="subCreateModal" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50">Cancel</button>
                <button type="button" id="subCreateSubmit" class="px-4 py-2 text-white rounded-lg text-sm font-semibold" style="background:#7c3aed">Create</button>
            </div>
        </div>
    </div>
</div>

<div id="subEditModal" class="cls-modal modal fixed inset-0 z-50 hidden">
    <div class="cls-modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4 pointer-events-none">
        <div class="bg-white w-full md:max-w-lg rounded-2xl shadow-2xl z-50 max-h-[90vh] overflow-y-auto pointer-events-auto">
            <div class="flex justify-between items-center px-6 py-4 border-b border-gray-100">
                <h3 class="text-base font-bold text-gray-800"><i class="fas fa-pen mr-2 text-purple-600"></i>Edit Sub-category</h3>
                <button type="button" data-close="subEditModal" class="text-gray-400 hover:text-gray-700"><i class="fas fa-times"></i></button>
            </div>
            <div class="px-6 py-5">
                <form id="subEditForm" action="" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="sub_edit_id" name="id">
                    <input type="hidden" id="sub_edit_item_id" name="item_id">
                    <div class="mb-4">
                        <label for="sub_edit_company_id" class="block text-sm font-semibold text-gray-700 mb-1.5">Company</label>
                        <select id="sub_edit_company_id" name="company_id" class="cls-select2 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">— Select Company —</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->short_name ?: $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="sub_edit_expense_category_id" class="block text-sm font-semibold text-gray-700 mb-1.5">Parent Category <span class="text-red-500">*</span></label>
                        <select id="sub_edit_expense_category_id" name="expense_category_id" class="cls-select2 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">— Select Category —</option>
                            @foreach($parentCategories as $parent)
                                <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                            @endforeach
                        </select>
                        <p id="sub_edit_category_msg" class="text-red-500 text-xs mt-1 hidden">Please choose a parent category</p>
                    </div>
                    <div class="mb-4">
                        <label for="sub_edit_name" class="block text-sm font-semibold text-gray-700 mb-1.5">Sub-category Name <span class="text-red-500">*</span></label>
                        <input type="text" id="sub_edit_name" name="name"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <p id="sub_edit_name_msg" class="text-red-500 text-xs mt-1 hidden">Please enter a name</p>
                    </div>
                    <div class="mb-4">
                        <label for="sub_edit_account_id" class="block text-sm font-semibold text-gray-700 mb-1.5">Posts To Account</label>
                        <select id="sub_edit_account_id" name="account_id" class="cls-select2 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">— Use the category's account —</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">[{{ $account->code }}] {{ $account->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-gray-500 mt-1">
                            <i class="fas fa-circle-info text-gray-400"></i> Overrides the category's account for this sub-category only.
                            Leave blank to follow the category.
                        </p>
                    </div>
                    <div class="mb-4">
                        <label for="sub_edit_description" class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                        <textarea id="sub_edit_description" name="description" rows="2"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm resize-none focus:outline-none focus:ring-2 focus:ring-purple-500"></textarea>
                    </div>
                    <div>
                        <label for="sub_edit_status" class="block text-sm font-semibold text-gray-700 mb-1.5">Status</label>
                        <select id="sub_edit_status" name="status" class="cls-select2 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button type="button" data-close="subEditModal" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50">Cancel</button>
                <button type="button" id="subEditSubmit" class="px-4 py-2 text-white rounded-lg text-sm font-semibold" style="background:#7c3aed">Save changes</button>
            </div>
        </div>
    </div>
</div>

{{-- ══════════ Shared delete confirmation ══════════ --}}

<div id="clsDeleteModal" class="cls-modal modal fixed inset-0 z-50 hidden">
    <div class="cls-modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4 pointer-events-none">
        <div class="bg-white w-full md:max-w-md rounded-2xl shadow-2xl z-50 pointer-events-auto">
            <div class="px-6 py-6 text-center">
                <div class="w-14 h-14 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-triangle-exclamation text-red-500 text-xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900">Delete this <span id="clsDeleteKind">record</span>?</h3>
                <p class="text-sm text-gray-500 mt-1.5">
                    <span id="clsDeleteName" class="font-semibold text-gray-700"></span> will be removed.
                </p>
                <p id="clsDeleteSubs" class="hidden mt-3 text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                    <i class="fas fa-circle-info"></i>
                    It has <span class="font-bold">0</span> sub-categories, which would be left pointing at a category that no longer exists.
                </p>
            </div>
            <div class="flex justify-center gap-2 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button type="button" data-close="clsDeleteModal" class="px-5 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50">Cancel</button>
                <button type="button" id="clsDeleteConfirm" class="px-5 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700">Delete</button>
            </div>
        </div>
    </div>
</div>
