<div class="sale-section">
    <div class="sale-section-title"><i class="fas fa-user"></i> Client Information</div>
    <input type="hidden" id="{{ $prefix }}_client_id" name="client_id">
    <div class="grid pay-grid">
        <div><label class="lbl">Select Agent / Vendor</label><select id="{{ $prefix }}_agent_picker" class="sel select2">
                <option value="">— None —</option>@foreach($clients as $client)<option value="{{ $client->id }}">{{ $client->name }}{{ $client->phone ? ' - '.$client->phone : '' }}</option>@endforeach
            </select>
        </div>
        <div><label class="lbl">Select Customer</label><select id="{{ $prefix }}_customer_picker" class="sel select2">
                <option value="">— None —</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}{{ $customer->phone ? ' - '.$customer->phone : '' }}</option>@endforeach
            </select>
        </div>
        <div><label class="lbl">Phone</label><input id="{{ $prefix }}_client_phone" class="inp bg-soft" readonly placeholder="Auto from client"></div>
    </div>
    <p id="{{ $prefix }}_client_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select an agent, vendor, or customer</p>
</div>

<div class="sale-section">
    <div class="sale-section-title"><i class="fas fa-folder-open"></i> Select Contract Files To Bundle
        <span>Application Board থেকে multiple contract files select করুন</span>
    </div>
    <div class="file-filter-row">
        <select id="{{ $prefix }}_country_id" name="country_id" class="sel select2 file-country-filter">
            <option value="">-- All Countries --</option>@foreach($countries as $country)<option value="{{ $country->id }}">{{ $country->name }}</option>@endforeach
        </select>
        <button type="button" class="filter-chip" onclick="renderFileBundle('{{ $prefix }}')"><i class="fas fa-filter"></i> Filter</button>
    </div>
    <div class="bundle-table-wrap">
        <table class="bundle-table">
            <thead>
                <tr>
                    <th style="width:32px"><input type="checkbox" id="{{ $prefix }}_select_all" onclick="toggleAllBundleFiles('{{ $prefix }}', this)"></th>
                    <th>File #</th>
                    <th>Applicant</th>
                    <th>Country / Category</th>
                    <th>Sale Price (BDT)</th>
                    <th>Vendor Cost</th>
                    <th>Profit</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="{{ $prefix }}_files_body">
                <tr><td colspan="8" class="empty mini">Loading contract files...</td></tr>
            </tbody>
        </table>
        <div class="bundle-selected-bar">
            <span id="{{ $prefix }}_selected_count">0 files selected</span>
            <span>Grand Total: BDT <b id="{{ $prefix }}_grand_total">0</b></span>
            <span id="{{ $prefix }}_grand_profit" class="bundle-profit">Profit: BDT 0 (0%)</span>
        </div>
    </div>
    <p id="{{ $prefix }}_file_ids_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select at least one contract file</p>
    <div id="{{ $prefix }}_hidden_inputs"></div>
</div>

<div class="sale-section">
    <div class="sale-section-title"><i class="fas fa-file-invoice"></i> Voucher Details</div>
    <div class="grid">
        <div><label class="lbl">Invoice #</label><input id="{{ $prefix }}_invoice_number" name="invoice_number" class="inp bg-soft" readonly value="{{ $prefix === 'create' ? $nextInvoiceNumber : '' }}"></div>
        <div><label class="lbl">Voucher Date *</label><input type="date" id="{{ $prefix }}_sale_date" name="sale_date" class="inp">
            <p id="{{ $prefix }}_sale_date_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose the sale date</p>
        </div>
    </div>
</div>

<div class="sale-section">
    <div class="sale-section-title"><i class="fas fa-wallet"></i> Payment Information</div>
    <div class="grid pay-grid">
        <div><label class="lbl">Total Amount (BDT)</label><input type="number" min="0" step="0.01" id="{{ $prefix }}_total_amount" name="total_amount" class="inp bg-soft calc" readonly>
            <p id="{{ $prefix }}_total_amount_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please enter total amount</p>
        </div>
        <div><label class="lbl">Paid Amount (BDT)</label><input type="number" min="0" step="0.01" id="{{ $prefix }}_paid_amount" name="paid_amount" class="inp calc"></div>
        <div><label class="lbl">Due Amount (BDT)</label><input type="text" id="{{ $prefix }}_due_amount" class="inp bg-due" readonly value="0.00"></div>
        <div><label class="lbl">Receivable Date <span class="font-normal text-gray-400">(customer থেকে নিবে)</span></label><input type="date" id="{{ $prefix }}_receivable_date" name="receivable_date" class="inp"></div>
        <div><label class="lbl">Vendor Cost</label><input type="number" min="0" step="0.01" id="{{ $prefix }}_vendor_cost" name="vendor_cost" class="inp bg-soft calc" readonly></div>
        <div><label class="lbl">Payment Status</label><select id="{{ $prefix }}_payment_status" name="payment_status" class="sel">
                <option value="due">Due</option>
                <option value="partial">Partial</option>
                <option value="paid">Paid</option>
            </select></div>
        <div><label class="lbl">Payment Method</label><select id="{{ $prefix }}_payment_method" name="payment_method" class="sel" onchange="toggleFileSaleBankField('{{ $prefix }}')">
                <option value="cash">Cash</option>
                <option value="card">Card</option>
                <option value="advance">Advance</option>
                <option value="checque">Cheque</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="mobile_banking">Mobile Banking</option>
                <option value="other">Other</option>
            </select></div>
        <div id="{{ $prefix }}_bank_field"><label class="lbl">Payment Account (Bank)</label><select id="{{ $prefix }}_bank_id" name="bank_id" class="sel">
                <option value="">— Select bank —</option>
                @foreach($banks as $bank)
                    <option value="{{ $bank->id }}">{{ $bank->name }} — {{ $bank->account_number }}</option>
                @endforeach
            </select></div>
    </div>
</div>

<div class="sale-section mb-0">
    <label class="lbl">Notes</label><textarea id="{{ $prefix }}_notes" name="notes" class="txt" placeholder="Printed on voucher"></textarea>
</div>
