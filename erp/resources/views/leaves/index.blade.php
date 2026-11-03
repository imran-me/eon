@extends('layout.app')
@section('meta-information')
    <title>Manage Leaves</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@include('layout.table-design')
<style>
    .leave-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    @media (max-width: 1024px) { .leave-stats { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 600px) { .leave-stats { grid-template-columns: 1fr; } }

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
    .stat-icon.blue { background: #dbeafe; color: #2563eb; }
    .stat-icon.yellow { background: #fef3c7; color: #d97706; }
    .stat-icon.green { background: #d1fae5; color: #059669; }
    .stat-icon.red { background: #fee2e2; color: #dc2626; }
    .stat-info p {
        margin: 0;
        font-size: .78rem;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .05em;
    }
    .stat-info h3 { margin: 2px 0 0; font-size: 1.6rem; font-weight: 700; color: #1e293b; }

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
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .filter-card-body { padding: 20px; }
    .filter-grid-form {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 16px;
        align-items: end;
    }
    @media (max-width: 1200px) { .filter-grid-form { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 768px) { .filter-grid-form { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 600px) { .filter-grid-form { grid-template-columns: 1fr; } }

    .filter-field-group { display: flex; flex-direction: column; gap: 6px; }
    .filter-label {
        font-size: .8rem;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .filter-input {
        width: 100%;
        padding: 9px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: .9rem;
        color: #334155;
        background: #fff;
        box-sizing: border-box;
    }
    .filter-input:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
    .filter-actions-group {
        grid-column: 1 / -1;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding-top: 10px;
        border-top: 1px solid #f1f5f9;
    }
    .btn-submit {
        background: #2563eb;
        color: #fff;
        border: none;
        padding: 9px 22px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: background .2s;
    }
    .btn-submit:hover { background: #1d4ed8; }
    .btn-reset {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
        padding: 9px 22px;
        border-radius: 6px;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
        transition: all .2s;
    }
    .btn-reset:hover { background: #e2e8f0; color: #1e293b; }

    .manage-user-container .btn-view,
    .manage-user-container .btn-edit,
    .manage-user-container .btn-delete,
    .manage-user-container .btn-approve {
        color: #fff;
        border: none;
        padding: 5px 10px;
        border-radius: 0;
        cursor: pointer;
        font-size: .82rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        line-height: 1.3;
    }
    .manage-user-container .btn-view { background: #6366f1; }
    .manage-user-container .btn-view:hover { background: #4f46e5; color: #fff; }
    .manage-user-container .btn-edit { background: #3b82f6; }
    .manage-user-container .btn-edit:hover { background: #2563eb; color: #fff; }
    .manage-user-container .btn-delete { background: #ef4444; }
    .manage-user-container .btn-delete:hover { background: #dc2626; color: #fff; }
    .manage-user-container .btn-approve { background:#16a34a; }
    .manage-user-container .btn-approve:hover { background:#15803d; color:#fff; }

    .emp-name { font-weight: 600; color: #1e293b; }
    .emp-sub { font-size: .75rem; color: #94a3b8; }
    .leave-type-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: .75rem;
        font-weight: 600;
        background: #dbeafe;
        color: #1d4ed8;
    }

    /* Compact report-style leave filters */
    .filter-card-container { border-radius: 9px; margin-bottom: 14px; }
    .filter-card-header { display: none; }
    .filter-card-body { padding: 10px 12px; }
    .filter-grid-form {
        grid-template-columns: 220px minmax(165px,1fr) minmax(145px,.9fr) minmax(130px,.8fr) 120px 135px auto;
        gap: 8px;
    }
    .filter-field-group { gap: 3px; min-width: 0; }
    .filter-label { font-size: 10px; line-height: 1.1; letter-spacing: .035em; }
    .filter-input { height: 34px; padding: 5px 9px; border-radius: 5px; font-size: 12px; }
    .filter-card-container .select2-container .select2-selection--single { height: 34px; }
    .filter-card-container .select2-selection__rendered { line-height: 32px !important; padding-left: 9px !important; font-size: 12px; }
    .filter-card-container .select2-selection__arrow { height: 32px !important; }
    .filter-actions-group { grid-column: auto; gap: 7px; padding: 0; border: 0; }
    .btn-submit, .btn-reset { height: 34px; min-width: 72px; display: inline-flex; align-items: center; justify-content: center; gap: 5px; padding: 0 10px; border-radius: 5px; font-size: 11px; line-height: 1; white-space: nowrap; }
    .pending-stat-link { display:block; color:inherit; text-decoration:none; border-radius:10px; }
    .pending-stat-link .stat-card { height:100%; transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease; cursor:pointer; }
    .pending-stat-link:hover .stat-card { transform:translateY(-2px); border-color:#f59e0b; box-shadow:0 8px 22px rgba(217,119,6,.14); }

    /* Shared Add/Edit Leave modal language */
    #createModal .modal-container, #editModal .modal-container {
        width: min(94vw, 720px); max-width: 720px; max-height: 92vh;
        border-radius: 14px; overflow-y: auto; box-shadow: 0 24px 70px rgba(15,23,42,.28);
    }
    #createModal .modal-content, #editModal .modal-content { padding: 0; }
    #createModal .modal-header, #editModal .modal-header {
        position: sticky; top: 0; z-index: 4; padding: 15px 18px;
        border: 0; background: linear-gradient(135deg,#1d4ed8,#2563eb); color: #fff;
    }
    #createModal .modal-header h3, #editModal .modal-header h3 { margin: 0; font-size: 16px; font-weight: 800; }
    #createModal .modal-header h3 small, #editModal .modal-header h3 small { display:block; margin-top:2px; color:#dbeafe; font-size:10px; font-weight:500; }
    #createModal .modal-close-create, #editModal .modal-close-edit { width:30px; height:30px; border-radius:7px; color:#fff; background:rgba(255,255,255,.14); }
    #createModal .modal-body, #editModal .modal-body { padding: 16px 18px 6px; background:#f8fafc; }
    #createModal .modal-body form, #editModal .modal-body form { padding:14px; border:1px solid #dbe4f0; border-radius:10px; background:#fff; }
    #createModal .mb-4, #editModal .mb-4 { margin-bottom: 10px; }
    #createModal label, #editModal label { margin-bottom:4px; color:#475569; font-size:11px; font-weight:700; }
    #createModal input, #createModal select, #createModal textarea,
    #editModal input, #editModal select, #editModal textarea { border-color:#cbd5e1; border-radius:6px; font-size:12px; }
    #createModal textarea, #editModal textarea { min-height:72px; }
    #createModal .modal-footer, #editModal .modal-footer {
        position:sticky; bottom:0; z-index:4; padding:11px 18px; border-top:1px solid #e2e8f0; background:#fff;
    }
    #createModal .modal-footer button, #editModal .modal-footer button { min-height:34px; padding:0 14px; border-radius:6px; font-size:11px; font-weight:700; }
    #createModal .select2-container, #editModal .select2-container { width:100% !important; }
    #createModal .select2-selection--single, #editModal .select2-selection--single { height:36px !important; }
    #createModal .select2-selection__rendered, #editModal .select2-selection__rendered { line-height:34px !important; font-size:12px; }
    #createModal .select2-selection__arrow, #editModal .select2-selection__arrow { height:34px !important; }

    #leaveReasonModal .reason-modal-panel { width:min(92vw,560px); border-radius:14px; overflow:hidden; background:#fff; box-shadow:0 24px 70px rgba(15,23,42,.3); }
    #leaveReasonModal .reason-modal-head { display:flex; align-items:center; justify-content:space-between; padding:14px 16px; background:linear-gradient(135deg,#1d4ed8,#2563eb); color:#fff; }
    #leaveReasonModal .reason-modal-head h3 { margin:0; font-size:15px; font-weight:800; }
    #leaveReasonModal .reason-modal-head .reason-modal-close { width:30px; height:30px; padding:0; border:0; border-radius:7px; background:rgba(255,255,255,.14); color:#fff; cursor:pointer; }
    #leaveReasonModal .reason-modal-body { padding:18px; }
    #leaveReasonModal .reason-employee { margin:0 0 10px; color:#64748b; font-size:11px; }
    #leaveReasonModal .reason-text { min-height:110px; margin:0; padding:14px; border:1px solid #dbe4f0; border-left:3px solid #2563eb; border-radius:7px; background:#f8fafc; color:#1e293b; font-size:13px; line-height:1.65; white-space:pre-wrap; overflow-wrap:anywhere; }
    #leaveReasonModal .reason-modal-foot { display:flex; justify-content:flex-end; padding:11px 16px; border-top:1px solid #e2e8f0; background:#fff; }
    #leaveReasonModal .reason-modal-foot button { position:static; width:auto; min-width:72px; height:34px; margin:0; padding:0 14px; transform:none; display:inline-flex; align-items:center; justify-content:center; border:1px solid #cbd5e1; border-radius:6px; background:#f8fafc; color:#475569; font-size:11px; font-weight:700; line-height:1; }

    @media (max-width:1400px) { .filter-grid-form { grid-template-columns:repeat(4,minmax(150px,1fr)); } }
    @media (max-width:900px) { .filter-grid-form { grid-template-columns:repeat(2,minmax(140px,1fr)); } }
    @media (max-width:600px) { .filter-grid-form { grid-template-columns:1fr; } .filter-actions-group { justify-content:flex-end; } }
</style>
@endsection
@section('main-content')

    <div class="leave-stats">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-info">
                <p>Total</p>
                <h3>{{ $totalCount ?? $datas->total() }}</h3>
            </div>
        </div>
        <a class="pending-stat-link" title="Show pending leave requests"
            href="{{ route('role.leaves.index', array_merge(['role' => Str::slug(Auth::user()->getRoleNames()->first())], request()->except('page'), ['status' => 'pending'])) }}">
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <p>Pending <i class="fas fa-arrow-right ml-1"></i></p>
                    <h3>{{ $pendingCount ?? 0 }}</h3>
                </div>
            </div>
        </a>
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

    @php($canViewAllLeaves = Auth::user()->hasPermissionTo('view all leave'))
    <div class="filter-card-container">
        <div class="filter-card-header">
            <h5 class="filter-card-title">
                <i class="fas fa-filter"></i> Filter Leaves
            </h5>
        </div>
        <div class="filter-card-body">
            <form id="leaveFilterForm" action="{{ route('role.leaves.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="GET" class="filter-grid-form">
                <div class="filter-field-group">
                    <label for="user_id" class="filter-label">Employee</label>
                    <select id="user_id" name="user_id" class="filter-input select2" {{ $canViewAllLeaves ? '' : 'disabled' }}>
                        <option value="">{{ $canViewAllLeaves ? 'All Employees' : 'My Leaves' }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" {{ $user->id == request('user_id') ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-field-group">
                    <label for="company_id" class="filter-label">Company</label>
                    <select id="company_id" name="company_id" class="filter-input select2" {{ $canViewAllLeaves ? '' : 'disabled' }}>
                        <option value="">{{ $canViewAllLeaves ? 'All Companies' : 'My Company' }}</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" {{ $company->id == request('company_id') ? 'selected' : '' }}>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-field-group">
                    <label for="leave_type_id" class="filter-label">Leave Type</label>
                    <select id="leave_type_id" name="leave_type_id" class="filter-input select2">
                        <option value="">All Types</option>
                        @foreach ($leave_types as $leave_type)
                            <option value="{{ $leave_type->id }}" {{ $leave_type->id == request('leave_type_id') ? 'selected' : '' }}>{{ $leave_type->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-field-group">
                    <label for="status" class="filter-label">Status</label>
                    <select id="status" name="status" class="filter-input select2">
                        <option value="">All Status</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>

                <div class="filter-field-group">
                    <label for="month" class="filter-label">Month</label>
                    <select id="month" name="month" class="filter-input select2">
                        <option value="">All Months</option>
                        @foreach ($months as $monthValue => $monthLabel)
                            <option value="{{ $monthValue }}" {{ request('month') === $monthValue ? 'selected' : '' }}>{{ $monthLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-field-group">
                    <label for="date" class="filter-label">Leave Date</label>
                    <input type="date" name="date" value="{{ request('date') }}" id="date" class="filter-input">
                </div>

                <div class="filter-actions-group">
                    <button type="submit" class="btn-submit"><i class="fas fa-search mr-1"></i> Filter</button>
                    <a href="{{ route('role.leaves.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="btn-reset">
                        <i class="fas fa-redo mr-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="states-table bg-white rounded-lg shadow-md overflow-hidden">
        <div class="states-table-container">
            <div class="states-table-header bg-blue-600 px-6 py-4 flex justify-between items-center">
                <h2 class="states-table-title text-white text-xl font-semibold" style="color: white">
                    <i class="fas fa-list mr-2"></i>Leave List
                </h2>
                @can('create leave')
                <button class="btn btn-primary create-new-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add New Leave
                </button>
                @endcan
            </div>

            <div class="states-table-content manage-user-container">
                <div class="table-responsive overflow-x-auto" style="padding: 15px">
                    <table class="table table-hover min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th style="padding-left: 20px" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SL</th>                                
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>	                                	
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>	
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Company</th>	                                                                                             	
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Date</th>	                                                                                             	
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Date</th>	                                                                                             	
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                @canany(['view leave', 'edit leave', 'delete leave'])
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                @endcanany
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($datas as $key => $value)                       
                                <tr id="leave-approval-{{ $value->id }}">
                                    <td class="px-6 py-4 whitespace-nowrap" style="padding-left: 20px">
                                        <strong>{{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}</strong>
                                    </td>                                                                                           
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="leave-type-badge">{{ $value->leave_type?->name }}</span>
                                        @if ($value->leave_time)
                                            <div class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($value->leave_time)->format('h:i A') }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="emp-name">{{ $value->user?->name }}</div>
                                        <div class="emp-sub">ID: {{ $value->user_id }}</div>
                                    </td>  
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->company?->name }}                                                                                                                        
                                    </td>                                                     
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($value->start_date)->format('d M Y') }}
                                    </td>                                                     
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($value->end_date)->format('d M Y') }}
                                    </td>                                                     
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if ($value->created_at)
                                            <div>{{ $value->created_at->format('d M Y') }}</div>
                                            <div class="text-xs text-gray-500">{{ $value->created_at->format('h:i A') }}</div>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if ($value->status == 'approved')
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                            <i class="fas fa-check mr-1"></i>Approved
                                        </span>                                            
                                        @elseif ($value->status == 'rejected')
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                            <i class="fas fa-times mr-1"></i>Rejected
                                        </span>                                            
                                        @else                                            
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">
                                            <i class="fas fa-clock mr-1"></i>Pending
                                        </span>
                                        @endif          
                                    </td>
                                    @canany(['view leave', 'edit leave', 'delete leave'])
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex gap-0">
                                            @if($value->status === 'pending')
                                                @can('approve leave')
                                                <button type="button" class="btn-approve quick-approve-btn"
                                                    data-url="{{ route('role.leaves.approve', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'leave' => $value->id]) }}"
                                                    data-employee="{{ $value->user?->name }}" title="Approve Leave">
                                                    <i class="fas fa-check mr-1"></i>Approve
                                                </button>
                                                @endcan
                                            @endif
                                            @can('view leave')
                                            <button type="button" class="btn-view view-reason-btn"
                                                data-employee="{{ $value->user?->name }}"
                                                data-reason="{{ $value->reason }}"
                                                title="View Leave">
                                                View
                                            </button>
                                            @endcan
                                            @can('edit leave')
                                            <button class="btn-edit edit-item-btn"
                                                data-item_id="{{ $value->id }}" 
                                                data-user_id="{{ $value->user_id }}"
                                                data-company_id="{{ $value->company_id }}"
                                                data-leave_type_id="{{ $value->leave_type_id }}"
                                                data-leave_time="{{ $value->leave_time }}"
                                                data-start_date="{{ $value->start_date }}"
                                                data-end_date="{{ $value->end_date }}"
                                                data-reason="{{ $value->reason }}"
                                                data-status="{{ $value->status }}"
                                                title="Edit Item">
                                                Edit
                                            </button>
                                            @endcan
                                            @can('delete leave')
                                            <button class="btn-delete" onclick="confirmDelete('{{ $value->id }}', 'this leave')">
                                                Delete
                                            </button>
                                            @endcan
                                        </div>
                                    </td>
                                    @endcanany
                                </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="text-center py-8">
                                    <i class="fas fa-inbox fa-3x text-gray-400 mb-4"></i>
                                    <h4 class="text-gray-500 text-xl font-medium mb-2">No leave records found</h4>
                                    <p class="text-gray-400 mb-4">Try adjusting your filters or add a new leave.</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="p-3 border-t border-gray-200">                    
                    {{ $datas->appends(request()->all())->links() }}
                </div>
            </div>
        </div>
    </div>
    
    @include('leaves.create-modal')
    @include('leaves.edit-modal')
    @include('leaves.delete-modal')

    <div id="leaveReasonModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
        <div class="reason-modal-backdrop fixed inset-0 bg-black opacity-50"></div>
        <div class="reason-modal-panel relative z-10">
            <div class="reason-modal-head">
                <h3><i class="fas fa-align-left mr-2"></i>Leave Reason</h3>
                <button type="button" class="reason-modal-close"><i class="fas fa-times"></i></button>
            </div>
            <div class="reason-modal-body">
                <p id="leaveReasonEmployee" class="reason-employee"></p>
                <div id="leaveReasonText" class="reason-text"></div>
            </div>
            <div class="reason-modal-foot">
                <button type="button" class="reason-modal-close">Close</button>
            </div>
        </div>
    </div>

@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {

            // initialized select2
            $('.select2').select2();

            $('#user_id').on('change.leaveAutoLoad', function() {
                const form = document.getElementById('leaveFilterForm');
                const filterButton = form?.querySelector('.btn-submit');

                if (filterButton) {
                    filterButton.disabled = true;
                    filterButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading';
                }

                if (form) {
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                }
            });

            // Show/hide the Leave Time field based on the selected leave type's requires_time flag
            function toggleLeaveTimeField(prefix) {
                const selected = $('#' + prefix + '_leave_type_id option:selected');
                const requiresTime = selected.length && Number(selected.data('requires-time')) === 1;
                const isLateIn = selected.length && Number(selected.data('late-in')) === 1;
                const $wrapper = $('#' + prefix + '_leave_time_wrapper');
                $wrapper.toggleClass('hidden', !requiresTime);
                $('#' + prefix + '_leave_time').prop('required', requiresTime);
                $('#' + prefix + '_leave_time_label').text(isLateIn ? 'Expected Arrival Time' : 'Leave Time');
                if (!requiresTime) {
                    $('#' + prefix + '_leave_time').val('');
                }
            }

            // Show/hide the End Date field based on the Single Day / Multiple Days toggle
            function syncDurationMode(prefix) {
                const mode = $('input[name="' + prefix + '_duration_mode"]:checked').val() || 'multiple';
                const isSingle = mode === 'single';
                $('#' + prefix + '_end_date_wrapper').toggleClass('hidden', isSingle);
                $('#' + prefix + '_start_date_label').text(isSingle ? 'Date' : 'Start Date');
                if (isSingle) {
                    $('#' + prefix + '_end_date').val($('#' + prefix + '_start_date').val());
                }
            }

            // A leave type that requires a time (e.g. Early Leave) can only ever be a single day
            function updateDurationLock(prefix) {
                const selected = $('#' + prefix + '_leave_type_id option:selected');
                const requiresTime = selected.length && Number(selected.data('requires-time')) === 1;

                $('#' + prefix + '_duration_multiple').prop('disabled', requiresTime);
                if (requiresTime) {
                    $('#' + prefix + '_duration_single').prop('checked', true);
                }
                syncDurationMode(prefix);
            }

            $('#create_leave_type_id').on('change', function() {
                toggleLeaveTimeField('create');
                updateDurationLock('create');
            });
            $('#edit_leave_type_id').on('change', function() {
                toggleLeaveTimeField('edit');
                updateDurationLock('edit');
            });

            $('input[name="create_duration_mode"]').on('change', function() {
                syncDurationMode('create');
            });
            $('input[name="edit_duration_mode"]').on('change', function() {
                syncDurationMode('edit');
            });

            $('#create_start_date').on('input change', function() {
                syncDurationMode('create');
            });
            $('#edit_start_date').on('input change', function() {
                syncDurationMode('edit');
            });

            updateDurationLock('create');

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // Show create modal
            $('.create-new-btn').click(function() {
                $('#createModal').removeClass('hidden');
            });

            // Show edit modal
            $('.edit-item-btn').click(function() {
                const item_id = $(this).data('item_id');
                const user_id = $(this).data('user_id');
                const company_id = $(this).data('company_id');
                const leave_type_id = $(this).data('leave_type_id');
                const leave_time = $(this).data('leave_time');
                const start_date = $(this).data('start_date');
                const end_date = $(this).data('end_date');
                const reason = $(this).data('reason');
                const status = $(this).data('status');

                // Set values in the edit form
                $('#editItemId').val(item_id);
                $('#edit_user_id').val(user_id).trigger('change');
                $('#edit_company_id').val(company_id).trigger('change');
                $('#edit_leave_type_id').val(leave_type_id).trigger('change');
                $('#edit_leave_time').val(leave_time || '');
                $('#edit_start_date').val(start_date);
                $('#edit_end_date').val(end_date);
                $('#' + (start_date === end_date ? 'edit_duration_single' : 'edit_duration_multiple')).prop('checked', true);
                updateDurationLock('edit');
                $('#edit_reason').val(reason);
                $('#edit_status').val(status).trigger('change'); 
                $('#editModal').removeClass('hidden'); 
            });

            $('.view-reason-btn').on('click', function() {
                const employee = this.dataset.employee || 'Employee';
                const reason = (this.dataset.reason || '').trim() || 'No reason was provided.';

                $('#leaveReasonEmployee').text('Submitted by ' + employee);
                $('#leaveReasonText').text(reason);
                $('#leaveReasonModal').removeClass('hidden').addClass('flex');
            });

            $('.reason-modal-close, .reason-modal-backdrop').on('click', function() {
                $('#leaveReasonModal').addClass('hidden').removeClass('flex');
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

            // Close success alert
            $('.close-btn').click(function() {
                $(this).closest('.alert').addClass('hidden');
            });

            // Create state form submission
            $('#createSubmit').click(function(e) {
                e.preventDefault();
                syncDurationMode('create');
                console.log(validateCreateForm());
                if (validateCreateForm()) {
                    let formData = new FormData($('#createForm')[0]);
                    $.ajax({
                        url: $('#createForm').attr('action'),
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function (response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Done',
                                    text: 'Data created successfully!'
                                });
                                $('#createModal').addClass('hidden');
                                $('#createForm')[0].reset();
                                toggleLeaveTimeField('create');
                                updateDurationLock('create');
                                setTimeout(() => window.location.reload(), 800);
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Oops...',
                                    text: response.message || 'Something went wrong.'
                                });
                            }
                        },
                        error: function (xhr) {
                            console.error(xhr.responseText);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: 'Failed to create data.'
                            });
                        }
                    });                                     
                }
            });

            // Edit state form submission
            $('#editSubmit').click(function() {
                syncDurationMode('edit');
                if (validateEditForm()) {
                    let formData = new FormData($('#editForm')[0]);
                    $.ajax({
                        url: $(this).data('action'),
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function (response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: "success",
                                    title: "Done",
                                    text: "Data updated successfully!",
                                });
                                $('#editModal').addClass('hidden');
                                setTimeout(() => window.location.reload(), 800);
                            } else {
                                Swal.fire({
                                    icon: "error",
                                    title: "Oops...",
                                    text: response.message || "Update failed.",
                                });
                            }
                        },
                        error: function (xhr) {
                            console.error('❌ Error:', xhr.responseText);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: 'Something went wrong!'
                            });
                        }
                    }); 
                }
            });

            // Delete confirmation
            $('#confirmDeleteBtn').click(function() {
                const dataId = $(this).data('item-id');
                const deleteUrl = $(this).data('action');
                $.ajax({
                    url: deleteUrl,
                    method: 'DELETE',
                    data: {
                        item_id: dataId,
                    },
                    success: function (response) {
                        console.log(response);
                        if (response.success) {
                            Swal.fire({
                                icon: "success",
                                title: "Done",
                                text: "Data deleted successfully!",
                            });
                            $('#deleteModal').addClass('hidden');
                            console.log('trigger reload');                                
                            setTimeout(() => {
                                window.location.reload();
                            }, 500);
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Opps...",
                                text: response.message,
                            });
                        }
                    },
                    error: function (xhr) {
                        console.error('❌ Error:', xhr.responseText);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'Something went wrong!'
                        });
                    }
                });  
            });

            $('.quick-approve-btn').on('click', function() {
                const button = this;
                const employee = button.dataset.employee || 'this employee';

                Swal.fire({
                    icon: 'question',
                    title: 'Approve leave request?',
                    text: 'This will approve the pending request for ' + employee + '.',
                    showCancelButton: true,
                    confirmButtonColor: '#16a34a',
                    confirmButtonText: 'Yes, approve',
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                    $.post(button.dataset.url)
                        .done((response) => {
                            Swal.fire({ icon:'success', title:'Approved', text:response.message, timer:1200, showConfirmButton:false });
                            setTimeout(() => window.location.reload(), 700);
                        })
                        .fail((xhr) => {
                            button.disabled = false;
                            button.innerHTML = '<i class="fas fa-check mr-1"></i>Approve';
                            Swal.fire({ icon:'error', title:'Unable to approve', text:xhr.responseJSON?.message || 'Something went wrong.' });
                        });
                });
            });
        });

        function select2SetValueNoEvent(selectId, value) {
            var $select = $(selectId);

            // Set the underlying value
            $select.val(value);

            // Find the selected option text
            var text = $select.find('option:selected').text() || '';

            // Update the visible Select2 box manually
            $select.data('select2').$container.find('.select2-selection__rendered').text(text);
        }
        
        // Form validation functions
        function validateCreateForm() {
            let isValid = true;
            
            // Reset error messages
            $('#createForm .error-message').addClass('hidden');
            $('#createForm .form-select, #createForm .form-input').removeClass('border-red-500');                                                
                        
            if (!$('#create_start_date').val().trim()) {
                $('#create_start_date').next('.error-message').removeClass('hidden');
                $('#create_start_date').addClass('border-red-500');
                isValid = false;
            }                    
            if (!$('#create_end_date').val().trim()) {
                $('#create_end_date').next('.error-message').removeClass('hidden');
                $('#create_end_date').addClass('border-red-500');
                isValid = false;
            }                                                                                      
            if (!$('#create_user_id').val() || ($('#create_user_id').val() == '') || ($('#create_user_id').val() == null)) {
                $('#create_user_msg').removeClass('hidden');
                isValid = false;
            }    
            if (!$('#create_company_id').val() || ($('#create_company_id').val() == '') || ($('#create_company_id').val() == null)) {
                $('#create_company_msg').removeClass('hidden');
                isValid = false;
            }    
            if (!$('#create_leave_type_id').val() || ($('#create_leave_type_id').val() == '') || ($('#create_leave_type_id').val() == null)) {
                $('#create_leave_type_msg').removeClass('hidden');
                isValid = false;
            }
            if ($('#create_leave_time').prop('required') && !$('#create_leave_time').val()) {
                $('#create_leave_time').next('.error-message').removeClass('hidden');
                $('#create_leave_time').addClass('border-red-500');
                isValid = false;
            }

            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            
            // Reset error messages
            $('#editForm .error-message').addClass('hidden');
            $('#editForm .form-select, #editForm .form-input').removeClass('border-red-500');
            
            if (!$('#edit_start_date').val().trim()) {
                $('#edit_start_date').next('.error-message').removeClass('hidden');
                $('#edit_start_date').addClass('border-red-500');
                isValid = false;
            }                    
            if (!$('#edit_end_date').val().trim()) {
                $('#edit_end_date').next('.error-message').removeClass('hidden');
                $('#edit_end_date').addClass('border-red-500');
                isValid = false;
            }                                        
            if (!$('#edit_user_id').val() || ($('#edit_user_id').val() == '') || ($('#edit_user_id').val() == null)) {
                $('#edit_user_msg').removeClass('hidden');
                isValid = false;
            }    
            if (!$('#edit_company_id').val() || ($('#edit_company_id').val() == '') || ($('#edit_company_id').val() == null)) {
                $('#edit_company_msg').removeClass('hidden');
                isValid = false;
            }    
            if (!$('#edit_leave_type_id').val() || ($('#edit_leave_type_id').val() == '') || ($('#edit_leave_type_id').val() == null)) {
                $('#edit_leave_type_msg').removeClass('hidden');
                isValid = false;
            }
            if ($('#edit_leave_time').prop('required') && !$('#edit_leave_time').val()) {
                $('#edit_leave_time').next('.error-message').removeClass('hidden');
                $('#edit_leave_time').addClass('border-red-500');
                isValid = false;
            }

            return isValid;
        }

        // Reset create form
        function resetCreateForm() {
            $('#createForm')[0].reset();
            $('#createForm .error-message').addClass('hidden');
            $('#createForm .form-select, #createForm .form-input').removeClass('border-red-500');
        }

        // Delete confirmation
        function confirmDelete(id, name=null) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            $('#deleteModal').removeClass('hidden');
        }

    </script>
@endsection
