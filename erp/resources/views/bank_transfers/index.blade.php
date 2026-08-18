@extends('layout.app')

@section('meta-information')
    <title>Bank Transfers</title>
@endsection

@section('css')
    @include('bank_transfers.css')
@endsection

@section('main-content')
{{-- The tab row sits OUTSIDE the padded wrapper below, exactly as it does on
     banks/dashboard, banks/index and banks/statement: a direct child of
     <main class="flex-1 p-3">. Nested inside `px-4 py-6` it started 24px lower
     and 16px narrower than on the other three pages, so the bar jumped every
     time you clicked between the tabs. Keep it here if this page's wrapper
     changes. --}}
@include('layout.bank-tabs')

<div class="px-4 pb-6 max-w-screen-2xl mx-auto">

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Bank Transfers</h1>
            <nav class="text-sm text-gray-500 mt-1">
                <a href="#" class="hover:text-blue-600">Dashboard</a>
                <span class="mx-1">/</span>
                <span class="text-gray-700">Bank Transfers</span>
            </nav>
        </div>
        <a href="{{ route('role.bank_transfers.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
           class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Transfer
        </a>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div id="flash-success" class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-5 text-sm">
            <svg class="w-5 h-5 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div id="flash-error" class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-5 text-sm">
            <svg class="w-5 h-5 shrink-0 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-2.707-9.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm p-4 bt-summary-card bt-summary-card--total">
            <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide">Total Transfers</p>
            <p class="text-3xl font-bold text-gray-700 mt-1">{{ $summary['total'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 bt-summary-card bt-summary-card--completed">
            <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide">Completed</p>
            <p class="text-3xl font-bold text-emerald-600 mt-1">{{ $summary['completed'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 bt-summary-card bt-summary-card--pending">
            <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide">Pending</p>
            <p class="text-3xl font-bold text-amber-500 mt-1">{{ $summary['pending'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 bt-summary-card bt-summary-card--amount">
            <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide">Transferred (Completed)</p>
            <p class="text-2xl font-bold text-blue-600 mt-1">{{ number_format($summary['amount'], 2) }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm p-4 mb-5">
        <form method="GET" action="{{ route('role.bank_transfers.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="flex flex-wrap gap-3 items-end">
            <div class="flex flex-col gap-1 min-w-[140px]">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">From Bank</label>
                <select name="from_bank" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-300 focus:outline-none bg-gray-50">
                    <option value="">All</option>
                    @foreach($banks as $bank)
                        <option value="{{ $bank->id }}" @selected(request('from_bank') == $bank->id)>{{ $bank->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-1 min-w-[140px]">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">To Bank</label>
                <select name="to_bank" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-300 focus:outline-none bg-gray-50">
                    <option value="">All</option>
                    @foreach($banks as $bank)
                        <option value="{{ $bank->id }}" @selected(request('to_bank') == $bank->id)>{{ $bank->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-1 min-w-[120px]">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</label>
                <select name="status" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-300 focus:outline-none bg-gray-50">
                    <option value="">All</option>
                    <option value="pending"   @selected(request('status') === 'pending')>Pending</option>
                    <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                    <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Date From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-300 focus:outline-none bg-gray-50">
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Date To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-300 focus:outline-none bg-gray-50">
            </div>
            <div class="flex flex-col gap-1 min-w-[160px]">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Reference No.</label>
                <input type="text" name="search" placeholder="TRF-..." value="{{ request('search') }}"
                       class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-300 focus:outline-none bg-gray-50">
            </div>
            <div class="flex gap-2">
                <button type="submit"
                        class="inline-flex items-center gap-1 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    Search
                </button>
                <a href="{{ route('role.bank_transfers.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                   class="inline-flex items-center gap-1 border border-gray-300 hover:bg-gray-100 text-gray-600 text-sm font-semibold px-4 py-2 rounded-lg transition">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm bt-table">
                <thead>
                    <tr class="bg-gray-800 text-white text-xs uppercase tracking-wide">
                        <th class="px-4 py-3 text-left font-semibold">#</th>
                        <th class="px-4 py-3 text-left font-semibold">Reference No.</th>
                        <th class="px-4 py-3 text-left font-semibold">Date</th>
                        <th class="px-4 py-3 text-left font-semibold">From Bank</th>
                        <th class="px-4 py-3 text-left font-semibold">To Bank</th>
                        <th class="px-4 py-3 text-right font-semibold">Amount</th>
                        <th class="px-4 py-3 text-left font-semibold">Method</th>
                        <th class="px-4 py-3 text-left font-semibold">Status</th>
                        <th class="px-4 py-3 text-left font-semibold">Created By</th>
                        <th class="px-4 py-3 text-center font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($transfers as $transfer)
                    <tr class="transition">
                        <td class="px-4 py-3 text-gray-400 text-xs">{{ $transfers->firstItem() + $loop->index }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('role.bank_transfers.show', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'bank_transfer' => $transfer]) }}"
                               class="font-semibold text-blue-600 hover:text-blue-800 hover:underline">
                                {{ $transfer->reference_no }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $transfer->payment_date->format('d M Y') }}</td>
                        <td class="px-4 py-3">
                            <span class="font-medium text-gray-700">{{ $transfer->fromBank?->name }}</span>
                            <div class="text-xs text-gray-400">{{ $transfer->fromBank?->account_number }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="font-medium text-gray-700">{{ $transfer->toBank?->name }}</span>
                            <div class="text-xs text-gray-400">{{ $transfer->toBank?->account_number }}</div>
                        </td>
                        <td class="px-4 py-3 text-right font-bold text-gray-800">{{ number_format($transfer->amount, 2) }}</td>
                        <td class="px-4 py-3 text-gray-600 capitalize">{{ str_replace('_', ' ', $transfer->payment_method) }}</td>
                        <td class="px-4 py-3">
                            <span class="bt-badge bt-badge--{{ $transfer->status }}">{{ ucfirst($transfer->status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ optional($transfer->creator)->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1">
                                <a href="{{ route('role.bank_transfers.show', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'bank_transfer' => $transfer]) }}"
                                   class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 transition" title="View">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                @if($transfer->status !== 'completed')
                                    <a href="{{ route('role.bank_transfers.edit', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'bank_transfer' => $transfer->id]) }}"
                                       class="p-1.5 rounded-lg text-amber-600 hover:bg-amber-50 transition" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <form action="{{ route('role.bank_transfers.destroy', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'bank_transfer' => $transfer->id]) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Delete this transfer?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="p-1.5 rounded-lg text-red-600 hover:bg-red-50 transition" title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-16 text-center text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                            </svg>
                            <p class="font-medium">No bank transfers found.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($transfers->hasPages())
        <div class="px-4 py-3 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-2 text-sm text-gray-500">
            <span>Showing {{ $transfers->firstItem() }}–{{ $transfers->lastItem() }} of {{ $transfers->total() }} records</span>
            {{ $transfers->links() }}
        </div>
        @endif
    </div>

</div>
@endsection

@section('js')
<script>
    ['flash-success','flash-error'].forEach(id => {
        const el = document.getElementById(id);
        if (el) setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .5s'; setTimeout(() => el.remove(), 500); }, 4000);
    });
</script>
@endsection