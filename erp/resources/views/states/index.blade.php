@extends('layout.app')
@section('meta-information')
    <title>States Management</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @include('layout.table-design')
@endsection
@section('main-content')
    @include('states.content')
    @if (false)
    <div class="mb-4 flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-blue-50 text-blue-600"><i class="fas fa-map-location-dot"></i></span>
            <div><h1 class="text-lg font-bold text-slate-900">States</h1><p class="text-xs text-slate-500">Manage country-wise states used across ticketing and business services.</p></div>
        </div>
        @can('create geography')
            <button type="button" class="create-state-btn rounded-lg bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"><i class="fas fa-plus mr-1"></i>Add State</button>
        @endcan
    </div>
    <!-- Stats Overview -->
    <div class="admin-stats-grid">
        <div class="admin-stat-card primary">
            <div class="position-relative">
                <div class="admin-stat-value">{{ $totalStates }}</div>
                <div class="admin-stat-label">Total States</div>
                <div class="admin-stat-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
            </div>
        </div>

        <div class="admin-stat-card success">
            <div class="position-relative">
                <div class="admin-stat-value">{{ $activeStates }}</div>
                <div class="admin-stat-label">Active States</div>
                <div class="admin-stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>

        <div class="admin-stat-card warning">
            <div class="position-relative">
                <div class="admin-stat-value">{{ $inactiveStates }}</div>
                <div class="admin-stat-label">Inactive States</div>
                <div class="admin-stat-icon">
                    <i class="fas fa-pause-circle"></i>
                </div>
            </div>
        </div>

        <div class="admin-stat-card info">
            <div class="position-relative">
                <div class="admin-stat-value">{{ $countriesCount }}</div>
                <div class="admin-stat-label">Countries</div>
                <div class="admin-stat-icon">
                    <i class="fas fa-globe"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- States Table -->    
    <div class="states-table bg-white rounded-lg shadow-md overflow-hidden">
        <div class="states-table-container">
            <div class="states-table-header bg-blue-600 px-6 py-4 flex justify-between items-center">
                <h2 class="states-table-title text-white text-xl font-semibold" style="color: white">
                    <i class="fas fa-list mr-2"></i>States List
                </h2>
                @can('create geography')
                <button class="btn btn-primary create-state-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add New State
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
                                    <label for="country_id">Country</label>
                                    <select id="country_id" name="country_id" class="form-control select2" style="width: 100%">
                                        <option value="">All Countries</option>
                                        @foreach ($countries as $country)
                                            <option value="{{ $country->id }}" {{ $country->id == request('country_id') ? 'selected' : '' }}>{{ $country->name }}</option>                                            
                                        @endforeach
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="state_name">State</label>
                                    <input type="text" name="state_name" id="state_name" value="{{ request('state_name') }}" placeholder="Type State Name...">
                                </div>
                                <div class="filter-group">
                                    <label for="status">Status</label>
                                    <select id="status" name="status" class="form-control select2" style="width: 100%">
                                        <option value="" {{ request('status') === null || request('status') === '' ? 'selected' : '' }}>All Statuses</option>
                                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                                    </select>                                    
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
                @if ($totalStates == 0)
                <div class="text-center pt-4">
                    <i class="fas fa-inbox fa-3x text-gray-400 mb-4"></i>
                    <h4 class="text-gray-500 text-xl font-medium mb-2">No states found</h4>
                    <p class="text-gray-400 mb-4">Get started by adding your first state.</p>
                    <button class="btn btn-primary create-state-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Add New State
                    </button>
                </div>                    
                @else                    
                <div class="table-responsive overflow-x-auto" style="padding: 15px">
                    <table class="table table-hover min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th style="width: 10%; padding-left: 20px" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SL</th>
                                <th style="width: 25%" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Country</th>
                                <th style="width: 25%" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">State</th>
                                <th style="width: 10%" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th style="width: 15%" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                @canany(['edit geography', 'delete geography'])
                                <th style="width: 15%" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                @endcanany
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($states as $key => $state)                       
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap" style="padding-left: 20px">
                                        <strong>{{ ($states->currentPage() - 1) * $states->perPage() + $key + 1 }}</strong>
                                    </td>                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $state->country?->name }}                                                                                                                        
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $state->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if ($state->status)
                                        <span class="badge text-white bg-green-500 px-2 py-1 rounded-full text-xs">
                                            Active
                                        </span>                                            
                                        @else                                            
                                        <span class="badge text-white bg-yellow-500 px-2 py-1 rounded-full text-xs">
                                            Inactive
                                        </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <small class="text-gray-500">{{ date('M d, Y', strtotime($state->created_at)) }}</small>
                                    </td>
                                    @canany(['edit geography', 'delete geography'])
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="btn-group btn-group-sm flex space-x-1">
                                            @can('edit geography')
                                            <button class="btn btn-outline-primary edit-state-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200" data-country_id="{{ $state->country_id }}" data-country_name="{{ $state->country?->name }}" data-state_name="{{ $state->name }}" data-state_id="{{ $state->id }}" data-state_status="{{ $state->status }}" data-action="{{ route('role.states.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'state' => $state->id]) }}" title="Edit State">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            @endcan
                                            @can('delete geography')
                                            <button class="btn btn-outline-danger border border-red-500 text-red-500 hover:bg-red-500 hover:text-white px-3 py-1 rounded-md transition duration-200" onclick="confirmDelete('{{ $state->id }}', '{{ $state->name }}')">
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
                                    <h4 class="text-gray-500 text-xl font-medium mb-2">No states found</h4>
                                    <p class="text-gray-400 mb-4">Try filtering with different datas.</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @endif

                <!-- Pagination -->
                <div class="p-3 border-t border-gray-200">                    
                    {{ $states->appends(request()->all())->links() }}
                </div>
            </div>
        </div>
    </div>
    
    @endif
    @include('states.create-modal')
    @include('states.edit-modal')
    @include('states.delete-modal')

