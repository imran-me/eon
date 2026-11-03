@if(auth()->user()?->is_super_admin)
<div id="discountModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden" role="dialog" aria-modal="true" aria-labelledby="discountTitle">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeDiscountModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-xl z-10 flex flex-col max-h-[92vh] overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 bg-violet-600">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center"><i class="fas fa-percent text-white"></i></div>
                <div>
                    <h3 id="discountTitle" class="text-base font-bold text-white">Give Discount / Credit Note</h3>
                    <p class="text-xs text-violet-100">Lowers what the party owes — no bank or cash moves</p>
                </div>
            </div>
            <button type="button" onclick="closeDiscountModal()" aria-label="Close" class="w-8 h-8 flex items-center justify-center rounded-lg bg-white/20 hover:bg-white/30 text-white transition"><i class="fas fa-times text-sm"></i></button>
        </div>
        <form id="discountForm" onsubmit="submitDiscount(event)" class="flex flex-col flex-1 min-h-0">
            @csrf
            <div class="px-6 py-4 space-y-4 overflow-y-auto flex-1">
                <div class="grid grid-cols-2 gap-1 p-1 bg-gray-100 rounded-xl text-sm font-semibold">
                    <button type="button" id="discountModeSingle" onclick="setDiscountMode('single')" class="py-2 rounded-lg transition">Single invoice</button>
                    <button type="button" id="discountModeOverall" onclick="setDiscountMode('overall')" class="py-2 rounded-lg transition">Overall (all invoices)</button>
                </div>
                <div id="discountSingleBox">
                    <label for="discountDocument" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Invoice / Flight package</label>
                    <select id="discountDocument" required onchange="discountDueHint()" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-violet-300"></select>
                    <p id="discountDueHint" class="text-xs text-violet-600 mt-1.5 min-h-[1rem]"></p>
                </div>
                <div id="discountOverallBox" class="hidden rounded-xl border border-violet-100 bg-violet-50 px-4 py-3 text-sm text-violet-900">
                    <p class="font-semibold mb-1">Spread oldest invoice first</p>
                    <div id="discountSplit" class="text-xs space-y-0.5 text-violet-800">Enter an amount to see how it is split.</div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="discountAmount" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Discount (৳)</label>
                        <input id="discountAmount" oninput="discountPreview()" required type="number" min="0.01" step="0.01" placeholder="0.00" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-300">
                    </div>
                    <div>
                        <label for="discountDate" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Date</label>
                        <input id="discountDate" required type="date" max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-300">
                    </div>
                </div>
                <div>
                    <label for="discountReason" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Reason</label>
                    <textarea id="discountReason" required minlength="5" maxlength="500" rows="2" placeholder="Why is this discount being given? (min 5 characters)" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-300"></textarea>
                    <p class="text-xs text-gray-400 mt-1.5">Journal: Dr Sales Discounts &amp; Allowances / Cr Accounts Receivable.</p>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 px-6 py-4 bg-gray-50 border-t border-gray-100">
                <button type="button" onclick="closeDiscountModal()" class="px-5 py-2.5 text-sm font-semibold text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-100 transition">Cancel</button>
                <button id="discountSubmit" type="submit" class="px-5 py-2.5 text-sm font-semibold text-white bg-violet-600 rounded-xl hover:bg-violet-700 disabled:opacity-50 disabled:cursor-not-allowed transition"><i class="fas fa-check mr-1.5"></i>Post Discount</button>
            </div>
        </form>
    </div>
