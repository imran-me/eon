<style>
.tk-create-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9000;display:flex;align-items:center;justify-content:center;padding:1rem;}
.tk-create-overlay.hidden{display:none;}
.tk-create-box{background:#fff;border-radius:14px;width:100%;max-width:660px;box-shadow:0 20px 60px rgba(0,0,0,.18);overflow:hidden;animation:tkSlideIn .18s ease;font-family:'DM Sans',sans-serif;}
@keyframes tkSlideIn{from{transform:translateY(-20px);opacity:0}to{transform:translateY(0);opacity:1}}
.tk-create-head{padding:16px 20px;background:linear-gradient(135deg,#5b21b6,#7c3aed);display:flex;align-items:center;gap:10px;}
.tk-create-title{font-size:14px;font-weight:700;color:#fff;}
.tk-create-close{margin-left:auto;background:rgba(255,255,255,.2);border:none;color:#fff;width:28px;height:28px;border-radius:6px;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;transition:background .15s;line-height:1;}
.tk-create-close:hover{background:rgba(255,255,255,.35);}
.tk-create-body{padding:18px 20px;max-height:calc(90vh - 140px);overflow-y:auto;}
.tk-create-err{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:8px;padding:8px 12px;font-size:12px;margin-bottom:12px;display:none;}
.tk-create-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;}
.tk-create-grid .tk-full{grid-column:1/-1;}
.tk-create-grid .tk-half{grid-column:span 1;}
.tk-lbl{font-size:11px;font-weight:600;color:#5a6480;margin-bottom:5px;display:block;letter-spacing:.2px;}
.tk-lbl .req{color:#dc2626;margin-left:2px;}
.tk-input,.tk-select{width:100%;padding:9px 12px;border:1px solid #e4e8f0;border-radius:8px;font-size:13px;font-family:'DM Sans',sans-serif;outline:none;transition:border-color .12s;color:#1a2035;background:#fff;}
.tk-input:focus,.tk-select:focus{border-color:#2563eb;box-shadow:0 0 0 3px #eff6ff;}
.tk-create-foot{padding:14px 20px;background:#f8fafc;border-top:1px solid #e4e8f0;display:flex;justify-content:flex-end;gap:8px;}
.tk-btn{padding:7px 16px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:none;font-family:'DM Sans',sans-serif;transition:all .15s;display:inline-flex;align-items:center;gap:5px;}
.tk-btn-ghost{background:#f0f2f8;color:#5a6480;border:1px solid #e4e8f0;}
.tk-btn-ghost:hover{border-color:#d0d6e8;color:#1a2035;}
.tk-btn-save{background:#7c3aed;color:#fff;}
.tk-btn-save:hover{background:#6d28d9;}
.tk-check-wrap{display:flex;align-items:center;gap:8px;padding:9px 12px;border:1px solid #e4e8f0;border-radius:8px;background:#fff;cursor:pointer;}
.tk-check-wrap input[type="checkbox"]{width:16px;height:16px;accent-color:#7c3aed;cursor:pointer;}
.tk-check-wrap span{font-size:13px;color:#1a2035;font-weight:500;}

/* Select2 overrides inside this modal */
.tk-create-box .select2-container .select2-selection--single{height:38px;border:1px solid #e4e8f0;border-radius:8px;display:flex;align-items:center;}
.tk-create-box .select2-container--default .select2-selection--single .select2-selection__rendered{line-height:38px;padding-left:10px;font-size:13px;color:#1a2035;}
.tk-create-box .select2-container--default .select2-selection--single .select2-selection__arrow{height:36px;}
.tk-create-box .select2-container--default.select2-container--focus .select2-selection--single{border-color:#2563eb;box-shadow:0 0 0 3px #eff6ff;}
.tk-create-box .select2-container--default .select2-selection--single .select2-selection__placeholder{color:#9aa3bc;}
</style>

<div id="createModal" class="tk-create-overlay hidden modal-backdrop">
    <div class="tk-create-box">
        <div class="tk-create-head">
            <span style="font-size:18px;">✈</span>
            <span class="tk-create-title">Add New Ticket Route</span>
            <button type="button" class="tk-create-close modal-close-create">✕</button>
        </div>
        <div class="tk-create-body">
            <div class="tk-create-err" id="createModalErr"></div>
            <form class="closest" id="createForm" action="{{ route('role.tickets.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="tk-create-grid">
                    <div class="tk-full">
                        <label class="tk-lbl" for="create_title">Ticket Title <span class="req">*</span></label>
                        <input type="text" id="create_title" name="title" class="tk-input" placeholder="e.g. Dhaka to Kuala Lumpur">
                        <p id="create_title_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please enter a title</p>
                    </div>

                    <div>
                        <label class="tk-lbl" for="create_from_airport_id">From Airport</label>
                        <select id="create_from_airport_id" name="from_airport_id" class="select2" style="width:100%">
                            <option value="">— Select airport —</option>
                            @foreach ($airports as $from_airport)
                                <option value="{{ $from_airport->id }}">{{ $from_airport->name }}</option>
                            @endforeach
                        </select>
                        <p id="create_from_airport_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose an airport</p>
                    </div>

                    <div>
                        <label class="tk-lbl" for="create_to_airport_id">To Airport</label>
                        <select id="create_to_airport_id" name="to_airport_id" class="select2" style="width:100%">
                            <option value="">— Select airport —</option>
                            @foreach ($airports as $to_airport)
                                <option value="{{ $to_airport->id }}">{{ $to_airport->name }}</option>
                            @endforeach
                        </select>
                        <p id="create_to_airport_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose an airport</p>
                    </div>

                    <div>
                        <label class="tk-lbl" for="create_airline_id">Airline</label>
                        <select id="create_airline_id" name="airline_id" class="select2" style="width:100%">
                            <option value="">— Select airline —</option>
                            @foreach ($airlines as $airline)
                                <option value="{{ $airline->id }}">{{ $airline->name }}</option>
                            @endforeach
                        </select>
                        <p id="create_airline_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose an airline</p>
                    </div>

                    <div>
                        <label class="tk-lbl" for="create_vendor_id">Vendor <span class="req" title="At least one of vendor or portal is required">*</span></label>
                        <select id="create_vendor_id" name="vendor_id" class="select2" style="width:100%">
                            <option value="">— Select vendor —</option>
                            @foreach ($vendors as $vendor)
                                <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                            @endforeach
                        </select>
                        <p id="create_vendor_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a vendor</p>
                    </div>

                    <div>
                        <label class="tk-lbl" for="create_portal_id">Portal <span class="req" title="At least one of vendor or portal is required">*</span></label>
                        <select id="create_portal_id" name="portal_id" class="select2" style="width:100%">
                            <option value="">— Select portal —</option>
                            @foreach ($portals as $portal)
                                <option value="{{ $portal->id }}">{{ $portal->name }}</option>
                            @endforeach
                        </select>
                        <p id="create_portal_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a portal</p>
                    </div>

                    <div>
                        <label class="tk-lbl" for="create_price">Default Cost Price</label>
                        <input type="number" id="create_price" name="price" value="0" min="0" step="0.01" class="tk-input" placeholder="0.00">
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a price</p>
                    </div>

                    <div>
                        <label class="tk-lbl" for="create_qty">Total Stock Quantity</label>
                        <input type="number" id="create_qty" name="qty" value="0" min="0" class="tk-input" placeholder="0">
                        <p class="text-red-500 text-xs mt-1 hidden error-message">Please enter a quantity</p>
                    </div>

                    <div style="display:flex;flex-direction:column;justify-content:flex-end;">
                        <label class="tk-lbl">&nbsp;</label>
                        <label class="tk-check-wrap">
                            <input type="checkbox" id="create_status" name="status" checked>
                            <span>Active</span>
                        </label>
                    </div>
                </div>
            </form>
        </div>
        <div class="tk-create-foot">
            <button type="button" class="tk-btn tk-btn-ghost modal-close-create">✕ Cancel</button>
            <button id="createSubmit" type="button" class="tk-btn tk-btn-save">💾 Save Ticket Route</button>
        </div>
    </div>
</div>
