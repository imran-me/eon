@extends('layout.app')
@section('meta-information')
    <title>Ticket Operations</title>
@endsection
@section('css')
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
    :root {
      --bg:#f4f6fb; --bg2:#ffffff; --bg3:#f0f2f8;
      --border:#e4e8f0; --border2:#d0d6e8;
      --text:#1a2035; --text2:#5a6480; --text3:#9aa3bc;
      --accent:#2563eb; --accent2:#1d4ed8; --accent-light:#eff6ff;
      --green:#16a34a; --green-light:#f0fdf4;
      --amber:#d97706; --red:#dc2626;
      --shadow:0 1px 4px rgba(30,50,100,.07);
      --shadow2:0 4px 16px rgba(30,50,100,.10);
    }
    body { font-family:'DM Sans',sans-serif; }

    .tv-page-head{background:#fff;border:1px solid var(--border);border-radius:14px;padding:16px 20px;display:flex;align-items:center;gap:14px;margin-bottom:0;box-shadow:var(--shadow);}
    .tv-page-logo{width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#5b21b6,#7c3aed);color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
    .tv-page-title{font-size:18px;font-weight:700;color:var(--text);}
    .tv-page-sub{font-size:12px;color:var(--text2);margin-top:1px;}
    .tv-page-actions{margin-left:auto;display:flex;gap:8px;}
    .erp-btn{padding:7px 14px;border-radius:8px;font-size:12px;font-weight:500;cursor:pointer;border:none;font-family:'DM Sans',sans-serif;transition:all .15s;display:inline-flex;align-items:center;gap:5px;text-decoration:none;}
    .btn-ghost{background:var(--bg3);color:var(--text2);border:1px solid var(--border);}
    .btn-ghost:hover{border-color:var(--border2);color:var(--text);}
    .btn-primary{background:var(--accent);color:#fff;}
    .btn-primary:hover{background:var(--accent2);}
    .btn-green-s{background:#16a34a;color:#fff;}
    .btn-green-s:hover{background:#15803d;}
    .btn-danger{background:#dc2626;color:#fff;}
    .btn-warning{background:#f59e0b;color:#fff;}

    /* Tab bar */
    .ops-tab-bar{background:linear-gradient(135deg,#5b21b6 0%,#7c3aed 100%);border-radius:0;padding:0 8px;display:flex;gap:2px;overflow-x:auto;box-shadow:0 4px 14px rgba(124,58,237,.18);margin-top:14px;}
    .op-tab{padding:13px 18px;color:rgba(255,255,255,.78);font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:8px;border-bottom:3px solid transparent;transition:all .15s;white-space:nowrap;user-select:none;}
    .op-tab:hover{color:#fff;}
    .op-tab.active{color:#fff;border-bottom-color:#fbbf24;background:rgba(255,255,255,.10);}
    .tab-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;}
    .dot-sale{background:#34d399;} .dot-refund{background:#f87171;} .dot-reissue{background:#60a5fa;} .dot-void{background:#fb923c;} .dot-emd{background:#c084fc;}
    .tab-content{display:none;padding:20px;background:#fff;border:1px solid var(--border);border-top:none;border-radius:0 0 14px 14px;box-shadow:var(--shadow2);}
    .tab-content.active{display:block;}

    /* Sections */
    .ds-section{background:#fff;border:1px solid var(--border);border-radius:12px;margin-bottom:14px;overflow:hidden;box-shadow:var(--shadow);}
    .ds-section-h{padding:13px 16px;background:#f8fafc;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
    .ds-sec-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
    .ds-sec-title{font-size:13.5px;font-weight:700;color:var(--text);}
    .ds-sec-sub{font-size:11.5px;color:var(--text2);}
    .ds-section-h .right{margin-left:auto;}
    .ds-section-body{padding:16px;}
    .ds-add-pax{padding:6px 13px;border-radius:7px;background:#ede9fe;color:#5b21b6;border:1px solid #c4b5fd;font-size:12px;font-weight:600;cursor:pointer;}
    .ds-add-pax:hover{background:#ddd6fe;}

    /* Grid */
    .ds-row{display:grid;gap:12px;margin-bottom:10px;}
    .ds-row:last-child{margin-bottom:0;}
    .ds-row.cols-2{grid-template-columns:repeat(2,1fr);}
    .ds-row.cols-3{grid-template-columns:repeat(3,1fr);}
    .ds-row.cols-4{grid-template-columns:repeat(4,1fr);}
    .ds-row.cols-6{grid-template-columns:repeat(6,1fr);}
    .span-2{grid-column:span 2;}
    @media(max-width:1024px){.ds-row.cols-4,.ds-row.cols-6{grid-template-columns:repeat(2,1fr);}.ds-row.cols-3{grid-template-columns:1fr 1fr;}}
    @media(max-width:640px){.ds-row.cols-2,.ds-row.cols-3,.ds-row.cols-4,.ds-row.cols-6{grid-template-columns:1fr;}}

    /* Fields */
    .ds-field-lbl{font-size:11px;color:var(--text2);font-weight:600;margin-bottom:5px;letter-spacing:.2px;display:block;}
    .ds-field-lbl .req{color:#dc2626;margin-left:2px;}
    .ds-field-input,.ds-field-select{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:#fff;color:var(--text);outline:none;transition:border-color .12s;font-family:'DM Sans',sans-serif;}
    .ds-field-input:focus,.ds-field-select:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-light);}
    .ds-field-input.highlight{border-color:#7c3aed;background:#faf5ff;}
    .ds-field-input.sale-price{border-color:#6d28d9;background:#faf5ff;font-weight:600;color:#5b21b6;}
    .ds-field-input[readonly]{background:#f8fafc;color:var(--text2);}
    .ds-field-input.success{border-color:#16a34a;background:#f0fdf4;}
    .ds-field-input.warning{border-color:#f59e0b;background:#fffbeb;}
    .ds-field-input.danger{border-color:#dc2626;background:#fef2f2;}

    /* Select2 */
    .select2-container--default .select2-selection--single{height:38px;border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;font-family:'DM Sans',sans-serif;font-size:13px;}
    .select2-container--default .select2-selection--single .select2-selection__rendered{line-height:38px;padding-left:12px;color:var(--text);}
    .select2-container--default .select2-selection--single .select2-selection__arrow{height:36px;}
    .select2-container--default.select2-container--focus .select2-selection--single{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-light);}

    /* Passenger cards */
    .ds-pax-card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px;margin-bottom:10px;}
    .ds-pax-h{display:flex;align-items:center;gap:10px;margin-bottom:12px;}
    .ds-pax-badge{padding:4px 11px;border-radius:6px;background:#ede9fe;color:#5b21b6;font-size:11px;font-weight:700;letter-spacing:.5px;}
    .ds-pax-remove{margin-left:auto;padding:4px 10px;border:1px solid #fecaca;background:#fff;color:#dc2626;font-size:11px;font-weight:600;border-radius:6px;cursor:pointer;}
    .ds-pax-remove:hover{background:#fee2e2;}
    .ds-add-another{display:block;width:100%;padding:11px;border:2px dashed #cbd5e1;background:transparent;color:var(--text2);font-size:12.5px;font-weight:600;border-radius:9px;cursor:pointer;margin-top:8px;transition:all .15s;font-family:'DM Sans',sans-serif;}
    .ds-add-another:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-light);}

    /* Summary bar */
    .ds-summary-bar{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:var(--border);border:1px solid var(--border);border-radius:11px;overflow:hidden;margin-bottom:14px;}
    .ds-summary-bar.cols-2{grid-template-columns:repeat(2,1fr);}
    .ds-sum-cell{background:#fff;padding:14px 16px;}
    .ds-sum-label{font-size:11px;color:var(--text2);font-weight:600;letter-spacing:.3px;text-transform:uppercase;margin-bottom:4px;}
    .ds-sum-value{font-size:18px;font-weight:700;font-family:'DM Mono',monospace;}
    .ds-sum-value.cost{color:#dc2626;} .ds-sum-value.sale{color:#0d9488;} .ds-sum-value.profit{color:#16a34a;} .ds-sum-value.penalty{color:#d97706;} .ds-sum-value.refund{color:#dc2626;}
    .taka{font-family:inherit;margin-right:2px;}

    /* Due line */
    .ds-due-line{padding:10px 16px;background:#fef2f2;border-top:1px solid #fecaca;display:flex;justify-content:space-between;align-items:center;}
    .ds-due-label{font-size:12px;color:var(--text2);font-weight:600;}
    .ds-due-amount{font-size:15px;font-weight:700;color:#dc2626;font-family:'DM Mono',monospace;}

    /* Action bar */
    .ds-action-bar{display:flex;gap:8px;justify-content:flex-end;padding:6px 0 0;}

    /* Alerts */
    .ds-alert{padding:11px 14px;border-radius:9px;font-size:12.5px;display:flex;align-items:center;gap:9px;margin-bottom:14px;font-weight:500;}
    .ds-alert.danger{background:#fef2f2;color:#991b1b;border:1px solid #fecaca;}
    .ds-alert.warning{background:#fffbeb;color:#92400e;border:1px solid #fde68a;}
    .ds-alert.info{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;}

    /* Steps */
    .ds-steps{display:flex;gap:6px;margin-bottom:14px;background:#f8fafc;padding:10px;border-radius:10px;border:1px solid var(--border);}
    .ds-step{flex:1;padding:8px 12px;background:#fff;border-radius:7px;font-size:11.5px;font-weight:600;color:var(--text2);border:1px solid var(--border);text-align:center;}
    .ds-step.done{background:#dcfce7;color:#166534;border-color:#bbf7d0;}
    .ds-step.active{background:#dbeafe;color:#1e40af;border-color:#bfdbfe;}

    /* Orig ref */
    .ds-orig-ref{background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px;padding:10px 14px;display:flex;flex-wrap:wrap;gap:14px 22px;align-items:center;margin-bottom:14px;font-size:11.5px;color:var(--text2);}
    .ds-orig-ref .ref-val{color:var(--text);font-weight:600;font-family:'DM Mono',monospace;margin-left:4px;}
    .ds-status-tag{padding:3px 10px;border-radius:11px;font-size:10.5px;font-weight:700;letter-spacing:.4px;margin-left:auto;}
    .ds-status-tag.confirm{background:#dcfce7;color:#166534;}
    .ds-status-tag.reissue{background:#dbeafe;color:#1e40af;}
    .ds-status-tag.void{background:#ffedd5;color:#9a3412;}

    /* Calc rows */
    .ds-calc{margin-top:12px;padding-top:12px;border-top:1px dashed var(--border);}
    .ds-calc-row{display:flex;justify-content:space-between;padding:5px 0;font-size:12.5px;color:var(--text2);max-width:360px;margin-left:auto;}
    .ds-calc-row.total{font-weight:700;color:var(--text);font-size:14px;border-top:1px solid var(--border);margin-top:5px;padding-top:8px;}
    .ds-calc-row .sec{color:var(--text3);}
    </style>
@endsection
@section('main-content')

<div style="padding:4px 0 20px;">

  {{-- Page header --}}
  <div class="tv-page-head">
    <div class="tv-page-logo">✈</div>
    <div>
      <div class="tv-page-title" id="ds-page-title">Ticket Operations</div>
      <div class="tv-page-sub" id="ds-page-sub">Direct Sale · Refund · Re-Issue · Void · EMD — Invoice: <strong>{{ $invoiceNo }}</strong></div>
    </div>
    <div class="tv-page-actions">
      <a href="{{ route('role.ticket-sales.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
         class="erp-btn btn-ghost">💼 Manage Sales</a>
    </div>
  </div>

  {{-- Tab bar --}}
  <div class="ops-tab-bar">
    <div class="op-tab active" onclick="switchOpTab('sale',this,'Direct Ticket Sale','Create purchase &amp; sale in one step — Invoice: {{ $invoiceNo }}')">
      <div class="tab-dot dot-sale"></div> Direct Sale
    </div>
    <div class="op-tab" onclick="switchOpTab('refund',this,'Ticket Refund','Process passenger refund &amp; airline recovery')">
      <div class="tab-dot dot-refund"></div> Refund
    </div>
    <div class="op-tab" onclick="switchOpTab('reissue',this,'Ticket Re-Issue','Change flight, route or date on existing ticket')">
      <div class="tab-dot dot-reissue"></div> Re-Issue
    </div>
    <div class="op-tab" onclick="switchOpTab('void',this,'Ticket Void','Cancel ticket within void window (24 hrs)')">
      <div class="tab-dot dot-void"></div> Void
    </div>
    <div class="op-tab" onclick="switchOpTab('emd',this,'EMD / Ancillary','Electronic miscellaneous document &amp; extras')">
      <div class="tab-dot dot-emd"></div> EMD / Ancillary
    </div>
  </div>

  {{-- ============================================================
       TAB 1 — DIRECT SALE
  ============================================================ --}}
  <div class="tab-content active" id="tab-sale">
    <form id="directSaleForm"
          action="{{ route('role.ticket-direct-sale.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
          method="POST" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="invoice_no" value="{{ $invoiceNo }}">

      {{-- Invoice Header --}}
      <div class="ds-section">
        <div class="ds-section-h">
          <div class="ds-sec-dot" style="background:#34d399"></div>
          <span class="ds-sec-title">Invoice Header</span>
          <span class="ds-sec-sub">(shared across all passengers)</span>
        </div>
        <div class="ds-section-body">
          <div class="ds-row cols-4">
            <div>
              <label class="ds-field-lbl">Agent <span class="req">*</span></label>
              <select name="agent_id" required class="select2 ds-field-select" style="width:100%">
                <option value="">— Select agent —</option>
                @foreach ($agents as $ag)
                  <option value="{{ $ag->id }}">{{ $ag->name }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="ds-field-lbl">Sale Date <span class="req">*</span></label>
              <input type="date" name="sale_date" required value="{{ date('Y-m-d') }}" class="ds-field-input">
            </div>
            <div>
              <label class="ds-field-lbl">Sale Status <span class="req">*</span></label>
              <select name="sale_status" required class="select2 ds-field-select" style="width:100%">
                <option value="confirm" selected>Confirm</option>
                <option value="pending">Pending</option>
                <option value="on_hold">On Hold</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
            <div>
              <label class="ds-field-lbl">Currency <span class="req">*</span></label>
              <select name="currency" required class="select2 ds-field-select" style="width:100%">
                <option value="BDT" selected>BDT (৳)</option>
                <option value="USD">USD ($)</option>
                <option value="EUR">EUR (€)</option>
                <option value="SAR">SAR</option>
                <option value="AED">AED</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      {{-- Passengers & Tickets --}}
      <div class="ds-section">
        <div class="ds-section-h">
          <div class="ds-sec-dot" style="background:#818cf8"></div>
          <span class="ds-sec-title">Passengers &amp; Tickets</span>
          <span class="ds-sec-sub">(one row per passenger — pick from Manage Tickets)</span>
          <div class="right">
            <button type="button" class="ds-add-pax" id="addPassengerBtn">+ Add Passenger</button>
          </div>
        </div>
        <div class="ds-section-body">
          <div id="passengerContainer">
            <div class="ds-pax-card" data-pax="1">
              <div class="ds-pax-h">
                <span class="ds-pax-badge">Passenger 1</span>
                <button type="button" class="ds-pax-remove" onclick="removePassenger(this)" style="display:none">✕ Remove</button>
              </div>
              <div class="ds-row cols-3">
                <div>
                  <label class="ds-field-lbl">Passport Holder <span class="req">*</span></label>
                  <select name="passengers[0][passport_holder_id]" required class="select2 ds-field-select" style="width:100%">
                    <option value="">— Select passenger —</option>
                    @foreach ($passport_holders as $ph)
                      <option value="{{ $ph->id }}">{{ $ph->name }}</option>
                    @endforeach
                  </select>
                </div>
                <div>
                  <label class="ds-field-lbl">Ticket Route <span class="req">*</span></label>
                  <select name="passengers[0][ticket_id]" required class="select2 ds-field-select" style="width:100%" onchange="onTicketChange(this,0)">
                    <option value="">— Select from Manage Tickets —</option>
                    @foreach ($tickets as $t)
                      <option value="{{ $t->id }}" data-price="{{ $t->price }}" data-vendor="{{ $t->vendor_id }}" data-portal="{{ $t->portal_id }}">{{ $t->title }}</option>
                    @endforeach
                  </select>
                </div>
                <div>
                  <label class="ds-field-lbl">Trip Type <span class="req">*</span></label>
                  <select name="passengers[0][trip_type]" required class="select2 ds-field-select" style="width:100%">
                    <option value="one-way">One-way</option>
                    <option value="round-trip">Round Trip</option>
                    <option value="multi-city">Multi-City</option>
                  </select>
                </div>
              </div>
              <div class="ds-row cols-4">
                <div>
                  <label class="ds-field-lbl">Vendor</label>
                  <select name="passengers[0][vendor_id]" class="select2 ds-field-select pax-vendor" style="width:100%">
                    <option value="">— Select vendor —</option>
                    @foreach ($vendors as $v)
                      <option value="{{ $v->id }}">{{ $v->name }}</option>
                    @endforeach
                  </select>
                </div>
                <div>
                  <label class="ds-field-lbl">Portal</label>
                  <select name="passengers[0][portal_id]" class="select2 ds-field-select pax-portal" style="width:100%" onchange="toggleCostBank(this)">
                    <option value="">— Select portal —</option>
                    @foreach ($portals as $p)
                      <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                  </select>
                </div>
                <div>
                  <label class="ds-field-lbl">Purchase Date <span class="req">*</span></label>
                  <input type="date" name="passengers[0][purchase_date]" required value="{{ date('Y-m-d') }}" class="ds-field-input">
                </div>
                <div>
                  <label class="ds-field-lbl">PNR / Ticket No <span class="req">*</span></label>
                  <input type="text" name="passengers[0][ticket_no]" required placeholder="Enter PNR (e.g. ABCD12)" class="ds-field-input highlight">
                </div>
              </div>
              <div class="ds-row cols-6">
                <div>
                  <label class="ds-field-lbl">Cost Price <span class="req">*</span></label>
                  <input type="number" name="passengers[0][cost_amount]" required step="0.01" placeholder="0.00" class="ds-field-input pax-cost" oninput="recalcSummary()">
                </div>
                <div>
                  <label class="ds-field-lbl">Cost Paid</label>
                  <input type="number" name="passengers[0][purchase_paid]" step="0.01" placeholder="0.00" class="ds-field-input" oninput="recalcSummary()">
                </div>
                <div>
                  <label class="ds-field-lbl">Pay Status <span class="req">*</span></label>
                  <select name="passengers[0][purchase_pay_status]" required class="select2 ds-field-select" style="width:100%">
                    <option value="due">Due</option><option value="partial">Partial</option><option value="paid">Paid</option>
                  </select>
                </div>
                <div>
                  <label class="ds-field-lbl">Cost Bank</label>
                  <select name="passengers[0][purchase_bank_id]" class="select2 ds-field-select pax-cost-bank" style="width:100%">
                    <option value="">— Select bank —</option>
                    @foreach ($banks as $b)
                      <option value="{{ $b->id }}">{{ $b->name }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="span-2">
                  <label class="ds-field-lbl">Sale Price <span class="req">*</span></label>
                  <input type="number" name="passengers[0][sale_price]" required step="0.01" placeholder="0.00" class="ds-field-input sale-price pax-sale" oninput="recalcSummary()">
                </div>
              </div>
            </div>
          </div>
          <button type="button" class="ds-add-another" id="addAnotherPax">⊕ Add Another Passenger</button>
        </div>
      </div>

      {{-- Summary --}}
      <div class="ds-summary-bar">
        <div class="ds-sum-cell"><div class="ds-sum-label">Total Cost</div><div class="ds-sum-value cost"><span class="taka">৳</span> <span id="sumCost">0.00</span></div></div>
        <div class="ds-sum-cell"><div class="ds-sum-label">Total Sale</div><div class="ds-sum-value sale"><span class="taka">৳</span> <span id="sumSale">0.00</span></div></div>
        <div class="ds-sum-cell"><div class="ds-sum-label">Gross Profit</div><div class="ds-sum-value profit"><span class="taka">৳</span> <span id="sumProfit">0.00</span></div></div>
      </div>

      {{-- Sale Payment --}}
      <div class="ds-section">
        <div class="ds-section-h">
          <div class="ds-sec-dot" style="background:#f472b6"></div>
          <span class="ds-sec-title">Sale Payment from Agent</span>
          <span class="ds-sec-sub">(for the entire invoice)</span>
        </div>
        <div class="ds-section-body">
          <div class="ds-row cols-3">
            <div>
              <label class="ds-field-lbl">Total Sale Price</label>
              <input type="number" id="totalSaleDisplay" step="0.01" value="0.00" class="ds-field-input" readonly>
            </div>
            <div>
              <label class="ds-field-lbl">Amount Received</label>
              <input type="number" name="sale_paid" id="salePaid" step="0.01" placeholder="0.00" class="ds-field-input" oninput="calcSaleDue()">
            </div>
            <div>
              <label class="ds-field-lbl">Payment Status <span class="req">*</span></label>
              <select name="sale_pay_status" required class="select2 ds-field-select" style="width:100%">
                <option value="due">Due</option><option value="partial">Partial</option><option value="paid">Paid</option>
              </select>
            </div>
          </div>
          <div class="ds-row cols-3">
            <div>
              <label class="ds-field-lbl">Payment Method <span class="req">*</span></label>
              <select name="sale_pay_method" required class="select2 ds-field-select" style="width:100%">
                <option value="cash">Cash</option><option value="card">Card</option><option value="bank_transfer">Bank Transfer</option><option value="mobile_banking">Mobile Banking</option><option value="advance">Advance</option><option value="other">Other</option>
              </select>
            </div>
            <div>
              <label class="ds-field-lbl">Receive into Account (Bank)</label>
              <select name="sale_bank_id" class="select2 ds-field-select" style="width:100%">
                <option value="">— Select bank —</option>
                @foreach ($banks as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
              </select>
            </div>
            <div>
              <label class="ds-field-lbl">Invoice Attachment</label>
              <input type="file" name="attachment" class="ds-field-input">
            </div>
          </div>
        </div>
        <div class="ds-due-line">
          <span class="ds-due-label">Due from Agent:</span>
          <span class="ds-due-amount">৳ <span id="saleDueDisplay">0.00</span></span>
          <input type="hidden" name="sale_due" id="saleDue" value="0">
        </div>
      </div>

      <div class="ds-action-bar">
        <a href="{{ route('role.ticket-sales.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="erp-btn btn-ghost">✕ Cancel</a>
        <button type="button" id="submitBtn" class="erp-btn btn-green-s">💾 Save Purchase + Sale</button>
      </div>
    </form>
  </div>

  {{-- ============================================================
       TAB 2 — REFUND
  ============================================================ --}}
  <div class="tab-content" id="tab-refund">
    <div class="ds-alert danger">⚠ Refund will reverse the original sale. Ensure airline has confirmed the refund amount before proceeding.</div>

    <div class="ds-section">
      <div class="ds-section-h"><div class="ds-sec-dot" style="background:#f87171"></div><span class="ds-sec-title">Refund Header</span></div>
      <div class="ds-section-body">
        <div class="ds-row cols-4">
          <div>
            <label class="ds-field-lbl">Agent <span class="req">*</span></label>
            <select class="select2 ds-field-select" style="width:100%">
              <option value="">— Select agent —</option>
              @foreach ($agents as $ag)<option value="{{ $ag->id }}">{{ $ag->name }}</option>@endforeach
            </select>
          </div>
          <div><label class="ds-field-lbl">Refund Date <span class="req">*</span></label><input type="date" value="{{ date('Y-m-d') }}" class="ds-field-input"></div>
          <div><label class="ds-field-lbl">Original Invoice Ref <span class="req">*</span></label><input type="text" placeholder="INV000007" class="ds-field-input danger"></div>
          <div>
            <label class="ds-field-lbl">Refund Status <span class="req">*</span></label>
            <select class="select2 ds-field-select" style="width:100%">
              <option>Confirm</option><option>Processing</option><option>Pending Airline</option><option>Completed</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="ds-section">
      <div class="ds-section-h"><div class="ds-sec-dot" style="background:#fca5a5"></div><span class="ds-sec-title">Original Ticket Info</span><span class="ds-sec-sub">(auto-filled from invoice ref)</span></div>
      <div class="ds-section-body">
        <div class="ds-orig-ref">
          <div>Passenger: <span class="ref-val">—</span></div>
          <div>PNR: <span class="ref-val">—</span></div>
          <div>Ticket No: <span class="ref-val">—</span></div>
          <div>Route: <span class="ref-val">—</span></div>
          <div>Original Cost: <span class="ref-val">—</span></div>
          <div>Original Sale: <span class="ref-val">—</span></div>
          <span class="ds-status-tag confirm">Confirmed</span>
        </div>
        <div class="ds-row cols-3">
          <div>
            <label class="ds-field-lbl">Vendor</label>
            <select class="select2 ds-field-select" style="width:100%">
              <option value="">— Select vendor —</option>
              @foreach ($vendors as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach
            </select>
          </div>
          <div>
            <label class="ds-field-lbl">Portal</label>
            <select class="select2 ds-field-select" style="width:100%">
              <option value="">— Select portal —</option>
              @foreach ($portals as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
            </select>
          </div>
          <div><label class="ds-field-lbl">Refund PNR / Ref No</label><input type="text" placeholder="Enter refund ref" class="ds-field-input danger"></div>
        </div>
      </div>
    </div>

    <div class="ds-section">
      <div class="ds-section-h"><div class="ds-sec-dot" style="background:#fbbf24"></div><span class="ds-sec-title">Refund Calculation</span><span class="ds-sec-sub">(per ticket)</span></div>
      <div class="ds-section-body">
        <div class="ds-row cols-3">
          <div><label class="ds-field-lbl">Original Ticket Cost <span class="req">*</span></label><input type="number" placeholder="0.00" class="ds-field-input" id="rOrgCost" oninput="calcRefund()"></div>
          <div><label class="ds-field-lbl">Airline Refund Amount <span class="req">*</span></label><input type="number" placeholder="0.00" class="ds-field-input success" id="rAirlineRefund" oninput="calcRefund()"></div>
          <div><label class="ds-field-lbl">Airline Penalty / Fee</label><input type="number" placeholder="0.00" class="ds-field-input warning" id="rPenalty" oninput="calcRefund()"></div>
        </div>
        <div class="ds-row cols-3">
          <div><label class="ds-field-lbl">Agent Service Charge</label><input type="number" placeholder="0.00" class="ds-field-input warning" id="rServiceCharge" oninput="calcRefund()"></div>
          <div><label class="ds-field-lbl">Original Sale Price <span class="req">*</span></label><input type="number" placeholder="0.00" class="ds-field-input" id="rOrgSale" oninput="calcRefund()"></div>
          <div><label class="ds-field-lbl">Net Refund to Customer <span class="req">*</span></label><input type="number" placeholder="0.00" class="ds-field-input danger" id="rNetRefund" readonly></div>
        </div>
        <div class="ds-calc">
          <div class="ds-calc-row"><span class="sec">Airline refund</span><span>৳ <span id="rCalcAirline">0.00</span></span></div>
          <div class="ds-calc-row"><span class="sec">Airline penalty</span><span style="color:#d97706">− ৳ <span id="rCalcPenalty">0.00</span></span></div>
          <div class="ds-calc-row"><span class="sec">Agent service charge</span><span style="color:#d97706">− ৳ <span id="rCalcService">0.00</span></span></div>
          <div class="ds-calc-row total"><span>Net refund to customer</span><span style="color:#dc2626">৳ <span id="rCalcNet">0.00</span></span></div>
        </div>
      </div>
    </div>

    <div class="ds-summary-bar">
      <div class="ds-sum-cell"><div class="ds-sum-label">Total Airline Refund</div><div class="ds-sum-value sale"><span class="taka">৳</span> <span id="rSumAirline">0.00</span></div></div>
      <div class="ds-sum-cell"><div class="ds-sum-label">Total Penalty</div><div class="ds-sum-value penalty"><span class="taka">৳</span> <span id="rSumPenalty">0.00</span></div></div>
      <div class="ds-sum-cell"><div class="ds-sum-label">Net Refund to Customer</div><div class="ds-sum-value refund"><span class="taka">৳</span> <span id="rSumNet">0.00</span></div></div>
    </div>

    <div class="ds-section">
      <div class="ds-section-h"><div class="ds-sec-dot" style="background:#f87171"></div><span class="ds-sec-title">Refund Payment to Customer / Agent</span></div>
      <div class="ds-section-body">
        <div class="ds-row cols-3">
          <div><label class="ds-field-lbl">Refund Amount Paid</label><input type="number" placeholder="0.00" class="ds-field-input"></div>
          <div>
            <label class="ds-field-lbl">Payment Method <span class="req">*</span></label>
            <select class="select2 ds-field-select" style="width:100%"><option value="">— Select method —</option><option>Cash</option><option>bKash</option><option>Nagad</option><option>Bank Transfer</option><option>Card Reversal</option></select>
          </div>
          <div>
            <label class="ds-field-lbl">Payment Status <span class="req">*</span></label>
            <select class="select2 ds-field-select" style="width:100%"><option>Due</option><option>Partial</option><option>Paid</option></select>
          </div>
        </div>
        <div class="ds-row cols-2">
          <div>
            <label class="ds-field-lbl">Paid from Account (Bank)</label>
            <select class="select2 ds-field-select" style="width:100%"><option value="">— Select bank —</option>@foreach ($banks as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select>
          </div>
          <div><label class="ds-field-lbl">Attachment / Proof</label><input type="file" class="ds-field-input"></div>
        </div>
      </div>
      <div class="ds-due-line"><span class="ds-due-label">Refund Due to Customer:</span><span class="ds-due-amount">৳ 0.00</span></div>
    </div>

    <div class="ds-action-bar">
      <button type="button" class="erp-btn btn-ghost">✕ Cancel</button>
      <button type="button" class="erp-btn btn-danger">↩ Process Refund</button>
    </div>
  </div>

  {{-- ============================================================
       TAB 3 — RE-ISSUE
  ============================================================ --}}
  <div class="tab-content" id="tab-reissue">
    <div class="ds-alert info">ℹ Re-issue changes the flight / date / route. Both old and new ticket details must be recorded for full audit trail.</div>

    <div class="ds-steps">
      <div class="ds-step done">1 — Original Ticket</div>
      <div class="ds-step active">2 — New Ticket Details</div>
      <div class="ds-step">3 — Charges &amp; Pricing</div>
      <div class="ds-step">4 — Payment</div>
    </div>

    <div class="ds-section">
      <div class="ds-section-h"><div class="ds-sec-dot" style="background:#93c5fd"></div><span class="ds-sec-title">Original Ticket Reference</span></div>
      <div class="ds-section-body">
        <div class="ds-row cols-4">
          <div>
            <label class="ds-field-lbl">Agent <span class="req">*</span></label>
            <select class="select2 ds-field-select" style="width:100%"><option value="">— Select agent —</option>@foreach ($agents as $ag)<option value="{{ $ag->id }}">{{ $ag->name }}</option>@endforeach</select>
          </div>
          <div><label class="ds-field-lbl">Re-Issue Date <span class="req">*</span></label><input type="date" value="{{ date('Y-m-d') }}" class="ds-field-input"></div>
          <div><label class="ds-field-lbl">Original Invoice <span class="req">*</span></label><input type="text" placeholder="INV000007" class="ds-field-input" style="border-color:#3b82f6;"></div>
          <div><label class="ds-field-lbl">Original PNR</label><select class="select2 ds-field-select" style="width:100%"><option>— Auto-filled —</option></select></div>
        </div>
        <div class="ds-orig-ref" style="margin-top:14px;">
          <div>Passenger: <span class="ref-val">—</span></div>
          <div>Old Route: <span class="ref-val">—</span></div>
          <div>Old Date: <span class="ref-val">—</span></div>
          <div>Old Ticket No: <span class="ref-val">—</span></div>
          <div>Original Fare: <span class="ref-val">—</span></div>
          <span class="ds-status-tag reissue">Re-issuing</span>
        </div>
      </div>
    </div>

    <div class="ds-section">
      <div class="ds-section-h"><div class="ds-sec-dot" style="background:#60a5fa"></div><span class="ds-sec-title">New Ticket Details</span></div>
      <div class="ds-section-body">
        <div class="ds-row cols-3">
          <div>
            <label class="ds-field-lbl">New Ticket Route <span class="req">*</span></label>
            <select class="select2 ds-field-select" style="width:100%;border-color:#3b82f6;"><option value="">— Select new route —</option>@foreach ($tickets as $t)<option value="{{ $t->id }}">{{ $t->title }}</option>@endforeach</select>
          </div>
          <div>
            <label class="ds-field-lbl">New Trip Type <span class="req">*</span></label>
            <select class="select2 ds-field-select" style="width:100%"><option value="one-way">One-way</option><option value="round-trip">Round Trip</option><option value="multi-city">Multi-City</option></select>
          </div>
          <div><label class="ds-field-lbl">New PNR / Ticket No <span class="req">*</span></label><input type="text" placeholder="Enter new PNR" class="ds-field-input" style="border-color:#3b82f6;"></div>
        </div>
        <div class="ds-row cols-4">
          <div>
            <label class="ds-field-lbl">New Vendor</label>
            <select class="select2 ds-field-select" style="width:100%"><option value="">— Select vendor —</option>@foreach ($vendors as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach</select>
          </div>
          <div>
            <label class="ds-field-lbl">New Portal</label>
            <select class="select2 ds-field-select" style="width:100%"><option value="">— Select portal —</option>@foreach ($portals as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
          </div>
          <div><label class="ds-field-lbl">New Travel Date</label><input type="date" class="ds-field-input"></div>
          <div><label class="ds-field-lbl">New Purchase Date <span class="req">*</span></label><input type="date" value="{{ date('Y-m-d') }}" class="ds-field-input"></div>
        </div>
      </div>
    </div>

    <div class="ds-section">
      <div class="ds-section-h"><div class="ds-sec-dot" style="background:#fbbf24"></div><span class="ds-sec-title">Re-Issue Charges &amp; Pricing</span></div>
      <div class="ds-section-body">
        <div class="ds-row cols-3">
          <div><label class="ds-field-lbl">Original Cost Price</label><input type="number" placeholder="0.00" class="ds-field-input" id="riOrgCost" oninput="calcReissue()"></div>
          <div><label class="ds-field-lbl">Airline Re-Issue Penalty <span class="req">*</span></label><input type="number" placeholder="0.00" class="ds-field-input warning" id="riPenalty" oninput="calcReissue()"></div>
          <div><label class="ds-field-lbl">Fare Difference (new − old) <span class="req">*</span></label><input type="number" placeholder="0.00" class="ds-field-input" style="border-color:#3b82f6;" id="riFareDiff" oninput="calcReissue()"></div>
        </div>
        <div class="ds-row cols-3">
          <div><label class="ds-field-lbl">New Total Cost Price</label><input type="number" placeholder="0.00" class="ds-field-input success" id="riNewCost" readonly></div>
          <div><label class="ds-field-lbl">Agent Service Charge</label><input type="number" placeholder="0.00" class="ds-field-input" id="riService" oninput="calcReissue()"></div>
          <div><label class="ds-field-lbl">New Sale Price <span class="req">*</span></label><input type="number" placeholder="0.00" class="ds-field-input sale-price" id="riNewSale" oninput="calcReissue()"></div>
        </div>
        <div class="ds-calc">
          <div class="ds-calc-row"><span class="sec">Airline penalty</span><span style="color:#d97706">৳ <span id="riCalcPenalty">0.00</span></span></div>
          <div class="ds-calc-row"><span class="sec">Fare difference</span><span>৳ <span id="riCalcFare">0.00</span></span></div>
          <div class="ds-calc-row"><span class="sec">Agent service</span><span>৳ <span id="riCalcService">0.00</span></span></div>
          <div class="ds-calc-row total"><span>Additional charge to customer</span><span style="color:#2563eb">৳ <span id="riCalcTotal">0.00</span></span></div>
        </div>
      </div>
    </div>

    <div class="ds-summary-bar">
      <div class="ds-sum-cell"><div class="ds-sum-label">New Cost Price</div><div class="ds-sum-value cost"><span class="taka">৳</span> <span id="riSumCost">0.00</span></div></div>
      <div class="ds-sum-cell"><div class="ds-sum-label">New Sale Price</div><div class="ds-sum-value sale"><span class="taka">৳</span> <span id="riSumSale">0.00</span></div></div>
      <div class="ds-sum-cell"><div class="ds-sum-label">Re-Issue Profit</div><div class="ds-sum-value profit"><span class="taka">৳</span> <span id="riSumProfit">0.00</span></div></div>
    </div>

    <div class="ds-section">
      <div class="ds-section-h"><div class="ds-sec-dot" style="background:#60a5fa"></div><span class="ds-sec-title">Additional Payment from Agent</span></div>
      <div class="ds-section-body">
        <div class="ds-row cols-3">
          <div><label class="ds-field-lbl">Additional Amount Due</label><input type="number" placeholder="0.00" class="ds-field-input" id="riPayDue" readonly></div>
          <div><label class="ds-field-lbl">Amount Received</label><input type="number" placeholder="0.00" class="ds-field-input"></div>
          <div>
            <label class="ds-field-lbl">Payment Status <span class="req">*</span></label>
            <select class="select2 ds-field-select" style="width:100%"><option>Due</option><option>Partial</option><option>Paid</option></select>
          </div>
        </div>
        <div class="ds-row cols-2">
          <div><label class="ds-field-lbl">Receive into Account (Bank)</label><select class="select2 ds-field-select" style="width:100%"><option value="">— Select bank —</option>@foreach ($banks as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
          <div><label class="ds-field-lbl">Attachment</label><input type="file" class="ds-field-input"></div>
        </div>
      </div>
      <div class="ds-due-line"><span class="ds-due-label">Due from Agent (Re-Issue):</span><span class="ds-due-amount">৳ 0.00</span></div>
    </div>

    <div class="ds-action-bar">
      <button type="button" class="erp-btn btn-ghost">✕ Cancel</button>
      <button type="button" class="erp-btn btn-primary" style="background:#1d4ed8;">✈ Save Re-Issue</button>
    </div>
  </div>

  {{-- ============================================================
       TAB 4 — VOID
  ============================================================ --}}
  <div class="tab-content" id="tab-void">
    <div class="ds-alert warning">⚠ Void is only allowed within the airline's void window (usually same day or 24 hrs). Late void may incur full penalty. This action cannot be undone.</div>

    <div class="ds-section">
      <div class="ds-section-h"><div class="ds-sec-dot" style="background:#fb923c"></div><span class="ds-sec-title">Void Header</span></div>
      <div class="ds-section-body">
        <div class="ds-row cols-4">
          <div>
            <label class="ds-field-lbl">Agent <span class="req">*</span></label>
            <select class="select2 ds-field-select" style="width:100%"><option value="">— Select agent —</option>@foreach ($agents as $ag)<option value="{{ $ag->id }}">{{ $ag->name }}</option>@endforeach</select>
          </div>
          <div><label class="ds-field-lbl">Void Date <span class="req">*</span></label><input type="date" value="{{ date('Y-m-d') }}" class="ds-field-input"></div>
          <div><label class="ds-field-lbl">Original Invoice <span class="req">*</span></label><input type="text" placeholder="INV000007" class="ds-field-input danger"></div>
          <div>
            <label class="ds-field-lbl">Void Reason <span class="req">*</span></label>
            <select class="select2 ds-field-select" style="width:100%"><option value="">— Select reason —</option><option>Wrong booking</option><option>Customer cancelled (same-day)</option><option>Schedule conflict</option><option>Duplicate booking</option><option>Other</option></select>
          </div>
        </div>
      </div>
    </div>

    <div class="ds-section">
      <div class="ds-section-h"><div class="ds-sec-dot" style="background:#fdba74"></div><span class="ds-sec-title">Ticket(s) to Void</span><span class="ds-sec-sub">(select from original invoice)</span></div>
      <div class="ds-section-body">
        <div class="ds-orig-ref">
          <div>Passenger: <span class="ref-val">—</span></div>
          <div>PNR: <span class="ref-val">—</span></div>
          <div>Ticket No: <span class="ref-val">—</span></div>
          <div>Route: <span class="ref-val">—</span></div>
          <div>Issue Date: <span class="ref-val">—</span></div>
          <div>Cost: <span class="ref-val">—</span></div>
          <div>Sale: <span class="ref-val">—</span></div>
          <span class="ds-status-tag void">Void Pending</span>
        </div>
        <div class="ds-row cols-3">
          <div><label class="ds-field-lbl">Vendor</label><select class="select2 ds-field-select" style="width:100%"><option>— Auto-filled —</option>@foreach ($vendors as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach</select></div>
          <div><label class="ds-field-lbl">Portal</label><select class="select2 ds-field-select" style="width:100%"><option>— Auto-filled —</option>@foreach ($portals as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
          <div><label class="ds-field-lbl">Airline Confirmation No.</label><input type="text" placeholder="Enter void ref" class="ds-field-input danger"></div>
        </div>
      </div>
    </div>

    <div class="ds-section">
      <div class="ds-section-h"><div class="ds-sec-dot" style="background:#fbbf24"></div><span class="ds-sec-title">Void Charges &amp; Reversal</span></div>
      <div class="ds-section-body">
        <div class="ds-row cols-3">
          <div><label class="ds-field-lbl">Original Cost Price</label><input type="number" placeholder="0.00" class="ds-field-input" id="vOrgCost" oninput="calcVoid()"></div>
          <div><label class="ds-field-lbl">Void Penalty / Fee</label><input type="number" placeholder="0.00" class="ds-field-input warning" id="vPenalty" oninput="calcVoid()"></div>
          <div><label class="ds-field-lbl">Net Cost Reversal</label><input type="number" placeholder="0.00" class="ds-field-input success" id="vNetCost" readonly></div>
        </div>
        <div class="ds-row cols-3">
          <div><label class="ds-field-lbl">Original Sale Price</label><input type="number" placeholder="0.00" class="ds-field-input" id="vOrgSale" oninput="calcVoid()"></div>
          <div><label class="ds-field-lbl">Agent Void Fee</label><input type="number" placeholder="0.00" class="ds-field-input warning" id="vAgentFee" oninput="calcVoid()"></div>
          <div><label class="ds-field-lbl">Net Sale Reversal</label><input type="number" placeholder="0.00" class="ds-field-input danger" id="vNetSale" readonly></div>
        </div>
        <div class="ds-calc">
          <div class="ds-calc-row"><span class="sec">Original sale price</span><span>৳ <span id="vCalcSale">0.00</span></span></div>
          <div class="ds-calc-row"><span class="sec">Void penalty</span><span style="color:#d97706">− ৳ <span id="vCalcPenalty">0.00</span></span></div>
          <div class="ds-calc-row"><span class="sec">Agent void fee</span><span style="color:#d97706">− ৳ <span id="vCalcFee">0.00</span></span></div>
          <div class="ds-calc-row total"><span>Refund to agent / customer</span><span style="color:#dc2626">৳ <span id="vCalcRefund">0.00</span></span></div>
        </div>
      </div>
    </div>

    <div class="ds-summary-bar cols-2">
      <div class="ds-sum-cell"><div class="ds-sum-label">Total Void Penalty</div><div class="ds-sum-value penalty"><span class="taka">৳</span> <span id="vSumPenalty">0.00</span></div></div>
      <div class="ds-sum-cell"><div class="ds-sum-label">Refund to Customer</div><div class="ds-sum-value refund"><span class="taka">৳</span> <span id="vSumRefund">0.00</span></div></div>
    </div>

    <div class="ds-section">
      <div class="ds-section-h"><div class="ds-sec-dot" style="background:#fb923c"></div><span class="ds-sec-title">Payment Reversal / Refund</span></div>
      <div class="ds-section-body">
        <div class="ds-row cols-3">
          <div><label class="ds-field-lbl">Refund Amount</label><input type="number" placeholder="0.00" class="ds-field-input"></div>
          <div><label class="ds-field-lbl">Refund Method <span class="req">*</span></label><select class="select2 ds-field-select" style="width:100%"><option value="">— Select method —</option><option>Cash</option><option>bKash</option><option>Nagad</option><option>Bank Transfer</option><option>Card Reversal</option></select></div>
          <div><label class="ds-field-lbl">Refund Status <span class="req">*</span></label><select class="select2 ds-field-select" style="width:100%"><option>Pending</option><option>Processing</option><option>Completed</option></select></div>
        </div>
        <div class="ds-row cols-2">
          <div><label class="ds-field-lbl">Paid from Account (Bank)</label><select class="select2 ds-field-select" style="width:100%"><option value="">— Select bank —</option>@foreach ($banks as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
          <div><label class="ds-field-lbl">Void Proof / Attachment</label><input type="file" class="ds-field-input"></div>
        </div>
      </div>
      <div class="ds-due-line"><span class="ds-due-label">Net Due to Agent:</span><span class="ds-due-amount">৳ 0.00</span></div>
    </div>

    <div class="ds-action-bar">
      <button type="button" class="erp-btn btn-ghost">✕ Cancel</button>
      <button type="button" class="erp-btn btn-warning">⊗ Confirm Void</button>
    </div>
  </div>

  {{-- ============================================================
       TAB 5 — EMD / ANCILLARY
  ============================================================ --}}
  <div class="tab-content" id="tab-emd">
    <div class="ds-alert info">ℹ EMD is used for ancillary charges: excess baggage, seat upgrade, meals, airport tax, visa fee, travel insurance, and other services.</div>

    <div class="ds-section">
      <div class="ds-section-h"><div class="ds-sec-dot" style="background:#c084fc"></div><span class="ds-sec-title">EMD / Ancillary Header</span></div>
      <div class="ds-section-body">
        <div class="ds-row cols-4">
          <div>
            <label class="ds-field-lbl">Agent <span class="req">*</span></label>
            <select class="select2 ds-field-select" style="width:100%"><option value="">— Select agent —</option>@foreach ($agents as $ag)<option value="{{ $ag->id }}">{{ $ag->name }}</option>@endforeach</select>
          </div>
          <div><label class="ds-field-lbl">Issue Date <span class="req">*</span></label><input type="date" value="{{ date('Y-m-d') }}" class="ds-field-input"></div>
          <div><label class="ds-field-lbl">Link to Invoice (optional)</label><select class="select2 ds-field-select" style="width:100%"><option value="">— None —</option></select></div>
          <div>
            <label class="ds-field-lbl">Currency <span class="req">*</span></label>
            <select class="select2 ds-field-select" style="width:100%"><option value="BDT" selected>BDT (৳)</option><option value="USD">USD ($)</option><option value="EUR">EUR (€)</option></select>
          </div>
        </div>
      </div>
    </div>

    <div class="ds-section">
      <div class="ds-section-h">
        <div class="ds-sec-dot" style="background:#a855f7"></div>
        <span class="ds-sec-title">Ancillary / EMD Items</span>
        <span class="ds-sec-sub">(one row per service)</span>
        <div class="right"><button type="button" class="ds-add-pax" id="addEmdItemBtn" style="background:#faf5ff;color:#7c3aed;border-color:#d8b4fe;">+ Add Item</button></div>
      </div>
      <div class="ds-section-body">
        <div id="emdItemContainer">
          <div class="ds-pax-card" data-emd="1">
            <div class="ds-pax-h">
              <span class="ds-pax-badge" style="background:#f3e8ff;color:#7c3aed;">Item 1</span>
              <button type="button" class="ds-pax-remove" onclick="removeEmdItem(this)" style="display:none">✕ Remove</button>
            </div>
            <div class="ds-row cols-3">
              <div>
                <label class="ds-field-lbl">Passenger / Customer <span class="req">*</span></label>
                <select class="select2 ds-field-select" style="width:100%"><option value="">— Select passenger —</option>@foreach ($passport_holders as $ph)<option value="{{ $ph->id }}">{{ $ph->name }}</option>@endforeach</select>
              </div>
              <div>
                <label class="ds-field-lbl">Service Type <span class="req">*</span></label>
                <select class="select2 ds-field-select" style="width:100%"><option value="">— Select type —</option><option>Excess Baggage</option><option>Seat Upgrade</option><option>Meal</option><option>Airport Tax</option><option>Visa Fee</option><option>Travel Insurance</option><option>Lounge Access</option><option>Other</option></select>
              </div>
              <div><label class="ds-field-lbl">EMD / Reference No.</label><input type="text" placeholder="Enter EMD no." class="ds-field-input" style="border-color:#9333ea;"></div>
            </div>
            <div class="ds-row cols-4">
              <div><label class="ds-field-lbl">Vendor / Airline</label><select class="select2 ds-field-select" style="width:100%"><option value="">— Select vendor —</option>@foreach ($vendors as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach</select></div>
              <div><label class="ds-field-lbl">Description</label><input type="text" placeholder="e.g. 23kg baggage" class="ds-field-input"></div>
              <div><label class="ds-field-lbl">Cost Price <span class="req">*</span></label><input type="number" placeholder="0.00" class="ds-field-input emd-cost" oninput="recalcEmd()"></div>
              <div><label class="ds-field-lbl">Sale Price <span class="req">*</span></label><input type="number" placeholder="0.00" class="ds-field-input sale-price emd-sale" oninput="recalcEmd()"></div>
            </div>
          </div>
        </div>
        <button type="button" class="ds-add-another" id="addAnotherEmd" style="color:#7c3aed;">⊕ Add Another Item</button>
      </div>
    </div>

    <div class="ds-summary-bar">
      <div class="ds-sum-cell"><div class="ds-sum-label">Total Cost</div><div class="ds-sum-value cost"><span class="taka">৳</span> <span id="emdSumCost">0.00</span></div></div>
      <div class="ds-sum-cell"><div class="ds-sum-label">Total Sale</div><div class="ds-sum-value sale"><span class="taka">৳</span> <span id="emdSumSale">0.00</span></div></div>
      <div class="ds-sum-cell"><div class="ds-sum-label">Gross Profit</div><div class="ds-sum-value profit"><span class="taka">৳</span> <span id="emdSumProfit">0.00</span></div></div>
    </div>

    <div class="ds-section">
      <div class="ds-section-h"><div class="ds-sec-dot" style="background:#c084fc"></div><span class="ds-sec-title">Payment from Agent</span></div>
      <div class="ds-section-body">
        <div class="ds-row cols-3">
          <div><label class="ds-field-lbl">Total Sale Amount</label><input type="number" value="0.00" class="ds-field-input" id="emdTotalDisplay" readonly></div>
          <div><label class="ds-field-lbl">Amount Received</label><input type="number" placeholder="0.00" class="ds-field-input" id="emdPaid" oninput="calcEmdDue()"></div>
          <div><label class="ds-field-lbl">Payment Status <span class="req">*</span></label><select class="select2 ds-field-select" style="width:100%"><option>Due</option><option>Partial</option><option>Paid</option></select></div>
        </div>
        <div class="ds-row cols-2">
          <div><label class="ds-field-lbl">Receive into Account (Bank)</label><select class="select2 ds-field-select" style="width:100%"><option value="">— Select bank —</option>@foreach ($banks as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
          <div><label class="ds-field-lbl">Attachment</label><input type="file" class="ds-field-input"></div>
        </div>
      </div>
      <div class="ds-due-line"><span class="ds-due-label">Due from Agent:</span><span class="ds-due-amount">৳ <span id="emdDueDisplay">0.00</span></span></div>
    </div>

    <div class="ds-action-bar">
      <button type="button" class="erp-btn btn-ghost">✕ Cancel</button>
      <button type="button" class="erp-btn btn-primary" style="background:#7c3aed;">💾 Save EMD / Ancillary</button>
    </div>
  </div>

</div>

@endsection
@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// ── Passport holder options blob (for dynamic rows) ───────────
const phOptions = `@foreach ($passport_holders as $ph)<option value="{{ $ph->id }}">{{ $ph->name }}</option>@endforeach`;
const ticketOptions = `@foreach ($tickets as $t)<option value="{{ $t->id }}" data-price="{{ $t->price }}" data-vendor="{{ $t->vendor_id }}" data-portal="{{ $t->portal_id }}">{{ $t->title }}</option>@endforeach`;
const vendorOptions = `@foreach ($vendors as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach`;
const portalOptions = `@foreach ($portals as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach`;
const bankOptions   = `@foreach ($banks as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach`;

$(document).ready(function () {
  $('.select2').select2();
  $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
});

// ── Tab switcher ──────────────────────────────────────────────
function switchOpTab(id, el, title, sub) {
  document.querySelectorAll('.op-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  if (el) el.classList.add('active');
  const target = document.getElementById('tab-' + id);
  if (target) target.classList.add('active');
  document.getElementById('ds-page-title').textContent = title;
  document.getElementById('ds-page-sub').textContent   = sub;
}

// ── Passenger rows ────────────────────────────────────────────
let paxCount = 1;
document.getElementById('addPassengerBtn').addEventListener('click', addPassenger);
document.getElementById('addAnotherPax').addEventListener('click', addPassenger);

function addPassenger() {
  const idx = paxCount;
  const html = `
  <div class="ds-pax-card" data-pax="${idx+1}">
    <div class="ds-pax-h">
      <span class="ds-pax-badge">Passenger ${idx+1}</span>
      <button type="button" class="ds-pax-remove" onclick="removePassenger(this)">✕ Remove</button>
    </div>
    <div class="ds-row cols-3">
      <div><label class="ds-field-lbl">Passport Holder <span class="req">*</span></label>
        <select name="passengers[${idx}][passport_holder_id]" required class="select2-dyn ds-field-select" style="width:100%"><option value="">— Select passenger —</option>${phOptions}</select></div>
      <div><label class="ds-field-lbl">Ticket Route <span class="req">*</span></label>
        <select name="passengers[${idx}][ticket_id]" required class="select2-dyn ds-field-select" style="width:100%" onchange="onTicketChange(this,${idx})"><option value="">— Select from Manage Tickets —</option>${ticketOptions}</select></div>
      <div><label class="ds-field-lbl">Trip Type <span class="req">*</span></label>
        <select name="passengers[${idx}][trip_type]" required class="select2-dyn ds-field-select" style="width:100%"><option value="one-way">One-way</option><option value="round-trip">Round Trip</option><option value="multi-city">Multi-City</option></select></div>
    </div>
    <div class="ds-row cols-4">
      <div><label class="ds-field-lbl">Vendor</label>
        <select name="passengers[${idx}][vendor_id]" class="select2-dyn ds-field-select pax-vendor" style="width:100%"><option value="">— Select vendor —</option>${vendorOptions}</select></div>
      <div><label class="ds-field-lbl">Portal</label>
        <select name="passengers[${idx}][portal_id]" class="select2-dyn ds-field-select pax-portal" style="width:100%" onchange="toggleCostBank(this)"><option value="">— Select portal —</option>${portalOptions}</select></div>
      <div><label class="ds-field-lbl">Purchase Date <span class="req">*</span></label><input type="date" name="passengers[${idx}][purchase_date]" required value="{{ date('Y-m-d') }}" class="ds-field-input"></div>
      <div><label class="ds-field-lbl">PNR / Ticket No <span class="req">*</span></label><input type="text" name="passengers[${idx}][ticket_no]" required placeholder="Enter PNR" class="ds-field-input highlight"></div>
    </div>
    <div class="ds-row cols-6">
      <div><label class="ds-field-lbl">Cost Price <span class="req">*</span></label><input type="number" name="passengers[${idx}][cost_amount]" required step="0.01" placeholder="0.00" class="ds-field-input pax-cost" oninput="recalcSummary()"></div>
      <div><label class="ds-field-lbl">Cost Paid</label><input type="number" name="passengers[${idx}][purchase_paid]" step="0.01" placeholder="0.00" class="ds-field-input" oninput="recalcSummary()"></div>
      <div><label class="ds-field-lbl">Pay Status <span class="req">*</span></label><select name="passengers[${idx}][purchase_pay_status]" required class="select2-dyn ds-field-select" style="width:100%"><option value="due">Due</option><option value="partial">Partial</option><option value="paid">Paid</option></select></div>
      <div><label class="ds-field-lbl">Cost Bank</label><select name="passengers[${idx}][purchase_bank_id]" class="select2-dyn ds-field-select pax-cost-bank" style="width:100%"><option value="">— Select bank —</option>${bankOptions}</select></div>
      <div style="grid-column:span 2"><label class="ds-field-lbl">Sale Price <span class="req">*</span></label><input type="number" name="passengers[${idx}][sale_price]" required step="0.01" placeholder="0.00" class="ds-field-input sale-price pax-sale" oninput="recalcSummary()"></div>
    </div>
  </div>`;
  document.getElementById('passengerContainer').insertAdjacentHTML('beforeend', html);
  $(document.getElementById('passengerContainer').lastElementChild).find('.select2-dyn').select2();
  paxCount++;
  updateRemoveVis();
}

function removePassenger(btn) {
  btn.closest('.ds-pax-card').remove();
  renumberPax();
  recalcSummary();
}

function renumberPax() {
  document.querySelectorAll('#passengerContainer .ds-pax-card').forEach((c,i) => {
    c.querySelector('.ds-pax-badge').textContent = 'Passenger ' + (i+1);
  });
  updateRemoveVis();
}

function updateRemoveVis() {
  const cards = document.querySelectorAll('#passengerContainer .ds-pax-card');
  cards.forEach(c => {
    const btn = c.querySelector('.ds-pax-remove');
    if (btn) btn.style.display = cards.length > 1 ? '' : 'none';
  });
}

function onTicketChange(sel) {
  const opt  = sel.options[sel.selectedIndex];
  const card = sel.closest('.ds-pax-card');
  const price = parseFloat(opt.dataset.price) || 0;
  const ci = card.querySelector('.pax-cost');
  if (ci) { ci.value = price.toFixed(2); }
  recalcSummary();
}

function toggleCostBank(sel) {
  const card = sel.closest('.ds-pax-card');
  if (!card) return;
  const bs = card.querySelector('.pax-cost-bank');
  if (!bs) return;
  bs.disabled = !!sel.value;
  if (sel.value) { bs.value = ''; $(bs).trigger('change'); }
}

// ── Direct Sale summary ───────────────────────────────────────
function recalcSummary() {
  let totalCost = 0, totalSale = 0;
  document.querySelectorAll('#passengerContainer .pax-cost').forEach(el => totalCost += parseFloat(el.value)||0);
  document.querySelectorAll('#passengerContainer .pax-sale').forEach(el => totalSale += parseFloat(el.value)||0);
  const profit = totalSale - totalCost;
  document.getElementById('sumCost').textContent   = totalCost.toFixed(2);
  document.getElementById('sumSale').textContent   = totalSale.toFixed(2);
  const pel = document.getElementById('sumProfit');
  pel.textContent = profit.toFixed(2);
  pel.closest('.ds-sum-value').style.color = profit >= 0 ? '#16a34a' : '#dc2626';
  document.getElementById('totalSaleDisplay').value = totalSale.toFixed(2);
  calcSaleDue();
}

function calcSaleDue() {
  const total = parseFloat(document.getElementById('totalSaleDisplay').value) || 0;
  const paid  = parseFloat(document.getElementById('salePaid').value) || 0;
  const due   = Math.max(0, total - paid).toFixed(2);
  document.getElementById('saleDueDisplay').textContent = due;
  document.getElementById('saleDue').value = due;
}

// ── Refund calc ───────────────────────────────────────────────
function calcRefund() {
  const a = parseFloat(document.getElementById('rAirlineRefund').value)||0;
  const p = parseFloat(document.getElementById('rPenalty').value)||0;
  const s = parseFloat(document.getElementById('rServiceCharge').value)||0;
  const net = Math.max(0, a-p-s);
  document.getElementById('rNetRefund').value = net.toFixed(2);
  document.getElementById('rCalcAirline').textContent = a.toFixed(2);
  document.getElementById('rCalcPenalty').textContent = p.toFixed(2);
  document.getElementById('rCalcService').textContent = s.toFixed(2);
  document.getElementById('rCalcNet').textContent     = net.toFixed(2);
  document.getElementById('rSumAirline').textContent  = a.toFixed(2);
  document.getElementById('rSumPenalty').textContent  = p.toFixed(2);
  document.getElementById('rSumNet').textContent      = net.toFixed(2);
}

// ── Re-issue calc ─────────────────────────────────────────────
function calcReissue() {
  const orgCost  = parseFloat(document.getElementById('riOrgCost').value)||0;
  const penalty  = parseFloat(document.getElementById('riPenalty').value)||0;
  const fareDiff = parseFloat(document.getElementById('riFareDiff').value)||0;
  const service  = parseFloat(document.getElementById('riService').value)||0;
  const newCost  = orgCost + penalty + fareDiff;
  const newSale  = parseFloat(document.getElementById('riNewSale').value)||0;
  const addCharge= penalty + fareDiff + service;
  document.getElementById('riNewCost').value = newCost.toFixed(2);
  document.getElementById('riCalcPenalty').textContent = penalty.toFixed(2);
  document.getElementById('riCalcFare').textContent    = fareDiff.toFixed(2);
  document.getElementById('riCalcService').textContent = service.toFixed(2);
  document.getElementById('riCalcTotal').textContent   = addCharge.toFixed(2);
  document.getElementById('riSumCost').textContent     = newCost.toFixed(2);
  document.getElementById('riSumSale').textContent     = newSale.toFixed(2);
  document.getElementById('riSumProfit').textContent   = (newSale - newCost).toFixed(2);
  document.getElementById('riPayDue').value            = addCharge.toFixed(2);
}

// ── Void calc ─────────────────────────────────────────────────
function calcVoid() {
  const orgCost  = parseFloat(document.getElementById('vOrgCost').value)||0;
  const penalty  = parseFloat(document.getElementById('vPenalty').value)||0;
  const orgSale  = parseFloat(document.getElementById('vOrgSale').value)||0;
  const agentFee = parseFloat(document.getElementById('vAgentFee').value)||0;
  const netCost  = Math.max(0, orgCost - penalty);
  const netSale  = Math.max(0, orgSale - penalty - agentFee);
  document.getElementById('vNetCost').value = netCost.toFixed(2);
  document.getElementById('vNetSale').value = netSale.toFixed(2);
  document.getElementById('vCalcSale').textContent    = orgSale.toFixed(2);
  document.getElementById('vCalcPenalty').textContent = penalty.toFixed(2);
  document.getElementById('vCalcFee').textContent     = agentFee.toFixed(2);
  document.getElementById('vCalcRefund').textContent  = netSale.toFixed(2);
  document.getElementById('vSumPenalty').textContent  = (penalty+agentFee).toFixed(2);
  document.getElementById('vSumRefund').textContent   = netSale.toFixed(2);
}

// ── EMD items ─────────────────────────────────────────────────
let emdCount = 1;
document.getElementById('addEmdItemBtn').addEventListener('click', addEmdItem);
document.getElementById('addAnotherEmd').addEventListener('click', addEmdItem);

function addEmdItem() {
  const idx = emdCount;
  const html = `
  <div class="ds-pax-card" data-emd="${idx+1}">
    <div class="ds-pax-h">
      <span class="ds-pax-badge" style="background:#f3e8ff;color:#7c3aed;">Item ${idx+1}</span>
      <button type="button" class="ds-pax-remove" onclick="removeEmdItem(this)">✕ Remove</button>
    </div>
    <div class="ds-row cols-3">
      <div><label class="ds-field-lbl">Passenger / Customer <span class="req">*</span></label><select class="select2-dyn ds-field-select" style="width:100%"><option value="">— Select passenger —</option>${phOptions}</select></div>
      <div><label class="ds-field-lbl">Service Type <span class="req">*</span></label><select class="select2-dyn ds-field-select" style="width:100%"><option value="">— Select type —</option><option>Excess Baggage</option><option>Seat Upgrade</option><option>Meal</option><option>Airport Tax</option><option>Visa Fee</option><option>Travel Insurance</option><option>Lounge Access</option><option>Other</option></select></div>
      <div><label class="ds-field-lbl">EMD / Reference No.</label><input type="text" placeholder="Enter EMD no." class="ds-field-input" style="border-color:#9333ea;"></div>
    </div>
    <div class="ds-row cols-4">
      <div><label class="ds-field-lbl">Vendor / Airline</label><select class="select2-dyn ds-field-select" style="width:100%"><option value="">— Select vendor —</option>${vendorOptions}</select></div>
      <div><label class="ds-field-lbl">Description</label><input type="text" placeholder="e.g. 23kg baggage" class="ds-field-input"></div>
      <div><label class="ds-field-lbl">Cost Price <span class="req">*</span></label><input type="number" placeholder="0.00" class="ds-field-input emd-cost" oninput="recalcEmd()"></div>
      <div><label class="ds-field-lbl">Sale Price <span class="req">*</span></label><input type="number" placeholder="0.00" class="ds-field-input sale-price emd-sale" oninput="recalcEmd()"></div>
    </div>
  </div>`;
  document.getElementById('emdItemContainer').insertAdjacentHTML('beforeend', html);
  $(document.getElementById('emdItemContainer').lastElementChild).find('.select2-dyn').select2();
  emdCount++;
  updateEmdRemoveVis();
}

function removeEmdItem(btn) {
  btn.closest('.ds-pax-card').remove();
  recalcEmd();
  updateEmdRemoveVis();
}

function updateEmdRemoveVis() {
  const cards = document.querySelectorAll('#emdItemContainer .ds-pax-card');
  cards.forEach(c => {
    const btn = c.querySelector('.ds-pax-remove');
    if (btn) btn.style.display = cards.length > 1 ? '' : 'none';
  });
}

function recalcEmd() {
  let tc = 0, ts = 0;
  document.querySelectorAll('#emdItemContainer .emd-cost').forEach(el => tc += parseFloat(el.value)||0);
  document.querySelectorAll('#emdItemContainer .emd-sale').forEach(el => ts += parseFloat(el.value)||0);
  document.getElementById('emdSumCost').textContent   = tc.toFixed(2);
  document.getElementById('emdSumSale').textContent   = ts.toFixed(2);
  document.getElementById('emdSumProfit').textContent = (ts-tc).toFixed(2);
  document.getElementById('emdTotalDisplay').value    = ts.toFixed(2);
  calcEmdDue();
}

function calcEmdDue() {
  const total = parseFloat(document.getElementById('emdTotalDisplay').value)||0;
  const paid  = parseFloat(document.getElementById('emdPaid').value)||0;
  document.getElementById('emdDueDisplay').textContent = Math.max(0, total-paid).toFixed(2);
}

// ── Direct Sale AJAX submit ───────────────────────────────────
$('#submitBtn').click(function(e) {
  e.preventDefault();
  if (!$('select[name="agent_id"]').val())
    return Swal.fire('Error!', 'Please select an agent.', 'error');
  if (!$('select[name="passengers[0][ticket_id]"]').val())
    return Swal.fire('Error!', 'Please select a ticket for Passenger 1.', 'error');
  if (!$('select[name="passengers[0][passport_holder_id]"]').val())
    return Swal.fire('Error!', 'Please select a passport holder for Passenger 1.', 'error');

  $.ajax({
    url: $('#directSaleForm').attr('action'),
    method: 'POST',
    data: new FormData($('#directSaleForm')[0]),
    processData: false,
    contentType: false,
    success: function(res) {
      if (res.success) {
        Swal.fire({ icon: 'success', title: 'Saved!', text: res.message });
        setTimeout(() => window.location.reload(), 900);
      } else {
        Swal.fire({ icon: 'error', title: 'Failed', text: res.message });
      }
    },
    error: function() {
      Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong.' });
    }
  });
});
</script>
@endsection
