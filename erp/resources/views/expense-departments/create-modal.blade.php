{{-- New expense department. Company is required: a department that belongs to no
     company cannot fill the company in on the expense form, which is the whole
     reason this list is separate from HR's. --}}
<div id="createModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="bg-white w-11/12 md:max-w-lg mx-auto rounded-2xl shadow-lg z-50 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-bold text-gray-800"><i class="fas fa-sitemap mr-2 text-blue-500"></i>New Expense Department</h3>
            <button class="modal-close-create text-gray-400 hover:text-gray-700"><i class="fas fa-times"></i></button>
        </div>
        <div class="px-6 py-5">
            <form id="createForm" action="{{ route('role.expense-departments.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label for="create_company_id" class="block text-sm font-semibold text-gray-700 mb-1.5">Company <span class="text-red-500">*</span></label>
                    <select id="create_company_id" name="company_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->short_name ?: $company->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-4">
                    <label for="create_name" class="block text-sm font-semibold text-gray-700 mb-1.5">Department Name <span class="text-red-500">*</span></label>
                    <input type="text" id="create_name" name="name" maxlength="255" placeholder="e.g. Site, Procurement, Marketing"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p id="create_name_msg" class="text-red-500 text-xs mt-1 hidden">Please give the department a name</p>
                </div>
                <div class="mb-4">
                    <label for="create_description" class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                    <input type="text" id="create_description" name="description" maxlength="255" placeholder="Optional"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="create_status" class="block text-sm font-semibold text-gray-700 mb-1.5">Status</label>
                    <select id="create_status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100">
            <button type="button" class="modal-close-create px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-200">Cancel</button>
            <button id="createSubmit" type="button" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700">Create</button>
        </div>
    </div>
</div>
