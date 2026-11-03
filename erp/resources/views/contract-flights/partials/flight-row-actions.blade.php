{{-- Flight-level actions on a passenger row of Flights & Passengers: edit the
     batch (opens the flight modal with its passenger builder), invoice, pay
     vendor when there is a due, delete the batch. Titled by flight number
     because they sit on a person's row. The .edit-btn class and data-* set
     are what the Add/Edit Flight script hydrates from — keep them intact. --}}
<button type="button" class="edit-btn mfp-btn amber"
    data-item_id="{{ $flight->id }}"
    data-action="{{ route('role.contract-flights.update', ['role' => $role, 'contract_flight' => $flight->id]) }}"
    data-handling_type="{{ $flight->handling_type }}"
    data-ticket_id="{{ $flight->ticket_id }}"
    data-airline_flight_no="{{ $flight->airline_flight_no }}"
    data-departure_at="{{ $flight->departure_at ? $flight->departure_at->format('Y-m-d\TH:i') : '' }}"
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
    title="Edit flight {{ $flight->flight_number }} &amp; its passengers">
    <i class="fas fa-pencil-alt"></i>
</button>
<a href="{{ route('role.contract-flights.invoice', ['role' => $role, 'contractFlight' => $flight->id]) }}" target="_blank"
    class="mfp-btn violet" title="Invoice for {{ $flight->flight_number }}">
    <i class="fas fa-file-invoice"></i>
</a>
@if($flight->due_amount > 0)
    <button type="button" class="mfp-btn green"
        onclick="openPayVendorModal({{ $flight->id }}, '{{ addslashes($flight->flight_number) }}', '{{ addslashes($flight->vendor?->name ?? 'Vendor (unassigned)') }}', {{ $flight->due_amount }})"
        title="Pay vendor for {{ $flight->flight_number }}">
        <i class="fas fa-money-bill-wave"></i>
    </button>
@endif
<button type="button" class="mfp-btn red"
    onclick="confirmDelete('{{ $flight->id }}', '{{ addslashes($flight->flight_number) }}')" title="Delete flight {{ $flight->flight_number }}">
    <i class="fas fa-trash"></i>
</button>
