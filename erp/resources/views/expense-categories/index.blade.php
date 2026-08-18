{{--
    SUPERSEDED — no route reaches this file.

    Department, Category and Sub-category were merged onto one screen:
    resources/views/expense-classification/index.blade.php (+ modals.blade.php),
    served by ExpenseClassificationController. /{role}/expense-categories redirects there.

    Kept only so the old markup is recoverable from one place. Editing it changes
    nothing a user can see — change the classification page instead.
--}}
@extends('layout.app')
@section('meta-information')
    <title>Manage Expense Category</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@include('layout.table-design')
<style>
    .ec-header-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }

    .ec-stats-bar {
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
        padding: 14px 16px 0;
    }

    .ec-stat-card {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 10px;
        padding: 10px 18px;
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 140px;
    }

    .ec-stat-card .stat-icon { font-size: 20px; color: #2563eb; }
    .ec-stat-card .stat-label { font-size: 11px; color: #6b7280; font-weight: 500; text-transform: uppercase; letter-spacing: 0.04em; }
    .ec-stat-card .stat-value { font-size: 20px; color: #1d4ed8; font-weight: 700; line-height: 1.1; }

    .ec-stat-card.green  { background: #f0fdf4; border-color: #bbf7d0; }
    .ec-stat-card.green  .stat-icon { color: #16a34a; }
    .ec-stat-card.green  .stat-value { color: #15803d; }

    .ec-stat-card.yellow { background: #fefce8; border-color: #fde68a; }
    .ec-stat-card.yellow .stat-icon { color: #ca8a04; }
    .ec-stat-card.yellow .stat-value { color: #a16207; }

    /* Override table-design title color */
    .states-table .states-table-header .states-table-title {
        color: #fff !important;
    }

    /* Name cell with colored dot */
    .category-name-cell { display: flex; align-items: center; gap: 8px; }
    .category-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: #3b82f6;
        flex-shrink: 0;
    }

    /* Description truncation */
    .desc-cell {
        max-width: 220px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: #6b7280;
        font-size: 13px;
    }

    /* Action button hover fix */
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
    .action-btn.edit   { border-color: #3b82f6; color: #3b82f6; }
    .action-btn.edit:hover { background: #3b82f6; color: #fff; }
    .action-btn.delete { border-color: #ef4444; color: #ef4444; }
    .action-btn.delete:hover { background: #ef4444; color: #fff; }

    @media (max-width: 768px) {
        .states-table-header { flex-direction: column; gap: 10px; align-items: flex-start !important; }
        .ec-header-actions { width: 100%; }
        .ec-stats-bar { padding: 10px 10px 0; }
        .ec-stat-card { flex: 1; min-width: 120px; }
    }
</style>
@endsection
@section('main-content')

    @include('layout.expense-tabs')


    <div class="states-table bg-white rounded-lg shadow-md overflow-hidden">
        <div class="states-table-container">

            {{-- Header --}}
            <div class="states-table-header bg-blue-600 px-6 py-4 flex justify-between items-center">
                <h2 class="states-table-title text-white text-xl font-semibold">
                    <i class="fas fa-tags mr-2"></i>Expense Category List
                </h2>
                @can('create expense')
                <div class="ec-header-actions">
                    <button class="create-new-btn inline-flex items-center gap-2 bg-white text-blue-600 font-semibold px-4 py-2 rounded-lg shadow hover:bg-blue-50 transition duration-200 text-sm">
                        <i class="fas fa-plus"></i>Add Category
                    </button>
                </div>
                @endcan
            </div>

            {{-- Stats bar --}}
            @php
                // All three count the SAME set. They used to disagree: total came
                // from the paginator (everything) while active/inactive counted
                // only the current page, so 29 categories with none inactive read
                // "29 total · 20 active · 0 inactive" and did not add up.
                $total    = $datas->total();
                $active   = $categoryStats['active'];
                $inactive = $categoryStats['inactive'];
                $unmapped = $categoryStats['unmapped'];
            @endphp
            <div class="ec-stats-bar">
                <div class="ec-stat-card">
                    <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                    <div>
                        <div class="stat-label">Total</div>
                        <div class="stat-value">{{ $total }}</div>
                    </div>
                </div>
                <div class="ec-stat-card green">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <div class="stat-label">Active</div>
                        <div class="stat-value">{{ $active }}</div>
                    </div>
                </div>
                <div class="ec-stat-card yellow">
                    <div class="stat-icon"><i class="fas fa-pause-circle"></i></div>
                    <div>
                        <div class="stat-label">Inactive</div>
                        <div class="stat-value">{{ $inactive }}</div>
                    </div>
                </div>
                {{-- Unmapped is the number that costs money to ignore: those
                     categories post to the general expense account, so their
                     spending arrives in the ledger as one undifferentiated
                     bucket. Shown here so it is noticed now, not at year end. --}}
                <div class="ec-stat-card {{ $unmapped ? 'yellow' : 'green' }}">
                    <div class="stat-icon"><i class="fas fa-{{ $unmapped ? 'triangle-exclamation' : 'link' }}"></i></div>
                    <div>
                        <div class="stat-label">Not Mapped</div>
                        <div class="stat-value">{{ $unmapped }}</div>
                    </div>
                </div>
            </div>

            @if($unmapped)
                <div class="mx-4 mb-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs text-amber-800">
                    <i class="fas fa-triangle-exclamation mr-1"></i>
                    <strong>{{ $unmapped }}</strong> {{ Str::plural('category', $unmapped) }} {{ $unmapped === 1 ? 'has' : 'have' }} no expense account set,
                    so {{ $unmapped === 1 ? 'its' : 'their' }} expenses all post to
                    <strong>{{ $fallbackAccount ? '[' . $fallbackAccount->code . '] ' . $fallbackAccount->name : 'the general expense account' }}</strong>.
                    Edit each one and set <em>Posts To Account</em> to report spending by account properly.
                </div>
            @endif

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
                                <th style="padding-left: 20px" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Company</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Posts To</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                @canany(['edit expense', 'delete expense'])
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                @endcanany
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($datas as $key => $value)
                                <tr class="hover:bg-gray-50 transition-colors duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-500" style="padding-left: 20px">
                                        {{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="category-name-cell">
                                            <span class="category-dot"></span>
                                            <span class="font-medium text-gray-800 text-sm">{{ $value->name }}</span>
                                        </div>
                                    </td>
                                    {{-- No company means shared by the whole group, which is
                                         the normal case here — a bare dash would read as
                                         missing data and invite someone to "fix" it by
                                         pinning the category to one company. --}}
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                        @if ($value->company)
                                            {{ $value->company->name }}
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 text-xs font-medium">
                                                <i class="fas fa-globe text-[10px]"></i> All companies
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="desc-cell" title="{{ $value->description }}">
                                            {{ $value->description ?: '—' }}
                                        </span>
                                    </td>
                                    {{-- Unmapped is called out rather than left blank: it is not a
                                         missing detail, it is every expense in this category landing
                                         in the general bucket. --}}
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($value->account)
                                            <span class="text-sm font-medium text-gray-700">{{ $value->account->name }}</span>
                                            <p class="text-[11px] text-gray-400 font-mono leading-tight">{{ $value->account->code }}</p>
                                        @else
                                            <span class="inline-flex items-center gap-1 bg-amber-100 text-amber-700 text-xs font-semibold px-2.5 py-1 rounded-full"
                                                  title="Falls back to {{ $fallbackAccount?->code }} {{ $fallbackAccount?->name }}">
                                                <i class="fas fa-triangle-exclamation" style="font-size:9px"></i>Not mapped
                                            </span>
                                        @endif
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
                                                data-name="{{ $value->name }}"
                                                data-description="{{ $value->description }}"
                                                data-account_id="{{ $value->account_id }}"
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
                                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-tags text-2xl text-gray-400"></i>
                                        </div>
                                        <h4 class="text-gray-600 font-semibold text-lg">No categories found</h4>
                                        <p class="text-gray-400 text-sm">Try adjusting your filters or add a new category.</p>
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

    @include('expense-categories.create-modal')
    @include('expense-categories.edit-modal')
    @include('expense-categories.delete-modal')

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
                const item_id     = $(this).data('item_id');
                const company_id  = $(this).data('company_id');
                const name        = $(this).data('name');
                const description = $(this).data('description');
                const status      = $(this).data('status');

                $('#editItemId').val(item_id);
                $('#edit_company_id').val(company_id).trigger('change');
                $('#edit_name').val(name);
                $('#edit_description').val(description);
                $('#edit_account_id').val($(this).data('account_id') || '').trigger('change');
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
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Category created successfully!' });
                            $('#createModal').addClass('hidden');
                            $('#createForm')[0].reset();
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Something went wrong.' });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to create category.' });
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
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Category updated successfully!' });
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
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Category deleted successfully!' });
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
                window.location = '{{ route('role.expense-categories.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}';
            });
        });
    </script>
@endsection
