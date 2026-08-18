@extends('layout.app')
@section('meta-information')
    <title>Employee Document Vault</title>
@endsection

@section('main-content')
    <div class="states-table bg-white rounded-lg shadow-md overflow-hidden">
        <div class="states-table-container">
            <div class="states-table-header bg-blue-600 px-6 py-4 flex justify-between items-center">
                <h2 class="states-table-title text-white text-xl font-semibold" style="color: white">
                    <i class="fas fa-folder-open mr-2"></i>Employee Document Vault
                </h2>
                <a href="{{ route('role.user.index', ['role' => request()->route('role')]) }}"
                    class="btn btn-primary bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>User List
                </a>
            </div>

            <div class="states-table-content">
                <div class="bg-gray-50 border-b border-gray-200 p-6">
                    <form method="GET" action="{{ route('role.user.documents', ['role' => request()->route('role')]) }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Name</label>
                            <input type="text" name="name" value="{{ request('name') }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Search by name..." />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                            <input type="text" name="phone" value="{{ request('phone') }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Search by phone..." />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Company</label>
                            <select name="company"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
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
                            <select name="department"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Departments</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}" {{ request('department') == $department->id ? 'selected' : '' }}>
                                        {{ $department->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="md:col-span-2 lg:col-span-4 flex gap-2">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition duration-200 inline-flex items-center">
                                <i class="fas fa-search mr-2"></i>Search
                            </button>
                        </div>
                    </form>
                </div>

                <div class="table-responsive overflow-x-auto" style="padding: 15px">
                    <table class="table table-hover min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SL</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Files</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Upload / Update</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($users as $key => $employee)
                                <tr>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <strong>{{ ($users->currentPage() - 1) * $users->perPage() + $key + 1 }}</strong>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <div class="font-semibold text-gray-900">{{ $employee->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $employee->email }}</div>
                                        <div class="text-xs text-gray-500">{{ $employee->phone }}</div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            {{ $employee->profile?->department?->name ?? 'N/A' }}
                                            / {{ $employee->profile?->designation?->name ?? 'N/A' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm">
                                        <div class="space-y-2">
                                            <div>
                                                <span class="font-medium">Photo:</span>
                                                @if($employee->employeeDocument?->passport_size_image)
                                                    <a class="text-blue-600 hover:underline ml-1" href="{{ asset($employee->employeeDocument->passport_size_image) }}" target="_blank">View</a>
                                                @else
                                                    <span class="text-gray-400 ml-1">Not uploaded</span>
                                                @endif
                                            </div>
                                            <div>
                                                <span class="font-medium">NID:</span>
                                                @if($employee->employeeDocument?->nid)
                                                    <a class="text-blue-600 hover:underline ml-1" href="{{ asset($employee->employeeDocument->nid) }}" target="_blank">View</a>
                                                @else
                                                    <span class="text-gray-400 ml-1">Not uploaded</span>
                                                @endif
                                            </div>
                                            <div>
                                                <span class="font-medium">Appointment Letter:</span>
                                                @if($employee->employeeDocument?->appointment_letter)
                                                    <a class="text-blue-600 hover:underline ml-1" href="{{ asset($employee->employeeDocument->appointment_letter) }}" target="_blank">View</a>
                                                @else
                                                    <span class="text-gray-400 ml-1">Not uploaded</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <form method="POST" enctype="multipart/form-data"
                                            action="{{ route('role.user.documents.update', ['role' => request()->route('role'), 'user' => $employee->id]) }}"
                                            class="space-y-2">
                                            @csrf
                                            <div>
                                                <label class="block text-xs text-gray-600 mb-1">Passport Size Image (jpg/png/webp, max 2MB)</label>
                                                <input type="file" name="passport_size_image" accept=".jpg,.jpeg,.png,.webp"
                                                    class="w-full text-sm border border-gray-300 rounded-md p-1" />
                                            </div>
                                            <div>
                                                <label class="block text-xs text-gray-600 mb-1">NID (jpg/png/pdf, max 4MB)</label>
                                                <input type="file" name="nid" accept=".jpg,.jpeg,.png,.pdf"
                                                    class="w-full text-sm border border-gray-300 rounded-md p-1" />
                                            </div>
                                            <div>
                                                <label class="block text-xs text-gray-600 mb-1">Appointment Letter (pdf/doc/docx, max 5MB)</label>
                                                <input type="file" name="appointment_letter" accept=".pdf,.doc,.docx"
                                                    class="w-full text-sm border border-gray-300 rounded-md p-1" />
                                            </div>
                                            <button type="submit"
                                                class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-3 py-1 rounded-md transition duration-200">
                                                Save Documents
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-8">
                                        <i class="fas fa-inbox fa-3x text-gray-400 mb-4"></i>
                                        <h4 class="text-gray-500 text-xl font-medium mb-2">No employee found</h4>
                                        <p class="text-gray-400">Try searching with different filters.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-3 border-t border-gray-200">
                    {{ $users->appends(request()->all())->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