@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {

            // initialized select2
            $('#country_id, #status').select2();
            $('#create_country_id').select2({ dropdownParent: $('#createStateModal') });
            $('#editCountry').select2({ dropdownParent: $('#editStateModal') });

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // Show create modal
            $('.create-state-btn').click(function() {
                $('#createStateModal').removeClass('hidden');
            });

            // Show edit modal
            $('.edit-state-btn').click(function() {
                const stateId = $(this).data('state_id');
                const stateName = $(this).data('state_name');
                const countryId = $(this).data('country_id');
                const countryName = $(this).data('country_name');
                const formAction = $(this).data('action');
                const isActive = $(this).data('state_status');                                                
                
                // Set values in the edit form
                $('#editStateId').val(stateId);
                $('#editStateName').val(stateName);
                $('#editCountry').val(countryId).trigger('change');
                $('#editStatus').prop('checked', isActive);         
                $('#updateFormAction').val(formAction);       
                $('#editStateModal').removeClass('hidden');
            });

            // Close modals
            $('.modal-close-create, .modal-backdrop').click(function(e) {
                if ($(e.target).closest('.modal-close-create').length || $(e.target).hasClass('modal-backdrop')) {
                    $('#createStateModal').addClass('hidden');
                }
            });

            $('.modal-close-edit, .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-edit').length) {
                    $('#editStateModal').addClass('hidden');
                }
            });

            $('.modal-close-delete, .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-delete').length) {
                    $('#deleteStateModal').addClass('hidden');
                }
            });

            $('.state-overlay').on('click', function(e) {
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
                            country_id: $('#create_country_id').val(),
                            name: $('#create_stateName').val(),
                            state_status: $('#create_status').is(':checked') ? 1 : 0,
                        },
                        success: function (response) {
                            console.log(response);
                            if (response.success) {
                                Swal.fire({
                                    icon: "success",
                                    title: "Done",
                                    text: "State created successfully!",
                                });
                                $('#createStateModal').addClass('hidden');
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
            $('#editStateSubmit').click(function() {
                if (validateEditForm()) {
                    $.ajax({
                        url: $('#updateFormAction').val(),
                        method: 'PUT',
                        data: {
                            id: $('#editStateId').val(),
                            name: $('#editStateName').val(),
                            country_id: $('#editCountry').val(),
                            state_status: $('#editStatus').is(':checked') ? 1 : 0,
                        },
                        success: function (response) {
                            console.log(response);
                            if (response.success) {
                                Swal.fire({
                                    icon: "success",
                                    title: "Done",
                                    text: "State updated successfully!",
                                });
                                $('#editStateModal').addClass('hidden');                                
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
                const stateId = $(this).data('state-id');
                const deleteUrl = $(this).data('action');
                $.ajax({
                    url: deleteUrl,
                    method: 'DELETE',
                    data: {
                        state_id: stateId,
                    },
                    success: function (response) {
                        console.log(response);
                        if (response.success) {
                            Swal.fire({
                                icon: "success",
                                title: "Done",
                                text: "State deleted successfully!",
                            });
                            $('#deleteStateModal').addClass('hidden');
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
            $('#createStateForm .error-message').addClass('hidden');
            $('#createStateForm .form-select, #createStateForm .form-input').removeClass('border-red-500');
            
            // Validate country
            if (!$('#create_country_id').val()) {
                $('#create_country_id').next('.error-message').removeClass('hidden');
                $('#create_country_id').addClass('border-red-500');
                isValid = false;
            }
            
            // Validate state name
            if (!$('#create_stateName').val().trim()) {
                $('#create_stateName').next('.error-message').removeClass('hidden');
                $('#create_stateName').addClass('border-red-500');
                isValid = false;
            }
            
            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            
            // Reset error messages
            $('#editStateForm .error-message').addClass('hidden');
            $('#editStateForm .form-select, #editStateForm .form-input').removeClass('border-red-500');
            
            // Validate country
            if (!$('#editCountry').val()) {
                $('#editCountry').next('.error-message').removeClass('hidden');
                $('#editCountry').addClass('border-red-500');
                isValid = false;
            }
            
            // Validate state name
            if (!$('#editStateName').val().trim()) {
                $('#editStateName').next('.error-message').removeClass('hidden');
                $('#editStateName').addClass('border-red-500');
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
            $('#deleteStateName').text(name);
            $('#confirmDeleteBtn').data('state-id', id);
            $('#deleteStateModal').removeClass('hidden');
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
                window.location = '{{ route('role.states.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}';
            });
        });
    </script>
@endsection
