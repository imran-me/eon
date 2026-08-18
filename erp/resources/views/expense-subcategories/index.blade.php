{{--
    SUPERSEDED — no route reaches this file.

    Department, Category and Sub-category were merged onto one screen:
    resources/views/expense-classification/index.blade.php (+ modals.blade.php),
    served by ExpenseClassificationController. /{role}/expense-subcategories redirects there.

    Kept only so the old markup is recoverable from one place. Editing it changes
    nothing a user can see — change the classification page instead.
--}}
@extends('layout.app')
@section('meta-information')
    <title>Manage Expense Sub-Category</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@include('layout.table-design')
<style>
    /* Override shared table-design title color */
    .states-table .states-table-header .states-table-title {
        color: #fff !important;
    }

    .esc-header-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }

    /* Stats bar */
    .esc-stats-bar {
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
        padding: 14px 16px 0;
    }
    .esc-stat-card {
        background: #f5f3ff;
        border: 1px solid #ddd6fe;
        border-radius: 10px;
        padding: 10px 18px;
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 140px;
    }
    .esc-stat-card .stat-icon  { font-size: 20px; color: #7c3aed; }
    .esc-stat-card .stat-label { font-size: 11px; color: #6b7280; font-weight: 500; text-transform: uppercase; letter-spacing: 0.04em; }
    .esc-stat-card .stat-value { font-size: 20px; color: #6d28d9; font-weight: 700; line-height: 1.1; }

    .esc-stat-card.green  { background: #f0fdf4; border-color: #bbf7d0; }
    .esc-stat-card.green  .stat-icon  { color: #16a34a; }
    .esc-stat-card.green  .stat-value { color: #15803d; }

    .esc-stat-card.yellow { background: #fefce8; border-color: #fde68a; }
    .esc-stat-card.yellow .stat-icon  { color: #ca8a04; }
    .esc-stat-card.yellow .stat-value { color: #a16207; }

    /* Category breadcrumb badge */
    .cat-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #ede9fe;
        color: #6d28d9;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 20px;
        white-space: nowrap;
    }
    .cat-badge i { font-size: 9px; }

    /* Subcategory name cell */
    .subcat-name-cell { display: flex; align-items: center; gap: 8px; }
    .subcat-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: #8b5cf6;
        flex-shrink: 0;
    }

    /* Description truncation */
    .desc-cell {
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: #6b7280;
        font-size: 13px;
    }

    /* Action buttons */
    .action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px; height: 32px;
        border-radius: 6px;
        border: 1px solid transparent;
        cursor: pointer;
        transition: all 0.15s;
        font-size: 13px;
        background: transparent;
    }
    .action-btn.edit   { border-color: #7c3aed; color: #7c3aed; }
    .action-btn.edit:hover { background: #7c3aed; color: #fff; }
    .action-btn.delete { border-color: #ef4444; color: #ef4444; }
    .action-btn.delete:hover { background: #ef4444; color: #fff; }

    @media (max-width: 768px) {
        .states-table-header { flex-direction: column; gap: 10px; align-items: flex-start !important; }
        .esc-header-actions  { width: 100%; }
        .esc-stats-bar       { padding: 10px 10px 0; }
        .esc-stat-card       { flex: 1; min-width: 120px; }
    }
</style>
@endsection
@section('main-content')

    @include('layout.expense-tabs')


    <div class="states-table bg-white rounded-lg shadow-md overflow-hidden">
        <div class="states-table-container">

            {{-- Header --}}
            <div class="states-table-header px-6 py-4 flex justify-between items-center" style="background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%)">
                <h2 class="states-table-title text-xl font-semibold" style="color:#fff; margin:0">
                    <i class="fas fa-sitemap mr-2"></i>Expense Sub-Category List
                </h2>
                @can('create expense')
                <div class="esc-header-actions">
                    <button class="create-new-btn inline-flex items-center gap-2 font-semibold px-4 py-2 rounded-lg shadow transition duration-200 text-sm"
                        style="background:#fff; color:#7c3aed">
                        <i class="fas fa-plus"></i>Add Sub-Category
                    </button>
                </div>
                @endcan
            </div>

            {{-- Stats bar --}}
            @php
                $total    = $datas->total();
                $active   = $datas->getCollection()->where('status', 1)->count();
                $inactive = $datas->getCollection()->where('status', 0)->count();
            @endphp
            <div class="esc-stats-bar">
                <div class="esc-stat-card">
                    <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                    <div>
                        <div class="stat-label">Total</div>
                        <div class="stat-value">{{ $total }}</div>
                    </div>
                </div>
                <div class="esc-stat-card green">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <div class="stat-label">Active</div>
                        <div class="stat-value">{{ $active }}</div>
                    </div>
                </div>
                <div class="esc-stat-card yellow">
                    <div class="stat-icon"><i class="fas fa-pause-circle"></i></div>
                    <div>
                        <div class="stat-label">Inactive</div>
                        <div class="stat-value">{{ $inactive }}</div>
                    </div>
                </div>
            </div>

            <div class="states-table-content">

                {{-- Filter --}}
                <form action="" method="get">
                    <div class="filter-container">
                        <div class="filter-header">
                            <h3><i class="fas fa-filter mr-2"></i>Filter Options</h3>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="filter-content">
                            <div class="closest filter-row">
                                <div class="filter-group">
                                    <label for="user_id">Created By</label>
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
                                    <label for="expense_category_id">Expense Category</label>
                                    <select id="expense_category_id" name="expense_category_id" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>
                                        @foreach ($expense_categories as $expense_category)
                                            <option value="{{ $expense_category->id }}" {{ $expense_category->id == request('expense_category_id') ? 'selected' : '' }}>{{ $expense_category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="status">Status</label>
                                    <select id="status" name="status" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>
                                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="name">Name</label>
                                    <input type="text" name="name" value="{{ request('name') }}" id="name" class="form-control" placeholder="Search name…">
                                </div>
                            </div>
                            <div class="filter-actions">
                                <button type="button" class="reset-btn">Reset</button>
                                <button type="submit" class="apply-btn">Apply Filters</button>
                            </div>
                        </div>
                    </div>
                </form>

                {{-- Table --}}
                <div class="table-responsive overflow-x-auto" style="padding: 15px">
                    <table class="table table-hover min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th style="padding-left:20px" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sub-Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parent Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Company</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                @canany(['edit expense', 'delete expense'])
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                @endcanany
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($datas as $key => $value)
                                <tr class="hover:bg-purple-50 transition-colors duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-500" style="padding-left:20px">
                                        {{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="subcat-name-cell">
                                            <span class="subcat-dot"></span>
                                            <span class="font-medium text-gray-800 text-sm">{{ $value->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($value->expense_category?->name)
                                            <span class="cat-badge">
                                                <i class="fas fa-tags"></i>{{ $value->expense_category->name }}
                                            </span>
                                        @else
                                            <span class="text-gray-400 text-sm">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                        {{ $value->company?->name ?? '—' }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="desc-cell" title="{{ $value->description }}">
                                            {{ $value->description ?: '—' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                        {{ $value->user?->name ?? '—' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if ($value->status)
                                            <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 text-xs font-semibold px-2.5 py-1 rounded-full">
                                                <i class="fas fa-circle" style="font-size:6px"></i>Active
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 bg-yellow-100 text-yellow-700 text-xs font-semibold px-2.5 py-1 rounded-full">
                                                <i class="fas fa-circle" style="font-size:6px"></i>Inactive
                                            </span>
                                        @endif
                                    </td>
                                    @canany(['edit expense', 'delete expense'])
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            @can('edit expense')
                                            <button class="action-btn edit edit-item-btn"
                                                data-item_id="{{ $value->id }}"
                                                data-company_id="{{ $value->company_id }}"
                                                data-expense_category_id="{{ $value->expense_category_id }}"
                                                data-name="{{ $value->name }}"
                                                data-description="{{ $value->description }}"
                                                data-status="{{ $value->status }}"
                                                title="Edit">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            @endcan
                                            @can('delete expense')
                                            <button class="action-btn delete" onclick="confirmDelete('{{ $value->id }}', '{{ addslashes($value->name) }}')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            @endcan
                                        </div>
                                    </td>
                                    @endcanany
                                </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="text-center py-12">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="w-16 h-16 rounded-full flex items-center justify-center" style="background:#f5f3ff">
                                            <i class="fas fa-sitemap text-2xl" style="color:#8b5cf6"></i>
                                        </div>
                                        <h4 class="text-gray-600 font-semibold text-lg">No sub-categories found</h4>
                                        <p class="text-gray-400 text-sm">Try adjusting your filters or add a new sub-category.</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="p-3 border-t border-gray-200">
                    {{ $datas->appends(request()->all())->links() }}
                </div>
            </div>
        </div>
    </div>

    @include('expense-subcategories.create-modal')
    @include('expense-subcategories.edit-modal')
    @include('expense-subcategories.delete-modal')

@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {

            $('.select2').select2();

            $.ajaxSetup({
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
            });

            // Show create modal
            $('.create-new-btn').click(function() {
                $('#createModal').removeClass('hidden');
            });

            // Show edit modal
            $('.edit-item-btn').click(function() {
                const item_id              = $(this).data('item_id');
                const company_id           = $(this).data('company_id');
                const expense_category_id  = $(this).data('expense_category_id');
                const name                 = $(this).data('name');
                const description          = $(this).data('description');
                const status               = $(this).data('status');

                $('#editItemId').val(item_id);
                $('#edit_company_id').val(company_id).trigger('change');
                $('#edit_expense_category_id').val(expense_category_id).trigger('change');
                $('#edit_name').val(name);
                $('#edit_description').val(description);
                $('#edit_status').prop('checked', status == 1);
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

            $('.close-btn').click(function() {
                $(this).closest('.alert').addClass('hidden');
            });

            // Create
            $('#createSubmit').click(function(e) {
                e.preventDefault();
                if (!validateCreateForm()) return;
                let formData = new FormData($('#createForm')[0]);
                $.ajax({
                    url: $('#createForm').attr('action'),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Sub-category created successfully!' });
                            $('#createModal').addClass('hidden');
                            $('#createForm')[0].reset();
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Something went wrong.' });
                        }
                    },
                    error: function() {
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to create sub-category.' });
                    }
                });
            });

            // Edit
            $('#editSubmit').click(function() {
                if (!validateEditForm()) return;
                let formData = new FormData($('#editForm')[0]);
                $.ajax({
                    url: $(this).data('action'),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Sub-category updated successfully!' });
                            $('#editModal').addClass('hidden');
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Update failed.' });
                        }
                    },
                    error: function() {
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong!' });
                    }
                });
            });

            // Delete
            $('#confirmDeleteBtn').click(function() {
                const dataId    = $(this).data('item-id');
                const deleteUrl = $(this).data('action');
                $.ajax({
                    url: deleteUrl,
                    method: 'DELETE',
                    data: { item_id: dataId },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Sub-category deleted successfully!' });
                            $('#deleteModal').addClass('hidden');
                            setTimeout(() => window.location.reload(), 500);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops...', text: response.message });
                        }
                    },
                    error: function() {
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong!' });
                    }
                });
            });
        });

        function validateCreateForm() {
            let isValid = true;
            $('#createForm .error-message').addClass('hidden');
            $('#createForm .form-select, #createForm .form-input').removeClass('border-red-500');

            if (!$('#create_name').val().trim()) {
                $('#create_name').next('.error-message').removeClass('hidden');
                $('#create_name').addClass('border-red-500');
                isValid = false;
            }
            if (!$('#create_expense_category_id').val()) {
                $('#create_expense_category_msg').removeClass('hidden');
                isValid = false;
            }
            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            $('#editForm .error-message').addClass('hidden');
            $('#editForm .form-select, #editForm .form-input').removeClass('border-red-500');

            if (!$('#edit_name').val().trim()) {
                $('#edit_name').next('.error-message').removeClass('hidden');
                $('#edit_name').addClass('border-red-500');
                isValid = false;
            }
            if (!$('#edit_expense_category_id').val()) {
                $('#edit_expense_category_msg').removeClass('hidden');
                isValid = false;
            }
            return isValid;
        }

        function confirmDelete(id, name) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            $('#deleteModal').removeClass('hidden');
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterHeader  = document.querySelector('.filter-container .filter-header');
            const filterContent = document.querySelector('.filter-container .filter-content');

            filterHeader.addEventListener('click', function() {
                this.classList.toggle('active');
                filterContent.classList.toggle('active');
            });

            document.querySelector('.reset-btn').addEventListener('click', function(e) {
                e.preventDefault();
                window.location = '{{ route('role.expense-subcategories.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}';
            });
        });
    </script>
@endsection
