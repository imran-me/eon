<div id="editModal" class="modal fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-gray-900/60 backdrop-blur-sm"></div>
    <div class="fixed inset-0 flex items-center justify-center p-3 sm:p-4">
        <div class="modal-container relative bg-white w-full max-w-2xl rounded-2xl shadow-2xl flex flex-col max-h-[95vh] sm:max-h-[90vh] animate-proj-modal">

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 bg-gradient-to-r from-violet-600 to-purple-600 rounded-t-2xl flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-edit text-white text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-white font-semibold text-base leading-tight">Edit Project</h3>
                        <p class="text-purple-100 text-xs">Update the project details</p>
                    </div>
                </div>
                <button class="modal-close-edit w-8 h-8 bg-white/20 hover:bg-white/30 rounded-lg flex items-center justify-center transition-colors">
                    <i class="fas fa-times text-white text-sm"></i>
                </button>
            </div>

            {{-- Body --}}
            <div class="overflow-y-auto flex-1 px-5 py-4 proj-modal-scroll">
                <form id="editForm" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="editItemId" name="id">

                    {{-- Basic Info --}}
                    <div class="mb-5">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Basic Information</span>
                            <div class="flex-1 h-px bg-gray-100"></div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="edit_company_id" class="block text-gray-600 text-xs font-medium mb-1.5">Company</label>
                                <select id="edit_company_id" name="company_id" class="form-select select2 w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50">
                                    <option value="">Select Company</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                                    @endforeach
                                </select>
                                <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Company</p>
                            </div>
                            <div>
                                <label for="edit_project_category_id" class="block text-gray-600 text-xs font-medium mb-1.5">Project Category</label>
                                <select id="edit_project_category_id" name="project_category_id" class="form-select select2 w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50">
                                    <option value="">Select Project Category</option>
                                    @foreach($projectCategories as $projectCategory)
                                        <option value="{{ $projectCategory->id }}" data-company-id="{{ $projectCategory->company_id }}">{{ $projectCategory->name }}</option>
                                    @endforeach
                                </select>
                                <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Project Category</p>
                            </div>
                            <div>
                                <label for="edit_customer_id" class="block text-gray-600 text-xs font-medium mb-1.5">Customer</label>
                                <select id="edit_customer_id" name="customer_id" class="form-select select2 w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50">
                                    <option value="">Select Customer Name</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                    @endforeach
                                </select>
                                <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Customer</p>
                            </div>
                            <div>
                                <label for="edit_project_name" class="block text-gray-600 text-xs font-medium mb-1.5">Project Name</label>
                                <input type="text" id="edit_project_name" name="project_name"
                                    class="form-input w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50"
                                    placeholder="Enter project name">
                                <p class="text-red-500 text-xs mt-1 hidden error-message">Project Name is required</p>
                            </div>
                            <div>
                                <label for="edit_type" class="block text-gray-600 text-xs font-medium mb-1.5">Project Type</label>
                                <select id="edit_type" name="type" class="form-select select2 w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50">
                                    <option value="task_project">Task Project</option>
                                    <option value="lead_project">Sales Project</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Schedule & Status --}}
                    <div class="mb-5">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Schedule & Status</span>
                            <div class="flex-1 h-px bg-gray-100"></div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="edit_start_date" class="block text-gray-600 text-xs font-medium mb-1.5">Start Date</label>
                                <input type="date" id="edit_start_date" name="start_date"
                                    class="form-input w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50">
                                <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a start date</p>
                            </div>
                            <div>
                                <label for="edit_end_date" class="block text-gray-600 text-xs font-medium mb-1.5">End Date</label>
                                <input type="date" id="edit_end_date" name="end_date"
                                    class="form-input w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50">
                                <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter an end date</p>
                            </div>
                            <div>
                                <label for="edit_status" class="block text-gray-600 text-xs font-medium mb-1.5">Project Status</label>
                                <select id="edit_status" name="status" class="form-select select2 w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50">
                                    <option value="">Select Project Status</option>
                                    <option value="not_started">Not Started</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                    <option value="on_hold">On Hold</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                                <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Project Status</p>
                            </div>
                            <div>
                                <label for="edit_color" class="block text-gray-600 text-xs font-medium mb-1.5">Project Color</label>
                                <select id="edit_color" name="color" class="form-select select2 w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50">
                                    <option value="gray">Gray</option>
                                    <option value="blue">Blue</option>
                                    <option value="purple">Purple</option>
                                    <option value="green">Green</option>
                                    <option value="yellow">Yellow</option>
                                    <option value="red">Red</option>
                                    <option value="indigo">Indigo</option>
                                    <option value="pink">Pink</option>
                                    <option value="orange">Orange</option>
                                    <option value="teal">Teal</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Budget & Team --}}
                    <div class="mb-2">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Budget & Team</span>
                            <div class="flex-1 h-px bg-gray-100"></div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="edit_budget" class="block text-gray-600 text-xs font-medium mb-1.5">Budget</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">৳</span>
                                    <input type="number" id="edit_budget" name="budget"
                                        class="form-input w-full pl-7 pr-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50"
                                        placeholder="0.00">
                                </div>
                                <p class="text-red-500 text-xs mt-1 hidden error-message">Budget is required</p>
                            </div>
                            <div>
                                <label for="edit_team_members" class="block text-gray-600 text-xs font-medium mb-1.5">Team Members</label>
                                <select id="edit_team_members" name="team_members[]" class="form-select select2 w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50" multiple>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                    @endforeach
                                </select>
                                <p class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Team Member</p>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="edit_description" class="block text-gray-600 text-xs font-medium mb-1.5">Description</label>
                                <textarea id="edit_description" name="description" rows="3"
                                    class="form-input w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 bg-gray-50 resize-none"
                                    placeholder="Brief description about the project..."></textarea>
                                <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a description</p>
                            </div>
                        </div>
                    </div>

                </form>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-2 px-5 py-4 border-t border-gray-100 bg-gray-50/80 rounded-b-2xl flex-shrink-0">
                <button type="button" class="modal-close-edit px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-100 transition-colors">
                    Cancel
                </button>
                <button data-action="{{ route('role.projects.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'project' => 1]) }}"
                    id="editSubmit" type="button"
                    class="px-5 py-2 text-sm font-medium text-white bg-gradient-to-r from-violet-600 to-purple-600 rounded-xl hover:from-violet-700 hover:to-purple-700 transition-all shadow-sm flex items-center gap-2">
                    <i class="fas fa-save text-xs"></i> Update Project
                </button>
            </div>

        </div>
    </div>
</div>

<style>
@keyframes projModalIn {
    from { opacity: 0; transform: translateY(-10px) scale(0.98); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}
.animate-proj-modal { animation: projModalIn 0.2s ease-out both; }

.proj-modal-scroll { scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; }
.proj-modal-scroll::-webkit-scrollbar { width: 5px; }
.proj-modal-scroll::-webkit-scrollbar-track { background: transparent; }
.proj-modal-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
.proj-modal-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>
