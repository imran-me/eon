@extends('layout.app')
@section('meta-information')
    <title>Manage Products</title>
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
                    <i class="fas fa-list mr-2"></i>Product List
                </h2>
                @can('create product')
                <button class="btn btn-primary create-new-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add New Product
                </button>
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
                                    <label for="brand_id">Brand</label>
                                    <select id="brand_id" name="brand_id" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>                                        
                                        @foreach ($brands as $brand)
                                            <option value="{{ $brand->id }}" {{ $brand->id == request('brand_id') ? 'selected' : '' }}>{{ $brand->name }}</option>                                            
                                        @endforeach
                                    </select>
                                </div>                                
                                <div class="filter-group">
                                    <label for="unit_id">Unit</label>
                                    <select id="unit_id" name="unit_id" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>                                        
                                        @foreach ($units as $unit)
                                            <option value="{{ $unit->id }}" {{ $unit->id == request('unit_id') ? 'selected' : '' }}>{{ $unit->name }}</option>                                            
                                        @endforeach
                                    </select>
                                </div>                                
                                <div class="filter-group">
                                    <label for="category_id">Category</label>
                                    <select id="category_id" name="category_id" onchange="getSubCategory(this, '#sub_category_id')" data-action="{{ route('role.get-sub-category', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" {{ $category->id == request('category_id') ? 'selected' : '' }}>{{ $category->name }}</option>                                            
                                        @endforeach
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="sub_category_id">Sub Category</label>
                                    <select id="sub_category_id" name="sub_category_id" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>
                                        @if (!empty($req_subdatas))
                                        @foreach ($req_subdatas as $subdata)
                                            <option value="{{ $subdata->id }}" {{ $subdata->id == request('sub_category_id') ? 'selected' : '' }}>{{ $subdata->name }}</option>                                            
                                        @endforeach
                                        @endif
                                    </select>
                                </div>
                            </div>
                            <div class="closest filter-row">    
                                <div class="filter-group">
                                    <label for="user_id">Created By</label>
                                    <select id="user_id" name="user_id" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}" {{ $user->id == request('user_id') ? 'selected' : '' }}>{{ $user->name }}</option>                                            
                                        @endforeach
                                    </select>
                                </div>                               
                                <div class="filter-group">
                                    <label for="stock_qty">Stock</label>
                                    <select id="stock_qty" name="stock_qty" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>
                                        <option value="1">In Stock</option>
                                        <option value="0">Out Stock</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="is_active">Status</label>
                                    <select id="is_active" name="is_active" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="search">Name/SKU</label>
                                    <input type="text" name="search" value="{{ request('search') }}" id="search" class="form-control" placeholder="Enter search">
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>	     
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>	                                
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SubCategory</th>  
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Brand</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>                                                                
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Stock</th>                                                                
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                @canany(['edit product', 'delete product'])
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                @endcanany
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($datas as $key => $value)                       
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap" style="padding-left: 20px">
                                        <strong>{{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}</strong>
                                    </td>                                  
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->name }}</td>       
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->category?->name }}                                                                                                                        
                                    </td>                                                                          
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->sub_category?->name }}                                                                                                                        
                                    </td>                                                         
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->brand?->name }}                                                                                                                        
                                    </td>                                                                          
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->unit?->name }}
                                    </td>                                                                                                                                                                                       
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->supplier?->name }}
                                    </td>                                                                                                                                                                                       
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->user?->name }}                                                                                                                        
                                    </td>  
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->selling_price }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->stock_qty }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if ($value->is_active)
                                        <span class="badge text-white bg-green-500 px-2 py-1 rounded-full text-xs">
                                            Active
                                        </span>                                            
                                        @else                                            
                                        <span class="badge text-white bg-yellow-500 px-2 py-1 rounded-full text-xs">
                                            Inactive
                                        </span>
                                        @endif          
                                    </td>
                                    @canany(['edit product', 'delete product'])
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="btn-group btn-group-sm flex space-x-1">
                                            @can('edit product')
                                            <button class="btn btn-outline-primary edit-item-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200" 
                                                data-item_id="{{ $value->id }}" 
                                                data-edit_sub_category_id="{{ $value->sub_category_id }}" 
                                                data-action="{{ route('role.get-product-edit-modal', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                                                data-get_sub_action="{{ route('role.get-sub-category', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                                                title="Edit Item">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            @endcan

                                            @can('delete product')
                                            <button class="btn btn-outline-danger border border-red-500 text-red-500 hover:bg-red-500 hover:text-white px-3 py-1 rounded-md transition duration-200" onclick="confirmDelete('{{ $value->id }}', 'this item')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            @endcan
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
                    {{ $datas->appends(request()->all())->links() }}
                </div>
            </div>
        </div>
    </div>
    
    @include('products.create-modal')
    @include('products.delete-modal')

    <div id="editModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
        <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
        <div class="modal-container bg-white w-11/12 md:max-w-3xl mx-auto rounded shadow-lg z-50">
            <div class="modal-content flex flex-col py-4 text-left px-6" id="appendEditHtml">

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
            $('.select2').select2();
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
        });

        // Show create modal
        $('.create-new-btn').click(function() {
            $('#createModal').removeClass('hidden');
        });

        // Show edit modal
        $('.edit-item-btn').click(function() {
            const item_id = $(this).data('item_id');
            const edit_sub_category_id = $(this).data('edit_sub_category_id');
            $.ajax({
                url: $(this).data('action'),
                method: 'GET',
                data: {
                    item_id: item_id
                },
                success: function (response) {
                    console.log(response);                   
                    if (response.success && response.data.modal_view) {                                                            
                        $('#appendEditHtml').html(response.data.modal_view);
                        $('#editModal').removeClass('hidden');
                        $('#edit_category_id,#edit_sub_category_id,#edit_brand_id,#edit_unit_id,#edit_supplier_id').select2();
                        getEditSubCategory(edit_sub_category_id);
                    } else{
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

        // Close modals
        $('.modal-close-create, .modal-backdrop').click(function(e) {
            if ($(e.target).closest('.modal-close-create').length || $(e.target).hasClass('modal-backdrop')) {
                $('#createModal').addClass('hidden');
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

        function getEditSubCategory(edit_sub_category_id = null){
            $.ajax({
                url: $('.edit-item-btn').data('get_sub_action'),
                method: 'GET',
                data: {
                    category_id: $('#edit_category_id').val()
                },
                success: function (response) {
                    console.log(response);
                    if (response.success) {                                                            
                        const targetSelect = $('#edit_sub_category_id');
                        console.log(targetSelect);                        
                        targetSelect.empty();
                        targetSelect.append('<option value="">Select an Item</option>');
                        $.each(response.data, function(index, item) {
                            targetSelect.append(
                                `<option value="${item.id}" ${ edit_sub_category_id && (edit_sub_category_id == item.id) ? 'selected' : ''}>${item.name}</option>`
                            );
                        });
                        if (targetSelect.hasClass('select2-hidden-accessible')) {
                            targetSelect.trigger('change.select2');
                        }
                    } else{
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
        }
        
        function getSubCategory(obj, targetId){
            $.ajax({
                url: $(obj).data('action'),
                method: 'GET',
                data: {
                    category_id: $(obj).val()
                },
                success: function (response) {
                    console.log(response);
                    if (response.success) {                                                            
                        const targetSelect = $(obj).closest('.closest').find(targetId);
                        console.log(targetSelect);                        
                        targetSelect.empty();
                        targetSelect.append('<option value="">Select an Item</option>');
                        $.each(response.data, function(index, item) {
                            targetSelect.append(
                                `<option value="${item.id}">${item.name}</option>`
                            );
                        });
                        if (targetSelect.hasClass('select2-hidden-accessible')) {
                            targetSelect.trigger('change.select2');
                        }
                    } else{
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
        }

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
                        
            if (!$('#create_name').val().trim()) {
                $('#create_name').next('.error-message').removeClass('hidden');
                $('#create_name').addClass('border-red-500');
                isValid = false;
            }                          
            if (!$('#create_purchase_price').val().trim()) {
                $('#create_purchase_price').next('.error-message').removeClass('hidden');
                $('#create_purchase_price').addClass('border-red-500');
                isValid = false;
            }   

            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            
            // Reset error messages
            $('#editForm .error-message').addClass('hidden');
            $('#editForm .form-select, #editForm .form-input').removeClass('border-red-500');
            
            if (!$('#edit_name').val().trim()) {
                $('#edit_name').next('.error-message').removeClass('hidden');
                $('#edit_name').addClass('border-red-500');
                isValid = false;
            }                          
            if (!$('#edit_purchase_price').val().trim()) {
                $('#edit_purchase_price').next('.error-message').removeClass('hidden');
                $('#edit_purchase_price').addClass('border-red-500');
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
                window.location = '{{ route('role.products.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}';
            });
        });
    </script>
@endsection