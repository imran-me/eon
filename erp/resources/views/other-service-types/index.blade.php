@extends('layout.app')
@section('meta-information')
    <title>Other Service Types</title>
@endsection
@section('css')
    <style>
        .swal2-container { z-index: 100000 !important; }
        span[aria-current="page"] span { background-color: #2563eb !important; color: white; border-color: #2563eb; }
    </style>
@endsection
@section('main-content')

{{-- Page header --}}
<header class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm mb-4">
    <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-violet-50 text-violet-600 flex-shrink-0"><i class="fas fa-tags"></i></span>
    <div class="flex-1">
        <h1 class="text-lg font-bold text-slate-900">Other Service Types</h1>
        <p class="text-xs text-slate-500">Manage the catalog of Other Visa Services types — icon, color, default fee</p>
    </div>
    <button type="button" class="create-new-btn rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700">
        <i class="fas fa-plus mr-1"></i> New Type
    </button>
</header>

{{-- Table --}}
<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">#</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Name</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Preview</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Default Fee (৳)</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Status</th>
                    <th class="px-4 py-3 text-center text-[10px] font-bold uppercase tracking-wide text-slate-500">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($datas as $key => $value)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-xs text-slate-400">{{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}</td>
                    <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ $value->name }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-bold"
                            style="background:{{ $value->bg_color ?? '#f1f5f9' }};color:{{ $value->color ?? '#64748b' }};">
                            <i class="fas {{ $value->icon ?? 'fa-circle-dot' }} text-[10px]"></i>
                            {{ $value->name }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm font-semibold text-violet-700">{{ $value->default_fee ? '৳ ' . number_format($value->default_fee, 2) : '—' }}</td>
                    <td class="px-4 py-3">
                        @if ($value->is_active)
                            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-semibold text-emerald-700">Active</span>
                        @else
                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-semibold text-amber-700">Inactive</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-center gap-1">
                            <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100 edit-btn"
                                data-item_id="{{ $value->id }}"
                                data-action="{{ route('role.other-service-types.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'other_service_type' => $value->id]) }}"
                                data-name="{{ $value->name }}"
                                data-icon="{{ $value->icon }}"
                                data-color="{{ $value->color }}"
                                data-bg_color="{{ $value->bg_color }}"
                                data-default_fee="{{ $value->default_fee }}"
                                data-is_active="{{ $value->is_active ? 1 : 0 }}"
                                title="Edit">
                                <i class="fas fa-edit text-xs"></i>
                            </button>
                            <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100"
                                onclick="confirmDelete('{{ $value->id }}', '{{ addslashes($value->name) }}')"
                                title="Delete">
                                <i class="fas fa-trash text-xs"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="p-12 text-center">
                        <i class="fas fa-inbox mb-3 block text-4xl text-slate-300"></i>
                        <p class="text-sm font-semibold text-slate-500">No service types found</p>
                        <p class="mt-1 text-xs text-slate-400">Add a new service type to get started.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4">{{ $datas->appends(request()->all())->links() }}</div>
</div>

@include('other-service-types.create-modal')
@include('other-service-types.edit-modal')
@include('other-service-types.delete-modal')

@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // Live preview while typing in create modal
            $(document).on('input change', '#create_name, #create_icon, #create_color, #create_bg_color', updateCreatePreview);
            $(document).on('input change', '#edit_name, #edit_icon, #edit_color, #edit_bg_color', updateEditPreview);
        });

        function updateCreatePreview() {
            const name = $('#create_name').val() || 'Preview';
            const icon = $('#create_icon').val() || 'fa-circle-dot';
            const color = $('#create_color').val() || '#64748b';
            const bg = $('#create_bg_color').val() || '#f1f5f9';
            $('#create_preview').html(`<i class="fas ${icon}"></i> ${name}`).css({ background: bg, color: color });
        }

        function updateEditPreview() {
            const name = $('#edit_name').val() || 'Preview';
            const icon = $('#edit_icon').val() || 'fa-circle-dot';
            const color = $('#edit_color').val() || '#64748b';
            const bg = $('#edit_bg_color').val() || '#f1f5f9';
            $('#edit_preview').html(`<i class="fas ${icon}"></i> ${name}`).css({ background: bg, color: color });
        }

        // Show create modal
        $('.create-new-btn').click(function() {
            $('#createForm')[0].reset();
            $('#create_is_active').prop('checked', true);
            updateCreatePreview();
            $('#createModal').removeClass('hidden');
        });

        // Show edit modal
        $('.edit-btn').click(function() {
            $('#editItemId').val($(this).data('item_id'));
            $('#editSubmit').data('action', $(this).data('action'));
            $('#edit_name').val($(this).data('name'));
            $('#edit_icon').val($(this).data('icon'));
            $('#edit_color').val($(this).data('color'));
            $('#edit_bg_color').val($(this).data('bg_color'));
            $('#edit_default_fee').val($(this).data('default_fee'));
            $('#edit_is_active').prop('checked', $(this).data('is_active') == 1);
            $('#editModal .error-message').addClass('hidden');
            updateEditPreview();
            $('#editModal').removeClass('hidden');
        });

        // Close modals
        $(document).on('click', '.modal-close-create, .modal-close-edit, .modal-close-delete', function() {
            $('#createModal, #editModal, #deleteModal').addClass('hidden');
        });
        $('#createModal, #editModal, #deleteModal').on('click', function(e) {
            if (e.target === this) $(this).addClass('hidden');
        });

        function validateCreateForm() {
            let isValid = true;
            $('#createForm .error-message').addClass('hidden');
            if (!$('#create_name').val().trim()) { $('#create_name_msg').removeClass('hidden'); isValid = false; }
            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            $('#editForm .error-message').addClass('hidden');
            if (!$('#edit_name').val().trim()) { $('#edit_name_msg').removeClass('hidden'); isValid = false; }
            return isValid;
        }

        // Create submit
        $('#createSubmit').click(function(e) {
            e.preventDefault();
            if (validateCreateForm()) {
                let formData = new FormData($('#createForm')[0]);
                formData.set('is_active', $('#create_is_active').is(':checked') ? 1 : 0);
                $.ajax({
                    url: $('#createForm').attr('action'),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Service type created successfully!' });
                            $('#createModal').addClass('hidden');
                            $('#createForm')[0].reset();
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Something went wrong.' });
                        }
                    },
                    error: function (xhr) {
                        console.error(xhr.responseText);
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to create service type.' });
                    }
                });
            }
        });

        // Edit submit
        $('#editSubmit').click(function() {
            if (validateEditForm()) {
                let formData = new FormData($('#editForm')[0]);
                formData.set('is_active', $('#edit_is_active').is(':checked') ? 1 : 0);
                $.ajax({
                    url: $(this).data('action'),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Service type updated successfully!' });
                            $('#editModal').addClass('hidden');
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Update failed.' });
                        }
                    },
                    error: function (xhr) {
                        console.error('Error:', xhr.responseText);
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong!' });
                    }
                });
            }
        });

        // Delete
        $('#confirmDeleteBtn').click(function() {
            const dataId = $(this).data('item-id');
            const deleteUrl = $(this).data('action');
            $.ajax({
                url: deleteUrl,
                method: 'DELETE',
                data: { item_id: dataId },
                success: function (response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Done', text: 'Service type deleted successfully!' });
                        $('#deleteModal').addClass('hidden');
                        setTimeout(() => window.location.reload(), 500);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Oops...', text: response.message });
                    }
                },
                error: function (xhr) {
                    console.error('Error:', xhr.responseText);
                    Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong!' });
                }
            });
        });

        function confirmDelete(id, name = null) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            const role = '{{ Str::slug(Auth::user()->getRoleNames()->first()) }}';
            $('#confirmDeleteBtn').data('action', `/${role}/other-service-types/${id}`);
            $('#deleteModal').removeClass('hidden');
        }
    </script>
@endsection
