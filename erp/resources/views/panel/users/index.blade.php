@extends('layout.app')
@section('meta-information')
    <title>Manage Users</title>
@endsection
@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @include('layout.table-design')
@endsection
@section('main-content')
    <!-- States Table -->
    <div class="states-table bg-white rounded-lg shadow-md overflow-hidden">
        <div class="states-table-container">
            <div class="states-table-header bg-blue-600 px-6 py-4 flex justify-between items-center">
                <h2 class="states-table-title text-white text-xl font-semibold" style="color: white">
                    <i class="fas fa-list mr-2"></i>User List
                </h2>
                @can('create users')
                <a href="{{ route('role.user.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="btn btn-primary create-new-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add New User
                </a>
                @endcan
            </div>

            <div class="states-table-content">
                <!-- Filter Form -->
                <div class="bg-gray-50 border-b border-gray-200 p-6">
                    <form method="GET" action="{{ route('role.user.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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
                        
                        <div class="md:col-span-2 lg:col-span-4 flex gap-2">
                            <button 
                                type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition duration-200 inline-flex items-center"
                            >
                                <i class="fas fa-search mr-2"></i>Search
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Table with Data -->
                <div class="table-responsive overflow-x-auto" style="padding: 15px">
                    <table class="table table-hover min-w-full divide-y divide-gray-200">
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
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->name }}</td>
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
                                            <span class="badge text-white bg-green-500 px-2 py-1 rounded-full text-xs">
                                                Active
                                            </span>
                                        @elseif($value->status === 'resigned')
                                            <span class="badge text-white bg-red-500 px-2 py-1 rounded-full text-xs">
                                                Resigned
                                            </span>
                                        @endif
                                    </td>
                                    @canany(['edit users'])
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="btn-group btn-group-sm flex space-x-1">
                                            @if($value->hasRole('employee'))
                                                <a href="{{ route('role.user.summary', [
                                                    'role' => Str::slug(Auth::user()->getRoleNames()->first()),
                                                    'user' => $value->id,
                                                ]) }}"
                                                    class="btn btn-outline-indigo border border-indigo-500 text-indigo-500 hover:bg-indigo-500 hover:text-white px-3 py-1 rounded-md transition duration-200"
                                                    title="Profile Summary">
                                                    <i class="fas fa-user-circle"></i>
                                                </a>
                                            @endif
                                            <a href="{{ route('role.user.edit', [
                                                'role' => Str::slug(Auth::user()->getRoleNames()->first()),
                                                'user' => $value->id,
                                            ]) }}"
                                                class="btn btn-outline-primary edit-item-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200">Edit</a>
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
                <div class="p-3 border-t border-gray-200">
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
