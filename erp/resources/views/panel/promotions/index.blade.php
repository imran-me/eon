@extends('layout.app')
@section('meta-information')
    <title>Manage Promotions</title>
@endsection
@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .manage-user-container .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; }
        .manage-user-container .badge-pending { background: #fef3c7; color: #92400e; }
        .manage-user-container .badge-approved { background: #d1fae5; color: #065f46; }
        .manage-user-container .badge-rejected { background: #fee2e2; color: #991b1b; }
        .manage-user-container .arrow { color: #3b82f6; margin: 0 5px; font-weight: bold; }
        .manage-user-container .btn-info { background: #3b82f6; color: white; border: none; padding: 5px 10px; border-radius: 0px; cursor: pointer; }
        .manage-user-container .btn-approve { background: #10b981; color: white; border: none; padding: 5px 10px; border-radius: 0px; cursor: pointer; }
        .manage-user-container .btn-approve:hover { background: #059669; }
        .manage-user-container .btn-reject { background: #b97510; color: white; border: none; padding: 5px 10px; border-radius: 0px; cursor: pointer; }
        .manage-user-container .btn-reject:hover { background: #b97510; }
        .manage-user-container .btn-delete { background: #b91610; color: white; border: none; padding: 5px 10px; border-radius: 0px; cursor: pointer; }
        .manage-user-container .btn-delete:hover { background: #962205; }
    </style>
    <style>
        /* Card Container */
        .filter-card-container {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Header */
        .filter-card-header {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f5f9;
            background-color: #f8fafc;
            border-radius: 10px 10px 0 0;
        }

        .filter-card-title {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Body and Form Grid */
        .filter-card-body {
            padding: 20px;
        }

        .filter-grid-form {
            display: grid;
            /* This creates 4 equal columns by default */
            grid-template-columns: repeat(4, 1fr); 
            gap: 20px;
            align-items: end;
        }

        /* Responsive Grid: Switch to 2 columns on tablets, 1 on mobile */
        @media (max-width: 1024px) {
            .filter-grid-form { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 600px) {
            .filter-grid-form { grid-template-columns: 1fr; }
        }

        /* Individual Fields */
        .filter-field-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-label {
            font-size: 0.85rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
        }

        .filter-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.95rem;
            color: #334155;
            background-color: #fff;
            box-sizing: border-box; /* Ensures padding doesn't break width */
        }

        .filter-input:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Buttons Section */
        .filter-actions-group {
            grid-column: 1 / -1; /* Spans across all grid columns */
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 10px;
            border-top: 1px solid #f1f5f9;
        }

        .btn-submit {
            background-color: #2563eb;
            color: #ffffff;
            border: none;
            padding: 10px 24px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-submit:hover {
            background-color: #1d4ed8;
        }

        .btn-reset {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            padding: 10px 24px;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }

        .btn-reset:hover {
            background-color: #e2e8f0;
            color: #1e293b;
        }
    </style>
@endsection
@section('main-content')
    <!-- States Table -->
    <div class="states-table bg-white rounded-lg shadow-md overflow-hidden">
        <div class="filter-card-container">
            <div class="filter-card-header">
                <h5 class="filter-card-title">
                    <i class="fas fa-filter"></i> Filter Promotions
                </h5>
            </div>
            <div class="filter-card-body">
                <form action="{{ route('role.promotions.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="GET" class="filter-grid-form">
                    <div class="filter-field-group">
                        <label class="filter-label">Employee</label>
                        <select name="user_id" class="filter-input select2">
                            <option value="">All Employees</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-field-group">
                        <label class="filter-label">New Designation</label>
                        <select name="new_designation_id" class="filter-input select2">
                            <option value="">All Designations</option>
                            @foreach($designations as $desig)
                                <option value="{{ $desig->id }}" {{ request('new_designation_id') == $desig->id ? 'selected' : '' }}>
                                    {{ $desig->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-field-group">
                        <label class="filter-label">Status</label>
                        <select name="status" class="filter-input select2">
                            <option value="">All Status</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                        </select>
                    </div>

                    <div class="filter-field-group">
                        <label class="filter-label">Effective From</label>
                        <input type="date" name="effective_from" class="filter-input" value="{{ request('effective_from') }}">
                    </div>
                    
                    <div class="filter-actions-group">
                        <button type="submit" class="btn-submit">Filter</button>
                        <a href="{{ route('role.promotions.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="btn-reset">Reset</a>
                    </div>
                </form>
            </div>
        </div>
        <div class="states-table-container">
            <div class="states-table-header bg-blue-600 px-6 py-4 flex justify-between items-center">
                <h2 class="states-table-title text-white text-xl font-semibold" style="color: white">
                    <i class="fas fa-list mr-2"></i>Promotions List
                </h2>
                <a href="{{ route('role.promotions.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    class="btn btn-primary create-new-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Manage New Promotions
                </a>
            </div>

            <div class="states-table-content manage-user-container">
                <!-- Table with Data -->
                <div class="table-responsive overflow-x-auto" style="padding: 15px">
                    {{-- <table class="table table-hover min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="padding-left: 20px">SL</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Old → New Position</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Old → New Salary</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Effective Date</th>                                
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($promotions as $key => $promo)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap" style="padding-left: 20px">
                                        <strong>{{ ($users->currentPage() - 1) * $users->perPage() + $key + 1 }}</strong>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $promo->user->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <small class="text-gray">{{ $promo->previousDesignation->name }}</small>
                                        <span class="arrow">→</span>
                                        <strong>{{ $promo->newDesignation->name }}</strong>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        ${{ number_format($promo->previous_salary, 2) }} 
                                        <span class="arrow">→</span>
                                        <strong>${{ number_format($promo->new_salary, 2) }}</strong>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $promo->effective_from }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="badge badge-{{ $promo->status }}">
                                            {{ ucfirst($promo->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($promo->status == 'pending')
                                            <form action="{{ route('promotion.approve', $promo->id) }}" method="POST">
                                                @csrf
                                                <button class="btn-approve">Approve</button>
                                            </form>
                                        @endif
                                    </td>
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
                    </table> --}}
                    <table class="table table-hover min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SL</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Position</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Salary</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Effective From</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>

                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($promotions as $key => $promo)
                                <tr>
                                    <td class="px-6 py-4 font-semibold">
                                        {{ ($promotions->currentPage() - 1) * $promotions->perPage() + $key + 1 }}
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900">{{ $promo->user->name }}</div>
                                        <div class="text-xs text-gray-500">ID: {{ $promo->user_id }}</div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <span class="text-gray-500">{{ $promo->previousDesignation->name }}</span>
                                        <span class="mx-1">→</span>
                                        <strong>{{ $promo->newDesignation->name }}</strong>
                                    </td>

                                    <td class="px-6 py-4">
                                        {{ number_format($promo->previous_salary, 2) }}
                                        <span class="mx-1">→</span>
                                        <strong>{{ number_format($promo->new_salary, 2) }}</strong>
                                    </td>

                                    <td class="px-6 py-4">
                                        {{ optional($promo->effective_from)->format('d M Y') ?? '—' }}

                                        @if($promo->effective_from && $promo->effective_from->isFuture())
                                            <span class="text-xs text-blue-600 block">(Future)</span>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4">
                                        @php
                                            $colors = [
                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                'approved' => 'bg-green-100 text-green-800',
                                                'rejected' => 'bg-red-100 text-red-800',
                                            ];
                                        @endphp

                                        <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $colors[$promo->status] }}">
                                            {{ ucfirst($promo->status) }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4">
                                        @if($promo->status === 'pending')
                                            <div class="flex gap-0">
                                                <a href="{{ route('role.promotions.edit', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'promotion' => $promo->id]) }}" class="btn btn-info btn-sm">Edit</a>
                                                <form action="{{ route('role.promotions.approve', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'id' => $promo->id]) }}" method="POST">
                                                    @csrf
                                                    <button class="btn btn-approve btn-sm">Approve</button>
                                                </form>
                                                <form action="{{ route('role.promotions.reject', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'id' => $promo->id]) }}" method="POST">
                                                    @csrf
                                                    <button class="btn btn-reject btn-sm">Reject</button>
                                                </form>
                                                <form action="{{ route('role.promotions.destroy', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'promotion' => $promo->id]) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-delete btn-sm">Delete</button>
                                                </form>
                                            </div>
                                        @elseif ($promo->status === 'rejected')
                                            <form action="{{ route('role.promotions.destroy', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'promotion' => $promo->id]) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-delete btn-sm">Delete</button>
                                            </form>
                                        @else
                                            <form action="{{ route('role.promotions.destroy', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'promotion' => $promo->id]) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-delete btn-sm">Delete</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-10 text-gray-500">
                                        No promotion records found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="mt-4">
                        {{ $promotions->links() }}
                    </div>

                </div>

                <!-- Pagination -->
                <div class="p-3 border-t border-gray-200">
                    {{ $promotions->appends(request()->all())->links() }}
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
