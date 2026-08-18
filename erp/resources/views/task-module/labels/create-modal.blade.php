<div id="createModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden" style="margin-top: -50px">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 mx-auto rounded shadow-lg z-50 overflow-y-auto" style="max-width: 700px; max-height: 90vh;">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">
                    Add New Label
                </h3>
                <button class="modal-close-create z-50 text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="modal-body mt-4">
                <form id="createForm" action="{{ route('role.labels.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label for="project_id" class="block text-gray-700 text-sm font-semibold mb-2">Select Project<span class="text-red-500">*</span>
                        </label>
                        <select id="project_id" name="project_id" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" style="width: 100%">
                            <option value="">Select project</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}">{{ $project->project_name }}</option>
                            @endforeach
                        </select>
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a project</p>
                    </div>
                    <div class="mb-4">
                        <label for="name" class="block text-gray-700 text-sm font-semibold mb-2">Name<span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="name" name="name" placeholder="Enter label name"
                        class="form-input w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a label name</p>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-semibold mb-3">Color</label>
                        <input type="hidden" name="color" id="create_color" value="blue">
                        <div class="grid grid-cols-5 gap-3">
                            <label class="cursor-pointer">
                                <input type="radio" name="color_preset" value="gray" class="hidden color-radio">
                                <div class="color-option w-full h-16 rounded-lg flex items-center justify-center transition-all duration-200 hover:scale-105" style="background: linear-gradient(135deg, #F3F4F6 0%, #9CA3AF 100%);">
                                    <span class="text-gray-700 font-medium text-sm">Gray</span>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="color_preset" value="blue" class="hidden color-radio" checked>
                                <div class="color-option w-full h-16 rounded-lg flex items-center justify-center transition-all duration-200 hover:scale-105" style="background: linear-gradient(135deg, #DBEAFE 0%, #60A5FA 100%);">
                                    <span class="text-blue-700 font-medium text-sm">Blue</span>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="color_preset" value="purple" class="hidden color-radio">
                                <div class="color-option w-full h-16 rounded-lg flex items-center justify-center transition-all duration-200 hover:scale-105" style="background: linear-gradient(135deg, #EDE9FE 0%, #A78BFA 100%);">
                                    <span class="text-purple-700 font-medium text-sm">Purple</span>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="color_preset" value="green" class="hidden color-radio">
                                <div class="color-option w-full h-16 rounded-lg flex items-center justify-center transition-all duration-200 hover:scale-105" style="background: linear-gradient(135deg, #D1FAE5 0%, #34D399 100%);">
                                    <span class="text-green-700 font-medium text-sm">Green</span>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="color_preset" value="yellow" class="hidden color-radio">
                                <div class="color-option w-full h-16 rounded-lg flex items-center justify-center transition-all duration-200 hover:scale-105" style="background: linear-gradient(135deg, #FEF3C7 0%, #FBBF24 100%);">
                                    <span class="text-yellow-700 font-medium text-sm">Yellow</span>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="color_preset" value="red" class="hidden color-radio">
                                <div class="color-option w-full h-16 rounded-lg flex items-center justify-center transition-all duration-200 hover:scale-105" style="background: linear-gradient(135deg, #FEE2E2 0%, #F87171 100%);">
                                    <span class="text-red-700 font-medium text-sm">Red</span>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="color_preset" value="indigo" class="hidden color-radio">
                                <div class="color-option w-full h-16 rounded-lg flex items-center justify-center transition-all duration-200 hover:scale-105" style="background: linear-gradient(135deg, #E0E7FF 0%, #818CF8 100%);">
                                    <span class="text-indigo-700 font-medium text-sm">Indigo</span>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="color_preset" value="pink" class="hidden color-radio">
                                <div class="color-option w-full h-16 rounded-lg flex items-center justify-center transition-all duration-200 hover:scale-105" style="background: linear-gradient(135deg, #FCE7F3 0%, #F472B6 100%);">
                                    <span class="text-pink-700 font-medium text-sm">Pink</span>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="color_preset" value="orange" class="hidden color-radio">
                                <div class="color-option w-full h-16 rounded-lg flex items-center justify-center transition-all duration-200 hover:scale-105" style="background: linear-gradient(135deg, #FFEDD5 0%, #FB923C 100%);">
                                    <span class="text-orange-700 font-medium text-sm">Orange</span>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="color_preset" value="teal" class="hidden color-radio">
                                <div class="color-option w-full h-16 rounded-lg flex items-center justify-center transition-all duration-200 hover:scale-105" style="background: linear-gradient(135deg, #CCFBF1 0%, #2DD4BF 100%);">
                                    <span class="text-teal-700 font-medium text-sm">Teal</span>
                                </div>
                            </label>
                        </div>
                        <div class="mt-3 flex items-center gap-3 p-3 border border-gray-200 rounded-lg">
                            <label for="create_custom_color" class="text-sm font-semibold text-gray-700">Custom Color</label>
                            <input type="color" id="create_custom_color" value="#3b82f6" class="custom-color-picker" style="width:42px;height:32px;border:1px solid #e5e7eb;border-radius:6px;padding:2px;cursor:pointer;">
                            <span id="create_custom_color_value" class="text-xs font-mono text-gray-500">#3b82f6</span>
                        </div>
                        <p class="text-gray-500 text-xs mt-2">Choose a preset or pick a custom color</p>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer flex justify-end pt-4 mt-4">
                <button type="button" class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-create">
                    Cancel
                </button>
                <button id="createSubmit" type="button" class="btn btn-primary px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-200 shadow">
                    Submit
                </button>
            </div>
        </div>
    </div>
</div>
