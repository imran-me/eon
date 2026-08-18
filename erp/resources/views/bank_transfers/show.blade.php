@extends('layout.app')

@section('meta-information')
    <title>Transfer — {{ $bankTransfer->reference_no }}</title>
@endsection

@section('css')
    @include('bank_transfers.css')
@endsection

@section('main-content')
<div class="px-4 py-6 max-w-screen-2xl mx-auto">

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Transfer Details</h1>
            <nav class="text-sm text-gray-500 mt-1">
                <a href="#" class="hover:text-blue-600">Dashboard</a>
                <span class="mx-1">/</span>
                <a href="{{ route('role.bank_transfers.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="hover:text-blue-600">Bank Transfers</a>
                <span class="mx-1">/</span>
                <span class="text-gray-700">{{ $bankTransfer->reference_no }}</span>
            </nav>
        </div>
        <div class="flex gap-2 no-print">
            {{-- <button onclick="window.print()"
                    class="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-100 text-gray-600 text-sm font-semibold px-4 py-2 rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Print
            </button> --}}
            @if($bankTransfer->status !== 'completed')
                <a href="{{ route('role.bank_transfers.edit', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'bank_transfer' => $bankTransfer]) }}"
                   class="inline-flex items-center gap-2 border border-amber-300 hover:bg-amber-50 text-amber-700 text-sm font-semibold px-4 py-2 rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit
                </a>
                <form action="{{ route('role.bank_transfers.destroy', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'bank_transfer' => $bankTransfer]) }}" method="POST" class="inline"
                      onsubmit="return confirm('Delete this transfer?')">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="inline-flex items-center gap-2 border border-red-300 hover:bg-red-50 text-red-600 text-sm font-semibold px-4 py-2 rounded-lg transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Delete
                    </button>
                </form>
            @endif
            <a href="{{ route('role.bank_transfers.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
               class="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-100 text-gray-600 text-sm font-semibold px-4 py-2 rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back
            </a>
        </div>
    </div>

    @if(session('success'))
        <div id="flash-success" class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-5 text-sm no-print">
            <svg class="w-5 h-5 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-col lg:flex-row gap-6">

        {{-- ── Main ───────────────────────────────────────────────────────────── --}}
        <div class="flex-1 min-w-0 space-y-5">

            {{-- Header Card --}}
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-start justify-between mb-5">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide font-semibold">Reference Number</p>
                        <p class="text-xl font-bold text-gray-800 mt-0.5">{{ $bankTransfer->reference_no }}</p>
                    </div>
                    <span class="bt-badge bt-badge--{{ $bankTransfer->status }} text-sm px-3 py-1">
                        {{ ucfirst($bankTransfer->status) }}
                    </span>
                </div>

                {{-- Flow Visualizer --}}
                <div class="grid grid-cols-11 gap-3 items-stretch my-5">
                    <div class="col-span-5 bg-red-50 border border-red-200 rounded-xl p-4 text-center">
                        <p class="text-xs text-gray-400 mb-1">From</p>
                        <p class="font-bold text-gray-800">{{ $bankTransfer->fromBank->name }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $bankTransfer->fromBank->account_number }}</p>
                        <span class="inline-block mt-2 text-xs bg-gray-200 text-gray-600 rounded-full px-2 py-0.5 capitalize">
                            {{ str_replace('_', ' ', $bankTransfer->fromBank->type) }}
                        </span>
                    </div>
                    <div class="col-span-1 bt-arrow-wrapper">
                        <div class="bt-arrow">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                            <span>{{ number_format($bankTransfer->amount, 2) }}</span>
                        </div>
                    </div>
                    <div class="col-span-5 bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-center">
                        <p class="text-xs text-gray-400 mb-1">To</p>
                        <p class="font-bold text-gray-800">{{ $bankTransfer->toBank->name }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $bankTransfer->toBank->account_number }}</p>
                        <span class="inline-block mt-2 text-xs bg-gray-200 text-gray-600 rounded-full px-2 py-0.5 capitalize">
                            {{ str_replace('_', ' ', $bankTransfer->toBank->type) }}
                        </span>
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-4 grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Payment Date</p>
                        <p class="text-sm font-medium text-gray-700 mt-1">{{ $bankTransfer->payment_date->format('d M Y') }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Method</p>
                        <p class="text-sm font-medium text-gray-700 mt-1 capitalize">{{ str_replace('_', ' ', $bankTransfer->payment_method) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Created By</p>
                        <p class="text-sm font-medium text-gray-700 mt-1">{{ optional($bankTransfer->creator)->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Created At</p>
                        <p class="text-sm font-medium text-gray-700 mt-1">{{ $bankTransfer->created_at->format('d M Y H:i') }}</p>
                    </div>
                    @if($bankTransfer->remarks)
                    <div class="col-span-2 sm:col-span-4">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Remarks</p>
                        <p class="text-sm text-gray-700 mt-1">{{ $bankTransfer->remarks }}</p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Ledger Entries --}}
            @if($bankTransfer->transactions->count())
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 font-semibold text-gray-700 text-sm flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Ledger Entries
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm bt-table">
                        <thead>
                            <tr class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide border-b border-gray-100">
                                <th class="px-4 py-3 text-left font-semibold">Type</th>
                                <th class="px-4 py-3 text-left font-semibold">Account</th>
                                <th class="px-4 py-3 text-left font-semibold">Date</th>
                                <th class="px-4 py-3 text-right font-semibold">Debit</th>
                                <th class="px-4 py-3 text-right font-semibold">Credit</th>
                                <th class="px-4 py-3 text-right font-semibold">Balance</th>
                                <th class="px-4 py-3 text-left font-semibold">Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($bankTransfer->transactions as $txn)
                            <tr>
                                <td class="px-4 py-3">
                                    @if($txn->debit > 0)
                                        <span class="bt-badge bt-badge--cancelled">Debit</span>
                                    @else
                                        <span class="bt-badge bt-badge--completed">Credit</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ optional($txn->account)->name ?? 'Bank #' . $txn->account_id }}
                                </td>
                                <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($txn->payment_date)->format('d M Y') }}
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-red-600">
                                    {{ $txn->debit > 0 ? number_format($txn->debit, 2) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-emerald-600">
                                    {{ $txn->credit > 0 ? number_format($txn->credit, 2) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-gray-800">
                                    {{ number_format($txn->balance, 2) }}
                                </td>
                                <td class="px-4 py-3 text-gray-500 text-xs max-w-[200px] truncate">
                                    {{ $txn->remarks }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

        </div>

        {{-- ── Sidebar ──────────────────────────────────────────────────────── --}}
        <div class="lg:w-72 shrink-0 space-y-5">

            {{-- Amount Card --}}
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                @php
                    $headerBg = match($bankTransfer->status) {
                        'completed' => 'bg-emerald-600',
                        'pending'   => 'bg-amber-500',
                        'cancelled' => 'bg-red-500',
                        default     => 'bg-gray-600',
                    };
                @endphp
                <div class="{{ $headerBg }} text-white px-5 py-3 font-semibold text-sm">Transfer Amount</div>
                <div class="p-5 text-center">
                    <p class="text-4xl font-bold text-gray-800">{{ number_format($bankTransfer->amount, 2) }}</p>
                    @if($bankTransfer->fromBank->currency)
                        <p class="text-sm text-gray-400 mt-1">{{ $bankTransfer->fromBank->currency }}</p>
                    @endif
                    <span class="inline-block mt-3 bt-badge bt-badge--{{ $bankTransfer->status }}">
                        {{ ucfirst($bankTransfer->status) }}
                    </span>
                </div>
            </div>

            {{-- Source Bank --}}
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 font-semibold text-gray-700 text-sm flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span>
                    Source Bank
                </div>
                <div class="p-5 space-y-2.5 text-sm">
                    @foreach([
                        'Name'          => $bankTransfer->fromBank->name,
                        'Branch'        => $bankTransfer->fromBank->branch_name ?? '—',
                        'Account No.'   => $bankTransfer->fromBank->account_number,
                        'Routing No.'   => $bankTransfer->fromBank->routing_number ?? null,
                        'IBAN'          => $bankTransfer->fromBank->iban ?? null,
                        'Swift Code'    => $bankTransfer->fromBank->swift_code ?? null,
                    ] as $label => $value)
                        @if($value)
                        <div class="flex justify-between items-start gap-2">
                            <span class="text-gray-400 shrink-0">{{ $label }}</span>
                            <span class="font-medium text-gray-700 text-right">{{ $value }}</span>
                        </div>
                        @endif
                    @endforeach
                    <div class="flex justify-between items-center border-t border-gray-100 pt-2.5">
                        <span class="text-gray-400">Balance</span>
                        <span class="font-bold text-red-600">{{ number_format($bankTransfer->fromBank->balance, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Destination Bank --}}
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 font-semibold text-gray-700 text-sm flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 inline-block"></span>
                    Destination Bank
                </div>
                <div class="p-5 space-y-2.5 text-sm">
                    @foreach([
                        'Name'          => $bankTransfer->toBank->name,
                        'Branch'        => $bankTransfer->toBank->branch_name ?? '—',
                        'Account No.'   => $bankTransfer->toBank->account_number,
                        'Routing No.'   => $bankTransfer->toBank->routing_number ?? null,
                        'IBAN'          => $bankTransfer->toBank->iban ?? null,
                        'Swift Code'    => $bankTransfer->toBank->swift_code ?? null,
                    ] as $label => $value)
                        @if($value)
                        <div class="flex justify-between items-start gap-2">
                            <span class="text-gray-400 shrink-0">{{ $label }}</span>
                            <span class="font-medium text-gray-700 text-right">{{ $value }}</span>
                        </div>
                        @endif
                    @endforeach
                    <div class="flex justify-between items-center border-t border-gray-100 pt-2.5">
                        <span class="text-gray-400">Balance</span>
                        <span class="font-bold text-emerald-600">{{ number_format($bankTransfer->toBank->balance, 2) }}</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>
@endsection

@section('js')
<script>
    const flash = document.getElementById('flash-success');
    if (flash) setTimeout(() => { flash.style.opacity = '0'; flash.style.transition = 'opacity .5s'; setTimeout(() => flash.remove(), 500); }, 4000);
</script>
@endsection