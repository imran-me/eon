@extends('layout.app')
@section('meta-information')
    <title>Workspace Users</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .mt-0{
        margin-top: 0 !important;
    }
    .modal {
        transition: opacity 0.25s ease;
    }
    .modal-backdrop {
        background-color: rgba(0, 0, 0, 0.5);
    }
    .admin-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .admin-stats-grid .admin-stat-card {
        border-radius: 6px;
        padding: 1.5rem;
        color: white;
        position: relative;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .admin-stats-grid .admin-stat-card.primary {
        /* background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); */
        background: #f4f4f4;
        color: #764ba2;
    }
    
    .admin-stats-grid .admin-stat-card.success {
        /* background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); */
        background: #f4f4f4;
        color: #3aa31f;
    }
    
    .admin-stats-grid .admin-stat-card.warning {
        /* background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); */
        background: #f4f4f4;
        color: #f5576c;
    }
    
    .admin-stats-grid .admin-stat-card.info {
        /* background: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%); */
        background: #f4f4f4;
        color: #129fa7;
    }
    
    .admin-stats-grid .admin-stat-card .position-relative {
        position: relative;
    }
    
    .admin-stats-grid .admin-stat-card .admin-stat-value {
        font-size: 2rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
    }
    
    .admin-stats-grid .admin-stat-card .admin-stat-label {
        font-size: 0.9rem;
        opacity: 0.9;
    }
    
    .admin-stats-grid .admin-stat-card .admin-stat-icon {
        position: absolute;
        top: 0;
        right: 0;
        font-size: 1.5rem;
        opacity: 0.7;
    }
    
    .states-table {
        margin-top: 2rem;
    }
    
    .states-table .states-table-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }
    
    .states-table .states-table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        border-bottom: 1px solid #e9ecef;
    }
    
    .states-table .states-table-header .states-table-title {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        color: #333;
    }
    
    .states-table .states-table-header .btn {
        border-radius: 8px;
        padding: 0.5rem 1rem;
        font-weight: 500;
    }
    
    .states-table .states-table-content {
        padding: 0;
    }
    
    .states-table .states-table-content .alert {
        margin: 1rem;
        border-radius: 8px;
        border: none;
    }
    
    .states-table .states-table-content .alert-success {
        background-color: #d4edda;
        color: #155724;
    }
    
    .states-table .states-table-content .text-center {
        padding: 3rem 1rem;
    }
    
    .states-table .states-table-content .text-center .fa-inbox {
        opacity: 0.5;
    }
    
    .states-table .states-table-content .table-responsive {
        overflow-x: auto;
    }
    
    .states-table .states-table-content .table {
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .states-table .states-table-content .table thead th {
        background-color: #f8f9fa;
        border-bottom: 2px solid #e9ecef;
        padding: 1rem 0.75rem;
        font-weight: 600;
        color: #495057;
    }
    
    .states-table .states-table-content .table tbody td {
        padding: 1rem 0.75rem;
        vertical-align: middle;
        border-bottom: 1px solid #e9ecef;
    }
    
    .states-table .states-table-content .table tbody tr:hover {
        background-color: #f8f9fa;
    }
    
    .states-table .states-table-content .badge {
        font-size: 0.75rem;
        padding: 0.375rem 0.75rem;
        border-radius: 6px;
        font-weight: 500;
    }
    
    .states-table .states-table-content .badge.bg-light {
        color: #6c757d !important;
        background-color: #f8f9fa !important;
    }
    
    .states-table .states-table-content .badge.bg-info {
        background-color: #17a2b8 !important;
    }
    
    .states-table .states-table-content .badge.bg-success {
        background-color: #28a745 !important;
    }
    
    .states-table .states-table-content .badge.bg-secondary {
        background-color: #6c757d !important;
    }

    .states-table .states-table-content .badge.bg-warning {
        background-color: orange !important;
    }
    
    .states-table-header {
        background: linear-gradient(90deg, #1e3a8a 0%, #1e40af 100%);
        color: white
    }

    .states-table .states-table-content .btn-group {
        border-radius: 6px;
        overflow: hidden;
    }
    
    .states-table .states-table-content .btn-group .btn {
        border-radius: 0;
        padding: 0.375rem 0.75rem;
    }
    
    .states-table .states-table-content .btn-group .btn:first-child {
        border-top-left-radius: 6px;
        border-bottom-left-radius: 6px;
    }
    
    .states-table .states-table-content .btn-group .btn:last-child {
        border-top-right-radius: 6px;
        border-bottom-right-radius: 6px;
    }
    
    .states-table .states-table-content .pagination {
        margin-bottom: 0;
        padding: 1rem;
    }
    
    .states-table .states-table-content .pagination .page-link {
        border-radius: 6px;
        margin: 0 0.2rem;
        border: 1px solid #dee2e6;
        color: #007bff;
    }
    
    .states-table .states-table-content .pagination .page-item.active .page-link {
        background-color: #007bff;
        border-color: #007bff;
    }
    
    @media (max-width: 768px) {
        .admin-stats-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .states-table .states-table-header {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
        }
        
        .states-table .states-table-header .btn {
            width: 100%;
        }
    }
</style>
<style>
    .filter-container {
        margin: 15px 15px 0 15px;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .filter-container .filter-header {
        background-color: #f8f9fa;
        padding: 16px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-left: 4px solid #3b82f6;
        transition: background-color 0.3s;
    }

    .filter-container .filter-header:hover {
        background-color: #e9ecef;
    }

    .filter-container .filter-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
    }

    .filter-container .filter-header .toggle-icon {
        transition: transform 0.3s;
    }

    .filter-container .filter-header.active .toggle-icon {
        transform: rotate(180deg);
    }

    .filter-container .filter-content {
        background-color: white;
        padding: 0;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out, padding 0.3s ease-out;
    }

    .filter-container .filter-content.active {
        padding: 20px;
        max-height: 500px;
    }

    .filter-container .filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 16px;
    }

    .filter-container .filter-group {
        flex: 1;
        min-width: 200px;
    }

    .filter-container .filter-group label {
        display: block;
        margin-bottom: 6px;
        font-weight: 500;
        color: #374151;
    }

    .filter-container .filter-group select,
    .filter-container .filter-group input {
        width: 100%;
        padding: 10px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
        transition: border-color 0.3s;
    }

    .filter-container .filter-group select:focus,
    .filter-container .filter-group input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .filter-container .filter-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 20px;
    }

    .filter-container .filter-actions button {
        padding: 10px 20px;
        border-radius: 6px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
    }

    .filter-container .filter-actions .apply-btn {
        background-color: #3b82f6;
        color: white;
        border: none;
    }

    .filter-container .filter-actions .apply-btn:hover {
        background-color: #2563eb;
    }

    .filter-container .filter-actions .reset-btn {
        background-color: #f8f9fa;
        color: #6b7280;
        border: 1px solid #d1d5db;
    }

    .filter-container .filter-actions .reset-btn:hover {
        background-color: #e5e7eb;
    }
    .select2-container .select2-selection--single {        
        height: 42px;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 40px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 42px;
        position: absolute;
        top: 1px;
        right: 3px;
        width: 20px;
    }
    /* Example: change active page background and text */
    span [aria-current="page"] span{
        background-color: #2563eb !important;
        background: #2563eb !important;
        color: white;
        border-color: #2563eb;
    }
