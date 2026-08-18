@extends('layout.app')
@section('meta-information')
    <title>Airport Management</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@include('layout.table-design')

@endsection
@section('main-content')
    @include('airport.content')
    @if (false)
    <!-- Stats Overview -->
    <div class="admin-stats-grid">
        <div class="admin-stat-card primary">
            <div class="position-relative">
                <div class="admin-stat-value">{{ $totalAirports }}</div>
                <div class="admin-stat-label">Total Airports</div>
                <div class="admin-stat-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
            </div>
        </div>

        <div class="admin-stat-card success">
            <div class="position-relative">
                <div class="admin-stat-value">{{ $countriesCount }}</div>
                <div class="admin-stat-label">Countries with Airports</div>
                <div class="admin-stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>

        <div class="admin-stat-card success">
            <div class="position-relative">
                <div class="admin-stat-value">{{ $statesCount }}</div>
                <div class="admin-stat-label">States with Airports</div>
                <div class="admin-stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>

    </div>

    <!-- States Table -->    
    <div class="states-table bg-white rounded-lg shadow-md overflow-hidden">
        <div class="states-table-container">
            <div class="states-table-header bg-blue-600 px-6 py-4 flex justify-between items-center">
                <h2 class="states-table-title text-white text-xl font-semibold" style="color: white">
                    <i class="fas fa-list mr-2"></i>Airport List
                </h2>
                @can('create geography')
                <button class="btn btn-primary create-state-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add New Airport
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
                                    <label for="country_id">Country</label>
                                    <select id="country_id" name="country_id" class="form-control select2" style="width: 100%" onchange="getStates(this, '#state_id')" data-action="{{ route('role.get-states-by-country', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}">
                                        <option value="">All Countries</option>
                                        @foreach ($countries as $country)
                                            <option value="{{ $country->id }}" {{ $country->id == request('country_id') ? 'selected' : '' }}>{{ $country->name }}</option>                                            
                                        @endforeach
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="state_id">State</label>
                                    <select id="state_id" name="state_id" class="form-control select2" style="width: 100%">
                                        <option value="">All State</option>
                                        @if (!empty($request_states))
                                        @foreach ($request_states as $state)
                                            <option value="{{ $state->id }}" {{ $state->id == request('state_id') ? 'selected' : '' }}>{{ $state->name }}</option>                                            
                                        @endforeach
                                        @endif
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="name_code">Name/Code</label>
                                    <input type="text" name="name_code" id="name_code" value="{{ request('name_code') }}" placeholder="Type Airport Name or Code...">  
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
                                <th style="width: 10%; padding-left: 20px" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SL</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>                                
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Country</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">State</th>
                                @canany(['edit geography', 'delete geography'])
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
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->name }}</td>                                    
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->code }}</td>                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->country?->name }}                                                                                                                        
                                    </td>  
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->state?->name }}                                                                                                                        
                                    </td>
                                    @canany(['edit geography', 'delete geography'])
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="btn-group btn-group-sm flex space-x-1">
                                            @can('edit geography')
                                            <button class="btn btn-outline-primary edit-state-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200" 
                                                data-country_id="{{ $value->country_id }}" data-state_id="{{ $value->state_id }}" data-item_name="{{ $value->name }}" data-item_code="{{ $value->code }}" data-item_id="{{ $value->id }}" 
                                                data-get_states_action="{{ route('role.get-states-by-country', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                                                data-action="{{ route('role.airport.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'airport' => $value->id]) }}" title="Edit State">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            @endcan
                                            @can('delete geography')
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
                                <td colspan="7" class="text-center py-8">
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
    
    @endif
    @include('airport.create-modal')
    @include('airport.edit-modal')
    @include('airport.delete-modal')

@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {

            // initialized select2
            $('#country_id, #state_id').select2();
            $('#create_country_id, #create_state_id').select2({ dropdownParent: $('#createAirportModal') });
            $('#edit_country_id, #edit_state_id').select2({ dropdownParent: $('#editAirportModal') });

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // Show create modal
            $('.create-state-btn').click(function() {
                $('#createAirportModal').removeClass('hidden');
            });

            // Show edit modal
            $('.edit-state-btn').click(function() {
                const itemId = $(this).data('item_id');
                const stateId = $(this).data('state_id');
                const countryId = $(this).data('country_id');
                const itemName = $(this).data('item_name');
                const itemCode = $(this).data('item_code');
                const formAction = $(this).data('action');
                
                // Set values in the edit form
                $('#editItemId').val(itemId);
                $('#edit_name').val(itemName);
                $('#edit_code').val(itemCode);
                $('#updateFormAction').val(formAction);       
                select2SetValueNoEvent('#edit_country_id', countryId);
                $('#editAirportModal').removeClass('hidden');
                if (countryId) {
                    $.ajax({
                        url: $(this).data('get_states_action'),
                        method: 'GET',
                        data: {
                            country_id: countryId
                        },
                        success: function (response) {
                            console.log(response);
                            if (response.success) {                                                                               
                                const stateSelect = $('#edit_state_id');
                                console.log(stateSelect);                        
                                stateSelect.empty();

                                // Add default option
                                stateSelect.append('<option value="">Select State</option>');

                                // Populate new options
                                $.each(response.data, function(index, state) {
                                    stateSelect.append(
                                        `<option value="${state.id}" ${(stateId == state.id) ? 'selected' : ''}>${state.name}</option>`
                                    );
                                });

                                // Refresh Select2 if you are using it
                                if (stateSelect.hasClass('select2-hidden-accessible')) {
                                    stateSelect.trigger('change.select2');
                                }
                            } else{
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
                } else {
                    $('#edit_state_id').val('').trigger('change');  
                }  
            });

            // Close modals
            $('.modal-close-create, .modal-backdrop').click(function(e) {
                if ($(e.target).closest('.modal-close-create').length || $(e.target).hasClass('modal-backdrop')) {
                    $('#createAirportModal').addClass('hidden');
                }
            });

            $('.modal-close-edit, .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-edit').length) {
                    $('#editAirportModal').addClass('hidden');
                }
            });

            $('.modal-close-delete, .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-delete').length) {
                    $('#deleteAirportModal').addClass('hidden');
                }
            });

            $('.airport-overlay').on('click', function(e) {
                if (e.target === this) {
                    $(this).addClass('hidden');
                }
            });

            // Close success alert
            $('.close-btn').click(function() {
                $(this).closest('.alert').addClass('hidden');
            });

            // Create state form submission
            $('#createStateSubmit').click(function(e) {
                e.preventDefault();
                console.log(validateCreateForm());                
                if (validateCreateForm()) {
                    $.ajax({
                        url: $('#createStateForm').attr('action'),
                        method: 'POST',
                        data: {
                            name: $('#create_name').val(),
                            code: $('#create_code').val(),
                            state_id: $('#create_state_id').val(),
                            country_id: $('#create_country_id').val()
                        },
                        success: function (response) {
                            console.log(response);
                            if (response.success) {
                                Swal.fire({
                                    icon: "success",
                                    title: "Done",
                                    text: "Data created successfully!",
                                });
                                $('#createAirportModal').addClass('hidden');
                                resetCreateForm();
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
                }
            });

            // Edit state form submission
            $('#editAirportSubmit').click(function() {
                if (validateEditForm()) {
                    $.ajax({
                        url: $('#updateFormAction').val(),
                        method: 'PUT',
                        data: {
                            id: $('#editItemId').val(),
                            name: $('#edit_name').val(),
                            code: $('#edit_code').val(),
                            state_id: $('#edit_state_id').val(),
                            country_id: $('#edit_country_id').val()
                        },
                        success: function (response) {
                            console.log(response);
                            if (response.success) {
                                Swal.fire({
                                    icon: "success",
                                    title: "Done",
                                    text: "Data updated successfully!",
                                });
                                $('#editAirportModal').addClass('hidden');                                
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
                }
            });

            // Delete confirmation
            $('#confirmDeleteBtn').click(function() {
                const dataId = $(this).data('airport-id');
                const deleteUrl = $(this).data('action');
                $.ajax({
                    url: deleteUrl,
                    method: 'DELETE',
                    data: {
                        airport_id: dataId,
                    },
                    success: function (response) {
                        console.log(response);
                        if (response.success) {
                            Swal.fire({
                                icon: "success",
                                title: "Done",
                                text: "Data deleted successfully!",
                            });
                            $('#deleteAirportModal').addClass('hidden');
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

        function getStates(obj, targetId){
            $.ajax({
                url: $(obj).data('action'),
                method: 'GET',
                data: {
                    country_id: $(obj).val()
                },
                success: function (response) {
                    console.log(response);
                    if (response.success) {                                                            
                        const stateSelect = $(obj).closest('.closest').find(targetId);
                        console.log(stateSelect);                        
                        stateSelect.empty();

                        // Add default option
                        stateSelect.append('<option value="">Select State</option>');

                        // Populate new options
                        $.each(response.data, function(index, state) {
                            stateSelect.append(
                                `<option value="${state.id}">${state.name}</option>`
                            );
                        });

                        // Refresh Select2 if you are using it
                        if (stateSelect.hasClass('select2-hidden-accessible')) {
                            stateSelect.trigger('change.select2');
                        }
                    } else{
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
        }
        
        // Form validation functions
        function validateCreateForm() {
            let isValid = true;
            
            // Reset error messages
            $('#createStateForm .error-message').addClass('hidden');
            $('#createStateForm .form-select, #createStateForm .form-input').removeClass('border-red-500');                        
            // Validate country                      
            if (!$('#create_country_id').val() || ($('#create_country_id').val() == '') || ($('#create_country_id').val() == null)) {
                $('#create_country_msg').removeClass('hidden');                
                isValid = false;
            }
            
            // Validate state
            if (!$('#create_state_id').val() || ($('#create_state_id').val() == '') || ($('#create_state_id').val() == null)) {
                $('#create_state_msg').removeClass('hidden');
                isValid = false;
            }
            
            // Validate name
            if (!$('#create_name').val().trim()) {
                $('#create_name').next('.error-message').removeClass('hidden');
                $('#create_name').addClass('border-red-500');
                isValid = false;
            }
            
            // Validate code
            if (!$('#create_code').val().trim()) {
                $('#create_code').next('.error-message').removeClass('hidden');
                $('#create_code').addClass('border-red-500');
                isValid = false;
            }
            
            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            
            // Reset error messages
            $('#editAirportForm .error-message').addClass('hidden');
            $('#editAirportForm .form-select, #editAirportForm .form-input').removeClass('border-red-500');
            
            // Validate country
            if (!$('#edit_country_id').val() || ($('#edit_country_id').val() == '') || ($('#edit_country_id').val() == null)) {
                $('#edit_country_msg').removeClass('hidden');                
                isValid = false;
            }
            
            // Validate state
            if (!$('#edit_state_id').val() || ($('#edit_state_id').val() == '') || ($('#edit_state_id').val() == null)) {
                $('#edit_state_msg').removeClass('hidden');
                isValid = false;
            }
            
            // Validate name
            if (!$('#edit_name').val().trim()) {
                $('#edit_name').next('.error-message').removeClass('hidden');
                $('#edit_name').addClass('border-red-500');
                isValid = false;
            }
            
            // Validate code
            if (!$('#edit_code').val().trim()) {
                $('#edit_code').next('.error-message').removeClass('hidden');
                $('#edit_code').addClass('border-red-500');
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
            $('#deleteAirportName').text(name);
            $('#confirmDeleteBtn').data('airport-id', id);
            $('#deleteAirportModal').removeClass('hidden');
        }

    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterHeader = document.querySelector('.filter-container .filter-header');
            const filterContent = document.querySelector('.filter-container .filter-content');
            if (!filterHeader || !filterContent) {
                return;
            }
            
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
                window.location = '{{ route('role.airport.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}';
            });
        });
    </script>
@endsection
