@extends('layout.app')

@section('meta-information')
    <title>Vendor List</title>
@endsection

@section('main-content')

<div class="min-h-screen bg-gray-50 p-6">

    {{-- Page Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Travels Vendor Management</h1>
        <p class="text-sm text-gray-500 mt-0.5">Epal Group ERP</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">

        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center gap-3 p-4 border-b border-gray-100">
            {{-- Search --}}
            <form method="GET" action="{{ route('role.vendor.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                  class="flex items-center gap-3 flex-1 flex-wrap">
                <div class="relative min-w-[260px]">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                        </svg>
                    </span>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Search vendor, ID, email..."
                           class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-gray-50">
                </div>
                <button type="submit"
                        class="flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                    </svg>
                    Filter
                </button>
            </form>

            {{-- Right buttons --}}
            <div class="flex items-center gap-2 ml-auto">
                <a href="{{ route('role.vendor.export.excel', array_merge(['role' => Str::slug(Auth::user()->getRoleNames()->first())], request()->only('search'))) }}"
                   class="flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                    </svg>
                    Export Excel
                </a>
                <a href="{{ route('role.vendor.export.pdf', array_merge(['role' => Str::slug(Auth::user()->getRoleNames()->first())], request()->only('search'))) }}"
                   class="flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    Export PDF
                </a>
                <a href="{{ route('role.vendor.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                   class="flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Vendor
                </a>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        <th class="px-5 py-3 text-left">Vendor ID</th>
                        <th class="px-5 py-3 text-left">Vendor Name</th>
                        <th class="px-5 py-3 text-left">Contact</th>
                        <th class="px-5 py-3 text-left">Address</th>
                        <th class="px-5 py-3 text-left">Balance / Payable</th>
                        <th class="px-5 py-3 text-left">Last Transaction</th>
                        <th class="px-5 py-3 text-left">Status</th>
                        <th class="px-5 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($vendors as $vendor)
                        @php
                            $iteration = $loop->iteration + ($vendors->currentPage() - 1) * $vendors->perPage();
                            $vendorId  = 'EP-VN-' . str_pad($vendor->id, 3, '0', STR_PAD_LEFT);
                        @endphp
                        <tr class="hover:bg-blue-50/30 transition-colors group">

                            {{-- Vendor ID --}}
                            <td class="px-5 py-3.5">
                                <span class="font-mono text-xs font-semibold text-blue-600 bg-blue-50 px-2 py-1 rounded">
                                    {{ $vendorId }}
                                </span>
                            </td>

                            {{-- Vendor Name --}}
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                                        {{ strtoupper(substr($vendor->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800 leading-tight">{{ $vendor->name }}</p>
                                        <p class="text-xs text-gray-400 mt-0.5">{{ $vendor->email }}</p>
                                    </div>
                                </div>
                            </td>

                            {{-- Contact --}}
                            <td class="px-5 py-3.5">
                                <div>
                                    @if($vendor->phone)
                                        <p class="text-gray-700 font-medium">{{ $vendor->phone }}</p>
                                    @else
                                        <span class="text-gray-300 text-xs italic">—</span>
                                    @endif
                                    @if($vendor->contact_person)
                                        <p class="text-xs text-gray-400 mt-0.5">{{ $vendor->contact_person }}</p>
                                    @endif
                                </div>
                            </td>

                            {{-- Address --}}
                            <td class="px-5 py-3.5">
                                @if($vendor->address)
                                    <span class="text-gray-600 text-xs">{{ Str::limit($vendor->address, 30) }}</span>
                                @else
                                    <span class="text-gray-300 text-xs italic">—</span>
                                @endif
                            </td>

                            {{-- Balance / Payable --}}
                            @php $ledger = $balances[$vendor->id] ?? null; @endphp
                            <td class="px-5 py-3.5">
                                <a href="{{ route('role.party-statement.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'party_id' => $vendor->id]) }}"
                                   class="inline-flex items-center gap-1 text-xs font-bold hover:underline
                                       {{ !$ledger || $ledger->balance == 0 ? 'text-gray-400' : ($ledger->balance > 0 ? 'text-red-600' : 'text-emerald-600') }}"
                                   title="Open party statement">
                                    <i class="fas fa-taka-sign text-[10px]"></i>
                                    {{ $ledger ? number_format(abs($ledger->balance)) : '0' }}
                                    @if($ledger && $ledger->balance != 0)
                                        <span class="text-[10px] font-semibold">{{ $ledger->balance > 0 ? 'Dr' : 'Cr' }}</span>
                                    @endif
                                </a>
                            </td>

                            {{-- Last Transaction --}}
                            <td class="px-5 py-3.5">
                                <span class="text-gray-600 text-xs">
                                    {{ $ledger ? \Carbon\Carbon::parse($ledger->payment_date)->format('Y-m-d') : '—' }}
                                </span>
                            </td>

                            {{-- Status --}}
                            <td class="px-5 py-3.5">
                                @if($vendor->status === 'active')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span>
                                        Active
                                    </span>
                                @elseif($vendor->status === 'on_hold')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-yellow-500 inline-block"></span>
                                        On Hold
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-600">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 inline-block"></span>
                                        Inactive
                                    </span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-center gap-1">
                                    {{-- Eye / View --}}
                                    <a href="{{ route('role.vendor.edit', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'vendor' => $vendor->id]) }}"
                                       class="w-8 h-8 flex items-center justify-center rounded-lg text-blue-500 bg-blue-50 hover:bg-blue-100 transition"
                                       title="View / Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>

                                    {{-- Edit (Pencil) --}}
                                    <a href="{{ route('role.vendor.edit', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'vendor' => $vendor->id]) }}"
                                       class="w-8 h-8 flex items-center justify-center rounded-lg text-indigo-500 bg-indigo-50 hover:bg-indigo-100 transition"
                                       title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </a>

                                    {{-- Toggle Status --}}
                                    <button type="button"
                                            class="w-8 h-8 flex items-center justify-center rounded-lg transition
                                                {{ $vendor->status === 'active'
                                                    ? 'text-amber-500 bg-amber-50 hover:bg-amber-100'
                                                    : 'text-green-600 bg-green-50 hover:bg-green-100' }}"
                                            data-vendor-id="{{ $vendor->id }}"
                                            data-vendor-status="{{ $vendor->status }}"
                                            onclick="openConfirmModal(this)"
                                            title="{{ $vendor->status === 'active' ? 'Deactivate' : 'Activate' }}">
                                        @if($vendor->status === 'active')
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                            </svg>
                                        @else
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        @endif
                                    </button>

                                    {{-- Delete --}}
                                    <button type="button"
                                            class="w-8 h-8 flex items-center justify-center rounded-lg text-red-500 bg-red-50 hover:bg-red-100 transition"
                                            data-delete-id="{{ $vendor->id }}"
                                            data-delete-name="{{ e($vendor->name) }}"
                                            onclick="openDeleteModal(this)"
                                            title="Delete Vendor">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-16 text-center">
                                <div class="flex flex-col items-center gap-2 text-gray-400">
                                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <p class="text-sm font-medium">No vendors found</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="flex items-center justify-between px-5 py-4 border-t border-gray-100">
            <p class="text-sm text-gray-500">
                Showing
                <span class="font-semibold text-gray-700">{{ $vendors->firstItem() ?? 0 }}</span>–<span class="font-semibold text-gray-700">{{ $vendors->lastItem() ?? 0 }}</span>
                of
                <span class="font-semibold text-gray-700">{{ $vendors->total() }}</span>
                vendors
            </p>
            <div class="pagination-custom">
                {{ $vendors->withQueryString()->links() }}
            </div>
        </div>
    </div>
</div>

{{-- Delete Confirm Modal --}}
<div id="deleteModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl p-6 w-96 max-w-full shadow-2xl">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </div>
            <h3 class="text-base font-semibold text-gray-800">Delete Vendor</h3>
        </div>
        <p id="deleteMessage" class="text-sm text-gray-600 mb-6">Are you sure you want to delete this vendor? This action cannot be undone.</p>
        <div class="flex justify-end gap-3">
            <button onclick="closeDeleteModal()"
                    class="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                Cancel
            </button>
            <form id="deleteForm" method="POST" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 transition cursor-pointer shadow-sm">
                    Yes, Delete
                </button>
            </form>
        </div>
    </div>
</div>

{{-- Confirm Status Modal --}}
<div id="confirmModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl p-6 w-96 max-w-full shadow-2xl animate-in">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
            </div>
            <h3 class="text-base font-semibold text-gray-800">Confirm Status Change</h3>
        </div>
        <p id="confirmMessage" class="text-sm text-gray-600 mb-6 pl-13">Are you sure you want to change the vendor status?</p>
        <div class="flex justify-end gap-3">
            <button onclick="closeConfirmModal()"
                    class="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                Cancel
            </button>
            <form id="confirmForm" method="POST" class="inline">
                @csrf
                @method('PUT')
                <button type="submit"
                        class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition cursor-pointer shadow-sm">
                    Yes, Confirm
                </button>
            </form>
        </div>
    </div>
</div>

@endsection

@section('raw-script')
<script>
    function openConfirmModal(button) {
        const vendorId     = button.getAttribute('data-vendor-id');
        const currentStatus = button.getAttribute('data-vendor-status');
        const newStatus    = currentStatus === 'active' ? 'inactive' : 'active';

        document.getElementById('confirmMessage').textContent =
            `Are you sure you want to change the status from "${currentStatus}" to "${newStatus}"?`;

        const form = document.getElementById('confirmForm');
        form.action = `/{{ Str::slug(Auth::user()->getRoleNames()->first()) }}/vendor/${vendorId}/toggle-status`;

        document.getElementById('confirmModal').classList.remove('hidden');
        document.getElementById('confirmModal').classList.add('flex');
    }

    function closeConfirmModal() {
        document.getElementById('confirmModal').classList.add('hidden');
        document.getElementById('confirmModal').classList.remove('flex');
    }

    function openDeleteModal(btn) {
        const vendorId   = btn.getAttribute('data-delete-id');
        const vendorName = btn.getAttribute('data-delete-name');
        document.getElementById('deleteMessage').textContent =
            `Are you sure you want to delete "${vendorName}"? This action cannot be undone.`;
        document.getElementById('deleteForm').action =
            `/{{ Str::slug(Auth::user()->getRoleNames()->first()) }}/vendor/${vendorId}`;
        document.getElementById('deleteModal').classList.remove('hidden');
        document.getElementById('deleteModal').classList.add('flex');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
        document.getElementById('deleteModal').classList.remove('flex');
    }
</script>
@endsection
