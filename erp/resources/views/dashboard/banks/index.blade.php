@extends('layout.app')

@section('meta-information')
    <title>Bank List</title>
    <script src="//unpkg.com/alpinejs" defer></script>
@endsection

@section('main-content')
<div class="bg-white p-6" x-data="{ showModal: false, selectedBank: {} }">
    <div class="flex justify-between mb-4">
        <h2 class="text-xl font-bold">All Banks</h2>
        <a href="{{ route('role.banks.create',['role'=>Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            + Add New Bank
        </a>
    </div>

    {{-- Success Message --}}
    @if(session('success'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">
            {{ session('success') }}
        </div>
    @elseif(session('error'))
        <div class="mb-4 p-3 bg-red-100 text-red-700 rounded">
            {{ session('error') }}
        </div>
    @endif

    {{-- Table --}}
    <table class="min-w-full table-auto border border-gray-300 text-sm">
        <thead class="bg-gray-100">
            <tr>
                <th class="border px-4 py-2">#</th>
                <th class="border px-4 py-2">Name</th>
                <th class="border px-4 py-2">Account Name</th>
                <th class="border px-4 py-2">Account Number</th>
                <th class="border px-4 py-2">Type</th>
                <th class="border px-4 py-2">Currency</th>
                <th class="border px-4 py-2">Status</th>
                <th class="border px-4 py-2">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($banks as $bank)
                <tr>
                    <td class="border px-4 py-2">
                        {{ $loop->iteration + ($banks->currentPage() - 1) * $banks->perPage() }}
                    </td>
                    <td class="border px-4 py-2">{{ $bank->name }}</td>
                    <td class="border px-4 py-2">{{ $bank->account_name }}</td>
                    <td class="border px-4 py-2">{{ $bank->account_number }}</td>
                    <td class="border px-4 py-2 capitalize">
                        {{ str_replace('_', ' ', $bank->type) }}
                    </td>
                    <td class="border px-4 py-2">{{ $bank->currency }}</td>
                    <td class="border px-4 py-2">
                        <span class="inline-block px-2 py-1 rounded text-xs font-semibold
                            {{ $bank->status ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' }}">
                            {{ $bank->status ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="border px-4 py-2 space-x-2">
                        <a href="{{ route('role.banks.edit', ['role'=>Str::slug(Auth::user()->getRoleNames()->first()), 'bank'=>$bank->id]) }}" class="text-blue-600 hover:underline">Edit</a>
                         <button
                            @click="selectedBank = {{ $bank->toJson() }}; showModal = true"
                            class="text-indigo-600 hover:underline cursor-pointer">
                            View
                        </button>
                        {{-- <form action="{{ route('role.banks.destroy', $bank->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Are you sure?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Delete</button>
                        </form> --}}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="border px-4 py-2 text-center">No banks found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $banks->links() }}
    </div>



    <!-- Modal -->
    <div x-show="showModal" x-cloak class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white w-full max-w-2xl rounded-lg shadow-lg p-6 relative" @click.outside="showModal = false">
            <button class="absolute top-2 right-2 text-gray-500 hover:text-gray-700" @click="showModal = false">&times;</button>

            <h3 class="text-lg font-bold mb-4">Bank Details</h3>

            <div class="grid grid-cols-2 gap-4 text-sm">
                <div><strong>Name:</strong> <span x-text="selectedBank.name"></span></div>
                <div><strong>Branch:</strong> <span x-text="selectedBank.branch_name"></span></div>
                <div><strong>Account Name:</strong> <span x-text="selectedBank.account_name"></span></div>
                <div><strong>Account Type:</strong> <span x-text="selectedBank.account_type"></span></div>
                <div><strong>Account Number:</strong> <span x-text="selectedBank.account_number"></span></div>
                <div><strong>Type:</strong> <span x-text="selectedBank.type"></span></div>
                <div><strong>Bank Type:</strong> <span x-text="selectedBank.bank_type"></span></div>
                <div><strong>Routing Number:</strong> <span x-text="selectedBank.routing_number || '-'"></span></div>
                <div><strong>SWIFT:</strong> <span x-text="selectedBank.swift_code || '-'"></span></div>
                <div><strong>IBAN:</strong> <span x-text="selectedBank.iban || '-'"></span></div>
                <div><strong>Currency:</strong> <span x-text="selectedBank.currency"></span></div>
                <div><strong>Address:</strong> <span x-text="selectedBank.address || '-'"></span></div>
                <div><strong>Balance:</strong> <span x-text="selectedBank.balance"></span></div>
                <div><strong>Status:</strong>
                    <span x-text="selectedBank.status ? 'Active' : 'Inactive'"></span>
                </div>
            </div>

            <div class="mt-6 text-right">
                <button class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400" @click="showModal = false">Close</button>
            </div>
        </div>
    </div>

</div>
@endsection
