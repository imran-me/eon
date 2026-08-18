@extends('layout.app')
@section('meta-information')
    <title>Resignation List</title>
@endsection
@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @include('layout.table-design')
    <style>
        /* ── Summary Cards ── */
        .resign-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        @media (max-width: 1024px) { .resign-stats { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 600px)  { .resign-stats { grid-template-columns: 1fr; } }

        .stat-card {
            background: #fff;
            border-radius: 10px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,.06);
            border: 1px solid #e2e8f0;
        }
        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; flex-shrink: 0;
        }
        .stat-icon.blue   { background: #dbeafe; color: #2563eb; }
        .stat-icon.yellow { background: #fef3c7; color: #d97706; }
        .stat-icon.green  { background: #d1fae5; color: #059669; }
        .stat-icon.red    { background: #fee2e2; color: #dc2626; }
        .stat-info p { margin: 0; font-size: .78rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
        .stat-info h3 { margin: 2px 0 0; font-size: 1.6rem; font-weight: 700; color: #1e293b; }

        /* ── Filter Card ── */
        .filter-card-container {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
            margin-bottom: 20px;
        }
        .filter-card-header {
            padding: 14px 20px;
            border-bottom: 1px solid #f1f5f9;
            background: #f8fafc;
            border-radius: 10px 10px 0 0;
        }
        .filter-card-title {
            margin: 0; font-size: 1rem; font-weight: 600; color: #1e293b;
            display: flex; align-items: center; gap: 8px;
        }
        .filter-card-body { padding: 20px; }
        .filter-grid-form {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            align-items: end;
        }
        @media (max-width: 1024px) { .filter-grid-form { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 600px)  { .filter-grid-form { grid-template-columns: 1fr; } }

        .filter-field-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-label { font-size: .8rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
        .filter-input {
            width: 100%; padding: 9px 12px;
            border: 1px solid #cbd5e1; border-radius: 6px;
            font-size: .9rem; color: #334155; background: #fff;
            box-sizing: border-box;
        }
        .filter-input:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
        .filter-actions-group {
            grid-column: 1 / -1;
            display: flex; justify-content: flex-end; gap: 10px;
            padding-top: 10px; border-top: 1px solid #f1f5f9;
        }
        .btn-submit  { background: #2563eb; color: #fff; border: none; padding: 9px 22px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background .2s; }
        .btn-submit:hover { background: #1d4ed8; }
        .btn-reset   { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; padding: 9px 22px; border-radius: 6px; font-weight: 600; text-decoration: none; display: inline-block; transition: all .2s; }
        .btn-reset:hover { background: #e2e8f0; color: #1e293b; }

        /* ── Table Action Buttons ── */
        .manage-user-container .btn-edit   { background: #3b82f6; color: #fff; border: none; padding: 5px 10px; border-radius: 0; cursor: pointer; font-size: .82rem; }
        .manage-user-container .btn-edit:hover { background: #2563eb; }
        .manage-user-container .btn-approve { background: #10b981; color: #fff; border: none; padding: 5px 10px; border-radius: 0; cursor: pointer; font-size: .82rem; }
        .manage-user-container .btn-approve:hover { background: #059669; }
        .manage-user-container .btn-reject  { background: #f59e0b; color: #fff; border: none; padding: 5px 10px; border-radius: 0; cursor: pointer; font-size: .82rem; }
        .manage-user-container .btn-reject:hover { background: #d97706; }
        .manage-user-container .btn-delete  { background: #ef4444; color: #fff; border: none; padding: 5px 10px; border-radius: 0; cursor: pointer; font-size: .82rem; }
        .manage-user-container .btn-delete:hover { background: #dc2626; }

        /* ── Employee Cell ── */
        .emp-name  { font-weight: 600; color: #1e293b; }
        .emp-sub   { font-size: .75rem; color: #94a3b8; }

        /* ── Resign Type Badge ── */
        .rtype { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: .75rem; font-weight: 600; text-transform: capitalize; }
        .rtype-voluntary    { background: #dbeafe; color: #1d4ed8; }
        .rtype-termination  { background: #fee2e2; color: #b91c1c; }
        .rtype-abscond      { background: #fef3c7; color: #92400e; }

        /* ── Print List ── */
        .print-only-title { text-align: center; margin-bottom: 16px; }
        .print-only-title h2 { margin: 0; font-size: 1.3rem; font-weight: 700; color: #1e293b; }
        .print-only-title p  { margin: 2px 0 0; font-size: .8rem; color: #64748b; }

        @media print {
            .no-print { display: none !important; }
            .print-only-title { display: block !important; }
            body, .states-table { background: #fff !important; box-shadow: none !important; }
            .states-table-content { padding: 0 !important; }
            table { font-size: 11px; }
        }
    </style>
@endsection

@section('main-content')

    {{-- ── Summary Cards ── --}}
    <div class="resign-stats no-print">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <p>Total</p>
                <h3>{{ $resignations->total() }}</h3>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <p>Pending</p>
                <h3>{{ $pendingCount ?? 0 }}</h3>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <p>Approved</p>
                <h3>{{ $approvedCount ?? 0 }}</h3>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
            <div class="stat-info">
                <p>Rejected</p>
                <h3>{{ $rejectedCount ?? 0 }}</h3>
            </div>
        </div>
    </div>

    {{-- ── Filter Card ── --}}
    <div class="filter-card-container no-print">
        <div class="filter-card-header">
            <h5 class="filter-card-title">
                <i class="fas fa-filter"></i> Filter Resignations
            </h5>
        </div>
        <div class="filter-card-body">
            <form action="{{ route('role.resignations.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="GET" class="filter-grid-form">

                <div class="filter-field-group">
                    <label class="filter-label">Employee</label>
                    <select name="employee_id" class="filter-input select2">
                        <option value="">All Employees</option>
                        @foreach($employees ?? [] as $emp)
                            <option value="{{ $emp->id }}" {{ request('employee_id') == $emp->id ? 'selected' : '' }}>
                                {{ $emp->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-field-group">
                    <label class="filter-label">Resign Type</label>
                    <select name="resign_type" class="filter-input select2">
                        <option value="">All Types</option>
                        <option value="voluntary"   {{ request('resign_type') == 'voluntary'   ? 'selected' : '' }}>Voluntary</option>
                        <option value="termination" {{ request('resign_type') == 'termination' ? 'selected' : '' }}>Termination</option>
                        <option value="abscond"     {{ request('resign_type') == 'abscond'     ? 'selected' : '' }}>Abscond</option>
                    </select>
                </div>

                <div class="filter-field-group">
                    <label class="filter-label">Status</label>
                    <select name="status" class="filter-input select2">
                        <option value="">All Status</option>
                        <option value="pending"  {{ request('status') == 'pending'  ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>

                <div class="filter-field-group">
                    <label class="filter-label">Resign Date</label>
                    <input type="date" name="resign_date" class="filter-input" value="{{ request('resign_date') }}">
                </div>

                <div class="filter-actions-group">
                    <button type="submit" class="btn-submit"><i class="fas fa-search mr-1"></i> Filter</button>
                    <a href="{{ route('role.resignations.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="btn-reset">
                        <i class="fas fa-redo mr-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Main Table Card ── --}}
    <div class="states-table bg-white rounded-lg shadow-md overflow-hidden">
        <div class="states-table-container">

            <div class="states-table-header bg-blue-600 px-6 py-4 flex justify-between items-center no-print">
                <h2 class="states-table-title text-white text-xl font-semibold" style="color:white">
                    <i class="fas fa-file-alt mr-2"></i>Resignation List
                </h2>
                <div class="flex items-center gap-2">
                    <a href="{{ route('role.resignations.print', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}?{{ http_build_query(request()->all()) }}"
                        target="_blank"
                        class="btn btn-primary bg-cyan-600 hover:bg-cyan-700 text-white px-4 py-2 rounded-md transition duration-200">
                        <i class="fas fa-print mr-2"></i>Print List
                    </a>
                    @can('create resignation')
                    <a href="{{ route('role.resignations.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                        class="btn btn-primary create-new-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Add New Resignation
                    </a>
                    @endcan
                </div>
            </div>

            {{-- Print-only heading --}}
            <div class="print-only-title" style="display:none;">
                <h2>Resignation List</h2>
                <p>Generated on {{ now()->format('d M Y, h:i A') }}</p>
            </div>

            <div class="states-table-content manage-user-container">
                <div class="table-responsive overflow-x-auto" style="padding:15px">
                    <table class="table table-hover min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="padding-left:20px">SL</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resign Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Working Day</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notice Days</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approved By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                @canany(['edit resignation'])
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider no-print">Actions</th>
                                @endcanany
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($resignations as $key => $value)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap" style="padding-left:20px">
                                        <strong>{{ ($resignations->currentPage() - 1) * $resignations->perPage() + $key + 1 }}</strong>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="emp-name">{{ $value->user->name }}</div>
                                        <div class="emp-sub">ID: {{ $value->employee_id }}</div>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>{{ \Carbon\Carbon::parse($value->resign_date)->format('d M Y') }}</div>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($value->last_working_day)->format('d M Y') }}
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @php
                                            $typeClass = match($value->resign_type) {
                                                'termination' => 'rtype-termination',
                                                'abscond'     => 'rtype-abscond',
                                                default       => 'rtype-voluntary',
                                            };
                                        @endphp
                                        <span class="rtype {{ $typeClass }}">{{ ucfirst($value->resign_type) }}</span>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->reason ? ucwords(str_replace('_', ' ', $value->reason)) : '-' }}
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="font-semibold">{{ $value->notice_period_days }}</span>
                                        <span class="text-xs text-gray-400"> days</span>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($value->approvedBy)
                                            <div class="emp-name">{{ $value->approvedBy->name }}</div>
                                            @if($value->approved_at)
                                                <div class="emp-sub">{{ \Carbon\Carbon::parse($value->approved_at)->format('d M Y') }}</div>
                                            @endif
                                        @else
                                            <span class="text-gray-400 text-sm">—</span>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if ($value->status === 'approved')
                                            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                                <i class="fas fa-check mr-1"></i>Approved
                                            </span>
                                        @elseif($value->status === 'rejected')
                                            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                                <i class="fas fa-times mr-1"></i>Rejected
                                            </span>
                                        @else
                                            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">
                                                <i class="fas fa-clock mr-1"></i>Pending
                                            </span>
                                        @endif
                                    </td>

                                    @canany(['edit resignation'])
                                    <td class="px-6 py-4 whitespace-nowrap no-print">
                                        <div class="flex gap-0">
                                            <a href="{{ route('role.resignations.show', [
                                                    'role'        => Str::slug(Auth::user()->getRoleNames()->first()),
                                                    'resignation' => $value->id,
                                                ]) }}" class="btn-edit" style="background:#6366f1">View</a>
                                            <a href="{{ route('role.resignations.edit', [
                                                    'role'        => Str::slug(Auth::user()->getRoleNames()->first()),
                                                    'resignation' => $value->id,
                                                ]) }}" class="btn-edit">Edit</a>
                                            <a href="{{ route('role.resignations.show', [
                                                    'role'        => Str::slug(Auth::user()->getRoleNames()->first()),
                                                    'resignation' => $value->id,
                                                ]) }}?print=1" target="_blank" class="btn-edit" style="background:#0891b2" title="Print resignation letter">
                                                <i class="fas fa-print"></i>
                                            </a>

                                            @if($value->status === 'pending')
                                                <form action="{{ route('role.resignations.approve', [
                                                        'role' => Str::slug(Auth::user()->getRoleNames()->first()),
                                                        'id'   => $value->id,
                                                    ]) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn-approve">Approve</button>
                                                </form>

                                                <form action="{{ route('role.resignations.reject', [
                                                        'role' => Str::slug(Auth::user()->getRoleNames()->first()),
                                                        'id'   => $value->id,
                                                    ]) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn-reject">Reject</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                    @endcanany
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-10">
                                        <i class="fas fa-inbox fa-3x text-gray-300 mb-3 d-block"></i>
                                        <h5 class="text-gray-500 font-medium mb-1">No resignation records found</h5>
                                        <p class="text-gray-400 text-sm">Try adjusting your filters or add a new resignation.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-3 border-t border-gray-200 no-print">
                    {{ $resignations->appends(request()->all())->links() }}
                </div>
            </div>

        </div>
    </div>

@endsection

@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $('.select2').select2();
    </script>
@endsection
