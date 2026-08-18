@extends('layout.app')
@section('meta-information')
    <title>Manage Sales</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @include('layout.table-design')
    <style>
        .payment-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: 20px;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            --primary-color: #4361ee;
            --primary-dark: #3a56d4;
            --secondary-color: #7209b7;
            --success-color: #2ecc71;
            --warning-color: #f8961e;
            --danger-color: #e74c3c;
            --light-color: #f8f9fa;
            --dark-color: #2c3e50;
            --gray-color: #7f8c8d;
            --border-color: #dee2e6;
            --card-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        /* Active state for modal */
        .payment-modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Payment Modal */
        .payment-modal-overlay .payment-modal {
            background-color: white;
            border-radius: 12px;
            width: 100%;
            max-width: 700px;
            max-height: 98vh;
            overflow-y: auto;
            scrollbar-width: thin;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }

        .payment-modal-overlay.active .payment-modal {
            transform: translateY(0);
        }

        /* Modal Header */
        .payment-modal-overlay .modal-header {
            padding: 22px 30px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }

        .payment-modal-overlay .modal-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.2);
        }

        .payment-modal-overlay .modal-title {
            font-size: 24px;
            font-weight: 700;
        }

        .payment-modal-overlay .invoice-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 8px;
            font-size: 15px;
            opacity: 0.9;
            flex-wrap: wrap;
        }

        .payment-modal-overlay .invoice-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .payment-modal-overlay .close-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
            transition: var(--transition);
        }

        .payment-modal-overlay .close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        /* Modal Body */
        .payment-modal-overlay .modal-body {
            padding: 0px;
            /* display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px; */
        }

        @media (max-width: 768px) {
            .payment-modal-overlay .modal-body {
                /* grid-template-columns: 1fr;
                gap: 25px; */
            }
        }

        /* Payment Summary Section */
        .payment-modal-overlay .payment-summary-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            border: 1px solid var(--border-color);
        }

        .payment-modal-overlay .section-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .payment-modal-overlay .section-title i {
            font-size: 20px;
        }

        .payment-modal-overlay .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .payment-modal-overlay .summary-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .payment-modal-overlay .summary-label {
            font-size: 14px;
            color: var(--gray-color);
            margin-bottom: 5px;
        }

        .payment-modal-overlay .summary-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--dark-color);
        }

        .payment-modal-overlay .summary-value.total {
            color: var(--primary-color);
        }

        .payment-modal-overlay .summary-value.paid {
            color: var(--success-color);
        }

        .payment-modal-overlay .summary-value.due {
            color: var(--danger-color);
        }

        .payment-modal-overlay .amount-input-container {
            margin-top: 0px;
        }

        .payment-modal-overlay .amount-input-label {
            display: block;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark-color);
        }

        .payment-modal-overlay .amount-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .payment-modal-overlay .currency-symbol {
            position: absolute;
            left: 15px;
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .payment-modal-overlay .amount-input {
            width: 100%;
            padding: 16px 16px 16px 35px;
            font-size: 20px;
            font-weight: 700;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            color: var(--primary-color);
            text-align: right;
            transition: var(--transition);
            background: white;
        }

        .payment-modal-overlay .amount-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .payment-modal-overlay .amount-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .payment-modal-overlay .amount-action-btn {
            flex: 1;
            padding: 10px;
            background: #e9ecef;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            color: var(--dark-color);
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }

        .payment-modal-overlay .amount-action-btn:hover {
            background: #dee2e6;
        }

        /* Payment Details Section */
        .payment-modal-overlay .payment-details-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            border: 1px solid var(--border-color);
        }

        .payment-modal-overlay .form-group {
            margin-bottom: 20px;
        }

        .payment-modal-overlay .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark-color);
        }

        .payment-modal-overlay .form-control {
            width: 100%;
            padding: 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 16px;
            transition: var(--transition);
            background: white;
        }

        .payment-modal-overlay .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .payment-modal-overlay .form-control.select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%237f8c8d' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 16px;
            padding-right: 40px;
        }

        /* Payment Method Options */
        .payment-modal-overlay .payment-method-options {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-top: 10px;
        }

        @media (max-width: 576px) {
            .payment-modal-overlay .payment-method-options {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .payment-modal-overlay .method-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 18px 10px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            background: white;
        }

        .payment-modal-overlay .method-option:hover {
            border-color: var(--primary-color);
            background: #f8f9ff;
        }

        .payment-modal-overlay .method-option.selected {
            border-color: var(--primary-color);
            background: #f0f3ff;
            box-shadow: 0 5px 10px rgba(67, 97, 238, 0.1);
        }

        .payment-modal-overlay .method-icon {
            font-size: 24px;
            margin-bottom: 8px;
            color: var(--primary-color);
        }

        .payment-modal-overlay .method-name {
            font-size: 14px;
            font-weight: 600;
        }

        /* Notes Section */
        .payment-modal-overlay .notes-section {
            margin-top: 25px;
        }

        .payment-modal-overlay .textarea-control {
            width: 100%;
            padding: 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 16px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
            transition: var(--transition);
            background: white;
        }

        .payment-modal-overlay .textarea-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        /* Modal Footer */
        .payment-modal-overlay .modal-footer {
            padding: 22px 30px;
            background: #f8f9fa;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .payment-modal-overlay .payment-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .payment-modal-overlay .btn {
            padding: 14px 28px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: var(--transition);
        }

        .payment-modal-overlay .btn-secondary {
            background: white;
            color: var(--gray-color);
            border: 1px solid #dee2e6;
        }

        .payment-modal-overlay .btn-secondary:hover {
            background: #f8f9fa;
            color: var(--dark-color);
        }

        .payment-modal-overlay .btn-primary {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        .payment-modal-overlay .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(67, 97, 238, 0.4);
        }

        .payment-modal-overlay .btn-success {
            background: var(--success-color);
            color: white;
            box-shadow: 0 4px 12px rgba(46, 204, 113, 0.3);
        }

        .payment-modal-overlay .btn-success:hover {
            background: #27ae60;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(46, 204, 113, 0.4);
        }

        .payment-modal-overlay .receipt-option {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .payment-modal-overlay .checkbox-container {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .payment-modal-overlay .checkbox-container input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .payment-modal-overlay .checkbox-label {
            font-size: 15px;
            color: var(--dark-color);
        }
    </style>
@endsection
@section('main-content')

    <!-- States Table -->    
    <div class="states-table bg-white rounded-lg shadow-md overflow-hidden">
        <div class="states-table-container">
            <div class="states-table-header bg-blue-600 px-6 py-4 flex justify-between items-center">
                <h2 class="states-table-title text-white text-xl font-semibold" style="color: white">
                    <i class="fas fa-list mr-2"></i>Sale List
                </h2>
                @can('create sales')                
                <a href="{{ route('role.sales.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="btn btn-primary create-new-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add New Sale
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
                                    <label for="customer_id">Customer</label>
                                    <select id="customer_id" name="customer_id" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>                                        
                                        @foreach ($customers as $customer)
                                            <option value="{{ $customer->id }}" {{ $customer->id == request('customer_id') ? 'selected' : '' }}>{{ $customer->name }}</option>                                            
                                        @endforeach
                                    </select>
                                </div>                                
                                <div class="filter-group">
                                    <label for="company_id">Company</label>
                                    <select id="company_id" name="company_id" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>                                        
                                        @foreach ($companies as $company)
                                            <option value="{{ $company->id }}" {{ $company->id == request('company_id') ? 'selected' : '' }}>{{ $company->name }}</option>                                            
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
                                    <label for="search">Invoice No</label>
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice No</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Account</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                                @canany(['edit sales', 'delete sales', 'create return reference'])
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
                                        <strong>{{ $value->invoice_no }}</strong> <br>{{ $value->sale_date }} 
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->customer?->name }} <br>
                                        {{ $value->customer?->phone }}
                                    </td>  
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->total_amount }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->paid_amount }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->due_amount }}</td>     
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $value->bank?->name ?? '-' }}</td>                                                                                                                                         
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @php
                                            $statusColors = [
                                                'paid' => 'bg-green-100 text-green-800',
                                                'partial' => 'bg-yellow-100 text-yellow-800',
                                                'due' => 'bg-red-100 text-red-800',
                                            ];
                                            $statusClass = $statusColors[$value->payment_status] ?? 'bg-gray-100 text-gray-800';
                                        @endphp
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusClass }}">
                                            {{ ucfirst($value->payment_status) }}
                                        </span>
                                    </td>                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @php
                                            $methodColors = [
                                                'cash' => 'bg-blue-100 text-blue-800',
                                                'card' => 'bg-purple-100 text-purple-800',
                                                'bank' => 'bg-indigo-100 text-indigo-800',
                                            ];
                                            $methodClass = $methodColors[$value->payment_method] ?? 'bg-gray-100 text-gray-800';
                                        @endphp
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $methodClass }}">
                                            {{ ucfirst($value->payment_method) }}
                                        </span>
                                    </td>                                                        
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->createdBy?->name }}                                                                                                                        
                                    </td>
                                    @canany(['edit sales', 'delete sales', 'create return reference'])                                      
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="btn-group btn-group-sm flex space-x-1">
                                            <a target="_blank" href="{{ route('role.sales.view-invoice', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'id' => $value->id]) }}" class="btn btn-outline-info edit-item-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200" title="Invoice">
                                                <i class="fas fa-print"></i>
                                            </a>  
                                            @php $hasReturn = $value->returns->isNotEmpty(); @endphp

                                            @if ($value->due_amount > 0 && !$hasReturn)
                                                <button data-id="{{ $value->id }}" data-action="{{ route('role.sales.view.modal', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'id' => $value->id]) }}" class="open_payment_modal btn btn-outline-success edit-item-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200 cursor-pointer" title="Receive Payment">
                                                    <i class="fas fa-dollar-sign"></i>
                                                </button>
                                                <button onclick="openSaleSchedule({{ $value->id }}, '{{ addslashes($value->invoice_no) }}', {{ $value->due_amount }}, '{{ addslashes($value->customer?->name ?? '') }}')"
                                                        class="btn btn-outline-success edit-item-btn border border-indigo-500 text-indigo-500 hover:bg-indigo-500 hover:text-white px-3 py-1 rounded-md transition duration-200 cursor-pointer"
                                                        title="Schedule Payment">
                                                    <i class="fas fa-calendar-alt"></i>
                                                </button>
                                            @endif

                                            @can('edit sales')
                                                @if (!$hasReturn)
                                                    <a href="{{ route('role.sales.edit', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'sale' => $value->id]) }}" class="btn btn-outline-primary edit-item-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200" title="Edit Item">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                @endif
                                            @endcan

                                            @can('create return reference')
                                                @if (!$hasReturn)
                                                    <a href="{{ route('role.return-refs.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'sale' => $value->id]) }}" class="btn btn-outline-warning edit-item-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200" title="Return Item">
                                                        <i class="fas fa-undo"></i>
                                                    </a>
                                                @else
                                                    <span class="btn btn-outline-secondary px-3 py-1 rounded-md" title="Already Returned">
                                                        <i class="fas fa-undo"></i>
                                                    </span>
                                                @endif
                                            @endcan

                                            @can('delete sales')
                                                @if (!$hasReturn)
                                                    <button class="btn btn-outline-danger border border-red-500 text-red-500 hover:bg-red-500 hover:text-white px-3 py-1 rounded-md transition duration-200" onclick="confirmDelete('{{ $value->id }}', '{{ $value->invoice_no }}')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                @endif
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

    <!-- Delete Modal -->
    @include('sales.delete-modal')

    <!-- Payment Modal -->
    <div class="payment-modal-overlay" id="paymentModal">
        {{-- load via ajax --}}
    </div>

    {{-- ── Schedule Payment Modal ── --}}
    <div id="saleScheduleOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:12px;width:460px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;">
            <div style="background:#4338ca;padding:16px 20px;display:flex;justify-content:space-between;align-items:center;">
                <h4 style="color:#fff;font-size:15px;font-weight:700;margin:0;"><i class="fas fa-calendar-alt" style="margin-right:8px;"></i>Schedule Receivable Payment</h4>
                <button onclick="closeSaleSchedule()" style="background:rgba(255,255,255,.2);border:none;color:#fff;width:30px;height:30px;border-radius:6px;cursor:pointer;font-size:16px;">&times;</button>
            </div>
            <form id="saleScheduleForm" method="POST"
                  action="{{ route('role.payment-schedules.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}">
                @csrf
                <input type="hidden" name="schedulable_type" value="sale">
                <input type="hidden" name="schedulable_id" id="ss_sale_id">
                <div style="padding:20px;">
                    <div style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;">
                        <div><strong>Customer:</strong> <span id="ss_customer" style="color:#3730a3;font-weight:700;"></span></div>
                        <div style="margin-top:3px;"><strong>Invoice:</strong> <span id="ss_invoice" style="font-family:monospace;"></span>
                            &nbsp;|&nbsp; <strong>Due:</strong> <span id="ss_due" style="color:#dc2626;font-weight:700;"></span></div>
                    </div>

                    <div id="ss_rows">
                        <div class="ss-row" style="display:grid;grid-template-columns:1fr 1fr auto;gap:8px;margin-bottom:8px;align-items:end;">
                            <div>
                                <label style="font-size:11px;font-weight:700;color:#475569;display:block;margin-bottom:3px;">Amount (৳) *</label>
                                <input type="number" name="schedules[0][amount]" step="0.01" min="0.01" required
                                       style="width:100%;border:1.5px solid #e2e8f0;border-radius:6px;padding:8px 10px;font-size:13px;">
                            </div>
                            <div>
                                <label style="font-size:11px;font-weight:700;color:#475569;display:block;margin-bottom:3px;">Due Date *</label>
                                <input type="date" name="schedules[0][date]" required
                                       value="{{ date('Y-m-d', strtotime('+7 days')) }}"
                                       style="width:100%;border:1.5px solid #e2e8f0;border-radius:6px;padding:8px 10px;font-size:13px;">
                            </div>
                            <div style="padding-bottom:1px;">
                                <button type="button" disabled style="width:30px;height:36px;background:#f1f5f9;border:none;border-radius:6px;color:#94a3b8;cursor:default;"><i class="fas fa-trash-alt"></i></button>
                            </div>
                        </div>
                    </div>

                    <button type="button" onclick="addSsRow()" style="padding:6px 14px;background:#eff6ff;border:1.5px dashed #93c5fd;color:#2563eb;border-radius:7px;cursor:pointer;font-size:12px;font-weight:600;margin-bottom:14px;">
                        <i class="fas fa-plus" style="margin-right:4px;"></i>Add Another Date
                    </button>

                    <div>
                        <label style="font-size:11px;font-weight:700;color:#475569;display:block;margin-bottom:3px;">Note (Optional)</label>
                        <input type="text" name="schedules[0][note]" placeholder="e.g. Payment split for Q1…"
                               style="width:100%;border:1.5px solid #e2e8f0;border-radius:6px;padding:8px 10px;font-size:13px;">
                    </div>
                </div>
                <div style="padding:12px 20px;background:#f8fafc;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px;">
                    <button type="button" onclick="closeSaleSchedule()" style="padding:8px 18px;border-radius:7px;background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;cursor:pointer;font-weight:600;">Cancel</button>
                    <button type="submit" style="padding:8px 20px;border-radius:7px;background:#4338ca;color:#fff;border:none;cursor:pointer;font-weight:700;"><i class="fas fa-calendar-check" style="margin-right:5px;"></i>Save Schedule</button>
                </div>
            </form>
        </div>
    </div>

