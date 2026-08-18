@extends('layout.app')
@section('meta-information')
    <title>Passport Categories</title>
@endsection
@section('main-content')

<div class="min-h-screen bg-gray-50 p-6">

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Passport Categories</h1>
            <p class="text-sm text-gray-500 mt-0.5">Organise holders by category type</p>
        </div>
        @can('create passport holder')
        <button type="button" onclick="showModal('#createModal')"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-sm">
            <i class="fas fa-plus text-xs"></i> Add Category
        </button>
        @endcan
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-layer-group text-blue-600 text-lg"></i>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Categories</p>
                <p class="text-2xl font-bold text-gray-800 mt-0.5">{{ $totalCategories }}</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-passport text-indigo-600 text-lg"></i>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Passport Holders</p>
                <p class="text-2xl font-bold text-gray-800 mt-0.5">{{ $totalHolders }}</p>
            </div>
        </div>
    </div>

    {{-- Main card --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">

        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center gap-3 p-4 border-b border-gray-100">
            <form method="GET" action="{{ route('role.passport-holder-category.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                  class="flex items-center gap-3 flex-1 flex-wrap">
                <div class="relative min-w-[260px]">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                        <i class="fas fa-search text-xs"></i>
                    </span>
                    <input type="text" name="name" value="{{ request('name') }}"
                           placeholder="Search category name..."
                           class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50">
                </div>
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-600 border border-gray-200 rounded-xl hover:bg-gray-50 transition">
                    <i class="fas fa-filter text-xs"></i> Filter
                </button>
                <a href="{{ route('role.passport-holder-category.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-500 border border-gray-200 rounded-xl hover:bg-gray-50 transition">
                    <i class="fas fa-times text-xs"></i> Reset
                </a>
            </form>
        </div>

        {{-- Flash messages --}}
        @if(session('success'))
            <div class="mx-4 mt-4 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl flex items-center gap-2">
                <i class="fas fa-check-circle text-green-500"></i> {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mx-4 mt-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl flex items-center gap-2">
                <i class="fas fa-exclamation-circle text-red-500"></i> {{ session('error') }}
            </div>
        @endif

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        <th class="px-5 py-3 text-left w-14">#</th>
                        <th class="px-5 py-3 text-left">Category Name</th>
                        <th class="px-5 py-3 text-left">Description</th>
                        <th class="px-5 py-3 text-center">Holders</th>
                        <th class="px-5 py-3 text-left">Created On</th>
                        <th class="px-5 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($datas as $key => $value)
                        <tr class="hover:bg-blue-50/30 transition-colors">

                            {{-- # --}}
                            <td class="px-5 py-4">
                                <span class="text-xs font-semibold text-gray-400">
                                    {{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}
                                </span>
                            </td>

                            {{-- Name --}}
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold flex-shrink-0 shadow-sm">
                                        {{ strtoupper(substr($value->name, 0, 1)) }}
                                    </div>
                                    <span class="font-semibold text-gray-800">{{ $value->name }}</span>
                                </div>
                            </td>

                            {{-- Description --}}
                            <td class="px-5 py-4 text-gray-500 text-xs max-w-xs">
                                {{ $value->description ? Str::limit($value->description, 50) : '—' }}
                            </td>

                            {{-- Holders count --}}
                            <td class="px-5 py-4 text-center">
                                <span class="inline-flex items-center justify-center min-w-[32px] px-2.5 py-1 text-xs font-semibold rounded-full
                                    {{ $value->passport_holders_count > 0 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-400' }}">
                                    {{ $value->passport_holders_count }}
                                </span>
                            </td>

                            {{-- Created --}}
                            <td class="px-5 py-4 text-gray-400 text-xs">
                                {{ $value->created_at->format('d M Y') }}
                            </td>

                            {{-- Actions --}}
                            <td class="px-5 py-4">
                                <div class="flex items-center justify-center gap-1.5">
                                    @can('edit passport holder')
                                    <button type="button"
                                            class="w-8 h-8 flex items-center justify-center rounded-lg text-indigo-500 bg-indigo-50 hover:bg-indigo-100 transition edit-btn"
                                            data-item_id="{{ $value->id }}"
                                            data-item_name="{{ $value->name }}"
                                            data-item_description="{{ $value->description }}"
                                            data-action="{{ route('role.passport-holder-category.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'passport_holder_category' => $value->id]) }}"
                                            title="Edit">
                                        <i class="fas fa-pencil-alt text-xs"></i>
                                    </button>
                                    @endcan
                                    @can('delete passport holder')
                                    <button type="button"
                                            class="w-8 h-8 flex items-center justify-center rounded-lg text-red-500 bg-red-50 hover:bg-red-100 transition"
                                            onclick="confirmDelete('{{ $value->id }}', '{{ addslashes($value->name) }}', {{ $value->passport_holders_count }})"
                                            title="Delete">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-16 text-center">
                                <div class="flex flex-col items-center gap-2 text-gray-400">
                                    <div class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-1">
                                        <i class="fas fa-layer-group text-2xl text-gray-300"></i>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-500">No categories found</p>
                                    <p class="text-xs text-gray-400">Add your first category to get started</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="flex items-center justify-between px-5 py-4 border-t border-gray-100">
            <p class="text-sm text-gray-500">
                Showing
                <span class="font-semibold text-gray-700">{{ $datas->firstItem() ?? 0 }}</span>–<span class="font-semibold text-gray-700">{{ $datas->lastItem() ?? 0 }}</span>
                of <span class="font-semibold text-gray-700">{{ $datas->total() }}</span> categories
            </p>
            <div>{{ $datas->withQueryString()->links() }}</div>
        </div>
    </div>
</div>

@include('passport-holder-category.create-modal')
@include('passport-holder-category.edit-modal')
@include('passport-holder-category.delete-modal')

@endsection
@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function () {
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

        // Edit modal – populate fields
        $(document).on('click', '.edit-btn', function () {
            $('#editItemId').val($(this).data('item_id'));
            $('#edit_name').val($(this).data('item_name'));
            $('#edit_description').val($(this).data('item_description'));
            showModal('#editModal');
        });

        // Close modals
        $(document).on('click', '.modal-close-create', function () { hideModal('#createModal'); });
        $(document).on('click', '.modal-close-edit',   function () { hideModal('#editModal'); });
        $(document).on('click', '.modal-close-delete', function () { hideModal('#deleteModal'); });
        $(document).on('click', '.modal-backdrop', function () {
            hideModal('#createModal'); hideModal('#editModal'); hideModal('#deleteModal');
        });

        // Create
        $('#createSubmit').on('click', function (e) {
            e.preventDefault();
            const name = $('#create_name').val().trim();
            if (!name) {
                $('#create_name').addClass('ring-2 ring-red-400 border-red-400');
                $('#create_name_error').removeClass('hidden');
                return;
            }
            $('#create_name').removeClass('ring-2 ring-red-400 border-red-400');
            $('#create_name_error').addClass('hidden');

            $.ajax({
                url: $('#createForm').attr('action'),
                method: 'POST',
                data: new FormData($('#createForm')[0]),
                processData: false,
                contentType: false,
                success: function (r) {
                    if (r.success) {
                        Swal.fire({ icon: 'success', title: 'Created!', text: 'Category added successfully.', timer: 1500, showConfirmButton: false });
                        hideModal('#createModal');
                        $('#createForm')[0].reset();
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: r.message || 'Something went wrong.' });
                    }
                },
                error: function () {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed. Please try again.' });
                }
            });
        });

        // Edit
        $('#editSubmit').on('click', function () {
            const name = $('#edit_name').val().trim();
            if (!name) {
                $('#edit_name').addClass('ring-2 ring-red-400 border-red-400');
                $('#edit_name_error').removeClass('hidden');
                return;
            }
            $('#edit_name').removeClass('ring-2 ring-red-400 border-red-400');
            $('#edit_name_error').addClass('hidden');

            $.ajax({
                url: $(this).data('action'),
                method: 'POST',
                data: new FormData($('#editForm')[0]),
                processData: false,
                contentType: false,
                success: function (r) {
                    if (r.success) {
                        Swal.fire({ icon: 'success', title: 'Updated!', text: 'Category updated successfully.', timer: 1500, showConfirmButton: false });
                        hideModal('#editModal');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: r.message || 'Update failed.' });
                    }
                },
                error: function () {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong!' });
                }
            });
        });

        // Delete
        $('#confirmDeleteBtn').on('click', function () {
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Deleting...');
            $.ajax({
                url: btn.data('action'),
                method: 'DELETE',
                data: { item_id: btn.data('item-id') },
                success: function (r) {
                    if (r.success) {
                        Swal.fire({ icon: 'success', title: 'Deleted!', text: 'Category removed.', timer: 1500, showConfirmButton: false });
                        hideModal('#deleteModal');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        btn.prop('disabled', false).html('<i class="fas fa-trash mr-1.5"></i> Delete');
                        Swal.fire({ icon: 'error', title: 'Error', text: r.message });
                    }
                },
                error: function () {
                    btn.prop('disabled', false).html('<i class="fas fa-trash mr-1.5"></i> Delete');
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong!' });
                }
            });
        });
    });

    function showModal(id) { $(id).removeClass('hidden').addClass('flex'); }
    function hideModal(id) { $(id).removeClass('flex').addClass('hidden'); }

    function confirmDelete(id, name, count) {
        $('#deleteCategoryName').text(name);
        $('#deleteCategoryCount').text(count);
        $('#deleteHolderWarning').toggleClass('hidden', count === 0);
        $('#confirmDeleteBtn').data('item-id', id);
        showModal('#deleteModal');
    }
</script>
@endsection