</div>
@endif
<div id="paymentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closePaymentModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-5xl z-10 flex flex-col max-h-[92vh] overflow-hidden">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 bg-emerald-600 rounded-t-2xl">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center">
                    <i class="fas fa-money-bill-wave text-white"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Record Payment</h3>
                    <p class="text-xs text-emerald-100">Mark a payment received from party</p>
                </div>
            </div>
            <button onclick="closePaymentModal()"
                class="w-8 h-8 flex items-center justify-center rounded-lg bg-white/20 hover:bg-white/30 text-white transition">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        {{-- Body --}}
        <div class="px-6 py-4 space-y-3 overflow-y-auto flex-1">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            {{-- Party (readonly) --}}
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                    Party (Agent / Vendor / Customer)
                </label>
                <div id="pmPartyDisplay"
                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-100 text-gray-600 font-medium">
                    —
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Transaction Action</label>
                <select id="pmAction"
                    class="w-full px-4 py-2.5 text-sm font-semibold border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-white transition">
                </select>
                <p id="pmActionHelp" class="mt-1 text-xs text-gray-400"></p>
            </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {{-- Amount --}}
                <div class="col-span-2 sm:col-span-1">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                        Amount (৳) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" id="pmAmount" min="0.01" step="0.01" placeholder="0"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-gray-50 transition">
                </div>

                {{-- Payment Date --}}
                <div class="col-span-2 sm:col-span-1">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Payment Date</label>
                    <input type="date" id="pmDate" max="{{ now()->format('Y-m-d') }}"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-gray-50 transition">
                </div>
                <div class="col-span-2 sm:col-span-1">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Custom Time <span class="text-red-500">*</span></label>
                    <div class="flex items-center gap-1">
                        <select id="pmHour" class="min-w-0 flex-1 px-2 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50">@for($h=1;$h<=12;$h++)<option value="{{ $h }}">{{ str_pad($h,2,'0',STR_PAD_LEFT) }}</option>@endfor</select>
                        <b>:</b>
                        <select id="pmMinute" class="min-w-0 flex-1 px-2 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50">@for($m=0;$m<60;$m++)<option value="{{ $m }}">{{ str_pad($m,2,'0',STR_PAD_LEFT) }}</option>@endfor</select>
                        <select id="pmPeriod" class="min-w-0 flex-1 px-2 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50"><option>AM</option><option>PM</option></select>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Payment Method --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Payment Method</label>
                    <select id="pmMode"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-gray-50 transition">
                        <option value="cash">Cash</option>
                        <option value="bank_transfer" selected>Bank Transfer</option>
                        <option value="cheque">Cheque</option>
                        <option value="mobile_banking">Mobile Banking</option>
                        <option value="online">Online</option>
                    </select>
                </div>

                {{-- Bank Account --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                        Bank / Cash Account <span class="text-red-500">*</span>
                    </label>
                    <select id="pmBank"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-gray-50 transition">
                        <option value="">— Select Account —</option>
                        @include('partials.bank-options', ['banks' => $banks, 'selected' => request('bank_id')])
                    </select>
                </div>
            </div>

            <div id="pmPayerFields" class="hidden rounded-xl border border-blue-200 bg-blue-50/60 p-3">
                <p id="pmPayerTitle" class="mb-2 text-[11px] font-bold uppercase tracking-wide text-blue-700"></p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div><label id="pmPayerBankLabel" class="block text-xs font-semibold text-gray-600 mb-1.5"></label><input type="text" id="pmPayerBank" maxlength="150" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-white"></div>
                    <div><label id="pmPayerReferenceLabel" class="block text-xs font-semibold text-gray-600 mb-1.5"></label><input type="text" id="pmPayerReference" maxlength="100" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-white"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
            <div id="pmAllocationWrap">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Payment Allocation</label>
                <select id="pmAllocationMode" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50">
                    <option value="unallocated" selected>Overall Advance / Unallocated Payment</option>
                    <option value="auto">Auto Allocate — Oldest First</option>
                    <option value="manual">Manual Allocation</option>
                </select>
                <div id="pmAllocationTable" class="mt-2 overflow-hidden rounded-xl border border-gray-200"></div>
                <p id="pmInvoiceHelp" class="mt-1 text-xs text-gray-400">Oldest unpaid documents are settled first.</p>
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900">
                <div id="pmAllocationNoticeTitle" class="font-semibold"><i class="fas fa-circle-info mr-1"></i> General / unallocated party payment</div>
                <div id="pmAccountingHint" class="mt-1"></div>
                <div id="pmAllocationNoticeBody" class="mt-1">Select an invoice above to settle its due, or leave it unallocated for an advance/general payment.</div>
            </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
            {{-- Reference No --}}
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">System Reference</label>
                <input type="text" id="pmRef" readonly value="Auto-generated when saved"
                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-100 text-gray-500 cursor-not-allowed">
            </div>

            {{-- Notes --}}
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Notes</label>
                <textarea id="pmNotes" rows="2" placeholder="Payment purpose, invoice ref…"
                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-gray-50 transition resize-none"></textarea>
            </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
            <button onclick="closePaymentModal()"
                class="px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition">
                Cancel
            </button>
            <button id="pmSubmit" onclick="submitPayment()"
                class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold text-white bg-emerald-600 rounded-xl hover:bg-emerald-700 transition shadow-sm">
                <i class="fas fa-check text-xs"></i> Record Payment
            </button>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════
     CREATE INVOICE MODAL
══════════════════════════════════════════════ --}}
<div id="invoiceModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeInvoiceModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg z-10 flex flex-col">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 bg-blue-600 rounded-t-2xl">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center">
                    <i class="fas fa-file-invoice text-white"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Create Invoice</h3>
                    <p class="text-xs text-blue-100">Issue a new invoice for this party</p>
                </div>
            </div>
            <button onclick="closeInvoiceModal()"
                class="w-8 h-8 flex items-center justify-center rounded-lg bg-white/20 hover:bg-white/30 text-white transition">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        {{-- Body --}}
        <div class="px-6 py-5 space-y-4">

            {{-- Bill To (readonly) --}}
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                    Bill To <span class="text-red-500">*</span>
                </label>
                <div id="invPartyDisplay"
                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-100 text-gray-600 font-medium">
                    —
                </div>
            </div>

            {{-- Service Description --}}
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                    Service Description <span class="text-red-500">*</span>
                </label>
                <input type="text" id="invDesc" placeholder="e.g. Saudi Arabia Work Permit Package ×8"
                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 transition">
            </div>

            <div class="grid grid-cols-2 gap-4">
                {{-- Amount --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                        Amount (৳) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" id="invAmount" min="0.01" step="0.01" placeholder="0"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 transition">
                </div>

                {{-- Due Date --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Due Date</label>
                    <input type="date" id="invDue"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 transition">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                {{-- Destination --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Destination</label>
                    <select id="invCountry"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 transition">
                        <option value="">— Select Country —</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- No. of Candidates --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">No. of Candidates</label>
                    <input type="number" id="invCandidates" min="1" placeholder="e.g. 8"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 transition">
                </div>
            </div>

            {{-- Notes --}}
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Notes</label>
                <textarea id="invNotes" rows="2" placeholder="Payment terms, special conditions…"
                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 transition resize-none"></textarea>
            </div>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
            <button onclick="closeInvoiceModal()"
                class="px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition">
                Cancel
            </button>
            <button id="invSubmit" onclick="submitInvoice()"
                class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-sm">
                <i class="fas fa-file-invoice text-xs"></i> Create Invoice
            </button>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════
     OPENING BALANCE MODAL
══════════════════════════════════════════════ --}}
<div id="obModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeOpeningBalanceModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md z-10 flex flex-col">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 bg-slate-600 rounded-t-2xl">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center">
                    <i class="fas fa-flag text-white"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Set Opening Balance</h3>
                    <p class="text-xs text-slate-200">One-time starting balance for this party</p>
                </div>
            </div>
            <button onclick="closeOpeningBalanceModal()"
                class="w-8 h-8 flex items-center justify-center rounded-lg bg-white/20 hover:bg-white/30 text-white transition">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        {{-- Body --}}
        <div class="px-6 py-5 space-y-4">

            {{-- Party (readonly) --}}
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Party</label>
                <div id="obPartyDisplay"
                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-100 text-gray-600 font-medium">
                    —
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                {{-- Amount --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                        Amount (৳) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" id="obAmount" min="0.01" step="0.01" placeholder="0"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-500 bg-gray-50 transition">
                </div>

                {{-- Direction --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Direction</label>
                    <select id="obDirection"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-500 bg-gray-50 transition">
                        <option value="receivable">Receivable</option>
                        <option value="payable">Payable</option>
                    </select>
                </div>
            </div>

            {{-- As of date --}}
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                    As Of Date <span class="text-red-500">*</span>
                </label>
                <input type="date" id="obDate"
                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-500 bg-gray-50 transition">
                <p class="text-[11px] text-gray-400 mt-1">Pick a date earlier than any existing entries for this party.</p>
            </div>

            {{-- Note --}}
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Note</label>
                <textarea id="obNote" rows="2" placeholder="e.g. Carried over from previous manual ledger"
                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-500 bg-gray-50 transition resize-none"></textarea>
            </div>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
            <button onclick="closeOpeningBalanceModal()"
                class="px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition">
                Cancel
            </button>
            <button id="obSubmit" onclick="submitOpeningBalance()"
                class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold text-white bg-slate-600 rounded-xl hover:bg-slate-700 transition shadow-sm">
                <i class="fas fa-flag text-xs"></i> Set Opening Balance
            </button>
        </div>
    </div>
</div>

{{-- The Invoice window — sales invoice + new invoice in one big modal (2026-10-14). --}}
@if(\Illuminate\Support\Facades\Route::has('role.party-statement.sales-invoice-lines'))
    @include('party-statement.partials.invoice-window')
@endif
