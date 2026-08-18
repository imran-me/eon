@extends('layout.app')
@section('meta-information')
    <title>Manage Leave Types</title>
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
                    <i class="fas fa-list mr-2"></i>Contracts List
                </h2>
                @can('create contract')
                <button class="btn btn-primary create-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add New Contract
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
                                <!-- Contract No Filter -->
                                <div class="filter-group">
                                    <label for="contract_no">Contract No</label>
                                    <input type="text" name="contract_no" id="contract_no" value="{{ request('contract_no') }}" placeholder="Search by contract no">  
                                </div>

                                <!-- Deal Filter -->
                                <div class="filter-group">
                                    <label for="deal_id">Deal</label>
                                    <select name="deal_id" id="filter_deal" class="select2">
                                        <option value="">All Deals</option>
                                        @foreach($deals as $deal)
                                            <option value="{{ $deal->id }}" {{ request('deal_id') == $deal->id ? 'selected' : '' }}>
                                                {{ $deal->deal_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Customer Filter -->
                                <div class="filter-group">
                                    <label for="customer_id">Customer</label>
                                    <select name="customer_id" id="filter_customer" class="select2">
                                        <option value="">All Customers</option>
                                        @foreach($customers as $customer)
                                            <option value="{{ $customer->id }}" {{ request('customer_id') == $customer->id ? 'selected' : '' }}>
                                                {{ $customer->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Project Filter -->
                                <div class="filter-group">
                                    <label for="project_id">Project</label>
                                    <select name="project_id" id="filter_project" class="select2">
                                        <option value="">All Projects</option>
                                        @foreach($projects as $project)
                                            <option value="{{ $project->id }}" {{ request('project_id') == $project->id ? 'selected' : '' }}>
                                                {{ $project->project_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Contract Type Filter -->
                                <div class="filter-group">
                                    <label for="contract_type_id">Contract Type</label>
                                    <select name="contract_type_id" id="filter_contract_type" class="select2">
                                        <option value="">All Types</option>
                                        @foreach($contractTypes as $type)
                                            <option value="{{ $type->id }}" {{ request('contract_type_id') == $type->id ? 'selected' : '' }}>
                                                {{ $type->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Status Filter -->
                                <div class="filter-group">
                                    <label for="status">Status</label>
                                    <select name="status" id="filter_status">
                                        <option value="">All Statuses</option>
                                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                        <option value="signed" {{ request('status') == 'signed' ? 'selected' : '' }}>Signed</option>
                                        <option value="expired" {{ request('status') == 'expired' ? 'selected' : '' }}>Expired</option>
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deals</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>                                                                                                                     
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contract Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contract No</th>   
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contract Date</th>   
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valid Until</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contract Value</th>   
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>   
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                @canany(['edit contract', 'delete contract'])   
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                @endcanany
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($datas as $key => $value)                       
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap" style="padding-left: 20px">
                                        <strong>{{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}</strong>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->deal->deal_name }}</td>                                                                     
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->customer->name }}</td>                                                                     
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->project->project_name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->contractType->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->contract_no }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <small class="text-gray-500">{{ $value->contract_date ? $value->contract_date->format('Y-m-d') : '' }}</small>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <small class="text-gray-500">{{ $value->valid_until ? $value->valid_until->format('Y-m-d') : '' }}</small>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->contract_value }}</td>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if ($value->status == 'draft')
                                        <span class="badge text-white bg-gray-500 px-2 py-1 rounded-full text-xs">
                                            Draft
                                        </span>                                            
                                        @elseif($value->status == 'signed')                                            
                                        <span class="badge text-white bg-blue-500 px-2 py-1 rounded-full text-xs">
                                            Signed
                                        </span>
                                        @elseif($value->status == 'expired')                                            
                                        <span class="badge text-white bg-red-500 px-2 py-1 rounded-full text-xs">
                                            Expired
                                        </span>
                                        @endif          
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->description }}</td>
                                    @canany(['edit contract', 'delete contract'])                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="btn-group btn-group-sm flex space-x-1">
                                            @can('edit contract')
                                            <button class="btn btn-outline-primary edit-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200" 
                                                data-item_id="{{ $value->id }}" 
                                                data-deal_id="{{ $value->deal_id }}" 
                                                data-customer_id="{{ $value->customer_id }}" 
                                                data-project_id="{{ $value->project_id }}" 
                                                data-contract_type_id="{{ $value->contract_type_id }}"
                                                data-contract_no="{{ $value->contract_no }}" 
                                                data-contract_date="{{ $value->contract_date ? $value->contract_date->format('Y-m-d') : '' }}" 
                                                data-valid_until="{{ $value->valid_until ? $value->valid_until->format('Y-m-d') : '' }}"
                                                data-contract_value="{{ $value->contract_value }}"
                                                data-status="{{ $value->status }}"
                                                data-description="{{ $value->description }}"
                                                data-action="{{ route('role.contracts.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'contract' => $value->id]) }}" title="Edit Item">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            @endcan
                                            @can('delete contract')
                                            <button class="btn btn-outline-danger border border-red-500 text-red-500 hover:bg-red-500 hover:text-white px-3 py-1 rounded-md transition duration-200" onclick="confirmDelete('{{ $value->id }}', '{{ $value->contract_no }}')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            @endcan
                                        </div>
                                    </td>
                                    @endcanany
                                </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center py-8">
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
    
    @include('contracts.create-modal')
    @include('contracts.edit-modal')
    @include('contracts.delete-modal')
    
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

            // Show create modal
            $('.create-btn').click(function() {
                $('#createModal').removeClass('hidden');
                // Initialize select2 after modal is shown
                $('#createModal .select2').select2();
            });

            // Show edit modal
            $('.edit-btn').click(function() {
                const item_id = $(this).data('item_id');
                const deal_id = $(this).data('deal_id');
                const customer_id = $(this).data('customer_id');
                const project_id = $(this).data('project_id');
                const contract_type_id = $(this).data('contract_type_id');
                const contract_no = $(this).data('contract_no');
                const contract_date = $(this).data('contract_date');
                const valid_until = $(this).data('valid_until');
                const contract_value = $(this).data('contract_value');
                const status = $(this).data('status');
                const description = $(this).data('description');
                
                $('#editModal').removeClass('hidden');

                // Initialize select2 after modal is shown
                $('#editModal .select2').select2();
                
                // Set values in the edit form
                $('#editItemId').val(item_id);
                $('#edit_deal_id').val(deal_id).trigger('change');
                $('#edit_customer_id').val(customer_id).trigger('change');
                $('#edit_contract_type_id').val(contract_type_id).trigger('change');
                $('#edit_project_id').val(project_id).trigger('change');
                $('#edit_contract_no').val(contract_no);
                $('#edit_contract_date').val(contract_date);
                $('#edit_valid_until').val(valid_until);
                $('#edit_contract_value').val(contract_value);
                $('#edit_contract_status').val(status).trigger('change');
                $('#edit_description').val(description);
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
                                text: 'Failed to create contract.'
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
            
            if (!$('#deal_id').val().trim()) {
                $('#deal_id').parent().find('.error-message').removeClass('hidden');
                $('#deal_id').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#customer_id').val().trim()) {
                $('#customer_id').parent().find('.error-message').removeClass('hidden');
                $('#customer_id').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#project_id').val().trim()) {
                $('#project_id').parent().find('.error-message').removeClass('hidden');
                $('#project_id').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#contract_type_id').val().trim()) {
                $('#contract_type_id').parent().find('.error-message').removeClass('hidden');
                $('#contract_type_id').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#create_contract_no').val().trim()) {
                $('#create_contract_no').parent().find('.error-message').removeClass('hidden');
                $('#create_contract_no').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#contract_status').val().trim()) {
                $('#contract_status').parent().find('.error-message').removeClass('hidden');
                $('#contract_status').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#contract_value').val().trim()) {
                $('#contract_value').parent().find('.error-message').removeClass('hidden');
                $('#contract_value').addClass('border-red-500');
                isValid = false;
            }
            
            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            
            // Reset error messages
            $('#editForm .error-message').addClass('hidden');
            $('#editForm .form-select, #editForm .form-input').removeClass('border-red-500');
            
            let editDealId = $('#edit_deal_id').val();

            if (!editDealId || editDealId === '' || editDealId === 'undefined' || isNaN(editDealId)) {
                $('#edit_deal_id').parent().find('.error-message').removeClass('hidden');
                $('#edit_deal_id').addClass('border-red-500');
                isValid = false;
            }

            let editCustomer = $('#edit_customer_id').val();
            if (!editCustomer || editCustomer === '' || editCustomer === 'undefined' || isNaN(editCustomer)) {
                $('#edit_customer_id').parent().find('.error-message').removeClass('hidden');
                $('#edit_customer_id').addClass('border-red-500');
                isValid = false;
            }

            let editProject = $('#edit_project_id').val();
            if (!editProject || editProject === '' || editProject === 'undefined' || isNaN(editProject)) {
                $('#edit_project_id').parent().find('.error-message').removeClass('hidden');
                $('#edit_project_id').addClass('border-red-500');
                isValid = false;
            }

            let editContractType = $('#edit_contract_type_id').val();
            if (!editContractType || editContractType === '' || editContractType === 'undefined' || isNaN(editContractType)) {
                $('#edit_contract_type_id').parent().find('.error-message').removeClass('hidden');
                $('#edit_contract_type_id').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#edit_contract_no').val().trim()) {
                $('#edit_contract_no').parent().find('.error-message').removeClass('hidden');
                $('#edit_contract_no').addClass('border-red-500');
                isValid = false;
            }

            let editStatus = $('#edit_contract_status').val();
            if (!editStatus || editStatus === '') {
                $('#edit_contract_status').parent().find('.error-message').removeClass('hidden');
                $('#edit_contract_status').addClass('border-red-500');
                isValid = false;
            }

            let editBudget = $('#edit_contract_value').val();
            if (!editBudget || editBudget === '') {
                $('#edit_contract_value').parent().find('.error-message').removeClass('hidden');
                $('#edit_contract_value').addClass('border-red-500');
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
            const filterHeader = document.querySelector('.filter-container .filter-header');
            const filterContent = document.querySelector('.filter-container .filter-content');
            
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
                window.location = '{{ route('role.projects.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}';
            });
        });
    </script>
@endsection