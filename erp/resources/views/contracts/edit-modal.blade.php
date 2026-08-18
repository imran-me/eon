<div id="editModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden" style="margin-top: -50px">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">Edit Contracts</h3>
                <button class="modal-close-edit z-50">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="editForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input type="hidden" id="editItemId" name="id">
                        <div>
                            <label for="edit_deal_id" class="block text-gray-700 text-sm font-medium mb-2">Deals</label>
                            <select id="edit_deal_id" name="deal_id" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Deals</option>
                                @foreach($deals as $deal)
                                    <option value="{{ $deal->id }}">{{ $deal->deal_name }}</option>
                                @endforeach
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Project Category</p>
                        </div>
                        <div>
                            <label for="edit_customer_id" class="block text-gray-700 text-sm font-medium mb-2">Customer Name</label>
                            <select id="edit_customer_id" name="customer_id" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Customer Name</option>
                                @foreach($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Customer</p>
                        </div>
                        <div>
                            <label for="edit_project_id" class="block text-gray-700 text-sm font-medium mb-2">Projects</label>
                            <select id="edit_project_id" name="project_id" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Project Name</option>
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}">{{ $project->project_name }}</option>
                                @endforeach
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Project</p>
                        </div>
                        <div>
                            <label for="edit_contract_type_id" class="block text-gray-700 text-sm font-medium mb-2">Contract Types</label>
                            <select id="edit_contract_type_id" name="contract_type_id" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Contract Types</option>
                                @foreach($contractTypes as $contractType)
                                    <option value="{{ $contractType->id }}">{{ $contractType->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Contract Type</p>
                        </div>
                        <div>
                            <label for="edit_contract_no" class="block text-gray-700 text-sm font-medium mb-2">Contract No</label>
                            <input type="text" id="edit_contract_no" name="contract_no" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Please enter a Contract No">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Contract No is required</p>
                        </div>
                        <div>
                            <label for="edit_contract_date" class="block text-gray-700 text-sm font-medium mb-2">Contract Date</label>
                            <input type="date" id="edit_contract_date" name="contract_date"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a contract date</p>
                        </div>
                        <div>
                            <label for="edit_valid_until" class="block text-gray-700 text-sm font-medium mb-2">Valid Until</label>
                            <input type="date" id="edit_valid_until" name="valid_until"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a valid until date</p>
                        </div>
                        <div>
                            <label for="edit_contract_value" class="block text-gray-700 text-sm font-medium mb-2">Contract Value</label>
                            <input type="number" id="edit_contract_value" name="contract_value"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter your contract value">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Contract Value is required</p>
                        </div>
                        <div>
                            <label for="edit_contract_status" class="block text-gray-700 text-sm font-medium mb-2">Select Status</label>
                            <select id="edit_contract_status" name="status" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Status</option>
                                <option value="draft">Draft</option>
                                <option value="signed">Signed</option>
                                <option value="expired">Expired</option>
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Status</p>
                        </div>
                        <div>
                            <label for="edit_description" class="block text-gray-700 text-sm font-medium mb-2">Description</label>
                            <textarea id="edit_description" name="description" placeholder="Enter Description"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a description</p>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer flex justify-end pt-2">
                <button type="button" class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-edit">
                    Cancel
                </button>
                <button data-action="{{ route('role.contracts.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'contract' => 1]) }}" id="editSubmit" type="button" class="btn btn-primary px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200">
                    Update
                </button>
            </div>
        </div>
    </div>
</div>
