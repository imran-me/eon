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
                    <i class="fas fa-list mr-2"></i>Proposal List
                </h2>
                    @can('create proposal')
                <button class="btn btn-primary create-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add New Proposal
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
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Proposal No</label>
                                    <input type="text" name="proposal_no" id="filter_proposal_no" value="{{ request('proposal_no') }}" 
                                        class="form-input w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Search by proposal no">
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
                                        <option value="Draft" {{ request('status') == 'Draft' ? 'selected' : '' }}>Draft</option>
                                        <option value="Sent" {{ request('status') == 'Sent' ? 'selected' : '' }}>Sent</option>
                                        <option value="Accepted" {{ request('status') == 'Accepted' ? 'selected' : '' }}>Accepted</option>
                                        <option value="Rejected" {{ request('status') == 'Rejected' ? 'selected' : '' }}>Rejected</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Proposal Date From</label>
                                    <input type="date" name="date_from" id="filter_date_from" value="{{ request('date_from') }}" 
                                        class="form-input w-full px-3 py-2 border border-gray-300 rounded-md">
                                </div>
                                <div class="filter-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Proposal Date To</label>
                                    <input type="date" name="date_to" id="filter_date_to" value="{{ request('date_to') }}" 
                                        class="form-input w-full px-3 py-2 border border-gray-300 rounded-md">
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Proposal No</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Proposal Date</th>                                                                                                                     
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valid Until</th>   
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>  
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Terms</th>   
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th> 
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th> 
                                @canany(['edit proposal', 'delete proposal'])
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
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->deal? $value->deal->deal_name : 'N/A' }}</td>                                                                     
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->proposal_no }}</td>                                                                     
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->proposal_date }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->valid_until }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($value->status == 'draft')
                                            <span class="badge text-white bg-gray-500 px-2 py-1 rounded-full text-xs">Draft</span>
                                        @elseif($value->status == 'sent')
                                            <span class="badge text-white bg-blue-500 px-2 py-1 rounded-full text-xs">Sent</span>
                                        @elseif($value->status == 'approved')
                                            <span class="badge text-white bg-green-500 px-2 py-1 rounded-full text-xs">Approved</span>
                                        @elseif($value->status == 'rejected')
                                            <span class="badge text-white bg-red-500 px-2 py-1 rounded-full text-xs">Rejected</span>
                                        @else
                                            <span class="badge text-white bg-yellow-500 px-2 py-1 rounded-full text-xs">Unknown</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->terms }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->description }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <small class="text-gray-500">{{ date('M d, Y', strtotime($value->created_at)) }}</small>
                                    </td>
                                    @canany(['edit proposal', 'delete proposal'])                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="btn-group btn-group-sm flex space-x-1">
                                            @can('edit proposal')
                                            <button class="btn btn-outline-primary edit-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200" 
                                                data-item_id="{{ $value->id }}"
                                                data-deal_id="{{ $value->deal_id }}"
                                                data-proposal_no="{{ $value->proposal_no }}"
                                                data-proposal_date="{{ $value->proposal_date }}"
                                                data-valid_until="{{ $value->valid_until }}"
                                                data-status="{{ $value->status }}" 
                                                data-terms="{{ $value->terms }}"
                                                data-description="{{ $value->description }}"
                                                data-action="{{ route('role.proposals.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'proposal' => $value->id]) }}" title="Edit Item">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            @endcan
                                            @can('delete proposal')
                                            <button class="btn btn-outline-danger border border-red-500 text-red-500 hover:bg-red-500 hover:text-white px-3 py-1 rounded-md transition duration-200" onclick="confirmDelete('{{ $value->id }}', '{{ $value->proposal_no }}')" title="Delete Item">
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
    
    @include('proposals.create-modal')
    @include('proposals.edit-modal')
    @include('proposals.delete-modal')

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
                const proposal_no = $(this).data('proposal_no');
                const proposal_date = $(this).data('proposal_date');
                const valid_until = $(this).data('valid_until');
                const status = $(this).data('status');
                const terms = $(this).data('terms');
                const description = $(this).data('description');
                
                $('#editModal').removeClass('hidden');

                // Initialize select2 after modal is shown
                $('#editModal .select2').select2();
                
                // Set values in the edit form
                $('#editItemId').val(item_id);
                $('#edit_proposal_no').val(proposal_no);
                $('#edit_proposal_date').val(proposal_date);
                $('#edit_valid_until').val(valid_until);
                $('#edit_terms').val(terms);
                $('#edit_description').val(description);
                
                // Set select values
                $('#edit_deal_id').val(deal_id).trigger('change');
                $('#edit_status').val(status).trigger('change');
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
                                text: 'Failed to create Proposal.'
                            });
                        }
                    });                                    
                }
            });            

            // Edit state form submission
            $('#editSubmit').click(function() {
                console.log($(this).data('action'));
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

            if (!$('#proposal_no').val().trim()) {
                $('#proposal_no').parent().find('.error-message').removeClass('hidden');
                $('#proposal_no').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#proposal_date').val().trim()) {
                $('#proposal_date').parent().find('.error-message').removeClass('hidden');
                $('#proposal_date').addClass('border-red-500');
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

            if (!$('#edit_proposal_no').val().trim()) {
                $('#edit_proposal_no').parent().find('.error-message').removeClass('hidden');
                $('#edit_proposal_no').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#edit_proposal_date').val().trim()) {
                $('#edit_proposal_date').parent().find('.error-message').removeClass('hidden');
                $('#edit_proposal_date').addClass('border-red-500');
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
                window.location = '{{ route('role.proposals.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}';
            });
        });
    </script>
@endsection