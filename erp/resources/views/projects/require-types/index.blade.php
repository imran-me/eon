@extends('layout.app')
@section('meta-information')
    <title>Require Types</title>
@endsection
@section('css')
    @include('layout.table-design')
@endsection
@section('main-content')

<div class="states-table bg-white rounded-lg shadow-md overflow-hidden mt-0">
    <div class="states-table-container">

        {{-- Header --}}
        <div class="states-table-header bg-blue-600 px-6 py-4 flex justify-between items-center">
            <h2 class="states-table-title text-white text-xl font-semibold" style="color: white">
                <i class="fas fa-layer-group mr-2"></i>Require Types
            </h2>
            <button class="btn btn-primary create-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                <i class="fas fa-plus mr-2"></i>Add New Require Type
            </button>
        </div>

        <div class="states-table-content">

            {{-- Table --}}
            <div class="table-responsive overflow-x-auto" style="padding: 15px">
                <table class="table table-hover min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th style="width:5%" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sl</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Custom Fields</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($datas as $key => $item)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap" style="padding-left: 20px">
                                <strong>{{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}</strong>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                {{ $item->name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button class="manage-fields-btn border border-blue-400 text-blue-600 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md text-xs transition duration-200"
                                    data-id="{{ $item->id }}" data-name="{{ $item->name }}">
                                    <i class="fas fa-sliders mr-1"></i>Fields ({{ $item->fieldDefinitions->count() }})
                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($item->status)
                                    <span class="badge text-white bg-green-500 px-2 py-1 rounded-full text-xs">Active</span>
                                @else
                                    <span class="badge text-white bg-gray-400 px-2 py-1 rounded-full text-xs">Inactive</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="btn-group btn-group-sm flex space-x-1">
                                    <button class="btn btn-outline-primary edit-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200"
                                        data-id="{{ $item->id }}"
                                        data-name="{{ $item->name }}"
                                        data-status="{{ $item->status ? 1 : 0 }}"
                                        title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-danger delete-btn border border-red-500 text-red-500 hover:bg-red-500 hover:text-white px-3 py-1 rounded-md transition duration-200"
                                        data-id="{{ $item->id }}" data-name="{{ $item->name }}"
                                        title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-8">
                                <i class="fas fa-inbox fa-3x text-gray-400 mb-4"></i>
                                <h4 class="text-gray-500 text-xl font-medium mb-2">No data found</h4>
                                <p class="text-gray-400 mb-4">No require types added yet.</p>
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

{{-- ══ CREATE / EDIT MODAL ══ --}}
<div id="typeModal" class="modal fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-gray-900/60 backdrop-blur-sm"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 bg-gradient-to-r from-blue-600 to-indigo-600">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-layer-group text-white text-sm"></i>
                    </div>
                    <h3 id="typeModalTitle" class="text-white font-semibold text-base">Add Require Type</h3>
                </div>
                <button id="typeModalClose" class="w-7 h-7 bg-white/20 hover:bg-white/30 rounded-lg flex items-center justify-center transition-colors">
                    <i class="fas fa-times text-white text-xs"></i>
                </button>
            </div>
            <form id="typeForm" class="px-5 py-5 space-y-4">
                @csrf
                <input type="hidden" id="typeId">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Department Name <span class="text-red-500">*</span></label>
                    <input type="text" id="typeName" autocomplete="off"
                        class="form-input w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="e.g. Construction, IT, Finance…">
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="typeStatus" checked class="rounded">
                    <label for="typeStatus" class="text-sm text-gray-700">Active</label>
                </div>
                <div class="flex gap-2 pt-1">
                    <button type="button" id="typeModalCancelBtn"
                        class="flex-1 px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                        class="flex-1 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors flex items-center justify-center gap-2">
                        <i class="fas fa-check text-xs"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══ FIELDS MODAL ══ --}}
<div id="fieldsModal" class="modal fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-gray-900/60 backdrop-blur-sm"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white w-full max-w-2xl rounded-2xl shadow-2xl flex flex-col max-h-[90vh]">
            <div class="flex items-center justify-between px-5 py-4 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-t-2xl flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-sliders text-white text-sm"></i>
                    </div>
                    <div>
                        <p class="text-blue-200 text-xs">Custom Fields</p>
                        <h3 class="text-white font-semibold text-sm"><span id="fieldsModalTypeName"></span></h3>
                    </div>
                </div>
                <button id="fieldsModalClose" class="w-7 h-7 bg-white/20 hover:bg-white/30 rounded-lg flex items-center justify-center transition-colors">
                    <i class="fas fa-times text-white text-xs"></i>
                </button>
            </div>

            <div class="overflow-y-auto flex-1 p-5 space-y-4">
                {{-- Fields list --}}
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Defined Fields</p>
                    <div id="fieldsListContainer" class="space-y-2"></div>
                </div>

                {{-- Add field form --}}
                <div class="bg-gray-50 rounded-lg border border-gray-200 p-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Add New Field</p>
                    <form id="addFieldForm">
                        <input type="hidden" id="fieldProjectDeptId">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Label <span class="text-red-500">*</span></label>
                                <input type="text" id="fieldLabel" autocomplete="off"
                                    class="form-input w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="e.g. Site Address, Passport No…">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Field Type</label>
                                <select id="fieldType" class="form-select w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="text">Text</option>
                                    <option value="number">Number</option>
                                    <option value="date">Date</option>
                                    <option value="select">Dropdown (Select)</option>
                                    <option value="textarea">Textarea</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                                <input type="number" id="fieldSortOrder" value="0" min="0"
                                    class="form-input w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div id="fieldOptionsWrapper" class="sm:col-span-2 hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Options <span class="text-gray-400 font-normal text-xs">(comma separated)</span>
                                </label>
                                <input type="text" id="fieldOptions"
                                    class="form-input w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Option A, Option B, Option C">
                            </div>
                            <div class="sm:col-span-2 flex items-center gap-2">
                                <input type="checkbox" id="fieldRequired" class="rounded">
                                <label for="fieldRequired" class="text-sm text-gray-700">Required field</label>
                            </div>
                        </div>
                        <div class="mt-3 flex justify-end">
                            <button type="submit"
                                class="btn btn-primary bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm transition duration-200 flex items-center gap-2">
                                <i class="fas fa-plus text-xs"></i> Add Field
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function () {
    const csrfToken = '{{ csrf_token() }}';
    const baseUrl   = "{{ url(Str::slug(Auth::user()->getRoleNames()->first())) }}";

    // ── Open create modal ──
    $('.create-btn').click(function () {
        $('#typeModalTitle').text('Add Require Type');
        $('#typeId').val('');
        $('#typeName').val('');
        $('#typeStatus').prop('checked', true);
        $('#typeModal').removeClass('hidden');
    });

    // ── Open edit modal ──
    $(document).on('click', '.edit-btn', function () {
        $('#typeModalTitle').text('Edit Require Type');
        $('#typeId').val($(this).data('id'));
        $('#typeName').val($(this).data('name'));
        $('#typeStatus').prop('checked', $(this).data('status') == 1);
        $('#typeModal').removeClass('hidden');
    });

    // ── Close type modal ──
    $('#typeModalClose, #typeModalCancelBtn, #typeModal .modal-backdrop').click(function () {
        $('#typeModal').addClass('hidden');
    });

    // ── Save type ──
    $('#typeForm').submit(function (e) {
        e.preventDefault();
        const id     = $('#typeId').val();
        const url    = id ? `${baseUrl}/require-types/${id}` : `${baseUrl}/require-types`;
        const method = id ? 'PUT' : 'POST';
        const $btn   = $(this).find('[type=submit]');

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin text-xs mr-1"></i> Saving…');

        $.ajax({
            url, method,
            data: {
                _token: csrfToken,
                name:   $('#typeName').val(),
                status: $('#typeStatus').is(':checked') ? 1 : 0,
            },
            success(res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Saved!', text: res.message, timer: 1500, showConfirmButton: false });
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                    $btn.prop('disabled', false).html('<i class="fas fa-check text-xs"></i> Save');
                }
            },
            error() {
                Swal.fire({ icon: 'error', title: 'Server Error', text: 'Something went wrong.' });
                $btn.prop('disabled', false).html('<i class="fas fa-check text-xs"></i> Save');
            }
        });
    });

    // ── Delete type ──
    $(document).on('click', '.delete-btn', function () {
        const id   = $(this).data('id');
        const name = $(this).data('name');
        Swal.fire({
            title: `Delete "${name}"?`,
            text: 'This will also remove all its custom fields.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, delete',
        }).then(r => {
            if (!r.isConfirmed) return;
            $.ajax({
                url: `${baseUrl}/require-types/${id}`,
                method: 'DELETE',
                data: { _token: csrfToken },
                success(res) {
                    if (res.success) {
                        Swal.fire({ icon: 'success', title: 'Deleted', timer: 1200, showConfirmButton: false });
                        setTimeout(() => location.reload(), 1200);
                    }
                }
            });
        });
    });

    // ══ FIELDS MODAL ══

    function renderField(f) {
        const typeBadge = { text:'Text', number:'Number', date:'Date', select:'Dropdown', textarea:'Textarea' };
        return `
        <tr data-fid="${f.id}">
            <td class="px-4 py-2 text-sm font-medium text-gray-700">${f.label}</td>
            <td class="px-4 py-2">
                <span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded-md">${typeBadge[f.type] || f.type}</span>
            </td>
            <td class="px-4 py-2">
                ${f.required
                    ? '<span class="px-2 py-0.5 bg-red-50 text-red-500 text-xs rounded-md">Required</span>'
                    : '<span class="text-gray-400 text-xs">—</span>'}
            </td>
            <td class="px-4 py-2 text-xs text-gray-400 max-w-[120px] truncate">${f.options || '—'}</td>
            <td class="px-4 py-2 text-right">
                <button class="delete-field-btn btn btn-outline-danger border border-red-500 text-red-500 hover:bg-red-500 hover:text-white px-2 py-1 rounded-md text-xs transition duration-200" data-fid="${f.id}">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>`;
    }

    function loadFields(deptId) {
        $('#fieldsListContainer').html(`
            <table class="w-full text-sm"><tbody>
            <tr><td colspan="5" class="text-center py-4 text-gray-400 text-xs">
                <i class="fas fa-spinner fa-spin mr-1"></i> Loading…
            </td></tr></tbody></table>`);

        $.get(`${baseUrl}/require-types/${deptId}/fields`, function (res) {
            if (res.success && res.data.length) {
                const rows = res.data.map(f => renderField(f)).join('');
                $('#fieldsListContainer').html(`
                    <table class="w-full text-sm divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Label</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Required</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Options</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">${rows}</tbody>
                    </table>`);
            } else {
                $('#fieldsListContainer').html(`
                    <p class="text-center text-gray-400 text-sm py-4">
                        <i class="fas fa-inbox mr-1"></i> No custom fields yet.
                    </p>`);
            }
        });
    }

    $(document).on('click', '.manage-fields-btn', function () {
        const id   = $(this).data('id');
        const name = $(this).data('name');
        $('#fieldProjectDeptId').val(id);
        $('#fieldsModalTypeName').text(name);
        loadFields(id);
        $('#fieldsModal').removeClass('hidden');
    });

    $('#fieldsModalClose, #fieldsModal .modal-backdrop').click(function () {
        $('#fieldsModal').addClass('hidden');
    });

    $('#fieldType').on('change', function () {
        $(this).val() === 'select'
            ? $('#fieldOptionsWrapper').removeClass('hidden')
            : $('#fieldOptionsWrapper').addClass('hidden');
    });

    $('#addFieldForm').submit(function (e) {
        e.preventDefault();
        const deptId = $('#fieldProjectDeptId').val();
        const label  = $.trim($('#fieldLabel').val());
        if (!label) { $('#fieldLabel').focus(); return; }

        const $btn = $(this).find('[type=submit]');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin text-xs mr-1"></i> Adding…');

        $.ajax({
            url: `${baseUrl}/require-types/${deptId}/fields`,
            method: 'POST',
            data: {
                _token:     csrfToken,
                label:      label,
                type:       $('#fieldType').val(),
                options:    $('#fieldOptions').val(),
                required:   $('#fieldRequired').is(':checked') ? 1 : 0,
                sort_order: $('#fieldSortOrder').val(),
            },
            success(res) {
                $btn.prop('disabled', false).html('<i class="fas fa-plus text-xs"></i> Add Field');
                if (res.success) {
                    $('#fieldLabel').val('');
                    $('#fieldOptions').val('');
                    $('#fieldRequired').prop('checked', false);
                    $('#fieldSortOrder').val('0');
                    loadFields(deptId);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                }
            },
            error() {
                $btn.prop('disabled', false).html('<i class="fas fa-plus text-xs"></i> Add Field');
                Swal.fire({ icon: 'error', title: 'Server Error', text: 'Something went wrong.' });
            }
        });
    });

    $(document).on('click', '.delete-field-btn', function () {
        const fid    = $(this).data('fid');
        const deptId = $('#fieldProjectDeptId').val();
        Swal.fire({
            title: 'Delete this field?',
            text: 'Existing project data for this field will also be removed.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Delete',
        }).then(r => {
            if (!r.isConfirmed) return;
            $.ajax({
                url: `${baseUrl}/project-field-definitions/${fid}`,
                method: 'DELETE',
                data: { _token: csrfToken },
                success(res) { if (res.success) loadFields(deptId); }
            });
        });
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            $('#typeModal, #fieldsModal').addClass('hidden');
        }
    });
});
</script>
@endsection
