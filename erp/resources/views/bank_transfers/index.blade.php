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
    <div class="bt-page-header flex items-center justify-between mb-5">
        <div>
            <h1><i class="fas fa-exchange-alt"></i> Bank Transfers</h1>
            <p>Review and move funds between company accounts.</p>
        </div>
        <button type="button" id="openBankTransferModal"
           class="inline-flex items-center gap-2 bg-white text-blue-700 text-sm font-bold px-4 py-2.5 rounded-lg transition shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Transfer
        </button>
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
    <div class="bg-white rounded-xl shadow-sm mb-4 bt-filter-panel">
        <form method="GET" id="bankTransferFilter" action="{{ route('role.bank_transfers.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="bt-filter-toolbar">
            <div class="bt-filter-search">
                <i class="fas fa-search"></i>
                <input type="text" name="search" aria-label="Search reference number" placeholder="Search transfer reference..." value="{{ request('search') }}">
            </div>
            <select name="from_bank" aria-label="From bank" title="From bank">
                    <option value="">From: All banks</option>
                    @include('bank_transfers.partials.bank-options', ['banks' => $banks, 'selected' => request('from_bank')])
            </select>
            <select name="to_bank" aria-label="To bank" title="To bank">
                    <option value="">To: All banks</option>
                    @include('bank_transfers.partials.bank-options', ['banks' => $banks, 'selected' => request('to_bank')])
            </select>
            <select name="status" aria-label="Transfer status" title="Status">
                    <option value="">All status</option>
                    <option value="pending"   @selected(request('status') === 'pending')>Pending</option>
                    <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                    <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
            </select>
            <div class="bt-quick-ranges" aria-label="Quick date ranges">
                <button type="button" data-transfer-range="today">Today</button>
                <button type="button" data-transfer-range="yesterday">Yesterday</button>
                <button type="button" data-transfer-range="week">Last 7 Days</button>
            </div>
            <input type="date" id="transferDateFrom" name="date_from" aria-label="Date from" value="{{ request('date_from') }}">
            <span class="bt-date-separator">to</span>
            <input type="date" id="transferDateTo" name="date_to" aria-label="Date to" value="{{ request('date_to') }}">
            <button type="submit" class="bt-filter-apply">Apply</button>
            <a href="{{ route('role.bank_transfers.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="bt-filter-reset" title="Reset filters"><i class="fas fa-undo"></i> Reset</a>
        </form>
    </div>

    {{-- WHICH COLUMNS THIS LIST CARRIES (owner, 2026-08-22: "i dont need this
         two column here, do not delete them, just hide them").

         Method and Created By are switched OFF, not cut out. Every line of them
         is still below, behind these flags, so putting one back on screen is
         changing a false to a true here — no markup to write again, and nothing
         to hunt for in the history. The data itself is untouched: both columns
         are still written on save, still on the transfer's own page, and still
         in the database.

         Remarks joins the list in their place. It is the note typed on the entry
         form and until now it was only readable by opening the transfer. --}}
    @php
        $btShowMethod    = false;   // "Cash" / "Bank Transfer"
        $btShowCreatedBy = false;   // who entered it
        $btShowRemarks   = true;    // the note from the entry form
        // The empty-state row has to span whatever survived the switches: eight
        // columns are always there (#, reference, date, from, to, amount, status,
        // actions) and the three below come and go.
        $btCols = 8 + (int) $btShowMethod + (int) $btShowCreatedBy + (int) $btShowRemarks;
    @endphp

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm bt-table bt-table--list">
                <thead>
                    <tr class="bg-gray-800 text-white text-xs uppercase tracking-wide">
                        <th class="px-4 py-3 text-left font-semibold">#</th>
                        <th class="px-4 py-3 text-left font-semibold">Reference No.</th>
                        <th class="px-4 py-3 text-left font-semibold">Date</th>
                        <th class="px-4 py-3 text-left font-semibold">From Bank</th>
                        <th class="px-4 py-3 text-left font-semibold">To Bank</th>
                        @if($btShowRemarks)
                        <th class="px-4 py-3 text-left font-semibold">Remarks</th>
                        @endif
                        <th class="px-4 py-3 text-right font-semibold">Amount</th>
                        @if($btShowMethod)
                        <th class="px-4 py-3 text-left font-semibold">Method</th>
                        @endif
                        <th class="px-4 py-3 text-left font-semibold">Status</th>
                        @if($btShowCreatedBy)
                        <th class="px-4 py-3 text-left font-semibold">Created By</th>
                        @endif
                        <th class="px-2 py-3 text-center font-semibold" style="width:1%;white-space:nowrap">Actions</th>
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
                        {{-- The day, and under it the clock — the same cell the
                             banks ledger prints (banks/dashboard's recordedTimeLine),
                             down to the 0.7rem grey and the 2px gap, so the two
                             screens read alike.

                             bank_transfers.payment_date is a DATE column: a transfer
                             has no clock time of its own. The time below it is
                             created_at — when the entry was written — which is why
                             it is only shown when it lands on the transfer's OWN
                             day. Enter a transfer today for last Tuesday and the
                             row shows Tuesday with no time, rather than Tuesday
                             over a clock reading that belongs to today. --}}
                        <td class="px-4 py-3 text-gray-600 bt-when">
                            {{ $transfer->payment_date->format('d/m/Y') }}
                            @if($transfer->created_at && $transfer->created_at->isSameDay($transfer->payment_date))
                                <div class="bt-when-t">{{ $transfer->created_at->format('H:i:s') }}</div>
                            @endif
                        </td>
                        {{-- Both bank columns are capped and clipped so the space
                             they were holding goes to the remarks (owner, 2026-08-22).
                             The title carries the untruncated name and account, so a
                             clipped name is one hover away from being read in full —
                             a column may lose room, never a fact. --}}
                        <td class="px-4 py-3 bt-bank"
                            title="{{ trim(($transfer->fromBank?->name ?? '—') . ' ' . ($transfer->fromBank?->account_number ?? '')) }}">
                            <span class="font-medium text-gray-700">{{ $transfer->fromBank?->name }}</span>
                            <div class="text-xs text-gray-400">{{ $transfer->fromBank?->account_number }}</div>
                        </td>
                        <td class="px-4 py-3 bt-bank"
                            title="{{ trim(($transfer->toBank?->name ?? '—') . ' ' . ($transfer->toBank?->account_number ?? '')) }}">
                            <span class="font-medium text-gray-700">{{ $transfer->toBank?->name }}</span>
                            <div class="text-xs text-gray-400">{{ $transfer->toBank?->account_number }}</div>
                        </td>
                        @if($btShowRemarks)
                        {{-- Sits between the banks and the amount, where the owner
                             put it (2026-08-22): the row then reads as one sentence
                             — from here, to there, for this, this much — and the
                             money keeps the last word before the status.

                             Clipped, with the whole note in the tooltip: a transfer
                             carrying a paragraph would otherwise set the height of
                             every row beside it. An empty note prints a dash rather
                             than nothing, so the column still reads as a column. --}}
                        <td class="px-4 py-3 text-gray-600 bt-remarks" title="{{ $transfer->remarks }}">
                            {{ $transfer->remarks ? Str::limit($transfer->remarks, 60) : '—' }}
                        </td>
                        @endif
                        <td class="px-4 py-3 text-right font-bold text-gray-800">{{ number_format($transfer->amount, 2) }}</td>
                        @if($btShowMethod)
                        <td class="px-4 py-3 text-gray-600 capitalize">{{ str_replace('_', ' ', $transfer->payment_method) }}</td>
                        @endif
                        <td class="px-4 py-3 bt-status">
                            <span class="bt-badge bt-badge--{{ $transfer->status }}">{{ ucfirst($transfer->status) }}</span>
                        </td>
                        @if($btShowCreatedBy)
                        <td class="px-4 py-3 text-gray-600">{{ optional($transfer->creator)->name ?? '—' }}</td>
                        @endif
                        <td class="px-2 py-3">
                            <div style="display:grid;grid-template-columns:repeat(2,24px);gap:4px;width:max-content;margin:0 auto;justify-content:center" data-row-actions>
                                <a href="{{ route('role.bank_transfers.show', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'bank_transfer' => $transfer]) }}"
                                   class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 transition" title="View" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:13px;height:13px;margin:0">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                @if($transfer->status !== 'completed')
                                    <a href="{{ route('role.bank_transfers.edit', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'bank_transfer' => $transfer->id]) }}"
                                       class="p-1.5 rounded-lg text-amber-600 hover:bg-amber-50 transition" title="Edit" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:13px;height:13px;margin:0">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <form action="{{ route('role.bank_transfers.destroy', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'bank_transfer' => $transfer->id]) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Delete this transfer?')" style="display:contents">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="p-1.5 rounded-lg text-red-600 hover:bg-red-50 transition" title="Delete" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:13px;height:13px;margin:0">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                @elseif(in_array($transfer->id, $reversedTransferIds, true))
                                    <span class="p-1.5 rounded-lg text-gray-300 cursor-not-allowed" title="Already reversed" style="width:24px;height:24px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:6px">
                                        <svg class="w-4 h-4" style="width:13px;height:13px;margin:0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
                                        </svg>
                                    </span>
                                @else
                                    <form action="{{ route('role.bank_transfers.reverse', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'bank_transfer' => $transfer->id]) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Reverse this transfer? This will create an audit reversal and keep the original row visible.')" style="display:contents">
                                        @csrf
                                        <button type="submit" class="p-1.5 rounded-lg text-purple-600 hover:bg-purple-50 transition" title="Reverse" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:13px;height:13px;margin:0">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
                                            </svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $btCols }}" class="px-4 py-16 text-center text-gray-400">
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

@include('bank_transfers.create-modal')
@endsection

@section('js')
<script>
    ['flash-success','flash-error'].forEach(id => {
        const el = document.getElementById(id);
        if (el) setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .5s'; setTimeout(() => el.remove(), 500); }, 4000);
    });

    const filterForm = document.getElementById('bankTransferFilter');
    const filterDateFrom = document.getElementById('transferDateFrom');
    const filterDateTo = document.getElementById('transferDateTo');
    const localDate = date => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };
    document.querySelectorAll('[data-transfer-range]').forEach(button => {
        button.addEventListener('click', () => {
            const end = new Date();
            const start = new Date(end);
            if (button.dataset.transferRange === 'yesterday') {
                start.setDate(start.getDate() - 1);
                end.setDate(end.getDate() - 1);
            } else if (button.dataset.transferRange === 'week') {
                start.setDate(start.getDate() - 6);
            }
            filterDateFrom.value = localDate(start);
            filterDateTo.value = localDate(end);
            filterForm.submit();
        });
    });

    const transferModal = document.getElementById('bankTransferModal');
    const transferForm = document.getElementById('bankTransferCreateForm');
    const submitButton = document.getElementById('createTransferButton');
    const fromBank = document.getElementById('modal_from_bank_id');
    const toBank = document.getElementById('modal_to_bank_id');
    const amount = document.getElementById('modal_transfer_amount');
    const charge = document.getElementById('modal_charge_amount');
    const vat = document.getElementById('modal_vat_amount');
    const destinationBalance = document.getElementById('modalDestinationBalance');
    const transferHour = document.getElementById('modal_transfer_hour');
    const transferMinute = document.getElementById('modal_transfer_minute');
    const transferAmPm = document.getElementById('modal_transfer_ampm');
    const transferTime = document.getElementById('modal_transfer_time');

    function toggleTransferModal(open) {
        transferModal.classList.toggle('is-open', open);
        transferModal.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.body.classList.toggle('bt-modal-open', open);
        if (open) setTimeout(() => fromBank.focus(), 80);
    }

    document.getElementById('openBankTransferModal').addEventListener('click', () => toggleTransferModal(true));
    document.querySelectorAll('[data-close-transfer-modal]').forEach(el => el.addEventListener('click', () => toggleTransferModal(false)));
    document.addEventListener('keydown', event => { if (event.key === 'Escape') toggleTransferModal(false); });

    function updateModalSummary() {
        const selected = fromBank.options[fromBank.selectedIndex];
        const destination = toBank.options[toBank.selectedIndex];
        const balance = Number(selected?.dataset.balance || 0);
        const transferAmount = Number(amount.value || 0);
        const charges = Number(charge.value || 0) + Number(vat.value || 0);
        const total = transferAmount + charges;
        const remaining = balance - total;
        const money = value => `৳${value.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        document.getElementById('modalSourceBalance').textContent = selected?.value
            ? balance.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})
            : '—';
        document.getElementById('modalTransferTotal').textContent = money(total);
        document.getElementById('modalSummaryFrom').textContent = selected?.value ? selected.text.trim() : '—';
        document.getElementById('modalSummaryTo').textContent = destination?.value ? destination.text.trim() : '—';
        document.getElementById('modalSummaryBalance').textContent = selected?.value ? money(balance) : '—';
        document.getElementById('modalSummaryAmount').textContent = money(transferAmount);
        document.getElementById('modalSummaryCharges').textContent = money(charges);
        document.getElementById('modalSummaryDeduction').textContent = money(total);
        const remainingEl = document.getElementById('modalSummaryRemaining');
        remainingEl.textContent = selected?.value ? money(remaining) : '—';
        remainingEl.classList.toggle('is-negative', Boolean(selected?.value && remaining < 0));
        document.getElementById('modalInsufficientBalance').classList.toggle('is-visible', Boolean(selected?.value && total > 0 && remaining < 0));
    }
    [fromBank, amount, charge, vat].forEach(el => el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', updateModalSummary));
    fromBank.addEventListener('change', () => {
        Array.from(toBank.options).forEach(option => option.disabled = option.value !== '' && option.value === fromBank.value);
        if (toBank.value === fromBank.value) toBank.value = '';
    });
    function updateDestinationBalance() {
        const selected = toBank.options[toBank.selectedIndex];
        const balance = Number(selected?.dataset.balance || 0);
        destinationBalance.textContent = selected?.value
            ? balance.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})
            : '—';
    }
    toBank.addEventListener('change', () => {
        toBank.setCustomValidity('');
        updateDestinationBalance();
        updateModalSummary();
    });
    updateDestinationBalance();
    updateModalSummary();

    function composeTransferTime() {
        if (!transferHour.value) {
            transferTime.value = '';
            return;
        }
        let hour = Number(transferHour.value) % 12;
        if (transferAmPm.value === 'PM') hour += 12;
        transferTime.value = String(hour).padStart(2, '0') + ':' + String(transferMinute.value || 0).padStart(2, '0');
    }
    [transferHour, transferMinute, transferAmPm].forEach(el => el.addEventListener('change', composeTransferTime));
    composeTransferTime();

    transferForm.addEventListener('submit', function (event) {
        composeTransferTime();
        if (!transferForm.checkValidity()) return;
        if (fromBank.value === toBank.value) {
            event.preventDefault();
            toBank.setCustomValidity('Source and destination accounts must be different.');
            toBank.reportValidity();
            return;
        }
        toBank.setCustomValidity('');
        submitButton.disabled = true;
        submitButton.classList.add('is-loading');
    });

    @if($errors->any())
        toggleTransferModal(true);
    @endif
</script>
@endsection
