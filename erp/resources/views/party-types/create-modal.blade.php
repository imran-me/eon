<div id="createModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">Add Party Type</h3>
                <button class="modal-close-create z-50"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form id="createForm"
                      action="{{ route('role.party-types.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                      method="POST">
                    @csrf
                    @if($isSuperAdmin)
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Company <span class="text-red-500">*</span></label>
                        <select name="company_id" id="create_company_id"
                                class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">— Select Company —</option>
                            @foreach($companies as $c)
                                <option value="{{ $c->id }}" {{ $filterCompanyId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Name <span class="text-red-500">*</span></label>
                        <input type="text" id="create_name" name="name"
                               class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="e.g. Customer, Agent, Bank…">
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a name</p>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Maps To (Model)</label>
                        <select id="create_model_class" name="model_class"
                                class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            @foreach($modelOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Select a model to load a dropdown list, or leave as None for free-text entry.</p>
                    </div>
                </form>
            </div>
            <div class="modal-footer flex justify-end pt-2">
                <button type="button" class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 mr-2 modal-close-create">
                    Cancel
                </button>
                <button id="createSubmit" type="button"
                        class="btn btn-primary px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                    Save
                </button>
            </div>
        </div>
    </div>
</div>
