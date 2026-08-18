<div id="editModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden" style="margin-top: -50px">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">Edit Deal Manager</h3>
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
                            <label for="edit_lead_id" class="block text-gray-700 text-sm font-medium mb-2">Lead</label>
                            <select id="edit_lead_id" name="lead_id" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Lead</option>
                                @foreach($leads as $lead)
                                    <option value="{{ $lead->id }}">{{ $lead->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Lead</p>
                        </div>
                        <div>
                            <label for="edit_deal_agent" class="block text-gray-700 text-sm font-medium mb-2">Deal Agent</label>
                            <select id="edit_deal_agent" name="deal_agent" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Deal Agent</option>
                                @foreach($deal_agents as $deal_agent)
                                    <option value="{{ $deal_agent->id }}">{{ $deal_agent->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Deal Agent</p>
                        </div>
                        <div>
                            <label for="edit_deal_watcher" class="block text-gray-700 text-sm font-medium mb-2">Deal Watcher</label>
                            <select id="edit_deal_watcher" name="deal_watcher" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Deal Watcher</option>
                                @foreach($deal_watchers as $deal_watcher)
                                    <option value="{{ $deal_watcher->id }}">{{ $deal_watcher->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Deal Watcher</p>
                        </div>
                        <div>
                            <label for="edit_deal_name" class="block text-gray-700 text-sm font-medium mb-2">Deal Name</label>
                            <input type="text" id="edit_deal_name" name="deal_name" placeholder="Enter Deal Name"
                            class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Deal Name</p>
                        </div>
                        <div>
                            <label for="edit_product_id" class="block text-gray-700 text-sm font-medium mb-2">Products</label>
                            <select id="edit_product_id" name="product_id" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Product</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Product</p>
                        </div>
                        {{-- <div>
                            <label for="edit_pipeline" class="block text-gray-700 text-sm font-medium mb-2">Pipeline</label>
                            <input type="text" id="edit_pipeline" name="pipeline"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a pipeline</p>
                        </div> --}}
                        <div>
                            <label for="edit_stage" class="block text-gray-700 text-sm font-medium mb-2">Select Stage</label>
                            <select id="edit_stage" name="stage" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Stage</option>
                                <option value="generated">Generated</option>
                                <option value="qualified">Qualified</option>
                                <option value="initial_contact">Initial Contact</option>
                                <option value="schedule_appointment">Schedule Appointment</option>
                                <option value="proposal_sent">Proposal Sent</option>
                                <option value="win">Win</option>
                                <option value="lost">Lost</option>
                            </select>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Stage</p>
                        </div>
                        <div>
                            <label for="edit_amount" class="block text-gray-700 text-sm font-medium mb-2">Amount</label>
                            <input type="text" id="edit_amount" name="amount" placeholder="Enter Amount"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter an amount</p>
                        </div>
                        <div>
                            <label for="edit_closing_date" class="block text-gray-700 text-sm font-medium mb-2">Closing Date</label>
                            <input type="date" id="edit_closing_date" name="closing_date"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a closing date</p>
                        </div>
                        <div>
                            <label for="edit_notes" class="block text-gray-700 text-sm font-medium mb-2">Notes</label>
                            <textarea id="edit_notes" name="notes" placeholder="Enter notes"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a note</p>
                        </div>
                    </div>  
                </form>
            </div>
            <div class="modal-footer flex justify-end pt-2">
                <button type="button" class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-edit">
                    Cancel
                </button>
                <button data-action="{{ route('role.deals.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'deal' => 1]) }}" id="editSubmit" type="button" class="btn btn-primary px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200">
                    Update
                </button>
            </div>
        </div>
    </div>
</div>
