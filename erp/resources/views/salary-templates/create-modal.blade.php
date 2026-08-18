<div id="createModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden" style="margin-top: -50px">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">Add New Template</h3>
                <button class="modal-close-create z-50">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form class="closest" id="createForm"
                    action="{{ route('role.salary-templates.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="create_name" class="block text-gray-700 text-sm font-medium mb-2">Name</label>
                            <input type="text" id="create_name" name="name"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="e.g., Grade A, Manager, Senior Developer">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a name</p>
                        </div>
                        <div class="">
                            <label for="create_basic_salary" class="block text-gray-700 text-sm font-medium mb-2">Basic Salary</label>
                            <input type="number" id="create_basic_salary" name="basic_salary"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter Amount">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Basic Salary</p>
                        </div>
                        <div class="mt-2">
                            <label for="create_house_rent" class="block text-gray-700 text-sm font-medium mb-2">House Rent</label>
                            <input type="number" id="create_house_rent" name="house_rent"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter Amount">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a House Rent</p>
                        </div>
                        <div class="mt-2">
                            <label for="create_medical_allowance" class="block text-gray-700 text-sm font-medium mb-2">Medical Allowance</label>
                            <input type="number" id="create_medical_allowance" name="medical_allowance"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter Amount">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Medical Allowance</p>
                        </div>
                        <div class="mt-2">
                            <label for="create_conveyance_allowance" class="block text-gray-700 text-sm font-medium mb-2">Conveyance Allowance</label>
                            <input type="number" id="create_conveyance_allowance" name="conveyance_allowance"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter Amount">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Conveyance Allowance</p>
                        </div>
                        <div class="mt-2">
                            <label for="create_other_allowance" class="block text-gray-700 text-sm font-medium mb-2">Other Allowance</label>
                            <input type="number" id="create_other_allowance" name="other_allowance"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter Amount">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Other Allowance</p>
                        </div>
                        <div class="mt-2">
                            <label for="create_bonus" class="block text-gray-700 text-sm font-medium mb-2">Bonus</label>
                            <input type="number" id="create_bonus" name="bonus" value="0"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter Amount">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Bonus</p>
                        </div>
                        <div class="mt-2">
                            <label for="create_total_salary" class="block text-gray-700 text-sm font-medium mb-2">Total Salary</label>
                            <input type="number" id="create_total_salary" name="total_salary"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter Amount">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Total Salary</p>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer flex justify-end pt-2 mt-4">
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
