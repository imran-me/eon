@extends('layout.app')
@section('meta-information')
    <title>Flight Schedule</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .mf-status-open, .mf-status-boarding { background:#fef3c7; color:#92400e; border-radius:20px; padding:3px 10px; font-size:10.5px; font-weight:800; display:inline-block; }
    .mf-status-departed { background:#dcfce7; color:#166534; border-radius:20px; padding:3px 10px; font-size:10.5px; font-weight:800; display:inline-block; }
    .mf-status-cancelled { background:#fee2e2; color:#991b1b; border-radius:20px; padding:3px 10px; font-size:10.5px; font-weight:800; display:inline-block; }
    .swal2-container { z-index: 100000 !important; }
    .select2-container--open, .select2-dropdown { z-index: 99999; }
</style>
@endsection
@section('main-content')
@php
    $role = \Illuminate\Support\Str::slug(Auth::user()->getRoleNames()->first());
    $today = now()->startOfDay();
    $groupedFlights = $datas->groupBy(fn($flight) => $flight->departure_at ? $flight->departure_at->toDateString() : 'unscheduled');
    $labelForDate = function ($dateKey) use ($today) {
        if ($dateKey === 'unscheduled') return 'Unscheduled Flights';
        $date = \Carbon\Carbon::parse($dateKey);
        $prefix = $date->isSameDay((clone $today)->subDay()) ? 'Yesterday' : ($date->isSameDay($today) ? 'Today' : ($date->isSameDay((clone $today)->addDay()) ? 'Tomorrow' : $date->format('l')));
        return $prefix . ' - ' . $date->format('d M Y (l)');
    };
    $departedCount = fn($flights) => $flights->where('status', 'departed')->count();
    $flightStoreRoute = route('role.flight-schedules.store', ['role' => $role]);
    $flightCreateTitle = 'Add Flight';
    $flightCreateSub = 'Create a scheduled contract flight';
    $flightEditTitle = 'Edit Flight';
    $flightEditSub = 'Update scheduled flight details';
@endphp

<header class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm mb-4">
    <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-blue-50 text-blue-600 flex-shrink-0">
        <i class="fas fa-calendar-alt"></i>
    </span>
    <div class="flex-1">
        <h1 class="text-lg font-bold text-slate-900">Flight Schedule</h1>
        <p class="text-xs text-slate-500">Contract flights - yesterday / today / tomorrow + filter by date or category</p>
    </div>
    <button class="create-btn rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700"><i class="fas fa-plus mr-1"></i> Add Flight</button>
</header>

@if($reminderFlights->isNotEmpty())
<div class="flex items-center gap-4 rounded-2xl bg-gradient-to-r from-orange-500 to-red-500 text-white px-5 py-4 mb-4 shadow-lg" id="scheduleReminder">
    <div class="h-11 w-11 rounded-xl bg-white/20 flex items-center justify-center text-xl flex-shrink-0"><i class="fas fa-clock"></i></div>
    <div class="flex-1 min-w-0">
        <div class="text-sm font-bold">Reminder - tomorrow ({{ now()->addDay()->format('d M Y') }}) {{ $reminderFlights->count() }} flights scheduled</div>
        <div class="text-xs mt-1 font-semibold opacity-95">
            {{ $reminderFlights->sum('seats_sold') }} passengers -
            first departure {{ optional($reminderFlights->first()->departure_at)->format('h:i A') }} -
            {{ $reminderFlights->first()->airline?->name ?? 'Airline pending' }}
            {{ $reminderFlights->first()->airline_flight_no ? '(' . $reminderFlights->first()->airline_flight_no . ')' : '' }}
            {{ $reminderFlights->first()->route ? ' ' . $reminderFlights->first()->route : '' }}
        </div>
    </div>
    <button type="button" class="rounded-xl bg-white text-orange-700 px-4 py-2 text-xs font-bold cursor-pointer"><i class="fas fa-bell"></i> Send Reminders</button>
    <button type="button" class="h-7 w-7 rounded-lg border border-white/40 bg-white/15 text-white cursor-pointer" onclick="document.getElementById('scheduleReminder').style.display='none'">x</button>
</div>
@endif

<section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <div class="rounded-xl bg-amber-50 p-4">
        <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-orange-400 to-orange-600 flex items-center justify-center text-white text-xl mb-3 shadow-md"><i class="fas fa-chart-bar"></i></div>
        <div class="text-3xl font-black text-amber-900 leading-none mb-1">{{ $stats['yesterday_count'] }}</div>
        <div class="text-[10px] font-bold uppercase tracking-wide text-orange-700">Yesterday ({{ $stats['yesterday_label'] }})</div>
        <div class="text-xs text-slate-500 mt-1.5 font-semibold">Departed flights</div>
    </div>
    <div class="rounded-xl bg-blue-50 p-4">
        <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-blue-400 to-blue-700 flex items-center justify-center text-white text-xl mb-3 shadow-md"><i class="fas fa-plane"></i></div>
        <div class="text-3xl font-black text-blue-900 leading-none mb-1">{{ $stats['today_count'] }}</div>
        <div class="text-[10px] font-bold uppercase tracking-wide text-blue-700">Today ({{ $stats['today_label'] }})</div>
        <div class="text-xs text-slate-500 mt-1.5 font-semibold">{{ $stats['today_departed'] }} departed, {{ $stats['today_upcoming'] }} upcoming</div>
    </div>
    <div class="rounded-xl bg-emerald-50 p-4">
        <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center text-white text-xl mb-3 shadow-md"><i class="fas fa-user-clock"></i></div>
        <div class="text-3xl font-black text-emerald-900 leading-none mb-1">{{ $stats['tomorrow_count'] }}</div>
        <div class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Tomorrow ({{ $stats['tomorrow_label'] }})</div>
        <div class="text-xs text-slate-500 mt-1.5 font-semibold">Scheduled flights</div>
    </div>
    <div class="rounded-xl bg-violet-50 p-4">
        <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-violet-400 to-violet-700 flex items-center justify-center text-white text-xl mb-3 shadow-md"><i class="fas fa-chart-line"></i></div>
        <div class="text-3xl font-black text-violet-900 leading-none mb-1">{{ $stats['week_count'] }}</div>
        <div class="text-[10px] font-bold uppercase tracking-wide text-violet-700">This Week</div>
        <div class="text-xs text-slate-500 mt-1.5 font-semibold">Total contract flights</div>
    </div>
</section>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm mb-4">
 <form method="GET" action="{{ route('role.flight-schedules.index', ['role' => $role]) }}" class="flex flex-wrap items-center gap-2.5 bg-slate-50 px-4 py-3">
  <div class="flex min-w-48 flex-1 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2">
   <i class="fas fa-search text-xs text-slate-400"></i>
   <input type="text" name="search" value="{{ request('search') }}" placeholder="Search flight #, airline, route..." class="flex-1 border-0 bg-transparent text-sm outline-none">
  </div>
  <input type="date" name="date" value="{{ request('date') }}" class="h-9 rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-400">
  <select name="flight_category_id" class="h-9 rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-400">
   <option value="">All Categories</option>
   @foreach($categories as $cat)
    <option value="{{ $cat->id }}" {{ request('flight_category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
   @endforeach
  </select>
  <select name="status" class="h-9 rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-400">
   <option value="">All Status</option>
   <option value="open" {{ request('status') == 'open' ? 'selected' : '' }}>Open</option>
   <option value="boarding" {{ request('status') == 'boarding' ? 'selected' : '' }}>Boarding</option>
   <option value="departed" {{ request('status') == 'departed' ? 'selected' : '' }}>Departed</option>
   <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
  </select>
  <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white">Filter</button>
  <a href="{{ route('role.flight-schedules.index', ['role' => $role]) }}" class="rounded-lg border px-4 py-2 text-xs font-semibold text-slate-600">Reset</a>
 </form>
</div>

@forelse($groupedFlights as $dateKey => $flights)
<div class="overflow-hidden rounded-xl bg-white shadow-sm mb-4">
    <div class="flex items-center gap-2.5 px-4 py-3">
        <i class="fas fa-chart-bar text-orange-500"></i>
        <div class="text-sm font-bold text-slate-900">{{ $labelForDate($dateKey) }}</div>
        <div class="rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-bold text-orange-700">{{ $departedCount($flights) ?: $flights->count() }} {{ $departedCount($flights) ? 'flights departed' : 'flights scheduled' }}</div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase text-slate-500">Flight #</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase text-slate-500">Airline</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase text-slate-500">Route</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase text-slate-500">Departure</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase text-slate-500">Category</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase text-slate-500">Seats (Sold / Total)</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase text-slate-500">Revenue (BDT)</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase text-slate-500">Status</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase text-slate-500">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($flights as $flight)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-sm"><span class="font-mono font-bold text-blue-600">{{ $flight->flight_number }}</span></td>
                    <td class="px-4 py-3 text-sm">{{ optional($flight->airline)->name ?? 'Airline pending' }}{{ $flight->airline_flight_no ? ' ('.$flight->airline_flight_no.')' : '' }}</td>
                    <td class="px-4 py-3 text-sm">{{ $flight->route ?? '-' }}</td>
                    <td class="px-4 py-3 text-sm font-mono">{{ $flight->departure_at ? $flight->departure_at->format('h:i A') : '-' }}</td>
                    <td class="px-4 py-3 text-sm">{{ optional($flight->flightCategory)->name ?? '-' }}</td>
                    <td class="px-4 py-3 text-sm font-mono">{{ $flight->seats_sold }} / {{ $flight->total_seats }}</td>
                    <td class="px-4 py-3 text-sm font-mono font-bold">{{ number_format($flight->revenue, 0) }}</td>
                    <td class="px-4 py-3 text-sm"><span class="mf-status-pill mf-status-{{ $flight->status }}">{{ ucfirst($flight->status) }}</span></td>
                    <td class="px-4 py-3">
                        <div class="flex gap-1.5">
                            <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600" onclick="viewFlight({{ $flight->id }})" title="View"><i class="fas fa-eye text-xs"></i></button>
                            <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100 edit-btn"
                                data-item_id="{{ $flight->id }}"
                                data-action="{{ route('role.flight-schedules.update', ['role' => $role, 'flight_schedule' => $flight->id]) }}"
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
                                data-status="{{ $flight->status }}"
                                data-notes="{{ $flight->notes }}"
                                title="Edit"><i class="fas fa-edit text-xs"></i></button>
                            <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100" onclick="confirmDelete('{{ $flight->id }}', '{{ addslashes($flight->flight_number) }}')" title="Delete"><i class="fas fa-trash text-xs"></i></button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@empty
<div class="overflow-hidden rounded-xl bg-white shadow-sm mb-4">
    <div class="p-12 text-center text-sm text-slate-500">
        <i class="fas fa-plane-slash text-4xl opacity-30 mb-3 block"></i>
        <h4 class="font-bold mb-1">No scheduled flights found</h4>
        <p>Try another date or add a new flight.</p>
    </div>
</div>
@endforelse

@include('contract-flights.create-modal')
@include('contract-flights.edit-modal')
@include('contract-flights.delete-modal')
@include('contract-flights.view-modal')
@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        const mfFlights = @json($datas->values());
        const scheduleCategories = @json($categories);
        $(document).ready(function() {
            $('#create_flight_category_id, #create_agent_id').select2({ dropdownParent: $('#createModal') });
            $('#edit_flight_category_id, #edit_agent_id').select2({ dropdownParent: $('#editModal') });
            $('#create_flight_category_type_id').select2({ dropdownParent: $('#createModal') });
            $('#edit_flight_category_type_id').select2({ dropdownParent: $('#editModal') });
            $('#create_ticket_id').select2({ dropdownParent: $('#createModal'), placeholder: 'Select ticket route...' });
            $('#edit_ticket_id').select2({ dropdownParent: $('#editModal'), placeholder: 'Select ticket route...' });
            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
            $('.create-btn').click(function() {
                $('#createForm')[0].reset();
                $('#createForm .error-message').addClass('hidden');
                $('#create_flight_category_id, #create_agent_id, #create_ticket_id').val(null).trigger('change');
                $('#create_flight_category_type_id').val(null).trigger('change').prop('disabled', true);
                $('#createForm input[name="handling_type"][value="manpower_wise"]').prop('checked', true);
                $('#create_status').val('open');
                $('#createModal').removeClass('hidden');
            });
            if (new URLSearchParams(window.location.search).get('open') === 'create') {
                $('.create-btn').first().trigger('click');
            }
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
            $('.modal-close-create, .modal-backdrop').click(function(e) { if ($(e.target).closest('.modal-close-create').length || $(e.target).hasClass('modal-backdrop')) $('#createModal').addClass('hidden'); });
            $('.modal-close-edit, .modal-backdrop').click(function(e) { if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-edit').length) $('#editModal').addClass('hidden'); });
            $('.modal-close-delete, .modal-backdrop').click(function(e) { if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-delete').length) $('#deleteModal').addClass('hidden'); });
            $('.modal-close-view, .modal-backdrop').click(function(e) { if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-view').length) $('#viewModal').addClass('hidden'); });
            $('#createSubmit').click(function(e) {
                e.preventDefault();
                if (!validateCreateForm()) return;
                $.ajax({
                    url: $('#createForm').attr('action'), method: 'POST', data: $('#createForm').serialize(),
                    success: function(response) {
                        if (response.success) { Swal.fire({ icon: 'success', title: 'Done', text: 'Flight scheduled successfully!' }); $('#createModal').addClass('hidden'); setTimeout(() => window.location.reload(), 800); }
                        else { Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Something went wrong.' }); }
                    },
                    error: function(xhr) { console.error(xhr.responseText); Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to schedule flight.' }); }
                });
            });
            $('#editSubmit').click(function() {
                if (!validateEditForm()) return;
                $.ajax({
                    url: $(this).data('action'), method: 'POST', data: $('#editeForm').serialize() + '&_method=PUT',
                    success: function(response) {
                        if (response.success) { Swal.fire({ icon: 'success', title: 'Done', text: 'Flight updated successfully!' }); $('#editModal').addClass('hidden'); setTimeout(() => window.location.reload(), 800); }
                        else { Swal.fire({ icon: 'error', title: 'Oops...', text: response.message || 'Update failed.' }); }
                    },
                    error: function(xhr) { console.error(xhr.responseText); Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong!' }); }
                });
            });
            $('#confirmDeleteBtn').click(function() {
                const dataId = $(this).data('item-id');
                $.ajax({
                    url: $(this).data('action'), method: 'DELETE', data: { item_id: dataId },
                    success: function(response) {
                        if (response.success) { Swal.fire({ icon: 'success', title: 'Done', text: 'Flight deleted successfully!' }); $('#deleteModal').addClass('hidden'); setTimeout(() => window.location.reload(), 500); }
                        else { Swal.fire({ icon: 'error', title: 'Oops...', text: response.message }); }
                    },
                    error: function(xhr) { console.error(xhr.responseText); Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong!' }); }
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
        const tkrStoreUrl = "{{ route('role.tickets.store', ['role' => $role]) }}";
        let _tkrTargetSelectId = null;
        function openTicketRouteModal(selectId) {
            _tkrTargetSelectId = selectId;
            ['tkrTitle'].forEach(id => { document.getElementById(id).value = ''; });
            $('#tkrFromAirport, #tkrToAirport, #tkrAirline, #tkrVendor, #tkrPortal').val('');
            $('#tkrPrice').val(0); $('#tkrQty').val(0); $('#tkrErr').hide(); $('#tkrOverlay').addClass('open');
        }
        function closeTicketRouteModal() { $('#tkrOverlay').removeClass('open'); _tkrTargetSelectId = null; }
        $(document).on('click', '#tkrOverlay', function(e) { if (e.target === this) closeTicketRouteModal(); });
        function submitTicketRouteModal() {
            const err = $('#tkrErr'); const saveBtn = $('#tkrSaveBtn'); err.hide();
            const title = $('#tkrTitle').val().trim(); const airlineId = $('#tkrAirline').val(); const vendorId = $('#tkrVendor').val(); const portalId = $('#tkrPortal').val();
            if (!title) { err.text('Ticket title is required.').show(); return; }
            if (!airlineId) { err.text('Please select an airline.').show(); return; }
            if (!vendorId && !portalId) { err.text('Please select at least a vendor or portal.').show(); return; }
            saveBtn.prop('disabled', true).text('Saving...');
            $.ajax({
                url: tkrStoreUrl, method: 'POST',
                data: { title: title, airline_id: airlineId, from_airport_id: $('#tkrFromAirport').val(), to_airport_id: $('#tkrToAirport').val(), vendor_id: vendorId, portal_id: portalId, price: $('#tkrPrice').val() || 0, qty: $('#tkrQty').val() || 0, status: 1 },
                success: function(res) {
                    saveBtn.prop('disabled', false).html('Save Route');
                    if (!res.success) { err.text(res.message || 'Failed to save.').show(); return; }
                    const t = res.data; const fromCode = t.from_airport ? t.from_airport.code : ''; const toCode = t.to_airport ? t.to_airport.code : ''; const airlineName = t.airline ? t.airline.name : '';
                    let label = t.title; if (airlineName) label += ' - ' + airlineName; if (fromCode && toCode) label += ' (' + fromCode + ' to ' + toCode + ')';
                    $('.ticket-route-select').each(function() { if (!$(this).find('option[value="' + t.id + '"]').length) $(this).append(new Option(label, t.id, false, false)); });
                    if (_tkrTargetSelectId) $('#' + _tkrTargetSelectId).val(t.id).trigger('change');
                    closeTicketRouteModal(); Swal.fire({ icon: 'success', title: 'Added!', text: 'Ticket route saved.', timer: 1500, showConfirmButton: false });
                },
                error: function(xhr) { saveBtn.prop('disabled', false).html('Save Route'); err.text(xhr.responseJSON?.message || 'Server error. Please try again.').show(); }
            });
        }
        function confirmDelete(id, name) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            $('#confirmDeleteBtn').data('action', '/{{ $role }}/flight-schedules/' + id);
            $('#deleteModal').removeClass('hidden');
        }
        function viewFlight(id) {
            const f = mfFlights.find(x => x.id === id); if (!f) return;
            $('#view_flight_number').text(f.flight_number);
            $('#view_airline').text((f.airline ? f.airline.name : '-') + (f.airline_flight_no ? ' (' + f.airline_flight_no + ')' : ''));
            $('#view_route').text(f.route || '-');
            $('#view_departure').text(f.departure_at ? new Date(f.departure_at).toLocaleString() : '-');
            $('#view_category').text(f.flight_category ? f.flight_category.name : '-');
            $('#view_category_type').text(f.category_type ? f.category_type.name : '-');
            $('#view_handling_type').text((f.handling_type || 'manpower_wise').replace('_', '-').replace(/\b\w/g, c => c.toUpperCase()));
            $('#view_seats').text(f.seats_sold + ' / ' + f.total_seats);
            $('#view_revenue').text('BDT ' + Number(f.revenue).toLocaleString());
            $('#view_profit').text('BDT ' + Number(f.revenue - f.cost_price).toLocaleString());
            $('#view_agent').text(f.agent ? f.agent.name : 'Direct');
            $('#view_status').html('<span class="mf-status-pill mf-status-' + f.status + '">' + f.status.charAt(0).toUpperCase() + f.status.slice(1) + '</span>');
            $('#view_notes').text(f.notes || '-');
            $('#viewModal').removeClass('hidden');
        }
    </script>
    @include('contract-flights.operations-script')
@endsection
