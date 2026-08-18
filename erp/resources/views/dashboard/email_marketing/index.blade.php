@extends('layout.app')

@section('meta-information')
    <title>Vendor List</title>
@endsection

@section('main-content')

<div class="bg-white p-6 rounded shadow">
    <h2 class="text-2xl font-semibold mb-4">Email marketing</h2>

    {{-- Top Bar with Create Button and Filter Form --}}
    <div class="flex justify-between items-center mb-4">

        <form method="GET" action="{{ route('role.email-marketing.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="flex space-x-4">
            <input type="text" name="search" placeholder="Search by name, email, phone" value="{{ request('search') }}"
                   class="border border-gray-300 px-4 py-2 rounded-lg w-72 focus:outline-none focus:ring-1 focus:ring-blue-500">
            <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition duration-200">Filter</button>
        </form>
        <a href="{{ route('role.email-marketing.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
           class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition duration-200">
            + Create Campaign
        </a>

    </div>

    {{-- Vendors Table --}}
    <div class="overflow-x-auto">
        <table class="min-w-full text-left border border-gray-200">
            <thead class="bg-gray-100">
                <tr>
                    <th class="py-2 px-4 border-b">#</th>
                    <th class="py-2 px-4 border-b">Subject</th>
                    <th class="py-2 px-4 border-b">Message</th>
                    <th class="py-2 px-4 border-b">Schedule At</th>
                    <th class="py-2 px-4 border-b">Status</th>
                    {{-- <th class="py-2 px-4 border-b">Action</th> --}}
                </tr>
            </thead>
            <tbody>
                @forelse($campaigns as $vendor)
                    <tr class="hover:bg-gray-50">
                        <td class="py-2 px-4 border-b">{{ $loop->iteration + ($campaigns->currentPage() - 1) * $campaigns->perPage() }}</td>
                        <td class="py-2 px-4 border-b">{{ $vendor->subject }}</td>
                        <td class="py-2 px-4 border-b">{{ $vendor->body }}</td>
                        <td class="py-2 px-4 border-b">{{ $vendor->schedule_at }}</td>
                        <td class="py-2 px-4 border-b">
                            @if($vendor->status === 'active')
                                <button type="button" class="px-3 py-1 text-xs rounded bg-green-500 text-white cursor-pointer hover:opacity-80 transition">Active</button>
                            @else
                                <button type="button" class="px-3 py-1 text-xs rounded bg-red-500 text-white cursor-pointer hover:opacity-80 transition">Inactive</button>
                            @endif
                        </td>
                        {{-- <td class="py-2 px-4 border-b">
                            <a href="{{ route('role.vendor.edit', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'vendor'=> $vendor->id]) }}" class="text-blue-600 hover:underline">Edit</a>
                        </td> --}}
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-4 px-4 text-center text-gray-500">No Message found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $campaigns->withQueryString()->links() }}
    </div>
</div>



@endsection

@section('raw-script')
<script>
    function openConfirmModal(button) {
        const vendorId = button.getAttribute('data-vendor-id');
        const currentStatus = button.getAttribute('data-vendor-status');
        const newStatus = currentStatus === 'active' ? 'inactive' : 'active';

        // Update modal message dynamically
        document.getElementById('confirmMessage').textContent =
            `Are you sure you want to change status from "${currentStatus}" to "${newStatus}"?`;

        // Update form action dynamically
        const form = document.getElementById('confirmForm');
        form.action = `/{{ Str::slug(Auth::user()->getRoleNames()->first()) }}/vendor/${vendorId}/toggle-status`;

        // Show modal
        document.getElementById('confirmModal').classList.remove('hidden');
        document.getElementById('confirmModal').classList.add('flex');
    }

    function closeConfirmModal() {
        document.getElementById('confirmModal').classList.add('hidden');
        document.getElementById('confirmModal').classList.remove('flex');
    }
</script>
@endsection