</style>
@endsection
@section('main-content')
    <!-- States Table -->    
    <div class="states-table bg-white rounded-lg shadow-md overflow-hidden mt-0">
        <div class="states-table-container">
            <div class="states-table-header bg-blue-600 px-6 py-4 flex justify-between items-center">
                <h2 class="states-table-title text-white text-xl font-semibold" style="color: white">
                    <i class="fas fa-list mr-2"></i>Workspace User List
                </h2>
                <button class="btn btn-primary create-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add Workspace User
                </button>
            </div>

            <div class="states-table-content">
                <!-- Success Alert -->
                <form action="" method="get">
                    <div class="filter-container">
                        <div class="filter-header">
                            <h3><i class="fas fa-filter mr-2"></i>Filter Options</h3>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="filter-content">
                            <div class="filter-row">
                                <div class="filter-group">
                                    <label for="filter_workspace" class="block text-sm font-medium text-gray-700 mb-2">Workspace</label>
                                    <select id="filter_workspace" name="workspace_id" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        <option value="">All Workspaces</option>
                                        @foreach($companies as $workspace)
                                            <option value="{{ $workspace->id }}" {{ request('workspace_id') == $workspace->id ? 'selected' : '' }}>
                                                {{ $workspace->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="filter-group">
                                    <label for="filter_project" class="block text-sm font-medium text-gray-700 mb-2">Project</label>
                                    <select id="filter_project" name="project_id" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        <option value="">All Projects</option>
                                        @foreach($projects as $project)
                                            <option value="{{ $project->id }}" {{ request('project_id') == $project->id ? 'selected' : '' }}>
                                                {{ $project->project_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- <div class="filter-group">
                                    <label for="filter_role" class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                                    <select id="filter_role" name="role" class="form-select w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        <option value="">All Roles</option>
                                        <option value="owner" {{ request('role') == 'owner' ? 'selected' : '' }}>Owner</option>
                                        <option value="admin" {{ request('role') == 'admin' ? 'selected' : '' }}>Admin</option>
                                        <option value="member" {{ request('role') == 'member' ? 'selected' : '' }}>Member</option>
                                    </select>
                                </div> --}}
                                
                                <div class="filter-actions filter-group" style="margin: 0; flex-direction:column">
                                    <div>                                        
                                        <button type="button" class="btn-sm reset-btn">Reset</button>
                                        <button type="submit" class="btn-sm apply-btn">Apply Filters</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Table with Data -->                 
                <div class="table-responsive overflow-x-auto" style="padding: 15px">
                    <table class="table table-hover min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th style="width: 5%" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sl</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Workspace</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Users & Roles</th>                                                                                                                  
                                <th style="width: 15%" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created on</th>   
                                <th style="width: 10%" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($datas as $key => $value)                       
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap" style="padding-left: 20px">
                                        <strong>{{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}</strong>
                                    </td> 
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->workspace->name ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <strong>{{ $value->project->project_name ?? 'N/A' }}</strong>
                                    </td>
                                    {{-- <td class="px-6 py-4">
                                        <div class="space-y-1">
                                            @if($value->owners)
                                                @foreach(explode(', ', $value->owners) as $owner)
                                                    @if($owner)
                                                    <span class="inline-block badge text-white bg-green-500 px-2 py-1 rounded-full text-xs mr-1 mb-1">
                                                        👑 {{ $owner }}
                                                    </span>
                                                    @endif
                                                @endforeach
                                            @endif
                                            @if($value->admins)
                                                @foreach(explode(', ', $value->admins) as $admin)
                                                    @if($admin)
                                                    <span class="inline-block badge text-white bg-blue-500 px-2 py-1 rounded-full text-xs mr-1 mb-1">
                                                        🔧 {{ $admin }}
                                                    </span>
                                                    @endif
                                                @endforeach
                                            @endif
                                            @if($value->members)
                                                @foreach(explode(', ', $value->members) as $member)
                                                    @if($member)
                                                    <span class="inline-block badge text-white bg-yellow-500 px-2 py-1 rounded-full text-xs mr-1 mb-1">
                                                        👤 {{ $member }}
                                                    </span>
                                                    @endif
                                                @endforeach
                                            @endif
                                        </div>
                                    </td>
                                    --}}

                                    <td class="px-6 py-4">
                                        <div class="space-y-1">

                                        {{-- Owners --}}
                                        @if($value->owners)
                                            @foreach(explode(', ', $value->owners) as $owner)
                                                @if($owner)
                                                <div class="flex items-center space-x-2">
                                                    <span class="font-medium">{{ $owner }}</span>
                                                    <span class="badge text-white bg-green-500 px-2 py-1 rounded-full text-xs">Owner</span>
                                                </div>
                                                @endif
                                            @endforeach
                                        @endif


                                        {{-- Admins --}}
                                        @if($value->admins)
                                            @foreach(explode(', ', $value->admins) as $admin)
                                                @if($admin)
                                                <div class="flex items-center space-x-2">
                                                    <span class="font-medium">{{ $admin }}</span>
                                                    <span class="badge text-white bg-blue-500 px-2 py-1 rounded-full text-xs">Admin</span>
                                                </div>
                                                @endif
                                            @endforeach
                                        @endif


                                        {{-- Members --}}
                                        @if($value->members)
                                            @foreach(explode(', ', $value->members) as $member)
                                                @if($member)
                                                <div class="flex items-center space-x-2">
                                                    <span class="font-medium">{{ $member }}</span>
                                                    <span class="badge text-white bg-yellow-500 px-2 py-1 rounded-full text-xs">Member</span>
                                                </div>
                                                @endif
                                            @endforeach
                                        @endif

                                        </div>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <small class="text-gray-500">{{ \Carbon\Carbon::parse($value->created_at)->format('M d, Y') }}</small>
                                    </td>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="btn-group btn-group-sm flex space-x-1">
                                            <button class="btn btn-outline-primary edit-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200" 
                                                data-project_id="{{ $value->project->id ?? '' }}"
                                                data-project_name="{{ $value->project->project_name ?? '' }}"
                                                title="Edit Users & Roles"> 
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-8">
                                    <i class="fas fa-inbox fa-3x text-gray-400 mb-4"></i>
                                    <h4 class="text-gray-500 text-xl font-medium mb-2">No data found</h4>
                                    <p class="text-gray-400 mb-4">Try filtering with different data.</p>
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
    
    @include('task-module.workspace-user.create-modal')
    @include('task-module.workspace-user.edit-modal')
    @include('task-module.workspace-user.delete-modal')

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

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // Show create modal
            $('.create-btn').click(function() {
                $('#createModal').removeClass('hidden');
                // Initialize select2 after modal opens
                $('#createModal .select2').select2();
                // Reset form state
                $('#user_ids').prop('disabled', true).empty().append('<option value="">Select workspace first</option>');
                $('#userRolesContainer').addClass('hidden');
                $('#userRolesList').empty();
                $('.error-message').addClass('hidden');
                $('.no-users-message').addClass('hidden');
            });

            $('#workspace_id').on('change', function() {
                const workspaceId = $(this).val();
                const userSelect = $('#user_ids');
                
                $('#userSelectContainer').removeClass('hidden');

                userSelect.prop('disabled', true).empty().append('<option value="">Loading users...</option>');
                $('#userRolesContainer').addClass('hidden');
                $('#userRolesList').empty();
                $('.no-users-message').addClass('hidden');
                
                if (!workspaceId) {
                    userSelect.empty().append('<option value="">Select Project first</option>');
                    return;
                }
                
                $.ajax({
                    url: '{{ route("role.workspace-users.index", ["role" => Str::slug(Auth::user()->getRoleNames()->first())]) }}',
                    method: 'GET',
                    data: {
                        workspace_id: workspaceId
                    },
                    success: function (response) {
                        if (response.success && response.users.length > 0) {
                            userSelect.empty().append('<option value="">Select Users</option>');
                            response.users.forEach(function(user) {
                                userSelect.append('<option value="' + user.id + '">' + user.text + '</option>');
                            });
                            userSelect.prop('disabled', false);
                            $('.no-users-message').addClass('hidden');
                        } else {
                            $('.no-users-message').removeClass('hidden').text(response.message || 'No users available for the selected workspace.');
                            userSelect.empty().append('<option value="">No users available</option>');
                        }
                        userSelect.trigger('change.select2');
                    },
                    error: function (xhr) {
                        console.error('Error loading users:', xhr.responseText);
                        userSelect.empty().append('<option value="">Error loading users</option>');
                    }
                });
            });

            $('#user_ids').on('change', function() {
                const selectedUsers = $(this).select2('data').filter(u => u.id);
                
                const existingRoleSelections = {};
                $('#userRolesList .role-select').each(function() {
                    const userId = $(this).data('user-id');
                    const selectedRole = $(this).val();
                    if (selectedRole) {
                        existingRoleSelections[userId] = selectedRole;
                    }
                });
                
                // Use unified function with CREATE mode
                const rolesHtml = generateUserRoleSelects(selectedUsers, {
                    mode: 'create',
                    existingSelections: existingRoleSelections
                });
                
                $('#userRolesList').html(rolesHtml);
                
                if (selectedUsers.length > 0) {
                    $('#userRolesContainer').removeClass('hidden');
                    $('#userRolesList .role-select').select2({
                        minimumResultsForSearch: Infinity,
                        width: '100%'
                    });
                } else {
                    $('#userRolesContainer').addClass('hidden');
                }
            });

            // Universal function to generate user role selects for both CREATE and EDIT modals
            function generateUserRoleSelects(users, options = {}) {
                if (users.length === 0) return '';
                
                const {
                    mode = 'create',              // 'create' or 'edit'
                    existingSelections = {},      // For create mode
                    showRemoveButton = false      // Show remove button
                } = options;
                
                const roles = @json($roles);
                let html = '';
                
                users.forEach(function(user) {
                    // Handle different data structures
                    const userName = user.user_name || user.text || user.name;
                    const userId = user.user_id || user.id;
                    const workspaceUserId = user.id;
                    const currentRole = user.role || existingSelections[userId] || '';
                    
                    // CSS class based on mode
                    const selectClass = mode === 'edit' ? 'edit-role-select' : 'role-select';
                    
                    html += `
                        <div class="user-role-item bg-gray-50 p-3 rounded-lg border border-gray-200">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex-1">
                                    <label class="text-sm font-medium text-gray-700 mb-1 block">
                                        ${userName}
                                    </label>
                                </div>
                                <div class="flex-1">
                                    <select name="user_roles[${workspaceUserId}]" 
                                            class="${selectClass} form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500" 
                                            data-user-id="${userId}" 
                                            ${mode === 'edit' ? `data-workspace-user-id="${workspaceUserId}"` : ''}
                                            style="width: 100%">`;
                    
                    // Add default option for create mode
                    if (mode === 'create') {
                        html += `<option value="">Select role</option>`;
                    }
                    
                    roles.forEach(function(role) {
                        const selected = (currentRole === role.value) ? ' selected' : '';
                        html += `<option value="${role.value}"${selected}>${role.name}</option>`;
                    });
                    
                    html += `</select>`;
                    
                    // Show error message for create mode
                    if (mode === 'create') {
                        html += `<p class="text-xs mt-1 hidden error-message" style="color: #ef4444;">Role required</p>`;
                    }
                    
                    html += `</div>`;
                    
                    // Add remove button if needed
                    if (showRemoveButton || mode === 'edit') {
                        html += `
                                <div>
                                    <button type="button" class="btn btn-sm btn-danger remove-user-btn bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded" 
                                            data-workspace-user-id="${workspaceUserId}" 
                                            title="Remove user">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>`;
                    }
                    
                    html += `
                            </div>
                        </div>`;
                });
                
                return html;
            }

            // Show edit modal
            $(document).on('click', '.edit-btn', function() {
                const project_id = $(this).data('project_id');
                const project_name = $(this).data('project_name');
                
                if (!project_id) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Project ID not found.'
                    });
                    return;
                }

                // Set project info
                $('#edit_project_id').val(project_id);
                $('#edit_project_name').text(project_name);
                
                // Show modal with loading state
                $('#editModal').removeClass('hidden');
                $('#editUserRolesList').html(`
                    <div class="text-center py-4">
                        <i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i>
                        <p class="text-gray-500 mt-2">Loading users...</p>
                    </div>
                `);
                
                // Load users for this project
                const baseUrl = '{{ route("role.workspace-users.get-project-users", ["role" => Str::slug(Auth::user()->getRoleNames()->first()), "project_id" => "project_id"]) }}';
                const url = baseUrl.replace('project_id', project_id);
                
                $.ajax({
                    url: url,
                    method: 'GET',
                    success: function(response) {
                        if (response.success && response.users.length > 0) {
                            // Use unified function with EDIT mode
                            const html = generateUserRoleSelects(response.users, {
                                mode: 'edit',
                                showRemoveButton: true
                            });
                            $('#editUserRolesList').html(html);
                        } else {
                            $('#editUserRolesList').html(`
                                <div class="text-center py-4">
                                    <i class="fas fa-users-slash text-3xl text-gray-400 mb-2"></i>
                                    <p class="text-gray-500">No users assigned to this project yet.</p>
                                </div>
                            `);
                        }
                    },
                    error: function(xhr) {
                        console.error('Error loading users:', xhr.responseText);
                        $('#editUserRolesList').html(`
                            <div class="text-center py-4 text-red-500">
                                <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                                <p>Failed to load users. Please try again.</p>
                            </div>
                        `);
                    }
                });
            });

            // Remove user from project
            $(document).on('click', '.remove-user-btn', function() {
                const workspaceUserId = $(this).data('workspace-user-id');
                const userItem = $(this).closest('.user-role-item');
                
                Swal.fire({
                    title: 'Are you sure?',
                    text: "This user will be removed from the project!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, remove it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '{{ route("role.workspace-users.destroy", ["role" => Str::slug(Auth::user()->getRoleNames()->first()), "workspace_user" => "PLACEHOLDER"]) }}'.replace('PLACEHOLDER', workspaceUserId),
                            method: 'DELETE',
                            data: { item_id: workspaceUserId },
                            success: function(response) {
                                if (response.success) {
                                    userItem.fadeOut(300, function() {
                                        $(this).remove();
                                        if ($('#editUserRolesList .user-role-item').length === 0) {
                                            $('#editUserRolesList').html(`
                                                <div class="text-center py-4">
                                                    <i class="fas fa-users-slash text-3xl text-gray-400 mb-2"></i>
                                                    <p class="text-gray-500">No users assigned to this project.</p>
                                                </div>
                                            `);
                                        }
                                    });
                                    Swal.fire('Removed!', response.message, 'success');
                                } else {
                                    Swal.fire('Error!', response.message, 'error');
                                }
                            },
                            error: function(xhr) {
                                console.error('Error removing user:', xhr.responseText);
                                Swal.fire('Error!', 'Failed to remove user.', 'error');
                            }
                        });
                    }
                });
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
                if (validateCreateForm()) {
                    const workspaceId = $('#workspace_id').val();
                    const userRoles = {};
                    let hasErrors = false;
                    
                    $('#userRolesList .role-select').each(function() {
                        const userId = $(this).data('user-id');
                        const role = $(this).val();
                        
                        if (role) {
                            userRoles[userId] = role;
                        } else {
                            hasErrors = true;
                            $(this).addClass('border-red-500');
                            $(this).siblings('.error-message').removeClass('hidden');
                        }
                    });
                    
                    if (hasErrors) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Incomplete',
                            text: 'Please assign roles to all selected users.'
                        });
                        return;
                    }
                    
                    const assignments = [];
                    for (const userId in userRoles) {
                        assignments.push({
                            user_id: userId,
                            role: userRoles[userId]
                        });
                    }
                    
                    $.ajax({
                        url: $('#createForm').attr('action'),
                        method: 'POST',
                        data: {
                            workspace_id: workspaceId,
                            assignments: assignments
                        },
                        success: function (response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Done',
                                    text: response.message || 'Users assigned successfully!',
                                    timer: 2000
                                });
                                $('#createModal').addClass('hidden');
                                $('#createForm')[0].reset();
                                $('#userRolesContainer').addClass('hidden');
                                $('#userRolesList').empty();
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
                            console.error('Submission error:', xhr.responseText);
                            let errorMessage = 'Failed to assign users.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMessage = xhr.responseJSON.message;
                            }
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: errorMessage
                            });
                        }
                    });
                }
            });           

            $('#editSubmit').click(function(e) {
                e.preventDefault();
                
                const projectId = $('#edit_project_id').val();
                const userRoles = [];
                let hasErrors = false;
                
                // Collect all user roles
                $('#editUserRolesList .edit-role-select').each(function() {
                    const workspaceUserId = $(this).data('workspace-user-id');
                    const role = $(this).val();
                    
                    if (role && workspaceUserId) {
                        userRoles.push({
                            id: workspaceUserId,
                            role: role
                        });
                    } else {
                        hasErrors = true;
                        $(this).addClass('border-red-500');
                    }
                });
                
                if (hasErrors) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Incomplete',
                        text: 'Please select roles for all users.'
                    });
                    return;
                }
                
                if (userRoles.length === 0) {
                    Swal.fire({
                        icon: 'info',
                        title: 'No Changes',
                        text: 'No users to update.'
                    });
                    return;
                }
                
                // Submit the updates using the standard update route
                $.ajax({
                    url: '{{ route("role.workspace-users.update", ["role" => Str::slug(Auth::user()->getRoleNames()->first()), "workspace_user" => 0]) }}',
                    method: 'PUT',
                    data: {
                        project_id: projectId,
                        user_roles: userRoles
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: response.message || 'Roles updated successfully!',
                                timer: 2000
                            });
                            $('#editModal').addClass('hidden');
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'Failed to update roles.'
                            });
                        }
                    },
                    error: function(xhr) {
                        console.error('Error updating roles:', xhr.responseText);
                        let errorMessage = 'Failed to update roles.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: errorMessage
                        });
                    }
                });
            });

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
        });
        
        // Form validation functions
        function validateCreateForm() {
            let isValid = true;
            
            // Reset error messages
            $('#createForm .error-message').addClass('hidden');
            $('#createForm .form-select, #createForm .form-input').removeClass('border-red-500');                        
            
            // Validate workspace
            if (!$('#workspace_id').val()) {
                $('#workspace_id').siblings('.error-message').removeClass('hidden');
                $('#workspace_id').addClass('border-red-500');
                isValid = false;
            }
            
            // Validate users selected
            const selectedUsers = $('#user_ids').val();
            if (!selectedUsers || selectedUsers.length === 0) {
                $('#user_ids').siblings('.error-message').removeClass('hidden');
                $('#user_ids').addClass('border-red-500');
                isValid = false;
            }
            
            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            
            // Reset error messages
            $('#editeForm .error-message').addClass('hidden');
            $('#editeForm .form-select, #editeForm .form-input').removeClass('border-red-500');            
            
            if (!$('#edit_role').val().trim()) {
                $('#edit_role').next('.error-message').removeClass('hidden');
                $('#edit_role').addClass('border-red-500');
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
        function confirmDelete(id, name) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            $('#deleteModal').removeClass('hidden');
        }

    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterHeader = document.querySelector('.filter-container .filter-header');
            const filterContent = document.querySelector('.filter-container .filter-content');
            
            filterHeader.addEventListener('click', function() {
                this.classList.toggle('active');
                filterContent.classList.toggle('active');
            });
            
            // Reset button functionality
            document.querySelector('.filter-container .reset-btn').addEventListener('click', function() {
                const inputs = document.querySelectorAll('.filter-container select, .filter-container input');
                inputs.forEach(input => {
                    if (input.type === 'date') {
                        input.value = '';
                    } else {
                        input.selectedIndex = 0;
                    }
                });
            });
            document.querySelector('.reset-btn').addEventListener('click', function (e) {
                e.preventDefault();
                window.location = "{{ route('role.workspace-users.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}";
            });
        });
    </script>
@endsection