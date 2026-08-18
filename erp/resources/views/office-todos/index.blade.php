@extends('layout.app')
@section('meta-information')
    <title>Office Todos</title>
@endsection
@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @include('layout.table-design')
@endsection

@section('main-content')

<div class="states-table bg-white rounded-lg shadow-md overflow-hidden mt-0">
    <div class="states-table-container">

        {{-- Header --}}
        <div class="states-table-header bg-blue-600 px-6 py-4 flex justify-between items-center">
            <h2 class="states-table-title text-white text-xl font-semibold" style="color:white">
                <i class="fas fa-clipboard-check mr-2"></i>Office Todos
            </h2>
            @if(auth()->user()->hasRole('super admin') || auth()->user()->hasRole('admin'))
            <button id="openCreateModal" class="btn btn-primary bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                <i class="fas fa-plus mr-2"></i>Add New Todo
            </button>
            @endif
        </div>

        <div class="states-table-content">

            {{-- Filter --}}
            <form action="" method="get">
                <div class="filter-container">
                    <div class="filter-header" id="filterHeader">
                        <h3><i class="fas fa-filter mr-2"></i>Filter Options</h3>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="filter-content" id="filterContent">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label>Title</label>
                                <input type="text" name="title" value="{{ request('title') }}" placeholder="Search title...">
                            </div>
                            <div class="filter-group">
                                <label>Status</label>
                                <select name="status">
                                    <option value="">All Status</option>
                                    <option value="pending"     {{ request('status') == 'pending'     ? 'selected' : '' }}>Pending</option>
                                    <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                    <option value="completed"   {{ request('status') == 'completed'   ? 'selected' : '' }}>Completed</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Priority</label>
                                <select name="priority">
                                    <option value="">All Priority</option>
                                    <option value="low"    {{ request('priority') == 'low'    ? 'selected' : '' }}>Low</option>
                                    <option value="medium" {{ request('priority') == 'medium' ? 'selected' : '' }}>Medium</option>
                                    <option value="high"   {{ request('priority') == 'high'   ? 'selected' : '' }}>High</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Department</label>
                                <select name="department_id">
                                    <option value="">All Departments</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>
                                            {{ $dept->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @if(auth()->user()->hasRole('super admin') || auth()->user()->hasRole('admin'))
                            <div class="filter-group">
                                <label>Type</label>
                                <select name="is_self">
                                    <option value="">All Types</option>
                                    <option value="1" {{ request('is_self') === '1' ? 'selected' : '' }}>My Own</option>
                                    <option value="0" {{ request('is_self') === '0' ? 'selected' : '' }}>Assigned</option>
                                </select>
                            </div>
                            @endif
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="apply-btn"><i class="fas fa-search mr-1"></i>Search</button>
                            <a href="{{ url()->current() }}" class="reset-btn" style="padding:10px 20px;border-radius:6px;font-weight:500;background:#f8f9fa;color:#6b7280;border:1px solid #d1d5db;text-decoration:none;">
                                <i class="fas fa-times mr-1"></i>Reset
                            </a>
                        </div>
                    </div>
                </div>
            </form>

            {{-- Table --}}
            <div class="overflow-x-auto" style="padding:15px;">
                <table class="table w-full min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Priority</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Assignees</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                {{ (auth()->user()->hasRole('super admin') || auth()->user()->hasRole('admin')) ? 'Overall Status' : 'My Status' }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($datas as $index => $todo)
                        <tr>
                            <td class="px-4 py-3">{{ $datas->firstItem() + $index }}</td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-800">{{ $todo->title }}</div>
                                @if($todo->description)
                                <div class="text-gray-500 text-xs mt-1">{{ Str::limit($todo->description, 60) }}</div>
                                @endif
                                @if($todo->checklists_total > 0)
                                @php
                                    $pct = (int) round($todo->checklists_checked / $todo->checklists_total * 100);
                                @endphp
                                <button class="view-checklist-btn mt-1 inline-flex items-center gap-2 text-xs font-medium px-2 py-0.5 rounded-full"
                                    style="background:#eff6ff;color:#2563eb;border:none;cursor:pointer;"
                                    data-id="{{ $todo->id }}"
                                    data-title="{{ $todo->title }}">
                                    <i class="fas fa-list-check"></i>
                                    <span class="cl-count">{{ $todo->checklists_checked }}/{{ $todo->checklists_total }} done</span>
                                    <span style="display:inline-block;width:46px;height:5px;background:#dbeafe;border-radius:999px;overflow:hidden;vertical-align:middle;">
                                        <span class="cl-bar" style="display:block;height:100%;width:{{ $pct }}%;background:{{ $pct === 100 ? '#16a34a' : '#2563eb' }};"></span>
                                    </span>
                                    <span class="cl-pct" style="font-weight:700;">{{ $pct }}%</span>
                                </button>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ optional($todo->department)->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $pClass = match($todo->priority) {
                                        'high'  => 'background:#fee2e2;color:#dc2626;',
                                        'low'   => 'background:#dcfce7;color:#16a34a;',
                                        default => 'background:#fef9c3;color:#ca8a04;',
                                    };
                                @endphp
                                <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;{{ $pClass }}">
                                    {{ ucfirst($todo->priority) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if($todo->due_date)
                                    @php $isOverdue = \Carbon\Carbon::parse($todo->due_date)->isPast() && $todo->status !== 'completed'; @endphp
                                    <span style="{{ $isOverdue ? 'color:#dc2626;font-weight:700;' : 'color:#374151;' }}">
                                        {{ \Carbon\Carbon::parse($todo->due_date)->format('d M Y') }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($todo->is_self)
                                    <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;background:#ede9fe;color:#7c3aed;">Own</span>
                                @else
                                    <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;background:#dbeafe;color:#1d4ed8;">Assigned</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($todo->is_self)
                                    <span class="text-gray-400 text-xs">—</span>
                                @else
                                    <div style="display:flex;gap:-4px;">
                                        @foreach($todo->assignees->take(4) as $assignee)
                                            <img src="{{ $assignee->image ? asset($assignee->image) : 'https://ui-avatars.com/api/?name='.urlencode($assignee->name).'&size=28&background=4f46e5&color=fff' }}"
                                                 style="width:28px;height:28px;border-radius:50%;border:2px solid white;object-fit:cover;margin-right:-6px;"
                                                 title="{{ $assignee->name }}">
                                        @endforeach
                                        @if($todo->assignees->count() > 4)
                                            <span style="width:28px;height:28px;border-radius:50%;background:#e5e7eb;color:#374151;font-size:10px;display:inline-flex;align-items:center;justify-content:center;border:2px solid white;font-weight:700;">
                                                +{{ $todo->assignees->count() - 4 }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $isAdmin = auth()->user()->hasRole('super admin') || auth()->user()->hasRole('admin');
                                    $status = $isAdmin ? $todo->status : ($todo->my_status ?? $todo->status);
                                    $stStyle = match($status) {
                                        'completed'   => 'background:#dcfce7;color:#16a34a;',
                                        'in_progress' => 'background:#dbeafe;color:#1d4ed8;',
                                        default       => 'background:#f3f4f6;color:#6b7280;',
                                    };
                                    $stLabel = match($status) {
                                        'in_progress' => 'In Progress',
                                        default       => ucfirst($status),
                                    };
                                @endphp
                                <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;{{ $stStyle }}">
                                    {{ $stLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div style="display:flex;gap:8px;align-items:center;">
                                    @if($isAdmin)
                                        <button class="edit-todo-btn" data-id="{{ $todo->id }}"
                                            style="color:#2563eb;background:none;border:none;cursor:pointer;font-size:14px;" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="delete-todo-btn" data-id="{{ $todo->id }}"
                                            style="color:#dc2626;background:none;border:none;cursor:pointer;font-size:14px;" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    @else
                                        <button class="update-status-btn"
                                            data-id="{{ $todo->id }}"
                                            data-current="{{ $todo->my_status ?? 'pending' }}"
                                            data-note="{{ $todo->my_note ?? '' }}"
                                            style="color:#7c3aed;background:none;border:none;cursor:pointer;font-size:13px;font-weight:600;" title="Update My Status">
                                            <i class="fas fa-check-circle"></i> Update
                                        </button>
                                    @endif
                                    @if($todo->attachment)
                                        <a href="{{ asset($todo->attachment) }}" target="_blank"
                                            style="color:#6b7280;font-size:14px;" title="Attachment">
                                            <i class="fas fa-paperclip"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-8 text-gray-400">
                                <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.4;"></i>
                                No todos found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3">
                {{ $datas->withQueryString()->links() }}
            </div>
        </div>
    </div>
</div>

@include('office-todos.create-modal')
@include('office-todos.edit-modal')
@include('office-todos.delete-modal')
@include('office-todos.status-modal')
@include('office-todos.checklist-modal')

@endsection

{{-- ── Scripts ── --}}
@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<style>
    .cl-ghost { opacity: .45; background: #eff6ff !important; }
    .cl-handle:active { cursor: grabbing !important; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Filter toggle ─────────────────────────────────────────────────────────
    const filterHeader  = document.getElementById('filterHeader');
    const filterContent = document.getElementById('filterContent');
    if (filterHeader && filterContent) {
        // Open automatically if any filter is active
        const params = new URLSearchParams(window.location.search);
        const hasFilter = ['title','status','priority','department_id','is_self'].some(k => params.get(k));
        if (hasFilter) {
            filterHeader.classList.add('active');
            filterContent.classList.add('active');
        }
        filterHeader.addEventListener('click', function () {
            this.classList.toggle('active');
            filterContent.classList.toggle('active');
        });
    }

    // ── Select2 init ──────────────────────────────────────────────────────────
    $('.select2-filter').select2({ width: '100%' });

    // ── Create Modal ──────────────────────────────────────────────────────────
    $('#openCreateModal').on('click', function () {
        $('#createForm')[0].reset();
        $('#create_assigned_to').val(null).trigger('change');
        $('#create_assignee_block').show();
        $('#createModal').removeClass('hidden');
    });

    $('#closeCreateModal, #cancelCreate').on('click', function () {
        $('#createModal').addClass('hidden');
    });

    // Close on backdrop click
    $('#createModal').on('click', function (e) {
        if ($(e.target).is('#createModal')) $(this).addClass('hidden');
    });

    $('#createForm').on('submit', function (e) {
        e.preventDefault();
        var btn = $(this).find('[type=submit]');
        btn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: '{{ route("role.office-todos.store", Str::slug(auth()->user()->getRoleNames()->first())) }}',
            method: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false,
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            success: function (res) {
                if (res.success) {
                    $('#createModal').addClass('hidden');
                    location.reload();
                } else {
                    alert(res.message || 'Failed to create todo.');
                }
            },
            error: function (xhr) {
                alert(xhr.responseJSON?.message || 'An error occurred.');
            },
            complete: function () {
                btn.prop('disabled', false).text('Save');
            }
        });
    });

    // ── Edit Modal ────────────────────────────────────────────────────────────
    $(document).on('click', '.edit-todo-btn', function () {
        var id = $(this).data('id');
        $.get('{{ url(Str::slug(auth()->user()->getRoleNames()->first()) . "/office-todos") }}/' + id + '/edit', function (res) {
            if (!res.success) { alert('Failed to load data.'); return; }
            var d = res.data;
            $('#edit_id').val(d.id);
            $('#edit_title').val(d.title);
            $('#edit_description').val(d.description);
            $('#edit_department_id').val(d.department_id).trigger('change');
            $('#edit_start_date').val(d.start_date ? d.start_date.substring(0,10) : '');
            $('#edit_due_date').val(d.due_date   ? d.due_date.substring(0,10)   : '');
            $('#edit_priority').val(d.priority).trigger('change');
            $('#edit_status').val(d.status).trigger('change');
            if (d.is_self) {
                $('#edit_is_self').prop('checked', true);
                $('#edit_assignee_block').hide();
            } else {
                $('#edit_is_self').prop('checked', false);
                $('#edit_assignee_block').show();
            }
            var ids = d.assignees ? d.assignees.map(function(a){ return a.id; }) : [];
            $('#edit_assigned_to').val(ids).trigger('change');
            $('#editModal').removeClass('hidden');
        });
    });

    $('#closeEditModal, #cancelEdit').on('click', function () {
        $('#editModal').addClass('hidden');
    });
    $('#editModal').on('click', function (e) {
        if ($(e.target).is('#editModal')) $(this).addClass('hidden');
    });

    $('#editForm').on('submit', function (e) {
        e.preventDefault();
        var id  = $('#edit_id').val();
        var btn = $(this).find('[type=submit]');
        btn.prop('disabled', true).text('Updating...');
        var fd  = new FormData(this);
        fd.append('_method', 'PUT');

        $.ajax({
            url: '{{ url(Str::slug(auth()->user()->getRoleNames()->first()) . "/office-todos") }}/' + id,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            success: function (res) {
                if (res.success) { $('#editModal').addClass('hidden'); location.reload(); }
                else { alert(res.message || 'Failed to update.'); }
            },
            error: function (xhr) { alert(xhr.responseJSON?.message || 'An error occurred.'); },
            complete: function () { btn.prop('disabled', false).text('Update'); }
        });
    });

    // ── Delete ────────────────────────────────────────────────────────────────
    $(document).on('click', '.delete-todo-btn', function () {
        $('#delete_item_id').val($(this).data('id'));
        $('#deleteModal').removeClass('hidden');
    });
    $('#closeDeleteModal, #cancelDelete').on('click', function () {
        $('#deleteModal').addClass('hidden');
    });
    $('#deleteModal').on('click', function (e) {
        if ($(e.target).is('#deleteModal')) $(this).addClass('hidden');
    });

    $('#deleteForm').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: '{{ route("role.office-todos.destroy", Str::slug(auth()->user()->getRoleNames()->first())) }}',
            method: 'DELETE',
            data: { item_id: $('#delete_item_id').val(), _token: '{{ csrf_token() }}' },
            success: function (res) {
                if (res.success) { location.reload(); }
                else { alert(res.message || 'Failed.'); }
            }
        });
    });

    // ── Employee: Update My Status ────────────────────────────────────────────
    $(document).on('click', '.update-status-btn', function () {
        $('#status_todo_id').val($(this).data('id'));
        $('#status_my_status').val($(this).data('current')).trigger('change');
        $('#status_my_note').val($(this).data('note') || '');
        $('#statusModal').removeClass('hidden');
    });
    $('#closeStatusModal, #cancelStatus').on('click', function () {
        $('#statusModal').addClass('hidden');
    });
    $('#statusModal').on('click', function (e) {
        if ($(e.target).is('#statusModal')) $(this).addClass('hidden');
    });

    $('#statusForm').on('submit', function (e) {
        e.preventDefault();
        var id  = $('#status_todo_id').val();
        var btn = $(this).find('[type=submit]');
        btn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: '{{ url(Str::slug(auth()->user()->getRoleNames()->first()) . "/office-todos") }}/' + id + '/my-status',
            method: 'POST',
            data: {
                _token:  '{{ csrf_token() }}',
                _method: 'PATCH',
                status:  $('#status_my_status').val(),
                note:    $('#status_my_note').val(),
            },
            success: function (res) {
                if (res.success) { $('#statusModal').addClass('hidden'); location.reload(); }
                else { alert(res.message || 'Failed.'); }
            },
            error: function (xhr) { alert(xhr.responseJSON?.message || 'Error.'); },
            complete: function () { btn.prop('disabled', false).text('Save'); }
        });
    });

    // ── Select2 for modals (init after modal elements exist) ─────────────────
    $('#create_assigned_to').select2({ width: '100%', dropdownParent: $('#createModal') });
    $('#create_department_id, #create_priority').select2({ width: '100%', dropdownParent: $('#createModal') });
    $('#edit_assigned_to').select2({ width: '100%', dropdownParent: $('#editModal') });
    $('#edit_department_id, #edit_priority, #edit_status').select2({ width: '100%', dropdownParent: $('#editModal') });
    $('#status_my_status').select2({ width: '100%', dropdownParent: $('#statusModal') });

    // ── is_self toggle ────────────────────────────────────────────────────────
    $('#create_is_self').on('change', function () {
        if ($(this).is(':checked')) {
            $('#create_assignee_block').hide();
            $('#create_assigned_to').val(null).trigger('change');
        } else {
            $('#create_assignee_block').show();
        }
    });
    $('#edit_is_self').on('change', function () {
        if ($(this).is(':checked')) {
            $('#edit_assignee_block').hide();
            $('#edit_assigned_to').val(null).trigger('change');
        } else {
            $('#edit_assignee_block').show();
        }
    });

    // ── Checklist builder helpers ─────────────────────────────────────────────
    function escAttr(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Each row keeps its own index so the id travels back with the title —
    // that's what lets the server update rows in place instead of wiping ticks.
    var checklistRowSeq = 0;

    // Every row carries a client-side key, and sub-items carry their parent's,
    // so a newly added child can be linked to a parent that has no database id
    // yet. Existing children must send it too, or the save would flatten them.
    function makeChecklistRow(item, prefix, parentKey) {
        var data  = (typeof item === 'string') ? { title: item } : (item || {});
        var idx   = checklistRowSeq++;
        var base  = 'checklists[' + idx + ']';
        var key   = 'k' + idx;
        var isSub = !!parentKey;
        var pr    = data.priority || 'medium';
        var day   = function (v) { return v ? String(v).substr(0, 10) : ''; };
        var opt   = function (v, label) {
            return '<option value="' + v + '"' + (pr === v ? ' selected' : '') + '>' + label + '</option>';
        };

        var $row = $(
            '<div class="checklist-row bg-gray-50 border border-gray-200 rounded-md px-3 py-2 space-y-2"' +
                ' data-key="' + key + '"' + (isSub ? ' data-parent-key="' + escAttr(parentKey) + '"' : '') +
                ' style="margin-bottom:6px;' + (isSub ? 'border-left:3px solid #bfdbfe;' : '') + '">' +
                '<div class="flex items-center gap-2">' +
                    '<i class="fas fa-grip-vertical text-gray-300 text-xs cb-handle" style="cursor:grab;"></i>' +
                    '<span class="cb-serial" style="font-size:11px;font-weight:700;color:#94a3b8;min-width:26px;"></span>' +
                    '<input type="hidden" name="' + base + '[key]" value="' + key + '">' +
                    (isSub ? '<input type="hidden" name="' + base + '[parent_key]" value="' + escAttr(parentKey) + '">' : '') +
                    (data.id ? '<input type="hidden" name="' + base + '[id]" value="' + escAttr(data.id) + '">' : '') +
                    '<input type="text" name="' + base + '[title]" value="' + escAttr(data.title) + '" ' +
                        'placeholder="' + (isSub ? 'Sub-item…' : 'Checklist item…') + '" ' +
                        'class="flex-1 border border-gray-200 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400">' +
                    (data.is_checked ? '<span class="text-xs font-semibold text-green-600 whitespace-nowrap"><i class="fas fa-check-circle"></i> done</span>' : '') +
                    (isSub ? '' : '<button type="button" class="add-sub-checklist text-blue-500 hover:text-blue-700 text-xs whitespace-nowrap" title="Add sub-item"><i class="fas fa-plus"></i> Sub</button>') +
                    '<button type="button" class="remove-checklist-item text-red-400 hover:text-red-600 text-xs"><i class="fas fa-times"></i></button>' +
                '</div>' +
                '<div class="flex flex-wrap items-center gap-2 pl-5">' +
                    '<select name="' + base + '[priority]" class="border border-gray-200 rounded px-2 py-1 text-xs bg-white">' +
                        opt('low', 'Low') + opt('medium', 'Medium') + opt('high', 'High') +
                    '</select>' +
                    '<input type="date" name="' + base + '[start_date]" value="' + day(data.start_date) + '" ' +
                        'class="border border-gray-200 rounded px-2 py-1 text-xs" title="Start date">' +
                    '<input type="date" name="' + base + '[end_date]" value="' + day(data.end_date) + '" ' +
                        'class="border border-gray-200 rounded px-2 py-1 text-xs" title="End date">' +
                '</div>' +
                // Sub-items nest inside their parent, so the form still submits a
                // parent ahead of its own children (document order) while a drag
                // moves the whole block together.
                (isSub ? '' : '<div class="cb-children" data-parent-key="' + key + '" style="margin-left:22px;margin-top:6px;"></div>') +
            '</div>'
        );

        return $row.data('key', key);
    }

    $(document).on('click', '.add-sub-checklist', function () {
        var $parent = $(this).closest('.checklist-row');
        var $row    = makeChecklistRow({ title: '' }, '', $parent.attr('data-key'));

        $parent.children('.cb-children').append($row);
        cbInitSortable();
        cbRenumber();
        $row.find('input[name$="[title]"]').focus();
    });

    /**
     * Numbering mirrors the checklist modal: 1, 2, 3 at the top level and
     * 1.1, 1.2 beneath each parent, recomputed from the DOM after every drop.
     */
    function cbRenumber() {
        $('#create_checklist_list, #edit_checklist_list').each(function () {
            $(this).children('.checklist-row').each(function (i) {
                var top = i + 1;
                $(this).children('.flex').children('.cb-serial').text(top + '.');
                $(this).children('.cb-children').children('.checklist-row').each(function (j) {
                    $(this).children('.flex').children('.cb-serial').text(top + '.' + (j + 1));
                });
            });
        });
    }

    function cbInitSortable() {
        if (typeof Sortable === 'undefined') return;

        var opts = {
            handle: '.cb-handle',
            animation: 150,
            ghostClass: 'cl-ghost',
            fallbackOnBody: true,
            onEnd: cbRenumber
        };

        // Marked so re-running this after adding a row doesn't stack instances.
        $('#create_checklist_list, #edit_checklist_list, .cb-children').each(function () {
            if (this.dataset.sortableReady) return;
            this.dataset.sortableReady = '1';

            var isTop = this.id === 'create_checklist_list' || this.id === 'edit_checklist_list';
            Sortable.create(this, $.extend({
                group: isTop ? 'cb-top-' + this.id : 'cb-kids-' + $(this).data('parent-key')
            }, opts));
        });
    }

    // Create modal checklist
    $('#create_add_checklist_btn').on('click', function () {
        var val = $('#create_checklist_input').val().trim();
        if (!val) return;
        $('#create_checklist_list').append(makeChecklistRow(val, 'create'));
        cbInitSortable();
        cbRenumber();
        $('#create_checklist_input').val('').focus();
    });
    $('#create_checklist_input').on('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); $('#create_add_checklist_btn').trigger('click'); }
    });

    // Edit modal checklist
    $('#edit_add_checklist_btn').on('click', function () {
        var val = $('#edit_checklist_input').val().trim();
        if (!val) return;
        $('#edit_checklist_list').append(makeChecklistRow(val, 'edit'));
        cbInitSortable();
        cbRenumber();
        $('#edit_checklist_input').val('').focus();
    });
    $('#edit_checklist_input').on('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); $('#edit_add_checklist_btn').trigger('click'); }
    });

    // Removing a parent takes its sub-items with it — they're nested inside it,
    // so there's nothing left behind to orphan.
    $(document).on('click', '.remove-checklist-item', function () {
        $(this).closest('.checklist-row').remove();
        cbRenumber();
    });

    // Reset create modal checklist on open
    $('#openCreateModal').on('click.checklist', function () {
        $('#create_checklist_list').empty();
        $('#create_checklist_input').val('');
    });

    // Populate edit modal checklist from loaded data
    $(document).on('checklist:load', function (e, checklists) {
        $('#edit_checklist_list').empty();
        $('#edit_checklist_input').val('');
        if (!checklists || !checklists.length) return;

        // Rebuild the tree so each parent is followed straight away by its own
        // sub-items, which is the order syncChecklists() relies on.
        var byId = {}, roots = [];
        checklists.forEach(function (it) { byId[it.id] = { item: it, children: [] }; });
        checklists.forEach(function (it) {
            if (it.parent_id && byId[it.parent_id]) byId[it.parent_id].children.push(byId[it.id]);
            else roots.push(byId[it.id]);
        });

        roots.forEach(function (node) {
            var $row = makeChecklistRow(node.item, 'edit', '');
            $('#edit_checklist_list').append($row);
            var pKey    = $row.attr('data-key');
            var $kids   = $row.children('.cb-children');
            node.children.forEach(function (child) {
                $kids.append(makeChecklistRow(child.item, 'edit', pKey));
            });
        });

        cbInitSortable();
        cbRenumber();
    });

    // Hook into edit data load to populate checklists
    var _origEditClick = $(document).data('editClickBound');
    $(document).on('click.checklist', '.edit-todo-btn', function () {
        var id = $(this).data('id');
        $.get('{{ url(Str::slug(auth()->user()->getRoleNames()->first()) . "/office-todos") }}/' + id + '/edit', function (res) {
            if (res.success && res.data.checklists) {
                $(document).trigger('checklist:load', [res.data.checklists]);
            }
        });
    });

    // ── Checklist view modal ──────────────────────────────────────────────────
    var checklistBaseUrl = '{{ url(Str::slug(auth()->user()->getRoleNames()->first()) . "/office-todos") }}';

    var CL_PRIORITY_STYLE = {
        high:   'background:#fee2e2;color:#dc2626;',
        low:    'background:#dcfce7;color:#16a34a;',
        medium: 'background:#fef9c3;color:#ca8a04;'
    };

    var CL_STATUS_STYLE = {
        completed:   'background:#dcfce7;color:#16a34a;',
        in_progress: 'background:#dbeafe;color:#1d4ed8;',
        pending:     'background:#f3f4f6;color:#6b7280;'
    };
    var CL_STATUS_LABEL = { pending: 'Pending', in_progress: 'In Progress', completed: 'Completed' };

    function clStatusOf(item) {
        return item.is_checked ? 'completed' : (item.status || 'pending');
    }

    // Sub-items are what actually get worked on, so a parent heading is left out
    // of the maths — otherwise it would be counted once for itself and again
    // through the children that make it up.
    function clLeaves(list) {
        var hasChild = {};
        list.forEach(function (it) { if (it.parent_id) hasChild[it.parent_id] = true; });
        return list.filter(function (it) { return !hasChild[it.id]; });
    }

    // Only completed items count toward the percentage.
    function paintChecklistProgress(checklists, todoId) {
        var leaves  = clLeaves(checklists);
        var total   = leaves.length;
        var checked = leaves.filter(function (c) { return clStatusOf(c) === 'completed'; }).length;
        var pct     = total ? Math.round(checked / total * 100) : 0;
        var colour  = pct === 100 ? '#16a34a' : '#2563eb';

        $('#checklist_progress_wrap').removeClass('hidden');
        $('#checklist_progress_count').text(checked + '/' + total + ' done');
        $('#checklist_progress_bar').css({ width: pct + '%', background: colour });
        $('#checklist_progress_text').text(checked + ' of ' + total + ' items completed (' + pct + '%)');

        // Keep the row badge behind the modal in step, so closing the modal
        // doesn't leave a stale count until the page is reloaded.
        var $badge = $('.view-checklist-btn[data-id="' + todoId + '"]');
        $badge.find('.cl-count').text(checked + '/' + total + ' done');
        $badge.find('.cl-bar').css({ width: pct + '%', background: colour });
        $badge.find('.cl-pct').text(pct + '%');
    }

    $(document).on('click', '.cl-parent-row', function (e) {
        // The sub-item list sits inside the parent now, so a click on a sub-item,
        // the drag grip or a status control would otherwise bubble up here and
        // fold the parent out from under whatever the user was actually doing.
        if ($(e.target).closest('.cl-children, .cl-handle, select, option').length) return;

        var $row      = $(this);
        var collapsed = $row.attr('data-collapsed') === '1';

        $row.attr('data-collapsed', collapsed ? '0' : '1');
        $row.children('.flex').children('.cl-caret')
            .css('transform', collapsed ? 'rotate(0deg)' : 'rotate(-90deg)');
        $row.children('.cl-children').css('display', collapsed ? 'block' : 'none');
    });

    function clBuildTree(list) {
        var byId = {}, roots = [];
        list.forEach(function (it) { byId[it.id] = { item: it, children: [] }; });
        list.forEach(function (it) {
            var node = byId[it.id];
            if (it.parent_id && byId[it.parent_id]) byId[it.parent_id].children.push(node);
            else roots.push(node);
        });
        return roots;
    }

    function renderChecklistItems(checklists, isAdmin) {
        $('#checklist_loading').hide();
        if (!checklists || !checklists.length) {
            $('#checklist_empty').removeClass('hidden');
            $('#checklist_items_list').addClass('hidden');
            $('#checklist_progress_wrap').addClass('hidden');
            $('#checklist_progress_text').text('');
            return;
        }

        var todoId = $('#checklistModal').data('todo-id');
        paintChecklistProgress(checklists, todoId);

        var day = function (v) { return v ? String(v).substr(0, 10) : ''; };
        var $list = $('#checklist_items_list').empty().removeClass('hidden');

        // Sub-items live inside their parent's element rather than beside it, so
        // dragging a parent carries its own sub-items along and a sub-item can
        // never be dropped above the parent it belongs to.
        function renderNode(node, depth) {
            var item     = node.item;
            var isParent = node.children.length > 0;
            var status   = clStatusOf(item);

            var $li = $('<li>').addClass('cl-node px-3 py-2 rounded-md border border-gray-100 bg-white hover:bg-gray-50 transition-colors')
                .attr('data-id', item.id)
                .css({ borderLeft: depth ? '3px solid #dbeafe' : '', marginBottom: '6px' });

            var $top   = $('<div>').addClass('flex items-start gap-2');

            // Only the grip starts a drag, so tapping a row still scrolls on a
            // phone and clicking the status control doesn't tear the row loose.
            $top.append($('<i>').addClass('fas fa-grip-vertical cl-handle')
                .css({ color: '#cbd5e1', fontSize: '11px', marginTop: '4px', cursor: 'grab', flexShrink: 0 }));

            $top.append($('<span>').addClass('cl-serial')
                .css({ fontSize: '11px', fontWeight: '700', color: '#94a3b8', marginTop: '2px', minWidth: '26px', flexShrink: 0 }));

            var $label = $('<div>').addClass('flex-1 min-w-0 text-sm').text(item.title);
            if (status === 'completed') {
                $label.css({ 'text-decoration': 'line-through', 'color': '#9ca3af' });
            }
            if (isParent) {
                $label.css('font-weight', '600');
            }

            // Parents fold their sub-items away so a long checklist stays
            // scannable; the caret is both the affordance and the state.
            if (isParent) {
                $li.addClass('cl-parent-row').attr('data-collapsed', '1');
                $top.append($('<i>').addClass('fas fa-chevron-down cl-caret')
                    .css({ color: '#94a3b8', fontSize: '11px', marginTop: '4px', width: '11px', cursor: 'pointer', transition: 'transform .15s', transform: 'rotate(-90deg)' }));
            }
            $top.append($label);

            // A parent's status is derived from its children, so it's shown as a
            // read-only badge; only leaves get the editable control.
            if (isParent) {
                $top.append(
                    $('<span>').addClass('cl-status-badge')
                        .attr('data-id', item.id)
                        .attr('style', 'font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;white-space:nowrap;' + (CL_STATUS_STYLE[status] || CL_STATUS_STYLE.pending))
                        .text(CL_STATUS_LABEL[status] || 'Pending')
                );
            } else {
                var $sel = $('<select>')
                    .attr('style', 'font-size:10px;font-weight:700;border:none;border-radius:999px;padding:2px 6px;cursor:pointer;' + (CL_STATUS_STYLE[status] || CL_STATUS_STYLE.pending));
                ['pending', 'in_progress', 'completed'].forEach(function (s) {
                    $sel.append($('<option>').val(s).text(CL_STATUS_LABEL[s]).prop('selected', s === status));
                });

                $sel.on('change', function () {
                    var newStatus = $(this).val();
                    var prev      = clStatusOf(item);
                    var tid       = $('#checklistModal').data('todo-id');

                    $sel.attr('style', 'font-size:10px;font-weight:700;border:none;border-radius:999px;padding:2px 6px;cursor:pointer;' + (CL_STATUS_STYLE[newStatus] || CL_STATUS_STYLE.pending));
                    $label.css({
                        'text-decoration': newStatus === 'completed' ? 'line-through' : 'none',
                        'color': newStatus === 'completed' ? '#9ca3af' : ''
                    });

                    $.ajax({
                        url: checklistBaseUrl + '/' + tid + '/checklists/' + item.id + '/toggle',
                        method: 'POST',
                        data: { _token: '{{ csrf_token() }}', _method: 'PATCH', status: newStatus },
                        success: function (res) {
                            if (!res.success) { $sel.val(prev).trigger('change'); return; }

                            item.status     = res.status;
                            item.is_checked = res.is_checked;

                            // The parent may have rolled over to done/in-progress.
                            if (res.parent) {
                                var p = checklists.filter(function (x) { return x.id === res.parent.id; })[0];
                                if (p) { p.status = res.parent.status; p.is_checked = res.parent.is_checked; }
                                var $pb = $('.cl-status-badge[data-id="' + res.parent.id + '"]');
                                $pb.attr('style', 'font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;white-space:nowrap;' + (CL_STATUS_STYLE[res.parent.status] || CL_STATUS_STYLE.pending))
                                   .text(CL_STATUS_LABEL[res.parent.status] || 'Pending');
                            }

                            paintChecklistProgress(checklists, tid);
                        },
                        error: function () { $sel.val(prev).trigger('change'); }
                    });
                });

                $top.append($sel);
            }

            $li.append($top);

            var $meta = $('<div>').addClass('flex flex-wrap items-center gap-2')
                .css({ marginTop: '4px', paddingLeft: depth ? '16px' : '0' });
            var pr = item.priority || 'medium';
            $meta.append(
                $('<span>')
                    .attr('style', 'font-size:10px;font-weight:700;padding:1px 7px;border-radius:999px;' + (CL_PRIORITY_STYLE[pr] || CL_PRIORITY_STYLE.medium))
                    .text(pr.charAt(0).toUpperCase() + pr.slice(1))
            );
            if (item.start_date || item.end_date) {
                $meta.append(
                    $('<span>').attr('style', 'font-size:10px;color:#6b7280;')
                        .html('<i class="fas fa-calendar-alt" style="margin-right:3px;"></i>' +
                              (day(item.start_date) || '—') + ' → ' + (day(item.end_date) || '—'))
                );
            }
            if (isParent) {
                var doneKids = node.children.filter(function (c) { return clStatusOf(c.item) === 'completed'; }).length;
                $meta.append(
                    $('<span>').attr('style', 'font-size:10px;color:#6b7280;')
                        .html('<i class="fas fa-list-ul" style="margin-right:3px;"></i>' + doneKids + '/' + node.children.length + ' sub-items')
                );
            }
            $li.append($meta);

            if (isParent) {
                var $kids = $('<ul>').addClass('cl-children')
                    .attr('data-parent', item.id)
                    .css({ listStyle: 'none', margin: '8px 0 0 22px', padding: 0, display: 'none' });
                node.children.forEach(function (child) { $kids.append(renderNode(child, depth + 1)); });
                $li.append($kids);
            }

            return $li;
        }

        clBuildTree(checklists).forEach(function (node) { $list.append(renderNode(node, 0)); });

        clRenumber();
        clInitSortable();
    }

    /**
     * Rebuild the visible numbering from the DOM: 1, 2, 3 down the top level and
     * 1.1, 1.2 inside each parent. Runs after every drop, so the numbers always
     * describe what's on screen rather than the order things were created in.
     */
    function clRenumber() {
        $('#checklist_items_list').children('.cl-node').each(function (i) {
            var top = i + 1;
            $(this).children('.flex').children('.cl-serial').text(top + '.');
            $(this).children('.cl-children').children('.cl-node').each(function (j) {
                $(this).children('.flex').children('.cl-serial').text(top + '.' + (j + 1));
            });
        });
    }

    function clCollectOrder() {
        var ids = [];
        $('#checklist_items_list').children('.cl-node').each(function () {
            ids.push($(this).data('id'));
            $(this).children('.cl-children').children('.cl-node').each(function () {
                ids.push($(this).data('id'));
            });
        });
        return ids;
    }

    function clPersistOrder() {
        var todoId = $('#checklistModal').data('todo-id');
        $.ajax({
            url: checklistBaseUrl + '/' + todoId + '/checklists/reorder',
            method: 'POST',
            data: { _token: '{{ csrf_token() }}', _method: 'PATCH', order: clCollectOrder() },
            error: function () {
                alert('Could not save the new order. Reopen the checklist to see the stored arrangement.');
            }
        });
    }

    function clInitSortable() {
        if (typeof Sortable === 'undefined') return;

        var opts = {
            handle: '.cl-handle',
            animation: 150,
            ghostClass: 'cl-ghost',
            fallbackOnBody: true,
            onEnd: function () { clRenumber(); clPersistOrder(); }
        };

        // Top level moves whole parent blocks.
        Sortable.create(document.getElementById('checklist_items_list'), $.extend({ group: 'cl-top' }, opts));

        // Each parent gets its own group name, which is what stops a sub-item
        // from being dragged into a different parent for now.
        $('#checklist_items_list').find('.cl-children').each(function () {
            Sortable.create(this, $.extend({ group: 'cl-kids-' + $(this).data('parent') }, opts));
        });
    }

    $(document).on('click', '.view-checklist-btn', function () {
        var id    = $(this).data('id');
        var title = $(this).data('title');
        $('#checklistModal').data('todo-id', id);
        $('#checklist_modal_title').text(title);
        $('#checklist_loading').show();
        $('#checklist_empty').addClass('hidden');
        $('#checklist_items_list').addClass('hidden').empty();
        $('#checklist_progress_wrap').addClass('hidden');
        $('#checklist_progress_text').text('');
        $('#checklistModal').removeClass('hidden');

        $.get(checklistBaseUrl + '/' + id + '/checklists', function (res) {
            if (res.success) {
                renderChecklistItems(res.checklists, res.is_admin);
            }
        }).fail(function () {
            $('#checklist_loading').hide();
            $('#checklist_empty').removeClass('hidden').find('i').after(' Failed to load.');
        });
    });

    $('#closeChecklistModal, #cancelChecklist').on('click', function () {
        $('#checklistModal').addClass('hidden');
    });
    $('#checklistModal').on('click', function (e) {
        if ($(e.target).is('#checklistModal')) $(this).addClass('hidden');
    });
});
</script>
@endsection
