{{-- Create Sale Modal --}}
<div id="vsCreateModal" class="fixed inset-0 z-[9999] bg-black/50 hidden [&:not(.hidden)]:flex items-start justify-center p-6 overflow-y-auto">
    <div class="w-full max-w-2xl bg-white rounded-2xl overflow-hidden shadow-2xl">
        <div class="flex items-center gap-3 bg-gradient-to-r from-teal-700 to-cyan-500 px-5 py-4">
            <div class="h-10 w-10 rounded-xl bg-white/20 flex items-center justify-center text-white flex-shrink-0">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="flex-1">
                <h2 class="text-sm font-bold text-white">Create Sale (Client-wise Voucher)</h2>
                <p class="text-xs text-teal-100 mt-0.5">Bundle multiple applications into one invoice</p>
            </div>
            <button onclick="vsCloseCreate()" class="ml-auto h-8 w-8 rounded-full bg-white/20 border-0 text-white cursor-pointer flex items-center justify-center">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>

        <div class="p-5 max-h-[calc(100vh-120px)] overflow-y-auto">
            <form id="vsCreateForm" method="POST" action="{{ route('role.visa-sales.store', ['role' => $role]) }}">
                @csrf

                {{-- Client Information --}}
                <p class="mb-3 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                    <i class="fas fa-user"></i> Client Information
                </p>
                <input type="hidden" id="create_client_id" name="client_id">
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Select Existing Client</label>
                        <select id="create_select_client" class="select2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-teal-500" style="width:100%" onchange="vsPickParty('create', 'client', this.value)">
                            <option value="">— None —</option>
                            @foreach($clients as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Select Existing Agent</label>
                        <select id="create_select_agent" class="select2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-teal-500" style="width:100%" onchange="vsPickParty('create', 'agent', this.value)">
                            <option value="">— None —</option>
                            @foreach($agents as $a)
                                <option value="{{ $a->id }}">{{ $a->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 mb-3 -mt-2">Pick one — selecting a client clears the agent, and vice versa. Phone &amp; email are shown from that party's record.</p>
                <p id="create_client_id_msg" class="text-red-500 text-xs mb-3 -mt-2 hidden error-message">Please select a client or agent</p>
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Phone</label>
                        <input type="text" id="create_client_phone" readonly placeholder="Auto from selected party"
                            class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Email</label>
                        <input type="email" id="create_client_email" readonly placeholder="Auto from selected party"
                            class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Send Voucher Via</label>
                        <select name="send_via" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-teal-500">
                            <option value="email">📧 Email</option>
                            <option value="sms">📱 SMS</option>
                            <option value="whatsapp">💬 WhatsApp</option>
                        </select>
                    </div>
                </div>

                {{-- Select Applications --}}
                <p class="mb-3 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                    <i class="fas fa-passport"></i> Select Applications to Bundle
                    <span class="ml-1 normal-case font-normal">Application Board থেকে multiple applications select করুন</span>
                </p>
                <div class="flex gap-2 mb-2 items-center">
                    <select id="vsCountryFilter" class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-teal-500" onchange="vsLoadApplications()">
                        <option value="">— All Countries —</option>
                    </select>
                    <button type="button" id="vsShowUnpaidBtn" onclick="vsToggleUnpaid()"
                        class="h-[38px] px-3 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 flex items-center gap-1.5">
                        <i class="fas fa-filter text-[10px]"></i> Show Unpaid
                    </button>
                </div>
                <div class="rounded-xl overflow-hidden max-h-64 overflow-y-auto border border-slate-200 mb-1" id="vsAppsTableWrap">
                    <table class="vs-apps-table w-full border-collapse text-xs">
                        <thead class="bg-slate-50 sticky top-0">
                            <tr>
                                <th class="px-2.5 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-slate-400" style="width:32px">
                                    <input type="checkbox" id="vsSelectAll" onclick="vsToggleAllApps(this)">
                                </th>
                                <th class="px-2.5 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-slate-400">App #</th>
                                <th class="px-2.5 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-slate-400">Applicant</th>
                                <th class="px-2.5 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-slate-400">Country / Type</th>
                                <th class="px-2.5 py-2 text-right text-[10px] font-bold uppercase tracking-wide text-slate-400">Sale Price ({{ $taka }})</th>
                                <th class="px-2.5 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-slate-400">Status</th>
                            </tr>
                        </thead>
                        <tbody id="vsAppsBody">
                            <tr><td colspan="6" class="text-center py-6 text-slate-400 text-xs">Loading applications...</td></tr>
                        </tbody>
                    </table>
                    <div class="flex justify-between items-center px-3 py-2 bg-teal-50 text-xs border-t border-teal-100">
                        <span id="vsSelectedCount" class="font-semibold text-teal-700">0 applications selected</span>
                        <span class="font-bold text-emerald-700">Grand Total: {{ $taka }} <span id="vsGrandTotal">0</span></span>
                    </div>
                </div>
                <p id="create_apps_msg" class="text-red-500 text-xs mt-1 mb-3 hidden error-message">Please select at least one application or other service to bundle</p>
                <div id="vsHiddenInputs"></div>

                {{-- Add Other Services --}}
                <p class="mb-3 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                    <i class="fas fa-puzzle-piece"></i> Add Other Services
                    <span class="ml-1 normal-case font-normal">এই client-এর Others service খুঁজে নিচের তালিকা থেকে যোগ করুন</span>
                </p>
                <div class="rounded-xl overflow-hidden max-h-52 overflow-y-auto border border-slate-200 mb-4" id="vsOtherTableWrap">
                    <table class="vs-apps-table w-full border-collapse text-xs">
                        <thead class="bg-slate-50 sticky top-0">
                            <tr>
                                <th class="px-2.5 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-slate-400" style="width:32px">
                                    <input type="checkbox" id="vsOtherSelectAll" onclick="vsToggleAllOther(this)">
                                </th>
                                <th class="px-2.5 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-slate-400">Service #</th>
                                <th class="px-2.5 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-slate-400">Passenger</th>
                                <th class="px-2.5 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-slate-400">Service</th>
                                <th class="px-2.5 py-2 text-right text-[10px] font-bold uppercase tracking-wide text-slate-400">Sale Price ({{ $taka }})</th>
                                <th class="px-2.5 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-slate-400">Status</th>
                            </tr>
                        </thead>
                        <tbody id="vsOtherBody">
                            <tr><td colspan="6" class="text-center py-6 text-slate-400 text-xs">Loading other services...</td></tr>
                        </tbody>
                    </table>
                    <div class="flex justify-between items-center px-3 py-2 bg-teal-50 text-xs border-t border-teal-100">
                        <span id="vsOtherSelectedCount" class="font-semibold text-teal-700">0 other services selected</span>
                        <span class="font-bold text-emerald-700">Subtotal: {{ $taka }} <span id="vsOtherSubtotal">0</span></span>
                    </div>
                </div>
                <div id="vsOtherHiddenInputs"></div>

                {{-- Voucher Details --}}
                <p class="mb-3 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                    <i class="fas fa-file-invoice"></i> Voucher Details
                </p>
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Invoice Number</label>
                        <input type="text" name="invoice_number" value="{{ $nextInvoice }}" readonly
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Voucher Date <sup class="text-red-500">*</sup></label>
                        <input type="date" id="create_voucher_date" name="voucher_date" value="{{ now()->format('Y-m-d') }}"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-teal-500">
                        <p id="create_voucher_date_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose the voucher date</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Issued By</label>
                        <select name="issued_by" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-teal-500">
                            <option value="">— Select Staff —</option>
                            @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ $u->id == auth()->id() ? 'selected' : '' }}>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Bundle Label <span class="text-slate-400 font-normal">(optional — e.g. Family, Couple, Group)</span></label>
                    <input type="text" name="bundle_label" placeholder="Family"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-teal-500">
                </div>

                {{-- Payment Information --}}
                <p class="mb-3 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                    <i class="fas fa-wallet"></i> Payment Information
                </p>
                <div class="grid grid-cols-3 gap-3 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Total Amount ({{ $taka }})</label>
                        <input type="text" id="vsTotalDisplay" readonly value="0"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-bold text-teal-700 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Paid Amount ({{ $taka }})</label>
                        <input type="number" name="paid_amount" id="vsPaidInput" min="0" step="0.01" value="0"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-teal-500" oninput="vsCalcDue()">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Due Amount ({{ $taka }})</label>
                        <input type="text" id="vsDueDisplay" readonly value="0"
                            class="w-full rounded-xl border border-slate-200 bg-amber-50 px-3 py-2 text-sm font-bold text-amber-600 outline-none">
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-3 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">
                            Receivable Date
                            <span class="normal-case font-normal text-slate-400 ml-1">(customer থেকে নিবে)</span>
                        </label>
                        <input type="date" id="create_receivable_date" name="receivable_date"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-teal-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Payment Method</label>
                        <select name="payment_method" id="create_payment_method" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-teal-500" onchange="toggleVisaSaleBankField('create')">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="advance">Advance</option>
                            <option value="checque">Cheque</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="mobile_banking">Mobile Banking</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div id="create_bank_field">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Payment Account (Bank)</label>
                        <select name="bank_id" id="create_bank_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-teal-500">
                            <option value="">— Select bank —</option>
                            @foreach($banks as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->name }} — {{ $bank->account_number }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="mb-5">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Notes (Printed on Voucher)</label>
                    <textarea name="notes" rows="2" placeholder="e.g. Family tour for Japan, all 5 members applying together. Payment received via bKash."
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-teal-500 resize-none"></textarea>
                </div>

                <div class="flex justify-end gap-2 bg-slate-50 -mx-5 px-5 py-3 -mb-5">
                    <button type="button" onclick="vsCloseCreate()"
                        class="rounded-xl border border-slate-200 bg-white text-slate-700 px-4 py-2 text-sm font-semibold">Cancel</button>
                    <button type="button" id="vsCreateSubmit"
                        class="rounded-xl bg-teal-600 text-white px-5 py-2 text-sm font-semibold flex items-center gap-2">
                        <i class="fas fa-save text-xs"></i> Save & Generate Voucher
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>
