@extends('layout.app')
@section('meta-information')
    <title>Payment Management</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .modal {
        transition: opacity 0.25s ease;
    }
    .modal-backdrop {
        background-color: rgba(0, 0, 0, 0.5);
    }
    .admin-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .admin-stats-grid .admin-stat-card {
        border-radius: 6px;
        padding: 1.5rem;
        color: white;
        position: relative;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .admin-stats-grid .admin-stat-card.primary {
        /* background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); */
        background: #f4f4f4;
        color: #764ba2;
    }
    
    .admin-stats-grid .admin-stat-card.success {
        /* background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); */
        background: #f4f4f4;
        color: #3aa31f;
    }
    
    .admin-stats-grid .admin-stat-card.warning {
        /* background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); */
        background: #f4f4f4;
        color: #f5576c;
    }
    
    .admin-stats-grid .admin-stat-card.info {
        /* background: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%); */
        background: #f4f4f4;
        color: #129fa7;
    }
    
    .admin-stats-grid .admin-stat-card .position-relative {
        position: relative;
    }
    
    .admin-stats-grid .admin-stat-card .admin-stat-value {
        font-size: 2rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
    }
    
    .admin-stats-grid .admin-stat-card .admin-stat-label {
        font-size: 0.9rem;
        opacity: 0.9;
    }
    
    .admin-stats-grid .admin-stat-card .admin-stat-icon {
        position: absolute;
        top: 0;
        right: 0;
        font-size: 1.5rem;
        opacity: 0.7;
    }
    
    .states-table {
        margin-top: 2rem;
    }
    
    .states-table .states-table-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }
    
    .states-table .states-table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        border-bottom: 1px solid #e9ecef;
    }
    
    .states-table .states-table-header .states-table-title {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        color: #333;
    }
    
    .states-table .states-table-header .btn {
        border-radius: 8px;
        padding: 0.5rem 1rem;
        font-weight: 500;
    }
    
    .states-table .states-table-content {
        padding: 0;
    }
    
    .states-table .states-table-content .alert {
        margin: 1rem;
        border-radius: 8px;
        border: none;
    }
    
    .states-table .states-table-content .alert-success {
        background-color: #d4edda;
        color: #155724;
    }
    
    .states-table .states-table-content .text-center {
        padding: 3rem 1rem;
    }
    
    .states-table .states-table-content .text-center .fa-inbox {
        opacity: 0.5;
    }
    
    .states-table .states-table-content .table-responsive {
        overflow-x: auto;
    }
    
    .states-table .states-table-content .table {
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .states-table .states-table-content .table thead th {
        background-color: #f8f9fa;
        border-bottom: 2px solid #e9ecef;
        padding: 1rem 0.75rem;
        font-weight: 600;
        color: #495057;
    }
    
    .states-table .states-table-content .table tbody td {
        padding: 1rem 0.75rem;
        vertical-align: middle;
        border-bottom: 1px solid #e9ecef;
    }
    
    .states-table .states-table-content .table tbody tr:hover {
        background-color: #f8f9fa;
    }
    
    .states-table .states-table-content .badge {
        font-size: 0.75rem;
        padding: 0.375rem 0.75rem;
        border-radius: 6px;
        font-weight: 500;
    }
    
    .states-table .states-table-content .badge.bg-light {
        color: #6c757d !important;
        background-color: #f8f9fa !important;
    }
    
    .states-table .states-table-content .badge.bg-info {
        background-color: #17a2b8 !important;
    }
    
    .states-table .states-table-content .badge.bg-success {
        background-color: #28a745 !important;
    }
    
    .states-table .states-table-content .badge.bg-secondary {
        background-color: #6c757d !important;
    }

    .states-table .states-table-content .badge.bg-warning {
        background-color: orange !important;
    }
    
    .states-table-header {
        background: linear-gradient(90deg, #1e3a8a 0%, #1e40af 100%);
        color: white
    }

    .states-table .states-table-content .btn-group {
        border-radius: 6px;
        overflow: hidden;
    }
    
    .states-table .states-table-content .btn-group .btn {
        border-radius: 0;
        padding: 0.375rem 0.75rem;
    }
    
    .states-table .states-table-content .btn-group .btn:first-child {
        border-top-left-radius: 6px;
        border-bottom-left-radius: 6px;
    }
    
    .states-table .states-table-content .btn-group .btn:last-child {
        border-top-right-radius: 6px;
        border-bottom-right-radius: 6px;
    }
    
    .states-table .states-table-content .pagination {
        margin-bottom: 0;
        padding: 1rem;
    }
    
    .states-table .states-table-content .pagination .page-link {
        border-radius: 6px;
        margin: 0 0.2rem;
        border: 1px solid #dee2e6;
        color: #007bff;
    }
    
    .states-table .states-table-content .pagination .page-item.active .page-link {
        background-color: #007bff;
        border-color: #007bff;
    }
    
    @media (max-width: 768px) {
        .admin-stats-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .states-table .states-table-header {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
        }
        
        .states-table .states-table-header .btn {
            width: 100%;
        }
    }
</style>
<style>
    .filter-container {
        margin: 15px 15px 0 15px;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .filter-container .filter-header {
        background-color: #f8f9fa;
        padding: 16px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-left: 4px solid #3b82f6;
        transition: background-color 0.3s;
    }

    .filter-container .filter-header:hover {
        background-color: #e9ecef;
    }

    .filter-container .filter-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
    }

    .filter-container .filter-header .toggle-icon {
        transition: transform 0.3s;
    }

    .filter-container .filter-header.active .toggle-icon {
        transform: rotate(180deg);
    }

    .filter-container .filter-content {
        background-color: white;
        padding: 0;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out, padding 0.3s ease-out;
    }

    .filter-container .filter-content.active {
        padding: 20px;
        max-height: 500px;
    }

    .filter-container .filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 16px;
    }

    .filter-container .filter-group {
        flex: 1;
        min-width: 200px;
    }

    .filter-container .filter-group label {
        display: block;
        margin-bottom: 6px;
        font-weight: 500;
        color: #374151;
    }

    .filter-container .filter-group select,
    .filter-container .filter-group input {
        width: 100%;
        padding: 10px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
        transition: border-color 0.3s;
    }

    .filter-container .filter-group select:focus,
    .filter-container .filter-group input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .filter-container .filter-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 20px;
    }

    .filter-container .filter-actions button {
        padding: 10px 20px;
        border-radius: 6px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
    }

    .filter-container .filter-actions .apply-btn {
        background-color: #3b82f6;
        color: white;
        border: none;
    }

    .filter-container .filter-actions .apply-btn:hover {
        background-color: #2563eb;
    }

    .filter-container .filter-actions .reset-btn {
        background-color: #f8f9fa;
        color: #6b7280;
        border: 1px solid #d1d5db;
    }

    .filter-container .filter-actions .reset-btn:hover {
        background-color: #e5e7eb;
    }
    .select2-container .select2-selection--single {        
        height: 42px;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 40px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 42px;
        position: absolute;
        top: 1px;
        right: 3px;
        width: 20px;
    }
    /* Example: change active page background and text */
    span [aria-current="page"] span{
        background-color: #2563eb !important;
        background: #2563eb !important;
        color: white;
        border-color: #2563eb;
    }
