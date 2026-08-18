@extends('layout.app')
@section('meta-information')
    <title>Manage Stock Movement</title>
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
                    <i class="fas fa-list mr-2"></i>Stock Movement List
                </h2>
                @can('create stock movement')               
                <a href="{{ route('role.stock-movements.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="btn btn-primary create-new-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add New Stock Movement
                </a>
                @endcan
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
                                    <label for="type">Type</label>
                                    <select id="type" name="type" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>
                                        <option value="sale" {{ request('type') == 'sale' ? 'selected' : '' }}>Sale</option>
                                        <option value="purchase" {{ request('type') == 'purchase' ? 'selected' : '' }}>Purchase</option>
                                        <option value="return" {{ request('type') == 'return' ? 'selected' : '' }}>Return</option>
                                        <option value="transfer" {{ request('type') == 'transfer' ? 'selected' : '' }}>Transfer</option>
                                        <option value="adjustment" {{ request('type') == 'adjustment' ? 'selected' : '' }}>Adjustment</option>
                                    </select>
                                </div>        
                                <div class="filter-group">
                                    <label for="movement">Movement Type</label>
                                    <select id="movement" name="movement" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>
                                        <option value="in" {{ request('movement') == 'in' ? 'selected' : '' }}>In</option>
                                        <option value="out" {{ request('movement') == 'out' ? 'selected' : '' }}>Out</option>
                                        <option value="other" {{ request('movement') == 'other' ? 'selected' : '' }}>Other</option>
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
                                    <label for="product_id">Product</label>
                                    <select id="product_id" name="product_id" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>                                        
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}" {{ $product->id == request('product_id') ? 'selected' : '' }}>{{ $product->name }}</option>                                            
                                        @endforeach
                                    </select>
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Branch</th>                                                                                                                                                                                                   
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Note</th>
                                @canany(['edit stock movement', 'delete stock movement'])
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
                                        @php
                                            $badgeColor = match($value->movement) {
                                                'in', 'stock_in' => 'bg-green-100 text-green-700 border-green-300',
                                                'out', 'stock_out' => 'bg-red-100 text-red-700 border-red-300',
                                                default => 'bg-gray-100 text-gray-700 border-gray-300',
                                            };
                                        @endphp
                                    
                                        <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full border {{ $badgeColor }}">
                                            {{ strtoupper($value->type) }} — {{ strtoupper(str_replace('_', ' ', $value->movement)) }}
                                        </span>
                                    
                                        <br>
                                        <strong class="text-gray-600 text-xs">{{ $value->reference_no }}</strong>
                                    </td>                                    
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->product?->name }}</td>                                      
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->quantity }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->branch?->name }}</td>                                                                          
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->note }}</td>                                 
                                    @canany(['edit stock movement', 'delete stock movement'])
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="btn-group btn-group-sm flex space-x-1">
                                            @can('edit stock movement')
                                            <a href="{{ route('role.stock-movements.edit', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'stock_movement' => $value->id]) }}" class="btn btn-outline-primary edit-item-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200" title="Edit Item">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endcan
                                            @can('delete stock movement')
                                            <button class="btn btn-outline-danger border border-red-500 text-red-500 hover:bg-red-500 hover:text-white px-3 py-1 rounded-md transition duration-200" onclick="confirmDelete('{{ $value->id }}', '{{ $value->transfer_no }}')">
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

    @include('stock-movements.delete-modal')

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
                window.location = '{{ route('role.stock-movements.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}';
            });
        });
    </script>
@endsection