@extends('layout.app')

@section('meta-information')
    <title>Edit Transfer — {{ $bankTransfer->reference_no }}</title>
@endsection

@section('css')
    @include('bank_transfers.css')
@endsection

@section('main-content')
<div class="px-4 py-6 max-w-screen-2xl mx-auto">

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Edit Transfer</h1>
            <nav class="text-sm text-gray-500 mt-1">
                <a href="#" class="hover:text-blue-600">Dashboard</a>
                <span class="mx-1">/</span>
                <a href="{{ route('role.bank_transfers.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="hover:text-blue-600">Bank Transfers</a>
                <span class="mx-1">/</span>
                <span class="text-gray-700">Edit</span>
            </nav>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('role.bank_transfers.show', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'bank_transfer' => $bankTransfer]) }}"
               class="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-100 text-gray-600 text-sm font-semibold px-4 py-2 rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                View
            </a>
            <a href="{{ route('role.bank_transfers.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
               class="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-100 text-gray-600 text-sm font-semibold px-4 py-2 rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back
            </a>
        </div>
    </div>

    {{-- Validation Errors --}}
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-5 text-sm">
            <p class="font-semibold mb-1">Please fix the following errors:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('role.bank_transfers.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'bank_transfer' => $bankTransfer]) }}" method="POST" id="transferForm">
        @csrf @method('PUT')

        <div class="flex flex-col lg:flex-row gap-6">

            {{-- ── Left Column ──────────────────────────────────────────────── --}}
            <div class="flex-1 min-w-0 space-y-5">

                {{-- Bank Selection --}}
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-100 font-semibold text-gray-700 text-sm">
                        Transfer Route
                    </div>
                    <div class="p-5">
                        <div class="grid grid-cols-11 gap-3 items-center">

                            {{-- From Bank --}}
                            <div class="col-span-5 bt-bank-box">
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-2">From Bank</p>
                                <select name="from_bank_id" id="from_bank_id" required
                                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-300 focus:outline-none bg-white @error('from_bank_id') border-red-400 @enderror">
                                    <option value="">— Select Source —</option>
                                    @foreach($banks as $bank)
                                        <option value="{{ $bank->id }}"
                                                data-balance="{{ $bank->balance }}"
                                                @selected(old('from_bank_id', $bankTransfer->from_bank_id) == $bank->id)>
                                            {{ $bank->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('from_bank_id')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                                <div id="fromBankInfo" class="mt-3 pt-3 border-t border-gray-100">
                                    <p class="text-xs text-gray-500">Available Balance</p>
                                    <p id="fromBalance" class="text-lg font-bold text-emerald-600">
                                        {{ number_format($bankTransfer->fromBank->balance, 2) }}
                                    </p>
                                </div>
                            </div>

                            {{-- Arrow --}}
                            <div class="col-span-1 bt-arrow-wrapper">
                                <div class="bt-arrow">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                    </svg>
                                </div>
                            </div>

                            {{-- To Bank --}}
                            <div class="col-span-5 bt-bank-box">
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-2">To Bank</p>
                                <select name="to_bank_id" id="to_bank_id" required
                                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-300 focus:outline-none bg-white @error('to_bank_id') border-red-400 @enderror">
                                    <option value="">— Select Destination —</option>
                                    @foreach($banks as $bank)
                                        <option value="{{ $bank->id }}"
                                                data-balance="{{ $bank->balance }}"
                                                @selected(old('to_bank_id', $bankTransfer->to_bank_id) == $bank->id)>
                                            {{ $bank->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('to_bank_id')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                                <div id="toBankInfo" class="mt-3 pt-3 border-t border-gray-100">
                                    <p class="text-xs text-gray-500">Current Balance</p>
                                    <p id="toBalance" class="text-lg font-bold text-blue-600">
                                        {{ number_format($bankTransfer->toBank->balance, 2) }}
                                    </p>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                {{-- Transfer Details --}}
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-100 font-semibold text-gray-700 text-sm">
                        Transfer Details
                    </div>
                    <div class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                        {{-- Amount --}}
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                Amount <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-semibold text-sm">৳</span>
                                <input type="number" name="amount" id="amount" step="0.01" min="0.01"
                                       value="{{ old('amount', $bankTransfer->amount) }}" required
                                       class="w-full pl-7 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-300 focus:outline-none @error('amount') border-red-400 @enderror">
                            </div>
                            @error('amount')
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Payment Date --}}
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                Payment Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" name="payment_date"
                                   value="{{ old('payment_date', $bankTransfer->payment_date->format('Y-m-d')) }}" required
                                   class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-300 focus:outline-none @error('payment_date') border-red-400 @enderror">
                            @error('payment_date')
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Status --}}
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                Status <span class="text-red-500">*</span>
                            </label>
                            <select name="status" required
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-300 focus:outline-none @error('status') border-red-400 @enderror">
                                <option value="pending"   @selected(old('status', $bankTransfer->status) === 'pending')>Pending</option>
                                <option value="completed" @selected(old('status', $bankTransfer->status) === 'completed')>Completed</option>
                                <option value="cancelled" @selected(old('status', $bankTransfer->status) === 'cancelled')>Cancelled</option>
                            </select>
                            <p class="text-xs text-amber-600 flex items-center gap-1">
                                <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                                Setting to Completed will update bank balances.
                            </p>
                            @error('status')
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Reference No --}}
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                Reference No. <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="reference_no"
                                   value="{{ old('reference_no', $bankTransfer->reference_no) }}" required
                                   class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-300 focus:outline-none @error('reference_no') border-red-400 @enderror">
                            @error('reference_no')
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Payment Method --}}
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                Payment Method <span class="text-red-500">*</span>
                            </label>
                            <select name="payment_method" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-300 focus:outline-none @error('payment_method') border-red-400 @enderror">                                
                                <option value="cash" {{ $bankTransfer->payment_method == 'cash' ? 'selected' : '' }}>Cash</option>                                                    
                                <option value="card" {{ $bankTransfer->payment_method == 'card' ? 'selected' : '' }}>Card</option>
                                <option value="advance" {{ $bankTransfer->payment_method == 'advance' ? 'selected' : '' }}>Advance</option>
                                <option value="checque" {{ $bankTransfer->payment_method == 'checque' ? 'selected' : '' }}>Checque</option>
                                <option value="bank_transfer" {{ $bankTransfer->payment_method == 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                <option value="mobile_banking" {{ $bankTransfer->payment_method == 'mobile_banking' ? 'selected' : '' }}>Mobile Banking</option>
                                <option value="other" {{ $bankTransfer->payment_method == 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                            @error('payment_method')
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Remarks --}}
                        <div class="space-y-1 sm:col-span-2 lg:col-span-3">
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Remarks</label>
                            <textarea name="remarks" rows="3" placeholder="Optional notes..."
                                      class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-300 focus:outline-none resize-none @error('remarks') border-red-400 @enderror">{{ old('remarks', $bankTransfer->remarks) }}</textarea>
                            @error('remarks')
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>
                </div>
            </div>

            {{-- ── Right Sidebar ─────────────────────────────────────────────── --}}
            <div class="lg:w-72 shrink-0">
                <div class="bt-sidebar-sticky bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="bg-amber-500 text-white px-5 py-3 font-semibold text-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Editing Transfer
                    </div>
                    <div class="p-5 space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">From</span>
                            <span class="font-semibold text-gray-700 text-right max-w-[60%]" id="summaryFrom">{{ $bankTransfer->fromBank->name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">To</span>
                            <span class="font-semibold text-gray-700 text-right max-w-[60%]" id="summaryTo">{{ $bankTransfer->toBank->name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Source Balance</span>
                            <span class="text-gray-600" id="summaryBalance">{{ number_format($bankTransfer->fromBank->balance, 2) }}</span>
                        </div>
                        <div class="border-t border-gray-100 pt-3 flex justify-between items-center">
                            <span class="font-bold text-gray-700">Amount</span>
                            <span class="text-2xl font-bold text-amber-500" id="summaryAmount">{{ number_format($bankTransfer->amount, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Remaining</span>
                            <span class="font-semibold text-emerald-600" id="summaryRemaining">
                                {{ number_format($bankTransfer->fromBank->balance - $bankTransfer->amount, 2) }}
                            </span>
                        </div>

                        <div id="insufficientAlert"
                             class="hidden bg-red-50 border border-red-200 text-red-700 text-xs px-3 py-2 rounded-lg flex items-center gap-2">
                            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            Insufficient balance in source bank.
                        </div>

                        {{-- Meta info --}}
                        <div class="bg-gray-50 rounded-lg p-3 text-xs space-y-1 border border-gray-100">
                            <p class="text-gray-400 font-semibold uppercase tracking-wide">Transfer Info</p>
                            <p class="text-gray-600">Ref: <span class="font-semibold text-gray-800">{{ $bankTransfer->reference_no }}</span></p>
                            <p class="text-gray-600">Created: {{ $bankTransfer->created_at->format('d M Y H:i') }}</p>
                            <p class="text-gray-600">By: {{ optional($bankTransfer->creator)->name ?? '—' }}</p>
                        </div>
                    </div>
                    <div class="px-5 pb-5 space-y-2">
                        <button type="submit"
                                class="w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold py-2.5 rounded-lg transition flex items-center justify-center gap-2 text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                            </svg>
                            Update Transfer
                        </button>
                        <a href="{{ route('role.bank_transfers.show', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'bank_transfer' => $bankTransfer]) }}"
                           class="w-full block text-center border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm font-semibold py-2 rounded-lg transition">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </form>

</div>
@endsection

@section('js')
<script>
    const fromSelect        = document.getElementById('from_bank_id');
    const toSelect          = document.getElementById('to_bank_id');
    const amountInput       = document.getElementById('amount');
    const fromBalanceEl     = document.getElementById('fromBalance');
    const toBalanceEl       = document.getElementById('toBalance');
    const summaryFrom       = document.getElementById('summaryFrom');
    const summaryTo         = document.getElementById('summaryTo');
    const summaryBalance    = document.getElementById('summaryBalance');
    const summaryAmount     = document.getElementById('summaryAmount');
    const summaryRemaining  = document.getElementById('summaryRemaining');
    const insufficientAlert = document.getElementById('insufficientAlert');

    function fmt(n) {
        return parseFloat(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function updateSummary() {
        const fromOpt  = fromSelect.options[fromSelect.selectedIndex];
        const toOpt    = toSelect.options[toSelect.selectedIndex];
        const amount   = parseFloat(amountInput.value) || 0;
        const balance  = parseFloat(fromOpt?.dataset?.balance) || 0;
        const remaining = balance - amount;

        summaryFrom.textContent   = fromOpt?.value ? fromOpt.text : '—';
        summaryTo.textContent     = toOpt?.value   ? toOpt.text   : '—';
        summaryBalance.textContent = fromOpt?.value ? fmt(balance) : '—';
        summaryAmount.textContent  = fmt(amount);
        summaryRemaining.textContent = fromOpt?.value ? fmt(remaining) : '—';
        summaryRemaining.className = 'font-semibold ' + (remaining < 0 ? 'text-red-600' : 'text-emerald-600');

        insufficientAlert.classList.toggle('hidden', !(fromOpt?.value && amount > 0 && remaining < 0));

        if (fromOpt?.value) fromBalanceEl.textContent = fmt(balance);
        if (toOpt?.value)   toBalanceEl.textContent   = fmt(parseFloat(toOpt.dataset.balance) || 0);
    }

    fromSelect.addEventListener('change', updateSummary);
    toSelect.addEventListener('change', updateSummary);
    amountInput.addEventListener('input', updateSummary);
    updateSummary();
</script>
@endsection