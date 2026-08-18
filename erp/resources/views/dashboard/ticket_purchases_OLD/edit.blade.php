@extends('layout.app')

@section('meta-information')
    <title>Edit Ticket Purchase</title>
@endsection

@section('main-content')
<div class="bg-white p-8 mt-1">
    <h2 class="text-2xl font-semibold mb-6 text-gray-700">Edit Ticket Purchase</h2>

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

    <form action="{{ route('role.ticket-purchase.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'ticket_purchase' => $ticketPurchase->id]) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <!-- Passport Holder -->
        <div class="mb-4">
            <label for="passport_holder_id" class="block text-sm font-medium text-gray-700">Passport Holder</label>
            <select name="passport_holder_id" id="passport_holder_id" required
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">Select Passport Holder</option>
                @foreach($passportHolders as $holder)
                    <option value="{{ $holder->id }}" {{ $ticketPurchase->passport_holder_id == $holder->id ? 'selected' : '' }}>
                        {{ $holder->name }}
                    </option>
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
                    <option value="{{ $vendor->id }}" {{ $ticketPurchase->vendor_id == $vendor->id ? 'selected' : '' }}>
                        {{ $vendor->name }}
                    </option>
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
                    <option value="{{ $portal->id }}" {{ $ticketPurchase->portal_id == $portal->id ? 'selected' : '' }}>
                        {{ $portal->name }}
                    </option>
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
                    <option value="{{ $bank->id }}" {{ $ticketPurchase->bank_id == $bank->id ? 'selected' : '' }}>
                        {{ $bank->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <!-- Ticket Type -->
        <div class="mb-4">
            <label for="ticket_type" class="block text-sm font-medium text-gray-700">Ticket Type</label>
            <select name="ticket_type" id="ticket_type" required
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">Select Ticket Type</option>
                <option value="air" {{ $ticketPurchase->ticket_type == 'air' ? 'selected' : '' }}>Air</option>
                <option value="bus" {{ $ticketPurchase->ticket_type == 'bus' ? 'selected' : '' }}>Bus</option>
                <option value="train" {{ $ticketPurchase->ticket_type == 'train' ? 'selected' : '' }}>Train</option>
                <option value="other" {{ $ticketPurchase->ticket_type == 'other' ? 'selected' : '' }}>Other</option>
            </select>
        </div>

        <!-- Source -->
        <div class="mb-4" id="source-wrapper" style="{{ $ticketPurchase->ticket_type == 'other' ? '' : 'display:none;' }}">
            <label for="source" class="block text-sm font-medium text-gray-700">Source</label>
            <input type="text" name="source" id="source" value="{{ old('source', $ticketPurchase->source) }}"
                class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <!-- Trip Type -->
        <div class="mb-4">
            <label for="trip_type" class="block text-sm font-medium text-gray-700">Trip Type</label>
            <select name="trip_type" id="trip_type" required
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">Select Trip Type</option>
                <option value="one-way" {{ $ticketPurchase->trip_type == 'one-way' ? 'selected' : '' }}>One-way</option>
                <option value="two-way" {{ $ticketPurchase->trip_type == 'two-way' ? 'selected' : '' }}>Two-way</option>
            </select>
        </div>

        <!-- Outbound Leg -->
        <h3 class="text-lg font-semibold mb-4 text-gray-700">Outbound</h3>
        @php $outbound = $ticketPurchase->legs[0] ?? null; @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">From</label>
                <input type="text" name="legs[0][from_location]" value="{{ $outbound->from_location ?? '' }}" required
                       class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">To</label>
                <input type="text" name="legs[0][to_location]" value="{{ $outbound->to_location ?? '' }}" required
                       class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Travel Date</label>
                <input type="date" name="legs[0][travel_date]" value="{{ $outbound->travel_date ?? '' }}" required
                       class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Seat Number</label>
                <input type="text" name="legs[0][seat_number]" value="{{ $outbound->seat_number ?? '' }}"
                       class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Attachment</label>
            <input type="file" name="legs[0][attachment]" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg">
            @if(!empty($outbound->attachment))
                <p class="text-sm mt-1">Current: <a href="{{ asset('storage/'.$outbound->attachment) }}" target="_blank" class="text-blue-600 underline">View</a></p>
            @endif
        </div>

        <!-- Return Leg -->
        <h3 class="text-lg font-semibold mb-4 text-gray-700">Return (optional)</h3>
        @php $returnLeg = $ticketPurchase->legs[1] ?? null; @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">From</label>
                <input type="text" name="legs[1][from_location]" value="{{ $returnLeg->from_location ?? '' }}"
                       class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">To</label>
                <input type="text" name="legs[1][to_location]" value="{{ $returnLeg->to_location ?? '' }}"
                       class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Travel Date</label>
                <input type="date" name="legs[1][travel_date]" value="{{ $returnLeg->travel_date ?? '' }}"
                       class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Seat Number</label>
                <input type="text" name="legs[1][seat_number]" value="{{ $returnLeg->seat_number ?? '' }}"
                       class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Attachment</label>
            <input type="file" name="legs[1][attachment]" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg">
            @if(!empty($returnLeg->attachment))
                <p class="text-sm mt-1">Current: <a href="{{ asset('storage/'.$returnLeg->attachment) }}" target="_blank" class="text-blue-600 underline">View</a></p>
            @endif
        </div>

        <!-- Airline / Operator -->
        <div class="mb-4">
            <label for="airline_or_operator" class="block text-sm font-medium text-gray-700">Airline / Operator</label>
            <input type="text" name="airline_or_operator" id="airline_or_operator" value="{{ old('airline_or_operator', $ticketPurchase->airline_or_operator) }}"
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg">
        </div>

        <!-- Purchase Date -->
        <div class="mb-4">
            <label for="purchase_date" class="block text-sm font-medium text-gray-700">Purchase Date</label>
            <input type="date" name="purchase_date" id="purchase_date" value="{{ old('purchase_date', $ticketPurchase->purchase_date) }}"
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg">
        </div>

        <!-- Ticket No -->
        <div class="mb-4">
            <label for="ticket_no" class="block text-sm font-medium text-gray-700">Ticket No</label>
            <input type="text" name="ticket_no" id="ticket_no" value="{{ $ticketPurchase->ticket_no }}" readonly
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100">
        </div>

        <!-- Amount -->
        <div class="mb-4">
            <label for="amount" class="block text-sm font-medium text-gray-700">Amount</label>
            <input type="number" step="0.01" name="amount" id="amount" value="{{ old('amount', $ticketPurchase->amount) }}"
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg">
        </div>

        <!-- Currency -->
        <div class="mb-4">
            <label for="currency" class="block text-sm font-medium text-gray-700">Currency</label>
            <input type="text" name="currency" id="currency" value="{{ old('currency', $ticketPurchase->currency) }}"
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg">
        </div>

        <!-- Status -->
        <div class="mb-4">
            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
            <select name="status" id="status"
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg">
                <option value="pending" {{ $ticketPurchase->status == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="booked" {{ $ticketPurchase->status == 'booked' ? 'selected' : '' }}>Booked</option>
                <option value="cancelled" {{ $ticketPurchase->status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
        </div>

        <!-- General Attachment -->
        <div class="mb-4">
            <label for="attachment" class="block text-sm font-medium text-gray-700">Attachment</label>
            <input type="file" name="attachment" id="attachment"
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg">
            @if($ticketPurchase->attachment)
                <p class="text-sm mt-1">Current: <a href="{{ asset('storage/'.$ticketPurchase->attachment) }}" target="_blank" class="text-blue-600 underline">View</a></p>
            @endif
        </div>

        <!-- Submit -->
        <div class="flex justify-end">
            <button type="submit"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200 cursor-pointer">
                Update
            </button>
        </div>
    </form>
</div>
@endsection

@section('raw-script')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const ticketType = document.getElementById('ticket_type');
        const sourceWrapper = document.getElementById('source-wrapper');

        function toggleSourceField() {
            if (ticketType.value === 'other') {
                sourceWrapper.style.display = 'block';
            } else {
                sourceWrapper.style.display = 'none';
                document.getElementById('source').value = '';
            }
        }

        toggleSourceField();
        ticketType.addEventListener('change', toggleSourceField);
    });
</script>
@endsection
