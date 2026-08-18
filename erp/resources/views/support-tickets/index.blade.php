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
                    <i class="fas fa-list mr-2"></i>Support Tickets List
                </h2>
                @can('create support ticket')
                <button class="btn btn-primary create-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add New Support Ticket
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
                                <!-- Title Filter -->
                                <div class="filter-group">
                                    <label for="title">Title</label>
                                    <input type="text" name="title" id="title" value="{{ request('title') }}" placeholder="Search by title">  
                                </div>

                                <!-- Ticket Department Filter -->
                                <div class="filter-group">
                                    <label for="ticket_department_id">Ticket Department</label>
                                    <select name="ticket_department_id" id="filter_ticket_department" class="select2">
                                        <option value="">All Ticket Departments</option>
                                        @foreach($ticketDepartments as $department)
                                            <option value="{{ $department->id }}" {{ request('ticket_department_id') == $department->id ? 'selected' : '' }}>
                                                {{ $department->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Priority Filter -->
                                <div class="filter-group">
                                    <label for="priority">Priority</label>
                                    <select name="priority" id="filter_priority" class="select2">
                                        <option value="">All Priorities</option>
                                        <option value="low" {{ request('priority') == 'low' ? 'selected' : '' }}>Low</option>
                                        <option value="medium" {{ request('priority') == 'medium' ? 'selected' : '' }}>Medium</option>
                                        <option value="high" {{ request('priority') == 'high' ? 'selected' : '' }}>High</option>
                                    </select>
                                </div>
                                
                                <!-- Status Filter -->
                                <div class="filter-group">
                                    <label for="status">Status</label>
                                    <select name="status" id="filter_status" class="select2">
                                        <option value="">All Status</option>
                                        <option value="open" {{ request('status') == 'open' ? 'selected' : '' }}>Open</option>
                                        <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                        <option value="resolved" {{ request('status') == 'resolved' ? 'selected' : '' }}>Resolved</option>
                                        <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>Closed</option>
                                    </select>
                                </div>

                                <!-- Assigned To Filter -->
                                <div class="filter-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Assigned To</label>
                                    <select name="assigned_to" id="filter_assigned_to" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <option value="">All Employees</option>
                                        @foreach($employees as $employee)
                                            <option value="{{ $employee->id }}" {{ request('assigned_to') == $employee->id ? 'selected' : '' }}>
                                                {{ $employee->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Company Filter -->
                                <div class="filter-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Company</label>
                                    <select name="company_id" id="filter_company" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <option value="">All Companies</option>
                                        @foreach($companies as $company)
                                            <option value="{{ $company->id }}" {{ request('company_id') == $company->id ? 'selected' : '' }}>
                                                {{ $company->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Customer Filter (Optional) -->
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Attachment</th>                                                                                                                     
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ticket Department</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>   
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>   
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned To</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>   
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Updated By</th>   
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Company Id</th>   
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer Id</th>  
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($datas as $key => $value)                       
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap" style="padding-left: 20px">
                                        <strong>{{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}</strong>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->title }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->description }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($value->file_attachment)
                                            @php
                                                $extension = strtolower(pathinfo($value->file_attachment, PATHINFO_EXTENSION));
                                                $iconClass = 'fas fa-file';
                                                $iconColor = 'text-gray-500';
                                                
                                                if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg'])) {
                                                    $iconClass = 'fas fa-file-image';
                                                    $iconColor = 'text-blue-500';
                                                } elseif ($extension === 'pdf') {
                                                    $iconClass = 'fas fa-file-pdf';
                                                    $iconColor = 'text-red-500';
                                                } elseif (in_array($extension, ['doc', 'docx'])) {
                                                    $iconClass = 'fas fa-file-word';
                                                    $iconColor = 'text-blue-700';
                                                } elseif (in_array($extension, ['xls', 'xlsx'])) {
                                                    $iconClass = 'fas fa-file-excel';
                                                    $iconColor = 'text-green-600';
                                                }
                                            @endphp
                                            <a href="{{ asset($value->file_attachment) }}" target="_blank"><i class="{{ $iconClass }} {{ $iconColor }}"></i></a>
                                        @endif
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->ticketDepartment->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if ($value->priority == 'low')
                                        <span class="badge text-white bg-gray-500 px-2 py-1 rounded-full text-xs">
                                            Low
                                        </span>                                            
                                        @elseif($value->priority == 'medium')                                            
                                        <span class="badge text-white bg-blue-500 px-2 py-1 rounded-full text-xs">
                                            Medium
                                        </span>
                                        @elseif($value->priority == 'high')                                            
                                        <span class="badge text-white bg-red-500 px-2 py-1 rounded-full text-xs">
                                            High
                                        </span>
                                        @endif          
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if ($value->status == 'open')
                                        <span class="badge text-white bg-gray-500 px-2 py-1 rounded-full text-xs">
                                        Open
                                        </span>                                            
                                        @elseif($value->status == 'in_progress')                                            
                                        <span class="badge text-white bg-blue-500 px-2 py-1 rounded-full text-xs">
                                            In Progress
                                        </span>
                                        @elseif($value->status == 'resolved')                                            
                                        <span class="badge text-white bg-yellow-500 px-2 py-1 rounded-full text-xs">
                                            Resolved
                                        </span>
                                        @elseif($value->status == 'closed')                                            
                                        <span class="badge text-white bg-red-500 px-2 py-1 rounded-full text-xs">
                                            Closed
                                        </span>
                                        @endif          
                                    </td>                                                                     
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->assignedTo?->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->createdBy->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->updatedBy?->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->company->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->customer->name }}</td>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              
                                    @canany(['view support ticket', 'edit support ticket', 'delete support ticket'])
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="btn-group btn-group-sm flex space-x-1">
                                            @can('view support ticket')
                                            <a href="{{ route('role.support-tickets.show', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'support_ticket' => $value->id]) }}" 
                                            class="btn btn-outline-info border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @endcan
                                            @can('edit support ticket')
                                            <button class="btn btn-outline-primary edit-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200" 
                                                data-item_id="{{ $value->id }}" 
                                                data-title="{{ $value->title }}" 
                                                data-description="{{ $value->description }}" 
                                                data-file_attachment="{{ $value->file_attachment }}" 
                                                data-ticket_department_id="{{ $value->ticket_department_id }}"
                                                data-priority="{{ $value->priority }}" 
                                                data-status="{{ $value->status }}" 
                                                data-assigned_to="{{ $value->assigned_to }}"
                                                data-company_id="{{ $value->company_id }}"
                                                data-customer_id="{{ $value->customer_id }}"
                                                data-action="{{ route('role.support-tickets.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'support_ticket' => $value->id]) }}" title="Edit Item">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            @endcan
                                            @can('delete support ticket')
                                            <button class="btn btn-outline-danger border border-red-500 text-red-500 hover:bg-red-500 hover:text-white px-3 py-1 rounded-md transition duration-200" onclick="confirmDelete('{{ $value->id }}', '{{ $value->title }}')">
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
    
    @include('support-tickets.create-modal')
    @include('support-tickets.edit-modal')
    @include('support-tickets.delete-modal')
    
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
                const title = $(this).data('title');
                const description = $(this).data('description');
                const ticket_department_id = $(this).data('ticket_department_id');
                const priority = $(this).data('priority');
                const status = $(this).data('status');
                const assigned_to = $(this).data('assigned_to');
                const company_id = $(this).data('company_id');
                const customer_id = $(this).data('customer_id');
                const file_attachment = $(this).data('file_attachment');
                const baseUrl = "{{ url('/') }}";
                
                $('#editModal').removeClass('hidden');

                // Initialize select2 after modal is shown
                $('#editModal .select2').select2();
                
                // Set values in the edit form
                $('#editItemId').val(item_id);
                $('#edit_title').val(title);
                $('#edit_description').val(description);
                $('#edit_ticket_department_id').val(ticket_department_id).trigger('change');
                $('#edit_priority').val(priority).trigger('change');
                $('#edit_status').val(status).trigger('change');
                $('#edit_assigned_to').val(assigned_to).trigger('change');
                $('#edit_company_id').val(company_id).trigger('change');
                $('#edit_customer_id').val(customer_id).trigger('change');
                if (file_attachment && file_attachment.trim() !== '') {
                    $('#existing_image').attr('src', '{{ asset('/') }}' + file_attachment).show();
                } else {
                    $('#existing_image').hide();
                }
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
                                text: 'Failed to create support ticket.'
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
            
            if (!$('#create_title').val().trim()) {
                $('#create_title').parent().find('.error-message').removeClass('hidden');
                $('#create_title').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#ticket_department_id').val().trim()) {
                $('#ticket_department_id').parent().find('.error-message').removeClass('hidden');
                $('#ticket_department_id').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#create_priority').val().trim()) {
                $('#create_priority').parent().find('.error-message').removeClass('hidden');
                $('#create_priority').addClass('border-red-500');
                isValid = false;
            }
            @if('employee' != Auth::user()->getRoleNames()->first())
            if (!$('#create_status').val().trim()) {
                $('#create_status').parent().find('.error-message').removeClass('hidden');
                $('#create_status').addClass('border-red-500');
                isValid = false;
            }
            
            if (!$('#company_id').val().trim()) {
                $('#company_id').parent().find('.error-message').removeClass('hidden');
                $('#company_id').addClass('border-red-500');
                isValid = false;
            }
            @endif
            
            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            
            // Reset error messages
            $('#editForm .error-message').addClass('hidden');
            $('#editForm .form-select, #editForm .form-input').removeClass('border-red-500');
            
            if (!$('#edit_title').val().trim()) {
                $('#edit_title').parent().find('.error-message').removeClass('hidden');
                $('#edit_title').addClass('border-red-500');
                isValid = false;
            }

            let editTicketDepartmentId = $('#edit_ticket_department_id').val();
            if (!editTicketDepartmentId || editTicketDepartmentId === '' || editTicketDepartmentId === 'undefined' || isNaN(editTicketDepartmentId)) {
                $('#edit_ticket_department_id').parent().find('.error-message').removeClass('hidden');
                $('#edit_ticket_department_id').addClass('border-red-500');
                isValid = false;
            }

            let editPriority = $('#edit_priority').val();
            if (!editPriority || editPriority === '') {
                $('#edit_priority').parent().find('.error-message').removeClass('hidden');
                $('#edit_priority').addClass('border-red-500');
                isValid = false;
            }

            let editStatus = $('#edit_status').val();
            if (!editStatus || editStatus === '') {
                $('#edit_status').parent().find('.error-message').removeClass('hidden');
                $('#edit_status').addClass('border-red-500');
                isValid = false;
            }

            let editCompany = $('#edit_company_id').val();
            if (!editCompany || editCompany === '' || editCompany === 'undefined' || isNaN(editCompany)) {
                $('#edit_company_id').parent().find('.error-message').removeClass('hidden');
                $('#edit_company_id').addClass('border-red-500');
                isValid = false;
            }

            return isValid;
        }

        // Reset create form
        function resetCreateForm() {
            $('#createForm')[0].reset();
            $('#createForm .error-message').addClass('hidden');
            $('#createForm .form-select, #createForm .form-input').removeClass('border-red-500');
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
                window.location = "{{ route('role.support-tickets.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}";
            });
        });
    </script>
@endsection