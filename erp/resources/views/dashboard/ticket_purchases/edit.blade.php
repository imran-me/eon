@extends('layout.app')
@section('meta-information')
    <title>Update Purchase</title>
@endsection
@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @include('dashboard.ticket_purchases.edit-css')
@endsection
@section('main-content')
@include('layout.ticketing-tabs')

    <div class="states-table bg-white rounded-lg shadow-md overflow-hidden" style="margin-top: 0">
        <div class="states-table-container">
            <div class="states-table-header bg-blue-600 px-6 pb-4 flex justify-between items-center">
                <h2 class="states-table-title text-white text-xl font-semibold" style="color: white">
                    <i class="fas fa-plus mr-2"></i> Add New Purchase
                </h2>
                <a href="{{ route('role.ticket-purchase.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="btn btn-primary create-new-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-list mr-2"></i> Purchase List
                </a>
            </div>                                                          
            <div class="states-table-content" style="padding: 15px;">
                <div class="common-modal-body modal-body overflow-y-auto mt-2">
                    <form class="closest" id="purchaseCreateForm" action="{{ route('role.ticket-purchase.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'ticket_purchase' => $data->id]) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="section-divider pt-0 mt-0 pb-2" style="margin-top: 0">
                            <h2 class="text-xl font-semibold text-gray-800">Purchase Information</h2>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 pt-4" style="justify-content: center">   
                            <div class="mb-2">
                                <label for="passport_holder_id" class="block text-gray-700 text-sm font-bold mb-2">Passport Holder*</label>
                                <select id="passport_holder_id" required name="passport_holder_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">                                      
                                    @foreach ($passport_holders as $passport_holder)
                                        <option value="{{ $passport_holder->id }}" {{ $data->passport_holder_id == $passport_holder->id ? 'selected' : '' }}>{{ $passport_holder->name }}</option>                                            
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label for="ticket_type" class="block text-gray-700 text-sm font-bold mb-2">Ticket Type*</label>
                                <select id="ticket_type" required name="ticket_type" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                    <option value="air" {{ $data->ticket_type == 'air' ? 'selected' : '' }}>Air</option>
                                    <option value="bus" {{ $data->ticket_type == 'bus' ? 'selected' : '' }}>Bus</option>
                                    <option value="train" {{ $data->ticket_type == 'train' ? 'selected' : '' }}>Train</option>
                                    <option value="other" {{ $data->ticket_type == 'other' ? 'selected' : '' }}>Other</option>     
                                </select>
                            </div>
                            <div class="mb-2">
                                <label for="trip_type" class="block text-gray-700 text-sm font-bold mb-2">Trip Type*</label>
                                <select id="trip_type" required name="trip_type" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                    <option value="one-way" {{ $data->trip_type == 'one-way' ? 'selected' : '' }}>One-way</option>
                                    <option value="two-way" {{ $data->trip_type == 'two-way' ? 'selected' : '' }}>Two-way</option>
                                    <option value="multi-city" {{ $data->trip_type == 'multi-city' ? 'selected' : '' }}>Multi-city</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label for="status" class="block text-gray-700 text-sm font-bold mb-2">Status*</label>
                                <select id="status" required name="status" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 select2" style="width: 100%">
                                    <option value="pending" {{ $data->status == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="confirm" {{ $data->status == 'confirm' ? 'selected' : '' }}>Confirm</option>
                                    <option value="cancelled" {{ $data->status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                    <option value="draft" {{ $data->status == 'draft' ? 'selected' : '' }}>Draft</option>
                                </select>
                                @if($data->status === 'cancelled')
                                    <p class="text-xs text-red-600 mt-1">Cancelled{{ $data->refund_amount !== null ? ' — airline refund ৳' . number_format((float) $data->refund_amount, 2) . ', penalty ৳' . number_format((float) $data->amount - (float) $data->refund_amount, 2) : '' }}. A cancelled ticket cannot be reopened.</p>
                                @endif
                            </div>
                            @if($data->status !== 'cancelled')
                            {{-- Shown when Status is switched to Cancelled: what the airline
                                 gives back. Blank = the whole amount. The rest is the penalty;
                                 nothing already posted is deleted (2026-09-28). --}}
                            <div class="mb-2" id="refundBox" style="display:none;">
                                <label for="refund_amount" class="block text-gray-700 text-sm font-bold mb-2">Airline refund</label>
                                <input type="number" step="0.01" min="0" max="{{ (float) $data->amount }}" id="refund_amount" name="refund_amount" value="{{ number_format((float) $data->amount, 2, '.', '') }}" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">Ticket cost ৳{{ number_format((float) $data->amount, 2) }}. Penalty = cost − refund. The refund is added to the vendor's balance (or the portal wallet); nothing already posted is removed.</p>
                            </div>
                            @endif
                            <div class="mb-2">
                                <label for="currency" class="block text-gray-700 text-sm font-bold mb-2">Currency*</label>
                                <input type="text" id="currency" required value="{{ $data->currency ?? '৳' }}" name="currency" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="mb-2">
                                <label for="purchase_date" class="block text-gray-700 text-sm font-bold mb-2">Purchase Date*</label>
                                <input type="date" id="purchase_date" required value="{{ $data->purchase_date ?? date('Y-m-d') }}" name="purchase_date" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="mb-2">
                                <label for="ticket_no" class="block text-gray-700 text-sm font-bold mb-2">Ticket No*</label>
                                <input type="text" name="ticket_no" required value="{{ $data->ticket_no }}" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="mb-2">
                                <label for="name_ticket_no" class="block text-gray-700 text-sm font-bold mb-2">Name Ticket No.</label>
                                <input type="text" id="name_ticket_no" name="name_ticket_no" maxlength="100" value="{{ $data->name_ticket_no ?? '' }}" placeholder="e.g. 997-2345678901" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>                            
                            <div class="mb-2">
                                <label for="attachment" class="block text-gray-700 text-sm font-bold mb-2">Attachment</label>
                                <input type="file" name="attachment" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>                                                        
                        </div>                            
                        
                        <!-- Calculation Area -->
                        <div class="section-divider pt-4 pb-2">
                            <h2 class="text-xl font-semibold text-gray-800">Choose Your Ticket</h2>
                        </div>
                        
                        <div class="calculation-area mb-6">
                            <div style="display: flex; justify-content: space-between; gap: 15px">     
                                <!-- Selected Ticket -->                                 
                                <div class="products-area mb-0" style="width: 60%; max-height: 424px; overflow-y: auto; scrollbar-width: thin;">                                    
                                    <div class="product-selection-form bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-0">
                                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                                            <div class="md:col-span-12">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Ticket*</label>
                                                <select id="isProductSelected" required name="ticket_id" onchange="selectProduct(this)" class="select2 form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                    <option value="">Select a product</option>
                                                    @foreach ($tickets as $ticket)
                                                        <option value="{{ $ticket->id }}" {{ $data->ticket_id == $ticket->id ? 'selected' : '' }} data-portal_id="{{ $ticket->portal_id }}" data-vendor_id="{{ $ticket->vendor_id }}" data-price="{{ $ticket->price }}">{{ $ticket->title }}</option>
                                                    @endforeach
                                                </select>
                                            </div>                                            
                                            <div class="md:col-span-4">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Vendor</label>
                                                <select id="isVendorSelected" name="vendor_id" class="select2 form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                    <option value="">Select a vendor</option>
                                                    @foreach ($vendors as $vendor)
                                                        <option value="{{ $vendor->id }}" {{ $data->vendor_id == $vendor->id ? 'selected' : '' }}>{{ $vendor->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="md:col-span-4">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Portal</label>
                                                <select id="isPortalSelected" onchange="manageMethod(this)" name="portal_id" class="select2 form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                    <option value="">Select a portal</option>
                                                    @foreach ($portals as $portal)
                                                        <option value="{{ $portal->id }}" {{ $data->portal_id == $portal->id ? 'selected' : '' }}>{{ $portal->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>    
                                            <div class="md:col-span-4">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Price</label>
                                                <div class="relative">
                                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                        <span class="text-gray-500">৳</span>
                                                    </div>
                                                    <input id="selectsubtotal" value="{{ $data->amount }}" onblur="applyGrandTotal(this)" type="text" name="price" class="form-input w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md bg-gray-50">
                                                </div>
                                            </div>                                        
                                        </div>
                                    </div>                                                        
                                </div>                                                       
                                <!-- Payment Details Card -->
                                <div class="calculation-card p-5" style="width: 40%;">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Payment Information</h3>
                                    <div class="space-y-4">
                                        <div>
                                            <label for="paid_amount" class="block text-sm font-medium text-gray-700 mb-1">Pay Amount</label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <span class="text-gray-500">৳</span>
                                                </div>
                                                <input type="number" onkeyup="calculatePaidAmount()" value="0.00" step="0.01" id="paid_amount" name="paid_amount" class="form-input w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="0.00" style="background: white" {{ $data->payment_status == 'paid' || empty($data->due_amount) ? 'readonly' : '' }}>
                                            </div>
                                            <small><strong style="color: green">Previously Paid: <span class="paid_amount">{{ $data->paid_amount }}</span></strong></small>
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
                                                    <option value="due" {{ $data->payment_status == 'due' ? 'selected' : '' }}>Due</option>
                                                    <option value="paid" {{ $data->payment_status == 'paid' ? 'selected' : '' }}>Paid</option>
                                                    <option value="partial" {{ $data->payment_status == 'partial' ? 'selected' : '' }}>Partial</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div>
                                            <label for="bank_id" class="block text-sm font-medium text-gray-700 mb-1"><strong>Payment Account</strong></label>
                                            <select name="bank_id" id="bank_id" class="form-control select2" style="width: 100%" {{ !empty($data->portal_id) ? 'disabled' : '' }}>
                                                <option value="">Select</option>
                                                @include('partials.bank-options', ['banks' => $banks, 'selected' => $data->bank_id])
                                            </select>
                                        </div>
                                    </div>
                                    <br>
                                    <div class="space-y-4">                                        
                                        <div class="border-t border-gray-300 pt-3 flex justify-between items-center" style="border-top-width: 2px;">
                                            {{-- <span class="text-gray-700 font-semibold">Total Paid:</span>
                                            <span class="amount-highlight">৳ <span class="paid_amount">{{ $data->paid_amount }}</span></span>                                            
                                        </div>
                                        <div class="flex justify-between items-center"> --}}
                                            <span class="text-gray-800 font-semibold">Total Due:</span>
                                            <span class="amount-highlight">৳ <span class="due_amount">{{ $data->due_amount }}</span></span>
                                            <input type="hidden" name="due_amount" id="due_amount" value="{{ $data->due_amount }}">
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-gray-800 font-semibold">Total Amount:</span>
                                            <span class="text-xl font-bold text-blue-600">৳ <span class="amount">{{ $data->amount }}</span></span>
                                            <input type="hidden" name="amount" id="amount" value="{{ $data->amount }}">
                                        </div>
                                    </div>
                                </div>
                            </div>                                                        
                        </div>
                        
                        <!-- Route Legs -->
                        <div class="section-divider pt-4 pb-2 flex items-center justify-between">
                            <h2 class="text-xl font-semibold text-gray-800">Route Legs <span class="text-sm font-normal text-gray-500">(optional — break a multi-city itinerary into priced segments)</span></h2>
                            <button type="button" onclick="addLegRow()" class="btn btn-sm px-3 py-1.5 bg-blue-50 text-blue-700 border border-blue-200 rounded-md hover:bg-blue-100 text-sm"><i class="fas fa-plus mr-1"></i> Add Leg</button>
                        </div>
                        <div id="legsContainer" class="space-y-3 mb-4"></div>

                        <div class="flex justify-end pt-2">
                            <button id="createpurchaseBtn" type="button" class="btn btn-success px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition duration-200" style="cursor: pointer">
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
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>

        $(document).ready(function() {
            $('.select2').select2();
            // Airline refund: only while Status reads Cancelled on a live ticket.
            const toggleRefund = () => $('#refundBox').toggle($('#status').val() === 'cancelled');
            $('#status').on('change', toggleRefund);
            toggleRefund();
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            @php
                // Precomputed as a plain variable first — @json(expr) splits expr
                // on every top-level comma (it also accepts optional
                // $options/$depth args), so an array literal with 3+ keys inlined
                // directly gets silently truncated.
                // travel_date has no Carbon cast on TicketLeg — it's a plain
                // string, so optional(...)->format() would silently null it.
                $existingLegsForJs = $data->legs->map(fn ($leg) => [
                    'from_location' => $leg->from_location,
                    'to_location' => $leg->to_location,
                    'travel_date' => $leg->travel_date,
                    'seat_number' => $leg->seat_number,
                    'cost' => $leg->cost,
                ]);
            @endphp
            const existingLegs = @json($existingLegsForJs);
            existingLegs.forEach(function (leg) { addLegRow(leg); });
        });


        $('#createpurchaseBtn').click(function(e) {
            e.preventDefault();

            if (!$('#purchase_date').val().trim()) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Please Select a Date!'
                });   
                return;
            }                          

            if (!$('#isProductSelected').val() || ($('#isProductSelected').val() == '') || ($('#isProductSelected').val() == null)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Please Select a ticket!'
                });               
                return;
            }            

            let formData = new FormData($('#purchaseCreateForm')[0]);
            $.ajax({
                url: $('#purchaseCreateForm').attr('action'),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Done',
                            text: 'Purchase updated successfully!'
                        });                            
                        setTimeout(() => {
                            window.location.href = "{{ url('admin/ticket-purchase') }}";
                        }, 800);
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
                        text: 'Failed to update purchase.'
                    });
                }
            });                                     
        });


        function selectProduct(obj) {      
            var item_price = parseFloat($(obj).find('option:selected').data('price')) || 0;
            var portal_id = $(obj).find('option:selected').data('portal_id');
            var vendor_id = $(obj).find('option:selected').data('vendor_id');
            $('#selectsubtotal').val(item_price ? item_price : 0);            
            $('#amount').val(item_price ? item_price : 0);
            $('.amount').text(item_price ? item_price : 0);

            let paid_amount = parseFloat($('.paid_amount').text()) || 0;        
            $('#due_amount').val(item_price ? item_price-paid_amount : 0);
            $('.due_amount').text(item_price ? item_price-paid_amount : 0);

            $('#isPortalSelected').val(portal_id).trigger('change');
            $('#isVendorSelected').val(vendor_id).trigger('change');

            calculatePaidAmount();

            setTimeout(() => {
                manageMethod();
            }, 1000);
        }

        function calculatePaidAmount() {                  
            let previously_paid = parseFloat($('.paid_amount').text()) || 0;   
            var paid_amount = parseFloat($('#paid_amount').val()) || 0;
            let total_paid = previously_paid + paid_amount;
            var total_amount = parseFloat($('#amount').val()) || 0;

            if (total_paid > total_amount) {
                Swal.fire("Caution", "Paid amount cannot exceed total amount!", 'warning');
                $('#paid_amount').val(0); 
                total_paid = previously_paid;
            }

            var due = (total_amount - total_paid).toFixed(2);

            $('.due_amount').text(due);
            $('#due_amount').val(due);
        }

        function applyGrandTotal(obj) {      
            var total_price = parseFloat($(obj).val()) || 0;
            $('#amount').val(total_price ? total_price : 0);
            $('.amount').text(total_price ? total_price : 0);

            calculatePaidAmount()
        }

        function manageMethod() {
            var isBankSelected = $('#bank_id').val();
            var isPortalSelected = $('#isPortalSelected').val();
            if (isPortalSelected) {
                $('#bank_id').val('').trigger('change').prop('disabled', true);
            } else{
                $('#bank_id').prop('disabled', false);
            }
        }

        // ── Route legs ──────────────────────────────────────────────────
        let legIndex = 0;
        function addLegRow(prefill) {
            prefill = prefill || {};
            const i = legIndex++;
            const row = $(
                '<div class="leg-row bg-gray-50 border border-gray-200 rounded-md p-3 grid grid-cols-1 md:grid-cols-6 gap-3 items-end" data-leg-index="' + i + '">' +
                    '<div><label class="block text-xs font-medium text-gray-600 mb-1">From</label>' +
                        '<input type="text" name="legs[' + i + '][from_location]" class="form-input w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm" placeholder="e.g. DAC" value="' + (prefill.from_location || '') + '"></div>' +
                    '<div><label class="block text-xs font-medium text-gray-600 mb-1">To</label>' +
                        '<input type="text" name="legs[' + i + '][to_location]" class="form-input w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm" placeholder="e.g. IST" value="' + (prefill.to_location || '') + '"></div>' +
                    '<div><label class="block text-xs font-medium text-gray-600 mb-1">Travel Date</label>' +
                        '<input type="text" data-dt name="legs[' + i + '][travel_date]" class="form-input w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm" value="' + (prefill.travel_date || '') + '"></div>' +
                    '<div><label class="block text-xs font-medium text-gray-600 mb-1">Seat No</label>' +
                        '<input type="text" name="legs[' + i + '][seat_number]" class="form-input w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm" value="' + (prefill.seat_number || '') + '"></div>' +
                    '<div><label class="block text-xs font-medium text-gray-600 mb-1">Cost (৳)</label>' +
                        '<input type="number" min="0" step="0.01" name="legs[' + i + '][cost]" class="form-input w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm" value="' + (prefill.cost ?? '') + '"></div>' +
                    '<div class="flex items-center gap-2">' +
                        '<button type="button" onclick="$(this).closest(\'.leg-row\').remove()" class="text-red-600 hover:text-red-800 text-sm"><i class="fas fa-trash"></i> Remove</button>' +
                    '</div>' +
                '</div>'
            );
            $('#legsContainer').append(row);
        }
    </script>
@include('partials.datetime-picker'){{-- date + optional time on operational date boxes (2026-09-30) --}}
@endsection