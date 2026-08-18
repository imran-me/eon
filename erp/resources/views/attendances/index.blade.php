@extends('layout.app')
@section('meta-information')
    <title>Manage Attendances</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@include('layout.table-design')
@endsection
@section('main-content')

    <!-- States Table -->    
    <div class="states-table bg-white rounded-lg shadow-md overflow-hidden">
        <div class="states-table-container">
            <div class="states-table-header bg-blue-600 px-6 py-4 flex justify-between items-center">
                <h2 class="states-table-title text-white text-xl font-semibold" style="color: white">
                    <i class="fas fa-list mr-2"></i>Attendance List
                </h2>
                @can('create attendance')
                <button class="btn btn-primary create-new-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add New Attendance
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
                            <div class="closest filter-row">
                                @if('employee' != Str::slug(Auth::user()->getRoleNames()->first()))
                                <div class="filter-group">
                                    <label for="user_id">User</label>
                                    <select id="user_id" name="user_id" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}" {{ $user->id == request('user_id') ? 'selected' : '' }}>{{ $user->name }}</option>                                            
                                        @endforeach
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="company_id">Company</label>
                                    <select id="company_id" name="company_id" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>                                        
                                        @foreach ($companies as $company)
                                            <option value="{{ $company->id }}" {{ $company->id == request('company_id') ? 'selected' : '' }}>{{ $company->name }}</option>                                            
                                        @endforeach
                                    </select>
                                </div>                                
                                <div class="filter-group">
                                    <label for="shift_id">Shift</label>
                                    <select id="shift_id" name="shift_id" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>
                                        @foreach ($shifts as $shift)
                                        <option value="{{ $shift->id }}" {{ $shift->id == request('shift_id') ? 'selected' : '' }}>{{ $shift->name }}</option>                                            
                                        @endforeach
                                    </select>
                                </div>
                                    @endif
                                <div class="filter-group">
                                    <label for="status">Status</label>
                                    <select id="status" name="status" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>
                                        <option value="pending">Pending</option>
                                        <option value="approved">Approved</option>
                                        <option value="rejected">Rejected</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="date">Date</label>
                                    <input type="date" name="date" value="{{ request('date') }}" id="date" class="form-control">
                                </div>
                            </div>
                            <div class="filter-actions">
                                <button type="button" class="reset-btn" data-reset-url="{{ route('role.attendances.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}">Reset</button>
                                <button type="submit" class="apply-btn">Apply Filters</button>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Table with Data -->                 
                <div class="table-responsive overflow-x-auto" style="padding: 15px">
                    <table class="table table-hover min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th style="padding-left: 20px" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SL</th>                                                                	
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>	
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Company</th>	              
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>	                                                                                             	
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shift</th>	                                                                                             	
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check In</th>	                                                                                             	
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check Out</th>                                
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                @can('edit attendance')
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($datas as $key => $value)                       
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap" style="padding-left: 20px">
                                        <strong>{{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}</strong>
                                    </td>  
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->user?->name }}                                                                                                                        
                                    </td>  
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->company?->name }}                                                                                                                        
                                    </td>                                                                                  
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->date }}                                                                                                                        
                                    </td>     
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->shift?->name }}                                                                                                                        
                                    </td>                                                
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->check_in }}                                                                                                                        
                                    </td>                                                
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->check_out }}                                                                                                                        
                                    </td>                                                
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if ($value->status == 'present')
                                        <span class="badge text-white bg-green-500 px-2 py-1 rounded-full text-xs">
                                            Present
                                        </span>                                            
                                        @elseif ($value->status == 'holiday')
                                        <span class="badge text-white bg-blue-500 px-2 py-1 rounded-full text-xs">
                                            Holiday
                                        </span>                                            
                                        @elseif ($value->status == 'leave')
                                        <span class="badge text-white bg-yellow-500 px-2 py-1 rounded-full text-xs">
                                            Leave
                                        </span>                                            
                                        @else                                            
                                        <span class="badge text-white bg-red-500 px-2 py-1 rounded-full text-xs">
                                            Absent
                                        </span>
                                        @endif          
                                    </td>
                                    @can('edit attendance')
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="btn-group btn-group-sm flex space-x-1">
                                            @if ($value->source === 'manual' && $value->selfie)
                                            <button type="button" class="btn btn-outline-info view-selfie-btn border border-cyan-500 text-cyan-500 hover:bg-cyan-500 hover:text-white px-3 py-1 rounded-md transition duration-200"
                                                data-selfie="{{ asset($value->selfie) }}"
                                                data-user="{{ $value->user?->name }}"
                                                data-date="{{ $value->date }}"
                                                title="View Selfie Image">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            @endif
                                            <button class="btn btn-outline-primary edit-item-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200" 
                                                data-item_id="{{ $value->id }}" 
                                                data-user_id="{{ $value->user_id }}"
                                                data-company_id="{{ $value->company_id }}"
                                                data-shift_id="{{ $value->shift_id }}"
                                                data-attendence_setting_id="{{ $value->attendence_setting_id }}"
                                                data-date="{{ $value->date }}"
                                                data-check_in="{{ $value->check_in }}"
                                                data-check_out="{{ $value->check_out }}"
                                                data-note="{{ $value->note }}"
                                                data-status="{{ $value->status }}"
                                                title="Edit Item">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            {{-- <button class="btn btn-outline-danger border border-red-500 text-red-500 hover:bg-red-500 hover:text-white px-3 py-1 rounded-md transition duration-200" onclick="confirmDelete('{{ $value->id }}', 'this item')">
                                                <i class="fas fa-trash"></i>
                                            </button> --}}
                                        </div>
                                    </td>
                                    @endcan
                                </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="text-center py-8">
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
    
    @include('attendances.create-modal')
    @include('attendances.edit-modal')
    @include('attendances.delete-modal')
    @include('attendances.selfie-modal')

