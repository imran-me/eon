@extends('layout.app')

@section('meta-information')
    <title>Ticket Sales</title>
@endsection

@section('main-content')
<div class="bg-white p-6">
    <div class="flex justify-between mb-4">
        <h2 class="text-xl font-bold">All Ticket Sales</h2>
        <a href="{{ route('role.ticket-sales.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
           class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            Add Ticket Sale
        </a>
    </div>

    {{-- Success Message --}}
    @if(session('success'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">
            {{ session('success') }}
        </div>
    @endif

    <table class="min-w-full table-auto border border-gray-300 text-sm">
        <thead class="bg-gray-100">
            <tr>
                <th class="border px-4 py-2">#</th>
                <th class="border px-4 py-2">Invoice No</th>
                <th class="border px-4 py-2">Agent</th>
                <th class="border px-4 py-2">Sale Date</th>
                <th class="border px-4 py-2">Tickets</th>
                <th class="border px-4 py-2">Total</th>
                <th class="border px-4 py-2">Status</th>
                <th class="border px-4 py-2">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ticketSales as $sale)
                <tr>
                    <td class="border px-4 py-2">{{ $loop->iteration + ($ticketSales->currentPage() - 1) * $ticketSales->perPage() }}</td>
                    <td class="border px-4 py-2">{{ $sale->invoice_no }}</td>
                    <td class="border px-4 py-2">{{ $sale->agent->name ?? 'N/A' }}</td>
                    <td class="border px-4 py-2">{{ $sale->sale_date ? \Carbon\Carbon::parse($sale->sale_date)->format('d M, Y') : 'N/A' }}</td>
                    <td class="border px-4 py-2">
                        <ul class="list-disc list-inside">
                            @foreach($sale->items as $item)
                                <li>{{ $item->ticketPurchase->ticket_no ?? '' }} ({{ $item->ticketPurchase->passportHolder->name ?? 'N/A' }})</li>
                            @endforeach
                        </ul>
                    </td>
                    <td class="border px-4 py-2">{{ $sale->total_amount }} {{ $sale->currency }}</td>
                    <td class="border px-4 py-2 capitalize">{{ $sale->status }}</td>
                    <td class="border px-4 py-2 space-x-2">
                        <a href="{{ route('role.ticket-sales.edit', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'ticket_sale'=> $sale->id]) }}"
                           class="text-blue-600 hover:underline">Edit</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="border px-4 py-2 text-center">No ticket sales found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">
        {{ $ticketSales->links() }}
    </div>
</div>
@endsection
