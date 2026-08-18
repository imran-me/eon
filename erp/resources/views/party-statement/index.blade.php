@extends('layout.app')

@section('title', 'Party Statement')

@section('main-content')
@php $role = Str::slug(Auth::user()->getRoleNames()->first()); @endphp

<style>
@media print {
    /* Hide all layout chrome */
    #sidebar,
    #mobileMenuButton,
    .header,
    .admin-footer,
    #commandPalette { display: none !important; }

    /* Remove sidebar offset from main wrapper */
    #mainContentWrapper { margin-left: 0 !important; }

    /* Remove page padding */
    body { background: #fff !important; }
    main { padding: 0 !important; }

    /* Hide the outer page bg */
    .min-h-screen { background: #fff !important; }

    /* Hide search / filter bar */
    #searchFilterBar,
    #placeholderArea { display: none !important; }

    /* Hide action buttons in ledger header */
    #ledgerActionBtns { display: none !important; }

    /* Hide ledger meta footer (email/whatsapp/reminder) */
    #ledgerMeta { display: none !important; }

    /* Keep page header gradient but strip padding */
    .print-no-show { display: none !important; }
}
</style>

<div class="min-h-screen bg-gray-50">

    {{-- ══ PAGE HEADER ══ --}}
    <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-purple-700 px-6 py-5 shadow-lg">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                    <i class="fas fa-book-open text-white text-lg"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-white leading-tight">Party Statement</h1>
                    <p class="text-xs text-blue-200 mt-0.5">Bank-Style Ledger &mdash; Vendor &middot; Agent &middot; Client</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="text-right bg-white/10 rounded-xl px-4 py-2">
                    <p class="text-[10px] text-blue-200 uppercase tracking-wider font-semibold">Total Receivable</p>
                    <p class="text-lg font-bold text-white">৳{{ number_format($totalReceivable / 100000, 2) }}L</p>
                </div>
                <div class="text-right bg-white/10 rounded-xl px-4 py-2">
                    <p class="text-[10px] text-blue-200 uppercase tracking-wider font-semibold">Active Ledgers</p>
                    <p class="text-lg font-bold text-white">{{ $activeLedgers }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="px-6 py-5 space-y-5">

        {{-- ══ SEARCH / FILTER BAR ══ --}}
        <div id="searchFilterBar" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                <i class="fas fa-search text-blue-500"></i> Select Party
            </p>
            <div class="flex flex-wrap gap-3 items-end">
                {{-- Party search --}}
                <div class="flex-1 min-w-[220px] relative">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input type="text" id="partySearch" placeholder="Search by name, phone or ID…"
                            autocomplete="off"
                            class="w-full pl-9 pr-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 transition">
                    </div>
                    <div id="partyDropdown"
                        class="hidden absolute z-30 bg-white border border-gray-200 rounded-xl shadow-xl mt-1 w-full max-h-64 overflow-y-auto"></div>
                </div>

                {{-- Type filter --}}
                <select id="partyType" class="px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 transition">
                    <option value="all">All Parties</option>
                    <option value="vendor">Vendor Only</option>
                    <option value="agent">Agent Only</option>
                    <option value="client">Client Only</option>
                </select>

                {{-- Transaction type filter --}}
                <select id="txnTypeFilter" class="px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 transition">
                    <option value="all">All Transactions</option>
                    <option value="ticket_sale">Ticket Sale</option>
                    <option value="flight_sale">Flight Sale</option>
                    <option value="file_sale">File Sale</option>
                    <option value="visa_sale">Visa Sale</option>
                    <option value="party_payment">Payments</option>
                    <option value="party_invoice">Invoices</option>
                </select>

                {{-- Date preset --}}
                <select id="datePreset" class="px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 transition">
                    <option value="this_year">This Year</option>
                    <option value="this_month">This Month</option>
                    <option value="last_3">Last 3 Months</option>
                    <option value="last_6">Last 6 Months</option>
                    <option value="all_time">All Time</option>
                    <option value="custom">Custom Range</option>
                </select>

                {{-- Custom date row --}}
                <div id="customDateRow" class="hidden flex gap-2">
                    <input type="date" id="dateFrom" class="px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50">
                    <input type="date" id="dateTo"   class="px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50">
                </div>

                {{-- Load --}}
                <button id="loadBtn" disabled
                    class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-sm disabled:opacity-40 disabled:cursor-not-allowed">
                    <i class="fas fa-sync-alt text-xs" id="loadIcon"></i> Load
                </button>

                {{-- PDF --}}
                <button id="pdfBtn" onclick="exportPdf()" disabled
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-red-50 hover:border-red-200 hover:text-red-600 transition disabled:opacity-40 disabled:cursor-not-allowed">
                    <i class="fas fa-file-pdf text-xs"></i> PDF
                </button>

                {{-- Excel --}}
                <button id="excelBtn" onclick="exportExcel()" disabled
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-emerald-50 hover:border-emerald-200 hover:text-emerald-600 transition disabled:opacity-40 disabled:cursor-not-allowed">
                    <i class="fas fa-file-excel text-xs"></i> Excel
                </button>

                {{-- Print --}}
                <button id="printBtn" onclick="exportPdf()" disabled
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 hover:border-gray-300 transition disabled:opacity-40 disabled:cursor-not-allowed">
                    <i class="fas fa-print text-xs"></i> Print
                </button>
            </div>

            {{-- Selected party chip --}}
            <div id="selectedPartyChip" class="hidden mt-3 flex items-center gap-2">
                <span class="text-xs text-gray-400">Selected:</span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50 border border-blue-100 text-blue-700 text-xs font-semibold rounded-full">
                    <i class="fas fa-user text-[10px]"></i>
                    <span id="chipName"></span>
                </span>
                <button onclick="clearParty()" class="text-xs text-gray-400 hover:text-red-500 transition ml-1">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        {{-- ══ STATEMENT AREA ══ --}}
        <div id="statementArea" class="hidden space-y-4">

            {{-- Party Info + Summary card --}}
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl shadow-sm p-5">
                <div class="flex items-start justify-between flex-wrap gap-4">
                    <div class="flex items-center gap-4">
                        <div id="partyAvatar"
                            class="w-14 h-14 rounded-2xl bg-white/20 flex items-center justify-center text-white text-xl font-bold shadow-inner flex-shrink-0">??</div>
                        <div>
                            <h2 id="partyName" class="text-xl font-bold text-white"></h2>
                            <div class="flex items-center gap-3 mt-1 flex-wrap">
                                <span class="text-blue-100 text-xs flex items-center gap-1">
                                    <i class="fas fa-hashtag text-[10px]"></i><span id="partyIdDisplay"></span>
                                </span>
                                <span id="partyContactWrap" class="hidden text-blue-100 text-xs flex items-center gap-1">
                                    <i class="fas fa-user text-[10px]"></i><span id="partyContactName"></span>
                                </span>
                                <span id="partyPhoneWrap" class="hidden text-blue-100 text-xs flex items-center gap-1">
                                    <i class="fas fa-phone text-[10px]"></i><span id="partyPhone"></span>
                                </span>
                            </div>
                            <div id="partyRoles" class="flex gap-1.5 mt-2 flex-wrap"></div>
                        </div>
                    </div>
                    <div id="statementPeriod" class="text-xs text-blue-200 text-right"></div>
                </div>

                {{-- Summary cards --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4">
                    <div class="bg-white/10 rounded-xl p-3">
                        <p class="text-[10px] text-blue-200 uppercase tracking-wider font-semibold">Opening Balance</p>
                        <p id="summOpeningBal" class="text-lg font-bold text-white mt-0.5">৳0</p>
                        <p id="summOpeningDate" class="text-[10px] text-blue-200 mt-0.5"></p>
                    </div>
                    <div class="bg-white/10 rounded-xl p-3">
                        <p class="text-[10px] text-blue-200 uppercase tracking-wider font-semibold">Total Debit (Sales)</p>
                        <p id="summTotalDebit" class="text-lg font-bold text-white mt-0.5">৳0</p>
                        <p id="summDebitCount" class="text-[10px] text-blue-200 mt-0.5"></p>
                    </div>
                    <div class="bg-white/10 rounded-xl p-3">
                        <p class="text-[10px] text-blue-200 uppercase tracking-wider font-semibold">Total Credit (Received)</p>
                        <p id="summTotalCredit" class="text-lg font-bold text-white mt-0.5">৳0</p>
                        <p id="summCreditCount" class="text-[10px] text-blue-200 mt-0.5"></p>
                    </div>
                    <div class="bg-white/10 rounded-xl p-3 border border-white/30">
                        <p class="text-[10px] text-blue-200 uppercase tracking-wider font-semibold">Closing Balance</p>
                        <p id="summClosingBal" class="text-lg font-bold text-white mt-0.5">৳0</p>
                        <p id="summClosingLabel" class="text-[10px] text-blue-200 mt-0.5"></p>
                    </div>
                </div>
            </div>

            {{-- Ledger Table --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100 flex-wrap gap-2">
                    <div>
                        <p class="text-sm font-bold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-list-alt text-blue-500 text-xs"></i> Transaction Ledger
                        </p>
                        <p id="ledgerSubtitle" class="text-xs text-gray-400 mt-0.5"></p>
                    </div>
                    <div id="ledgerActionBtns" class="flex items-center gap-2">
                        <button onclick="openOpeningBalanceModal()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-slate-600 rounded-lg hover:bg-slate-700 transition shadow-sm">
                            <i class="fas fa-flag text-[10px]"></i> Opening Balance
                        </button>
                        <button onclick="openPaymentModal()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition shadow-sm">
                            <i class="fas fa-plus text-[10px]"></i> Record Payment
                        </button>
                        <button onclick="openInvoiceModal()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm">
                            <i class="fas fa-file-invoice text-[10px]"></i> Invoice
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100">
                                <th class="px-4 py-3 text-left text-[10px] font-bold text-gray-400 uppercase tracking-wider w-24">Date</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold text-gray-400 uppercase tracking-wider">Voucher #</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold text-gray-400 uppercase tracking-wider">Description</th>
                                <th class="px-4 py-3 text-center text-[10px] font-bold text-gray-400 uppercase tracking-wider w-24">Ref</th>
                                <th class="px-4 py-3 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wider w-32">Debit (Dr)</th>
                                <th class="px-4 py-3 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wider w-32">Credit (Cr)</th>
                                <th class="px-4 py-3 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wider w-36">Balance</th>
                            </tr>
                        </thead>
                        <tbody id="ledgerBody"></tbody>
                        <tfoot>
                            <tr id="ledgerFooter" class="hidden bg-blue-700 text-white">
                                <td colspan="4" class="px-4 py-3 text-xs font-bold uppercase tracking-wider">
                                    <i class="fas fa-flag-checkered mr-1.5"></i>
                                    Closing Balance (<span id="footerDate"></span>)
                                </td>
                                <td class="px-4 py-3 text-right text-xs font-bold" id="footerDebit"></td>
                                <td class="px-4 py-3 text-right text-xs font-bold" id="footerCredit"></td>
                                <td class="px-4 py-3 text-right text-sm font-bold" id="footerBalance"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div id="ledgerEmpty" class="hidden py-16 text-center">
                    <i class="fas fa-file-invoice text-gray-200 text-5xl mb-3"></i>
                    <p class="text-gray-400 text-sm font-medium">No transactions found</p>
                    <p class="text-gray-300 text-xs mt-1">for the selected party &amp; date range</p>
                </div>

                <div id="ledgerMeta" class="hidden px-5 py-2.5 border-t border-gray-100 flex items-center justify-between flex-wrap gap-2">
                    <span id="ledgerRowCount" class="text-xs text-gray-400"></span>
                    <div class="flex items-center gap-3">
                        <button class="text-xs text-blue-600 hover:text-blue-700 font-medium flex items-center gap-1 transition">
                            <i class="fas fa-envelope text-[10px]"></i> Email
                        </button>
                        <button class="text-xs text-emerald-600 hover:text-emerald-700 font-medium flex items-center gap-1 transition">
                            <i class="fab fa-whatsapp text-[10px]"></i> WhatsApp
                        </button>
                        <button class="text-xs text-amber-600 hover:text-amber-700 font-medium flex items-center gap-1 transition">
                            <i class="fas fa-bell text-[10px]"></i> Reminder
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ PLACEHOLDER ══ --}}
        <div id="placeholderArea" class="py-20 text-center">
            <div class="w-20 h-20 rounded-2xl bg-blue-50 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-search text-blue-300 text-3xl"></i>
            </div>
            <p class="text-gray-400 text-base font-medium">Search &amp; select a party above</p>
            <p class="text-gray-300 text-sm mt-1">then click <strong>Load</strong> to view their statement</p>
        </div>

    </div>
</div>

{{-- ══════════════════════════════════════════════
     RECORD PAYMENT MODAL
══════════════════════════════════════════════ --}}
<div id="paymentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closePaymentModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md z-10 flex flex-col">

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
        <div class="px-6 py-5 space-y-4">

            {{-- Party (readonly) --}}
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                    Party (Customer / Client / Vendor)
                </label>
                <div id="pmPartyDisplay"
                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-100 text-gray-600 font-medium">
                    —
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                {{-- Amount --}}
                <div class="col-span-2 sm:col-span-1">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                        Amount (৳) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" id="pmAmount" min="0.01" step="0.01" placeholder="0"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-gray-50 transition">
                </div>

                {{-- Mode --}}
                <div class="col-span-2 sm:col-span-1">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Mode</label>
                    <select id="pmMode"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-gray-50 transition">
                        <option value="cash">Cash</option>
                        <option value="bank_transfer" selected>Bank Transfer</option>
                        <option value="cheque">Cheque</option>
                        <option value="mobile_banking">Mobile Banking</option>
                        <option value="online">Online</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                {{-- Bank Account --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Bank Account</label>
                    <select id="pmBank"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-gray-50 transition">
                        <option value="">— Select Account —</option>
                        @foreach($banks as $bank)
                            <option value="{{ $bank->id }}">{{ $bank->name }}
                                @if($bank->account_number) · {{ $bank->account_number }}@endif
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Payment Date --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Payment Date</label>
                    <input type="date" id="pmDate"
                        class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-gray-50 transition">
                </div>
            </div>

            {{-- Reference No --}}
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Reference No. / Cheque / TXN ID</label>
                <input type="text" id="pmRef" placeholder="Cheque / TXN ID…"
                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-gray-50 transition">
            </div>

            {{-- Notes --}}
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Notes</label>
                <textarea id="pmNotes" rows="2" placeholder="Payment purpose, invoice ref…"
                    class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-gray-50 transition resize-none"></textarea>
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

@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script>
const SEARCH_URL  = "{{ route('role.party-statement.search',  ['role' => $role]) }}";
const LOAD_URL    = "{{ route('role.party-statement.load',    ['role' => $role]) }}";
const PAYMENT_URL = "{{ route('role.party-statement.payment', ['role' => $role]) }}";
const INVOICE_URL = "{{ route('role.party-statement.invoice', ['role' => $role]) }}";
const OPENING_BALANCE_URL = "{{ route('role.party-statement.opening-balance', ['role' => $role]) }}";
const EXCEL_URL   = "{{ route('role.party-statement.excel',   ['role' => $role]) }}";
const PDF_URL     = "{{ route('role.party-statement.pdf',     ['role' => $role]) }}";
const INVOICE_PDF_BASE = "/{{ $role }}/party-statement/invoice/";
const TS_DETAIL_BASE = "{{ url($role . '/ticket-sales') }}";
const CSRF = "{{ csrf_token() }}";

let selectedParty = null;
let lastLoadParams = {};
let searchTimer   = null;
const ticketDetailCache = {}; // invoice_id -> rendered HTML, so re-expanding a row doesn't re-fetch

// ── Badge helpers ──────────────────────────────────────────────────────────────
const typeBadge = {
    party_payment: { label: 'Payment',   cls: 'bg-emerald-100 text-emerald-700' },
    bank_transfer:  { label: 'Payment',   cls: 'bg-emerald-100 text-emerald-700' },
    party_invoice:  { label: 'Invoice',   cls: 'bg-blue-100   text-blue-700' },
    ticket_sale:    { label: 'Ticket',    cls: 'bg-blue-100   text-blue-700' },
    flight_sale:    { label: 'Flight',    cls: 'bg-sky-100    text-sky-700' },
    file_sale:      { label: 'File',      cls: 'bg-teal-100   text-teal-700' },
    visa_sale:      { label: 'Visa',      cls: 'bg-purple-100 text-purple-700' },
    sale:           { label: 'Invoice',   cls: 'bg-blue-100   text-blue-700' },
    purchase:       { label: 'Purchase',  cls: 'bg-violet-100 text-violet-700' },
    adjustment:     { label: 'Adj.',      cls: 'bg-amber-100  text-amber-700' },
    opening_balance:{ label: 'Opening',   cls: 'bg-slate-100  text-slate-700' },
};
function txBadge(type) {
    const b = typeBadge[type] || { label: type.replace(/_/g,' '), cls: 'bg-gray-100 text-gray-600' };
    return `<span class="inline-flex px-2 py-0.5 text-[10px] font-semibold rounded-full ${b.cls} capitalize">${b.label}</span>`;
}

// ── Ticket sale passenger/route/ticket breakdown (on-demand) ────────────────────
// Same shape as the "Manage Sales" invoice modal (tsShowDetail) — reused here via
// the ticket-sales detail endpoint so passenger/route data isn't duplicated.
function toggleTicketDetail(txId, invoiceId) {
    const row  = document.getElementById('tx-detail-' + txId);
    const body = document.getElementById('tx-detail-body-' + txId);
    const btn  = document.getElementById('tx-toggle-' + txId);
    if (!row) return;

    const willShow = row.classList.contains('hidden');
    row.classList.toggle('hidden');
    if (btn) {
        const icon = btn.querySelector('i');
        icon.classList.toggle('fa-chevron-down', !willShow);
        icon.classList.toggle('fa-chevron-up', willShow);
    }
    if (!willShow) return; // just collapsed — nothing more to do

    if (ticketDetailCache[invoiceId]) {
        body.innerHTML = ticketDetailCache[invoiceId];
        return;
    }

    body.innerHTML = '<div class="text-xs text-gray-400 py-2"><i class="fas fa-spinner fa-spin mr-1"></i>Loading passengers…</div>';

    $.ajax({
        url: TS_DETAIL_BASE + '/' + invoiceId + '/detail',
        method: 'GET',
        dataType: 'json',
        success: function (d) {
            if (!d.success) { body.innerHTML = '<div class="text-xs text-red-400 py-2">Failed to load ticket details.</div>'; return; }

            var rows = (d.items || []).map(function (it) {
                var route = (it.from || it.to) ? ((it.from || '—') + ' → ' + (it.to || '—')) : '—';
                return '<tr class="border-b border-blue-100/60 last:border-0">'
                    + '<td class="py-1.5 pl-3 pr-3 text-xs"><span class="font-mono font-bold text-blue-700">' + (it.ticket_no || '—') + '</span><br><span class="text-gray-500">' + (it.passenger || '—') + '</span></td>'
                    + '<td class="py-1.5 pr-3 text-xs text-gray-600">' + (it.route || '—') + '</td>'
                    + '<td class="py-1.5 pr-3 text-xs text-gray-600">' + route + '</td>'
                    + '<td class="py-1.5 pr-3 text-xs text-right font-semibold text-gray-700">' + (d.currency || '৳') + Number(it.price).toLocaleString() + '</td>'
                    + '</tr>';
            }).join('');

            var html = ''
                + '<div class="rounded-lg border border-blue-100 overflow-hidden bg-white">'
                + '  <table class="w-full">'
                + '    <thead><tr class="bg-blue-50">'
                + '      <th class="py-1.5 pl-3 pr-3 text-left text-[10px] font-semibold text-blue-700 uppercase">PNR / Passenger</th>'
                + '      <th class="py-1.5 pr-3 text-left text-[10px] font-semibold text-blue-700 uppercase">Ticket</th>'
                + '      <th class="py-1.5 pr-3 text-left text-[10px] font-semibold text-blue-700 uppercase">Route</th>'
                + '      <th class="py-1.5 pr-3 text-right text-[10px] font-semibold text-blue-700 uppercase">Price</th>'
                + '    </tr></thead>'
                + '    <tbody>' + (rows || '<tr><td colspan="4" class="text-center text-gray-400 text-xs py-3">No ticket items found.</td></tr>') + '</tbody>'
                + '  </table>'
                + '</div>';

            ticketDetailCache[invoiceId] = html;
            body.innerHTML = html;
        },
        error: function () {
            body.innerHTML = '<div class="text-xs text-red-400 py-2">Failed to load ticket details.</div>';
        }
    });
}
const roleCls = {
    vendor:   'bg-violet-100 text-violet-700',
    agent:    'bg-amber-100  text-amber-700',
    customer: 'bg-blue-100   text-blue-700',
};

// ── Formatters ─────────────────────────────────────────────────────────────────
function fmtDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' });
}
function fmtMoney(v) {
    return '৳ ' + Math.abs(v).toLocaleString('en-BD', { minimumFractionDigits: 0 });
}

// ── Date preset ────────────────────────────────────────────────────────────────
function resolveDates() {
    const preset = document.getElementById('datePreset').value;
    const now    = new Date();
    let from = '', to = now.toISOString().split('T')[0];
    if      (preset === 'this_year')  from = now.getFullYear() + '-01-01';
    else if (preset === 'this_month') from = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + '-01';
    else if (preset === 'last_3')     { const d = new Date(now); d.setMonth(d.getMonth()-3); from = d.toISOString().split('T')[0]; }
    else if (preset === 'last_6')     { const d = new Date(now); d.setMonth(d.getMonth()-6); from = d.toISOString().split('T')[0]; }
    else if (preset === 'all_time')   { from = ''; to = ''; }
    else { from = document.getElementById('dateFrom').value; to = document.getElementById('dateTo').value; }
    return { from, to };
}

document.getElementById('datePreset').addEventListener('change', function () {
    document.getElementById('customDateRow').classList.toggle('hidden', this.value !== 'custom');
});
function resolveType() {
    return document.getElementById('txnTypeFilter').value;
}
document.getElementById('txnTypeFilter').addEventListener('change', function () {
    if (selectedParty) document.getElementById('loadBtn').click();
});

// ── Party search ───────────────────────────────────────────────────────────────
document.getElementById('partySearch').addEventListener('input', function () {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    if (q.length < 1) { closeDropdown(); return; }
    searchTimer = setTimeout(() => doSearch(q), 280);
});
document.getElementById('partyType').addEventListener('change', () => {
    const q = document.getElementById('partySearch').value.trim();
    if (q.length >= 1) doSearch(q);
});

function doSearch(q) {
    const type = document.getElementById('partyType').value;
    fetch(`${SEARCH_URL}?q=${encodeURIComponent(q)}&type=${type}`)
        .then(r => r.json()).then(renderDropdown);
}
function renderDropdown(parties) {
    const dd = document.getElementById('partyDropdown');
    if (!parties.length) {
        dd.innerHTML = '<p class="px-4 py-3 text-xs text-gray-400">No parties found</p>';
        dd.classList.remove('hidden'); return;
    }
    dd.innerHTML = parties.map(p => {
        const roles = p.roles.map(r => `<span class="px-1.5 py-0.5 text-[9px] font-bold rounded-full ${roleCls[r]||'bg-gray-100 text-gray-500'}">${r}</span>`).join(' ');
        return `<div class="px-4 py-2.5 hover:bg-blue-50 cursor-pointer flex items-center gap-3 border-b border-gray-50 last:border-0 transition"
                     onclick='selectParty(${JSON.stringify(p)})'>
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">${p.initials}</div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-800 truncate">${p.name}</p>
                <p class="text-xs text-gray-400">${p.phone||p.email||'No contact'}</p>
            </div>
            <div class="flex gap-1 flex-shrink-0">${roles}</div>
        </div>`;
    }).join('');
    dd.classList.remove('hidden');
}
function closeDropdown() { document.getElementById('partyDropdown').classList.add('hidden'); }
function selectParty(p) {
    selectedParty = p;
    document.getElementById('partySearch').value = p.name;
    document.getElementById('chipName').textContent = p.name;
    document.getElementById('selectedPartyChip').classList.remove('hidden');
    document.getElementById('loadBtn').disabled = false;
    closeDropdown();
}
function clearParty() {
    selectedParty = null;
    document.getElementById('partySearch').value = '';
    document.getElementById('selectedPartyChip').classList.add('hidden');
    document.getElementById('loadBtn').disabled  = true;
    document.getElementById('pdfBtn').disabled   = true;
    document.getElementById('excelBtn').disabled = true;
    document.getElementById('printBtn').disabled = true;
    document.getElementById('statementArea').classList.add('hidden');
    document.getElementById('placeholderArea').classList.remove('hidden');
}
document.addEventListener('click', e => {
    if (!e.target.closest('#partySearch') && !e.target.closest('#partyDropdown')) closeDropdown();
});

// ── Load statement ─────────────────────────────────────────────────────────────
document.getElementById('loadBtn').addEventListener('click', function () {
    if (!selectedParty) return;
    const { from, to } = resolveDates();
    const type = resolveType();
    lastLoadParams = { party_id: selectedParty.id, from, to, type };
    const icon = document.getElementById('loadIcon');
    icon.classList.add('fa-spin');
    this.disabled = true;

    let url = `${LOAD_URL}?party_id=${selectedParty.id}`;
    if (from) url += `&from=${from}`;
    if (to)   url += `&to=${to}`;
    if (type && type !== 'all') url += `&type=${type}`;

    fetch(url).then(r => r.json())
        .then(data => { renderStatement(data); document.getElementById('pdfBtn').disabled = false; document.getElementById('excelBtn').disabled = false; document.getElementById('printBtn').disabled = false; })
        .catch(() => Swal.fire({ icon:'error', title:'Error', text:'Failed to load statement.' }))
        .finally(() => { icon.classList.remove('fa-spin'); this.disabled = false; });
});

// ── Render statement ───────────────────────────────────────────────────────────
function renderStatement(data) {
    const { party, opening_balance, total_debit, total_credit, closing_balance, transactions, date_from, date_to } = data;

    document.getElementById('partyAvatar').textContent    = party.initials;
    document.getElementById('partyName').textContent      = party.name;
    document.getElementById('partyIdDisplay').textContent = 'ID-' + String(party.id).padStart(4,'0');

    const setField = (wrapId, spanId, val) => {
        if (val) { document.getElementById(spanId).textContent = val; document.getElementById(wrapId).classList.remove('hidden'); }
        else     document.getElementById(wrapId).classList.add('hidden');
    };
    setField('partyContactWrap', 'partyContactName', party.contact_person);
    setField('partyPhoneWrap',   'partyPhone',        party.phone);

    document.getElementById('partyRoles').innerHTML = party.roles.map(r =>
        `<span class="px-2 py-0.5 text-[10px] font-bold rounded-full ${roleCls[r]||'bg-white/20 text-white'} capitalize">${r}</span>`
    ).join('');
    document.getElementById('statementPeriod').innerHTML =
        `<span class="text-blue-200 text-xs">${date_from ? fmtDate(date_from)+' → '+fmtDate(date_to) : 'All Time'}</span>`;

    document.getElementById('summOpeningBal').textContent  = fmtMoney(opening_balance);
    document.getElementById('summOpeningDate').textContent = date_from ? fmtDate(date_from) : '';
    document.getElementById('summTotalDebit').textContent  = fmtMoney(total_debit);
    document.getElementById('summDebitCount').textContent  = transactions.filter(t=>t.debit>0).length + ' invoices';
    document.getElementById('summTotalCredit').textContent = fmtMoney(total_credit);
    document.getElementById('summCreditCount').textContent = transactions.filter(t=>t.credit>0).length + ' payments';
    document.getElementById('summClosingBal').textContent  = fmtMoney(closing_balance);
    document.getElementById('summClosingLabel').textContent = closing_balance>0 ? 'Receivable (Dr)' : closing_balance<0 ? 'Payable (Cr)' : 'Settled';

    document.getElementById('ledgerSubtitle').textContent =
        (date_from ? fmtDate(date_from)+' to '+(date_to?fmtDate(date_to):'today') : 'All time') + ' — newest first';

    const tbody = document.getElementById('ledgerBody');
    tbody.innerHTML = '';

    if (!transactions.length) {
        document.getElementById('ledgerEmpty').classList.remove('hidden');
        document.getElementById('ledgerFooter').classList.add('hidden');
        document.getElementById('ledgerMeta').classList.add('hidden');
    } else {
        document.getElementById('ledgerEmpty').classList.add('hidden');

        // Newest first, the house default for every transaction table on
        // screen. The server still sends them oldest-first — each row's running
        // balance is only meaningful in that order, and the Excel/PDF exports
        // read the same feed and stay chronological — so this reverses a copy
        // for display only. slice() first: reverse() mutates, and the caller's
        // array is the one the summary counts above were taken from.
        //
        // The Opening Balance row is written after the loop for the same reason:
        // brought-forward now sits below the oldest entry, where the list ends.
        const openingRow = opening_balance !== 0 ? `
            <tr class="border-b border-gray-50 bg-blue-50/40">
                <td class="px-4 py-2.5 text-xs text-gray-400 italic">${date_from ? fmtDate(date_from) : ''}</td>
                <td class="px-4 py-2.5 text-xs text-gray-400">—</td>
                <td class="px-4 py-2.5 text-xs text-gray-500 italic" colspan="2">Opening Balance Brought Forward</td>
                <td class="px-4 py-2.5 text-right text-xs text-gray-400">—</td>
                <td class="px-4 py-2.5 text-right text-xs text-gray-400">—</td>
                <td class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600">${fmtMoney(opening_balance)} Dr</td>
            </tr>` : '';

        // The newest entry, identified before the flip — it is the one that
        // carries the live balance, and it is now the first row on screen.
        const latestTx = transactions[transactions.length - 1];

        transactions.slice().reverse().forEach((t) => {
            const isDebit  = t.debit > 0;
            const isCredit = t.credit > 0;
            const bal      = t.balance;
            const balCls   = bal > 0 ? 'text-red-600' : bal < 0 ? 'text-emerald-600' : 'text-gray-400';
            const balSign  = bal > 0 ? 'Dr' : bal < 0 ? 'Cr' : '';
            const isLatest = (t === latestTx);

            // Invoice PDF link for party_invoice type
            const invPdfLink = t.type === 'party_invoice' && t.invoice_id
                ? `<a href="${INVOICE_PDF_BASE}${t.invoice_id}/pdf" target="_blank"
                       class="ml-1.5 text-[10px] text-blue-500 hover:text-blue-700 transition" title="View Invoice PDF">
                       <i class="fas fa-file-pdf"></i></a>`
                : '';

            // Ticket sales bundle multiple passengers/routes into one ledger row —
            // expose them via an on-demand expandable row instead of a bulky remarks string.
            const isTicketSale = t.type === 'ticket_sale' && t.invoice_id;
            const ticketToggle = isTicketSale
                ? `<button type="button" id="tx-toggle-${t.id}" onclick="toggleTicketDetail(${t.id}, ${t.invoice_id})"
                       class="ml-1.5 text-[10px] text-blue-600 hover:text-blue-800 font-semibold inline-flex items-center gap-0.5 align-middle">
                       <i class="fas fa-chevron-down text-[9px]"></i> Passengers</button>`
                : '';

            tbody.innerHTML += `
            <tr class="border-b border-gray-50 hover:bg-gray-50/60 transition ${isLatest ? 'bg-amber-50/30' : ''}">
                <td class="px-4 py-3 text-xs text-gray-500">${fmtDate(t.payment_date)}</td>
                <td class="px-4 py-3 text-xs font-mono text-blue-600">${t.reference_no||'—'}</td>
                <td class="px-4 py-3 text-sm text-gray-700">${t.remarks||'—'}${isLatest ? ' <span class="ml-1 text-[10px] text-amber-500 font-semibold">● Live</span>' : ''}${ticketToggle}</td>
                <td class="px-4 py-3 text-center">${txBadge(t.type)}${invPdfLink}</td>
                <td class="px-4 py-3 text-right text-sm font-semibold ${isDebit  ? 'text-red-500'     : 'text-gray-300'}">${isDebit  ? fmtMoney(t.debit)  : '—'}</td>
                <td class="px-4 py-3 text-right text-sm font-semibold ${isCredit ? 'text-emerald-600' : 'text-gray-300'}">${isCredit ? fmtMoney(t.credit) : '—'}</td>
                <td class="px-4 py-3 text-right text-sm font-bold ${balCls}">${fmtMoney(bal)} ${balSign}</td>
            </tr>`;

            if (isTicketSale) {
                tbody.innerHTML += `
                <tr id="tx-detail-${t.id}" class="hidden bg-blue-50/30 border-b border-gray-50">
                    <td colspan="7" class="px-4 py-0">
                        <div id="tx-detail-body-${t.id}" class="py-3"></div>
                    </td>
                </tr>`;
            }
        });

        tbody.innerHTML += openingRow;

        document.getElementById('ledgerFooter').classList.remove('hidden');
        document.getElementById('footerDate').textContent    = date_to ? fmtDate(date_to) : new Date().toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'});
        document.getElementById('footerDebit').textContent   = fmtMoney(total_debit);
        document.getElementById('footerCredit').textContent  = fmtMoney(total_credit);
        document.getElementById('footerBalance').textContent = fmtMoney(closing_balance) + (closing_balance>0?' Dr':closing_balance<0?' Cr':'');

        document.getElementById('ledgerMeta').classList.remove('hidden');
        document.getElementById('ledgerRowCount').textContent = `${transactions.length} entries · ${transactions.filter(t=>t.credit>0).length} payments`;
    }

    document.getElementById('statementArea').classList.remove('hidden');
    document.getElementById('placeholderArea').classList.add('hidden');
}

// ── Export helpers ─────────────────────────────────────────────────────────────
function exportPdf() {
    if (!selectedParty) return;
    const { from, to } = resolveDates();
    const type = resolveType();
    let url = `${PDF_URL}?party_id=${selectedParty.id}`;
    if (from) url += `&from=${from}`;
    if (to)   url += `&to=${to}`;
    if (type && type !== 'all') url += `&type=${type}`;
    window.open(url, '_blank');
}
function exportExcel() {
    if (!selectedParty) return;
    const { from, to } = resolveDates();
    const type = resolveType();
    let url = `${EXCEL_URL}?party_id=${selectedParty.id}`;
    if (from) url += `&from=${from}`;
    if (to)   url += `&to=${to}`;
    if (type && type !== 'all') url += `&type=${type}`;
    window.location.href = url;
}

// ── Payment modal ──────────────────────────────────────────────────────────────
function openPaymentModal() {
    if (!selectedParty) return;
    document.getElementById('pmPartyDisplay').textContent = selectedParty.name;
    document.getElementById('pmAmount').value  = '';
    document.getElementById('pmMode').value    = 'bank_transfer';
    document.getElementById('pmBank').value    = '';
    document.getElementById('pmRef').value     = '';
    document.getElementById('pmNotes').value   = '';
    document.getElementById('pmDate').value    = new Date().toISOString().split('T')[0];
    document.getElementById('paymentModal').classList.remove('hidden');
}
function closePaymentModal() { document.getElementById('paymentModal').classList.add('hidden'); }

function submitPayment() {
    const amount = document.getElementById('pmAmount').value;
    if (!amount || parseFloat(amount) <= 0) { alert('Please enter a valid amount.'); return; }

    const btn = document.getElementById('pmSubmit');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i> Saving…';

    fetch(PAYMENT_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({
            party_id:       selectedParty.id,
            amount:         amount,
            payment_method: document.getElementById('pmMode').value,
            bank_id:        document.getElementById('pmBank').value,
            payment_date:   document.getElementById('pmDate').value,
            reference_no:   document.getElementById('pmRef').value,
            notes:          document.getElementById('pmNotes').value,
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            closePaymentModal();
            Swal.fire({ icon:'success', title:'Payment Recorded', text: res.message, timer:1800, showConfirmButton:false });
            setTimeout(() => document.getElementById('loadBtn').click(), 1900);
        } else {
            Swal.fire({ icon:'error', title:'Error', text: res.message||'Failed to record payment.' });
        }
    })
    .catch(() => Swal.fire({ icon:'error', title:'Error', text:'Network error.' }))
    .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check text-xs"></i> Record Payment'; });
}

// ── Invoice modal ──────────────────────────────────────────────────────────────
function openInvoiceModal() {
    if (!selectedParty) return;
    document.getElementById('invPartyDisplay').textContent = selectedParty.name;
    document.getElementById('invDesc').value       = '';
    document.getElementById('invAmount').value     = '';
    document.getElementById('invDue').value        = '';
    document.getElementById('invCountry').value    = '';
    document.getElementById('invCandidates').value = '';
    document.getElementById('invNotes').value      = '';
    document.getElementById('invoiceModal').classList.remove('hidden');
}
function closeInvoiceModal() { document.getElementById('invoiceModal').classList.add('hidden'); }

function submitInvoice() {
    const desc   = document.getElementById('invDesc').value.trim();
    const amount = document.getElementById('invAmount').value;
    if (!desc)                        { alert('Please enter a service description.'); return; }
    if (!amount || parseFloat(amount) <= 0) { alert('Please enter a valid amount.'); return; }

    const btn = document.getElementById('invSubmit');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i> Creating…';

    fetch(INVOICE_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({
            party_id:            selectedParty.id,
            service_description: desc,
            amount:              amount,
            due_date:            document.getElementById('invDue').value,
            country_id:          document.getElementById('invCountry').value,
            no_of_candidates:    document.getElementById('invCandidates').value,
            notes:               document.getElementById('invNotes').value,
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            closeInvoiceModal();
            Swal.fire({ icon:'success', title:'Invoice Created', text: res.message, timer:1800, showConfirmButton:false });
            setTimeout(() => document.getElementById('loadBtn').click(), 1900);
        } else {
            Swal.fire({ icon:'error', title:'Error', text: res.message||'Failed to create invoice.' });
        }
    })
    .catch(() => Swal.fire({ icon:'error', title:'Error', text:'Network error.' }))
    .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-file-invoice text-xs"></i> Create Invoice'; });
}

// ── Opening balance modal ──────────────────────────────────────────────────────
function openOpeningBalanceModal() {
    if (!selectedParty) return;
    document.getElementById('obPartyDisplay').textContent = selectedParty.name;
    document.getElementById('obAmount').value    = '';
    document.getElementById('obDirection').value = 'receivable';
    document.getElementById('obDate').value      = new Date().toISOString().split('T')[0];
    document.getElementById('obNote').value      = '';
    document.getElementById('obModal').classList.remove('hidden');
}
function closeOpeningBalanceModal() { document.getElementById('obModal').classList.add('hidden'); }

function submitOpeningBalance() {
    const amount = document.getElementById('obAmount').value;
    const asOfDate = document.getElementById('obDate').value;
    if (!amount || parseFloat(amount) <= 0) { alert('Please enter a valid amount.'); return; }
    if (!asOfDate) { alert('Please select an as-of date.'); return; }

    const btn = document.getElementById('obSubmit');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i> Saving…';

    fetch(OPENING_BALANCE_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({
            party_id:   selectedParty.id,
            amount:     amount,
            direction:  document.getElementById('obDirection').value,
            as_of_date: asOfDate,
            note:       document.getElementById('obNote').value,
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            closeOpeningBalanceModal();
            Swal.fire({ icon:'success', title:'Opening Balance Set', text: res.message, timer:1800, showConfirmButton:false });
            setTimeout(() => document.getElementById('loadBtn').click(), 1900);
        } else {
            Swal.fire({ icon:'error', title:'Error', text: res.message||'Failed to set opening balance.' });
        }
    })
    .catch(() => Swal.fire({ icon:'error', title:'Error', text:'Network error.' }))
    .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-flag text-xs"></i> Set Opening Balance'; });
}

// Escape closes all modals
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closePaymentModal(); closeInvoiceModal(); closeOpeningBalanceModal(); }
});

// ── Deep link: auto-load a party's ledger when arriving with ?party_id= ────────
@if($autoPartyId)
(function () {
    const { from, to } = resolveDates();
    lastLoadParams = { party_id: {{ $autoPartyId }}, from, to };
    let url = `${LOAD_URL}?party_id={{ $autoPartyId }}`;
    if (from) url += `&from=${from}`;
    if (to)   url += `&to=${to}`;

    fetch(url).then(r => r.json()).then(data => {
        selectedParty = {
            id: data.party.id, name: data.party.name, phone: data.party.phone,
            email: data.party.email, contact_person: data.party.contact_person,
            roles: data.party.roles, initials: data.party.initials,
        };
        document.getElementById('partySearch').value = selectedParty.name;
        document.getElementById('chipName').textContent = selectedParty.name;
        document.getElementById('selectedPartyChip').classList.remove('hidden');
        document.getElementById('loadBtn').disabled = false;
        document.getElementById('pdfBtn').disabled = false;
        document.getElementById('excelBtn').disabled = false;
        document.getElementById('printBtn').disabled = false;
        renderStatement(data);
    }).catch(() => Swal.fire({ icon:'error', title:'Error', text:'Failed to load statement.' }));
})();
@endif
</script>
@endsection
