@extends('layout.app')
@section('meta-information')
    <title>Add New Sale</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
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
        padding: 10px 20px;
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
<style>
    .sale-modal-body {
        font-family: 'Inter', sans-serif;
    }
    
    .sale-modal-body .form-input,
    .sale-modal-body .form-select {
        transition: all 0.3s ease;
    }
    
    .sale-modal-body .form-input:focus,
    .sale-modal-body .form-select:focus {
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .sale-modal-body .product-card {
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
    }
    
    .sale-modal-body .product-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border-left-color: #3b82f6;
    }
    
    .sale-modal-body .calculation-card {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 12px;
        border: 1px solid #e2e8f0;
    }
    
    .sale-modal-body .amount-highlight {
        font-weight: 700;
        color: #1e293b;
        font-size: 1.1rem;
    }
    
    .sale-modal-body .payment-status-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .sale-modal-body .payment-status-paid {
        background-color: #dcfce7;
        color: #166534;
    }
    
    .sale-modal-body .payment-status-pending {
        background-color: #fef3c7;
        color: #92400e;
    }
    
    .sale-modal-body .payment-status-partial {
        background-color: #dbeafe;
        color: #1e40af;
    }
    
    .sale-modal-body .add-product-btn {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
        transition: all 0.3s ease;
    }
    
    .sale-modal-body .add-product-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
    }
    
    .sale-modal-body .remove-product-btn {
        transition: all 0.2s ease;
    }
    
    .sale-modal-body .remove-product-btn:hover {
        background-color: #fee2e2;
        color: #dc2626;
    }
    
    .sale-modal-body .note-textarea {
        min-height: 100px;
        resize: vertical;
    }
    
    .sale-modal-body .section-divider {
        border-bottom: 3px solid #e8e8e8;
        width: 100%;
        margin: 1.5rem 0;
    }
