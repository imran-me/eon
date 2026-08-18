@extends('layout.app')
@section('meta-information')
    <title>Manage Expenses</title>
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
                    <i class="fas fa-list mr-2"></i>Estimate List
                </h2>
                @can('create estimate')
                <button class="btn btn-primary create-new-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add New Estimate
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
                                <div class="filter-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Estimate No</label>
                                    <input type="text" name="estimate_no" id="filter_estimate_no" value="{{ request('estimate_no') }}" 
                                        class="form-input w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Search by estimate no">
                                </div>
                                <div class="filter-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Deal</label>
                                    <select name="deal_id" id="filter_deal" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <option value="">All Deals</option>
                                        @foreach($deals as $deal)
                                            <option value="{{ $deal->id }}" {{ request('deal_id') == $deal->id ? 'selected' : '' }}>
                                                {{ $deal->deal_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                    <select name="status" id="filter_status" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <option value="">All Statuses</option>
                                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="sent" {{ request('status') == 'sent' ? 'selected' : '' }}>Sent</option>
                                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Estimate Date From</label>
                                    <input type="date" name="date_from" id="filter_date_from" value="{{ request('date_from') }}" 
                                        class="form-input w-full px-3 py-2 border border-gray-300 rounded-md">
                                </div>
                                <div class="filter-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Estimate Date To</label>
                                    <input type="date" name="date_to" id="filter_date_to" value="{{ request('date_to') }}" 
                                        class="form-input w-full px-3 py-2 border border-gray-300 rounded-md">
                                </div>
                            </div>
                            <div class="filter-actions">
                                <button type="button" class="reset-btn">Reset</button>
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deals</th>	                                	
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estimate No</th>	
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estimate Date</th>	
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valid Until</th>	
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>	                                
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>                                                      	
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                @canany(['edit estimate', 'delete estimate'])
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
                                        {{ $value->deal?->deal_name }}                                                                                                                        
                                    </td>  
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->estimate_no }}                                                                                                                        
                                    </td>                                                                          
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->estimate_date->format('Y-m-d') }}
                                    </td>                                                                          
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->valid_until->format('Y-m-d') }}                                                                                                                        
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($value->status == 'draft')
                                            <span class="badge text-white bg-gray-500 px-2 py-1 rounded-full text-xs">Draft</span>
                                        @elseif($value->status == 'pending')
                                            <span class="badge text-white bg-blue-500 px-2 py-1 rounded-full text-xs">Pending</span>
                                        @elseif($value->status == 'sent')
                                            <span class="badge text-white bg-orange-500 px-2 py-1 rounded-full text-xs">Sent</span>
                                        @elseif($value->status == 'approved')
                                            <span class="badge text-white bg-green-500 px-2 py-1 rounded-full text-xs">Approved</span>
                                        @elseif($value->status == 'rejected')
                                            <span class="badge text-white bg-red-500 px-2 py-1 rounded-full text-xs">Rejected</span>
                                        @else
                                            <span class="badge text-white bg-yellow-500 px-2 py-1 rounded-full text-xs">Unknown</span>
                                        @endif         
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->total_amount }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->description }}</td>
                                    @canany(['edit estimate', 'delete estimate'])
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="btn-group btn-group-sm flex space-x-1">
                                            @can('edit estimate')
                                            <button class="btn btn-outline-primary edit-item-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200" 
                                                data-item_id="{{ $value->id }}" 
                                                data-deal_id="{{ $value->deal_id }}"
                                                data-estimate_no="{{ $value->estimate_no }}"
                                                data-estimate_date="{{ $value->estimate_date ? $value->estimate_date->format('Y-m-d') : '' }}"
                                                data-valid_until="{{ $value->valid_until ? $value->valid_until->format('Y-m-d') : '' }}"
                                                data-status="{{ $value->status }}" 
                                                data-total_amount="{{ $value->total_amount }}"
                                                data-description="{{ $value->description }}"
                                                data-action="{{ route('role.estimates.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'estimate' => $value->id]) }}"
                                                title="Edit Item">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            @endcan
                                            @can('delete estimate')
                                            <button class="btn btn-outline-danger border border-red-500 text-red-500 hover:bg-red-500 hover:text-white px-3 py-1 rounded-md transition duration-200" onclick="confirmDelete('{{ $value->id }}', '{{ $value->estimate_no }}')" title="Delete Item">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            @endcan
                                        </div>
                                    </td>
                                    @endcan
                                </tr>
                            @empty
                            <tr>
                                <td colspan="12" class="text-center py-8">
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
    
    @include('estimates.create-modal')
    @include('estimates.edit-modal')
    @include('estimates.delete-modal')

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
                const deal_id = $(this).data('deal_id');
                const estimate_no = $(this).data('estimate_no');
                const estimate_date = $(this).data('estimate_date');
                const valid_until = $(this).data('valid_until');
                const status = $(this).data('status');
                const total_amount = $(this).data('total_amount');
                const description = $(this).data('description');
                
                // Show modal first
                $('#editModal').removeClass('hidden');
                
                // Initialize select2 after modal is shown
                $('#editModal .select2').select2();
                
                // Set values in the edit form
                $('#editItemId').val(item_id);
                $('#edit_deal_id').val(deal_id).trigger('change');              
                $('#edit_estimate_no').val(estimate_no);              
                $('#edit_estimate_date').val(estimate_date);
                $('#edit_valid_until').val(valid_until);
                $('#edit_status').val(status).trigger('change');
                $('#edit_total_amount').val(total_amount);
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

            if (!$('#estimate_no').val().trim()) {
                $('#estimate_no').parent().find('.error-message').removeClass('hidden');
                $('#estimate_no').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#estimate_date').val().trim()) {
                $('#estimate_date').parent().find('.error-message').removeClass('hidden');
                $('#estimate_date').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#valid_until').val().trim()) {
                $('#valid_until').parent().find('.error-message').removeClass('hidden');
                $('#valid_until').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#item_status').val().trim()) {
                $('#item_status').parent().find('.error-message').removeClass('hidden');
                $('#item_status').addClass('border-red-500');
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

            if (!$('#edit_estimate_no').val().trim()) {
                $('#edit_estimate_no').parent().find('.error-message').removeClass('hidden');
                $('#edit_estimate_no').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#edit_estimate_date').val().trim()) {
                $('#edit_estimate_date').parent().find('.error-message').removeClass('hidden');
                $('#edit_estimate_date').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#edit_valid_until').val().trim()) {
                $('#edit_valid_until').parent().find('.error-message').removeClass('hidden');
                $('#edit_valid_until').addClass('border-red-500');
                isValid = false;
            }

             if (!$('#edit_status').val().trim()) {
                $('#edit_status').parent().find('.error-message').removeClass('hidden');
                $('#edit_status').addClass('border-red-500');
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
                window.location = '{{ route('role.estimates.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}';
            });
        });
    </script>
@endsection