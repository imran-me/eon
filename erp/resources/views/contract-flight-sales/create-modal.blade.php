<div id="createModal" class="fixed inset-0 z-[9000] hidden items-start justify-center overflow-y-auto bg-slate-900/55 px-4 py-6 modal-backdrop flex">
    <div class="w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center gap-3 bg-gradient-to-r from-blue-700 to-blue-600 px-6 py-5 text-white">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/20 text-lg"><i class="fas fa-file-invoice-dollar"></i></div>
            <div>
                <div class="text-base font-extrabold">New Multi-Flight Voucher</div>
                <div class="mt-0.5 text-xs text-blue-100">Bundle multiple contract flights into one client invoice</div>
            </div>
            <button type="button" class="modal-close-create ml-auto flex h-8 w-8 items-center justify-center rounded-lg bg-white/20 text-white hover:bg-white/30" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>

        <div class="max-h-[calc(100vh-220px)] overflow-y-auto px-6 py-5">
            <form id="createForm" action="{{ route('role.contract-flight-sales.store', ['role' => $role]) }}" method="POST">
                @csrf
                <section class="mb-5">
                    <div class="mb-3 flex items-center gap-2 border-b border-slate-100 pb-2 text-[11px] font-extrabold uppercase tracking-[0.12em] text-slate-500"><i class="fas fa-user-tie"></i> Client Information</div>
                    <input type="hidden" id="create_client_id" name="client_id">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div><label class="mb-1.5 block text-xs font-bold text-slate-700">Select Agent / Vendor</label><select id="create_agent_picker" class="w-full rounded-lg border border-slate-300 text-sm" style="width:100%"><option value="">— None —</option>@foreach($agents as $agent)<option value="{{ $agent->id }}">{{ $agent->name }}{{ $agent->phone ? ' - '.$agent->phone : '' }}</option>@endforeach</select></div>
                        <div><label class="mb-1.5 block text-xs font-bold text-slate-700">Select Customer</label><select id="create_customer_picker" class="w-full rounded-lg border border-slate-300 text-sm" style="width:100%"><option value="">— None —</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}{{ $customer->phone ? ' - '.$customer->phone : '' }}</option>@endforeach</select></div>
                        <div class="md:col-span-2"><label class="mb-1.5 block text-xs font-bold text-slate-700">Phone</label><input type="text" id="create_client_phone" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm" readonly placeholder="Auto from selected client"></div>
                    </div>
                    <p id="create_client_id_msg" class="mt-1 hidden text-xs text-red-500 error-message">Please select an agent, vendor, or customer</p>
                </section>

                <section class="mb-5">
                    <div class="mb-3 flex items-center gap-2 border-b border-slate-100 pb-2 text-[11px] font-extrabold uppercase tracking-[0.12em] text-slate-500"><i class="fas fa-plane"></i> Select Contract Flights</div>
                    <div id="create_flight_items" class="overflow-hidden rounded-xl border border-slate-200 bg-white"></div>
                    <div id="create_hidden_items"></div>
                    <p id="create_items_msg" class="mt-2 hidden text-xs text-red-500 error-message">Select at least one valid contract flight.</p>
                    <div class="mt-2 text-[11px] font-medium text-slate-500">Seats and unit price can be adjusted for this voucher.</div>
                </section>

                <section class="mb-0">
                    <div class="mb-3 flex items-center gap-2 border-b border-slate-100 pb-2 text-[11px] font-extrabold uppercase tracking-[0.12em] text-slate-500"><i class="fas fa-receipt"></i> Payment Summary</div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div><label class="mb-1.5 block text-xs font-bold text-slate-700">Voucher Total (BDT)</label><input type="number" id="create_total_amount" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-bold text-slate-600" readonly value="0.00"></div>
                        <div><label class="mb-1.5 block text-xs font-bold text-slate-700">Paid Amount</label><input type="number" id="create_paid_amount" name="paid_amount" min="0" step="0.01" class="voucher-paid w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-100" value="0"><p id="create_paid_amount_msg" class="mt-1 hidden text-xs text-red-500 error-message">Paid amount cannot exceed total.</p></div>
                        <div><label class="mb-1.5 block text-xs font-bold text-slate-700">Receivable Date <span class="font-normal text-slate-400">(customer থেকে নিবে)</span></label><input type="date" id="create_receivable_date" name="receivable_date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-100"></div>
                        <div><label class="mb-1.5 block text-xs font-bold text-slate-700">Payment Status</label><select id="create_payment_status" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm" disabled><option value="due">Due</option><option value="partial">Partial</option><option value="paid">Paid</option></select></div>
                        <div><label class="mb-1.5 block text-xs font-bold text-slate-700">Payment Method</label><select id="create_payment_method" name="payment_method" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-100" onchange="toggleFlightBankField('create')"><option value="cash">Cash</option><option value="card">Card</option><option value="advance">Advance</option><option value="checque">Cheque</option><option value="bank_transfer">Bank Transfer</option><option value="mobile_banking">Mobile Banking</option><option value="other">Other</option></select></div>
                        <div id="create_bank_field"><label class="mb-1.5 block text-xs font-bold text-slate-700">Payment Account (Bank)</label><select id="create_bank_id" name="bank_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-100"><option value="">— Select bank —</option>@foreach($banks as $bank)<option value="{{ $bank->id }}">{{ $bank->name }} — {{ $bank->account_number }}</option>@endforeach</select></div>
                        <div class="md:col-span-3"><label class="mb-1.5 block text-xs font-bold text-slate-700">Notes</label><textarea id="create_notes" name="notes" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-100" placeholder="Voucher notes..."></textarea></div>
                    </div>
                </section>
            </form>
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4">
            <button type="button" class="modal-close-create rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100">Cancel</button>
            <button id="createSubmit" type="button" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2 text-sm font-bold text-white hover:bg-blue-700"><i class="fas fa-save"></i> Save Voucher</button>
        </div>
    </div>
</div>
