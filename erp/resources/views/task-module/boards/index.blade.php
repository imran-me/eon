@extends('layout.app')
@section('meta-information')
    <title>Board Lists</title>
@endsection
@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        /* ─── Modal ─────────────────────────────────────────────── */
        .mt-0 { margin-top: 0 !important; }
        .modal { transition: opacity 0.25s ease; }
        .modal-backdrop { background-color: rgba(0, 0, 0, 0.5); }

        /* ─── Stat Cards ─────────────────────────────────────────── */
        .board-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            padding: 1.25rem 1.25rem 0;
        }

        .board-stat-card {
            background: #fff;
            border-radius: 10px;
            padding: 1.1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
            border-left: 4px solid transparent;
        }
        .board-stat-card.total   { border-color: #6366f1; }
        .board-stat-card.pending { border-color: #f59e0b; }
        .board-stat-card.progress{ border-color: #3b82f6; }
        .board-stat-card.done    { border-color: #10b981; }

        .board-stat-icon {
            width: 42px; height: 42px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; flex-shrink: 0;
        }
        .board-stat-card.total   .board-stat-icon { background: #eef2ff; color: #6366f1; }
        .board-stat-card.pending .board-stat-icon { background: #fffbeb; color: #f59e0b; }
        .board-stat-card.progress .board-stat-icon{ background: #eff6ff; color: #3b82f6; }
        .board-stat-card.done    .board-stat-icon { background: #ecfdf5; color: #10b981; }

        .board-stat-info { line-height: 1.3; }
        .board-stat-value { font-size: 1.6rem; font-weight: 700; color: #111827; }
        .board-stat-label { font-size: 0.78rem; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; }

        /* ─── Page wrapper ───────────────────────────────────────── */
        .page-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
            overflow: hidden;
            margin-top: 0;
        }

        /* ─── Page header ────────────────────────────────────────── */
        .page-header {
            background: linear-gradient(90deg, #1e3a8a 0%, #1e40af 100%);
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-header-title {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 600;
            color: #fff;
            display: flex; align-items: center; gap: .5rem;
        }
        .page-header .header-actions { display: flex; gap: .5rem; }

        /* ─── Filter section ─────────────────────────────────────── */
        .filter-container {
            margin: 1rem 1rem 0;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        .filter-header {
            background: #f8fafc;
            padding: 0.75rem 1rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid #3b82f6;
            transition: background-color 0.2s;
        }
        .filter-header:hover { background: #f1f5f9; }
        .filter-header h3 {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 600;
            color: #374151;
        }
        .filter-header .toggle-icon { transition: transform 0.25s; color: #6b7280; }
        .filter-header.active .toggle-icon { transform: rotate(180deg); }

        .filter-content {
            background: #fff;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out, padding 0.3s ease-out;
            padding: 0 1rem;
        }
        .filter-content.active { max-height: 400px; padding: 1rem; }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
        }
        .filter-group { flex: 1; min-width: 180px; }
        .filter-group label {
            display: block;
            margin-bottom: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: .03em;
        }
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: #fff;
        }
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,.12);
        }
        .filter-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            padding-bottom: 2px;
        }
        .btn-filter-apply {
            background: #2563eb; color: #fff; border: none;
            padding: 8px 16px; border-radius: 6px;
            font-size: 0.875rem; font-weight: 500;
            cursor: pointer; transition: background 0.2s;
        }
        .btn-filter-apply:hover { background: #1d4ed8; }
        .btn-filter-reset {
            background: #f3f4f6; color: #374151;
            border: 1px solid #d1d5db;
            padding: 8px 16px; border-radius: 6px;
            font-size: 0.875rem; font-weight: 500;
            cursor: pointer; transition: background 0.2s;
        }
        .btn-filter-reset:hover { background: #e5e7eb; }

        /* ─── Table ──────────────────────────────────────────────── */
        .table-wrapper { padding: 1rem; }
        .table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .table thead tr { background: #f8fafc; }
        .table thead th {
            padding: 0.7rem 0.9rem;
            font-size: 0.75rem;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .05em;
            border-bottom: 2px solid #e5e7eb;
            white-space: nowrap;
        }
        .table tbody tr { transition: background 0.15s; }
        .table tbody tr:hover { background: #f8fafc; }
        .table tbody td {
            padding: 0.75rem 0.9rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            color: #374151;
        }
        .table tbody tr:last-child td { border-bottom: none; }

        /* row serial number */
        .row-serial {
            display: inline-flex; align-items: center; justify-content: center;
            width: 26px; height: 26px;
            background: #eff6ff; color: #2563eb;
            border-radius: 50%; font-size: 0.75rem; font-weight: 700;
        }

        /* ─── Column badges ──────────────────────────────────────── */
        .col-badges { display: flex; flex-wrap: wrap; gap: 4px; }
        .col-badge {
            display: inline-flex; align-items: center;
            padding: 2px 8px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 600; white-space: nowrap;
        }
        .col-badge-0 { background: #ede9fe; color: #5b21b6; }
        .col-badge-1 { background: #dcfce7; color: #166534; }
        .col-badge-2 { background: #fef9c3; color: #854d0e; }

        /* ─── Status select ──────────────────────────────────────── */
        .status-select {
            font-weight: 600;
            font-size: 0.8rem;
            border-radius: 20px;
            padding: 4px 10px;
            cursor: pointer;
            border: 1px solid transparent;
        }
        .status-select.pending     { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
        .status-select.in_progress { background: #dbeafe; color: #1e40af; border-color: #60a5fa; }
        .status-select.completed   { background: #d1fae5; color: #065f46; border-color: #34d399; }
        .status-select.cancelled   { background: #fee2e2; color: #991b1b; border-color: #f87171; }

        /* ─── Action buttons ─────────────────────────────────────── */
        .btn-icon {
            display: inline-flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; border-radius: 6px;
            border: 1px solid; transition: all 0.15s; font-size: 0.8rem;
            text-decoration: none;
        }
        .btn-icon-edit  { color: #2563eb; border-color: #bfdbfe; background: #eff6ff; }
        .btn-icon-edit:hover { background: #2563eb; color: #fff; border-color: #2563eb; }
        .btn-icon-del   { color: #dc2626; border-color: #fecaca; background: #fff5f5; }
        .btn-icon-del:hover { background: #dc2626; color: #fff; border-color: #dc2626; }

        /* ─── Empty state ────────────────────────────────────────── */
        .empty-state {
            text-align: center; padding: 3.5rem 1rem;
        }
        .empty-state-icon {
            width: 72px; height: 72px; margin: 0 auto 1rem;
            background: #f3f4f6; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; color: #9ca3af;
        }
        .empty-state h4 { font-size: 1rem; font-weight: 600; color: #374151; margin-bottom: .25rem; }
        .empty-state p  { font-size: 0.875rem; color: #9ca3af; }

        /* ─── Pagination ─────────────────────────────────────────── */
        .pagination-wrapper {
            padding: .75rem 1rem;
            border-top: 1px solid #f1f5f9;
        }
        span [aria-current="page"] span {
            background-color: #2563eb !important;
            background: #2563eb !important;
            color: white;
            border-color: #2563eb;
        }

        /* ─── Select2 height fix ─────────────────────────────────── */
        .select2-container .select2-selection--single { height: 38px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 38px; top: 1px; right: 3px; }

        /* ─── Responsive ─────────────────────────────────────────── */
        @media (max-width: 768px) {
            .page-header { flex-direction: column; gap: .75rem; align-items: flex-start; }
            .page-header .header-actions { width: 100%; }
            .page-header .header-actions .btn { width: 100%; }
            .board-stats-grid { grid-template-columns: 1fr 1fr; }
            .filter-row { flex-direction: column; }
        }
    </style>
@endsection

@section('main-content')

    {{-- ── Stat Cards ───────────────────────────────────────────────── --}}
    <div class="board-stats-grid">
        <div class="board-stat-card total">
            <div class="board-stat-icon"><i class="fas fa-layer-group"></i></div>
            <div class="board-stat-info">
                <div class="board-stat-value">{{ $datas->total() }}</div>
                <div class="board-stat-label">Total Boards</div>
            </div>
        </div>
        <div class="board-stat-card pending">
            <div class="board-stat-icon"><i class="fas fa-clock"></i></div>
            <div class="board-stat-info">
                <div class="board-stat-value">{{ $datas->getCollection()->where('status','pending')->count() }}</div>
                <div class="board-stat-label">Pending</div>
            </div>
        </div>
        <div class="board-stat-card progress">
            <div class="board-stat-icon"><i class="fas fa-spinner"></i></div>
            <div class="board-stat-info">
                <div class="board-stat-value">{{ $datas->getCollection()->where('status','in_progress')->count() }}</div>
                <div class="board-stat-label">In Progress</div>
            </div>
        </div>
        <div class="board-stat-card done">
            <div class="board-stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="board-stat-info">
                <div class="board-stat-value">{{ $datas->getCollection()->where('status','completed')->count() }}</div>
                <div class="board-stat-label">Completed</div>
            </div>
        </div>
    </div>

    {{-- ── Main Card ────────────────────────────────────────────────── --}}
    <div class="page-card" style="margin-top: 1rem;">

        {{-- Header --}}
        <div class="page-header">
            <h2 class="page-header-title">
                <i class="fas fa-th-large"></i> Board List
            </h2>
            <div class="header-actions">
                @can('view task')
                    <a href="{{ route('role.tasks.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                        class="btn btn-sm btn-success">
                        <i class="fas fa-tasks me-1"></i>My Tasks
                    </a>
                @endcan
                @can('create board')
                    <button class="btn btn-sm btn-light create-btn">
                        <i class="fas fa-plus me-1"></i>Add Board
                    </button>
                @endcan
            </div>
        </div>

        {{-- Filter --}}
        <form action="" method="get">
            <div class="filter-container">
                <div class="filter-header">
                    <h3><i class="fas fa-filter me-2"></i>Filter Options</h3>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
                <div class="filter-content">
                    <div class="filter-row">
                        @can('create board')
                            <div class="filter-group">
                                <label for="filter_workspace">Workspace</label>
                                <select id="filter_workspace" name="workspace_id" class="select2">
                                    <option value="">All Workspaces</option>
                                    @foreach ($workspaces as $workspace)
                                        <option value="{{ $workspace->id }}"
                                            {{ request('workspace_id') == $workspace->id ? 'selected' : '' }}>
                                            {{ $workspace->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endcan

                        <div class="filter-group">
                            <label for="filter_project">Project</label>
                            <select id="filter_project" name="project_id" class="select2">
                                <option value="">All Projects</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}"
                                        {{ request('project_id') == $project->id ? 'selected' : '' }}>
                                        {{ $project->project_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="filter_name">Board Name</label>
                            <input type="text" name="name" id="filter_name" value="{{ request('name') }}"
                                placeholder="Search by name…">
                        </div>

                        <div class="filter-actions" style="min-width: 160px;">
                            <button type="button" class="btn-filter-reset reset-btn">
                                <i class="fas fa-undo me-1"></i>Reset
                            </button>
                            <button type="submit" class="btn-filter-apply">
                                <i class="fas fa-search me-1"></i>Apply
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        {{-- Table --}}
        <div class="table-wrapper">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:4%">#</th>
                            <th>Workspace</th>
                            <th>Project</th>
                            <th style="width:16%">Columns</th>
                            <th>Board Name</th>
                            <th style="width:13%">Created By</th>
                            <th style="width:11%">Created On</th>
                            <th style="width:14%">Status</th>
                            <th style="width:10%">View</th>
                            @canany(['edit board', 'delete board'])
                                <th style="width:8%">Actions</th>
                            @endcanany
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $key => $value)
                            <tr>
                                <td>
                                    <span class="row-serial">
                                        {{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}
                                    </span>
                                </td>
                                <td>{{ $value->workspace->name }}</td>
                                <td>{{ $value->project->project_name }}</td>
                                <td>
                                    <div class="col-badges">
                                        @foreach ($value->columns as $index => $column)
                                            <span class="col-badge col-badge-{{ $index % 3 }}">
                                                {{ $column->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td><strong>{{ $value->name }}</strong></td>
                                <td>{{ $value->user->name }}</td>
                                <td>
                                    <span class="text-muted" style="font-size:.8rem">
                                        {{ date('M d, Y', strtotime($value->created_at)) }}
                                    </span>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm board-status status-select {{ $value->status }}"
                                        data-id="{{ $value->id }}" style="min-width:130px">
                                        <option value="pending"     {{ $value->status == 'pending'     ? 'selected' : '' }}>Pending</option>
                                        <option value="in_progress" {{ $value->status == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                        <option value="completed"   {{ $value->status == 'completed'   ? 'selected' : '' }}>Completed</option>
                                        <option value="cancelled"   {{ $value->status == 'cancelled'   ? 'selected' : '' }}>Cancelled</option>
                                    </select>
                                </td>
                                <td>
                                    <a href="{{ route('role.tasks.board', ['board' => $value->id, 'role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                                @canany(['edit board', 'delete board'])
                                    <td>
                                        <div class="d-flex gap-1">
                                            @can('edit board')
                                                <a href="{{ route('role.boards.edit', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'board' => $value->id]) }}"
                                                    class="btn-icon btn-icon-edit" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            @endcan
                                            @can('delete board')
                                                <button class="btn-icon btn-icon-del" title="Delete"
                                                    onclick="confirmDelete('{{ $value->id }}', '{{ $value->name }}')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            @endcan
                                        </div>
                                    </td>
                                @endcanany
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-layer-group"></i>
                                        </div>
                                        <h4>No boards found</h4>
                                        <p>Try adjusting your filters or create a new board.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pagination --}}
        <div class="pagination-wrapper">
            {{ $datas->appends(request()->all())->links() }}
        </div>

    </div>

    @include('task-module.boards.create-modal')
    @include('task-module.boards.delete-modal')

@endsection

@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function () {

            $('.select2').select2();

            $.ajaxSetup({
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
            });

            // ── Modals ────────────────────────────────────────────────
            $('.create-btn').click(function () {
                $('#createModal').removeClass('hidden');
                $('#createModal .select2').select2();
                $('#createForm')[0].reset();
                $('.error-message').addClass('hidden');
            });

            $('.edit-btn').click(function () {
                $('#editItemId').val($(this).data('item_id'));
                $('#edit_project_id').val($(this).data('project_id')).trigger('change');
                $('#edit_name').val($(this).data('name'));
                $('#edit_description').val($(this).data('description'));
                $('#columns').val($(this).data('columns')).trigger('change');
                $('#editModal').removeClass('hidden');
            });

            $('.modal-close-create, .modal-backdrop').click(function (e) {
                if ($(e.target).closest('.modal-close-create').length || $(e.target).hasClass('modal-backdrop')) {
                    $('#createModal').addClass('hidden');
                }
            });

            $('.modal-close-edit, .modal-backdrop').click(function (e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-edit').length) {
                    $('#editModal').addClass('hidden');
                }
            });

            $('.modal-close-delete, .modal-backdrop').click(function (e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-delete').length) {
                    $('#deleteModal').addClass('hidden');
                }
            });

            $('.close-btn').click(function () {
                $(this).closest('.alert').addClass('hidden');
            });

            // ── Create ────────────────────────────────────────────────
            $('#createSubmit').click(function (e) {
                e.preventDefault();
                if (!validateCreateForm()) return;

                $.ajax({
                    url: $('#createForm').attr('action'),
                    method: 'POST',
                    data: new FormData($('#createForm')[0]),
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Board created successfully!' });
                            $('#createModal').addClass('hidden');
                            $('#createForm')[0].reset();
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops…', text: response.message || 'Something went wrong.' });
                        }
                    },
                    error: function (xhr) {
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to create board.' });
                    }
                });
            });

            // ── Edit ──────────────────────────────────────────────────
            $('#editSubmit').click(function () {
                if (!validateEditForm()) return;

                $.ajax({
                    url: $(this).data('action'),
                    method: 'POST',
                    data: new FormData($('#editeForm')[0]),
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Board updated successfully!' });
                            $('#editModal').addClass('hidden');
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops…', text: response.message || 'Update failed.' });
                        }
                    },
                    error: function (xhr) {
                        Swal.fire({ icon: 'error', title: 'Error!', text: xhr.responseJSON?.message || 'Something went wrong!' });
                    }
                });
            });

            // ── Delete ────────────────────────────────────────────────
            $('#confirmDeleteBtn').click(function () {
                const dataId  = $(this).data('item-id');
                const deleteUrl = $(this).data('action');

                $.ajax({
                    url: deleteUrl,
                    method: 'DELETE',
                    data: { item_id: dataId },
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Board deleted successfully!' });
                            $('#deleteModal').addClass('hidden');
                            setTimeout(() => window.location.reload(), 500);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops…', text: response.message });
                        }
                    },
                    error: function () {
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong!' });
                    }
                });
            });

            // ── Column position inputs ────────────────────────────────
            $('#columns').on('change', function () {
                let container = $('#columnPositionsContainer');
                container.html('');

                ($(this).val() || []).forEach(function (columnId) {
                    let columnName = $('#columns option[value="' + columnId + '"]').text();
                    container.append(`
                        <div class="flex items-center gap-3 border p-2 rounded">
                            <div class="w-1/2">
                                <label class="text-sm text-gray-600">${columnName}</label>
                                <input type="hidden" name="column_ids[]" value="${columnId}">
                            </div>
                            <div class="w-1/2">
                                <input type="number" name="positions[]" placeholder="Enter position"
                                    class="w-full px-3 py-2 border rounded-lg" required>
                            </div>
                        </div>
                    `);
                });
            });
        });

        // ── Filter toggle ─────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', function () {
            const filterHeader  = document.querySelector('.filter-container .filter-header');
            const filterContent = document.querySelector('.filter-container .filter-content');

            filterHeader.addEventListener('click', function () {
                this.classList.toggle('active');
                filterContent.classList.toggle('active');
            });

            document.querySelector('.reset-btn').addEventListener('click', function (e) {
                e.preventDefault();
                window.location = "{{ route('role.boards.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}";
            });
        });

        // ── Status change ─────────────────────────────────────────────
        $(document).on('change', '.board-status', function () {
            let status    = $(this).val();
            let id        = $(this).data('id');
            let selectBox = $(this);

            let url = "{{ route('role.boards.updateStatus', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'id' => 'id']) }}";
            url = url.replace('id', id);

            $.ajax({
                url: url,
                type: 'POST',
                data: {
                    status: status,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function () {
                    selectBox.removeClass('pending in_progress completed cancelled').addClass(status);
                    Swal.fire({ icon: 'success', title: 'Status Updated', timer: 1200, showConfirmButton: false });
                },
                error: function () {
                    Swal.fire({ icon: 'error', title: 'Update Failed' });
                }
            });
        });

        // ── Validation ────────────────────────────────────────────────
        function validateCreateForm() {
            let isValid = true;
            $('#createForm .error-message').addClass('hidden');
            $('#createForm .form-select, #createForm .form-input').removeClass('border-red-500');

            if (!$('#project_id').val()) {
                $('#project_id').siblings('.error-message').removeClass('hidden');
                $('#project_id').addClass('border-red-500');
                isValid = false;
            }
            if (!$('#name').val().trim()) {
                $('#name').siblings('.error-message').removeClass('hidden');
                $('#name').addClass('border-red-500');
                isValid = false;
            }
            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            $('#editeForm .error-message').addClass('hidden');
            $('#editeForm .form-select, #editeForm .form-input').removeClass('border-red-500');

            if (!$('#edit_project_id').val().trim()) {
                $('#edit_project_id').next('.error-message').removeClass('hidden');
                $('#edit_project_id').addClass('border-red-500');
                isValid = false;
            }
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
@endsection
