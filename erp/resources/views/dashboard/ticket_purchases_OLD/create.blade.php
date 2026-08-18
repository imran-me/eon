@extends('layout.app')

@section('meta-information')
    <title>Ticket Purchase Create</title>
@endsection

@section('main-content')
<div class="bg-white p-8 mt-1">
    <h2 class="text-2xl font-semibold mb-6 text-gray-700">Add Ticket Purchase</h2>

    {{-- Success Message --}}
    @if (session('success'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">
            {{ session('success') }}
        </div>
    @endif

    {{-- Validation Errors --}}
    @if ($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-700 rounded">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('role.ticket-purchase.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST" enctype="multipart/form-data">
        @csrf

        <!-- Passport Holder -->
        <div class="mb-4">
            <label for="passport_holder_id" class="block text-sm font-medium text-gray-700">Passport Holder</label>
            <select name="passport_holder_id" id="passport_holder_id" required
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">Select Passport Holder</option>
                @foreach($passportHolders as $holder)
                    <option value="{{ $holder->id }}">{{ $holder->name }}</option>
                @endforeach
            </select>
        </div>

        <!-- Vendor -->
        <div class="mb-4">
            <label for="vendor_id" class="block text-sm font-medium text-gray-700">Vendor</label>
            <select name="vendor_id" id="vendor_id"
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">Select Vendor</option>
                @foreach($vendors as $vendor)
                    <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                @endforeach
            </select>
        </div>

        <!-- Portal -->
        <div class="mb-4">
            <label for="portal_id" class="block text-sm font-medium text-gray-700">Portal</label>
            <select name="portal_id" id="portal_id"
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">Select Portal</option>
                @foreach($portals as $portal)
                    <option value="{{ $portal->id }}">{{ $portal->name }}</option>
                @endforeach
            </select>
        </div>

        <!-- Bank -->
        <div class="mb-4">
            <label for="bank_id" class="block text-sm font-medium text-gray-700">Bank</label>
            <select name="bank_id" id="bank_id"
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">Select Bank</option>
                @foreach($banks as $bank)
                    <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                @endforeach
            </select>
        </div>

       <!-- Ticket Type -->
        <div class="mb-4">
            <label for="ticket_type" class="block text-sm font-medium text-gray-700">Ticket Type</label>
            <select name="ticket_type" id="ticket_type" required
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">Select Ticket Type</option>
                <option value="air" {{ old('ticket_type') == 'air' ? 'selected' : '' }}>Air</option>
                <option value="bus" {{ old('ticket_type') == 'bus' ? 'selected' : '' }}>Bus</option>
                <option value="train" {{ old('ticket_type') == 'train' ? 'selected' : '' }}>Train</option>
                <option value="other" {{ old('ticket_type') == 'other' ? 'selected' : '' }}>Other</option>
            </select>
        </div>

        <!-- Source (hidden by default, shown only if ticket_type = other) -->
        <div class="mb-4" id="source-wrapper" style="display: none;">
            <label for="source" class="block text-sm font-medium text-gray-700">Source</label>
            <input type="text" name="source" id="source" value="{{ old('source') }}"
                class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div class="mb-4">
    <label for="trip_type" class="block text-sm font-medium text-gray-700">Trip Type</label>
    <select name="trip_type" id="trip_type" required
            class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        <option value="">Select Trip Type</option>
        <option value="one-way" {{ old('trip_type') == 'one-way' ? 'selected' : '' }}>One-way</option>
        <option value="two-way" {{ old('trip_type') == 'two-way' ? 'selected' : '' }}>Two-way</option>
    </select>
</div>

        <!-- Outbound Leg -->
<h3 class="text-lg font-semibold mb-4 text-gray-700">Outbound</h3>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
    <div>
        <label for="legs[0][from_location]" class="block text-sm font-medium text-gray-700">From</label>
        <input type="text" name="legs[0][from_location]" required
               class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
    </div>

    <div>
        <label for="legs[0][to_location]" class="block text-sm font-medium text-gray-700">To</label>
        <input type="text" name="legs[0][to_location]" required
               class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
    <div>
        <label for="legs[0][travel_date]" class="block text-sm font-medium text-gray-700">Travel Date</label>
        <input type="date" name="legs[0][travel_date]" required
               class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
    </div>

    <div>
        <label for="legs[0][seat_number]" class="block text-sm font-medium text-gray-700">Seat Number</label>
        <input type="text" name="legs[0][seat_number]"
               class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
    </div>
</div>

<div class="mb-4">
    <label for="legs[0][attachment]" class="block text-sm font-medium text-gray-700">Attachment</label>
    <input type="file" name="legs[0][attachment]"
           class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
</div>

<!-- Return Leg (optional) -->
<h3 class="text-lg font-semibold mb-4 text-gray-700">Return (optional)</h3>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
    <div>
        <label for="legs[1][from_location]" class="block text-sm font-medium text-gray-700">From</label>
        <input type="text" name="legs[1][from_location]"
               class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
    </div>

    <div>
        <label for="legs[1][to_location]" class="block text-sm font-medium text-gray-700">To</label>
        <input type="text" name="legs[1][to_location]"
               class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
    <div>
        <label for="legs[1][travel_date]" class="block text-sm font-medium text-gray-700">Travel Date</label>
        <input type="date" name="legs[1][travel_date]"
               class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
    </div>

    <div>
        <label for="legs[1][seat_number]" class="block text-sm font-medium text-gray-700">Seat Number</label>
        <input type="text" name="legs[1][seat_number]"
               class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
    </div>
</div>

<div class="mb-4">
    <label for="legs[1][attachment]" class="block text-sm font-medium text-gray-700">Attachment</label>
    <input type="file" name="legs[1][attachment]"
           class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
</div>



        <!-- Airline / Operator -->
        <div class="mb-4">
            <label for="airline_or_operator" class="block text-sm font-medium text-gray-700">Airline / Operator</label>
            <input type="text" name="airline_or_operator" id="airline_or_operator"
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>
        
        <!-- Purchase Date -->
        <div class="mb-4">
            <label for="purchase_date" class="block text-sm font-medium text-gray-700">Purchase Date</label>
            <input type="date" name="purchase_date" id="purchase_date" value="{{ old('purchase_date') }}"
            class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <!-- Ticket No -->
        <div class="mb-4">
            <label for="ticket_no" class="block text-sm font-medium text-gray-700">Ticket No</label>
            <input type="text" name="ticket_no" id="ticket_no" value="{{ $ticketNo }}" readonly
            class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <!-- Amount -->
        <div class="mb-4">
            <label for="amount" class="block text-sm font-medium text-gray-700">Amount</label>
            <input type="number" step="0.01" name="amount" id="amount"
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <!-- Currency -->
        <div class="mb-4">
            <label for="currency" class="block text-sm font-medium text-gray-700">Currency</label>
            <input type="text" name="currency" id="currency"
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <!-- Status -->
        <div class="mb-4">
            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
            <select name="status" id="status"
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="pending">Pending</option>
                <option value="booked">Booked</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>

        <!-- Attachment -->
        <div class="mb-4">
            <label for="attachment" class="block text-sm font-medium text-gray-700">Attachment</label>
            <input type="file" name="attachment" id="attachment"
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <!-- Submit -->
        <div class="flex justify-end">
            <button type="submit"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200 cursor-pointer">
                Save
            </button>
        </div>
    </form>
</div>
@endsection


@section('raw-script')

<!-- Script -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const ticketType = document.getElementById('ticket_type');
        const sourceWrapper = document.getElementById('source-wrapper');

        function toggleSourceField() {
            if (ticketType.value === 'other') {
                sourceWrapper.style.display = 'block';
            } else {
                sourceWrapper.style.display = 'none';
                document.getElementById('source').value = ''; // reset if hidden
            }
        }

        // Run on load (for old value after validation error)
        toggleSourceField();

        // Run on change
        ticketType.addEventListener('change', toggleSourceField);
    });
</script>

@endsection
