<div id="editModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden" style="margin-top: -50px">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">Edit Designation</h3>
                <button class="modal-close-edit z-50">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="editForm" method="POST" enctype="multipart/form-data"
                    action="{{ route('role.attendence-settings.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'attendence_setting' => 1]) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="editItemId" name="id">
                    <div>
                        <label for="edit_time_before_checkin" class="block text-gray-700 text-sm font-medium mb-2">Time Before Checkin</label>
                        <input type="number" id="edit_time_before_checkin" name="time_before_checkin"
                            class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <span>(in minute) this time will not counted as late</span>
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a time</p>
                    </div>
                    <div>
                        <label for="edit_time_after_checkin" class="block text-gray-700 text-sm font-medium mb-2">Time After Checkin</label>
                        <input type="number" id="edit_time_after_checkin" name="time_after_checkin"
                            class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <span>(in minute) this time will not counted as late</span>
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a time</p>
                    </div>
                    <div>
                        <label for="edit_time_before_checkout" class="block text-gray-700 text-sm font-medium mb-2">Time Before Checkout</label>
                        <input type="number" id="edit_time_before_checkout" name="time_before_checkout"
                            class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <span>(in minute) this time will not counted as early leave</span>
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a time</p>
                    </div>
                    <div>
                        <label for="edit_time_after_checkout" class="block text-gray-700 text-sm font-medium mb-2">Time After Checkout</label>
                        <input type="number" id="edit_time_after_checkout" name="time_after_checkout"
                            class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <span>(in minute) this time will not counted as overtime</span>
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a time</p>
                    </div>
                </form>
            </div>
            <div class="modal-footer flex justify-end pt-2">
                <button type="button" class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-edit">
                    Cancel
                </button>
                <button data-action="{{ route('role.attendence-settings.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'attendence_setting' => 1]) }}" id="editSubmit" type="button" class="btn btn-primary px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200">
                    Update
                </button>
            </div>
        </div>
    </div>
</div>