</style>
@endsection
@section('main-content')

    <!-- States Table -->    
    <div class="states-table bg-white rounded-lg shadow-md overflow-hidden">
        <div class="states-table-container">
            <div class="states-table-header bg-blue-600 px-6 py-4 flex justify-between items-center">
                <h2 class="states-table-title text-white text-xl font-semibold" style="color: white">
                    <i class="fas fa-list mr-2"></i>Payment List
                </h2>
                <button class="btn btn-primary create-item-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add New Payment
                </button>
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
                                    <label for="user_id">User</label>
                                    <select id="user_id" name="user_id" class="form-control select2" style="width: 100%" onchange="getEmpSalaries(this, '#employee_salary_id')" data-action="{{ route('role.get-employee-salary', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}">
                                        <option value="">All</option>
                                        @foreach ($users as $country)
                                            <option value="{{ $country->id }}" {{ $country->id == request('user_id') ? 'selected' : '' }}>{{ $country->name }}</option>                                            
                                        @endforeach
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="employee_salary_id">Employee Salary</label>
                                    <select id="employee_salary_id" name="employee_salary_id" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>
                                        @if (!empty($request_datas))
                                        @foreach ($request_datas as $reqData)
                                            <option value="{{ $reqData->id }}" {{ $reqData->id == request('employee_salary_id') ? 'selected' : '' }}>{{ $reqData->year }}-{{ $reqData->month }} ({{ $reqData->net_salary }})</option>                                            
                                        @endforeach
                                        @endif
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="payment_date">Payment Date</label>
                                    <input type="date" name="payment_date" id="payment_date" class="form-control">
                                </div>
                                <div class="filter-group">
                                    <label for="transaction_no">Transaction No</label>
                                    <input type="text" name="transaction_no" id="transaction_no" class="form-control" placeholder="Ex: DSUIX898090">
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee Salary</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction No</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
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
                                        {{ $value->employee_salary?->year }}-{{ $value->employee_salary?->month }} ({{ $value->employee_salary?->net_salary }})                                                                                                                                                                                                                                                
                                    </td>                                      
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->payment_date }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->payment_method }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->amount }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->transaction_no }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->notes }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="btn-group btn-group-sm flex space-x-1">
                                            <button class="btn btn-outline-primary edit-item-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200" 
                                                data-item_id="{{ $value->id }}" 
                                                data-user_id="{{ $value->user_id }}"
                                                data-employee_salary_id="{{ $value->employee_salary_id }}"
                                                data-payment_date="{{ $value->payment_date }}"
                                                data-payment_method="{{ $value->payment_method }}"
                                                data-amount="{{ $value->amount }}"
                                                data-transaction_no="{{ $value->transaction_no }}"
                                                data-notes="{{ $value->notes }}"
                                                data-get_target_action="{{ route('role.get-employee-salary', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                                                title="Edit State">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger border border-red-500 text-red-500 hover:bg-red-500 hover:text-white px-3 py-1 rounded-md transition duration-200" onclick="confirmDelete('{{ $value->id }}', '{{ $value->name }}')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-8">
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
    
    @include('payments.create-modal')
    @include('payments.edit-modal')
    @include('payments.delete-modal')

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
            $('.create-item-btn').click(function() {
                $('#createModal').removeClass('hidden');
            });

            // Show edit modal
            $('.edit-item-btn').click(function() {
                const item_id = $(this).data('item_id');
                const userId = $(this).data('user_id');
                const employee_salary_id = $(this).data('employee_salary_id');
                const payment_date = $(this).data('payment_date');
                const payment_method = $(this).data('payment_method');
                const amount = $(this).data('amount');
                const transaction_no = $(this).data('transaction_no');
                const notes = $(this).data('notes');
                
                // Set values in the edit form
                $('#editItemId').val(item_id);
                select2SetValueNoEvent('#edit_user_id', userId);                
                $('#edit_payment_date').val(payment_date);
                $('#edit_payment_method').val(payment_method);
                $('#edit_amount').val(amount);
                $('#edit_transaction_no').val(transaction_no);
                $('#edit_notes').val(notes);
                $('#editModal').removeClass('hidden');
                if (userId) {
                    $.ajax({
                        url: $(this).data('get_target_action'),
                        method: 'GET',
                        data: {
                            user_id: userId
                        },
                        success: function (response) {
                            console.log(response);
                            if (response.success) {                                                                               
                                const targetSelect = $('#edit_employee_salary_id');                                
                                console.log(targetSelect);                        
                                targetSelect.empty();
                                targetSelect.append('<option value="">Select a Item</option>');
                                $.each(response.data, function(index, item) {
                                    targetSelect.append(
                                        `<option value="${item.id}" ${(employee_salary_id == item.id) ? 'selected' : ''}>${item.year}-${item.month} (${item.net_salary})</option>`
                                    );
                                });
                                if (targetSelect.hasClass('select2-hidden-accessible')) {
                                    targetSelect.trigger('change.select2');
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
                    $('#edit_employee_salary_id').val('').trigger('change');  
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

        function getEmpSalaries(obj, target_id){
            $.ajax({
                url: $(obj).data('action'),
                method: 'GET',
                data: {
                    user_id: $(obj).val()
                },
                success: function (response) {
                    console.log(response);
                    if (response.success) {                                                            
                        const targetSelect = $(obj).closest('.closest').find(target_id);
                        console.log(targetSelect);                        
                        targetSelect.empty();
                        targetSelect.append('<option value="">Select a Item</option>');
                        $.each(response.data, function(index, item) {
                            targetSelect.append(
                                `<option value="${item.id}">${item.year}-${item.month} (${item.net_salary})</option>`
                            );
                        });
                        if (targetSelect.hasClass('select2-hidden-accessible')) {
                            targetSelect.trigger('change.select2');
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
            
            $('#createForm .error-message').addClass('hidden');
            $('#createForm .form-select, #createForm .form-input').removeClass('border-red-500');                        

            if (!$('#create_user_id').val() || ($('#create_user_id').val() == '') || ($('#create_user_id').val() == null)) {
                $('#create_user_msg').removeClass('hidden');                
                isValid = false;
            }            
            if (!$('#create_employee_salary_id').val() || ($('#create_employee_salary_id').val() == '') || ($('#create_employee_salary_id').val() == null)) {
                $('#create_employee_salary_msg').removeClass('hidden');
                isValid = false;
            }            
            if (!$('#create_payment_date').val().trim()) {
                $('#create_payment_date').next('.error-message').removeClass('hidden');
                $('#create_payment_date').addClass('border-red-500');
                isValid = false;
            }
            if (!$('#create_payment_method').val().trim()) {
                $('#create_payment_method').next('.error-message').removeClass('hidden');
                $('#create_payment_method').addClass('border-red-500');
                isValid = false;
            }
            if (!$('#create_amount').val().trim()) {
                $('#create_amount').next('.error-message').removeClass('hidden');
                $('#create_amount').addClass('border-red-500');
                isValid = false;
            }  
            
            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            
            $('#editForm .error-message').addClass('hidden');
            $('#editForm .form-select, #editForm .form-input').removeClass('border-red-500');
            
            if (!$('#edit_user_id').val() || ($('#edit_user_id').val() == '') || ($('#edit_user_id').val() == null)) {
                $('#edit_user_msg').removeClass('hidden');                
                isValid = false;
            }            
            if (!$('#edit_employee_salary_id').val() || ($('#edit_employee_salary_id').val() == '') || ($('#edit_employee_salary_id').val() == null)) {
                $('#edit_employee_salary_msg').removeClass('hidden');
                isValid = false;
            }            
            if (!$('#edit_payment_date').val().trim()) {
                $('#edit_payment_date').next('.error-message').removeClass('hidden');
                $('#edit_payment_date').addClass('border-red-500');
                isValid = false;
            }
            if (!$('#edit_payment_method').val().trim()) {
                $('#edit_payment_method').next('.error-message').removeClass('hidden');
                $('#edit_payment_method').addClass('border-red-500');
                isValid = false;
            }
            if (!$('#edit_amount').val().trim()) {
                $('#edit_amount').next('.error-message').removeClass('hidden');
                $('#edit_amount').addClass('border-red-500');
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
                window.location = '{{ route('role.payments.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}';
            });
        });
    </script>
@endsection