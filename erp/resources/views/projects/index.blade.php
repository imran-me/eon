@extends('layout.app')
@section('meta-information')
    <title>Project Management</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @include('layout.table-design')
@endsection
@section('main-content')
    <!-- States Table -->    
    <div class="states-table bg-white rounded-lg shadow-md overflow-hidden mt-0">
        <div class="states-table-container">
            <div class="states-table-header bg-blue-600 px-6 py-4 flex justify-between items-center">
                <h2 class="states-table-title text-white text-xl font-semibold" style="color: white">
                    <i class="fas fa-list mr-2"></i>Project Manager List
                </h2>
                @can('create project')
                <button class="btn btn-primary create-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add New Project
                </button>
                @endcan
            </div>

            <div class="states-table-content">
                <!-- Success Alert -->
                <form action="" method="get">
                    <div class="filter-container">
                        <div class="filter-header">
                            <h3><i class="fas fa-filter mr-2"></i>Filter Options</h3>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="filter-content">
                            <div class="filter-row">
                                <div class="filter-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Project Name</label>
                                    <input type="text" name="project_name" id="filter_project_name" value="{{ request('project_name') }}" 
                                        class="form-input w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Search by project name">
                                </div>
                                <div class="filter-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Project Category</label>
                                    <select name="project_category_id" id="filter_project_category" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <option value="">All Categories</option>
                                        @foreach($projectCategories as $category)
                                            <option value="{{ $category->id }}" {{ request('project_category_id') == $category->id ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                                    <select name="customer_id" id="filter_customer" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <option value="">All Customers</option>
                                        @foreach($customers as $customer)
                                            <option value="{{ $customer->id }}" {{ request('customer_id') == $customer->id ? 'selected' : '' }}>
                                                {{ $customer->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                    <select name="status" id="filter_status" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <option value="">All Statuses</option>
                                        <option value="not_started" {{ request('status') == 'not_started' ? 'selected' : '' }}>Not Started</option>
                                        <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                                        <option value="on_hold" {{ request('status') == 'on_hold' ? 'selected' : '' }}>On Hold</option>
                                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                    </select>
                                </div>

                                <div class="filter-actions filter-group" style="margin: 0; flex-direction:column">
                                    <div>                                        
                                        <button type="button" class="btn-sm reset-btn">Reset</button>
                                        <button type="submit" class="btn-sm apply-btn">Apply Filters</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Table with Data -->                 
                <div class="table-responsive overflow-x-auto" style="padding: 15px">
                    <table class="table table-hover min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th style="width: 5%" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sl</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timeline</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Color</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                @can('create project')
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Budget</th>
                                @endcan
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Team</th>
                                @canany(['view project', 'edit project', 'delete project'])
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                @endcanany
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($datas as $key => $value)                       
                                @php
                                    $progress = $value->total_tasks > 0 ? (int) round(($value->completed_tasks / $value->total_tasks) * 100) : 0;
                                    $teamMembers = $value->teamMembers();
                                @endphp
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap" style="padding-left: 20px">
                                        <strong>{{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}</strong>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->project_name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->projectCategory->name ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->customer->name ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <small class="text-gray-500">
                                            {{ $value->start_date ? $value->start_date->format('M d, Y') : 'N/A' }} -
                                            {{ $value->end_date ? $value->end_date->format('M d, Y') : 'N/A' }}
                                        </small>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 rounded-lg text-xs font-medium {{ $value->color_details['bg'] }} {{ $value->color_details['text'] }}">
                                            {{ ucfirst($value->color ?? 'blue') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if ($value->status == 'not_started')
                                        <span class="badge text-white bg-gray-500 px-2 py-1 rounded-full text-xs">
                                            Not Started
                                        </span>                                            
                                        @elseif($value->status == 'in_progress')                                            
                                        <span class="badge text-white bg-blue-500 px-2 py-1 rounded-full text-xs">
                                            In Progress
                                        </span>
                                        @elseif($value->status == 'completed')                                            
                                        <span class="badge text-white bg-green-500 px-2 py-1 rounded-full text-xs">
                                            Completed
                                        </span>
                                        @elseif($value->status == 'on_hold')                                            
                                        <span class="badge text-white bg-yellow-500 px-2 py-1 rounded-full text-xs">
                                            On Hold
                                        </span>
                                        @elseif($value->status == 'cancelled')                                            
                                        <span class="badge text-white bg-red-500 px-2 py-1 rounded-full text-xs">
                                            Cancelled
                                        </span>
                                        @endif          
                                    </td>
                                    @can('create project')
                                    <td class="px-6 py-4 whitespace-nowrap">{{ number_format((float) $value->budget, 2) }}</td>
                                    @endcan
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="w-36 bg-gray-200 rounded-full h-2.5 mb-1">
                                            <div class="bg-green-500 h-2.5 rounded-full project-progress-bar" data-progress="{{ $progress }}"></div>
                                        </div>
                                        <small class="text-gray-600">{{ $value->completed_tasks }}/{{ $value->total_tasks }} ({{ $progress }}%)</small>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap font-sans text-sm text-gray-700">
                                        {{ $teamMembers->pluck('name')->take(2)->join(', ') ?: 'No members assigned' }}
                                        @if($teamMembers->count() > 2)
                                            <br><small class="text-gray-500">+{{ $teamMembers->count() - 2 }} more</small>
                                        @endif
                                    </td>
                                    @canany(['view project', 'edit project', 'delete project'])
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="btn-group btn-group-sm flex space-x-1">
                                            <a href="{{ route('role.projects.show', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'project' => $value->id]) }}"
                                               class="btn border border-indigo-500 text-indigo-500 hover:bg-indigo-500 hover:text-white px-3 py-1 rounded-md transition duration-200"
                                               title="Project Overview">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @can('edit project')
                                            <button class="btn btn-outline-primary edit-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200" 
                                                data-item_id="{{ $value->id }}"
                                                data-project_category_id="{{ $value->project_category_id }}"
                                                data-customer_id="{{ $value->customer_id }}"
                                                data-company_id="{{ $value->company_id }}" 
                                                data-deal_name="{{ $value->deal_name }}" 
                                                data-project_name="{{ $value->project_name }}" 
                                                data-start_date="{{ $value->start_date ? $value->start_date->format('Y-m-d') : '' }}" 
                                                data-end_date="{{ $value->end_date ? $value->end_date->format('Y-m-d') : '' }}"
                                                data-color="{{ $value->color ?? 'blue' }}"
                                                data-status="{{ $value->status }}"
                                                data-budget="{{ $value->budget }}"
                                                data-description="{{ $value->description }}"
                                                data-team_members="{{ json_encode($value->team_members) }}"
                                                data-type="{{ $value->type ?? 'task_project' }}"
                                                data-department_id="{{ $value->department_id }}"
                                                data-action="{{ route('role.projects.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'project' => $value->id]) }}" title="Edit Item">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            @endcan
                                            @can('delete project')
                                            <button class="btn btn-outline-danger border border-red-500 text-red-500 hover:bg-red-500 hover:text-white px-3 py-1 rounded-md transition duration-200" onclick="confirmDelete('{{ $value->id }}', '{{ $value->project_name }}')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                                @endcan
                                        </div>
                                    </td>
                                    @endcanany
                                </tr>
                            @empty
                            <tr>
                                <td colspan="11" class="text-center py-8">
                                    <i class="fas fa-inbox fa-3x text-gray-400 mb-4"></i>
                                    <h4 class="text-gray-500 text-xl font-medium mb-2">No data found</h4>
                                    <p class="text-gray-400 mb-4">Try filtering with different datas.</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="p-3 border-t border-gray-200">                    
                    {{ $datas->appends(request()->all())->links() }}
                </div>
            </div>
        </div>
    </div>
    
    @include('projects.create-modal')
    @include('projects.edit-modal')
    @include('projects.delete-modal')
    
</div>

@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // Filter project categories by company
            function filterCategories(companySelectId, categorySelectId, selectedCategoryId) {
                const companyId = $(companySelectId).val();
                const $cat = $(categorySelectId);
                $cat.find('option').each(function() {
                    const optCompany = $(this).data('company-id');
                    if (!$(this).val()) return; // keep placeholder
                    if (!companyId || !optCompany || String(optCompany) === String(companyId)) {
                        $(this).show().prop('disabled', false);
                    } else {
                        $(this).hide().prop('disabled', true);
                    }
                });
                // Re-trigger select2 so hidden options are respected
                if (selectedCategoryId) {
                    $cat.val(selectedCategoryId).trigger('change');
                } else {
                    const currentVal = $cat.val();
                    const currentOptCompany = $cat.find('option:selected').data('company-id');
                    if (companyId && currentOptCompany && String(currentOptCompany) !== String(companyId)) {
                        $cat.val('').trigger('change');
                    }
                }
            }

            // Show create modal
            $('.create-btn').click(function() {
                $('#createModal').removeClass('hidden');
                // Initialize select2 after modal is shown
                $('#createModal .select2').select2();
                filterCategories('#company_id', '#project_category_id', null);
            });

            // Company change in create modal
            $(document).on('change', '#company_id', function() {
                filterCategories('#company_id', '#project_category_id', null);
                $('#project_category_id').select2();
            });

            // Show edit modal
            $('.edit-btn').click(function() {
                const item_id = $(this).data('item_id');
                const project_category_id = $(this).data('project_category_id');
                const customer_id = $(this).data('customer_id');
                const company_id = $(this).data('company_id');
                const project_name = $(this).data('project_name');
                const start_date = $(this).data('start_date');
                const end_date = $(this).data('end_date');
                const color = $(this).data('color') || 'blue';
                const status = $(this).data('status');
                const budget = $(this).data('budget');
                const description = $(this).data('description');
                const team_members = $(this).data('team_members');
                
                $('#editModal').removeClass('hidden');

                // Initialize select2 after modal is shown
                $('#editModal .select2').select2();

                // Set company first, then filter categories, then set category
                $('#editItemId').val(item_id);
                $('#edit_company_id').val(company_id).trigger('change');
                filterCategories('#edit_company_id', '#edit_project_category_id', project_category_id);
                $('#edit_project_category_id').select2();
                $('#edit_customer_id').val(customer_id).trigger('change');
                $('#edit_project_name').val(project_name);
                $('#edit_start_date').val(start_date);
                $('#edit_end_date').val(end_date);
                $('#edit_color').val(color).trigger('change');
                $('#edit_status').val(status).trigger('change');
                $('#edit_budget').val(budget);
                $('#edit_description').val(description);
                $('#edit_team_members').val(team_members).trigger('change');
                const type = $(this).data('type') || 'task_project';
                $('#edit_type').val(type).trigger('change');

                const department_id = $(this).data('department_id');
                $('#edit_department_id').val(department_id).trigger('change');
            });

            // Company change in edit modal
            $(document).on('change', '#edit_company_id', function() {
                filterCategories('#edit_company_id', '#edit_project_category_id', null);
                $('#edit_project_category_id').select2();
            });

            // Close modals
            $('.modal-close-create, .modal-backdrop').click(function(e) {
                if ($(e.target).closest('.modal-close-create').length || $(e.target).hasClass('modal-backdrop')) {
                    $('#createModal').addClass('hidden');
                }
            });

            $('.modal-close-edit, .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-edit').length) {
                    $('#editModal').addClass('hidden');
                }
            });

            $('.modal-close-delete, .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-delete').length) {
                    $('#deleteModal').addClass('hidden');
                }
            });

            // Close success alert
            $('.close-btn').click(function() {
                $(this).closest('.alert').addClass('hidden');
            });

            // Create state form submission
            $('#createSubmit').click(function(e) {
                e.preventDefault();                            
                if (validateCreateForm()) {
                    let formData = new FormData($('#createForm')[0]);
                    $.ajax({
                        url: $('#createForm').attr('action'),
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function (response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Done',
                                    text: 'Data created successfully!'
                                });
                                $('#createModal').addClass('hidden');
                                $('#createForm')[0].reset();
                                setTimeout(() => window.location.reload(), 800);
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Oops...',
                                    text: response.message || 'Something went wrong.'
                                });
                            }
                        },
                        error: function (xhr) {
                            console.error(xhr.responseText);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: 'Failed to create project.'
                            });
                        }
                    });                                    
                }
            });            

            // Edit state form submission
            $('#editSubmit').click(function() {
                if (validateEditForm()) {
                    let formData = new FormData($('#editForm')[0]);
                    $.ajax({
                        url: $(this).data('action'),
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function (response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: "success",
                                    title: "Done",
                                    text: "Data updated successfully!",
                                });
                                $('#editModal').addClass('hidden');
                                setTimeout(() => window.location.reload(), 800);
                            } else {
                                Swal.fire({
                                    icon: "error",
                                    title: "Oops...",
                                    text: response.message || "Update failed.",
                                });
                            }
                        },
                        error: function (xhr) {
                            console.error('❌ Error:', xhr.responseText);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: 'Something went wrong!'
                            });
                        }
                    }); 
                }
            });

            // Delete confirmation
            $('#confirmDeleteBtn').click(function() {
                const dataId = $(this).data('item-id');
                const deleteUrl = $(this).data('action');
                $.ajax({
                    url: deleteUrl,
                    method: 'DELETE',
                    data: {
                        item_id: dataId,
                    },
                    success: function (response) {
                        console.log(response);
                        if (response.success) {
                            Swal.fire({
                                icon: "success",
                                title: "Done",
                                text: "Data deleted successfully!",
                            });
                            $('#deleteModal').addClass('hidden');
                            console.log('trigger reload');                                
                            setTimeout(() => {
                                window.location.reload();
                            }, 500);
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Opps...",
                                text: response.message,
                            });
                        }
                    },
                    error: function (xhr) {
                        console.error('❌ Error:', xhr.responseText);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'Something went wrong!'
                        });
                    }
                });  
            });
        });
        
        // Form validation functions
        function validateCreateForm() {
            let isValid = true;
            
            // Reset error messages
            $('#createForm .error-message').addClass('hidden');
            $('#createForm .form-select, #createForm .form-input').removeClass('border-red-500');                        
            
            if (!$('#project_category_id').val().trim()) {
                $('#project_category_id').parent().find('.error-message').removeClass('hidden');
                $('#project_category_id').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#customer_id').val().trim()) {
                $('#customer_id').parent().find('.error-message').removeClass('hidden');
                $('#customer_id').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#create_project_name').val().trim()) {
                $('#create_project_name').parent().find('.error-message').removeClass('hidden');
                $('#create_project_name').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#item_status').val().trim()) {
                $('#item_status').parent().find('.error-message').removeClass('hidden');
                $('#item_status').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#budget').val().trim()) {
                $('#budget').parent().find('.error-message').removeClass('hidden');
                $('#budget').addClass('border-red-500');
                isValid = false;
            }
            
            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            
            // Reset error messages
            $('#editForm .error-message').addClass('hidden');
            $('#editForm .form-select, #editForm .form-input').removeClass('border-red-500');
            
            let editProjectCategoryId = $('#edit_project_category_id').val();

            if (!editProjectCategoryId || editProjectCategoryId === '' || editProjectCategoryId === 'undefined' || isNaN(editProjectCategoryId)) {
                $('#edit_project_category_id').parent().find('.error-message').removeClass('hidden');
                $('#edit_project_category_id').addClass('border-red-500');
                isValid = false;
            }

            let editCustomer = $('#edit_customer_id').val();
            if (!editCustomer || editCustomer === '' || editCustomer === 'undefined' || isNaN(editCustomer)) {
                $('#edit_customer_id').parent().find('.error-message').removeClass('hidden');
                $('#edit_customer_id').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#edit_project_name').val().trim()) {
                $('#edit_project_name').parent().find('.error-message').removeClass('hidden');
                $('#edit_project_name').addClass('border-red-500');
                isValid = false;
            }

            let editStatus = $('#edit_status').val();
            if (!editStatus || editStatus === '') {
                $('#edit_status').parent().find('.error-message').removeClass('hidden');
                $('#edit_status').addClass('border-red-500');
                isValid = false;
            }

            let editBudget = $('#edit_budget').val();
            if (!editBudget || editBudget === '') {
                $('#edit_budget').parent().find('.error-message').removeClass('hidden');
                $('#edit_budget').addClass('border-red-500');
                isValid = false;
            }

            return isValid;
        }

        // Reset create form
        function resetCreateForm() {
            $('#createStateForm')[0].reset();
            $('#createStateForm .error-message').addClass('hidden');
            $('#createStateForm .form-select, #createStateForm .form-input').removeClass('border-red-500');
        }

        // Delete confirmation
        function confirmDelete(id, name) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            $('#deleteModal').removeClass('hidden');
        }

    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const resetUrl = "{{ route('role.projects.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}";
            const filterHeader = document.querySelector('.filter-container .filter-header');
            const filterContent = document.querySelector('.filter-container .filter-content');
            const progressBars = document.querySelectorAll('.project-progress-bar');

            progressBars.forEach((bar) => {
                const progress = parseInt(bar.getAttribute('data-progress') || '0', 10);
                bar.style.width = `${Math.min(Math.max(progress, 0), 100)}%`;
            });
            
            filterHeader.addEventListener('click', function() {
                this.classList.toggle('active');
                filterContent.classList.toggle('active');
            });
            
            // Reset button functionality
            document.querySelector('.filter-container .reset-btn').addEventListener('click', function() {
                const inputs = document.querySelectorAll('.filter-container select, .filter-container input');
                inputs.forEach(input => {
                    if (input.type === 'date') {
                        input.value = '';
                    } else {
                        input.selectedIndex = 0;
                    }
                });
            });
            document.querySelector('.reset-btn').addEventListener('click', function (e) {
                e.preventDefault();
                window.location = resetUrl;
            });
        });
    </script>
@endsection