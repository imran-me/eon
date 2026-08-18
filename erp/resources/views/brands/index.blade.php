@extends('layout.app')

@section('meta-information')
    <title>Manage Brands | Admin Dashboard</title>
@endsection

@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@include('layout.table-design')
<style>
    /* Premium UI Enhancements */
    .select2-container--default .select2-selection--single {
        border-color: #e5e7eb !important;
        height: 42px !important;
        padding-top: 6px !important;
        border-radius: 0.5rem !important;
    }
    .custom-shadow {
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
    }
    .status-dot {
        height: 8px;
        width: 8px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 6px;
    }
</style>
@endsection

@section('main-content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Manage Brands</h1>
            <p class="text-sm text-gray-500 mt-1">View, filter, and organize your product brands effortlessly.</p>
        </div>
        @can('create brand')
        <button class="create-new-btn inline-flex items-center px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition-all duration-200 shadow-sm hover:shadow-md transform hover:-translate-y-0.5">
            <i class="fas fa-plus-circle mr-2"></i> Add New Brand
        </button>
        @endcan
    </div>

    <div class="bg-white rounded-xl border border-gray-200 custom-shadow overflow-hidden">
        
        <div class="bg-gray-50/50 border-b border-gray-100 p-6">
            <form action="" method="get" id="filterForm">
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-gray-600 uppercase tracking-wider">Status</label>
                        <select id="is_active" name="is_active" class="select2 w-full">
                            <option value="">All Statuses</option>
                            <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                    <div class="space-y-1 lg:col-span-2">
                        <label class="text-xs font-bold text-gray-600 uppercase tracking-wider">Brand Name</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" name="name" value="{{ request('name') }}" placeholder="Search by name..." 
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
                        </div>
                    </div>
                    <div class="flex items-end gap-3">
                        <button type="submit" class="flex-1 bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 font-semibold transition shadow-sm">
                            Apply
                        </button>
                        <button type="button" class="reset-btn p-2.5 text-gray-400 hover:text-red-500 transition-colors" title="Reset Filters">
                            <i class="fas fa-undo-alt"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left whitespace-nowrap">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest">SL</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest">Brand Details</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-center">Status</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($datas as $key => $value)
                    <tr class="hover:bg-indigo-50/30 transition-colors group">
                        <td class="px-6 py-4 text-sm font-semibold text-gray-400">
                            {{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-bold text-gray-800">{{ $value->name }}</div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if ($value->is_active)
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-green-50 text-green-700 border border-green-100">
                                    <span class="status-dot bg-green-500"></span> Active
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-amber-50 text-amber-700 border border-amber-100">
                                    <span class="status-dot bg-amber-500"></span> Inactive
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="inline-flex space-x-2">
                                @can('edit brand')
                                <button class="edit-item-btn p-2 text-indigo-500 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition"
                                    data-item_id="{{ $value->id }}" 
                                    data-name="{{ $value->name }}"
                                    data-is_active="{{ $value->is_active }}"
                                    title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                @endcan
                                @can('delete brand')
                                <button class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" 
                                    onclick="confirmDelete('{{ $value->id }}', '{{ $value->name }}')">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                                    <i class="fas fa-box-open text-gray-300 text-2xl"></i>
                                </div>
                                <h4 class="text-gray-900 font-bold text-lg">No brands found</h4>
                                <p class="text-gray-500 max-w-xs mx-auto">It looks like there aren't any brands matching your criteria yet.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
            {{ $datas->appends(request()->all())->links() }}
        </div>
    </div>
</div>

@include('brands.create-modal')
@include('brands.edit-modal')
@include('brands.delete-modal')

@endsection

@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('.select2').select2({
                minimumResultsForSearch: Infinity
            });

            $.ajaxSetup({
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
            });

            // Modals
            $('.create-new-btn').click(() => $('#createModal').removeClass('hidden'));

            $('.edit-item-btn').click(function() {
                $('#editItemId').val($(this).data('item_id'));
                $('#edit_name').val($(this).data('name'));
                $('#edit_is_active').prop('checked', $(this).data('is_active'));
                $('#editModal').removeClass('hidden');
            });

            // Universal Backdrop Close
            $('.modal-backdrop, [class*="modal-close"]').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('[class*="modal-close"]').length) {
                    $('.modal').addClass('hidden');
                }
            });

            // Form Submissions
            $('#createSubmit').click(function(e) {
                e.preventDefault();
                if (validateCreateForm()) {
                    submitAjax('#createForm', '#createModal', 'Data created successfully!');
                }
            });

            $('#editSubmit').click(function() {
                if (validateEditForm()) {
                    submitAjax('#editForm', '#editModal', 'Data updated successfully!', $(this).data('action'));
                }
            });

            function submitAjax(formId, modalId, successMsg, customUrl = null) {
                let formData = new FormData($(formId)[0]);
                $.ajax({
                    url: customUrl || $(formId).attr('action'),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (res) {
                        if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Success', text: successMsg, showConfirmButton: false, timer: 1500 });
                            $(modalId).addClass('hidden');
                            setTimeout(() => window.location.reload(), 1000);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops...', text: res.message });
                        }
                    },
                    error: () => Swal.fire({ icon: 'error', title: 'Error', text: 'Operation failed.' })
                });
            }

            // Delete logic
            $('#confirmDeleteBtn').click(function() {
                $.ajax({
                    url: $(this).data('action'),
                    method: 'DELETE',
                    data: { item_id: $(this).data('item-id') },
                    success: function (res) {
                        if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Deleted!', showConfirmButton: false, timer: 1000 });
                            setTimeout(() => window.location.reload(), 800);
                        }
                    }
                });
            });

            $('.reset-btn').click(function() {
                window.location = '{{ route('role.brands.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}';
            });
        });

        function confirmDelete(id, name) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            $('#deleteModal').removeClass('hidden');
        }

        function validateCreateForm() {
            const name = $('#create_name');
            if (!name.val().trim()) {
                name.addClass('border-red-500').next('.error-message').removeClass('hidden');
                return false;
            }
            return true;
        }

        function validateEditForm() {
            const name = $('#edit_name');
            if (!name.val().trim()) {
                name.addClass('border-red-500').next('.error-message').removeClass('hidden');
                return false;
            }
            return true;
        }
    </script>
@endsection