{{--
    SUPERSEDED — no route reaches this file.

    Department, Category and Sub-category were merged onto one screen:
    resources/views/expense-classification/index.blade.php (+ modals.blade.php),
    served by ExpenseClassificationController. /{role}/expense-departments redirects there.

    Kept only so the old markup is recoverable from one place. Editing it changes
    nothing a user can see — change the classification page instead.
--}}
@extends('layout.app')
@section('meta-information')
    <title>Expense Departments</title>
@endsection
@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @include('layout.table-design')
@endsection
@section('main-content')

@php
    $role = Str::slug(Auth::user()->getRoleNames()->first());
    $canManage = auth()->user()->can('edit expense') || auth()->user()->can('delete expense');
    $cols = 6 + ($canManage ? 1 : 0);
@endphp

<div class="p-4 md:p-6 space-y-6">

    {{-- Inside the padded wrapper, like layout.payroll-tabs on the payroll pages,
         so the band lines up with the content below instead of running edge to edge. --}}
    @include('layout.expense-tabs')

    {{-- ── Header ── --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-xl font-extrabold text-gray-900 leading-tight">
                <i class="fas fa-sitemap text-blue-500 mr-1.5"></i>Expense Departments
            </h1>
            <p class="text-sm text-gray-500 mt-0.5">
                The units the expense desk budgets and reports against — each belongs to one company.
            </p>
        </div>
        @can('create expense')
            <button class="create-new-btn inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors shrink-0">
                <i class="fas fa-plus"></i> New Department
            </button>
        @endcan
    </div>

    {{-- A department is company-scoped on purpose, and that is what makes the
         expense form able to fill the company in the moment one is picked. --}}
    <div class="rounded-xl border border-blue-100 bg-blue-50/60 px-4 py-3 text-xs text-blue-800">
        <i class="fas fa-circle-info mr-1"></i>
        These are the expense desk's own departments, separate from the HR departments used on employee
        profiles. Because each belongs to one company, choosing a department on the expense form fills the
        company in for you.
    </div>

    <div class="states-table bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="states-table-container">
            <div class="px-6 py-4 flex flex-wrap justify-between items-center gap-3 border-b border-gray-100">
                <div>
                    <h2 class="text-gray-800 text-base font-bold">
                        <i class="fas fa-list mr-2 text-blue-500"></i>All Departments
                    </h2>
                    <p class="text-xs text-gray-400 mt-0.5">company · name · how many expenses use it</p>
                </div>
                <span class="text-xs text-gray-400">{{ $datas->total() }} {{ Str::plural('record', $datas->total()) }}</span>
            </div>

            <div class="states-table-content">
                <form action="" method="get">
                    <div class="filter-container">
                        <div class="filter-header {{ request()->hasAny(['company_id', 'search', 'status']) ? 'active' : '' }}">
                            <h3><i class="fas fa-filter mr-2"></i>Filter Options</h3>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="filter-content {{ request()->hasAny(['company_id', 'search', 'status']) ? 'active' : '' }}">
                            <div class="closest filter-row">
                                <div class="filter-group">
                                    <label for="search">Name</label>
                                    <input type="text" id="search" name="search" class="form-control"
                                           value="{{ request('search') }}" placeholder="e.g. Site" style="width:100%">
                                </div>
                                <div class="filter-group">
                                    <label for="company_id">Company</label>
                                    <select id="company_id" name="company_id" class="form-control select2" style="width:100%">
                                        <option value="">All</option>
                                        @foreach($companies as $company)
                                            <option value="{{ $company->id }}" {{ (string) $company->id === request('company_id') ? 'selected' : '' }}>
                                                {{ $company->short_name ?: $company->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="status">Status</label>
                                    <select id="status" name="status" class="form-control select2" style="width:100%">
                                        <option value="">All</option>
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

                <div class="table-responsive overflow-x-auto" style="padding: 15px">
                    <table class="table table-hover min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);">
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:5%">#</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:24%">
                                    <i class="fas fa-sitemap mr-1 text-indigo-400"></i>Department
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:18%">
                                    <i class="fas fa-building mr-1 text-cyan-500"></i>Company
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:26%">
                                    <i class="fas fa-note-sticky mr-1 text-gray-400"></i>Description
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:12%">
                                    <i class="fas fa-receipt mr-1 text-green-400"></i>Expenses
                                </th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:9%">
                                    <i class="fas fa-tag mr-1 text-yellow-400"></i>Status
                                </th>
                                @if($canManage)
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider" style="width:10%">
                                        <i class="fas fa-cogs mr-1 text-gray-400"></i>Actions
                                    </th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse($datas as $key => $value)
                                <tr class="hover:bg-blue-50 transition-colors duration-150">
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-100 text-gray-600 text-xs font-bold">
                                            {{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="text-sm font-semibold text-gray-800">{{ $value->name }}</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @include('payroll.partials.company-chip', ['company' => $value->company])
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $value->description ?: '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <span class="text-sm font-medium {{ $value->expenses_count ? 'text-gray-700' : 'text-gray-300' }}">
                                            {{ number_format($value->expenses_count) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        @if($value->status)
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200">
                                                <i class="fas fa-check-circle text-xs"></i> Active
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-500 border border-gray-200">
                                                <i class="fas fa-ban text-xs"></i> Inactive
                                            </span>
                                        @endif
                                    </td>
                                    @if($canManage)
                                        <td class="px-4 py-3 whitespace-nowrap text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                @can('edit expense')
                                                    <button class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-yellow-50 text-yellow-600 border border-yellow-200 hover:bg-yellow-500 hover:text-white transition-colors duration-150 edit-item-btn"
                                                        data-item_id="{{ $value->id }}"
                                                        data-company_id="{{ $value->company_id }}"
                                                        data-name="{{ $value->name }}"
                                                        data-description="{{ $value->description }}"
                                                        data-status="{{ (int) $value->status }}"
                                                        title="Edit this department">
                                                        <i class="fas fa-edit text-xs"></i>
                                                    </button>
                                                @endcan
                                                @can('delete expense')
                                                    <button type="button"
                                                        class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-red-50 text-red-600 border border-red-200 hover:bg-red-600 hover:text-white transition-colors duration-150"
                                                        onclick="confirmDelete('{{ $value->id }}', '{{ $value->name }}')"
                                                        title="{{ $value->expenses_count ? 'In use — set it inactive instead' : 'Delete this department' }}">
                                                        <i class="fas fa-trash text-xs"></i>
                                                    </button>
                                                @endcan
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $cols }}" class="text-center py-12">
                                        <div class="flex flex-col items-center gap-2">
                                            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center">
                                                <i class="fas fa-sitemap fa-2x text-gray-300"></i>
                                            </div>
                                            <h4 class="text-gray-500 text-base font-semibold mt-1">No expense departments yet</h4>
                                            <p class="text-gray-400 text-sm">Add the units you want to budget and report spending against.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                        @if($datas->total() > 0)
                            @php $page = $datas->getCollection(); @endphp
                            <tfoot class="border-t-2 border-gray-200">
                                <tr class="bg-gray-100 text-sm border-t border-gray-300">
                                    <td colspan="4" class="px-4 py-3.5 text-left font-extrabold text-gray-800">
                                        <i class="fas fa-calculator mr-1.5 text-blue-500"></i>{{ $page->count() }} {{ Str::plural('Department', $page->count()) }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-extrabold text-gray-800">{{ number_format($page->sum('expenses_count')) }}</td>
                                    <td colspan="{{ 1 + ($canManage ? 1 : 0) }}" class="px-4 py-3.5 text-center text-gray-500 text-xs">
                                        {{ $page->where('status', true)->count() }} active · {{ $page->where('status', false)->count() }} inactive
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                <div class="p-3 border-t border-gray-200">{{ $datas->links() }}</div>
            </div>
        </div>
    </div>
</div>

@include('expense-departments.create-modal')
@include('expense-departments.edit-modal')
@include('expense-departments.delete-modal')

@endsection

@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function () {
            $('.select2').select2();
            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

            $('.create-new-btn').click(() => $('#createModal').removeClass('hidden'));

            $('.edit-item-btn').click(function () {
                $('#editItemId').val($(this).data('item_id'));
                $('#edit_company_id').val($(this).data('company_id')).trigger('change');
                $('#edit_name').val($(this).data('name'));
                $('#edit_description').val($(this).data('description'));
                $('#edit_status').val($(this).data('status')).trigger('change');
                $('#editModal').removeClass('hidden');
            });

            $('.modal-close-create, .modal-close-edit, .modal-close-delete, .modal-backdrop').click(function (e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-create').length) $('#createModal').addClass('hidden');
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-edit').length) $('#editModal').addClass('hidden');
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-delete').length) $('#deleteModal').addClass('hidden');
            });

            function submit(form, url, method, modal) {
                $.ajax({
                    url: url, method: method, data: $(form).serialize(),
                    success: function (res) {
                        if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: res.message });
                            $(modal).addClass('hidden');
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops...', text: res.message || 'Something went wrong.' });
                        }
                    },
                    error: () => Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong.' })
                });
            }

            $('#createSubmit').click(function () {
                if (!$('#create_name').val().trim()) {
                    $('#create_name_msg').removeClass('hidden');
                    return;
                }
                submit('#createForm', $('#createForm').attr('action'), 'POST', '#createModal');
            });

            $('#editSubmit').click(function () {
                if (!$('#edit_name').val().trim()) {
                    $('#edit_name_msg').removeClass('hidden');
                    return;
                }
                submit('#editForm', $(this).data('action'), 'POST', '#editModal');
            });

            $('#confirmDeleteBtn').click(function () {
                $.ajax({
                    url: $(this).data('action'), method: 'DELETE',
                    data: { item_id: $(this).data('item-id') },
                    success: function (res) {
                        $('#deleteModal').addClass('hidden');
                        if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: res.message });
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            // In-use departments are refused with a reason, not a shrug.
                            Swal.fire({ icon: 'info', title: 'Cannot delete', text: res.message });
                        }
                    },
                    error: () => Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong.' })
                });
            });
        });

        function confirmDelete(id, name) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            $('#deleteModal').removeClass('hidden');
        }

        document.addEventListener('DOMContentLoaded', function () {
            const header = document.querySelector('.filter-container .filter-header');
            const content = document.querySelector('.filter-container .filter-content');
            if (header && content) {
                header.addEventListener('click', function () {
                    this.classList.toggle('active');
                    content.classList.toggle('active');
                });
            }
            const reset = document.querySelector('.filter-container .reset-btn');
            if (reset) {
                reset.addEventListener('click', function (e) {
                    e.preventDefault();
                    window.location = '{{ route('role.expense-departments.index', ['role' => $role]) }}';
                });
            }
        });
    </script>
@endsection