@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {

            // initialized select2
            $('.select2').select2();

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // Show create modal
            $('.create-new-btn').click(function() {
                $('#createModal').removeClass('hidden');
            });

            // Show edit modal
            $('.edit-item-btn').click(function() {
                const item_id = $(this).data('item_id');
                const user_id = $(this).data('user_id');
                const company_id = $(this).data('company_id');
                const shift_id = $(this).data('shift_id');
                const attendence_setting_id = $(this).data('attendence_setting_id');
                const date = $(this).data('date');
                const check_in = $(this).data('check_in');
                const check_out = $(this).data('check_out');
                const note = $(this).data('note');
                const status = $(this).data('status');
                
                // Set values in the edit form
                $('#editItemId').val(item_id);
                $('#edit_user_id').val(user_id).trigger('change');
                $('#edit_company_id').val(company_id).trigger('change'); 
                $('#edit_shift_id').val(shift_id).trigger('change'); 
                $('#edit_attendence_setting_id').val(attendence_setting_id).trigger('change'); 
                $('#edit_date').val(date);
                $('#edit_check_in').val(check_in);
                $('#edit_check_out').val(check_out);
                $('#edit_note').val(note);
                $('#edit_status').val(status).trigger('change');  
                $('#editModal').removeClass('hidden'); 
            });

            // Show selfie preview modal
            $('.view-selfie-btn').click(function() {
                const selfieUrl = $(this).data('selfie');
                const userName = $(this).data('user') || 'Manual Attendance';
                const date = $(this).data('date') || '';

                $('#selfieModalTitle').text(`Selfie Preview - ${userName}` + (date ? ` (${date})` : ''));
                $('#selfiePreview').attr('src', selfieUrl);
                $('#selfieModal').css('display', 'flex');
            });

            $('.modal-close-selfie, #selfieModal .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-selfie').length) {
                    $('#selfieModal').css('display', 'none');
                    $('#selfiePreview').attr('src', '');
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
                console.log(validateCreateForm());                
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
                                text: 'Failed to create data.'
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

        function select2SetValueNoEvent(selectId, value) {
            var $select = $(selectId);

            // Set the underlying value
            $select.val(value);

            // Find the selected option text
            var text = $select.find('option:selected').text() || '';

            // Update the visible Select2 box manually
            $select.data('select2').$container.find('.select2-selection__rendered').text(text);
        }
        
        // Form validation functions
        function validateCreateForm() {
            let isValid = true;
            
            // Reset error messages
            $('#createForm .error-message').addClass('hidden');
            $('#createForm .form-select, #createForm .form-input').removeClass('border-red-500');                                                
                        
            if (!$('#create_date').val().trim()) {
                $('#create_date').next('.error-message').removeClass('hidden');
                $('#create_date').addClass('border-red-500');
                isValid = false;
            }                                                                                
            if (!$('#create_user_id').val() || ($('#create_user_id').val() == '') || ($('#create_user_id').val() == null)) {
                $('#create_user_msg').removeClass('hidden');
                isValid = false;
            }    
            // if (!$('#create_company_id').val() || ($('#create_company_id').val() == '') || ($('#create_company_id').val() == null)) {
            //     $('#create_company_msg').removeClass('hidden');
            //     isValid = false;
            // }    
            // if (!$('#create_shift_id').val() || ($('#create_shift_id').val() == '') || ($('#create_shift_id').val() == null)) {
            //     $('#create_shift_msg').removeClass('hidden');
            //     isValid = false;
            // }                                                                    
            
            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            
            // Reset error messages
            $('#editForm .error-message').addClass('hidden');
            $('#editForm .form-select, #editForm .form-input').removeClass('border-red-500');
            
            // if (!$('#edit_date').val().trim()) {
            //     $('#edit_date').next('.error-message').removeClass('hidden');
            //     $('#edit_date').addClass('border-red-500');
            //     isValid = false;
            // }                                                                                
            if (!$('#edit_user_id').val() || ($('#edit_user_id').val() == '') || ($('#edit_user_id').val() == null)) {
                $('#edit_user_msg').removeClass('hidden');
                isValid = false;
            }    
            // if (!$('#edit_company_id').val() || ($('#edit_company_id').val() == '') || ($('#edit_company_id').val() == null)) {
            //     $('#edit_company_msg').removeClass('hidden');
            //     isValid = false;
            // }    
            // if (!$('#edit_shift_id').val() || ($('#edit_shift_id').val() == '') || ($('#edit_shift_id').val() == null)) {
            //     $('#edit_shift_msg').removeClass('hidden');
            //     isValid = false;
            // }     
            
            return isValid;
        }

        // Reset create form
        function resetCreateForm() {
            $('#createForm')[0].reset();
            $('#createForm .error-message').addClass('hidden');
            $('#createForm .form-select, #createForm .form-input').removeClass('border-red-500');
        }

        // Delete confirmation
        function confirmDelete(id, name=null) {
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
                window.location = this.dataset.resetUrl;
            });
        });
    </script>
@endsection