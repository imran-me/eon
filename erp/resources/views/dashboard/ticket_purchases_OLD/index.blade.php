@extends('layout.app')

@section('meta-information')
    <title>Ticket Purchases</title>
@endsection

@section('main-content')
<div class="bg-white p-6">
    <div class="flex justify-between mb-4">
        <h2 class="text-xl font-bold">All Ticket Purchases</h2>
        <a href="{{ route('role.ticket-purchase.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
           class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            Add Ticket Purchase
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
                <th class="border px-4 py-2">Passport Holder</th>
                <th class="border px-4 py-2">Vendor</th>
                <th class="border px-4 py-2">Portal</th>
                <th class="border px-4 py-2">Ticket No</th>
                <th class="border px-4 py-2">Travel Date</th>
                <th class="border px-4 py-2">Amount</th>
                <th class="border px-4 py-2">Status</th>
                <th class="border px-4 py-2">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ticketPurchases as $purchase)
                <tr>
                    <td class="border px-4 py-2">{{ $loop->iteration + ($ticketPurchases->currentPage() - 1) * $ticketPurchases->perPage() }}</td>
                    <td class="border px-4 py-2">{{ $purchase->passportHolder->name ?? 'N/A' }}</td>
                    <td class="border px-4 py-2">{{ $purchase->vendor->name ?? 'N/A' }}</td>
                    <td class="border px-4 py-2">{{ $purchase->portal->name ?? 'N/A' }}</td>
                    <td class="border px-4 py-2">{{ $purchase->ticket_no }}</td>
                    <td class="border px-4 py-2">{{ $purchase->travel_date ? \Carbon\Carbon::parse($purchase->travel_date)->format('d M, Y') : 'N/A' }}</td>
                    <td class="border px-4 py-2">{{ $purchase->amount }} {{ $purchase->currency }}</td>
                    <td class="border px-4 py-2 capitalize">{{ $purchase->status }}</td>
                    <td class="border px-4 py-2 space-x-2">
                        <a href="{{ route('role.ticket-purchase.edit', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'ticket_purchase'=> $purchase->id]) }}"
                           class="text-blue-600 hover:underline">Edit</a>

                        {{-- <form action="{{ route('role.ticket-purchase.destroy', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'ticket_purchase'=> $purchase->id]) }}"
                              method="POST" class="inline-block"
                              onsubmit="return confirm('Are you sure you want to delete this ticket purchase?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Delete</button>
                        </form> --}}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="border px-4 py-2 text-center">No ticket purchases found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">
        {{ $ticketPurchases->links() }}
    </div>
</div>
@endsection