</style>
@endsection
@section('main-content')

    <div class="states-table bg-white rounded-lg shadow-md overflow-hidden" style="margin-top: 0">
        <div class="states-table-container">
            <div class="states-table-header bg-blue-600 px-6 pb-4 flex justify-between items-center">
                <h2 class="states-table-title text-white text-xl font-semibold" style="color: white">
                    <i class="fas fa-plus mr-2"></i> Add New Sale
                </h2>
                <a href="{{ route('role.sales.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="btn btn-primary create-new-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-list mr-2"></i> Sale List
                </a>
            </div>

            <div class="states-table-content" style="padding: 15px;">
                <div class="sale-modal-body modal-body overflow-y-auto mt-2">
                    <form class="closest" id="saleCreateForm" action="{{ route('role.sales.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 pt-4" style="justify-content: center">   
                            <div class="mb-2">
                                <label for="customer_id" class="block text-gray-700 text-sm font-bold mb-2">Customer</label>
                                <select id="customer_id" name="customer_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                    <option value="">All</option>                                        
                                    @foreach ($customers as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>                                            
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label for="company_id" class="block text-gray-700 text-sm font-bold mb-2">Company</label>
                                <select id="company_id" name="company_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                    <option value="">All</option>                                        
                                    @foreach ($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->name }}</option>                                            
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label for="sale_date" class="block text-gray-700 text-sm font-bold mb-2">Sale Date</label>
                                <input type="date" id="sale_date" required value="{{ date('Y-m-d') }}" name="sale_date" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="mb-2">
                                <label for="sale_date" class="block text-gray-700 text-sm font-bold mb-2">Invoice No.</label>
                                <input type="text" readonly value="{{ $nextInvoice }}" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
        
                        {{-- <div class="modal-header flex justify-between items-center pt-4 pb-2" style="border-bottom: 3px solid #e8e8e8; width: 100%">
                            <h2 class="text-xl font-semibold">Choose Products</h2>
                        </div> --}}
                        <div class="section-divider pt-4 pb-2">
                            <h2 class="text-xl font-semibold text-gray-800">Choose Products</h2>
                        </div>
                         <!-- Products Selection Area -->
                        <div class="products-area mb-6">
                           
                            <!-- Product Selection Form -->
                            <div class="product-selection-form bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-4">
                                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                                    <div class="md:col-span-5">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Product</label>
                                        <select id="isProductSelected" onchange="selectProduct(this)" class="select2 form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="">Select a product</option>
                                            @foreach ($products as $product)
                                                <option value="{{ $product->id }}" data-name="{{ $product->name }}" data-sku="{{ $product->sku }}" data-selling_price="{{ $product->selling_price }}" {{ empty($product->stock_qty) ? 'disabled' : '' }}>{{ $product->name }} | SKU: {{ $product->sku }} | Stock Qty: {{ $product->stock_qty }}</option></option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                                        <input id="selectquantity" onkeyup="selectQuantity(this)" type="number" min="1" value="1" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Price</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500">৳</span>
                                            </div>
                                            <input id="selectprice" type="number" readonly step="0.01" class="form-input w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="0.00">
                                        </div>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Subtotal</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500">৳</span>
                                            </div>
                                            <input id="selectsubtotal" type="text" readonly class="form-input w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md bg-gray-50" value="0.00">
                                        </div>
                                    </div>
                                    <div class="md:col-span-1 flex items-end">
                                        <button type="button" onclick="addProductItem(this)" class="add-product-btn w-full bg-green-500 hover:bg-green-600 text-white py-2 px-3 rounded-md transition-colors" style="cursor: pointer">
                                            <i class="fas fa-check"></i> Add
                                        </button>
                                    </div>
                                </div>
                            </div>                                                        
                        </div>
                        
                        <!-- Calculation Area -->
                        <div class="section-divider pt-4 pb-2">
                            <h2 class="text-xl font-semibold text-gray-800">Sale Details</h2>
                        </div>
                        
                        <div class="calculation-area mb-6">
                            <div style="display: flex; justify-content: space-between; gap: 15px">     
                                <!-- Selected Products List -->
                                <div class="selected-products space-y-3" id="appendProducts" style="width: 65%; max-height: 424px; overflow-y: auto; scrollbar-width: thin;">                                    
                                    <div class="empty-card bg-white p-4 rounded-lg shadow-sm border border-gray-200 flex justify-between items-center">
                                        <div class="flex items-center text-center" style="width: 100%; flex-direction:column"> 
                                            <div>
                                                <i class="fas fa-warning"></i>    
                                            </div>                                                                       
                                            <h4 class="font-medium text-gray-800 text-center" style="width: 100%">No Products Selected!</h4>
                                        </div>                                        
                                    </div>
                                </div>                                                            
                                <!-- Payment Details Card -->
                                <div class="calculation-card p-5" style="width: 35%;">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Payment Information</h3>
                                    <div class="space-y-4">
                                        <div>
                                            <label for="paid_amount" class="block text-sm font-medium text-gray-700 mb-1">Paid Amount</label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <span class="text-gray-500">৳</span>
                                                </div>
                                                <input type="number" onblur="calculateGrandTotal()" value="0.00" step="0.01" id="paid_amount" name="paid_amount" class="form-input w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="0.00" style="background: white">
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <label for="due_amount" class="block text-sm font-medium text-gray-700 mb-1">Due Amount</label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <span class="text-gray-500">৳</span>
                                                </div>
                                                <input type="text" id="due_amount" name="due_amount" readonly class="form-input w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md bg-gray-100" value="0.00" style="background: white">
                                            </div>
                                        </div>                                        
                                        
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                                                <select name="payment_method" id="payment_method" class="form-control select2" style="width: 100%">
                                                    <option value="cash" selected>Cash</option>                                                    
                                                    <option value="card">Card</option>
                                                    <option value="advance">Advance</option>
                                                    <option value="checque">Checque</option>
                                                    <option value="bank_transfer">Bank Transfer</option>
                                                    <option value="mobile_banking">Mobile Banking</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </div>                                            
                                            <div>                                                
                                                <label for="payment_status" class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>                                                
                                                <select name="payment_status" id="payment_status" class="form-control select2" style="width: 100%">
                                                    <option value="due" selected>Due</option>
                                                    <option value="paid">Paid</option>
                                                    <option value="partial">Partial</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div>
                                            <label for="bank_id" class="block text-sm font-medium text-gray-700 mb-1">Payment Account</label>
                                            <select name="bank_id" id="bank_id" class="form-control select2" style="width: 100%">
                                                <option value="">Select</option>
                                                @foreach ($banks as $bank)                                                    
                                                <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <br>
                                    <div class="space-y-4">                                        
                                        <div class="border-t border-gray-300 pt-3 flex justify-between items-center" style="border-top-width: 2px;">
                                            <span class="text-gray-700 font-semibold">Sub Total:</span>
                                            <span class="amount-highlight">৳ <span class="sub_total">0.00</span></span>                                            
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-gray-800 font-semibold">Grand Total:</span>
                                            <span class="text-xl font-bold text-blue-600">৳ <span class="total_amount">0.00</span></span>
                                            <input type="hidden" name="total_amount" id="total_amount" value="0">
                                        </div>
                                    </div>
                                </div>
                            </div>                                                        
                        </div>
                        
                        <div class="flex justify-end pt-2">
                            <button id="createSaleBtn" type="button" class="btn btn-success px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition duration-200" style="cursor: pointer">
                                Submit
                            </button>
                        </div>

                    </form>
                </div>
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


        $('#createSaleBtn').click(function(e) {
            e.preventDefault();

            if (!$('#sale_date').val().trim()) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Please Select a Date!'
                });   
                return;
            }                          

            if (!$('#customer_id').val() || ($('#customer_id').val() == '') || ($('#customer_id').val() == null)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Please Select a Customer!'
                });               
                return;
            }
            
            let isListEmpty = $('#appendProducts .product-card').length;  
            if (isListEmpty == 0 || !isListEmpty) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Please Select at least one product!'
                });               
                return;
            }

            let formData = new FormData($('#saleCreateForm')[0]);
            $.ajax({
                url: $('#saleCreateForm').attr('action'),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Done',
                            text: 'Sale created successfully!'
                        });                            
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
                        text: 'Failed to create sale.'
                    });
                }
            });                                     
        });

        function selectProduct(obj) {
            let selectQuantity = $('#selectquantity').val();            
            var item_price = $(obj).find('option:selected').data('selling_price');
            $('#selectprice').val(item_price ? item_price : 0);
            $('#selectsubtotal').val(item_price ? item_price*selectQuantity : 0);
            calculateGrandTotal();
            setTimeout(function() {
                $('.add-product-btn').focus();
            }, 100);
        }

        $(document).on('keydown', '.add-product-btn', function(e) {
            if (e.key === "Enter") {
                e.preventDefault();
                addProductItem(this);
            }
        });

        function selectQuantity(obj) {
            let selectQuantity = $(obj).val();
            var item_price = $('#isProductSelected option:selected').data('selling_price');
            $('#selectprice').val(item_price ? item_price : 0);
            $('#selectsubtotal').val(item_price ? item_price * selectQuantity : 0);
            calculateGrandTotal();
        }

        function clearAddItem(){
            $('#isProductSelected').val('').trigger('change');
            $('#selectquantity').val(1);
            $('#selectprice').val(0);
            $('#selectsubtotal').val(0);
        }

        function addEmptyItem(){
            let isListEmpty = $('#appendProducts .product-card').length;  
            if (isListEmpty == 0 || !isListEmpty) {
                $('#appendProducts').append(`
                    <div class="empty-card bg-white p-4 rounded-lg shadow-sm border border-gray-200 flex justify-between items-center">
                        <div class="flex items-center text-center" style="width: 100%; flex-direction:column"> 
                            <div>
                                <i class="fas fa-warning"></i>    
                            </div>                                                                       
                            <h4 class="font-medium text-gray-800 text-center" style="width: 100%">No Products Selected!</h4>
                        </div>                                        
                    </div>
                `);
            }
        }

        function removeItem(obj){
            $(obj).closest('.product-card').remove();      
            addEmptyItem();
            calculateGrandTotal();
        }

        function addProductItem() {
            let isProductSelected = $('#isProductSelected').val();
            if (!isProductSelected) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Oops...',
                    text: 'Select a product first!'
                });
            } else{                                     

                let itemId = $('#isProductSelected option:selected').val();
                let itemName = $('#isProductSelected option:selected').data('name');
                let itemSku = $('#isProductSelected option:selected').data('sku');
                let itemQuantity = parseInt($('#selectquantity').val());
                let itemPrice = $('#selectprice').val();
                let itemSubtotal = $('#selectsubtotal').val();
                
                let existingCard = $('#appendProducts .card_no' + itemId);
                if (existingCard.length) {
                    let prevQuantity = parseInt(existingCard.find('.itemQuantity').val() || 0);
                    let newQuantity = prevQuantity + itemQuantity;
                    let newSubtotal = newQuantity * itemPrice;

                    if (newQuantity <= 0) {                        
                        existingCard.remove();
                        addEmptyItem();
                    } else {
                        existingCard.find('.item_Quantity').text(newQuantity);
                        existingCard.find('.itemQuantity').val(newQuantity);
                        existingCard.find('.item_Subtotal').text(newSubtotal);
                        existingCard.find('.itemSubtotal').val(newSubtotal);
                    }                                                         
                } else {         
                    let appendClass = '';
                    let totalSelectedItem = $('#appendProducts .product-card').length;  
                    if (totalSelectedItem % 2 === 0) {
                        appendClass = 'blue';
                    } else {
                        appendClass = 'green';
                    }     
                    if ($('#appendProducts').find('.empty-card').length) {
                        $('#appendProducts').html('');
                    }   

                    $('#appendProducts').prepend(`
                        <div class="product-card card_no${itemId} bg-white p-4 rounded-lg shadow-sm border border-gray-200 flex justify-between items-center">
                            <div class="flex items-center">
                                <div class="bg-${appendClass}-100 rounded-lg flex items-center justify-center mr-4" style="width: 60px; height: 60px; margin-right: 10px;">
                                    <i class="fas fa-box text-${appendClass}-500"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800 itemName">${itemName}</h4>
                                    <p class="text-sm text-gray-500">SKU: <span class="itemSku">${itemSku}</span></p>
                                    <input type="hidden" name="product_ids[]" class="itemId" value="${itemId}">
                                </div>
                            </div>
                            <div class="flex items-center space-x-6">
                                <div class="text-center" style="margin: 0">
                                    <p class="text-sm text-gray-500">Quantity</p>
                                    <p class="font-medium item_Quantity">${itemQuantity}</p>
                                    <input type="hidden" name="quantity[]" class="itemQuantity" value="${itemQuantity}">
                                </div>
                                <div class="text-center" style="margin: 0">
                                    <p class="text-sm text-gray-500">Price</p>
                                    <p class="font-medium">৳<span class="item_Price">${Math.round(itemPrice)}</span></p>
                                    <input type="hidden" name="unit_price[]" class="itemPrice" value="${Math.round(itemPrice)}">
                                </div>
                                <div class="text-center" style="margin: 0">
                                    <p class="text-sm text-gray-500">Total</p>
                                    <p class="font-medium text-blue-600">৳<span class="item_Subtotal">${itemSubtotal}</span></p>
                                    <input type="hidden" name="subtotal[]" class="itemSubtotal" value="${itemSubtotal}">
                                </div>
                                <button type="button" onclick="removeItem(this)" class="remove-product-btn text-gray-400 hover:text-red-500 p-2 rounded-full" style="cursor: pointer;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    `);
                }

                clearAddItem();
                calculateGrandTotal();
            }
        }

        function calculateGrandTotal() {
            let subTotal = 0;

            $('#appendProducts .itemSubtotal').each(function() {
                let itemSub = parseFloat($(this).val() || 0);
                subTotal += itemSub;
            });

            $('.sub_total').text(subTotal.toFixed(2));
            $('.total_amount').text(subTotal.toFixed(2));
            $('#total_amount').val(subTotal.toFixed(2));

            let paidAmount = parseFloat($('#paid_amount').val() || 0);
            if (paidAmount > subTotal) {
                paidAmount = subTotal;
                $('#paid_amount').val(subTotal);
            }

            let dueAmount = subTotal - paidAmount;
            $('#due_amount').val(dueAmount.toFixed(2));

            let paymentStatus = 'due';
            if (paidAmount <= 0) {
                paymentStatus = 'due';
            } else if (paidAmount >= subTotal) {                
                paymentStatus = 'paid';
            } else {
                paymentStatus = 'partial';
            }
            $('#payment_status').val(paymentStatus).trigger('change');                      
        }

    </script>    
@endsection
