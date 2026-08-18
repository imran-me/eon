@extends('layout.app')
@section('meta-information')
<title>Flight Sales & Bookings</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    :root {
        --border: #e4e8f0;
        --text: #1a2035;
        --text2: #5a6480;
        --text3: #9aa3bc;
        --shadow: 0 1px 4px rgba(30, 50, 100, .07);
        --shadow2: 0 4px 16px rgba(30, 50, 100, .10);
    }

    body {
        font-family: 'DM Sans', sans-serif;
    }

    .dash-page-band {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 18px 22px;
        margin-bottom: 16px;
        box-shadow: var(--shadow);
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .dash-page-band-ic {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        background: #eef6ff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        color: #7c2d12;
        flex-shrink: 0;
    }

    .dash-page-band-info {
        flex: 1;
    }

    .dash-page-band-title {
        font-size: 19px;
        font-weight: 800;
        color: var(--text);
        letter-spacing: 0;
    }

    .dash-page-band-sub {
        font-size: 12.5px;
        color: var(--text2);
        margin-top: 2px;
    }

    .dash-page-band-tag {
        padding: 8px 16px;
        border-radius: 8px;
        background: #dcfce7;
        color: #166534;
        font-size: 13px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: none;
        cursor: pointer;
        font-family: 'DM Sans', sans-serif;
    }

    .dash-stat-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-bottom: 18px;
    }

    .dash-stat {
        border-radius: 14px;
        padding: 20px 18px;
        min-height: 140px;
        position: relative;
        overflow: hidden;
    }

    .dash-stat.blue {
        background: #eef7ff
    }

    .dash-stat.green {
        background: #effaf2
    }

    .dash-stat.amber {
        background: #fff8e8
    }

    .dash-stat.purple {
        background: #f8f1ff
    }

    .dash-stat-ic {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: #fff;
        margin-bottom: 14px;
        box-shadow: 0 5px 12px rgba(0, 0, 0, .12);
    }

    .dash-stat.blue .dash-stat-ic {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8)
    }

    .dash-stat.green .dash-stat-ic {
        background: linear-gradient(135deg, #4ade80, #16a34a)
    }

    .dash-stat.amber .dash-stat-ic {
        background: linear-gradient(135deg, #fb923c, #ea580c)
    }

    .dash-stat.purple .dash-stat-ic {
        background: linear-gradient(135deg, #a855f7, #7c3aed)
    }

    .dash-stat-num {
        font-size: 31px;
        font-weight: 900;
        line-height: 1;
        margin-bottom: 4px;
    }

    .dash-stat.blue .dash-stat-num {
        color: #1e3a8a
    }

    .dash-stat.green .dash-stat-num {
        color: #14532d
    }

    .dash-stat.amber .dash-stat-num {
        color: #7c2d12
    }

    .dash-stat.purple .dash-stat-num {
        color: #581c87
    }

    .dash-stat-lbl {
        font-size: 10.5px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .5px;
    }

    .dash-stat.blue .dash-stat-lbl {
        color: #1d4ed8
    }

    .dash-stat.green .dash-stat-lbl {
        color: #15803d
    }

    .dash-stat.amber .dash-stat-lbl {
        color: #c2410c
    }

    .dash-stat.purple .dash-stat-lbl {
        color: #6d28d9
    }

    .dash-stat-trend {
        font-size: 11px;
        color: var(--text2);
        margin-top: 6px;
        font-weight: 600;
    }

    .fs-card {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 14px;
        box-shadow: var(--shadow2);
        padding: 16px;
        margin-bottom: 16px;
    }

    .fs-filter {
        display: grid;
        grid-template-columns: 340px 1fr 1fr auto auto;
        gap: 10px;
        align-items: center;
        margin-bottom: 14px;
    }

    .fs-search {
        display: flex;
        align-items: center;
        gap: 9px;
        background: #f8fafc;
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 0 13px;
        height: 42px;
    }

    .fs-search input {
        border: 0;
        outline: none;
        background: transparent;
        width: 100%;
        font-size: 13px;
    }

    .fs-filter select {
        height: 38px;
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 0 12px;
        font-size: 13px;
        background: #fff;
        color: var(--text);
    }

    .fs-btn {
        height: 38px;
        padding: 0 16px;
        border-radius: 8px;
        border: 0;
        font-size: 12.5px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .fs-btn-primary {
        background: #2563eb;
        color: #fff
    }

    .fs-btn-reset {
        background: #f3f4f6;
        color: #374151;
        border: 1px solid var(--border)
    }

    .tk-table-wrap {
        overflow-x: auto;
    }

    .tk-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12.5px;
    }

    .tk-table thead th {
        background: #f8fafc;
        padding: 12px 14px;
        text-align: left;
        font-size: 10.5px;
        font-weight: 800;
        color: var(--text2);
        text-transform: uppercase;
        letter-spacing: .4px;
        border-bottom: 1px solid var(--border);
        white-space: nowrap;
    }

    .tk-table thead th:first-child,
    .tk-table tbody td:first-child {
        padding-left: 14px;
    }

    .tk-table tbody td {
        padding: 13px 14px;
        border-bottom: 1px solid #eef2f7;
        vertical-align: middle;
        color: var(--text);
        white-space: nowrap;
    }

    .tk-table tbody tr:hover {
        background: #fafbff
    }

    .tk-table tbody tr:last-child td {
        border-bottom: none
    }

    .tk-mono {
        font-family: 'DM Mono', monospace;
        font-size: 12px
    }

    .fb-no {
        font-family: 'DM Mono', monospace;
        font-weight: 800;
        color: #1d4ed8
    }

    .client-main {
        font-weight: 800;
        color: #111827
    }

    .client-sub {
        font-size: 11px;
        color: #94a3b8;
        margin-top: 2px
    }

    .pay-pill {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 800
    }

    .pay-paid {
        background: #dcfce7;
        color: #166534
    }

    .pay-partial {
        background: #ffedd5;
        color: #c2410c
    }

    .pay-due {
        background: #fee2e2;
        color: #b91c1c
    }

    .mf-ic-btn {
        width: 27px;
        height: 27px;
        border-radius: 7px;
        border: 1px solid var(--border);
        background: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 11.5px;
        cursor: pointer;
        color: var(--text2);
        transition: all .12s;
    }

    .mf-ic-btn:hover {
        transform: translateY(-1px)
    }

    .mf-ic-btn.view:hover {
        background: #dbeafe;
        color: #2563eb;
        border-color: #2563eb
    }

    .mf-ic-btn.edit:hover {
        background: #fef3c7;
        color: #d97706;
        border-color: #d97706
    }

    .mf-ic-btn.inv:hover {
        background: #ede9fe;
        color: #7c3aed;
        border-color: #7c3aed
    }

    .mf-ic-btn.del:hover {
        background: #fee2e2;
        color: #dc2626;
        border-color: #dc2626
    }

    .tk-empty {
        text-align: center;
        padding: 48px 20px;
        color: var(--text2)
    }

    .tk-empty i {
        font-size: 2.5rem;
        opacity: .35;
        margin-bottom: 12px;
        display: block
    }

    .tk-pagination {
        padding: 12px 0 0;
        border-top: 1px solid var(--border);
        background: #fff;
    }

    @media(max-width:1100px) {
        .dash-stat-grid {
            grid-template-columns: repeat(2, 1fr)
        }

        .fs-filter {
            grid-template-columns: 1fr 1fr
        }
    }

    @media(max-width:640px) {
        .dash-page-band {
            align-items: flex-start;
            flex-direction: column
        }

        .dash-stat-grid,
        .fs-filter {
            grid-template-columns: 1fr
        }
    }
</style>
@endsection
@section('main-content')
@php
$role = \Illuminate\Support\Str::slug(Auth::user()->getRoleNames()->first());
$fmt = fn($n) => $n >= 100000 ? number_format($n / 100000, 1).'L' : number_format($n, 0);
@endphp

<div class="dash-page-band">
    <div class="dash-page-band-ic"><i class="fas fa-briefcase"></i></div>
    <div class="dash-page-band-info">
        <div class="dash-page-band-title">Flight Sales &amp; Bookings</div>
        <div class="dash-page-band-sub">Client / agent-wise multi-flight vouchers - one invoice can include several contract flights</div>
    </div>
    <button class="dash-page-band-tag create-btn"><i class="fas fa-plus"></i> New Booking</button>
</div>

<div class="dash-stat-grid">
    <div class="dash-stat blue">
        <div class="dash-stat-ic"><i class="fas fa-clipboard-list"></i></div>
        <div class="dash-stat-num">{{ $stats['bookings_mtd'] }}</div>
        <div class="dash-stat-lbl">Bookings (MTD)</div>
        <div class="dash-stat-trend">{{ $stats['seats_mtd'] }} seats sold</div>
    </div>
    <div class="dash-stat green">
        <div class="dash-stat-ic"><i class="fas fa-sack-dollar"></i></div>
        <div class="dash-stat-num">BDT {{ $fmt($stats['revenue_mtd']) }}</div>
        <div class="dash-stat-lbl">Revenue</div>
        <div class="dash-stat-trend">{{ $stats['revenue_trend'] !== null ? ($stats['revenue_trend'] >= 0 ? '+' : '').$stats['revenue_trend'].'% vs last month' : 'No previous month data' }}</div>
    </div>
    <div class="dash-stat amber">
        <div class="dash-stat-ic"><i class="fas fa-hourglass-half"></i></div>
        <div class="dash-stat-num">BDT {{ $fmt($stats['due_amount']) }}</div>
        <div class="dash-stat-lbl">Due / Pending</div>
        <div class="dash-stat-trend">{{ $stats['due_count'] }} bookings</div>
    </div>
    <div class="dash-stat purple">
        <div class="dash-stat-ic"><i class="fas fa-chair"></i></div>
        <div class="dash-stat-num">{{ $stats['seat_fill_rate'] }}%</div>
        <div class="dash-stat-lbl">Seat Fill Rate</div>
        <div class="dash-stat-trend">Across all flights</div>
    </div>
</div>

<div class="fs-card">
    <form method="GET" action="{{ route('role.contract-flight-sales.index', ['role' => $role]) }}">
        <div class="fs-filter">
            <div class="fs-search"><i class="fas fa-search"></i><input type="text" name="search" value="{{ request('search') }}" placeholder="Search booking, client, flight..."></div>
            <select name="payment_status">
                <option value="">All Payment Status</option>
                <option value="paid" {{ request('payment_status') == 'paid' ? 'selected' : '' }}>Paid</option>
                <option value="partial" {{ request('payment_status') == 'partial' ? 'selected' : '' }}>Partial</option>
                <option value="due" {{ request('payment_status') == 'due' ? 'selected' : '' }}>Due</option>
            </select>
            <select name="flight_category_id">
                <option value="">All Categories</option>
                @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ request('flight_category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="fs-btn fs-btn-primary">Filter</button>
            <a href="{{ route('role.contract-flight-sales.index', ['role' => $role]) }}" class="fs-btn fs-btn-reset">Reset</a>
        </div>
    </form>

    <div class="tk-table-wrap">
        <table class="tk-table">
            <thead>
                <tr>
                    <th>Voucher #</th>
                    <th>Client / Agent</th>
                    <th>Flights</th>
                    <th>Route</th>
                    <th>Date</th>
                    <th>Seats</th>
                    <th>Total (BDT)</th>
                    <th>Paid</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($datas as $booking)
                @php
                $voucherItems = $booking->items;
                $firstItem = $voucherItems->first();
                $flight = $firstItem?->contractFlight;
                $extraFlights = max(0, $voucherItems->count() - 1);
                @endphp
                <tr>
                    <td><span class="fb-no">{{ $booking->booking_number }}</span></td>
                    <td>
                        <div class="client-main">{{ $booking->client?->name ?? '—' }}</div>
                        <div class="client-sub"><i class="fas fa-phone"></i> {{ $booking->client?->phone ?? '-' }}</div>
                    </td>
                    <td>
                        {{ optional($flight?->airline)->name ?: '-' }}{{ $flight?->airline_flight_no ? ' '.$flight->airline_flight_no : '' }}
                        @if($extraFlights)<span class="pay-pill pay-partial">+{{ $extraFlights }} more</span>@endif
                    </td>
                    <td>{{ $flight?->route ?? '-' }}@if($extraFlights)<span class="client-sub">Multi-route</span>@endif</td>
                    <td>{{ $flight?->departure_at ? $flight->departure_at->format('d M Y') : '-' }}@if($extraFlights)<span class="client-sub">{{ $voucherItems->count() }} flights</span>@endif</td>
                    <td class="tk-mono">{{ $booking->seats }}</td>
                    <td class="tk-mono" style="font-weight:800;">{{ number_format($booking->total_amount, 0) }}</td>
                    <td class="tk-mono">{{ number_format($booking->paid_amount, 0) }}</td>
                    <td class="tk-mono">{{ number_format($booking->due_amount, 0) }}</td>
                    <td><span class="pay-pill pay-{{ $booking->payment_status }}">{{ ucfirst($booking->payment_status) }}</span></td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <button type="button" class="mf-ic-btn view" onclick="viewBooking({{ $booking->id }})" title="View"><i class="fas fa-eye"></i></button>
                            <button type="button" class="mf-ic-btn edit edit-btn"
                                data-item_id="{{ $booking->id }}"
                                data-action="{{ route('role.contract-flight-sales.update', ['role' => $role, 'contract_flight_sale' => $booking->id]) }}"
                                title="Edit"><i class="fas fa-edit"></i></button>
                            <a class="mf-ic-btn inv" href="{{ route('role.contract-flight-sales.invoice', ['role' => $role, 'contractFlightBooking' => $booking->id]) }}" target="_blank" title="Invoice"><i class="fas fa-file-invoice"></i></a>
                            <button type="button" class="mf-ic-btn del" onclick="confirmDelete('{{ $booking->id }}', '{{ addslashes($booking->booking_number) }}')" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="11">
                        <div class="tk-empty"><i class="fas fa-inbox"></i>
                            <h4>No vouchers found</h4>
                            <p>Add a voucher to start tracking contract flight sales.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="tk-pagination">{{ $datas->links() }}</div>
</div>

@include('contract-flight-sales.create-modal')
@include('contract-flight-sales.edit-modal')
@include('contract-flight-sales.delete-modal')
@include('contract-flight-sales.view-modal')
@endsection
@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    const bookings = @json($datas -> items());
    const flightCatalog = @json($flights);
    const customersData = @json($customers->keyBy('id'));
    const agentsData = @json($agents->keyBy('id'));
    const selectedFlights = {
        create: new Set(),
        edit: new Set()
    };
    const flightDrafts = {
        create: {},
        edit: {}
    };

    // Picking an agent/vendor clears and locks out the customer picker (and vice
    // versa) — only one party can be the booking's client_id — then syncs the
    // hidden field/phone.
    function toggleFlightBankField(prefix) {
        const isAdvance = $(`#${prefix}_payment_method`).val() === 'advance';
        $(`#${prefix}_bank_field`).toggleClass('hidden', isAdvance);
        if (isAdvance) $(`#${prefix}_bank_id`).val('');
    }

    function pickFlightClient(prefix, kind, id) {
        const otherSelector = kind === 'agent' ? `#${prefix}_customer_picker` : `#${prefix}_agent_picker`;
        if (id) {
            $(otherSelector).val('').trigger('change.select2').prop('disabled', true);
        } else {
            $(otherSelector).prop('disabled', false);
        }
        $(`#${prefix}_client_id`).val(id).trigger('change');
        const party = (kind === 'agent' ? agentsData : customersData)[id];
        $(`#${prefix}_client_phone`).val(party ? (party.phone || '') : '');
    }

    $(document).ready(function() {
        $('#create_agent_picker, #create_customer_picker').select2({
            dropdownParent: $('#createModal'),
            width: '100%'
        });
        $('#edit_agent_picker, #edit_customer_picker').select2({
            dropdownParent: $('#editModal'),
            width: '100%'
        });

        $('#create_agent_picker, #edit_agent_picker, #create_customer_picker, #edit_customer_picker').on('change', function() {
            const prefix = this.id.startsWith('edit_') ? 'edit' : 'create';
            const kind = this.id.includes('_agent_picker') ? 'agent' : 'customer';
            pickFlightClient(prefix, kind, this.value);
        });

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        $('.create-btn').click(function() {
            $('#createForm')[0].reset();
            $('#createForm .error-message').addClass('hidden');
            selectedFlights.create.clear();
            flightDrafts.create = {};
            $('#create_client_id').val(null);
            $('#create_agent_picker, #create_customer_picker').prop('disabled', false).val(null).trigger('change.select2');
            $('#create_paid_amount').val(0);
            $('#create_payment_method').val('cash');
            $('#create_bank_id').val('');
            toggleFlightBankField('create');
            renderFlightBundle('create');
            recalculateVoucher('create');
            $('#createModal').removeClass('hidden');
        });

        $('.edit-btn').click(function() {
            const b = bookings.find(x => Number(x.id) === Number($(this).data('item_id')));
            if (!b) return;
            selectedFlights.edit.clear();
            flightDrafts.edit = {};
            $('#editItemId').val(b.id);
            $('#edit_client_id').val(b.client_id || null);
            const isCustomer = !!customersData[b.client_id];
            $('#edit_agent_picker').prop('disabled', false).val(isCustomer ? null : (b.client_id || null)).trigger('change.select2');
            $('#edit_customer_picker').prop('disabled', false).val(isCustomer ? b.client_id : null).trigger('change.select2');
            if (b.client_id) {
                $(isCustomer ? '#edit_agent_picker' : '#edit_customer_picker').prop('disabled', true);
            }
            $('#edit_client_phone').val(b.client?.phone || '');
            $('#edit_paid_amount').val(b.paid_amount);
            $('#edit_receivable_date').val(b.receivable_date ? b.receivable_date.substring(0, 10) : '');
            $('#edit_payment_method').val(b.payment_method || 'cash');
            $('#edit_bank_id').val(b.bank_id || '');
            toggleFlightBankField('edit');
            $('#edit_notes').val(b.notes);
            (b.items || []).forEach(item => {
                const id = String(item.contract_flight_id);
                selectedFlights.edit.add(id);
                flightDrafts.edit[id] = {
                    seats: Number(item.seats || 1),
                    unit_price: Number(item.unit_price || 0)
                };
            });
            $('#editeForm .error-message').addClass('hidden');
            $('#editSubmit').data('action', $(this).data('action'));
            renderFlightBundle('edit');
            recalculateVoucher('edit');
            $('#editModal').removeClass('hidden');
        });

        $(document).on('input', '.flight-line-input', function() {
            const prefix = $(this).data('prefix'),
                id = String($(this).data('id'));
            flightDrafts[prefix][id] = flightDrafts[prefix][id] || {};
            if ($(this).hasClass('flight-seats')) flightDrafts[prefix][id].seats = Number($(this).val() || 0);
            if ($(this).hasClass('flight-unit')) flightDrafts[prefix][id].unit_price = Number($(this).val() || 0);
            updateHiddenItems(prefix);
            recalculateVoucher(prefix);
        });
        $('.voucher-paid').on('input change', function() {
            recalculateVoucher(this.id.startsWith('edit_') ? 'edit' : 'create')
        });
        $('.modal-close-create,.modal-backdrop').click(function(e) {
            if ($(e.target).closest('.modal-close-create').length || $(e.target).is('#createModal')) $('#createModal').addClass('hidden')
        });
        $('.modal-close-edit,.modal-backdrop').click(function(e) {
            if ($(e.target).closest('.modal-close-edit').length || $(e.target).is('#editModal')) $('#editModal').addClass('hidden')
        });
        $('.modal-close-delete,.modal-backdrop').click(function(e) {
            if ($(e.target).closest('.modal-close-delete').length || $(e.target).is('#deleteModal')) $('#deleteModal').addClass('hidden')
        });
        $('.modal-close-view,.modal-backdrop').click(function(e) {
            if ($(e.target).closest('.modal-close-view').length || $(e.target).is('#viewModal')) $('#viewModal').addClass('hidden')
        });
        $('#createSubmit').click(() => submitForm('#createForm', $('#createForm').attr('action'), 'Voucher created successfully!', '#createModal'));
        $('#editSubmit').click(function() {
            submitForm('#editeForm', $(this).data('action'), 'Voucher updated successfully!', '#editModal')
        });
        $('#confirmDeleteBtn').click(function() {
            $.ajax({
                url: $(this).data('action'),
                method: 'DELETE',
                data: {
                    item_id: $(this).data('item-id')
                },
                success: function(res) {
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Done',
                            text: 'Voucher deleted successfully!'
                        });
                        $('#deleteModal').addClass('hidden');
                        setTimeout(() => location.reload(), 500)
                    } else Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: res.message
                    });
                },
                error: function(xhr) {
                    console.error(xhr.responseText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Something went wrong!'
                    })
                }
            });
        });
    });

    function flightLabel(f) {
        return [f.flight_number, (f.airline ? f.airline.name : '') + (f.airline_flight_no ? ' ' + f.airline_flight_no : ''), f.route || '-', formatDate(f.departure_at)].filter(Boolean).join(' - ')
    }

    function renderFlightBundle(prefix) {
        const rows = flightCatalog.map(f => {
            const id = String(f.id),
                sel = selectedFlights[prefix].has(id),
                draft = flightDrafts[prefix][id] || {},
                seats = Number(draft.seats ?? 1),
                unit = Number(draft.unit_price ?? f.sale_price_per_pax ?? 0),
                line = seats * unit;
            return `<tr class="${sel?'selected':''}" onclick="toggleFlight('${prefix}','${id}')"><td class="px-3 py-3"><input type="checkbox" ${sel?'checked':''} onclick="event.stopPropagation();toggleFlight('${prefix}','${id}')"></td><td class="px-3 py-3"><strong>${escapeHtml(f.flight_number||'-')}</strong><div class="client-sub">${escapeHtml((f.airline?f.airline.name:'')+(f.airline_flight_no?' '+f.airline_flight_no:''))}</div></td><td class="px-3 py-3">${escapeHtml(f.route||'-')}<div class="client-sub">${escapeHtml(formatDate(f.departure_at))}</div></td><td class="px-3 py-3 font-mono">${Number(f.seats_available||0)}</td><td class="px-3 py-3"><input class="flight-line-input flight-seats h-8 w-24 rounded-lg border border-slate-300 px-2 text-xs outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-100" data-prefix="${prefix}" data-id="${id}" type="number" min="1" value="${seats}" onclick="event.stopPropagation()"></td><td class="px-3 py-3"><input class="flight-line-input flight-unit h-8 w-24 rounded-lg border border-slate-300 px-2 text-xs outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-100" data-prefix="${prefix}" data-id="${id}" type="number" min="0" step="0.01" value="${unit.toFixed(2)}" onclick="event.stopPropagation()"></td><td class="px-3 py-3 font-mono font-extrabold">${line.toLocaleString()}</td></tr>`
        }).join('');
        $('#' + prefix + '_flight_items').html(`<table class="w-full border-collapse text-xs"><thead class="bg-slate-50 text-slate-500"><tr><th class="w-8 px-3 py-3 text-left"><input type="checkbox" onclick="toggleAllFlights('${prefix}',this)"></th><th class="px-3 py-3 text-left font-extrabold uppercase">Flight</th><th class="px-3 py-3 text-left font-extrabold uppercase">Route / Date</th><th class="px-3 py-3 text-left font-extrabold uppercase">Available</th><th class="px-3 py-3 text-left font-extrabold uppercase">Seats</th><th class="px-3 py-3 text-left font-extrabold uppercase">Unit Price</th><th class="px-3 py-3 text-left font-extrabold uppercase">Amount</th></tr></thead><tbody>${rows}</tbody></table><div class="flex items-center justify-between border-teal-100 bg-teal-50 px-3 py-2 text-xs font-extrabold text-teal-700"><span id="${prefix}_selected_count">0 flights selected</span><span>Grand Total: BDT <b id="${prefix}_grand_total">0</b></span></div>`);
        updateHiddenItems(prefix);
        recalculateVoucher(prefix);
    }

    function toggleFlight(prefix, id) {
        if (selectedFlights[prefix].has(id)) selectedFlights[prefix].delete(id);
        else {
            selectedFlights[prefix].add(id);
            const f = flightCatalog.find(x => String(x.id) === id);
            flightDrafts[prefix][id] = flightDrafts[prefix][id] || {
                seats: 1,
                unit_price: Number(f?.sale_price_per_pax || 0)
            }
        }
        renderFlightBundle(prefix)
    }

    function toggleAllFlights(prefix, chk) {
        flightCatalog.forEach(f => {
            const id = String(f.id);
            if (chk.checked) {
                selectedFlights[prefix].add(id);
                flightDrafts[prefix][id] = flightDrafts[prefix][id] || {
                    seats: 1,
                    unit_price: Number(f.sale_price_per_pax || 0)
                }
            } else selectedFlights[prefix].delete(id)
        });
        renderFlightBundle(prefix)
    }

    function updateHiddenItems(prefix) {
        const wrap = $('#' + prefix + '_hidden_items').empty();
        let i = 0;
        selectedFlights[prefix].forEach(id => {
            const f = flightCatalog.find(x => String(x.id) === id),
                d = flightDrafts[prefix][id] || {};
            wrap.append(`<input type="hidden" name="items[${i}][contract_flight_id]" value="${id}"><input type="hidden" name="items[${i}][seats]" value="${Number(d.seats??1)}"><input type="hidden" name="items[${i}][unit_price]" value="${Number(d.unit_price??f?.sale_price_per_pax??0)}">`);
            i++;
        });
    }

    function recalculateVoucher(prefix) {
        let total = 0,
            seatsTotal = 0;
        selectedFlights[prefix].forEach(id => {
            const f = flightCatalog.find(x => String(x.id) === id),
                d = flightDrafts[prefix][id] || {},
                seats = Number(d.seats ?? 1),
                unit = Number(d.unit_price ?? f?.sale_price_per_pax ?? 0);
            total += seats * unit;
            seatsTotal += seats
        });
        $('#' + prefix + '_selected_count').text(`${selectedFlights[prefix].size} flight${selectedFlights[prefix].size===1?'':'s'} selected`);
        $('#' + prefix + '_grand_total').text(total.toLocaleString());
        $('#' + prefix + '_total_amount').val(total.toFixed(2));
        const paid = Number($('#' + prefix + '_paid_amount').val() || 0);
        $('#' + prefix + '_payment_status').val(paid >= total && total > 0 ? 'paid' : (paid > 0 ? 'partial' : 'due'));
    }

    function submitForm(form, url, msg, modal) {
        if (!validateBookingForm(form)) return;
        const button = form === '#createForm' ? $('#createSubmit') : $('#editSubmit');
        button.prop('disabled', true);
        $.ajax({
            url: url,
            method: 'POST',
            data: $(form).serialize(),
            success: function(res) {
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Done',
                        text: msg
                    });
                    $(modal).addClass('hidden');
                    setTimeout(() => location.reload(), 700)
                } else Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: res.message || 'Something went wrong.'
                });
            },
            error: function(xhr) {
                console.error(xhr.responseText);
                showRequestError(xhr)
            },
            complete: function() {
                button.prop('disabled', false)
            }
        })
    }

    function validateBookingForm(form) {
        const prefix = form === '#createForm' ? 'create' : 'edit';
        let ok = true;
        $(form + ' .error-message').addClass('hidden');
        if (!$('#' + prefix + '_client_id').val()) {
            $('#' + prefix + '_client_id_msg').removeClass('hidden');
            ok = false
        }
        if (!selectedFlights[prefix].size) {
            $('#' + prefix + '_items_msg').text('Select at least one contract flight.').removeClass('hidden');
            ok = false
        }
        selectedFlights[prefix].forEach(id => {
            const d = flightDrafts[prefix][id] || {};
            if (Number(d.seats ?? 1) < 1 || Number(d.unit_price ?? 0) < 0) ok = false
        });
        if (!ok) $('#' + prefix + '_items_msg').removeClass('hidden');
        const total = Number($('#' + prefix + '_total_amount').val() || 0),
            paid = Number($('#' + prefix + '_paid_amount').val() || 0);
        if (paid < 0 || paid > total) {
            $('#' + prefix + '_paid_amount_msg').removeClass('hidden');
            ok = false
        }
        return ok
    }

    function showRequestError(xhr) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: xhr.responseJSON?.message || 'Request failed.'
        })
    }

    function confirmDelete(id, name) {
        $('#deleteName').text(name);
        $('#confirmDeleteBtn').data('item-id', id).data('action', `/{{ $role }}/contract-flight-sales/${id}`);
        $('#deleteModal').removeClass('hidden')
    }

    function viewBooking(id) {
        const b = bookings.find(x => Number(x.id) === Number(id));
        if (!b) return;
        const items = b.items || [],
            status = b.payment_status.charAt(0).toUpperCase() + b.payment_status.slice(1);
        $('#view_booking_number').text(`Voucher ${b.booking_number} - ${b.client ? b.client.name : '—'}`);
        $('#view_client').text(b.client ? b.client.name : '—');
        $('#view_phone').text(b.client ? (b.client.phone || '-') : '-');
        $('#view_seats').text(b.seats);
        $('#view_flights_count').text(items.length);
        $('#view_invoice_link').attr('href', `/{{ $role }}/contract-flight-sales/${id}/invoice`);
        $('#view_total').text('BDT ' + Number(b.total_amount).toLocaleString());
        $('#view_paid').text('BDT ' + Number(b.paid_amount).toLocaleString());
        $('#view_due').text('BDT ' + Number(b.due_amount).toLocaleString());
        $('#view_status').html(`<span class="inline-flex rounded-full px-5 py-2 text-sm font-extrabold pay-pill pay-${b.payment_status}">Payment Status: ${status}</span>`);
        $('#view_notes').text(b.notes || '-');
        const rows = items.map(function(item, index) {
            const f = item.contract_flight || {},
                airline = f.airline ? f.airline.name : '-';
            return '<tr><td>' + (index + 1) + '</td><td class="px-3 py-3"><strong>' + escapeHtml(f.flight_number || '-') + '</strong><div class="client-sub">' + escapeHtml(airline + (f.airline_flight_no ? ' ' + f.airline_flight_no : '')) + '</div></td><td>' + escapeHtml(f.route || '-') + '<div class="client-sub">' + escapeHtml(formatDate(f.departure_at)) + '</div></td><td class="tk-mono">' + Number(item.seats || 0) + '</td><td class="tk-mono">' + Number(item.unit_price || 0).toLocaleString() + '</td><td class="tk-mono" style="font-weight:800">' + Number(item.total_amount || 0).toLocaleString() + '</td></tr>';
        }).join('');
        $('#view_flight_items').html(rows || '<tr><td colspan="6">No flight items found.</td></tr>');
        $('#viewModal').removeClass('hidden');
        $('#cfs-cmt-placeholder').html(cmtHtml('contract_flight_booking', b.id));
        loadComments('contract_flight_booking', b.id);
    }

    function formatDate(value) {
        if (!value) return 'No date';
        return new Date(value).toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        })
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        } [c]))
    }
</script>
@include('components.comment-panel')
@endsection