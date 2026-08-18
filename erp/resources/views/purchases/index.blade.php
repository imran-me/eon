@extends('layout.app')
@section('meta-information')
    <title>Manage Purchases</title>
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
                    <i class="fas fa-list mr-2"></i>Purchase List
                </h2>
                @can('create purchases')                
                <a href="{{ route('role.purchases.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="btn btn-primary create-new-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add New Purchase
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
                                    <label for="supplier_id">Supplier</label>
                                    <select id="supplier_id" name="supplier_id" class="form-control select2" style="width: 100%">
                                        <option value="">All</option>                                        
                                        @foreach ($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}" {{ $supplier->id == request('supplier_id') ? 'selected' : '' }}>{{ $supplier->name }}</option>                                            
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
                                    <label for="search">Purchase No</label>
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purchase No</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Account</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                                @canany(['edit purchases', 'delete purchases'])
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
                                        <strong>{{ $value->purchase_no }}</strong> <br>{{ $value->purchase_date }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->supplier?->name }} <br>
                                        {{ $value->supplier?->phone }}
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
                                    </td>                                                                                                                                                                                                                                                                                                                                                                                                                                                                </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $value->createdBy?->name }}                                                                                                                        
                                    </td>
                                    @canany(['edit purchases', 'delete purchases'])                                      
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="btn-group btn-group-sm flex space-x-1">
                                            <a target="_blank" href="{{ route('role.purchases.view-invoice', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'id' => $value->id]) }}" class="btn btn-outline-info edit-item-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200" title="Invoice">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            @if($value->due_amount > 0)
                                            <button onclick="openPurchaseSchedule({{ $value->id }}, '{{ addslashes($value->purchase_no) }}', {{ $value->due_amount }}, '{{ addslashes($value->supplier?->name ?? '') }}')"
                                                    class="btn btn-outline-success edit-item-btn border border-indigo-500 text-indigo-500 hover:bg-indigo-500 hover:text-white px-3 py-1 rounded-md transition duration-200 cursor-pointer"
                                                    title="Schedule Payment">
                                                <i class="fas fa-calendar-alt"></i>
                                            </button>
                                            @endif
                                            @can('edit purchases')
                                            <a href="{{ route('role.purchases.edit', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'purchase' => $value->id]) }}" class="btn btn-outline-primary edit-item-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200" title="Edit Item">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endcan
                                            @can('delete purchases')
                                            <button class="btn btn-outline-danger border border-red-500 text-red-500 hover:bg-red-500 hover:text-white px-3 py-1 rounded-md transition duration-200" onclick="confirmDelete('{{ $value->id }}', '{{ $value->purchase_no }}')">
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

    @include('purchases.delete-modal')

    {{-- ── Schedule Payable Modal ── --}}
    <div id="purchaseScheduleOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:12px;width:460px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;">
            <div style="background:#d97706;padding:16px 20px;display:flex;justify-content:space-between;align-items:center;">
                <h4 style="color:#fff;font-size:15px;font-weight:700;margin:0;"><i class="fas fa-calendar-alt" style="margin-right:8px;"></i>Schedule Payable Payment</h4>
                <button onclick="closePurchaseSchedule()" style="background:rgba(255,255,255,.2);border:none;color:#fff;width:30px;height:30px;border-radius:6px;cursor:pointer;font-size:16px;">&times;</button>
            </div>
            <form id="purchaseScheduleForm" method="POST"
                  action="{{ route('role.payment-schedules.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}">
                @csrf
                <input type="hidden" name="schedulable_type" value="purchase">
                <input type="hidden" name="schedulable_id" id="ps_purchase_id">
                <div style="padding:20px;">
                    <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;">
                        <div><strong>Supplier:</strong> <span id="ps_supplier" style="color:#92400e;font-weight:700;"></span></div>
                        <div style="margin-top:3px;"><strong>PO / Bill:</strong> <span id="ps_purchase_no" style="font-family:monospace;"></span>
                            &nbsp;|&nbsp; <strong>Due:</strong> <span id="ps_due" style="color:#dc2626;font-weight:700;"></span></div>
                    </div>

                    <div id="ps_rows">
                        <div class="ps-row" style="display:grid;grid-template-columns:1fr 1fr auto;gap:8px;margin-bottom:8px;align-items:end;">
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

                    <button type="button" onclick="addPsRow()" style="padding:6px 14px;background:#fffbeb;border:1.5px dashed #fcd34d;color:#d97706;border-radius:7px;cursor:pointer;font-size:12px;font-weight:600;margin-bottom:14px;">
                        <i class="fas fa-plus" style="margin-right:4px;"></i>Add Another Date
                    </button>

                    <div>
                        <label style="font-size:11px;font-weight:700;color:#475569;display:block;margin-bottom:3px;">Note (Optional)</label>
                        <input type="text" name="schedules[0][note]" placeholder="e.g. Payment installment 1 of 3…"
                               style="width:100%;border:1.5px solid #e2e8f0;border-radius:6px;padding:8px 10px;font-size:13px;">
                    </div>
                </div>
                <div style="padding:12px 20px;background:#f8fafc;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px;">
                    <button type="button" onclick="closePurchaseSchedule()" style="padding:8px 18px;border-radius:7px;background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;cursor:pointer;font-weight:600;">Cancel</button>
                    <button type="submit" style="padding:8px 20px;border-radius:7px;background:#d97706;color:#fff;border:none;cursor:pointer;font-weight:700;"><i class="fas fa-calendar-check" style="margin-right:5px;"></i>Save Schedule</button>
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
                window.location = '{{ route('role.purchases.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}';
            });
        });
    </script>

    <script>
        // ── Purchase Schedule Modal ──────────────────────
        let _psRowCount = 1;

        function openPurchaseSchedule(purchaseId, purchaseNo, dueAmt, supplierName) {
            document.getElementById('ps_purchase_id').value   = purchaseId;
            document.getElementById('ps_supplier').textContent  = supplierName || '—';
            document.getElementById('ps_purchase_no').textContent = purchaseNo;
            document.getElementById('ps_due').textContent      = '৳ ' + parseFloat(dueAmt).toLocaleString('en-BD', {minimumFractionDigits:2});
            _psRowCount = 1;
            const firstAmtInput = document.querySelector('#ps_rows input[type="number"]');
            if (firstAmtInput) firstAmtInput.value = parseFloat(dueAmt).toFixed(2);
            const overlay = document.getElementById('purchaseScheduleOverlay');
            overlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closePurchaseSchedule() {
            document.getElementById('purchaseScheduleOverlay').style.display = 'none';
            document.body.style.overflow = '';
        }

        function buildPsRow(idx) {
            return `<div class="ps-row" style="display:grid;grid-template-columns:1fr 1fr auto;gap:8px;margin-bottom:8px;align-items:end;">
                <div>
                    <label style="font-size:11px;font-weight:700;color:#475569;display:block;margin-bottom:3px;">Amount (৳) *</label>
                    <input type="number" name="schedules[${idx}][amount]" step="0.01" min="0.01" required
                           style="width:100%;border:1.5px solid #e2e8f0;border-radius:6px;padding:8px 10px;font-size:13px;">
                </div>
                <div>
                    <label style="font-size:11px;font-weight:700;color:#475569;display:block;margin-bottom:3px;">Due Date *</label>
                    <input type="date" name="schedules[${idx}][date]" required
                           value="{{ date('Y-m-d', strtotime('+7 days')) }}"
                           style="width:100%;border:1.5px solid #e2e8f0;border-radius:6px;padding:8px 10px;font-size:13px;">
                </div>
                <div style="padding-bottom:1px;">
                    <button type="button" onclick="removePsRow(this)"
                            style="width:30px;height:36px;background:#fee2e2;border:none;border-radius:6px;color:#b91c1c;cursor:pointer;">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>`;
        }

        function addPsRow() {
            document.getElementById('ps_rows').insertAdjacentHTML('beforeend', buildPsRow(_psRowCount));
            _psRowCount++;
        }

        function removePsRow(btn) { btn.closest('.ps-row').remove(); }

        document.getElementById('purchaseScheduleOverlay').addEventListener('click', function(e) {
            if (e.target === this) closePurchaseSchedule();
        });
    </script>
@endsection
