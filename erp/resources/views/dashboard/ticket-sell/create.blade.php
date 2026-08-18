@extends('layout.app')

@section('meta-information')
    <title>Create Ticket Sale</title>
@endsection

@section('main-content')
<div class="bg-white p-8 mt-1">
    <h2 class="text-2xl font-semibold mb-6 text-gray-700">Create Ticket Sale</h2>

    {{-- Success --}}
    @if (session('success'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">{{ session('success') }}</div>
    @endif

    {{-- Errors --}}
    @if ($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-700 rounded">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('role.ticket-sales.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST">
        @csrf

        <!-- Invoice -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Invoice No</label>
            <input type="text" name="invoice_no" value="{{ $invoiceNo }}" readonly
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm">
        </div>

        <!-- Agent -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Agent</label>
            <select name="agent_id" required
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm">
                <option value="">Select Agent</option>
                @foreach($agents as $agent)
                    <option value="{{ $agent->id }}" {{ old('agent_id') == $agent->id ? 'selected' : '' }}>
                        {{ $agent->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <!-- Sale Date -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Sale Date</label>
            <input type="date" name="sale_date" value="{{ old('sale_date') }}"
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm">
        </div>

        <!-- Tickets list -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Select Tickets (booked & not sold)</label>
            <div class="border rounded-lg p-4 max-h-72 overflow-y-auto space-y-2" id="tickets-box">
                @forelse($tickets as $t)
                    <div class="flex items-center gap-3">
                        <input type="checkbox" class="ticket-check"
                               name="tickets[{{ $t->id }}][id]" value="{{ $t->id }}">
                        <div class="flex-1 text-sm text-gray-700">
                            <div class="font-medium">
                                {{ $t->ticket_no }}
                                <span class="text-gray-500">| {{ $t->passportHolder->name ?? 'N/A' }}</span>
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ ucfirst($t->ticket_type) }} • {{ $t->trip_type }} • {{ $t->amount }} {{ $t->currency }}
                            </div>
                        </div>
                        <input type="number" step="0.01" placeholder="Sale price"
                               name="tickets[{{ $t->id }}][price]"
                               class="price-input w-32 px-2 py-1 border border-gray-300 rounded">
                    </div>
                @empty
                    <div class="text-sm text-gray-500">No available tickets to sell.</div>
                @endforelse
            </div>
        </div>

        <!-- Currency -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Currency</label>
            <input type="text" name="currency" value="{{ old('currency','BDT') }}"
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm">
        </div>

        <!-- Status -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700">Status</label>
            <select name="status" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm">
                <option value="pending" {{ old('status')=='pending' ? 'selected' : '' }}>Pending</option>
                <option value="paid" {{ old('status')=='paid' ? 'selected' : '' }}>Paid</option>
                <option value="cancelled" {{ old('status')=='cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
        </div>

        <!-- Total (auto) -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div class="text-gray-600">Invoice Total</div>
                <div class="text-xl font-semibold"><span id="total-amount">0.00</span> <span id="total-currency">BDT</span></div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200 cursor-pointer">
                Save Sale
            </button>
        </div>
    </form>
</div>
@endsection

@section('raw-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const priceInputs = document.querySelectorAll('.price-input');
    const currencyInput = document.querySelector('input[name="currency"]');
    const totalEl = document.getElementById('total-amount');
    const totalCur = document.getElementById('total-currency');

    function recalc() {
        let total = 0;
        document.querySelectorAll('#tickets-box > div').forEach(row => {
            const checked = row.querySelector('.ticket-check')?.checked;
            const price = parseFloat(row.querySelector('.price-input')?.value || '0');
            if (checked && !isNaN(price)) total += price;
        });
        totalEl.textContent = total.toFixed(2);
        totalCur.textContent = (currencyInput.value || 'BDT');
    }

    priceInputs.forEach(i => i.addEventListener('input', recalc));
    document.querySelectorAll('.ticket-check').forEach(c => c.addEventListener('change', recalc));
    currencyInput.addEventListener('input', recalc);
});
</script>
@endsection
