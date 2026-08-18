@extends('layout.app')
@section('meta-information')
    <title>Manage Companies</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@include('layout.table-design')
<style>
    .company-table { width:100%; border-collapse:collapse; }
    .company-table thead tr { background:#f1f5f9; }
    .company-table thead th {
        padding:12px 16px; text-align:left; font-weight:600;
        font-size:13px; color:#475569; text-transform:uppercase; letter-spacing:.5px;
        border-bottom:2px solid #e2e8f0; white-space:nowrap;
    }
    .company-table tbody tr { border-bottom:1px solid #f1f5f9; transition:background .15s; }
    .company-table tbody tr:hover { background:#f8fafc; }
    .company-table tbody td { padding:12px 16px; font-size:14px; color:#334155; vertical-align:middle; }
    .company-logo {
        width:44px; height:44px; border-radius:8px; object-fit:contain;
        background:#f8fafc; border:1px solid #e2e8f0; padding:3px;
    }
    .company-name { font-weight:600; color:#1f2937; }
    .company-meta { display:flex; flex-direction:column; gap:3px; font-size:13px; color:#64748b; line-height:1.35; }
    .company-meta span { display:inline-flex; align-items:center; gap:6px; word-break:break-word; }
    .company-link {
        display:inline-flex; align-items:center; gap:6px; color:#2563eb;
        font-weight:600; font-size:13px; text-decoration:none;
    }
    .company-link:hover { color:#1d4ed8; text-decoration:underline; }
    .status-badge {
        display:inline-block; padding:3px 10px; border-radius:999px;
        font-size:12px; font-weight:600;
    }
    .status-active { background:#d1fae5; color:#065f46; }
    .status-inactive { background:#fee2e2; color:#b91c1c; }
    .btn-action {
        display:inline-flex; align-items:center; justify-content:center;
        width:32px; height:32px; border-radius:6px; border:none;
        cursor:pointer; font-size:13px; transition:all .2s;
    }
    .btn-edit { background:#dbeafe; color:#1d4ed8; }
    .btn-edit:hover { background:#bfdbfe; }
    .btn-delete { background:#fee2e2; color:#b91c1c; }
    .btn-delete:hover { background:#fecaca; }
    .empty-state { text-align:center; padding:48px 20px; color:#94a3b8; }
    .empty-state i { font-size:40px; margin-bottom:12px; display:block; }
    .empty-state h4 { font-size:18px; font-weight:600; color:#64748b; margin-bottom:6px; }
    .empty-state p { font-size:14px; margin:0; }
    .company-pagination {
        margin-top:16px; display:flex; justify-content:space-between;
        align-items:center; gap:16px; font-size:14px; color:#64748b;
    }
    .filter-container .filter-content.active { padding:20px; max-height:500px; }
    .select2-container .select2-selection--single { height:42px; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height:40px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height:42px; top:1px; right:3px; width:20px; }
    span [aria-current="page"] span {
        background-color:#2563eb !important; background:#2563eb !important;
        color:#fff; border-color:#2563eb;
    }
    @media (max-width:768px) {
        .states-table-header { align-items:flex-start; flex-direction:column; gap:12px; }
        .company-table thead { display:none; }
        .company-table tbody tr { display:block; margin-bottom:12px; border:1px solid #e2e8f0; border-radius:8px; }
        .company-table tbody td {
            display:flex; justify-content:space-between; gap:16px;
            padding:10px 14px; border-bottom:1px solid #f1f5f9;
        }
        .company-table tbody td:last-child { border-bottom:none; }
        .company-table tbody td::before {
            content:attr(data-label); font-weight:600; color:#64748b;
            font-size:12px; text-transform:uppercase; flex:0 0 110px;
        }
        .company-meta { align-items:flex-end; text-align:right; }
        .company-pagination { align-items:flex-start; flex-direction:column; }
    }
</style>
@endsection
@section('main-content')
    <!-- Stats Overview -->
    <div class="admin-stats-grid">
        <div class="admin-stat-card primary">
            <div class="position-relative">
                <div class="admin-stat-value">{{ $totalCompanies }}</div>
                <div class="admin-stat-label">Total Companies</div>
                <div class="admin-stat-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
            </div>
        </div>

        <div class="admin-stat-card success">
            <div class="position-relative">
                <div class="admin-stat-value">{{ $activeCompanies }}</div>
                <div class="admin-stat-label">Active Companies</div>
                <div class="admin-stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>

        <div class="admin-stat-card warning">
            <div class="position-relative">
                <div class="admin-stat-value">{{ $inactiveCompanies }}</div>
                <div class="admin-stat-label">Inactive Companies</div>
                <div class="admin-stat-icon">
                    <i class="fas fa-pause-circle"></i>
                </div>
            </div>
        </div>

    </div>

    <!-- States Table -->
    <div class="states-table bg-white rounded-lg shadow-md overflow-hidden">
        <div class="states-table-container">
            <div class="states-table-header bg-blue-600 px-6 py-4 flex justify-between items-center">
                <h2 class="states-table-title text-white text-xl font-semibold" style="color: white">
                    <i class="fas fa-list mr-2"></i>Company List
                </h2>
                @can('create company setting')
                <button class="btn btn-primary create-btn bg-white text-blue-600 hover:bg-blue-50 px-4 py-2 rounded-md font-medium transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add New Company
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
                        <div class="filter-content {{ request()->hasAny(['name','email','phone','status']) ? 'active' : '' }}">
                            <div class="filter-row">
                                <div class="filter-group">
                                    <label for="name">Name</label>
                                    <input type="text" name="name" id="name" value="{{ request('name') }}" placeholder="Type Name...">
                                </div>
                                <div class="filter-group">
                                    <label for="email">Email</label>
                                    <input type="text" name="email" id="email" value="{{ request('email') }}" placeholder="Type Email...">
                                </div>
                                <div class="filter-group">
                                    <label for="phone">Phone</label>
                                    <input type="text" name="phone" id="phone" value="{{ request('phone') }}" placeholder="Type Phone...">
                                </div>
                                <div class="filter-group">
                                    <label for="status">Status</label>
                                    <select name="status" id="status" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>
                                        <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>Active</option>
                                        <option value="0" {{ request('status') == '0' ? 'selected' : '' }}>Inactive</option>
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
                <div class="p-4 overflow-x-auto">
                    @if($datas->isEmpty())
                        <div class="empty-state">
                            <i class="fas fa-building"></i>
                            <h4>No companies found</h4>
                            <p>Try adjusting your filters or add a new company.</p>
                        </div>
                    @else
                    <table class="company-table">
                        <thead>
                            <tr>
                                <th>Logo</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Website</th>
                                <th>Address</th>
                                <th>Joined</th>
                                <th>Status</th>
                                @canany(['edit company setting', 'delete company setting'])
                                <th class="text-center">Actions</th>
                                @endcanany
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($datas as $key => $value)
                                <tr>
                                    <td data-label="Logo">
                                        <img src="{{ asset($value->logo) }}" alt="{{ $value->name }} logo" class="company-logo">
                                    </td>
                                    <td data-label="Name">
                                        <span class="company-name">{{ $value->name }}</span>
                                    </td>
                                    <td data-label="Contact">
                                        <div class="company-meta">
                                            <span><i class="fas fa-envelope text-gray-400"></i>{{ $value->email }}</span>
                                            <span><i class="fas fa-phone text-gray-400"></i>{{ $value->phone }}</span>
                                        </div>
                                    </td>
                                    <td data-label="Website">
                                        @if($value->website)
                                            <a href="{{ $value->website }}" target="_blank" class="company-link">
                                                <i class="fas fa-external-link-alt"></i>Visit
                                            </a>
                                        @else
                                            <span class="text-gray-400 text-sm">N/A</span>
                                        @endif
                                    </td>
                                    <td data-label="Address">{{ $value->address }}</td>
                                    <td data-label="Joined">
                                        <small class="text-gray-500">{{ date('M d, Y', strtotime($value->created_at)) }}</small>
                                    </td>
                                    <td data-label="Status">
                                        @if ($value->status)
                                            <span class="status-badge status-active">Active</span>
                                        @else
                                            <span class="status-badge status-inactive">Inactive</span>
                                        @endif
                                    </td>
                                    @canany(['edit company setting', 'delete company setting'])
                                    <td data-label="Actions" class="text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            @can('edit company setting')
                                            <button class="btn-action btn-edit edit-btn"
                                                data-item_id="{{ $value->id }}"
                                                data-item_name="{{ $value->name }}"
                                                data-item_short_name="{{ $value->short_name }}"
                                                data-item_email="{{ $value->email }}"
                                                data-item_phone="{{ $value->phone }}"
                                                data-item_website="{{ $value->website }}"
                                                data-item_address="{{ $value->address }}"
                                                data-item_logo="{{ $value->logo }}"
                                                data-item_icon="{{ $value->icon }}"
                                                data-item_status="{{ $value->status }}"
                                                data-item_attendance_device_serial_no="{{ $value->attendance_device_serial_no }}"
                                                data-item_company_order="{{ $value->order }}"
                                                data-action="{{ route('role.company-settings.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'company_setting' => $value->id]) }}"
                                                title="Edit Item">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            @endcan
                                            @can('delete company setting')
                                            <button class="btn-action btn-delete" onclick="confirmDelete('{{ $value->id }}', '{{ $value->name }}')">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                            @endcan
                                        </div>
                                    </td>
                                    @endcanany
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    @if($datas->hasPages())
                        <div class="company-pagination">
                            <span>
                                Showing {{ $datas->firstItem() }}-{{ $datas->lastItem() }}
                                of {{ $datas->total() }} companies
                            </span>
                            <div>{{ $datas->appends(request()->all())->links() }}</div>
                        </div>
                    @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    @include('company.create-modal')
    @include('company.edit-modal')
    @include('company.delete-modal')

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
            $('.create-btn').click(function() {
                $('#createModal').removeClass('hidden');
            });

            // Show edit modal
            $('.edit-btn').click(function() {
                const $btn = $(this);
                const item_id       = $btn.data('item_id');
                const item_name     = $btn.data('item_name');
                const item_short    = $btn.data('item_short_name');
                const item_email    = $btn.data('item_email');
                const item_phone    = $btn.data('item_phone');
                const item_address  = $btn.data('item_address');
                const item_website  = $btn.data('item_website');
                const item_logo     = $btn.data('item_logo');
                const item_icon     = $btn.data('item_icon');
                const item_status   = $btn.data('item_status');
                const item_serial   = $btn.data('item_attendance_device_serial_no');
                const item_order    = $btn.data('item_company_order');
                const action        = $btn.data('action');

                $('#editItemId').val(item_id);
                $('#edit_name').val(item_name);
                $('#edit_short_name').val(item_short || '');
                $('#edit_email').val(item_email);
                $('#edit_phone').val(item_phone);
                $('#edit_website').val(item_website || '');
                $('#edit_address').val(item_address || '');
                $('#edit_attendance_device_serial_no').val(item_serial || '');
                $('#edit_company_order').val(item_order || 0);
                $('#preview_logo').attr('src', item_logo ? window.location.origin + '/' + item_logo : '');
                $('#preview_icon').attr('src', item_icon ? window.location.origin + '/' + item_icon : '');
                $('#edit_status').prop('checked', item_status == 1);
                $('#editSubmit').data('action', action);
                $('#editModal').removeClass('hidden');
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
                                    text: 'Company created successfully!'
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
                                text: 'Failed to create company.'
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
                                    text: "Company updated successfully!",
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

            if (!$('#create_name').val().trim()) {
                $('#create_name').next('.error-message').removeClass('hidden');
                $('#create_name').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#create_email').val().trim()) {
                $('#create_email').next('.error-message').removeClass('hidden');
                $('#create_email').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#create_phone').val().trim()) {
                $('#create_phone').next('.error-message').removeClass('hidden');
                $('#create_phone').addClass('border-red-500');
                isValid = false;
            }

            return isValid;
        }

        function validateEditForm() {
            let isValid = true;

            // Reset error messages
            $('#editForm .error-message').addClass('hidden');
            $('#editForm .form-select, #editForm .form-input').removeClass('border-red-500');

            if (!$('#edit_name').val().trim()) {
                $('#edit_name').next('.error-message').removeClass('hidden');
                $('#edit_name').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#edit_email').val().trim()) {
                $('#edit_email').next('.error-message').removeClass('hidden');
                $('#edit_email').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#edit_phone').val().trim()) {
                $('#edit_phone').next('.error-message').removeClass('hidden');
                $('#edit_phone').addClass('border-red-500');
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

            if ('{{ request()->hasAny(['name','email','phone','status']) ? '1' : '' }}' === '1') {
                filterHeader.classList.add('active');
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
                window.location = '{{ route('role.company-settings.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}';
            });
        });
    </script>
@endsection
