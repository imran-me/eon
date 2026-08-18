{{-- Edit Sale Modal --}}
<div id="vseModal" class="fixed inset-0 z-[9999] bg-black/50 hidden [&:not(.hidden)]:flex items-start justify-center p-6 overflow-y-auto">
    <div class="w-full max-w-2xl bg-white rounded-2xl overflow-hidden shadow-2xl">
        <div class="flex items-center gap-3 bg-gradient-to-r from-violet-600 to-purple-500 px-5 py-4">
            <div class="h-10 w-10 rounded-xl bg-white/20 flex items-center justify-center text-white flex-shrink-0">
                <i class="fas fa-pencil-alt"></i>
            </div>
            <div class="flex-1">
                <h2 class="text-sm font-bold text-white">Edit Sale</h2>
                <p id="vseModalSubtitle" class="text-xs text-violet-100 mt-0.5">Updating invoice</p>
            </div>
            <button onclick="vseClose()" class="ml-auto h-8 w-8 rounded-full bg-white/20 border-0 text-white cursor-pointer flex items-center justify-center">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>

        <div class="p-5 max-h-[calc(100vh-120px)] overflow-y-auto">
            <form id="vseForm" method="POST">
                @csrf @method('PUT')

                {{-- Client Information --}}
                <p class="mb-3 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                    <i class="fas fa-user"></i> Client Information
                </p>
                <input type="hidden" id="vseClientId" name="client_id">
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Select Existing Client</label>
                        <select id="vseSelectClient" class="select2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-violet-500" style="width:100%" onchange="vsPickParty('vse', 'client', this.value)">
                            <option value="">— None —</option>
                            @foreach($clients as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Select Existing Agent</label>
                        <select id="vseSelectAgent" class="select2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-violet-500" style="width:100%" onchange="vsPickParty('vse', 'agent', this.value)">
                            <option value="">— None —</option>
                            @foreach($agents as $a)
                                <option value="{{ $a->id }}">{{ $a->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 mb-3 -mt-2">Pick one — selecting a client clears the agent, and vice versa. Phone &amp; email are shown from that party's record.</p>
                <p id="edit_client_id_msg" class="text-red-500 text-xs mb-3 -mt-2 hidden error-message">Please select a client or agent</p>
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Phone</label>
                        <input type="text" id="vseClientPhone" readonly placeholder="Auto from selected party"
                            class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Email</label>
                        <input type="email" id="vseClientEmail" readonly placeholder="Auto from selected party"
                            class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Send Voucher Via</label>
                        <select name="send_via" id="vseSendVia" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-violet-500">
                            <option value="email">📧 Email</option>
                            <option value="sms">📱 SMS</option>
                            <option value="whatsapp">💬 WhatsApp</option>
                        </select>
                    </div>
                </div>

                {{-- Select Applications --}}
                <p class="mb-3 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                    <i class="fas fa-passport"></i> Select Applications to Bundle
                </p>
                <div class="flex gap-2 mb-2 items-center">
                    <select id="vseCountryFilter" class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-violet-500" onchange="vseLoadApps()">
                        <option value="">— All Countries —</option>
                    </select>
                    <button type="button" id="vseShowUnpaidBtn" onclick="vseToggleUnpaid()"
                        class="h-[38px] px-3 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 flex items-center gap-1.5">
                        <i class="fas fa-filter text-[10px]"></i> Show Unpaid
                    </button>
                </div>
                <div class="rounded-xl overflow-hidden max-h-64 overflow-y-auto border border-slate-200 mb-1" id="vseAppsTableWrap">
                    <table class="vs-apps-table w-full border-collapse text-xs">
                        <thead class="bg-slate-50 sticky top-0">
                            <tr>
                                <th class="px-2.5 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-slate-400" style="width:32px">
                                    <input type="checkbox" id="vseSelectAll" onclick="vseToggleAll(this)">
                                </th>
                                <th class="px-2.5 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-slate-400">App #</th>
                                <th class="px-2.5 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-slate-400">Applicant</th>
                                <th class="px-2.5 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-slate-400">Country / Type</th>
                                <th class="px-2.5 py-2 text-right text-[10px] font-bold uppercase tracking-wide text-slate-400">Sale Price ({{ $taka }})</th>
                                <th class="px-2.5 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-slate-400">Status</th>
                            </tr>
                        </thead>
                        <tbody id="vseAppsBody">
                            <tr><td colspan="6" class="text-center py-6 text-slate-400 text-xs">Loading…</td></tr>
                        </tbody>
                    </table>
                    <div class="flex justify-between items-center px-3 py-2 bg-violet-50 text-xs border-t border-violet-100">
                        <span id="vseSelectedCount" class="font-semibold text-violet-700">0 applications selected</span>
                        <span class="font-bold text-emerald-700">Grand Total: {{ $taka }} <span id="vseGrandTotal">0</span></span>
                    </div>
                </div>
                <p id="edit_apps_msg" class="text-red-500 text-xs mt-1 mb-3 hidden error-message">Please select at least one application or other service to bundle</p>
                <div id="vseHiddenInputs"></div>

                {{-- Add Other Services --}}
                <p class="mb-3 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                    <i class="fas fa-puzzle-piece"></i> Add Other Services
                </p>
                <div class="rounded-xl overflow-hidden max-h-52 overflow-y-auto border border-slate-200 mb-4" id="vseOtherTableWrap">
                    <table class="vs-apps-table w-full border-collapse text-xs">
                        <thead class="bg-slate-50 sticky top-0">
                            <tr>
                                <th class="px-2.5 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-slate-400" style="width:32px">
                                    <input type="checkbox" id="vseOtherSelectAll" onclick="vseToggleAllOther(this)">
                                </th>
                                <th class="px-2.5 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-slate-400">Service #</th>
                                <th class="px-2.5 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-slate-400">Passenger</th>
                                <th class="px-2.5 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-slate-400">Service</th>
                                <th class="px-2.5 py-2 text-right text-[10px] font-bold uppercase tracking-wide text-slate-400">Fee ({{ $taka }})</th>
                                <th class="px-2.5 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-slate-400">Status</th>
                            </tr>
                        </thead>
                        <tbody id="vseOtherBody">
                            <tr><td colspan="6" class="text-center py-6 text-slate-400 text-xs">Loading…</td></tr>
                        </tbody>
                    </table>
                    <div class="flex justify-between items-center px-3 py-2 bg-violet-50 text-xs border-t border-violet-100">
                        <span id="vseOtherSelectedCount" class="font-semibold text-violet-700">0 other services selected</span>
                        <span class="font-bold text-emerald-700">Subtotal: {{ $taka }} <span id="vseOtherSubtotal">0</span></span>
                    </div>
                </div>
                <div id="vseOtherHiddenInputs"></div>

                {{-- Voucher Details --}}
                <p class="mb-3 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                    <i class="fas fa-file-invoice"></i> Voucher Details
                </p>
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Invoice Number</label>
                        <input type="text" id="vseInvoiceNo" readonly
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Voucher Date <sup class="text-red-500">*</sup></label>
                        <input type="date" name="voucher_date" id="vseVoucherDate"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-violet-500">
                        <p id="edit_voucher_date_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose the voucher date</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Issued By</label>
                        <select name="issued_by" id="vseIssuedBy" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-violet-500">
                            <option value="">— Select Staff —</option>
                            @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Bundle Label</label>
                    <input type="text" name="bundle_label" id="vseBundleLabel" placeholder="Family"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-violet-500">
                </div>

                {{-- Payment Information --}}
                <p class="mb-3 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                    <i class="fas fa-wallet"></i> Payment Information
                </p>
                <div class="grid grid-cols-3 gap-3 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Total Amount ({{ $taka }})</label>
                        <input type="text" id="vseTotalDisplay" readonly value="0"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-bold text-violet-700 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Paid Amount ({{ $taka }})</label>
                        <input type="number" name="paid_amount" id="vsePaidInput" min="0" step="0.01" value="0"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-violet-500" oninput="vseCalcDue()">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Due Amount ({{ $taka }})</label>
                        <input type="text" id="vseDueDisplay" readonly value="0"
                            class="w-full rounded-xl border border-slate-200 bg-amber-50 px-3 py-2 text-sm font-bold text-amber-600 outline-none">
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-3 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">
                            Receivable Date
                            <span class="normal-case font-normal text-slate-400 ml-1">(customer থেকে নিবে)</span>
                        </label>
                        <input type="date" name="receivable_date" id="vseReceivableDate"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-violet-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Payment Method</label>
                        <select name="payment_method" id="vsePaymentMethod" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-violet-500" onchange="toggleVisaSaleBankField('vse')">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="advance">Advance</option>
                            <option value="checque">Cheque</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="mobile_banking">Mobile Banking</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div id="vse_bank_field">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Payment Account (Bank)</label>
                        <select name="bank_id" id="vseBankId" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-violet-500">
                            <option value="">— Select bank —</option>
                            @foreach($banks as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->name }} — {{ $bank->account_number }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="mb-5">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
                    <textarea name="notes" id="vseNotes" rows="2"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-violet-500 resize-none"></textarea>
                </div>

                <div class="flex justify-end gap-2 bg-slate-50 -mx-5 px-5 py-3 -mb-5">
                    <button type="button" onclick="vseClose()"
                        class="rounded-xl border border-slate-200 bg-white text-slate-700 px-4 py-2 text-sm font-semibold">Cancel</button>
                    <button type="button" id="vseSubmit"
                        class="rounded-xl bg-violet-600 text-white px-5 py-2 text-sm font-semibold flex items-center gap-2">
                        <i class="fas fa-save text-xs"></i> Update Sale
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>
