<div class="modal-header flex justify-between items-center pb-2" style="border-bottom: 3px solid #e8e8e8; width: 100%">
    <h3 class="text-xl font-semibold">Edit Product #{{ $product->name }}</h3>
    <button class="modal-close-edit z-50">
        <i class="fas fa-times"></i>
    </button>
</div>
<div class="modal-body mt-2">
    <form id="editForm" method="POST" enctype="multipart/form-data"
        action="{{ route('role.products.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'product' => 1]) }}">
        @csrf
        @method('PUT')
        <input type="hidden" id="editItemId" name="id" value={{ $product->id }}>
        <div class="modal-main-body overflow-y-auto mt-2" style="max-height: calc(90vh - 120px); scrollbar-width: thin;">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4">
                <div class="mb-2">
                    <label for="edit_category_id" class="block text-gray-700 text-sm font-bold mb-2">Category</label>
                    <select id="edit_category_id" name="category_id" onchange="getEditSubCategory()"
                        class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2"
                        style="width: 100%">
                        <option value="">All</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}"
                                {{ $product->category_id && $category->id == $product->category_id ? 'selected' : '' }}>
                                {{ $category->name }}</option>
                        @endforeach
                    </select>
                    <p id="edit_category_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a
                        Category</p>
                </div>
                <div class="mb-2">
                    <label for="edit_sub_category_id" class="block text-gray-700 text-sm font-bold mb-2">Sub
                        Category</label>
                    <select id="edit_sub_category_id" name="sub_category_id"
                        class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2"
                        style="width: 100%">
                        <option value="">All</option>
                        @if ($sub_categories)
                            @foreach ($sub_categories as $sub_catg)
                                <option value="{{ $sub_catg->id }}">{{ $sub_catg->name }}</option>
                            @endforeach
                        @endif
                    </select>
                    <p id="edit_category_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a sub
                        category</p>
                </div>
                <div class="mb-2">
                    <label for="edit_brand_id" class="block text-gray-700 text-sm font-bold mb-2">Brand</label>
                    <select id="edit_brand_id" name="brand_id"
                        class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2"
                        style="width: 100%">
                        <option value="">All</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}" {{ $brand->id == $product->brand_id ? 'selected' : '' }}>
                                {{ $brand->name }}</option>
                        @endforeach
                    </select>
                    <p id="edit_brand_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Brand</p>
                </div>
                <div class="mb-2">
                    <label for="edit_unit_id" class="block text-gray-700 text-sm font-bold mb-2">Unit</label>
                    <select id="edit_unit_id" name="unit_id"
                        class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2"
                        style="width: 100%">
                        <option value="">All</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}" {{ $unit->id == $product->unit_id ? 'selected' : '' }}>
                                {{ $unit->name }}</option>
                        @endforeach
                    </select>
                    <p id="edit_unit_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a Unit</p>
                </div>
                <div class="mb-2">
                    <label for="edit_supplier_id" class="block text-gray-700 text-sm font-bold mb-2">Supplier</label>
                    <select id="edit_supplier_id" name="supplier_id"
                        class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2"
                        style="width: 100%">
                        <option value="">All</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}"
                                {{ $supplier->id == $product->supplier_id ? 'selected' : '' }}>{{ $supplier->name }}
                            </option>
                        @endforeach
                    </select>
                    <p id="edit_supplier_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a
                        Supplier</p>
                </div>
                <div class="mb-2">
                    <label for="edit_name" class="block text-gray-700 text-sm font-bold mb-2">Name</label>
                    <input type="text" id="edit_name" value={{ $product->name }} name="name" required
                        class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Enter a Name">
                    <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Name</p>
                </div>
                <div class="mb-2">
                    <label for="edit_sku" class="block text-gray-700 text-sm font-bold mb-2">SKU</label>
                    <input type="text" id="edit_sku" value={{ $product->sku }} name="sku"
                        class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Enter a Name">
                    <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a SKU</p>
                </div>
                <div class="mb-2">
                    <label for="edit_purchase_price" class="block text-gray-700 text-sm font-bold mb-2">Purchase
                        Price</label>
                    <input type="number" id="edit_purchase_price" value={{ $product->purchase_price }}
                        name="purchase_price"
                        class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Enter a amount">
                    <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Purchase Price Amount</p>
                </div>
                <div class="mb-2">
                    <label for="edit_selling_price" class="block text-gray-700 text-sm font-bold mb-2">Selling
                        Price</label>
                    <input type="number" id="edit_selling_price" value={{ $product->selling_price }} required
                        name="selling_price"
                        class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Enter a amount">
                    <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a Selling Price Amount</p>
                </div>
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" id="edit_is_active" {{ $product->is_active ? 'checked' : '' }}
                            name="is_active" class="form-checkbox h-5 w-5 text-blue-600">
                        <span class="ml-2 text-gray-700">Active</span>
                    </label>
                </div>
            </div>    
            <div class="modal-header flex justify-between items-center pt-4 pb-2" style="border-bottom: 3px solid #e8e8e8; width: 100%">
                <h2 class="text-xl font-semibold">Manage Stock</h2>
            </div>
            @foreach ($product->stocks as $stock)
                <div class="modal-header flex justify-between items-center pt-4 pb-3">
                    <input type="hidden" name="branch_ids[]" value="{{ $stock->branch_id }}">
                    <h5 class="text-xl font-semibold" style="font-size: 16px;">Add Stock for #{{ $stock->branch?->name }}
                        Branch:</h5>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="mb-2">
                        <label for="edit_available_qty" class="block text-gray-700 text-sm font-bold mb-2">Available
                            Qty</label>
                        <input type="number" id="edit_available_qty" value="{{ $stock->available_qty }}"
                            name="available_qty[]"
                            class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Enter a quantity qmount">
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a quantity Amount</p>
                    </div>
                    <div class="mb-2">
                        <label for="edit_reserved_qty" class="block text-gray-700 text-sm font-bold mb-2">Reserved
                            Qty</label>
                        <input type="number" id="edit_reserved_qty" value="{{ $stock->reserved_qty }}"
                            name="reserved_qty[]"
                            class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Enter a quantity qmount">
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a a quantity Amount</p>
                    </div>
                    <div class="mb-2">
                        <label for="edit_damaged_qty" class="block text-gray-700 text-sm font-bold mb-2">Damaged
                            Qty</label>
                        <input type="number" id="edit_damaged_qty" value="{{ $stock->damaged_qty }}"
                            name="damaged_qty[]"
                            class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Enter a quantity qmount">
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a a quantity Amount</p>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="modal-footer flex justify-end pt-2">
            <button type="button"
                class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200 mr-2 modal-close-edit">
                Cancel
            </button>
            <button type="submit"
                class="btn btn-primary px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-200">
                Submit
            </button>
        </div>
    </form>
</div>
<script>
    $('.modal-close-edit, .modal-backdrop').click(function(e) {
        console.log(1);            
        if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-edit').length) {
            console.log(2);                
            $('#editModal').addClass('hidden');
        }
    });
</script>