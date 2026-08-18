@extends('layout.app')
@section('meta-information')
    <title>Edit Direct Purchase #{{ $ticketPurchase->ticket_no }}</title>
@endsection
@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endsection
@section('main-content')

    <div class="states-table bg-white rounded-lg shadow-md overflow-hidden" style="margin-top: 0">
        <div class="states-table-container">
            <div class="states-table-header bg-blue-600 px-6 pb-4 flex justify-between items-center">
                <h2 class="states-table-title text-white text-xl font-semibold" style="color: white">
                    <i class="fas fa-pencil mr-2"></i> Edit Direct Purchase
                </h2>
                <a href="{{ route('role.ticket-direct-sale.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="btn btn-primary create-new-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-list mr-2"></i> Purchase List
                </a>
            </div>
            <div class="states-table-content" style="padding: 15px;">
                <div class="common-modal-body modal-body overflow-y-auto mt-2">
                    <form class="closest" id="purchaseEditForm"
                          action="{{ route('role.ticket-direct-sale.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'ticket_direct_sale' => $ticketPurchase->id]) }}"
                          method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="section-divider pt-0 mt-0 pb-2" style="margin-top: 0">
                            <h2 class="text-xl font-semibold text-gray-800">Purchase Information</h2>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4">
                            <div class="mb-2">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Passport Holder*</label>
                                <select name="passport_holder_id" required class="form-select w-full px-3 py-2 border border-gray-300 rounded-md select2" style="width: 100%">
                                    <option value="">Select</option>
                                    @foreach ($passport_holders as $ph)
                                        <option value="{{ $ph->id }}" {{ $ticketPurchase->passport_holder_id == $ph->id ? 'selected' : '' }}>{{ $ph->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Ticket Route*</label>
                                <select name="ticket_id" required class="form-select w-full px-3 py-2 border border-gray-300 rounded-md select2" style="width: 100%">
                                    <option value="">Select</option>
                                    @foreach ($tickets as $t)
                                        <option value="{{ $t->id }}" {{ $ticketPurchase->ticket_id == $t->id ? 'selected' : '' }}>{{ $t->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Trip Type*</label>
                                <select name="trip_type" required class="form-select w-full px-3 py-2 border border-gray-300 rounded-md select2" style="width: 100%">
                                    <option value="one-way" {{ $ticketPurchase->trip_type == 'one-way' ? 'selected' : '' }}>One-way</option>
                                    <option value="two-way" {{ $ticketPurchase->trip_type == 'two-way' ? 'selected' : '' }}>Round Trip</option>
                                </select>
                                <input type="hidden" name="ticket_type" value="{{ $ticketPurchase->ticket_type }}">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="mb-2">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Vendor</label>
                                <select name="vendor_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md select2" style="width: 100%">
                                    <option value="">Select</option>
                                    @foreach ($vendors as $v)
                                        <option value="{{ $v->id }}" {{ $ticketPurchase->vendor_id == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Portal</label>
                                <select name="portal_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md select2" style="width: 100%">
                                    <option value="">Select</option>
                                    @foreach ($portals as $p)
                                        <option value="{{ $p->id }}" {{ $ticketPurchase->portal_id == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Purchase Date*</label>
                                <input type="date" name="purchase_date" required value="{{ \Carbon\Carbon::parse($ticketPurchase->purchase_date)->format('Y-m-d') }}" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                            <div class="mb-2">
                                <label class="block text-gray-700 text-sm font-bold mb-2">PNR / Ticket No*</label>
                                <input type="text" name="ticket_no" required value="{{ $ticketPurchase->ticket_no }}" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <div class="mb-2">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Cost Price*</label>
                                <input type="number" name="cost_amount" required step="0.01" value="{{ $ticketPurchase->amount }}" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                            <div class="mb-2">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Additional Payment</label>
                                <input type="number" name="purchase_paid" step="0.01" value="0.00" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md">
                                <small class="text-green-600">Previously Paid: {{ number_format($ticketPurchase->paid_amount, 2) }}</small>
                            </div>
                            <div class="mb-2">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Pay Status*</label>
                                <select name="purchase_pay_status" required class="form-select w-full px-3 py-2 border border-gray-300 rounded-md select2" style="width: 100%">
                                    <option value="due" {{ $ticketPurchase->payment_status == 'due' ? 'selected' : '' }}>Due</option>
                                    <option value="partial" {{ $ticketPurchase->payment_status == 'partial' ? 'selected' : '' }}>Partial</option>
                                    <option value="paid" {{ $ticketPurchase->payment_status == 'paid' ? 'selected' : '' }}>Paid</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Payment Method*</label>
                                <select name="purchase_pay_method" required class="form-select w-full px-3 py-2 border border-gray-300 rounded-md select2" style="width: 100%">
                                    <option value="cash">Cash</option>
                                    <option value="card">Card</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="mobile_banking">Mobile Banking</option>
                                    <option value="advance">Advance</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Cost Bank</label>
                                <select name="purchase_bank_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md select2" style="width: 100%">
                                    <option value="">Select</option>
                                    @foreach ($banks as $b)
                                        <option value="{{ $b->id }}" {{ $ticketPurchase->bank_id == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="mb-2">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Currency*</label>
                                <input type="text" name="currency" required value="{{ $ticketPurchase->currency ?? 'BDT' }}" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                            <div class="mb-2">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Replace Attachment</label>
                                <input type="file" name="attachment" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md">
                                @if($ticketPurchase->attachment)
                                    <small><a href="{{ \Illuminate\Support\Facades\Storage::url($ticketPurchase->attachment) }}" target="_blank" class="text-blue-600">View current attachment</a></small>
                                @endif
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button id="submitEditBtn" type="button" class="btn btn-success px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition duration-200" style="cursor: pointer">
                                Update Purchase
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2();
            $.ajaxSetup({
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
            });
        });

        $('#submitEditBtn').click(function(e) {
            e.preventDefault();
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Saving...');

            let formData = new FormData($('#purchaseEditForm')[0]);
            formData.append('_method', 'PUT');

            $.ajax({
                url: $('#purchaseEditForm').attr('action'),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Done', text: response.message });
                        setTimeout(() => { window.location.href = "{{ route('role.ticket-direct-sale.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"; }, 800);
                    } else {
                        $btn.prop('disabled', false).html('Update Purchase');
                        Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Something went wrong.' });
                    }
                },
                error: function (xhr) {
                    console.error(xhr.responseText);
                    $btn.prop('disabled', false).html('Update Purchase');
                    Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to update purchase.' });
                }
            });
        });
    </script>
@endsection
