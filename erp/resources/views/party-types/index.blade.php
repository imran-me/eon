@extends('layout.app')
@php $role = Str::slug(Auth::user()->getRoleNames()->first()); @endphp
@section('meta-information')
    <title>Party Types</title>
@endsection
@section('css')
@include('layout.table-design')
@endsection

@section('main-content')
<div class="states-table bg-white rounded-lg shadow-md overflow-hidden">
    <div class="states-table-container">

        {{-- Header --}}
        <div class="states-table-header bg-blue-600 px-6 py-4 flex justify-between items-center">
            <h2 class="states-table-title text-white text-xl font-semibold">
                <i class="fas fa-tags mr-2"></i>Party Types
            </h2>
            <button class="create-new-btn bg-white text-blue-600 font-semibold px-4 py-2 rounded-md hover:bg-blue-50 transition">
                <i class="fas fa-plus mr-1"></i>Add New
            </button>
        </div>

        <div class="states-table-content">

            @if(session('success'))
            <div class="mx-4 mt-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                {{ session('success') }}
            </div>
            @endif

            {{-- Filter --}}
            <form method="GET" class="p-4 flex flex-wrap gap-3 items-end">
                @if($isSuperAdmin)
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-gray-500 uppercase">Company</label>
                    <select name="company_id"
                            class="form-select px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 w-48"
                            onchange="this.form.submit()">
                        <option value="">— All Companies —</option>
                        @foreach($companies as $c)
                            <option value="{{ $c->id }}" {{ request('company_id') == $c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-gray-500 uppercase">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Search by name…"
                           class="form-input px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 w-56">
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md text-sm hover:bg-blue-600">Search</button>
                @if(request()->hasAny(['search', 'company_id']))
                <a href="{{ route('role.party-types.index', ['role' => $role]) }}"
                   class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm hover:bg-gray-300">Clear</a>
                @endif
            </form>

            {{-- Table --}}
            <div class="table-responsive overflow-x-auto px-4 pb-4">
                <table class="table table-hover min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                            @if($isSuperAdmin)
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                            @endif
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Slug</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Maps To</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($partyTypes as $key => $pt)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                {{ ($partyTypes->currentPage() - 1) * $partyTypes->perPage() + $key + 1 }}
                            </td>
                            @if($isSuperAdmin)
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $pt->company?->name ?? '—' }}</td>
                            @endif
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $pt->name }}</td>
                            <td class="px-4 py-3">
                                <code class="text-xs bg-gray-100 px-2 py-1 rounded">{{ $pt->slug }}</code>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if($pt->model_class)
                                    <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-1 rounded-full">
                                        <i class="fas fa-database text-xs"></i>
                                        {{ class_basename($pt->model_class) }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-500 text-xs font-semibold px-2 py-1 rounded-full">
                                        <i class="fas fa-pencil-alt text-xs"></i>
                                        Free text
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <button class="edit-item-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded text-xs transition"
                                            data-item_id="{{ $pt->id }}"
                                            data-name="{{ $pt->name }}"
                                            data-model_class="{{ $pt->model_class }}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="border border-red-500 text-red-500 hover:bg-red-500 hover:text-white px-3 py-1 rounded text-xs transition"
                                            onclick="confirmDelete('{{ $pt->id }}', '{{ addslashes($pt->name) }}')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-10 text-gray-400">
                                <i class="fas fa-tags fa-2x mb-2 block"></i>No party types found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-3 border-t border-gray-200">
                {{ $partyTypes->appends(request()->all())->links() }}
            </div>
        </div>
    </div>
</div>

@include('party-types.create-modal')
@include('party-types.edit-modal')
@include('party-types.delete-modal')
@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function () {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    // ── Open create ──
    $('.create-new-btn').click(() => $('#createModal').removeClass('hidden'));

    // ── Open edit ──
    $(document).on('click', '.edit-item-btn', function () {
        const id         = $(this).data('item_id');
        const name       = $(this).data('name');
        const modelClass = $(this).data('model_class') || '';

        $('#editItemId').val(id);
        $('#edit_name').val(name);
        $('#edit_model_class').val(modelClass);

        const base = "{{ route('role.party-types.update', ['role' => $role, 'party_type' => '__ID__']) }}".replace('__ID__', id);
        $('#editSubmit').data('action', base);
        $('#editForm').attr('action', base);
        $('#editModal').removeClass('hidden');
    });

    // ── Close modals ──
    $('.modal-close-create, .modal-close-edit, .modal-close-delete').click(function () {
        $(this).closest('.modal').addClass('hidden');
    });
    $(document).on('click', '.modal-backdrop', function () {
        $(this).closest('.modal').addClass('hidden');
    });

    // ── Create submit ──
    $('#createSubmit').click(function () {
        if (!$('#create_name').val().trim()) {
            $('#create_name').next('.error-message').removeClass('hidden');
            return;
        }
        $('#create_name').next('.error-message').addClass('hidden');

        $.ajax({
            url: $('#createForm').attr('action'),
            method: 'POST',
            data: new FormData($('#createForm')[0]),
            processData: false,
            contentType: false,
            success: function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Done', text: res.message, timer: 1200, showConfirmButton: false });
                    setTimeout(() => location.reload(), 1200);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                }
            },
            error: () => Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong.' })
        });
    });

    // ── Edit submit ──
    $('#editSubmit').click(function () {
        if (!$('#edit_name').val().trim()) {
            $('#edit_name').next('.error-message').removeClass('hidden');
            return;
        }
        $('#edit_name').next('.error-message').addClass('hidden');

        $.ajax({
            url: $(this).data('action'),
            method: 'POST',
            data: new FormData($('#editForm')[0]),
            processData: false,
            contentType: false,
            success: function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Done', text: res.message, timer: 1200, showConfirmButton: false });
                    setTimeout(() => location.reload(), 1200);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                }
            },
            error: () => Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong.' })
        });
    });

    // ── Delete confirm ──
    $('#confirmDeleteBtn').click(function () {
        const id  = $(this).data('item-id');
        const url = $(this).data('action');
        $.ajax({
            url,
            method: 'DELETE',
            data: { item_id: id },
            success: function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Deleted', text: res.message, timer: 1200, showConfirmButton: false });
                    setTimeout(() => location.reload(), 1200);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                }
            },
            error: () => Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong.' })
        });
    });
});

function confirmDelete(id, name) {
    $('#deleteName').text(name);
    const base = "{{ route('role.party-types.destroy', ['role' => $role, 'party_type' => '__ID__']) }}".replace('__ID__', id);
    $('#confirmDeleteBtn').data('item-id', id).data('action', base);
    $('#deleteModal').removeClass('hidden');
}
</script>
@endsection
