<div id="tsEditModal" class="fixed inset-0 z-[9999] bg-black/50 hidden [&:not(.hidden)]:flex items-start justify-center p-6 overflow-y-auto">
    <div class="w-full max-w-2xl bg-white rounded-2xl overflow-hidden shadow-2xl">
        <div class="flex items-center gap-3 bg-gradient-to-r from-violet-600 to-indigo-500 px-5 py-4">
            <div class="h-10 w-10 rounded-xl bg-white/20 flex items-center justify-center text-white flex-shrink-0">
                <i class="fas fa-pencil-alt"></i>
            </div>
            <div class="flex-1">
                <h2 class="text-sm font-bold text-white">Edit Ticket Sale</h2>
                <p class="text-xs text-violet-100 mt-0.5">Invoice: <span id="tseInvoiceLabel" class="font-semibold">—</span></p>
            </div>
            <button type="button" onclick="tseClose()" class="ml-auto h-8 w-8 rounded-full bg-white/20 border-0 text-white cursor-pointer flex items-center justify-center">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>

        <div class="p-5 max-h-[calc(100vh-160px)] overflow-y-auto">
            <form id="tsEditForm" method="POST">
                @csrf
                @method('PUT')

                {{-- Sale Information --}}
                <div class="mb-5">
                    <div class="mb-3 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                        <i class="fas fa-info-circle"></i> Sale Information
                    </div>
                    <input type="hidden" id="tse_client_id" name="client_id">
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Select Agent / Vendor</label>
                            <select id="tse_agent_picker" class="select2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500" style="width:100%">
                                <option value="">— None —</option>
                                @foreach ($agents as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->name }}{{ $agent->phone ? ' - '.$agent->phone : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Select Customer</label>
                            <select id="tse_customer_picker" class="select2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500" style="width:100%">
                                <option value="">— None —</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->name }}{{ $customer->phone ? ' - '.$customer->phone : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <p id="tse_client_id_msg" class="text-red-500 text-xs mt-1 mb-3 hidden">Please select an agent, vendor, or customer</p>
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Sale Date <sup class="text-red-500">*</sup></label>
                            <input type="date" id="tseSaleDate" name="sale_date" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Status <sup class="text-red-500">*</sup></label>
                            <select id="tseStatus" name="status" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
                                <option value="pending">Pending</option>
                                <option value="confirm">Confirm</option>
                                <option value="draft">Draft</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Currency <sup class="text-red-500">*</sup></label>
                            <input type="text" id="tseCurrency" name="currency" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
                        </div>
                    </div>
                </div>

                {{-- Select Ticket Purchases --}}
                <div class="mb-5">
                    <div class="mb-3 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                        <i class="fas fa-suitcase-rolling"></i> Select Tickets to Bundle
                    </div>
                    <div class="overflow-hidden rounded-xl bg-slate-50 max-h-72 overflow-y-auto" id="tseTicketsTableWrap">
                        <table class="tk-table w-full">
                            <thead class="bg-slate-100 sticky top-0">
                                <tr>
                                    <th class="w-8 px-3 py-2 text-left"><input type="checkbox" id="tseSelectAll" onclick="tseToggleAll(this)"></th>
                                    <th class="px-3 py-2 text-[10px] font-bold uppercase text-slate-400 text-left">PNR</th>
                                    <th class="px-3 py-2 text-[10px] font-bold uppercase text-slate-400 text-left">Passenger</th>
                                    <th class="px-3 py-2 text-[10px] font-bold uppercase text-slate-400 text-left">Route</th>
                                    <th class="px-3 py-2 text-[10px] font-bold uppercase text-slate-400 text-right">Sale Price</th>
                                </tr>
                            </thead>
                            <tbody id="tseTicketsBody">
                                <tr><td colspan="5" class="text-center py-6 text-slate-400 text-xs">Loading tickets...</td></tr>
                            </tbody>
                        </table>
                        <div class="flex justify-between items-center px-3 py-2 bg-violet-50 text-xs">
                            <span id="tseSelectedCount" class="font-semibold text-violet-700">0 tickets selected</span>
                            <div class="text-right leading-tight">
                                <div class="font-bold text-emerald-700">Grand Total: <span id="tseGrandTotal">0.00</span></div>
                                <div class="text-[10px] font-semibold text-slate-400" id="tseGrandMargin">Profit: 0.00 (0.0%)</div>
                            </div>
                        </div>
                    </div>
                    <p id="ts_edit_tickets_msg" class="text-red-500 text-xs mt-1 hidden">Please select at least one ticket.</p>
                    <div id="tseHiddenInputs"></div>
                </div>

                {{-- Payment Information --}}
                <div class="mb-5">
                    <div class="mb-3 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                        <i class="fas fa-wallet"></i> Payment Information
                    </div>
                    <div class="grid grid-cols-3 gap-3 mb-2">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Total Amount</label>
                            <input type="text" id="tseTotalDisplay" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none bg-slate-50 font-bold text-violet-700" readonly value="0.00">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Additional Payment</label>
                            <input type="number" name="paid_amount" id="tsePaidInput" min="0" step="0.01" value="0" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500" oninput="tseCalcDue()">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Due Amount</label>
                            <input type="text" id="tseDueDisplay" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none bg-amber-50 font-bold text-amber-600" readonly value="0.00">
                        </div>
                    </div>
                    <p class="text-xs text-emerald-600 mb-3">Previously Paid: <span id="tsePrevPaid" class="font-semibold">0.00</span></p>
                    <div class="grid grid-cols-3 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Receivable Date <span class="font-normal text-slate-400">(customer থেকে নিবে)</span></label>
                            <input type="date" name="due_date" id="tseReceivableDate" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Payment Method <sup class="text-red-500">*</sup></label>
                            <select id="tsePaymentMethod" name="payment_method" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500" onchange="tseToggleBankField()">
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="advance">Advance</option>
                                <option value="checque">Cheque</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="mobile_banking">Mobile Banking</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div id="tseBankField">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Payment Account (Bank)</label>
                            <select id="tseBank" name="bank_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
                                <option value="">— Select bank —</option>
                                @foreach ($banks as $bank)
                                    <option value="{{ $bank->id }}">{{ $bank->name }} — {{ $bank->account_number }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="payment_status" id="tsePaymentStatus" value="due">
                </div>

                <div class="flex gap-3 justify-end mt-2 pt-4">
                    <button type="button" onclick="tseClose()" class="rounded-xl border border-slate-200 bg-slate-100 text-slate-700 px-4 py-2 text-sm font-semibold cursor-pointer">Cancel</button>
                    <button type="button" id="tsEditSubmit" class="rounded-xl bg-violet-600 text-white px-5 py-2 text-sm font-semibold cursor-pointer flex items-center gap-2">
                        <i class="fas fa-save"></i> Update Sale
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
