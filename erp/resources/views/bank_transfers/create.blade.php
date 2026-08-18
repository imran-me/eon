@extends('layout.app')

@section('meta-information')
    <title>New Bank Transfer</title>
@endsection

@section('css')
    @include('bank_transfers.css')
@endsection

@section('main-content')
<div class="px-4 py-6 max-w-screen-2xl mx-auto">

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">New Bank Transfer</h1>
            <nav class="text-sm text-gray-500 mt-1">
                <a href="#" class="hover:text-blue-600">Dashboard</a>
                <span class="mx-1">/</span>
                <a href="{{ route('role.bank_transfers.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="hover:text-blue-600">Bank Transfers</a>
                <span class="mx-1">/</span>
                <span class="text-gray-700">New</span>
            </nav>
        </div>
        <a href="{{ route('role.bank_transfers.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
           class="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-100 text-gray-600 text-sm font-semibold px-4 py-2 rounded-lg transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back
        </a>
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

    <form action="{{ route('role.bank_transfers.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST" id="transferForm">
        @csrf

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
                                                data-currency="{{ $bank->currency ?? '' }}"
                                                @selected(old('from_bank_id') == $bank->id)>
                                            {{ $bank->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('from_bank_id')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                                <div id="fromBankInfo" class="hidden mt-3 pt-3 border-t border-gray-100">
                                    <p class="text-xs text-gray-500">Available Balance</p>
                                    <p id="fromBalance" class="text-lg font-bold text-emerald-600">0.00</p>
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
                                                @selected(old('to_bank_id') == $bank->id)>
                                            {{ $bank->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('to_bank_id')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                                <div id="toBankInfo" class="hidden mt-3 pt-3 border-t border-gray-100">
                                    <p class="text-xs text-gray-500">Current Balance</p>
                                    <p id="toBalance" class="text-lg font-bold text-blue-600">0.00</p>
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
                                       value="{{ old('amount') }}" placeholder="0.00" required
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
                                   value="{{ old('payment_date', date('Y-m-d')) }}" required
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
                                <option value="pending"   @selected(old('status', 'pending') === 'pending')>Pending</option>
                                <option value="completed" @selected(old('status') === 'completed')>Completed</option>
                                <option value="cancelled" @selected(old('status') === 'cancelled')>Cancelled</option>
                            </select>
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
                                   value="{{ old('reference_no', $referenceNo) }}" required
                                   class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-300 focus:outline-none @error('reference_no') border-red-400 @enderror">
                            @error('reference_no')
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Payment Method --}}
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Payment Method <span class="text-red-500">*</span></label>
                            <select name="payment_method" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-300 focus:outline-none @error('payment_method') border-red-400 @enderror">                              
                                <option value="cash">Cash</option>                                                    
                                <option value="card">Card</option>
                                <option value="advance">Advance</option>
                                <option value="checque">Checque</option>
                                <option value="bank_transfer" selected>Bank Transfer</option>
                                <option value="mobile_banking">Mobile Banking</option>
                                <option value="other">Other</option>
                            </select>
                            @error('payment_method')
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Remarks --}}
                        <div class="space-y-1 sm:col-span-2 lg:col-span-3">
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Remarks</label>
                            <textarea name="remarks" rows="3" placeholder="Optional notes about this transfer..."
                                      class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-300 focus:outline-none resize-none @error('remarks') border-red-400 @enderror">{{ old('remarks') }}</textarea>
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
                    <div class="bg-blue-600 text-white px-5 py-3 font-semibold text-sm">
                        Transfer Summary
                    </div>
                    <div class="p-5 space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">From</span>
                            <span class="font-semibold text-gray-700 text-right max-w-[60%]" id="summaryFrom">—</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">To</span>
                            <span class="font-semibold text-gray-700 text-right max-w-[60%]" id="summaryTo">—</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Source Balance</span>
                            <span class="text-gray-600" id="summaryBalance">—</span>
                        </div>
                        <div class="border-t border-gray-100 pt-3 flex justify-between items-center">
                            <span class="font-bold text-gray-700">Amount</span>
                            <span class="text-2xl font-bold text-blue-600" id="summaryAmount">0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Remaining</span>
                            <span class="font-semibold" id="summaryRemaining">—</span>
                        </div>

                        {{-- Insufficient balance alert --}}
                        <div id="insufficientAlert"
                             class="hidden bg-red-50 border border-red-200 text-red-700 text-xs px-3 py-2 rounded-lg flex items-center gap-2 bt-shake">
                            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            Insufficient balance in source bank.
                        </div>
                    </div>
                    <div class="px-5 pb-5">
                        <button type="submit"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-lg transition flex items-center justify-center gap-2 text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                            Create Transfer
                        </button>
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
    const fromBankInfo      = document.getElementById('fromBankInfo');
    const toBankInfo        = document.getElementById('toBankInfo');
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

        const showAlert = fromOpt?.value && amount > 0 && remaining < 0;
        insufficientAlert.classList.toggle('hidden', !showAlert);
        if (showAlert) {
            insufficientAlert.classList.remove('bt-shake');
            void insufficientAlert.offsetWidth;
            insufficientAlert.classList.add('bt-shake');
        }

        // From info panel
        if (fromOpt?.value) {
            fromBankInfo.classList.remove('hidden');
            fromBalanceEl.textContent = fmt(balance);
        } else {
            fromBankInfo.classList.add('hidden');
        }

        // To info panel
        if (toOpt?.value) {
            toBankInfo.classList.remove('hidden');
            toBalanceEl.textContent = fmt(parseFloat(toOpt.dataset.balance) || 0);
        } else {
            toBankInfo.classList.add('hidden');
        }
    }

    fromSelect.addEventListener('change', updateSummary);
    toSelect.addEventListener('change', updateSummary);
    amountInput.addEventListener('input', updateSummary);
    updateSummary();
</script>
@endsection