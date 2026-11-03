{{-- This shared ledger owns its controls. Department-wide automatic Select2
     enhancement must not restyle them when embedded in Ticket/Flight/Visa accounts. --}}
@section('shared-party-ledger', '1')
        <style>
            @include('party-statement.partials.bank-ledger-header-styles')
            #partyName { font-size: 1.42rem; font-weight: 800; letter-spacing: -0.021em; line-height: 1.12; margin: 0; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            #partyAvatar { font-size: 1.75rem; font-weight: 800; color: #fff; }
            #statementArea .lh-zone-id { justify-content: flex-start; }
            #statementArea .bank-btn { display: flex; align-items: center; justify-content: center; gap: 4px; padding: 8px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 600; border: none; cursor: pointer; }
            #statementArea .bank-btn-statement { background: #eef2ff; color: #4338ca; }
            #statementArea .bank-btn-deposit { background: #dcfce7; color: #15803d; }
            #statementArea .bank-btn-statement:hover { background: #c7d2fe; }
            #statementArea .bank-btn-deposit:hover { background: #bbf7d0; }
            #statementArea .ps-recent thead th { background: #f8fafc; color: #64748b; border-bottom-color: #e2e8f0; padding-top: 14px; padding-bottom: 14px; }
        </style>
        <div id="searchFilterBar" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                <i class="fas fa-search text-blue-500"></i> {{ isset($meta) ? "Find " . $meta["label"] : "Select Party" }}
            </p>
            <div class="flex flex-wrap gap-3 items-end">
                {{-- Party search --}}
                <div class="flex-1 min-w-[220px] relative">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input type="text" id="partySearch" placeholder="{{ isset($meta) ? "Search " . strtolower($meta["plural"]) . " by name, phone or code…" : "Search by name, phone or ID…" }}"
                            autocomplete="off"
                            class="w-full pl-9 pr-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 transition">
                    </div>
                    <div id="partyDropdown"
                        class="hidden absolute z-30 bg-white border border-gray-200 rounded-xl shadow-xl mt-1 w-full max-h-64 overflow-y-auto"></div>
                </div>

                {{-- Type filter. On a ledger page the type is the page, so the
                     select is not offered — LOCKED_PARTY_TYPE in scripts carries it. --}}
                @empty($type)
                <select id="partyType" class="px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 transition">
                    <option value="all">All Parties</option>
                    <option value="agent">Agent Only</option>
                    <option value="vendor">Vendor Only</option>
                    <option value="client">Customer Only</option>
                </select>
                @endempty

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
                {{-- Hidden, not removed: resolveDates() reads these, and the
                     statement's table bar writes them. --}}
                <select id="datePreset" hidden class="px-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 transition">
                    <option value="this_year">This Year</option>
                    <option value="this_month">This Month</option>
                    <option value="last_3">Last 3 Months</option>
                    <option value="last_6">Last 6 Months</option>
                    <option value="all_time">All Time</option>
                    <option value="custom">Custom Range</option>
                </select>

                {{-- Custom date row --}}
                <div id="customDateRow" hidden class="hidden flex gap-2">
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
                <button id="printBtn" onclick="psPrintOpen('st')" disabled
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
        <div id="statementArea" class="hidden" style="scroll-margin-top: 80px; --ps-detail-accent:{{ $accent['fg'] }}; --ps-detail-soft:{{ $accent['bg'] }};">

            {{-- Party Info + Summary card --}}
            <div class="ledger-header">
                <div class="ledger-head-top">
                    <div class="lh-zone lh-zone-id">
                        <span id="partyAvatar" class="ledger-avatar is-mono">??</span>
                        <div class="lh-idcol">
                            <h2 id="partyName"></h2>
                            <div class="lh-line"><span class="lh-k">ID</span><span class="lh-ac" id="partyIdDisplay"></span><span id="partyRoles"></span></div>
                            <div id="partyContactWrap" class="lh-line hidden"><i class="fas fa-user"></i><span id="partyContactName"></span></div>
                            <div id="partyPhoneWrap" class="lh-line hidden"><i class="fas fa-phone"></i><span id="partyPhone"></span></div>
                        </div>
                    </div>
                    <div class="lh-zone lh-zone-co">
                        <span class="lh-lbl" id="partyBalanceLabel">Receivable</span>
                        <span class="lh-fig" id="partyCurrentBalance">—</span>
                        <span class="lh-lbl">Statement Period</span>
                        <span class="lh-line" id="statementPeriod"></span>
                    </div>
                    <div class="lh-zone lh-zone-last">
                        <span class="lh-lbl">Latest Transaction</span>
                        <div class="lh-last-row">
                            <span class="lh-pill lh-pill-none" id="partyLastPill"><i class="fas fa-minus"></i><span id="partyLastDirection">None</span></span>
                            <span class="lh-last-amt" id="partyLastAmount">—</span>
                        </div>
                        <div class="lh-last-note" id="partyLastPurpose">—</div>
                        <div class="lh-last-when" id="partyLastDate">—</div>
                    </div>
                    <div id="ledgerActionBtns" class="lh-actions">
                        <button onclick="openOpeningBalanceModal()" class="bank-btn bank-btn-statement"><i class="fas fa-flag"></i> Opening Balance</button>
                        <button onclick="openPaymentModal()" class="bank-btn bank-btn-deposit"><i class="fas fa-plus"></i> Record Payment</button>
                        @if(auth()->user()?->is_super_admin && ($type ?? null) !== 'vendor')
                            <button onclick="openDiscountModal()" class="bank-btn bank-btn-statement"><i class="fas fa-tag"></i> Give Discount</button>
                        @endif
                        {{-- Vendors only: an airline refund on a cancelled ticket paid back to us (2026-09-28). --}}
                        @if(($type ?? null) === 'vendor' && \Illuminate\Support\Facades\Route::has('role.party-statement.vendor-refund'))
                            <button onclick="openPaymentModal('refund')" class="bank-btn bank-btn-statement"><i class="fas fa-rotate-left"></i> Receive Refund</button>
                        @endif
                        <button onclick="openInvoiceWindow()" class="bank-btn bank-btn-statement"><i class="fas fa-file-invoice"></i> Invoice</button>
                    </div>
                </div>
                <div class="ledger-meta">
                    <div><span class="lh-lbl"><i class="fas fa-arrow-up"></i>Debit Entries</span><span class="lh-fig" id="partyDebitEntries">—</span></div>
                    <div><span class="lh-lbl"><i class="fas fa-arrow-down"></i>Credit Entries</span><span class="lh-fig" id="partyCreditEntries">—</span></div>
                    <div><span class="lh-lbl"><i class="fas fa-list-ul"></i>Ledger Entries</span><span class="lh-fig" id="partyTxnCount">—</span></div>
                    <div><span class="lh-lbl"><i class="fas fa-flag"></i>Opening Balance</span><span class="lh-fig" id="summOpeningBal">—</span><span id="summOpeningDate" class="hidden"></span></div>
                    <div class="is-out"><span class="lh-lbl"><i class="fas fa-arrow-up"></i><span id="summDebitLabel">Total Charged</span></span><span class="lh-fig" id="summTotalDebit">—</span><span id="summDebitCount" class="hidden"></span></div>
                    <div class="is-in"><span class="lh-lbl"><i class="fas fa-arrow-down"></i><span id="summCreditLabel">Total Received</span></span><span class="lh-fig" id="summTotalCredit">—</span><span id="summCreditCount" class="hidden"></span></div>
                    <div class="is-close"><span class="lh-lbl"><i class="fas fa-flag-checkered"></i><span id="partyClosingLabel">Receivable</span></span><span class="lh-fig" id="summClosingBal">—</span><span id="summClosingLabel" class="hidden"></span></div>
                </div>
            </div>

            <div id="partyDiscountTotal" class="text-sm text-violet-700 mb-2"></div>
            {{-- Ledger Table --}}
            <div class="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
                <span id="ledgerSubtitle" class="hidden"></span>

                {{-- The bank statement's bar, on top of the headings. A row
                     search over the loaded statement; the range segment and
                     dates re-ask the server (Opening Balance and the totals are
                     the period's, so only the server can recompute them) by
                     driving the hidden preset boxes above and pressing Load —
                     one code path fetches a statement. --}}
                @include('party-statement.partials.table-bar', [
                    'prefix'      => 'st',
                    'placeholder' => 'Search description, voucher, type, bank, date or amount…',
                    'segments'    => [['today', 'Today'], ['yesterday', 'Yesterday'], ['week', 'Last Week'], ['month', 'This Month'], ['year', 'This Year'], ['all', 'All Time']],
                    'dates'       => true,
                    'print'       => true,
                ])
                {{-- The dashboard feed's table, column for column — the same
                     eight headings, the same widths (partials/feed-table-styles)
                     and the same cells (owner, 2026-09-16: "it should be fully
                     the same as the dashboard's table"). Party stays even though
                     the party is the page: it carries the movement badge, the
                     code and this row's opening → closing, as on the feed. --}}
                <div style="overflow-x:auto">
                    <table class="ps-recent">
                        <thead>
                            <tr>
                                <th class="ps-pick"><input type="checkbox" class="ps-pick-box ps-pick-all" title="Tick every row shown" aria-label="Select all shown"></th>
                                <th class="ps-date ps-c-date">Date</th><th class="ps-c-party">Party</th><th class="ps-c-entry">Entry</th><th class="ps-details ps-c-details">Details</th><th class="ps-c-via">Paid via</th>
                                <th class="ps-c-dr" style="text-align:right">Debit</th><th class="ps-c-cr" style="text-align:right">Credit</th><th class="ps-c-bal" style="text-align:right">Balance</th>
                            </tr>
                        </thead>
                        <tbody id="ledgerBody"></tbody>
                        <tfoot>
                            {{-- Same gradient as the party header card (bank-ledger-header-styles), so the
                                 sheet opens and closes in one colour. --}}
                            <tr id="ledgerFooter" class="hidden" style="background:linear-gradient(135deg, #4338ca 0%, #5b4ce0 45%, #6d28d9 100%);color:#fff;">
                                <td class="ps-pick"></td>
                                <td colspan="5" style="padding:11px 12px;font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;">
                                    <i class="fas fa-flag-checkered" style="margin-right:6px;"></i>
                                    Closing Balance (<span id="footerDate"></span>)
                                </td>
                                <td style="padding:11px 12px;text-align:right;font-size:0.75rem;font-weight:700;" id="footerDebit"></td>
                                <td style="padding:11px 12px;text-align:right;font-size:0.75rem;font-weight:700;" id="footerCredit"></td>
                                <td style="padding:11px 12px;text-align:right;font-weight:800;font-family:monospace;white-space:nowrap;" id="footerBalance"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div id="partyLedgerPager" style="display:none;padding:12px 14px;border-top:1px solid #e2e8f0;background:#f8fafc;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap"></div>
                <div id="ledgerEmpty" class="hidden py-16 text-center">
                    <i class="fas fa-file-invoice text-gray-200 text-5xl mb-3"></i>
                    <p class="text-gray-400 text-sm font-medium">No transactions found</p>
                    <p class="text-gray-300 text-xs mt-1">for the selected party &amp; date range</p>
                </div>

                <div id="ledgerMeta" class="hidden px-5 py-2.5 border-t border-gray-100 flex items-center justify-between flex-wrap gap-2">
                    <span id="ledgerRowCount" class="text-xs text-gray-400"></span>
                    {{-- Send the loaded statement to the party (scripts.blade: psSend*).
                         Email posts the range shown, with the PDF; WhatsApp opens
                         wa.me with a balance summary (a link cannot carry a file);
                         SMS posts only to the SMS gateway. Each channel is an
                         independent action. --}}
                    <div class="flex items-center gap-1.5">
                        <button type="button" id="psEmailBtn" onclick="psSendStatement()" class="cursor-pointer text-sm text-blue-600 hover:text-blue-800 hover:bg-blue-50 hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-blue-200 rounded-md px-2.5 py-1.5 font-semibold flex items-center gap-1.5 transition-all duration-150 disabled:opacity-50 disabled:cursor-wait disabled:hover:translate-y-0" title="Email this statement to the party with the PDF attached">
                            <i class="fas fa-envelope text-xs"></i> Email
                        </button>
                        <button type="button" id="psWhatsAppBtn" onclick="psWhatsApp()" class="cursor-pointer text-sm text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-emerald-200 rounded-md px-2.5 py-1.5 font-semibold flex items-center gap-1.5 transition-all duration-150" title="Open WhatsApp with a balance summary for the party">
                            <i class="fab fa-whatsapp text-xs"></i> WhatsApp
                        </button>
                        <button type="button" id="psReminderBtn" onclick="psSendReminder()" class="cursor-pointer text-sm text-amber-600 hover:text-amber-800 hover:bg-amber-50 hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-amber-200 rounded-md px-2.5 py-1.5 font-semibold flex items-center gap-1.5 transition-all duration-150 disabled:opacity-50 disabled:cursor-wait disabled:hover:translate-y-0" title="Send this statement summary by SMS only">
                            <i class="fas fa-comment-sms text-xs"></i> SMS
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
            <p class="text-gray-400 text-base font-medium">{{ isset($meta) ? "Pick " . strtolower($meta["label"]) . " from the list above, or search" : "Search & select a party above" }}</p>
            <p class="text-gray-300 text-sm mt-1">their statement opens here</p>
        </div>
