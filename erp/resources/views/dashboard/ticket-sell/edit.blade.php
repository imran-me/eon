@extends('layout.app')

@section('meta-information')
    <title>Edit Ticket Sale</title>
@endsection

@section('main-content')
<div class="bg-white p-8 mt-1">
    <h2 class="text-2xl font-semibold mb-6 text-gray-700">Edit Ticket Sale</h2>

    {{-- Errors --}}
    @if ($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-700 rounded">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('role.ticket-sales.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'ticket_sale'=>$ticket_sale->id]) }}"
          method="POST">
        @csrf
        @method('PUT')

        <!-- Invoice -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Invoice No</label>
            <input type="text" value="{{ $ticket_sale->invoice_no }}" readonly
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm bg-gray-100">
        </div>

        <!-- Agent -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Agent</label>
            <select name="agent_id" required
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm">
                @foreach($agents as $agent)
                    <option value="{{ $agent->id }}" {{ $ticket_sale->agent_id == $agent->id ? 'selected' : '' }}>
                        {{ $agent->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <!-- Sale Date -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Sale Date</label>
            <input type="date" name="sale_date" value="{{ $ticket_sale->sale_date }}"
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm">
        </div>

        <!-- Tickets list -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Select Tickets</label>
            <div class="border rounded-lg p-4 max-h-72 overflow-y-auto space-y-2" id="tickets-box">
                @forelse($tickets as $t)
                    @php
                        $existing = $ticket_sale->items->firstWhere('ticket_purchase_id', $t->id);
                    @endphp
                    <div class="flex items-center gap-3">
                        <input type="checkbox" class="ticket-check"
                               name="tickets[{{ $t->id }}][id]" value="{{ $t->id }}"
                               {{ $existing ? 'checked' : '' }}>
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
                               value="{{ $existing->price ?? '' }}"
                               class="price-input w-32 px-2 py-1 border border-gray-300 rounded">
                    </div>
                @empty
                    <div class="text-sm text-gray-500">No available tickets.</div>
                @endforelse
            </div>
        </div>

        <!-- Currency -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Currency</label>
            <input type="text" name="currency" value="{{ $ticket_sale->currency }}"
                   class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm">
        </div>

        <!-- Status -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700">Status</label>
            <select name="status" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm">
                <option value="pending" {{ $ticket_sale->status=='pending' ? 'selected' : '' }}>Pending</option>
                <option value="paid" {{ $ticket_sale->status=='paid' ? 'selected' : '' }}>Paid</option>
                <option value="cancelled" {{ $ticket_sale->status=='cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
        </div>

        <!-- Total -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div class="text-gray-600">Invoice Total</div>
                <div class="text-xl font-semibold">
                    <span id="total-amount">{{ $ticket_sale->total_amount }}</span>
                    <span id="total-currency">{{ $ticket_sale->currency }}</span>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200 cursor-pointer">
                Update Sale
            </button>
        </div>
    </form>
</div>
@endsection

@section('raw-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const recalc = () => {
        let total = 0;
        document.querySelectorAll('#tickets-box > div').forEach(row => {
            const checked = row.querySelector('.ticket-check')?.checked;
            const price = parseFloat(row.querySelector('.price-input')?.value || '0');
            if (checked && !isNaN(price)) total += price;
        });
        document.getElementById('total-amount').textContent = total.toFixed(2);
        document.getElementById('total-currency').textContent = document.querySelector('input[name="currency"]').value;
    };

    document.querySelectorAll('.price-input').forEach(i => i.addEventListener('input', recalc));
    document.querySelectorAll('.ticket-check').forEach(c => c.addEventListener('change', recalc));
    document.querySelector('input[name="currency"]').addEventListener('input', recalc);
});
</script>
@endsection
