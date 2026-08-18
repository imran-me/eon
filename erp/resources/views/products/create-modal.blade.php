<div id="createModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-3xl mx-auto rounded shadow-lg z-50">
        <div class="modal-content flex flex-col py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-2" style="border-bottom: 3px solid #e8e8e8; width: 100%">
                <h3 class="text-xl font-semibold">Add New Product</h3>
                <button class="modal-close-create z-50">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body overflow-y-auto mt-2" style="max-height: calc(90vh - 120px); scrollbar-width: thin;">
                <form class="closest" id="createForm" action="{{ route('role.products.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4">                                                               
                        <div class="mb-2">
                            <label for="create_category_id" class="block text-gray-700 text-sm font-bold mb-2">Category</label>
                            <select id="create_category_id" name="category_id" onchange="getSubCategory(this, '#create_sub_category_id')" data-action="{{ route('role.get-sub-category', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                <option value="">All</option>                                        
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>                                            
                                @endforeach
                            </select>
                            <p id="create_category_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Category</p>
                        </div>
                        <div class="mb-2">
                            <label for="create_sub_category_id" class="block text-gray-700 text-sm font-bold mb-2">Sub Category</label>
                            <select id="create_sub_category_id" name="sub_category_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                <option value="">All</option>    
                            </select>
                            <p id="create_category_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a sub category</p>
                        </div>
                        <div class="mb-2">
                            <label for="create_brand_id" class="block text-gray-700 text-sm font-bold mb-2">Brand</label>
                            <select id="create_brand_id" name="brand_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                <option value="">All</option>                                        
                                @foreach ($brands as $brand)
                                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>                                            
                                @endforeach
                            </select>
                            <p id="create_brand_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Brand</p>
                        </div>
                        <div class="mb-2">
                            <label for="create_unit_id" class="block text-gray-700 text-sm font-bold mb-2">Unit</label>
                            <select id="create_unit_id" name="unit_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                <option value="">All</option>                                        
                                @foreach ($units as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>                                            
                                @endforeach
                            </select>
                            <p id="create_unit_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Unit</p>
                        </div>
                        <div class="mb-2">
                            <label for="create_supplier_id" class="block text-gray-700 text-sm font-bold mb-2">Supplier</label>
                            <select id="create_supplier_id" name="supplier_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                <option value="">All</option>                                        
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>                                            
                                @endforeach
                            </select>
                            <p id="create_supplier_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Supplier</p>
                        </div>  
                        <div class="mb-2">
                            <label for="create_name" class="block text-gray-700 text-sm font-bold mb-2">Name</label>
                            <input type="text" id="create_name" name="name" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter a Name">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Name</p>
                        </div>
                        <div class="mb-2">
                            <label for="create_sku" class="block text-gray-700 text-sm font-bold mb-2">SKU</label>
                            <input type="text" id="create_sku" name="sku" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter a Name">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a SKU</p>
                        </div>
                        <div class="mb-2">
                            <label for="create_purchase_price" class="block text-gray-700 text-sm font-bold mb-2">Purchase Price</label>
                            <input type="number" id="create_purchase_price" name="purchase_price" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter a amount">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Purchase Price Amount</p>
                        </div> 
                        <div class="mb-2">
                            <label for="create_selling_price" class="block text-gray-700 text-sm font-bold mb-2">Selling Price</label>
                            <input type="number" id="create_selling_price" name="selling_price" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter a amount">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Selling Price Amount</p>
                        </div>                            
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" id="create_is_active" name="is_active" class="form-checkbox h-5 w-5 text-blue-600" checked>
                                <span class="ml-2 text-gray-700">Active</span>
                            </label>
                        </div>
                    </div>
    
                    <div class="modal-header flex justify-between items-center pt-4 pb-2" style="border-bottom: 3px solid #e8e8e8; width: 100%">
                        <h2 class="text-xl font-semibold">Manage Stock</h2>
                    </div>
                    @foreach ($branches as $branch)
                    <div class="modal-header flex justify-between items-center pt-4 pb-3">
                        <input type="hidden" name="branch_ids[]" value="{{ $branch->id }}">
                        <h5 class="text-xl font-semibold" style="font-size: 16px;">Add Stock for #{{ $branch->name }} Branch:</h5>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">                         
                        <div class="mb-2">
                            <label for="create_available_qty" class="block text-gray-700 text-sm font-bold mb-2">Available Qty</label>
                            <input type="number" id="create_available_qty" name="available_qty[]" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter a quantity qmount">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a quantity Amount</p>
                        </div>
                        <div class="mb-2">
                            <label for="create_reserved_qty" class="block text-gray-700 text-sm font-bold mb-2">Reserved Qty</label>
                            <input type="number" id="create_reserved_qty" name="reserved_qty[]" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter a quantity qmount">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a a quantity Amount</p>
                        </div>
                        <div class="mb-2">
                            <label for="create_damaged_qty" class="block text-gray-700 text-sm font-bold mb-2">Damaged Qty</label>
                            <input type="number" id="create_damaged_qty" name="damaged_qty[]" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter a quantity qmount">
                            <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a a quantity Amount</p>
                        </div>                                                                        
                    </div>
                    @endforeach
                </form>
            </div>
            <div class="modal-footer flex justify-end pt-2">
                <button type="button" class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-create">
                    Cancel
                </button>
                <button id="createSubmit" type="button" class="btn btn-primary px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200">
                    Submit
                </button>
            </div>
        </div>
    </div>
</div>