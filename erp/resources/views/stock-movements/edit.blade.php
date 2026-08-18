@extends('layout.app')
@section('meta-information')
    <title>Edit Stock Movement</title>
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
    .common-modal-body {
        font-family: 'Inter', sans-serif;
    }
    
    .common-modal-body .form-input,
    .common-modal-body .form-select {
        transition: all 0.3s ease;
    }
    
    .common-modal-body .form-input:focus,
    .common-modal-body .form-select:focus {
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .common-modal-body .product-card {
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
    }
    
    .common-modal-body .product-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border-left-color: #3b82f6;
    }
    
    .common-modal-body .calculation-card {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 12px;
        border: 1px solid #e2e8f0;
    }
    
    .common-modal-body .amount-highlight {
        font-weight: 700;
        color: #1e293b;
        font-size: 1.1rem;
    }
    
    .common-modal-body .payment-status-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .common-modal-body .payment-status-paid {
        background-color: #dcfce7;
        color: #166534;
    }
    
    .common-modal-body .payment-status-pending {
        background-color: #fef3c7;
        color: #92400e;
    }
    
    .common-modal-body .payment-status-partial {
        background-color: #dbeafe;
        color: #1e40af;
    }
    
    .common-modal-body .add-product-btn {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
        transition: all 0.3s ease;
    }
    
    .common-modal-body .add-product-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
    }
    
    .common-modal-body .remove-product-btn {
        transition: all 0.2s ease;
    }
    
    .common-modal-body .remove-product-btn:hover {
        background-color: #fee2e2;
        color: #dc2626;
    }
    
    .common-modal-body .note-textarea {
        min-height: 100px;
        resize: vertical;
    }
    
    .common-modal-body .section-divider {
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
                    <i class="fas fa-edit mr-2"></i> Edit Movement
                </h2>
                <a href="{{ route('role.stock-movements.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="btn btn-primary create-new-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-list mr-2"></i> Movement List
                </a>
            </div>

            <div class="states-table-content" style="padding: 15px;">
                <div class="common-modal-body modal-body overflow-y-auto mt-2">
                    <form class="closest" id="dataEditForm" action="{{ route('role.stock-movements.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'stock_movement' => $movement->id]) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="movement_id" value="{{ $movement->id }}">
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4" style="justify-content: center">      
                            <div class="filter-group">
                                <label for="type">Type</label>
                                <select id="type" name="type" class="form-control select2" style="width: 100%">
                                    {{-- <option value="sale" {{ request('type') == 'sale' ? 'selected' : '' }}>Sale</option>
                                    <option value="purchase" {{ request('type') == 'purchase' ? 'selected' : '' }}>Purchase</option>
                                    <option value="return" {{ request('type') == 'return' ? 'selected' : '' }}>Return</option>
                                    <option value="transfer" {{ request('type') == 'transfer' ? 'selected' : '' }}>Transfer</option> --}}
                                    <option value="adjustment" {{ $movement->type == 'adjustment' ? 'selected' : '' }}>Adjustment</option>
                                </select>
                            </div>  
                            <div class="filter-group">
                                <label for="movement">Movement Type</label>
                                <select id="movement" name="movement" class="form-control select2" style="width: 100%">
                                    <option value="in" {{ $movement->movement == 'in' ? 'selected' : '' }}>In</option>
                                    <option value="out" {{ $movement->movement == 'out' ? 'selected' : '' }}>Out</option>
                                </select>
                            </div>                           
                            <div class="mb-2">
                                <label for="branch_id" class="block text-gray-700 text-sm font-bold mb-2">Branch</label>
                                <select id="branch_id" name="branch_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ $movement->branch_id == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>                                            
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="block text-sm text-gray-700 font-bold mb-1">Product</label>
                                <select id="product_id" name="product_id" class="select2 form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select a product</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" {{ $movement->product_id == $product->id ? 'selected' : '' }}>
                                            {{ $product->name }} | SKU: {{ $product->sku }} | Stock Qty: {{ $product->stock_qty }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label for="quantity" class="block text-gray-700 text-sm font-bold mb-2">Quantity</label>
                                <input type="number" id="quantity" min="1" value="{{ $movement->quantity }}" name="quantity" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="mb-2">
                                <label class="block text-sm text-gray-700 font-bold mb-1">Additional Note</label>
                                <div class="space-y-4">
                                    <textarea 
                                    name="note" 
                                    class="w-full h-10 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none" 
                                    placeholder="Write ..." style="background: white;">{{ $movement->note }}</textarea>
                                </div>
                            </div>
                        </div>                                                                
                        
                        <div class="flex justify-end pt-2" style="align-items: center">                            
                            <button id="ediDataBtn" type="button" class="btn btn-success px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition duration-200" style="cursor: pointer">
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


        $('#ediDataBtn').click(function(e) {
            e.preventDefault();            
            
            let isListEmpty = $('#product_id').val();  
            if (!isListEmpty) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Please Select a product!'
                });               
                return;
            }

            let prodQty = $('#quantity').val();  
            if (!prodQty || (prodQty < 1)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Minimum quantity required!'
                });               
                return;
            }

            let formData = new FormData($('#dataEditForm')[0]);
            $.ajax({
                url: $('#dataEditForm').attr('action'),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Done',
                            text: 'Data Moved successfully!'
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
                        text: 'Failed to edit data.'
                    });
                }
            });                                     
        });
    </script>    
@endsection