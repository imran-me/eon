<style>
/* ── Ticket Route modal (restyled 2026-09-21) ─────────────────────────── */
.tk-create-overlay{position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(2px);z-index:9000;display:flex;align-items:center;justify-content:center;padding:1rem;}
.tk-create-overlay.hidden{display:none;}
.tk-create-box{background:#fff;border-radius:18px;width:100%;max-width:1100px;max-height:calc(100vh - 2rem);display:flex;flex-direction:column;box-shadow:0 30px 80px rgba(15,23,42,.35);overflow:hidden;animation:tkSlideIn .2s ease;font-family:'DM Sans',sans-serif;}
@keyframes tkSlideIn{from{transform:translateY(-16px) scale(.985);opacity:0}to{transform:none;opacity:1}}
.tk-create-head{position:relative;padding:18px 24px;background:linear-gradient(120deg,#4c1d95 0%,#6d28d9 55%,#7c3aed 100%);display:flex;align-items:center;gap:14px;color:#fff;}
.tk-create-head::after{content:'';position:absolute;inset:0;background:radial-gradient(circle at 85% -20%,rgba(255,255,255,.22),transparent 45%);pointer-events:none;}
.tk-head-ic{width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;}
.tk-create-title{font-size:16px;font-weight:700;letter-spacing:.2px;}
.tk-create-sub{font-size:12px;color:rgba(255,255,255,.75);margin-top:2px;}
.tk-create-close{position:relative;margin-left:auto;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);color:#fff;width:34px;height:34px;border-radius:50%;cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;transition:background .15s;flex-shrink:0;}
.tk-create-close:hover{background:rgba(255,255,255,.3);}
.tk-create-body{padding:20px 24px 22px;flex:1 1 auto;min-height:0;overflow-y:auto;scrollbar-gutter:stable;background:#fbfbfe;}
.tk-create-err{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:10px;padding:9px 12px;font-size:12px;margin-bottom:12px;display:none;}
/* minmax(0,1fr), not 1fr: a bare 1fr floors at min-content, and the long
   airport names in the select2 boxes then push the 3rd column past the
   card, where overflow:hidden clips it. */
.tk-create-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px 16px;}
.tk-create-grid > *{min-width:0;}
.tk-create-grid .tk-full{grid-column:1/-1;}
.tk-create-grid .tk-half{grid-column:span 1;}
@media (max-width:900px){.tk-create-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media (max-width:600px){.tk-create-grid{grid-template-columns:minmax(0,1fr);}}
/* section headings inside the grid */
.tk-section{grid-column:1/-1;display:flex;align-items:center;gap:10px;margin-top:6px;font-size:10.5px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;color:#6d28d9;}
.tk-section:first-child{margin-top:0;}
.tk-section i{width:24px;height:24px;border-radius:7px;background:#ede9fe;display:inline-flex;align-items:center;justify-content:center;font-size:11px;color:#6d28d9;}
.tk-section::after{content:'';flex:1;height:1px;background:linear-gradient(90deg,#ddd6fe,transparent);}
.tk-lbl{font-size:10.5px;font-weight:700;color:#64748b;margin-bottom:6px;display:block;letter-spacing:.5px;text-transform:uppercase;}
.tk-lbl .req{color:#dc2626;margin-left:2px;}
.tk-lbl span:not(.req){text-transform:none;letter-spacing:0;font-weight:500;}
.tk-input,.tk-select{box-sizing:border-box;width:100%;height:42px;padding:9px 13px;border:1px solid #dfe3ee;border-radius:10px;font-size:13.5px;font-family:'DM Sans',sans-serif;outline:none;transition:border-color .12s,box-shadow .12s;color:#0f172a;background:#fff;}
.tk-input:focus,.tk-select:focus{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12);}
.tk-create-foot{padding:14px 24px;background:#fff;border-top:1px solid #eceef5;display:flex;justify-content:flex-end;gap:10px;}
.tk-btn{padding:10px 18px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;border:none;font-family:'DM Sans',sans-serif;transition:all .15s;display:inline-flex;align-items:center;gap:7px;}
.tk-btn-ghost{background:#fff;color:#475569;border:1px solid #dfe3ee;}
.tk-btn-ghost:hover{border-color:#c7cddb;color:#0f172a;background:#f8fafc;}
.tk-btn-save{background:linear-gradient(120deg,#6d28d9,#7c3aed);color:#fff;box-shadow:0 8px 20px rgba(109,40,217,.28);}
.tk-btn-save:hover{background:linear-gradient(120deg,#5b21b6,#6d28d9);transform:translateY(-1px);}
.tk-check-wrap{display:flex;align-items:center;gap:9px;height:42px;padding:0 13px;border:1px solid #dfe3ee;border-radius:10px;background:#fff;cursor:pointer;}
.tk-check-wrap input[type="checkbox"]{width:16px;height:16px;accent-color:#7c3aed;cursor:pointer;}
.tk-check-wrap span{font-size:13.5px;color:#0f172a;font-weight:500;}

/* Select2 overrides inside this modal */
.tk-create-box .select2-container .select2-selection--single{height:42px;border:1px solid #dfe3ee;border-radius:10px;display:flex;align-items:center;background:#fff;}
.tk-create-box .select2-container{max-width:100%;}
.tk-create-box .select2-container--default .select2-selection--single .select2-selection__rendered{line-height:40px;padding-left:13px;padding-right:28px;font-size:13.5px;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.tk-create-box .select2-container--default .select2-selection--single .select2-selection__arrow{height:40px;right:6px;}
.tk-create-box .select2-container--default.select2-container--focus .select2-selection--single,.tk-create-box .select2-container--default.select2-container--open .select2-selection--single{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12);}
.tk-create-box .select2-container--default .select2-selection--single .select2-selection__placeholder{color:#94a3b8;}
</style>

<div id="createModal" class="tk-create-overlay hidden modal-backdrop">
    <div class="tk-create-box">
        <div class="tk-create-head">
            <span class="tk-head-ic"><i class="fas fa-route"></i></span>
            <div>
                <div class="tk-create-title">Add New Ticket Route</div>
                <div class="tk-create-sub">Route, seller and pricing — used by Ticketing and Contract Flights. Add via airports — each a transit or where a new ticket starts.</div>
            </div>
            <button type="button" class="tk-create-close modal-close-create"><i class="fas fa-times"></i></button>
        </div>
        <div class="tk-create-body">
            <div class="tk-create-err" id="createModalErr"></div>
            <form class="closest" id="createForm" action="{{ route('role.tickets.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="tk-create-grid">
                    <div class="tk-section"><i class="fas fa-map"></i> Route</div>
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
                                <option value="{{ $from_airport->id }}" data-flag="{{ strtolower($from_airport->country?->code ?? '') }}">{{ $from_airport->name }}</option>
                            @endforeach
                        </select>
                        <p id="create_from_airport_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose an airport</p>
                    </div>

                    <div>
                        <label class="tk-lbl" for="create_to_airport_id">To Airport</label>
                        <select id="create_to_airport_id" name="to_airport_id" class="select2" style="width:100%">
                            <option value="">— Select airport —</option>
                            @foreach ($airports as $to_airport)
                                <option value="{{ $to_airport->id }}" data-flag="{{ strtolower($to_airport->country?->code ?? '') }}">{{ $to_airport->name }}</option>
                            @endforeach
                        </select>
                        <p id="create_to_airport_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose an airport</p>
                    </div>

                    {{-- Via airports (owner, 2026-10-11: several via airports). Each stop is a TRANSIT inside
                         a ticket or the place a NEW TICKET starts; the ticket cards below follow
                         (tickets.route-via). The hidden via_airport_id = first stop, which keeps the
                         server's "route has stops" rules working; route_stops[] carries the list. --}}
                    <div class="tk-stops-wrap" style="grid-column:1 / -1;">
                        <label class="tk-lbl">Via airports <span style="font-weight:400;color:#8a94a6;">— optional. Add each stop in order; mark where a new ticket starts</span></label>
                        <input type="hidden" id="create_via_airport_id" name="via_airport_id" value="">
                        <div class="tk-stops" id="create_stops"></div>
                        <button type="button" class="tk-stop-add" data-prefix="create"><i class="fas fa-plus"></i> Add via airport</button>
                    </div>
                    @include('tickets.route-trip', ['prefix' => 'create'])

                    <div class="tk-section tk-direct-only"><i class="fas fa-tags"></i> Seller &amp; pricing</div>
                    <div class="tk-direct-only">
                        <label class="tk-lbl" for="create_airline_id">Airline <span style="font-weight:400;opacity:.6">(optional)</span></label>
                        <select id="create_airline_id" name="airline_id" class="select2" style="width:100%">
                            <option value="">— Select airline —</option>
                            @foreach ($airlines as $airline)
                                <option value="{{ $airline->id }}">{{ $airline->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs mt-1" style="color:#94a3b8">Leave blank to choose the airline on each ticket.</p>
                    </div>

                    <div class="tk-direct-only">
                        <label class="tk-lbl" for="create_vendor_id">Vendor <span class="req" title="At least one of vendor or portal is required">*</span></label>
                        <select id="create_vendor_id" name="vendor_id" class="select2" style="width:100%">
                            <option value="">— Select vendor —</option>
                            @foreach ($vendors as $vendor)
                                <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                            @endforeach
                        </select>
                        <p id="create_vendor_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a vendor</p>
                    </div>

                    <div class="tk-direct-only">
                        <label class="tk-lbl" for="create_portal_id">Portal <span class="req" title="At least one of vendor or portal is required">*</span></label>
                        <select id="create_portal_id" name="portal_id" class="select2" style="width:100%">
                            <option value="">— Select portal —</option>
                            @foreach ($portals as $portal)
                                <option value="{{ $portal->id }}">{{ $portal->name }}</option>
                            @endforeach
                        </select>
                        <p id="create_portal_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a portal</p>
                    </div>

                    <div class="tk-direct-only">
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
                    @include('tickets.route-via', ['prefix' => 'create'])
                </div>
            </form>
        </div>
        <div class="tk-create-foot">
            <button type="button" class="tk-btn tk-btn-ghost modal-close-create">Cancel</button>
            <button id="createSubmit" type="button" class="tk-btn tk-btn-save"><i class="fas fa-check"></i> Save Ticket Route</button>
        </div>
    </div>
</div>
