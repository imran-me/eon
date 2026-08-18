<div id="createModal" class="modal fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="flex min-h-full items-center justify-center p-4">
    <div class="modal-container relative bg-white w-full max-w-3xl mx-auto rounded shadow-lg z-50">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">Add New Company</h3>
                <button class="modal-close-create z-50">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form class="closest" id="createForm"
                    action="{{ route('role.company-settings.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Name -->
                        <div>
                            <label for="create_name" class="block text-gray-700 text-sm font-medium mb-2">Name <span class="text-red-500">*</span></label>
                            <input type="text" id="create_name" name="name"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter Company Name">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Name</p>
                        </div>

                        <!-- Short Name -->
                        <div>
                            <label for="create_short_name" class="block text-gray-700 text-sm font-medium mb-2">Short Name</label>
                            <input type="text" id="create_short_name" name="short_name" maxlength="50"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="e.g. EPAL">
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="create_email" class="block text-gray-700 text-sm font-medium mb-2">Email <span class="text-red-500">*</span></label>
                            <input type="email" id="create_email" name="email"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter Email">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter an Email</p>
                        </div>

                        <!-- Phone -->
                        <div>
                            <label for="create_phone" class="block text-gray-700 text-sm font-medium mb-2">Phone <span class="text-red-500">*</span></label>
                            <input type="text" id="create_phone" name="phone"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter Phone">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Phone</p>
                        </div>

                        <!-- Website -->
                        <div>
                            <label for="create_website" class="block text-gray-700 text-sm font-medium mb-2">Website</label>
                            <input type="text" id="create_website" name="website"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter Website">
                        </div>

                        <div>
                            <label for="create_attendance_device_serial_no" class="block text-gray-700 text-sm font-medium mb-2">Attendance Device Serial No</label>
                            <input type="text" id="create_attendance_device_serial_no" name="attendance_device_serial_no"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter Serial No">
                        </div>

                        <div>
                            <label for="create_company_order" class="block text-gray-700 text-sm font-medium mb-2">Company Order</label>
                            <input type="number" id="create_company_order" name="company_order"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter Order">
                        </div>
                    </div>

                    <!-- Address (Full width) -->
                    <div class="mt-4">
                        <label for="create_address" class="block text-gray-700 text-sm font-medium mb-2">Address</label>
                        <textarea id="create_address" name="address" rows="3" placeholder="Enter Address"
                            class="w-full rounded-md border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 p-3 text-sm text-gray-700 outline-none resize-none transition duration-150"></textarea>
                    </div>

                    <!-- Logo & Icon side by side -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label for="create_logo" class="block text-gray-700 text-sm font-medium mb-2">Logo</label>
                            <input type="file" id="create_logo" name="logo" accept="image/*" style="padding: 10px"
                                class="block w-full text-sm text-gray-700 border-2 border-gray-200 rounded-md cursor-pointer bg-gray-50 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-150">
                            <p class="text-gray-400 text-xs mt-1">PNG, JPG (max 2MB)</p>
                        </div>
                        <div>
                            <label for="create_icon" class="block text-gray-700 text-sm font-medium mb-2">Icon <span class="text-gray-400 text-xs">(favicon / small icon)</span></label>
                            <input type="file" id="create_icon" name="icon" accept="image/*" style="padding: 10px"
                                class="block w-full text-sm text-gray-700 border-2 border-gray-200 rounded-md cursor-pointer bg-gray-50 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition duration-150">
                            <p class="text-gray-400 text-xs mt-1">PNG, ICO (max 1MB)</p>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="mt-4">
                        <label class="flex items-center">
                            <input type="checkbox" id="create_status" name="status" class="form-checkbox h-5 w-5 text-blue-600" checked>
                            <span class="ml-2 text-gray-700">Active</span>
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer flex justify-end pt-2">
                <button type="button" class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-create">
                    Cancel
                </button>
                <button id="createSubmit" type="button" class="btn btn-primary px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200">
                    Submit
                </button>
            </div>
        </div>
    </div>
    </div>
</div>
