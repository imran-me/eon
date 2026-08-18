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
                    <i class="fas fa-list mr-2"></i>Deal Manager List
                </h2>
                @can('create deal')
                <button class="btn btn-primary create-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add New Deal
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
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Deal Name</label>
                                    <input type="text" name="deal_name" id="filter_deal_name" value="{{ request('deal_name') }}" 
                                        class="form-input w-full px-3 py-2 border border-gray-300 rounded-md" 
                                        placeholder="Search by deal name">
                                </div>
                                <div class="filter-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Lead</label>
                                    <select name="lead_id" id="filter_lead" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <option value="">All Leads</option>
                                        @foreach($leads as $lead)
                                            <option value="{{ $lead->id }}" {{ request('lead_id') == $lead->id ? 'selected' : '' }}>
                                                {{ $lead->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Deal Agent</label>
                                    <select name="deal_agent" id="filter_deal_agent" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <option value="">All Agents</option>
                                        @foreach($deal_agents as $agent)
                                            <option value="{{ $agent->id }}" {{ request('deal_agent') == $agent->id ? 'selected' : '' }}>
                                                {{ $agent->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Stage</label>
                                    <select name="stage" id="filter_stage" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <option value="">All Stages</option>
                                        <option value="generated" {{ request('stage') == 'generated' ? 'selected' : '' }}>Generated</option>
                                        <option value="qualified" {{ request('stage') == 'qualified' ? 'selected' : '' }}>Qualified</option>
                                        <option value="initial_contact" {{ request('stage') == 'initial_contact' ? 'selected' : '' }}>Initial Contact</option>
                                        <option value="schedule_appointment" {{ request('stage') == 'schedule_appointment' ? 'selected' : '' }}>Schedule Appointment</option>
                                        <option value="proposal_sent" {{ request('stage') == 'proposal_sent' ? 'selected' : '' }}>Proposal Sent</option>
                                        <option value="win" {{ request('stage') == 'win' ? 'selected' : '' }}>Win</option>
                                        <option value="lost" {{ request('stage') == 'lost' ? 'selected' : '' }}>Lost</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Product</label>
                                    <select name="product_id" id="filter_product" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <option value="">All Products</option>
                                        @foreach($products as $product)
                                            <option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>
                                                {{ $product->name }}
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lead</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deal Agent</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deal Watcher</th>                                                                                                                     
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Products</th>   
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deal Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stage</th>   
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>   
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Closing Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Note</th>   
                                @canany(['edit deal', 'delete deal'])
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
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->lead->name ?? 'N/A' }}</td>                                                                     
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->dealAgent->name ?? 'N/A' }}</td>                                                                     
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->dealWatcher->name ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->product->name ?? 'N/A' }}</td>    
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->deal_name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if ($value->stage == 'generated')
                                        <span class="badge text-white bg-green-500 px-2 py-1 rounded-full text-xs">
                                            Generated
                                        </span>                                            
                                        @elseif($value->stage == 'qualified')                                            
                                        <span class="badge text-white bg-blue-500 px-2 py-1 rounded-full text-xs">
                                            Qualified
                                        </span>
                                        @elseif($value->stage == 'initial_contact')                                            
                                        <span class="badge text-white bg-yellow-500 px-2 py-1 rounded-full text-xs">
                                            Initial Contact
                                        </span>
                                        @elseif($value->stage == 'schedule_appointment')                                            
                                        <span class="badge text-white bg-orange-500 px-2 py-1 rounded-full text-xs">
                                            Schedule Appointment
                                        </span>
                                        @elseif($value->stage == 'proposal_sent')                                            
                                        <span class="badge text-white bg-purple-500 px-2 py-1 rounded-full text-xs">
                                            Proposal Sent
                                        </span>
                                         @elseif($value->stage == 'win')                                            
                                        <span class="badge text-white bg-green-500 px-2 py-1 rounded-full text-xs">
                                            Win
                                        </span>
                                        @elseif($value->stage == 'lost')                                            
                                        <span class="badge text-white bg-red-500 px-2 py-1 rounded-full text-xs">
                                            Lost
                                        </span>
                                        @endif          
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->amount }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <small class="text-gray-500">{{ date('M d, Y', strtotime($value->closing_date)) }}</small>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->notes }}</td>
                                    @canany(['edit deal', 'delete deal'])                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="btn-group btn-group-sm flex space-x-1">
                                            @can('edit deal')
                                            <button class="btn btn-outline-primary edit-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200" 
                                                data-item_id="{{ $value->id }}" 
                                                data-lead_id="{{ $value->lead_id }}" 
                                                data-deal_agent="{{ $value->deal_agent }}" 
                                                data-deal_watcher="{{ $value->deal_watcher }}" 
                                                data-deal_name="{{ $value->deal_name }}" 
                                                data-product_id="{{ $value->product_id }}" 
                                                {{-- data-pipeline="{{ $value->pipeline }}"  --}}
                                                data-stage="{{ $value->stage }}" 
                                                data-amount="{{ $value->amount }}" 
                                                data-closing_date="{{ $value->closing_date }}" 
                                                data-notes="{{ $value->notes }}"
                                                data-action="{{ route('role.deals.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'deal' => $value->id]) }}" title="Edit Item">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            @endcan
                                            @can('delete deal')
                                            <button class="btn btn-outline-danger border border-red-500 text-red-500 hover:bg-red-500 hover:text-white px-3 py-1 rounded-md transition duration-200" onclick="confirmDelete('{{ $value->id }}', '{{ $value->name }}')">
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
    
    @include('deals.create-modal')
    @include('deals.edit-modal')
    @include('deals.delete-modal')

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
                const lead_id = $(this).data('lead_id');
                const deal_agent = $(this).data('deal_agent');
                const deal_watcher = $(this).data('deal_watcher');
                const product_id = $(this).data('product_id');
                const deal_name = $(this).data('deal_name');
                const pipeline = $(this).data('pipeline');
                const item_stage = $(this).data('stage');
                const item_notes = $(this).data('notes');

                const amount = $(this).data('amount');
                const closing_date = $(this).data('closing_date');
                
                $('#editModal').removeClass('hidden');

                // Initialize select2 after modal is shown
                $('#editModal .select2').select2();
                
                // Set values in the edit form
                $('#editItemId').val(item_id);
                $('#edit_deal_name').val(deal_name);
                $('#edit_pipeline').val(pipeline);
                $('#edit_notes').val(item_notes);
                $('#edit_closing_date').val(closing_date);
                $('#edit_amount').val(amount);
                // Set select values
                $('#edit_lead_id').val(lead_id).trigger('change');
                $('#edit_deal_agent').val(deal_agent).trigger('change');
                $('#edit_deal_watcher').val(deal_watcher).trigger('change');
                $('#edit_product_id').val(product_id).trigger('change');
                $('#edit_stage').val(item_stage).trigger('change');
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
                                text: 'Failed to create leave-types.'
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
            
            if (!$('#lead_id').val().trim()) {
                $('#lead_id').parent().find('.error-message').removeClass('hidden');
                $('#lead_id').addClass('border-red-500');
                isValid = false;
            }

            // if (!$('#deal_agent').val().trim()) {
            //     $('#deal_agent').parent().find('.error-message').removeClass('hidden');
            //     $('#deal_agent').addClass('border-red-500');
            //     isValid = false;
            // }

            if (!$('#deal_watcher').val().trim()) {
                $('#deal_watcher').parent().find('.error-message').removeClass('hidden');
                $('#deal_watcher').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#deal_name').val().trim()) {
                $('#deal_name').parent().find('.error-message').removeClass('hidden');
                $('#deal_name').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#product_id').val().trim()) {
                $('#product_id').parent().find('.error-message').removeClass('hidden');
                $('#product_id').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#stage').val().trim()) {
                $('#stage').parent().find('.error-message').removeClass('hidden');
                $('#stage').addClass('border-red-500');
                isValid = false;
            }
            
            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            
            // Reset error messages
            $('#editForm .error-message').addClass('hidden');
            $('#editForm .form-select, #editForm .form-input').removeClass('border-red-500');
            
            let editLeadId = $('#edit_lead_id').val();

            if (!editLeadId || editLeadId === '' || editLeadId === 'undefined' || isNaN(editLeadId)) {
                $('#edit_lead_id').parent().find('.error-message').removeClass('hidden');
                $('#edit_lead_id').addClass('border-red-500');
                isValid = false;
            }

            // let editDealAgent = $('#edit_deal_agent').val();
            // if (!editDealAgent || editDealAgent === '' || editDealAgent === 'undefined' || isNaN(editDealAgent)) {
            //     $('#edit_deal_agent').parent().find('.error-message').removeClass('hidden');
            //     $('#edit_deal_agent').addClass('border-red-500');
            //     isValid = false;
            // }

            let editDealWatcher = $('#edit_deal_watcher').val();
            if (!editDealWatcher || editDealWatcher === '' || editDealWatcher === 'undefined' || isNaN(editDealWatcher)) {
                $('#edit_deal_watcher').parent().find('.error-message').removeClass('hidden');
                $('#edit_deal_watcher').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#edit_deal_name').val().trim()) {
                $('#edit_deal_name').parent().find('.error-message').removeClass('hidden');
                $('#edit_deal_name').addClass('border-red-500');
                isValid = false;
            }

            let editProductId = $('#edit_product_id').val();
            if (!editProductId || editProductId === '' || editProductId === 'undefined' || isNaN(editProductId)) {
                $('#edit_product_id').parent().find('.error-message').removeClass('hidden');
                $('#edit_product_id').addClass('border-red-500');
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
                window.location = '{{ route('role.deals.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}';
            });
        });
    </script>
@endsection