@extends('layout.app')
@section('meta-information')
    <title>Manage Users</title>
@endsection
@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @include('layout.table-design')
    <style>
        .usr-shell{background:#f5f7fb;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,.06)}
        .usr-head{background:linear-gradient(120deg,#0f766e,#075985);padding:18px 22px;color:#fff;display:flex;align-items:center;justify-content:space-between;gap:16px}
        .usr-head h1{font-size:18px;font-weight:800;margin:0}.usr-head p{font-size:11px;color:#d5f5f1;margin:3px 0 0}.usr-head-actions{display:flex;align-items:center;gap:8px}.usr-add,.usr-print{display:inline-flex;align-items:center;gap:7px;padding:10px 14px;border-radius:9px;font-size:12px;font-weight:800}.usr-add{background:#fff;color:#0f766e;box-shadow:0 4px 12px #064e3b33}.usr-print{border:1px solid #ffffff66;background:#ffffff17;color:#fff}.usr-print:hover{background:#ffffff28}
        .usr-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;padding:16px 18px 0}.usr-stat{display:flex;align-items:center;gap:12px;padding:14px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;color:#64748b;transition:.15s}.usr-stat:hover{transform:translateY(-1px);border-color:#99cfc8}.usr-stat.is-active{border-color:#0f766e;box-shadow:0 0 0 2px #0f766e18}.usr-stat i{display:grid;place-items:center;width:38px;height:38px;border-radius:10px;background:#ecfdf5;color:#0f766e}.usr-stat.resigned i{background:#fff1f2;color:#e11d48}.usr-stat.inactive i{background:#f1f5f9;color:#64748b}.usr-stat.all i{background:#eff6ff;color:#2563eb}.usr-stat b{display:block;font-size:20px;line-height:1;color:#172033}.usr-stat span{font-size:11px;font-weight:700}
        .usr-filter{margin:14px 18px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px}.usr-filter-grid{display:grid;grid-template-columns:1.2fr 1fr 1fr 1fr auto;gap:10px;align-items:end}.usr-filter label{display:block;font-size:10px;font-weight:700;color:#64748b;margin-bottom:5px}.usr-filter input,.usr-filter select{width:100%;height:39px;border:1px solid #d8e0eb;border-radius:8px;padding:8px 10px;background:#fff;font-size:12px}.usr-filter-actions{display:flex;gap:7px}.usr-filter button,.usr-filter-clear{height:39px;display:inline-flex;align-items:center;gap:6px;padding:0 13px;border-radius:8px;font-size:11px;font-weight:700}.usr-filter button{background:#0f766e;color:#fff}.usr-filter-clear{border:1px solid #d8e0eb;color:#64748b;background:#fff}
        .usr-table-wrap{margin:0 18px 16px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:auto}.usr-table{width:100%;border-collapse:collapse;font-size:12px}.usr-table th{padding:11px 14px;background:#f8fafc;color:#718096;font-size:9px;text-transform:uppercase;letter-spacing:.55px;text-align:left}.usr-table td{padding:12px 14px;border-top:1px solid #edf1f5;color:#334155;vertical-align:middle}.usr-table tbody tr:hover{background:#f8fffe}.usr-person{display:flex;align-items:center;gap:10px}.usr-person-link{display:inline-flex;border-radius:9px;transition:.15s}.usr-person-link:hover .usr-person b,.usr-person-link:focus .usr-person b{color:#0f766e;text-decoration:underline}.usr-person-link:focus{outline:2px solid #99d5cf;outline-offset:3px}.usr-avatar{width:34px;height:34px;border-radius:10px;background:#e6f5f3;color:#0f766e;display:grid;place-items:center;font-weight:800}.usr-person b{display:block;color:#172033}.usr-person small{font-size:10px;color:#64748b}.usr-badge{display:inline-flex;padding:4px 8px;border-radius:999px;font-size:9px;font-weight:800;text-transform:uppercase}.usr-badge.active{background:#dcfce7;color:#15803d}.usr-badge.resigned{background:#ffe4e6;color:#be123c}.usr-badge.inactive{background:#e2e8f0;color:#475569}.usr-actions{display:flex;gap:6px}.usr-action{display:grid;place-items:center;height:30px;min-width:30px;padding:0 9px;border:1px solid #dbe3ee;border-radius:7px;color:#475569}.usr-action:hover{border-color:#0f766e;color:#0f766e;background:#f0fdfa}.usr-pagination{padding:12px 18px;border-top:1px solid #e2e8f0;background:#fff}
        @media(max-width:1000px){.usr-filter-grid{grid-template-columns:1fr 1fr}.usr-stats{grid-template-columns:1fr 1fr}.usr-filter-actions{grid-column:1/-1}}@media(max-width:600px){.usr-head{align-items:flex-start}.usr-stats{grid-template-columns:1fr}.usr-filter-grid{grid-template-columns:1fr}.usr-table-wrap,.usr-filter{margin-left:10px;margin-right:10px}}
    </style>
@endsection
@section('main-content')
    <!-- States Table -->
    <div class="usr-shell">
        <div class="states-table-container">
            <div class="usr-head">
                <div><h1><i class="fas fa-users mr-2"></i>{{ $role === 'employee' ? 'Employee Management' : 'User Management' }}</h1><p>Search, review and manage employee access and employment status</p></div>
                <div class="usr-head-actions">
                    @if($role === 'employee')
                        <a href="{{ route('role.user.print', ['role' => request()->route('role')] + request()->except('page')) }}" target="_blank" class="usr-print"><i class="fas fa-print"></i>Print List</a>
                    @endif
                    @can('create users')
                    <a href="{{ route('role.user.create', ['role' => request()->route('role')]) }}" class="usr-add">
                        <i class="fas fa-plus mr-2"></i>Add New {{ $role === 'employee' ? 'Employee' : 'User' }}
                    </a>
                    @endcan
                </div>
            </div>

            @php
                $statusQuery = request()->except(['status', 'page']);
                $userLabel = $role === 'employee' ? 'Employees' : 'Users';
            @endphp
            <div class="usr-stats">
                @foreach([
                    'active' => ["Active {$userLabel}",'fa-user-check'],
                    'resigned' => ["Resigned {$userLabel}",'fa-user-minus'],
                    'inactive' => ["Inactive {$userLabel}",'fa-user-clock'],
                    'all' => ["All {$userLabel}",'fa-users'],
                ] as $key => [$label,$icon])
                    <a class="usr-stat {{ $key }} {{ $status === $key ? 'is-active' : '' }}" href="{{ route('role.user.index', ['role' => request()->route('role')] + array_merge($statusQuery, ['status' => $key])) }}">
                        <i class="fas {{ $icon }}"></i><div><b>{{ $statusCounts[$key] }}</b><span>{{ $label }}</span></div>
                    </a>
                @endforeach
            </div>

            <div class="states-table-content">
                <!-- Filter Form -->
                <div class="usr-filter">
                    <form method="GET" action="{{ route('role.user.index', ['role' => request()->route('role')]) }}" class="usr-filter-grid">
                        <input type="hidden" name="status" value="{{ $status }}">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Name</label>
                            <input 
                                type="text" 
                                name="name" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Search by name..."
                                value="{{ request('name') }}"
                            />
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                            <input 
                                type="text" 
                                name="phone" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Search by phone..."
                                value="{{ request('phone') }}"
                            />
                            <input 
                                type="hidden" 
                                name="role" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 mt-2"
                                placeholder="Search by role..."
                                value="{{ request('role') }}"
                            />
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Company</label>
                            <select 
                                name="company" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="">All Companies</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" {{ request('company') == $company->id ? 'selected' : '' }}>
                                        {{ $company->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                            <select 
                                name="department" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="">All Departments</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}" {{ request('department') == $department->id ? 'selected' : '' }}>
                                        {{ $department->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="usr-filter-actions">
                            <button 
                                type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition duration-200 inline-flex items-center"
                            >
                                <i class="fas fa-search mr-2"></i>Search
                            </button>
                            <a class="usr-filter-clear" href="{{ route('role.user.index', ['role' => request()->route('role'), 'status' => $status]) }}"><i class="fas fa-rotate-left"></i>Reset</a>
                        </div>
                    </form>
                </div>

                <!-- Table with Data -->
                <div class="usr-table-wrap">
                    <table class="usr-table">
                        <thead class="bg-gray-50">
                            <tr>
                                <th style="padding-left: 20px"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    SL</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Phone</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Role</th>
                                {{-- <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Address</th> --}}
                                
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status</th>
                                @canany(['edit users'])
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                @endcanany
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($users as $key => $value)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap" style="padding-left: 20px">
                                        <strong>{{ ($users->currentPage() - 1) * $users->perPage() + $key + 1 }}</strong>
                                    </td>
                                    <td>
                                        <a class="usr-person-link" title="Open employee account" href="{{ route('role.user.summary', ['role' => request()->route('role'), 'user' => $value->id, 'return_url' => request()->fullUrl()]) }}">
                                            <span class="usr-person"><span class="usr-avatar">{{ strtoupper(mb_substr($value->name,0,1)) }}</span><span><b>{{ $value->name }}</b><small>{{ $value->employee_id_no ?: 'No employee ID' }}</small></span></span>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->phone }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->email }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->getRoleNames()->first() }}
                                    </td>
                                    {{-- <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->address }}
                                    </td> --}}
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if ($value->status === 'active')
                                            <span class="usr-badge active">Active</span>
                                        @elseif($value->status === 'inactive')
                                            <span class="usr-badge inactive">Inactive</span>
                                        @elseif($value->status === 'resigned')
                                            <span class="usr-badge resigned">Resigned</span>
                                        @endif
                                    </td>
                                    @canany(['edit users'])
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="usr-actions">
                                            @if($value->hasRole('employee'))
                                                <a href="{{ route('role.user.summary', [
                                                    'role' => request()->route('role'),
                                                    'user' => $value->id,
                                                    'return_url' => request()->fullUrl(),
                                                ]) }}"
                                                    class="usr-action"
                                                    title="Profile Summary">
                                                    <i class="fas fa-user-circle"></i>
                                                </a>
                                            @endif
                                            <a href="{{ route('role.user.edit', [
                                                'role' => request()->route('role'),
                                                'user' => $value->id,
                                                'return_url' => request()->fullUrl(),
                                            ]) }}"
                                                class="usr-action" title="Edit user"><i class="fas fa-pen"></i></a>
                                        </div>
                                    </td>
                                    @endcanany
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center py-8">
                                        <i class="fas fa-inbox fa-3x text-gray-400 mb-4"></i>
                                        <h4 class="text-gray-500 text-xl font-medium mb-2">No data found</h4>
                                        <p class="text-gray-400 mb-4">Try filtering with different datas.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="usr-pagination">
                    {{ $users->appends(request()->all())->links() }}
                </div>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
        <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
        <div class="modal-container bg-white w-11/12 md:max-w-3xl mx-auto rounded shadow-lg z-50">
            <div class="modal-content flex flex-col py-4 text-left px-6" id="appendEditHtml">

            </div>
        </div>
    </div>
@endsection
@section('js')
@endsection