@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Get modal and buttons
        const paymentModal = document.getElementById('paymentModal');
        const closeModal2Btn = document.getElementById('closeModalBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const paymentAmountInput = document.getElementById('paymentAmount');
        const openPaymentModalBtn = document.querySelectorAll('.open_payment_modal');
        
        $(document).on('click', '.open_payment_modal', function (e) {
            e.preventDefault();
            
            const $btn = $(this);
            const modalUrl = $btn.data('action');
            if ($btn.hasClass('is-loading')) return;
            $btn.addClass('is-loading').prop('disabled', true);

            $.ajax({
                url: modalUrl,
                method: 'GET',
                success: function (response) {
                    if (response.success) {
                        const $modal = $('#paymentModal');
                        $modal.html(response.data.html).addClass('active');
                        $('body').css('overflow', 'hidden');
                    } else {
                        showError(response.message || 'Something went wrong!');
                    }
                },
                error: function (xhr) {
                    console.error('❌ Error:', xhr.status, xhr.responseText);
                    showError('Something went wrong. Please try again.');
                },
                complete: function () {
                    $btn.removeClass('is-loading').prop('disabled', false);
                }
            });  

        });               
        
        // Close modal functions
        function closeModal2() {            
            $('#paymentModal').removeClass('active');           
            $('body').css('overflow', 'auto'); 
        }      
        function showError(text) {
            Swal.fire({
                icon: "error",
                title: "Oops...",
                text: text,
            });
        }                                 
    </script>
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
                window.location = '{{ route('role.sales.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}';
            });
        });
    </script>

    <script>
        // ── Sale Schedule Modal ──────────────────────────
        let _ssRowCount = 1;

        function openSaleSchedule(saleId, invoiceNo, dueAmt, customerName) {
            document.getElementById('ss_sale_id').value       = saleId;
            document.getElementById('ss_customer').textContent = customerName || '—';
            document.getElementById('ss_invoice').textContent  = invoiceNo;
            document.getElementById('ss_due').textContent      = '৳ ' + parseFloat(dueAmt).toLocaleString('en-BD', {minimumFractionDigits:2});
            // Reset rows
            _ssRowCount = 1;
            document.getElementById('ss_rows').innerHTML = buildSsRow(0, parseFloat(dueAmt).toFixed(2));
            const overlay = document.getElementById('saleScheduleOverlay');
            overlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeSaleSchedule() {
            document.getElementById('saleScheduleOverlay').style.display = 'none';
            document.body.style.overflow = '';
        }

        function buildSsRow(idx, amount) {
            const isFirst = idx === 0;
            return `<div class="ss-row" style="display:grid;grid-template-columns:1fr 1fr auto;gap:8px;margin-bottom:8px;align-items:end;">
                <div>
                    <label style="font-size:11px;font-weight:700;color:#475569;display:block;margin-bottom:3px;">Amount (৳) *</label>
                    <input type="number" name="schedules[${idx}][amount]" step="0.01" min="0.01" value="${amount}" required
                           style="width:100%;border:1.5px solid #e2e8f0;border-radius:6px;padding:8px 10px;font-size:13px;">
                </div>
                <div>
                    <label style="font-size:11px;font-weight:700;color:#475569;display:block;margin-bottom:3px;">Due Date *</label>
                    <input type="date" name="schedules[${idx}][date]" required
                           value="{{ date('Y-m-d', strtotime('+7 days')) }}"
                           style="width:100%;border:1.5px solid #e2e8f0;border-radius:6px;padding:8px 10px;font-size:13px;">
                </div>
                <div style="padding-bottom:1px;">
                    <button type="button" ${isFirst ? 'disabled' : `onclick="removeSsRow(this)"`}
                            style="width:30px;height:36px;background:${isFirst ? '#f1f5f9' : '#fee2e2'};border:none;border-radius:6px;color:${isFirst ? '#94a3b8' : '#b91c1c'};cursor:${isFirst ? 'default' : 'pointer'};">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>`;
        }

        function addSsRow() {
            document.getElementById('ss_rows').insertAdjacentHTML('beforeend', buildSsRow(_ssRowCount, ''));
            _ssRowCount++;
        }

        function removeSsRow(btn) {
            btn.closest('.ss-row').remove();
        }

        document.getElementById('saleScheduleOverlay').addEventListener('click', function(e) {
            if (e.target === this) closeSaleSchedule();
        });
    </script>
@endsection
