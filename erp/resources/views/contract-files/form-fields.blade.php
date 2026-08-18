<div class="cfm-section-title"><i class="fas fa-user"></i> Applicant Information</div>
<div class="cfm-grid">
    <div class="cfm-file-holder-row cfm-full">
        <div><label class="cfm-lbl">File No</label><input type="text" id="{{ $prefix }}_file_number" name="file_number" class="cfm-input" readonly></div>
        <div>
            <label class="cfm-lbl">Applicant Name / Passport Holder <sup>*</sup></label>
            <div class="cfm-holder-row">
                <select id="{{ $prefix }}_passport_holder_id" name="passport_holder_id" class="cfm-select">
                    <option value="">Select / search applicant</option>
                    @foreach($passportHolders as $holder)
                    <option value="{{ $holder->id }}">{{ $holder->name }}{{ $holder->phone ? ' - '.$holder->phone : '' }}</option>
                    @endforeach
                </select>
                <button type="button" class="cfm-add-holder" onclick="openCfPhModal('{{ $prefix }}_passport_holder_id')" title="Add new passport holder"><i class="fas fa-plus"></i> New</button>
            </div>
            <p id="{{ $prefix }}_passport_holder_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Applicant is required</p>
        </div>
    </div>
    <div id="{{ $prefix }}_holder_info" class="cfm-holder-info cfm-full hidden">
        <span>Passport No: <b id="{{ $prefix }}_holder_passport">-</b></span>
        <span>Phone: <b id="{{ $prefix }}_holder_phone">-</b></span>
        <span>Nationality: <b id="{{ $prefix }}_holder_nationality">-</b></span>
        <span>Date of Birth: <b id="{{ $prefix }}_holder_dob">-</b></span>
    </div>
</div>

<div class="cfm-section-title"><i class="fas fa-folder"></i> File Details</div>
<div class="cfm-grid">
    <div><label class="cfm-lbl">Destination Country <sup>*</sup></label><select id="{{ $prefix }}_country_id" name="country_id" class="cfm-select">
            <option value="">Select country</option>@foreach($countries as $country)<option value="{{ $country->id }}">{{ $country->name }}</option>@endforeach
        </select>
        <p id="{{ $prefix }}_country_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Country is required</p>
    </div>
    <div><label class="cfm-lbl">File Category (Job) <sup>*</sup></label><select id="{{ $prefix }}_contract_file_category_id" name="contract_file_category_id" class="cfm-select">
            <option value="">Select category</option>@foreach($categories as $category)<option value="{{ $category->id }}" data-country="{{ $category->country_id }}">{{ $category->name }}</option>@endforeach
        </select>
        <p id="{{ $prefix }}_contract_file_category_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Category is required</p>
    </div>
    <div><label class="cfm-lbl">Vendor / Agent <sup>*</sup></label><select id="{{ $prefix }}_vendor_id" name="vendor_id" class="cfm-select" onchange="cfOnVendorChange(this)">
            <option value="">Select vendor</option>@foreach($vendors as $vendor)<option value="{{ $vendor->id }}">{{ $vendor->name }}</option>@endforeach
        </select>
        <p id="{{ $prefix }}_vendor_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Vendor is required</p>
    </div>
    <div><label class="cfm-lbl">Portal</label><select id="{{ $prefix }}_portal_id" name="portal_id" class="cfm-select" onchange="cfOnPortalChange(this)">
            <option value="">— No Portal —</option>@foreach($portals as $portal)<option value="{{ $portal->id }}">{{ $portal->name }}</option>@endforeach
        </select>
    </div>
    <div><label class="cfm-lbl">Visa Rate (BDT) <sup>*</sup></label><input type="number" min="0" step="0.01" id="{{ $prefix }}_visa_rate" name="visa_rate" class="cfm-input" placeholder="450000">
        <p id="{{ $prefix }}_visa_rate_msg" class="text-red-500 text-xs mt-1 hidden error-message">Visa rate is required</p>
    </div>
    <div><label class="cfm-lbl">Purchase Date</label><input type="date" id="{{ $prefix }}_purchase_date" name="purchase_date" class="cfm-input"></div>
    <div><label class="cfm-lbl">Cost Paid (BDT)</label><input type="number" min="0" step="0.01" id="{{ $prefix }}_cost_paid_amount" name="cost_paid_amount" class="cfm-input" placeholder="0"></div>
    <div id="{{ $prefix }}_cost_bank_wrap"><label class="cfm-lbl">Cost Bank</label><select id="{{ $prefix }}_cost_bank_id" name="cost_bank_id" class="cfm-select">
            <option value="">— Select Bank —</option>@foreach($banks as $bank)<option value="{{ $bank->id }}">{{ $bank->name }} — {{ $bank->account_number }}</option>@endforeach
        </select>
    </div>
    <div id="{{ $prefix }}_cost_sched_wrap"><label class="cfm-lbl">Cost Due Date <span class="font-normal text-gray-400">(payable schedule)</span></label><input type="date" id="{{ $prefix }}_payable_date" name="payable_date" class="cfm-input"></div>
    <div><label class="cfm-lbl">Submit Date <sup>*</sup></label><input type="date" id="{{ $prefix }}_submit_date" name="submit_date" class="cfm-input">
        <p id="{{ $prefix }}_submit_date_msg" class="text-red-500 text-xs mt-1 hidden error-message">Submit date is required</p>
    </div>
    <div><label class="cfm-lbl">Expected Out / Release Date</label><input type="date" id="{{ $prefix }}_expected_out_date" name="expected_out_date" class="cfm-input"></div>
    <div><label class="cfm-lbl">Status</label><select id="{{ $prefix }}_status" name="status" class="cfm-select">@foreach($statuses as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
</div>

<div class="cfm-section-title"><i class="fas fa-file-lines"></i> Required Documents</div>
<div id="{{ $prefix }}_documents_grid" class="cfm-doc-grid"></div>
<div class="cfm-doc-add"><input type="text" id="{{ $prefix }}_custom_doc" class="cfm-input" placeholder="Add custom document name..."><button type="button" id="{{ $prefix }}AddDoc" class="cfm-btn cfm-btn-small"><i class="fas fa-plus"></i> Add Document</button></div>

<div style="margin-top:16px;"><label class="cfm-lbl">Notes</label><textarea id="{{ $prefix }}_notes" name="notes" class="cfm-textarea" placeholder="Demand letter received, medical pending..."></textarea></div>
