<div id="editModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden" style="margin-top: -50px">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">Edit Proposal</h3>
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
                            <label for="edit_deal_id" class="block text-gray-700 text-sm font-medium mb-2">Deal</label>
                            <select id="edit_deal_id" name="deal_id" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Deal</option>
                                @foreach($deals as $deal)
                                    <option value="{{ $deal->id }}">{{ $deal->deal_name }}</option>
                                @endforeach
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Deal</p>
                        </div>
                        <div>
                            <label for="edit_proposal_no" class="block text-gray-700 text-sm font-medium mb-2">Proposal No</label>
                            <input type="text" id="edit_proposal_no" name="proposal_no" placeholder="Enter proposal number"
                            class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter proposal number</p>
                        </div>
                        <div>
                            <label for="edit_proposal_date" class="block text-gray-700 text-sm font-medium mb-2">Proposal Date</label>
                            <input type="date" id="edit_proposal_date" name="proposal_date" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose proposal date</p>
                        </div>
                        <div>
                            <label for="edit_valid_until" class="block text-gray-700 text-sm font-medium mb-2">Valid Until</label>
                                <input type="date" id="edit_valid_until" name="valid_until" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose valid until date</p>
                        </div>
                        <div>
                            <label for="edit_status" class="block text-gray-700 text-sm font-medium mb-2">Status</label>
                            <select id="edit_status" name="status" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Status</option>
                                <option value="draft">Draft</option>
                                <option value="sent">Sent</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a status</p>
                        </div>
                        
                        <div>
                            <label for="edit_terms" class="block text-gray-700 text-sm font-medium mb-2">Terms</label>
                            <textarea name="terms" id="edit_terms" placeholder="Enter terms"
                            class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter terms</p>
                        </div>
                        <div>
                            <label for="edit_description" class="block text-gray-700 text-sm font-medium mb-2">Description</label>
                            <textarea id="edit_description" name="description" placeholder="Enter description"
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
                <button data-action="{{ route('role.proposals.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'proposal' => 1]) }}" id="editSubmit" type="button" class="btn btn-primary px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200">
                    Update
                </button>
            </div>
        </div>
    </div>
</div>
