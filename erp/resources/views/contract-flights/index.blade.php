@extends('layout.app')
@section('meta-information')
    <title>Manage Flight</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
:root {
  --border:#e4e8f0; --border2:#d0d6e8;
  --text:#1a2035; --text2:#5a6480; --text3:#9aa3bc;
  --shadow:0 1px 4px rgba(30,50,100,.07);
  --shadow2:0 4px 16px rgba(30,50,100,.10);
}
body { font-family:'DM Sans',sans-serif; }

/* Page band */
.dash-page-band { background:#fff; border:1px solid var(--border); border-radius:14px; padding:18px 22px; margin-bottom:16px; box-shadow:var(--shadow); display:flex; align-items:center; gap:16px; }
.dash-page-band-ic { width:50px; height:50px; border-radius:12px; background:#fff7ed; display:flex; align-items:center; justify-content:center; font-size:22px; color:#c2410c; flex-shrink:0; }
.dash-page-band-info { flex:1; }
.dash-page-band-title { font-size:19px; font-weight:700; color:var(--text); letter-spacing:-.3px; }
.dash-page-band-sub { font-size:12.5px; color:var(--text2); margin-top:2px; }
.dash-page-band-tag { padding:8px 16px; border-radius:8px; background:#dcfce7; color:#166534; font-size:13px; font-weight:600; display:inline-flex; align-items:center; gap:6px; border:none; cursor:pointer; font-family:'DM Sans',sans-serif; }
.dash-page-band-tag:hover { background:#bbf7d0; }

/* Stat grid */
.dash-stat-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:18px; }
@media (max-width:1100px) { .dash-stat-grid { grid-template-columns:repeat(2,1fr); } }
.dash-stat { border-radius:14px; padding:18px 18px; position:relative; overflow:hidden; }
.dash-stat.blue   { background:#eff6ff; }
.dash-stat.green  { background:#f0fdf4; }
.dash-stat.amber  { background:#fffbeb; }
.dash-stat.red    { background:#fef2f2; }
.dash-stat-ic { width:42px; height:42px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:18px; color:#fff; margin-bottom:12px; box-shadow:0 4px 10px rgba(0,0,0,.10); }
.dash-stat.blue   .dash-stat-ic { background:linear-gradient(135deg,#3b82f6,#2563eb); }
.dash-stat.green  .dash-stat-ic { background:linear-gradient(135deg,#22c55e,#16a34a); }
.dash-stat.amber  .dash-stat-ic { background:linear-gradient(135deg,#f59e0b,#d97706); }
.dash-stat.red    .dash-stat-ic { background:linear-gradient(135deg,#f43f5e,#e11d48); }
.dash-stat-num { font-size:28px; font-weight:800; line-height:1; letter-spacing:-.5px; margin-bottom:2px; }
.dash-stat.blue   .dash-stat-num { color:#1e3a8a; }
.dash-stat.green  .dash-stat-num { color:#14532d; }
.dash-stat.amber  .dash-stat-num { color:#78350f; }
.dash-stat.red    .dash-stat-num { color:#881337; }
.dash-stat-lbl { font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; }
.dash-stat.blue   .dash-stat-lbl { color:#1d4ed8; }
.dash-stat.green  .dash-stat-lbl { color:#15803d; }
.dash-stat.amber  .dash-stat-lbl { color:#b45309; }
.dash-stat.red    .dash-stat-lbl { color:#be123c; }
.dash-stat-trend { font-size:11px; color:var(--text2); margin-top:5px; font-weight:500; }

/* Filter bar */
.mf-filter-card { background:#fff; border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow); padding:14px 16px; margin-bottom:16px; }
.mf-search-wrap { display:flex; align-items:center; gap:8px; background:#f8fafc; border:1px solid var(--border); border-radius:10px; padding:9px 14px; margin-bottom:10px; }
.mf-search-wrap input { border:none; outline:none; flex:1; font-size:13px; font-family:'DM Sans',sans-serif; background:transparent; }
.mf-search-wrap i { color:var(--text3); }
.mf-filter-row { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
.mf-filter-row select, .mf-filter-row input[type="month"] { height:38px; border:1px solid var(--border); border-radius:8px; padding:0 12px; font-size:13px; font-family:'DM Sans',sans-serif; background:#fff; color:var(--text); flex:1; min-width:150px; }
.mf-filter-row select:focus, .mf-filter-row input:focus { border-color:#2563eb; outline:none; box-shadow:0 0 0 3px rgba(37,99,235,.08); }
.mf-btn-export { height:38px; padding:0 16px; border-radius:8px; background:#f3f4f6; color:#374151; border:1px solid var(--border); font-size:12.5px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; font-family:'DM Sans',sans-serif; }
.mf-btn-export:hover { background:#e5e7eb; }
.mf-btn-apply { height:38px; padding:0 18px; border-radius:8px; background:#2563eb; color:#fff; border:none; font-size:12.5px; font-weight:600; cursor:pointer; }
.mf-btn-apply:hover { background:#1d4ed8; }
.mf-btn-reset { height:38px; padding:0 16px; border-radius:8px; background:#f3f4f6; color:#374151; border:1px solid var(--border); font-size:12.5px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; }
.mf-btn-reset:hover { background:#e5e7eb; }

/* Table card */
.tk-page-card { background:#fff; border:1px solid var(--border); border-radius:14px; overflow:hidden; box-shadow:var(--shadow2); }
.tk-table-wrap { overflow-x:auto; padding:0; }
.tk-table { width:100%; border-collapse:collapse; font-size:12.5px; }
.tk-table thead th { background:#f8fafc; padding:11px 14px; text-align:left; font-size:10.5px; font-weight:700; color:var(--text2); text-transform:uppercase; letter-spacing:.4px; border-bottom:2px solid var(--border); white-space:nowrap; }
.tk-table thead th:first-child { padding-left:20px; }
.tk-table tbody td { padding:11px 14px; border-bottom:1px solid var(--border); vertical-align:middle; color:var(--text); white-space:nowrap; }
.tk-table tbody td:first-child { padding-left:20px; }
.tk-table tbody tr:hover { background:#fafbff; }
.tk-table tbody tr:last-child td { border-bottom:none; }
.tk-mono { font-family:'DM Mono',monospace; font-size:12px; }
.mf-flight-no { font-family:'DM Mono',monospace; font-weight:700; color:#2563eb; }
.mf-status-pill { display:inline-block; padding:3px 10px; border-radius:20px; font-size:10.5px; font-weight:700; }
.mf-status-open      { background:#fef3c7; color:#92400e; }
.mf-status-boarding   { background:#fef3c7; color:#92400e; }
.mf-status-departed  { background:#dcfce7; color:#166534; }
.mf-status-cancelled  { background:#fee2e2; color:#991b1b; }
.mf-ic-btn { width:27px; height:27px; border-radius:7px; border:1px solid var(--border); background:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:11.5px; cursor:pointer; color:var(--text2); transition:all .12s; }
.mf-ic-btn:hover { transform:translateY(-1px); }
.mf-ic-btn.view:hover { background:#dbeafe; color:#2563eb; border-color:#2563eb; }
.mf-ic-btn.edit:hover { background:#fef3c7; color:#d97706; border-color:#d97706; }
.mf-ic-btn.inv:hover  { background:#ede9fe; color:#7c3aed; border-color:#7c3aed; }
.mf-ic-btn.del:hover  { background:#fee2e2; color:#dc2626; border-color:#dc2626; }
.tk-empty { text-align:center; padding:48px 20px; color:var(--text2); }
.tk-empty i { font-size:2.5rem; opacity:.35; margin-bottom:12px; display:block; }
.tk-empty h4 { font-size:15px; font-weight:600; margin-bottom:4px; color:var(--text); }
.tk-empty p { font-size:12.5px; }
.tk-pagination { padding:12px 20px; border-top:1px solid var(--border); background:#fafbff; }
span [aria-current="page"] span{ background-color:#2563eb !important; color:white; border-color:#2563eb; }
</style>
@endsection
@section('main-content')
@php
    $role = \Illuminate\Support\Str::slug(Auth::user()->getRoleNames()->first());
    $taka = '৳';
    function mfFmtL($n) { if($n >= 100000) return number_format($n/100000, 1).'L'; if($n >= 1000) return number_format($n/1000, 1).'K'; return number_format($n, 0); }
@endphp

{{-- Page band --}}
<div class="dash-page-band">
    <div class="dash-page-band-ic"><i class="fas fa-suitcase"></i></div>
    <div class="dash-page-band-info">
        <div class="dash-page-band-title">Manage Flight</div>
        <div class="dash-page-band-sub">All contract flights — send invoices, track bookings, manage seats</div>
    </div>
    <button class="dash-page-band-tag create-btn"><i class="fas fa-plus"></i> Add Flight</button>
</div>

{{-- Stat cards --}}
<div class="dash-stat-grid">
    <div class="dash-stat blue">
        <div class="dash-stat-ic"><i class="fas fa-clipboard-list"></i></div>
        <div class="dash-stat-num">{{ $stats['total_flights_mtd'] }}</div>
        <div class="dash-stat-lbl">Total Contract Flights (MTD)</div>
        <div class="dash-stat-trend">{{ $stats['month_label'] }}</div>
    </div>
    <div class="dash-stat green">
        <div class="dash-stat-ic"><i class="fas fa-sack-dollar"></i></div>
        <div class="dash-stat-num">{{ $taka }} {{ mfFmtL($stats['revenue_mtd']) }}</div>
        <div class="dash-stat-lbl">Revenue Generated</div>
        <div class="dash-stat-trend">
            @if($stats['revenue_trend'] !== null)
                <i class="fas fa-arrow-{{ $stats['revenue_trend'] >= 0 ? 'up' : 'down' }}"></i> {{ abs($stats['revenue_trend']) }}% vs last month
            @else
                No data last month
            @endif
        </div>
    </div>
    <div class="dash-stat amber">
        <div class="dash-stat-ic"><i class="fas fa-chair"></i></div>
        <div class="dash-stat-num">{{ $stats['seats_sold_mtd'] }}</div>
        <div class="dash-stat-lbl">Seats Sold</div>
        <div class="dash-stat-trend">of {{ $stats['seats_total_mtd'] }} total · {{ $stats['seats_pct'] }}%</div>
    </div>
    <div class="dash-stat red">
        <div class="dash-stat-ic"><i class="fas fa-gem"></i></div>
        <div class="dash-stat-num">{{ $taka }} {{ mfFmtL($stats['net_profit_mtd']) }}</div>
        <div class="dash-stat-lbl">Net Profit</div>
        <div class="dash-stat-trend">After commission</div>
    </div>
</div>

{{-- Filters --}}
<div class="mf-filter-card">
    <form method="GET" action="{{ route('role.contract-flights.index', ['role' => $role]) }}">
        <div class="mf-search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search flight #, airline, route...">
        </div>
        <div class="mf-filter-row">
            <input type="month" name="month" value="{{ request('month') }}">
            <select name="status">
                <option value="">All Status</option>
                <option value="open" {{ request('status') == 'open' ? 'selected' : '' }}>Open</option>
                <option value="boarding" {{ request('status') == 'boarding' ? 'selected' : '' }}>Boarding</option>
                <option value="departed" {{ request('status') == 'departed' ? 'selected' : '' }}>Departed</option>
                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
            <select name="flight_category_id">
                <option value="">All Categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('flight_category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
            <select name="agent_id">
                <option value="">All Agents</option>
                @foreach($agents as $agent)
                    <option value="{{ $agent->id }}" {{ request('agent_id') == $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="mf-btn-apply">Filter</button>
            <a href="{{ route('role.contract-flights.index', ['role' => $role]) }}" class="mf-btn-reset">Reset</a>
            <button type="button" class="mf-btn-export" onclick="window.print()"><i class="fas fa-file-export"></i> Export</button>
        </div>
    </form>
</div>

{{-- Table --}}
<div class="tk-page-card">
    <div class="tk-table-wrap">
        <table class="tk-table">
            <thead>
                <tr>
                    <th>Flight #</th>
                    <th>Airline / Flight No</th>
                    <th>Route</th>
                    <th>Date / Time</th>
                    <th>Category</th>
                    <th>Seats (Sold / Total)</th>
                    <th>Revenue ({{ $taka }})</th>
                    <th>Agent</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($datas as $flight)
                <tr id="contract-flight-{{ $flight->id }}">
                    <td><span class="mf-flight-no">{{ $flight->flight_number }}</span></td>
                    <td>{{ optional($flight->airline)->name }}{{ $flight->airline_flight_no ? ' ('.$flight->airline_flight_no.')' : '' }}</td>
                    <td>{{ $flight->route ?? '—' }}</td>
                    <td class="tk-mono">{{ $flight->departure_at ? $flight->departure_at->format('d M · h:i A') : '—' }}</td>
                    <td>{{ optional($flight->flightCategory)->name ?? '—' }}</td>
                    <td class="tk-mono">{{ $flight->seats_sold }} / {{ $flight->total_seats }}</td>
                    <td class="tk-mono" style="font-weight:600;">{{ number_format($flight->revenue, 0) }}</td>
                    <td>{{ optional($flight->agent)->name ?? 'Direct' }}</td>
                    <td><span class="mf-status-pill mf-status-{{ $flight->status }}">{{ ucfirst($flight->status) }}</span></td>
                    <td>
                        <div style="display:flex;gap:5px;">
                            <button type="button" class="mf-ic-btn view" onclick="viewFlight({{ $flight->id }})" title="View"><i class="fas fa-eye"></i></button>
                            <button type="button" class="mf-ic-btn edit edit-btn"
                                data-item_id="{{ $flight->id }}"
                                data-action="{{ route('role.contract-flights.update', ['role' => $role, 'contract_flight' => $flight->id]) }}"
                                data-flight_category_id="{{ $flight->flight_category_id }}"
                                data-flight_category_type_id="{{ $flight->flight_category_type_id }}"
                                data-handling_type="{{ $flight->handling_type }}"
                                data-ticket_id="{{ $flight->ticket_id }}"
                                data-airline_flight_no="{{ $flight->airline_flight_no }}"
                                data-departure_at="{{ $flight->departure_at ? $flight->departure_at->format('Y-m-d\TH:i') : '' }}"
                                data-total_seats="{{ $flight->total_seats }}"
                                data-seats_sold="{{ $flight->seats_sold }}"
                                data-cost_price="{{ $flight->cost_price }}"
                                data-revenue="{{ $flight->revenue }}"
                                data-agent_id="{{ $flight->agent_id }}"
                                data-vendor_id="{{ $flight->vendor_id }}"
                                data-portal_id="{{ $flight->portal_id }}"
                                data-cost_bank_id="{{ $flight->cost_bank_id }}"
                                data-cost_paid_amount="{{ $flight->cost_paid_amount }}"
                                data-purchase_date="{{ $flight->purchase_date?->format('Y-m-d') }}"
                                data-payable_date="{{ $flight->payable_date?->format('Y-m-d') }}"
                                data-status="{{ $flight->status }}"
                                data-notes="{{ $flight->notes }}"
                                title="Edit"><i class="fas fa-edit"></i></button>
                            <a class="mf-ic-btn inv" href="{{ route('role.contract-flights.invoice', ['role' => $role, 'contractFlight' => $flight->id]) }}" target="_blank" title="Invoice"><i class="fas fa-file-invoice"></i></a>
                            @if($flight->due_amount > 0)
                                <button type="button" class="mf-ic-btn" style="background:#fef3c7;color:#92400e;"
                                    onclick="openPayVendorModal({{ $flight->id }}, '{{ addslashes($flight->flight_number) }}', '{{ addslashes($flight->vendor?->name ?? 'Vendor (unassigned)') }}', {{ $flight->due_amount }})"
                                    title="Pay Vendor"><i class="fas fa-money-bill-wave"></i></button>
                            @endif
                            <button type="button" class="mf-ic-btn del" onclick="confirmDelete('{{ $flight->id }}', '{{ addslashes($flight->flight_number) }}')" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10">
                        <div class="tk-empty">
                            <i class="fas fa-plane-slash"></i>
                            <h4>No contract flights found</h4>
                            <p>Try adjusting your filters, or add a new contract flight.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="tk-pagination">
        Showing {{ $datas->count() }} of {{ $datas->total() }} contract flights · MTD
        <div style="margin-top:6px;">{{ $datas->links() }}</div>
    </div>
</div>

@include('contract-flights.create-modal')
@include('contract-flights.edit-modal')
@include('contract-flights.delete-modal')
@include('contract-flights.view-modal')
@include('contract-flights.pay-vendor-modal')

@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        const mfFlights = @json($datas->items());
        const scheduleCategories = @json($categories);

        $(document).ready(function() {
            $('#create_flight_category_id, #create_agent_id, #create_vendor_id, #create_portal_id, #create_cost_bank_id').select2({ dropdownParent: $('#createModal') });
            $('#edit_flight_category_id, #edit_agent_id, #edit_vendor_id, #edit_portal_id, #edit_cost_bank_id').select2({ dropdownParent: $('#editModal') });
            $('#create_flight_category_type_id').select2({ dropdownParent: $('#createModal') });
            $('#edit_flight_category_type_id').select2({ dropdownParent: $('#editModal') });
            $('#create_ticket_id').select2({ dropdownParent: $('#createModal'), placeholder: 'Select ticket route...' });
            $('#edit_ticket_id').select2({ dropdownParent: $('#editModal'), placeholder: 'Select ticket route...' });

            $.ajaxSetup({
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
            });

            // Show create modal
            $('.create-btn').click(function() {
                $('#createForm')[0].reset();
                $('#createForm .error-message').addClass('hidden');
                $('#create_flight_category_id, #create_agent_id, #create_vendor_id, #create_ticket_id').val(null).trigger('change');
                $('#create_vendor_id, #create_portal_id, #create_cost_bank_id').prop('disabled', false);
                $('#create_portal_id').val(null).trigger('change');
                $('#create_cost_bank_wrap, #create_cost_sched_wrap').show();
                $('#create_flight_category_type_id').val(null).trigger('change').prop('disabled', true);
                $('#createForm input[name="handling_type"][value="manpower_wise"]').prop('checked', true);
                $('#create_status').val('open');
                $('#createModal').removeClass('hidden');
            });

            // Show edit modal
            $('.edit-btn').click(function() {
                $('#editItemId').val($(this).data('item_id'));
                $('#edit_flight_category_id').val($(this).data('flight_category_id')).trigger('change');
                $('#edit_flight_category_type_id').val($(this).data('flight_category_type_id')).trigger('change');
                $('#editeForm input[name="handling_type"][value="' + ($(this).data('handling_type') || 'manpower_wise') + '"]').prop('checked', true);
                $('#edit_ticket_id').val($(this).data('ticket_id')).trigger('change');
                $('#edit_airline_flight_no').val($(this).data('airline_flight_no'));
                $('#edit_departure_at').val($(this).data('departure_at'));
                $('#edit_total_seats').val($(this).data('total_seats'));
                $('#edit_seats_sold').val($(this).data('seats_sold'));
                $('#edit_cost_price').val($(this).data('cost_price'));
                $('#edit_revenue').val($(this).data('revenue'));
                $('#edit_agent_id').val($(this).data('agent_id')).trigger('change');
                $('#edit_vendor_id').val($(this).data('vendor_id')).trigger('change');
                $('#edit_portal_id').val($(this).data('portal_id')).trigger('change');
                $('#edit_cost_bank_id').val($(this).data('cost_bank_id')).trigger('change');
                $('#edit_cost_paid_amount').val($(this).data('cost_paid_amount'));
                $('#edit_purchase_date').val($(this).data('purchase_date'));
                flightOnVendorChange(document.getElementById('edit_vendor_id'));
                flightOnPortalChange(document.getElementById('edit_portal_id'));
                $('#edit_payable_date').val($(this).data('payable_date'));
                $('#edit_status').val($(this).data('status'));
                $('#edit_notes').val($(this).data('notes'));
                $('#editeForm .error-message').addClass('hidden');
                $('#editSubmit').data('action', $(this).data('action'));
                $('#editModal').removeClass('hidden');
            });
            $('#create_flight_category_id, #edit_flight_category_id').on('change', function() {
                const prefix = this.id.startsWith('edit_') ? 'edit' : 'create';
                syncCategoryType(prefix, this.value);
            });

            // Close modals
            $('.modal-close-create, .modal-backdrop').click(function(e) {
                if ($(e.target).closest('.modal-close-create').length || $(e.target).hasClass('modal-backdrop')) {
                    $('#createModal').addClass('hidden');
                }
            });
            $('.modal-close-edit, .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-edit').length) {
                    $('#editModal').addClass('hidden');
                }
            });
            $('.modal-close-delete, .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-delete').length) {
                    $('#deleteModal').addClass('hidden');
                }
            });
            $('.modal-close-view, .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-view').length) {
                    $('#viewModal').addClass('hidden');
                }
            });

            // Create submit
            $('#createSubmit').click(function(e) {
                e.preventDefault();
                if (validateCreateForm()) {
                    $.ajax({
                        url: $('#createForm').attr('action'),
                        method: 'POST',
                        data: $('#createForm').serialize(),
                        success: function (response) {
                            if (response.success) {
                                Swal.fire({ icon: 'success', title: 'Done', text: 'Contract flight created successfully!' });
                                $('#createModal').addClass('hidden');
                                $('#createForm')[0].reset();
                                setTimeout(() => window.location.reload(), 800);
                            } else {
                                Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Something went wrong.' });
                            }
                        },
                        error: function (xhr) {
                            console.error(xhr.responseText);
                            Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to create contract flight.' });
                        }
                    });
                }
            });

            // Edit submit
            $('#editSubmit').click(function() {
                if (validateEditForm()) {
                    $.ajax({
                        url: $(this).data('action'),
                        method: 'POST',
                        data: $('#editeForm').serialize() + '&_method=PUT',
                        success: function (response) {
                            if (response.success) {
                                Swal.fire({ icon: 'success', title: 'Done', text: 'Contract flight updated successfully!' });
                                $('#editModal').addClass('hidden');
                                setTimeout(() => window.location.reload(), 800);
                            } else {
                                Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Update failed.' });
                            }
                        },
                        error: function (xhr) {
                            console.error('Error:', xhr.responseText);
                            Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong!' });
                        }
                    });
                }
            });

            // Delete confirmation
            $('#confirmDeleteBtn').click(function() {
                const dataId = $(this).data('item-id');
                const deleteUrl = $(this).data('action');
                $.ajax({
                    url: deleteUrl,
                    method: 'DELETE',
                    data: { item_id: dataId },
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Done', text: 'Contract flight deleted successfully!' });
                            $('#deleteModal').addClass('hidden');
                            setTimeout(() => window.location.reload(), 500);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Oops...', text: response.message });
                        }
                    },
                    error: function (xhr) {
                        console.error('Error:', xhr.responseText);
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong!' });
                    }
                });
            });
        });
        function syncCategoryType(prefix, categoryId) {
            const category = scheduleCategories.find(c => String(c.id) === String(categoryId));
            const $type = $('#' + prefix + '_flight_category_type_id');
            $type.find('option').prop('disabled', true).prop('hidden', true);
            $type.find('option[value=""]').prop('disabled', false).prop('hidden', false);
            if (category && category.flight_category_type_id) {
                $type.find('option[value="' + category.flight_category_type_id + '"]').prop('disabled', false).prop('hidden', false);
                $type.prop('disabled', false).val(category.flight_category_type_id).trigger('change.select2');
            } else {
                $type.prop('disabled', true).val('').trigger('change.select2');
            }
        }

        function validateCreateForm() {
            return validateFlightForm('create');
        }

        function validateEditForm() {
            return validateFlightForm('edit');
        }

        // ─── Quick-create Ticket Route (mirrors visa-processing's quick passport-holder create) ───
        const tkrStoreUrl = "{{ route('role.tickets.store', ['role' => $role]) }}";
        let _tkrTargetSelectId = null;

        function openTicketRouteModal(selectId) {
            _tkrTargetSelectId = selectId;
            ['tkrTitle'].forEach(id => { document.getElementById(id).value = ''; });
            $('#tkrFromAirport, #tkrToAirport, #tkrAirline, #tkrVendor, #tkrPortal').val('');
            $('#tkrPrice').val(0);
            $('#tkrQty').val(0);
            $('#tkrErr').hide();
            $('#tkrOverlay').addClass('open');
        }

        function closeTicketRouteModal() {
            $('#tkrOverlay').removeClass('open');
            _tkrTargetSelectId = null;
        }

        $(document).on('click', '#tkrOverlay', function(e) {
            if (e.target === this) closeTicketRouteModal();
        });

        function submitTicketRouteModal() {
            const err = $('#tkrErr');
            const saveBtn = $('#tkrSaveBtn');
            err.hide();

            const title = $('#tkrTitle').val().trim();
            const airlineId = $('#tkrAirline').val();
            const vendorId = $('#tkrVendor').val();
            const portalId = $('#tkrPortal').val();

            if (!title) { err.text('Ticket title is required.').show(); return; }
            if (!airlineId) { err.text('Please select an airline.').show(); return; }
            if (!vendorId && !portalId) { err.text('Please select at least a vendor or portal.').show(); return; }

            const payload = {
                title: title,
                airline_id: airlineId,
                from_airport_id: $('#tkrFromAirport').val(),
                to_airport_id: $('#tkrToAirport').val(),
                vendor_id: vendorId,
                portal_id: portalId,
                price: $('#tkrPrice').val() || 0,
                qty: $('#tkrQty').val() || 0,
                status: 1,
            };

            saveBtn.prop('disabled', true).text('Saving…');

            $.ajax({
                url: tkrStoreUrl,
                method: 'POST',
                data: payload,
                success: function(res) {
                    saveBtn.prop('disabled', false).html('💾 Save Route');
                    if (!res.success) {
                        err.text(res.message || 'Failed to save.').show();
                        return;
                    }
                    const t = res.data;
                    const fromCode = t.from_airport ? t.from_airport.code : '';
                    const toCode = t.to_airport ? t.to_airport.code : '';
                    const airlineName = t.airline ? t.airline.name : '';
                    let label = t.title;
                    if (airlineName) label += ' — ' + airlineName;
                    if (fromCode && toCode) label += ' (' + fromCode + ' → ' + toCode + ')';

                    // Add the new route to BOTH create & edit ticket-route selects
                    $('.ticket-route-select').each(function() {
                        if (!$(this).find('option[value="' + t.id + '"]').length) {
                            $(this).append(new Option(label, t.id, false, false));
                        }
                    });

                    // Auto-select in the triggering dropdown
                    if (_tkrTargetSelectId) {
                        $('#' + _tkrTargetSelectId).val(t.id).trigger('change');
                    }

                    closeTicketRouteModal();
                    Swal.fire({ icon: 'success', title: 'Added!', text: 'Ticket route saved.', timer: 1500, showConfirmButton: false });
                },
                error: function(xhr) {
                    saveBtn.prop('disabled', false).html('💾 Save Route');
                    err.text(xhr.responseJSON?.message || 'Server error. Please try again.').show();
                }
            });
        }

        function confirmDelete(id, name) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            const role = '{{ $role }}';
            $('#confirmDeleteBtn').data('action', `/${role}/contract-flights/${id}`);
            $('#deleteModal').removeClass('hidden');
        }

        // ── Vendor / Portal / Cost Bank mutual exclusion ────────────────
        function flightFieldPrefix(el) {
            return el.id.startsWith('edit_') ? 'edit' : 'create';
        }

        function flightOnVendorChange(sel) {
            const prefix = flightFieldPrefix(sel);
            const portalSel = document.getElementById(prefix + '_portal_id');
            if (!portalSel) return;
            setTimeout(function () {
                if (sel.value) {
                    $(portalSel).val(null).prop('disabled', true).trigger('change');
                } else {
                    $(portalSel).prop('disabled', false).trigger('change');
                }
                flightToggleCostBank(portalSel);
            }, 0);
        }

        function flightOnPortalChange(sel) {
            const prefix = flightFieldPrefix(sel);
            const vendorSel = document.getElementById(prefix + '_vendor_id');
            if (!vendorSel) return;
            setTimeout(function () {
                if (sel.value) {
                    $(vendorSel).val(null).prop('disabled', true).trigger('change');
                } else {
                    $(vendorSel).prop('disabled', false).trigger('change');
                }
                flightToggleCostBank(sel);
            }, 0);
        }

        function flightToggleCostBank(portalSel) {
            const prefix = flightFieldPrefix(portalSel);
            const bankSel = document.getElementById(prefix + '_cost_bank_id');
            const bankWrap = document.getElementById(prefix + '_cost_bank_wrap');
            const schedWrap = document.getElementById(prefix + '_cost_sched_wrap');
            if (bankSel) {
                $(bankSel).prop('disabled', !!portalSel.value);
                if (portalSel.value) { $(bankSel).val(null).trigger('change'); }
            }
            if (bankWrap) bankWrap.style.display = portalSel.value ? 'none' : '';
            if (schedWrap) schedWrap.style.display = portalSel.value ? 'none' : '';
        }

        // ── Pay Vendor ────────────────────────────────────────────────
        let _payVendorFlightId = null;

        function openPayVendorModal(id, flightNumber, vendorName, dueAmount) {
            _payVendorFlightId = id;
            $('#pv_flight_number').text(flightNumber);
            $('#pv_vendor_name').text(vendorName);
            $('#pv_due_amount').text(Number(dueAmount).toFixed(2));
            $('#pv_bank_id').val('');
            $('#pv_payment_date').val('{{ now()->toDateString() }}');
            $('#pv_payment_amount').val(dueAmount);
            $('#pv_reference_no').val('');
            $('#pv_error').addClass('hidden').text('');
            $('#payVendorModal').removeClass('hidden');
        }

        $('.modal-close-pay-vendor').on('click', function () {
            $('#payVendorModal').addClass('hidden');
            _payVendorFlightId = null;
        });

        $('#pv_submit_btn').on('click', function () {
            const role = '{{ $role }}';
            const err  = $('#pv_error');
            err.addClass('hidden').text('');

            if (!$('#pv_bank_id').val()) {
                err.removeClass('hidden').text('Please select a bank.');
                return;
            }
            if (!$('#pv_payment_amount').val() || Number($('#pv_payment_amount').val()) <= 0) {
                err.removeClass('hidden').text('Please enter a valid payment amount.');
                return;
            }

            $.ajax({
                url: `/${role}/contract-flights/${_payVendorFlightId}/pay-vendor`,
                method: 'POST',
                data: {
                    bank_id:        $('#pv_bank_id').val(),
                    payment_date:   $('#pv_payment_date').val(),
                    payment_amount: $('#pv_payment_amount').val(),
                    reference_no:   $('#pv_reference_no').val(),
                },
                success: function (response) {
                    if (response.success) {
                        $('#payVendorModal').addClass('hidden');
                        window.location.reload();
                    } else {
                        err.removeClass('hidden').text(response.message || 'Payment failed.');
                    }
                },
                error: function (xhr) {
                    err.removeClass('hidden').text(xhr.responseJSON?.message || 'Payment failed.');
                }
            });
        });

        function viewFlight(id) {
            const f = mfFlights.find(x => x.id === id);
            if (!f) return;
            $('#view_flight_number').text(f.flight_number);
            $('#view_airline').text((f.airline ? f.airline.name : '—') + (f.airline_flight_no ? ' (' + f.airline_flight_no + ')' : ''));
            $('#view_route').text(f.route || '—');
            $('#view_departure').text(f.departure_at ? new Date(f.departure_at).toLocaleString() : '—');
            $('#view_category').text(f.flight_category ? f.flight_category.name : '—');
            $('#view_category_type').text(f.category_type ? f.category_type.name : '-');
            $('#view_handling_type').text((f.handling_type || 'manpower_wise').replace('_', '-').replace(/\b\w/g, c => c.toUpperCase()));
            $('#view_seats').text(f.seats_sold + ' / ' + f.total_seats);
            $('#view_revenue').text('৳ ' + Number(f.revenue).toLocaleString());
            $('#view_profit').text('৳ ' + Number(f.revenue - f.cost_price).toLocaleString());
            $('#view_agent').text(f.agent ? f.agent.name : 'Direct');
            $('#view_status').html(`<span class="mf-status-pill mf-status-${f.status}">${f.status.charAt(0).toUpperCase() + f.status.slice(1)}</span>`);
            $('#view_notes').text(f.notes || '—');
            $('#viewModal').removeClass('hidden');
            $('#fcm-cmt-placeholder').html(cmtHtml('contract_flight', f.id));
            loadComments('contract_flight', f.id);
        }
    </script>
    @include('contract-flights.operations-script')
    @include('components.comment-panel')
@endsection
