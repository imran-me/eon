@extends('layout.app')
@section('meta-information')
    <title>Direct Ticket Purchases</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .bg-draft     { background-color: #f3f4f6; color: #1f2937; border: 1px solid #d1d5db; }
    .bg-pending   { background-color: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
    .bg-confirm   { background-color: #dcfce7; color: #166534; border: 1px solid #86efac; }
    .bg-cancelled { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .bg-info-custom { background-color: #e0f2fe; color: #075985; border: 1px solid #7dd3fc; }
    .bg-paid      { background-color: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
    .bg-partial   { background-color: #fef9c3; color: #713f12; border: 1px solid #fde047; }
    .bg-due       { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

    .modal { transition: opacity 0.25s ease; }
    .modal-backdrop { background-color: rgba(0,0,0,0.5); }

    .states-table { margin-top: 2rem; }
    .states-table .states-table-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
        margin: 0; font-size: 1.25rem; font-weight: 600; color: #333;
    }
    .states-table .states-table-header .btn {
        border-radius: 8px; padding: 0.5rem 1rem; font-weight: 500;
    }
    .states-table .states-table-content { padding: 0; }
    .states-table .states-table-content .table-responsive { overflow-x: auto; }
    .states-table .states-table-content .table {
        margin-bottom: 0; border-collapse: separate; border-spacing: 0;
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
    .states-table .states-table-content .table tbody tr:hover { background-color: #f8f9fa; }
    .states-table .states-table-content .badge {
        font-size: 0.75rem; padding: 0.375rem 0.75rem; border-radius: 6px; font-weight: 500;
    }
    .states-table-header {
        background: linear-gradient(90deg, #1e3a8a 0%, #1e40af 100%);
        color: white;
    }
    .states-table .states-table-content .btn-group { border-radius: 6px; overflow: hidden; }
    .states-table .states-table-content .btn-group .btn { border-radius: 0; padding: 0.375rem 0.75rem; }
    .states-table .states-table-content .btn-group .btn:first-child { border-top-left-radius: 6px; border-bottom-left-radius: 6px; }
    .states-table .states-table-content .btn-group .btn:last-child { border-top-right-radius: 6px; border-bottom-right-radius: 6px; }
    .states-table .states-table-content .pagination { margin-bottom: 0; padding: 1rem; }

    /* Filter container */
    .filter-container {
        margin: 15px 15px 0 15px;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
    .filter-container .filter-header:hover { background-color: #e9ecef; }
    .filter-container .filter-header h3 { margin: 0; font-size: 18px; font-weight: 600; color: #1f2937; }
    .filter-container .filter-header .toggle-icon { transition: transform 0.3s; }
    .filter-container .filter-header.active .toggle-icon { transform: rotate(180deg); }
    .filter-container .filter-content {
        background-color: white;
        padding: 0;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out, padding 0.3s ease-out;
    }
    .filter-container .filter-content.active { padding: 20px; max-height: 500px; }
    .filter-container .filter-row { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 16px; }
    .filter-container .filter-group { flex: 1; min-width: 200px; }
    .filter-container .filter-group label { display: block; margin-bottom: 6px; font-weight: 500; color: #374151; }
    .filter-container .filter-group select,
    .filter-container .filter-group input {
        width: 100%; padding: 10px; border: 1px solid #d1d5db;
        border-radius: 6px; font-size: 14px; transition: border-color 0.3s;
    }
    .filter-container .filter-group select:focus,
    .filter-container .filter-group input:focus {
        outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
    }
    .filter-container .filter-actions {
        display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;
    }
    .filter-container .filter-actions button {
        padding: 10px 20px; border-radius: 6px; font-weight: 500; cursor: pointer; transition: all 0.3s;
    }
    .filter-container .filter-actions .apply-btn { background-color: #3b82f6; color: white; border: none; }
    .filter-container .filter-actions .apply-btn:hover { background-color: #2563eb; }
    .filter-container .filter-actions .reset-btn { background-color: #f8f9fa; color: #6b7280; border: 1px solid #d1d5db; }
    .filter-container .filter-actions .reset-btn:hover { background-color: #e5e7eb; }

    .select2-container .select2-selection--single { height: 42px; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 40px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 42px; position: absolute; top: 1px; right: 3px; width: 20px;
    }
    span [aria-current="page"] span {
        background-color: #2563eb !important;
        background: #2563eb !important;
        color: white;
        border-color: #2563eb;
    }

    /* Payment modal */
    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9998; align-items:center; justify-content:center; }
    .modal-box { background:#fff; border-radius:10px; padding:28px; width:440px; max-width:95vw; z-index:9999; box-shadow:0 8px 32px rgba(0,0,0,.2); }
    .modal-title { font-size:1.05rem; font-weight:700; margin-bottom:16px; }
    .form-row { margin-bottom:12px; }
    .form-row label { display:block; font-size:.82rem; font-weight:600; color:#374151; margin-bottom:4px; }
    .form-row input, .form-row select { width:100%; padding:7px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:.9rem; }

    @media (max-width: 768px) {
        .states-table .states-table-header { flex-direction: column; gap: 1rem; align-items: flex-start; }
        .states-table .states-table-header .btn { width: 100%; }
    }
</style>
@endsection

@section('main-content')
<div class="states-table bg-white rounded-lg shadow-md overflow-hidden">
    <div class="states-table-container">

        {{-- Header --}}
        <div class="states-table-header bg-blue-600 px-6 py-4 flex justify-between items-center">
            <h2 class="states-table-title text-white text-xl font-semibold" style="color:white">
                <i class="fas fa-ticket-alt mr-2"></i> Direct Ticket Purchases
            </h2>
            @can('create ticket direct sale')
            <a href="{{ route('role.ticket-direct-sale.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
               class="btn btn-primary create-new-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                <i class="fas fa-plus mr-2"></i> New Direct Purchase
            </a>
            @endcan
        </div>

        <div class="states-table-content">

            {{-- Flash messages --}}
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-2 mx-4 mt-4 rounded">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-2 mx-4 mt-4 rounded">{{ session('error') }}</div>
            @endif

            <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-2 mx-4 mt-4 rounded text-sm">
                <i class="fas fa-info-circle mr-1"></i> Purchases created here are confirmed automatically. Sell them anytime from <strong>Manage Sales</strong>.
            </div>

            {{-- Filters --}}
            <form action="" method="get">
                <div class="filter-container">
                    <div class="filter-header">
                        <h3><i class="fas fa-filter mr-2"></i>Filter Options</h3>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="filter-content">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="ticket_no">PNR / Ticket No</label>
                                <input type="text" name="ticket_no" id="ticket_no" value="{{ request('ticket_no') }}" placeholder="Search PNR..." class="form-control">
                            </div>
                            <div class="filter-group">
                                <label for="ticket_id">Ticket Route</label>
                                <select id="ticket_id" name="ticket_id" class="form-control select2" style="width:100%">
                                    <option value="">All Routes</option>
                                    @foreach($tickets as $t)
                                        <option value="{{ $t->id }}" @selected(request('ticket_id') == $t->id)>{{ $t->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="purchase_date">Purchase Date</label>
                                <input type="date" name="purchase_date" id="purchase_date" value="{{ request('purchase_date') }}" class="form-control">
                            </div>
                            <div class="filter-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="form-control select2" style="width:100%">
                                    <option value="">All Statuses</option>
                                    <option value="confirm"   @selected(request('status')=='confirm')>Confirm</option>
                                    <option value="pending"   @selected(request('status')=='pending')>Pending</option>
                                    <option value="cancelled" @selected(request('status')=='cancelled')>Cancelled</option>
                                    <option value="draft"     @selected(request('status')=='draft')>Draft</option>
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

            {{-- Table --}}
            <div class="table-responsive overflow-x-auto" style="padding:15px">
                <table class="table table-hover min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th style="padding-left:20px" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SL</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Passenger / PNR</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ticket Route</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vendor / Portal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purchase Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid / Due</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($datas as $i => $purchase)
                            @php
                                $role   = Str::slug(Auth::user()->getRoleNames()->first());
                                $pBadge = ['paid'=>'bg-paid','partial'=>'bg-partial','due'=>'bg-due'];
                                $pClass = $pBadge[$purchase->payment_status] ?? 'bg-draft';
                                $sBadge = ['draft'=>'bg-draft','pending'=>'bg-pending','confirm'=>'bg-confirm','cancelled'=>'bg-cancelled'];
                                $sClass = $sBadge[$purchase->status] ?? 'bg-draft';
                                $alreadySold = $purchase->ticket_sale_item_exists ?? null;
                            @endphp
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap" style="padding-left:20px">
                                    {{ $datas->firstItem() + $i }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium">{{ $purchase->passportHolder?->name ?? '—' }}</div>
                                    <div class="text-xs text-gray-500">{{ $purchase->ticket_no }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $purchase->ticket?->title ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $purchase->vendor?->name ?? $purchase->portal?->name ?? '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ \Carbon\Carbon::parse($purchase->purchase_date)->format('d M Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap font-semibold">৳{{ number_format($purchase->amount, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    Paid: <span style="color:rgb(37,151,37)">৳{{ number_format($purchase->paid_amount, 2) }}</span><br>
                                    Due: <span style="color:rgb(255,81,81)">৳{{ number_format($purchase->due_amount, 2) }}</span><br>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $pClass }}">
                                        {{ ucfirst($purchase->payment_status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $sClass }}">
                                        {{ ucfirst($purchase->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="btn-group btn-group-sm flex justify-center space-x-1">

                                        {{-- Pay vendor --}}
                                        @if($purchase->due_amount > 0)
                                        <button onclick="openPayModal({{ $purchase->id }}, '{{ $purchase->ticket_no }}', {{ $purchase->due_amount }})"
                                                class="btn border border-red-500 text-red-500 hover:bg-red-500 hover:text-white px-3 py-1 rounded-md transition duration-200"
                                                title="Pay vendor due">
                                            <i class="fas fa-credit-card"></i>
                                        </button>
                                        @endif

                                        {{-- Schedule payment --}}
                                        @if($purchase->due_amount > 0)
                                        <button onclick="openScheduleModal({{ $purchase->id }}, {{ $purchase->due_amount }})"
                                                class="btn border border-indigo-500 text-indigo-500 hover:bg-indigo-500 hover:text-white px-3 py-1 rounded-md transition duration-200"
                                                title="Schedule vendor payment">
                                            <i class="fas fa-calendar-alt"></i>
                                        </button>
                                        @endif

                                        {{-- Edit --}}
                                        @can('edit ticket direct sale')
                                        <a href="{{ route('role.ticket-direct-sale.edit', ['role' => $role, 'ticket_direct_sale' => $purchase->id]) }}"
                                           class="btn btn-outline-primary border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-3 py-1 rounded-md transition duration-200"
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @endcan

                                        {{-- Delete --}}
                                        @can('delete ticket direct sale')
                                        <button onclick="confirmDelete({{ $purchase->id }})"
                                                class="btn btn-outline-danger border border-red-500 text-red-500 hover:bg-red-500 hover:text-white px-3 py-1 rounded-md transition duration-200"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-8">
                                <i class="fas fa-inbox fa-3x text-gray-400 mb-4"></i>
                                <h4 class="text-gray-500 text-xl font-medium mb-2">No data found</h4>
                                <p class="text-gray-400 mb-4">Try filtering with different criteria.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="p-3 border-t border-gray-200">
                {{ $datas->appends(request()->all())->links() }}
            </div>
        </div>
    </div>
</div>

{{-- ================================================================ --}}
{{--  MODAL: Make Payment (purchase due to vendor)                    --}}
{{-- ================================================================ --}}
<div id="payModalOverlay" class="modal-overlay" onclick="closeModal('pay')">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-title text-red-700"><i class="fas fa-credit-card mr-2"></i>Pay Vendor Due</div>
        <p class="text-xs text-gray-500 mb-3">Ticket No: <strong id="payTicketNo"></strong> — Due: <strong id="payDue"></strong></p>
        <form id="payForm" method="POST">
            @csrf
            <div class="form-row">
                <label>Amount *</label>
                <input type="number" name="payment_amount" id="payAmount" step="0.01" min="0.01" required>
            </div>
            <div class="form-row">
                <label>Bank Account *</label>
                <select name="bank_id" required class="select2m">
                    <option value="">Select bank</option>
                    @foreach(\App\Models\Bank::where('status',1)->get() as $b)
                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-row">
                <label>Payment Method *</label>
                <select name="payment_method" required>
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="mobile_banking">Mobile Banking</option>
                    <option value="advance">Advance</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-row">
                <label>Payment Date *</label>
                <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" required>
            </div>
            <div class="form-row">
                <label>Reference No</label>
                <input type="text" name="reference_no" placeholder="Optional">
            </div>
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="closeModal('pay')"
                        class="px-4 py-2 bg-gray-200 rounded-md text-sm font-semibold">Cancel</button>
                <button type="submit"
                        class="px-5 py-2 bg-red-600 text-white rounded-md text-sm font-semibold">Confirm Payment</button>
            </div>
        </form>
    </div>
</div>

{{-- ================================================================ --}}
{{--  MODAL: Schedule Vendor Payment                                  --}}
{{-- ================================================================ --}}
<div id="scheduleModalOverlay" class="modal-overlay" onclick="closeModal('schedule')">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-title text-indigo-700"><i class="fas fa-calendar-alt mr-2"></i>Schedule Vendor Payment</div>
        <form id="scheduleForm" method="POST"
              action="{{ route('role.payment-schedules.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}">
            @csrf
            <input type="hidden" name="schedulable_type" value="ticket_purchase">
            <input type="hidden" name="schedulable_id" id="schedId">
            <div class="form-row">
                <label>Amount *</label>
                <input type="number" name="schedules[0][amount]" step="0.01" min="0.01" required id="schedAmount">
            </div>
            <div class="form-row">
                <label>Scheduled Date *</label>
                <input type="date" name="schedules[0][date]" value="{{ date('Y-m-d', strtotime('+7 days')) }}" required>
            </div>
            <div class="form-row">
                <label>Note</label>
                <input type="text" name="schedules[0][note]" placeholder="Optional note">
            </div>
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="closeModal('schedule')"
                        class="px-4 py-2 bg-gray-200 rounded-md text-sm font-semibold">Cancel</button>
                <button type="submit"
                        class="px-5 py-2 bg-indigo-600 text-white rounded-md text-sm font-semibold">Save Schedule</button>
            </div>
        </form>
    </div>
</div>

{{-- Hidden delete form --}}
<form id="deleteForm" method="POST" style="display:none">
    @csrf @method('DELETE')
</form>

@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function () {
        $('.select2').select2();
        $('.select2m').select2({ dropdownParent: $('body') });
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
    });

    const role = '{{ Str::slug(Auth::user()->getRoleNames()->first()) }}';

    // Pay modal
    function openPayModal(purchaseId, ticketNo, dueAmt) {
        document.getElementById('payTicketNo').textContent = ticketNo;
        document.getElementById('payDue').textContent      = '৳' + parseFloat(dueAmt).toFixed(2);
        document.getElementById('payAmount').value         = parseFloat(dueAmt).toFixed(2);
        document.getElementById('payAmount').max           = dueAmt;
        document.getElementById('payForm').action          = '/' + role + '/ticket-purchase/make/payment/' + purchaseId;
        document.getElementById('payModalOverlay').style.display = 'flex';
    }

    // Schedule modal
    function openScheduleModal(purchaseId, purchaseDue) {
        document.getElementById('schedId').value     = purchaseId;
        document.getElementById('schedAmount').value = parseFloat(purchaseDue).toFixed(2);
        document.getElementById('schedAmount').max   = purchaseDue;
        document.getElementById('scheduleModalOverlay').style.display = 'flex';
    }

    function closeModal(which) {
        document.getElementById(which + 'ModalOverlay').style.display = 'none';
    }

    // Delete
    function confirmDelete(id) {
        Swal.fire({
            title:             'Delete this record?',
            text:              'This will reverse all journals, transactions, and stock movements.',
            icon:              'warning',
            showCancelButton:  true,
            confirmButtonColor:'#dc2626',
            confirmButtonText: 'Yes, delete it',
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.getElementById('deleteForm');
                form.action = '/' + role + '/ticket-direct-sale/' + id;
                form.submit();
            }
        });
    }

    // Filter toggle
    document.addEventListener('DOMContentLoaded', function () {
        const filterHeader  = document.querySelector('.filter-container .filter-header');
        const filterContent = document.querySelector('.filter-container .filter-content');

        @php
            $hasFilters = collect(['ticket_no','ticket_id','purchase_date','status'])->contains(fn($k) => request($k));
        @endphp
        @if($hasFilters)
        filterHeader.classList.add('active');
        filterContent.classList.add('active');
        @endif

        filterHeader.addEventListener('click', function () {
            this.classList.toggle('active');
            filterContent.classList.toggle('active');
        });

        document.querySelector('.reset-btn').addEventListener('click', function (e) {
            e.preventDefault();
            window.location = '{{ route('role.ticket-direct-sale.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}';
        });
    });
</script>
@endsection
