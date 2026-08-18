<div id="editModal" class="modal fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="fixed inset-0 flex items-center justify-center pointer-events-none">
    <div class="modal-container bg-white w-11/12 md:max-w-lg mx-auto rounded-xl shadow-2xl z-50 overflow-y-auto pointer-events-auto">

        {{-- Modal header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-indigo-600 to-indigo-500 rounded-t-xl">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(255,255,255,0.2)">
                    <i class="fas fa-pen text-sm" style="color:#fff"></i>
                </div>
                <h3 class="font-semibold text-lg" style="color:#fff">Edit Category</h3>
            </div>
            <button class="modal-close-edit w-8 h-8 flex items-center justify-center rounded-lg transition duration-150" style="background:rgba(255,255,255,0.2); color:#fff">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        {{-- Modal body --}}
        <div class="px-6 py-5">
            <form id="editForm" method="POST" enctype="multipart/form-data"
                action="{{ route('role.expense-categories.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'expense_category' => 1]) }}">
                @csrf
                @method('PUT')
                <input type="hidden" id="editItemId" name="id">
                <div class="space-y-4">

                    <div>
                        <label for="edit_company_id" class="block text-sm font-semibold text-gray-700 mb-1.5">
                            Company
                        </label>
                        {{-- Blank means SHARED, not unfilled — see the create modal. --}}
                        <select id="edit_company_id" name="company_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 select2 text-sm" style="width: 100%">
                            <option value="">All companies — shared</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1.5">
                            Moving a shared category to one company takes it out of every other company's expense form.
                        </p>
                    </div>

                    <div>
                        <label for="edit_name" class="block text-sm font-semibold text-gray-700 mb-1.5">
                            Category Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="edit_name" name="name"
                            class="form-input w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                            placeholder="e.g. Office Supplies">
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a name</p>
                    </div>

                    {{-- Which chart-of-accounts expense account this category posts to.
                         Set once here so nobody has to pick a GL code while recording an
                         expense. Left blank, every expense in this category lands in the
                         general expense account and your reporting by account becomes one
                         undifferentiated pile. --}}
                    <div>
                        <label for="edit_account_id" class="block text-sm font-semibold text-gray-700 mb-1.5">
                            Posts To Account
                        </label>
                        <select id="edit_account_id" name="account_id"
                            class="form-select w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 select2 text-sm" style="width: 100%">
                            <option value="">— Not mapped —</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">[{{ $account->code }}] {{ $account->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-gray-500 mt-1">
                            <i class="fas fa-circle-info text-gray-400"></i>
                            Every expense in this category is debited here.
                            @if($fallbackAccount)
                                Leave it unmapped and it falls back to <span class="font-semibold">[{{ $fallbackAccount->code }}] {{ $fallbackAccount->name }}</span>.
                            @endif
                        </p>
                    </div>

                    <div>
                        <label for="edit_description" class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                        <textarea id="edit_description" name="description" rows="3" placeholder="Optional description…"
                            class="w-full rounded-lg border border-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 px-3 py-2 text-sm text-gray-700 outline-none resize-none transition duration-150"></textarea>
                    </div>

                    <div class="flex items-center gap-3 py-1">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="status" id="edit_status" class="sr-only peer" checked>
                            <div class="w-10 h-5 bg-gray-300 peer-checked:bg-indigo-500 rounded-full transition-colors duration-200 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-5"></div>
                        </label>
                        <span class="text-sm font-medium text-gray-700">Active</span>
                    </div>

                </div>
            </form>
        </div>

        {{-- Modal footer --}}
        <div class="flex justify-end gap-3 px-6 py-4 bg-gray-50 rounded-b-xl border-t border-gray-100">
            <button type="button" class="modal-close-edit px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition duration-150">
                Cancel
            </button>
            <button data-action="{{ route('role.expense-categories.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'expense_category' => 1]) }}"
                id="editSubmit" type="button"
                class="px-5 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition duration-150 inline-flex items-center gap-2">
                <i class="fas fa-save"></i>Update Category
            </button>
        </div>

    </div>
    </div>
</div>
