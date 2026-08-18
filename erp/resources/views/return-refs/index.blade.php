@extends('layout.app')
@section('meta-information')
    <title>Manage Returns</title>
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
                    <i class="fas fa-list mr-2"></i>Return List
                </h2>                
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
                            <div class="closest filter-row">                                                                                                                        
                                <div class="filter-group">
                                    <label for="return_type">Return Type</label>
                                    <select id="return_type" name="return_type" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>                                                                                
                                        <option value="damaged" {{ 'damaged' == request('return_type') ? 'selected' : '' }}>Damaged</option>                                            
                                        <option value="expired" {{ 'expired' == request('return_type') ? 'selected' : '' }}>Expired</option>                                            
                                        <option value="defective" {{ 'defective' == request('return_type') ? 'selected' : '' }}>Defective</option>                                            
                                        <option value="wrong_item" {{ 'wrong_item' == request('return_type') ? 'selected' : '' }}>Wrong Item</option> 
                                        <option value="others" {{ 'wrong_item' == request('return_type') ? 'selected' : '' }}>Others</option>                                                                                       
                                    </select>
                                </div>                                    					
                                <div class="filter-group">
                                    <label for="branch_id">Branch</label>
                                    <select id="branch_id" name="branch_id" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>                                        
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}" {{ $branch->id == request('branch_id') ? 'selected' : '' }}>{{ $branch->name }}</option>                                            
                                        @endforeach
                                    </select>
                                </div>         
                                <div class="filter-group">
                                    <label for="created_by">Created By</label>
                                    <select id="created_by" name="created_by" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}" {{ $user->id == request('created_by') ? 'selected' : '' }}>{{ $user->name }}</option>                                            
                                        @endforeach
                                    </select>
                                </div>        
                                <div class="filter-group">
                                    <label for="return_date">Return Date</label>
                                    <input type="date" name="return_date" value="{{ request('return_date') }}" id="return_date" class="form-control">
                                </div>
                                <div class="filter-group">
                                    <label for="search">Return/Reference No</label>
                                    <input type="text" name="search" value="{{ request('search') }}" id="search" class="form-control" placeholder="Enter search...">
                                </div>
                            </div>
                            <div class="filter-actions">
                                <button type="button" class="reset-btn">Reset</button>
                                <button type="submit" class="apply-btn">Apply Filters</button>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Table with Data -->                 
                <div class="table-responsive overflow-x-auto" style="padding: 15px">
                    <table class="table table-hover min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th style="padding-left: 20px" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SL</th>                                
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Return No</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Return Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference No</th>                                                              
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Branch</th>                                                                
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>                                                                                                                            
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                                @canany(['edit return reference', 'delete return reference'])
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                @endcanany
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($datas as $key => $value)                       
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap" style="padding-left: 20px">
                                        {{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}
                                    </td> 
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <strong>{{ $value->return_no }}</strong> <br>{{ $value->return_date }} 
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @php
                                            $statusColors = [
                                                'paid' => 'bg-green-100 text-green-800',
                                                'partial' => 'bg-yellow-100 text-yellow-800',
                                                'due' => 'bg-red-100 text-red-800',
                                            ];
                                            $statusClass = $statusColors[$value->return_type] ?? 'bg-gray-100 text-gray-800';
                                        @endphp
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusClass }}">
                                            {{ ucfirst($value->return_type) }}
                                        </span>
                                    </td> 
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->reference?->invoice_no }}</td>   
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->branch?->name ?? '-' }}</td>  
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->total_amount }}</td>                                                        
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->createdBy?->name }}                                                                                                                        
                                    </td>
                                    @canany(['edit return reference', 'delete return reference'])                                      
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="btn-group btn-group-sm flex space-x-1">
                                            @can('edit return reference')
                                            <a href="{{ route('role.return-refs.edit', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'return_ref' => $value->id]) }}" class="btn btn-outline-primary edit-item-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200" title="Edit Item">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endcan
                                            @can('delete return reference')
                                            <button class="btn btn-outline-danger border border-red-500 text-red-500 hover:bg-red-500 hover:text-white px-3 py-1 rounded-md transition duration-200" onclick="confirmDelete('{{ $value->id }}', '{{ $value->invoice_no }}')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            @endcan
                                        </div>
                                    </td>
                                    @endcanany
                                </tr>
                            @empty
                            <tr>
                                <td colspan="15" class="text-center py-8">
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
                    {{ $datas->appends(request()->all())->links() }}
                </div>
            </div>
        </div>
    </div>

    @include('return-refs.delete-modal')

@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2();
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
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

        // Delete confirmation
        function confirmDelete(id, name=null) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            $('#deleteModal').removeClass('hidden');
        }

        // filter js
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
                window.location = '{{ route('role.return-refs.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}';
            });
        });
    </script>
@endsection