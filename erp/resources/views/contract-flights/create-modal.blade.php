<style>
.fcm-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9000;display:flex;align-items:flex-start;justify-content:center;padding:24px 16px;overflow-y:auto;}
.fcm-overlay.hidden{display:none;}
.fcm-box{background:#fff;border-radius:12px;width:100%;max-width:1120px;box-shadow:0 20px 60px rgba(0,0,0,.18);overflow:hidden;animation:fcmSlideIn .18s ease;font-family:'DM Sans',sans-serif;}
@keyframes fcmSlideIn{from{transform:translateY(-20px);opacity:0}to{transform:translateY(0);opacity:1}}
.fcm-head{padding:18px 22px;background:linear-gradient(135deg,#ea580c,#c2410c);display:flex;align-items:center;gap:12px;}
.fcm-head-ic{width:38px;height:38px;border-radius:10px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;flex-shrink:0;}
.fcm-title{font-size:15px;font-weight:700;color:#fff;}
.fcm-sub{font-size:11.5px;color:#fed7aa;margin-top:1px;}
.fcm-close{margin-left:auto;background:rgba(255,255,255,.2);border:none;color:#fff;width:30px;height:30px;border-radius:8px;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;transition:background .15s;}
.fcm-close:hover{background:rgba(255,255,255,.35);}
.fcm-body{padding:22px;max-height:calc(100vh - 220px);overflow-y:auto;}
.fcm-section{margin-bottom:20px;}
.fcm-section:last-child{margin-bottom:0;}
.fcm-section-title{font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#6b7280;display:flex;align-items:center;gap:6px;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid #f3f4f6;}
.fcm-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.fcm-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;}
.fcm-grid-4{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;}
.fcm-grid-2 .fcm-full,.fcm-grid-3 .fcm-full,.fcm-grid-4 .fcm-full{grid-column:1/-1;}
.fcm-lbl{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;}
.fcm-lbl sup,.fcm-lbl .req{color:#ef4444;}
.fcm-input,.fcm-select{width:100%;border:1px solid #d1d5db;border-radius:10px;padding:9px 12px;font-size:13px;outline:none;transition:border-color .15s;font-family:'DM Sans',sans-serif;color:#1a2035;background:#fff;}
.fcm-input:focus,.fcm-select:focus{border-color:#ea580c;box-shadow:0 0 0 3px rgba(234,88,12,.1);}
.fcm-foot{padding:14px 22px;background:#f8fafc;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:8px;}
.fcm-btn{padding:9px 18px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;border:none;font-family:'DM Sans',sans-serif;transition:all .15s;display:inline-flex;align-items:center;gap:6px;}
.fcm-btn-ghost{background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;}
.fcm-btn-ghost:hover{background:#e5e7eb;}
.fcm-btn-save{background:#ea580c;color:#fff;}
.fcm-btn-save:hover{background:#c2410c;}
.fcm-route-row{display:flex;gap:8px;align-items:flex-start;}
.fcm-route-row .fcm-select-wrap{flex:1;}
.fcm-add-route-btn{height:40px;padding:0 12px;border-radius:10px;background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;flex-shrink:0;}
.fcm-add-route-btn:hover{background:#fed7aa;}
.fcm-readonly{background:#f1f5f9!important;color:#334155;font-weight:700;}
.fcm-positive{background:#ecfdf5!important;color:#15803d;font-weight:700;}
.fcm-help{font-size:11px;color:#64748b;margin-top:7px;}
.fcm-passenger-row{display:flex;gap:8px;align-items:flex-start;}
.fcm-passenger-row .select2-container{flex:1;}
.fcm-mini-add{height:40px;width:40px;border:1px solid #bfdbfe;background:#eff6ff;color:#1d4ed8;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;flex:0 0 40px;cursor:pointer;}
.handling-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.handling-card{position:relative;border:1px solid #dbe3ef;border-radius:8px;padding:13px 14px 13px 42px;cursor:pointer;background:#fff}.handling-card input{position:absolute;left:15px;top:17px}.handling-card:has(input:checked){border-color:#2563eb;background:#eff6ff}.handling-title{font-size:12px;font-weight:800;color:#374151;text-transform:uppercase}.handling-sub{font-size:10.5px;color:#64748b;margin-top:3px;line-height:1.35}@media(max-width:760px){.handling-grid,.fcm-grid-2,.fcm-grid-3,.fcm-grid-4{grid-template-columns:1fr}.fcm-box{max-width:100%}}

.fcm-box .select2-container .select2-selection--single{height:40px !important;border:1px solid #d1d5db;border-radius:10px;display:flex;align-items:center;}
.fcm-box .select2-container--default .select2-selection--single .select2-selection__rendered{line-height:38px;padding-left:10px;font-size:13px;color:#1a2035;}
.fcm-box .select2-container--default .select2-selection--single .select2-selection__arrow{height:38px;}
.fcm-box .select2-container--default.select2-container--focus .select2-selection--single{border-color:#ea580c;box-shadow:0 0 0 3px rgba(234,88,12,.1);}

/* Quick-create Ticket Route overlay (nested above create/edit modal) */
.tkr-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9500;display:none;align-items:center;justify-content:center;padding:1rem;}
.tkr-overlay.open{display:flex;}
.tkr-box{background:#fff;border-radius:14px;width:100%;max-width:560px;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;font-family:'DM Sans',sans-serif;max-height:90vh;overflow-y:auto;}
.tkr-head{padding:16px 20px;background:linear-gradient(135deg,#5b21b6,#7c3aed);display:flex;align-items:center;gap:10px;}
.tkr-head-title{font-size:14px;font-weight:700;color:#fff;}
.tkr-head-close{margin-left:auto;background:rgba(255,255,255,.2);border:none;color:#fff;width:28px;height:28px;border-radius:6px;cursor:pointer;font-size:16px;}
.tkr-body{padding:18px 20px;}
.tkr-err{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:8px;padding:8px 12px;font-size:12px;margin-bottom:12px;display:none;}
.tkr-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.tkr-grid .tkr-full{grid-column:1/-1;}
.tkr-foot{padding:14px 20px;background:#f8fafc;border-top:1px solid #e4e8f0;display:flex;justify-content:flex-end;gap:8px;}
</style>

@php
    $flightStoreRoute = $flightStoreRoute ?? route('role.contract-flights.store', ['role' => $role]);
    $flightCreateTitle = $flightCreateTitle ?? 'Add Contract Flight';
    $flightCreateSub = $flightCreateSub ?? 'Schedule a flight, set seats & assign an agent';
@endphp

<div id="createModal" class="fcm-overlay hidden modal-backdrop">
    <div class="fcm-box">
        <div class="fcm-head">
            <div class="fcm-head-ic"><i class="fas fa-plane"></i></div>
            <div>
                <div class="fcm-title">{{ $flightCreateTitle }}</div>
                <div class="fcm-sub">{{ $flightCreateSub }}</div>
            </div>
            <button type="button" class="fcm-close modal-close-create">✕</button>
        </div>
        <div class="fcm-body">
            <form class="closest" id="createForm"
                action="{{ $flightStoreRoute }}"
                method="POST">
                @csrf

                {{-- Trip and ticket --}}
                <div class="fcm-section">
                    <div class="fcm-section-title"><i class="fas fa-plane mr-1.5"></i> Trip &amp; Ticket</div>
                    <div class="fcm-grid-4">
                        <div>
                            <label class="fcm-lbl">Flight Category <sup>*</sup></label>
                            <select id="create_flight_category_id" name="flight_category_id" class="fcm-select" style="width:100%">
                                <option value="">— Select Category —</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                            <p id="create_flight_category_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select a category</p>
                        </div>
                        <div>
                            <label class="fcm-lbl">Category Type</label>
                            <select id="create_flight_category_type_id" name="flight_category_type_id" class="fcm-select category-type-schedule" style="width:100%">
                                <option value="">Select category type</option>
                                @foreach($categoryTypes as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="fcm-lbl">Airline <sup>*</sup></label>
                            <select id="create_pricing_airline_id" class="fcm-select pricing-airline" style="width:100%">
                                <option value="">Select airline</option>
                                @foreach($airlines as $airline)
                                    <option value="{{ $airline->id }}">{{ $airline->name }}{{ $airline->code ? ' ('.$airline->code.')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="fcm-lbl">Airline Flight No <sup>*</sup></label>
                            <input type="text" id="create_airline_flight_no" name="airline_flight_no" class="fcm-input" placeholder="e.g. BG-088">
                            <p id="create_airline_flight_no_msg" class="text-red-500 text-xs mt-1 hidden error-message">Flight number is required.</p>
                        </div>
                        <div class="fcm-full">
                            <label class="fcm-lbl">Ticket Route <sup>*</sup> <span class="text-gray-400 font-normal">(from Ticketing — Airline, From/To Airport)</span></label>
                            <div class="fcm-route-row">
                                <div class="fcm-select-wrap">
                                    <select id="create_ticket_id" name="ticket_id" class="ticket-route-select" style="width:100%">
                                        <option value="">— Select Ticket Route —</option>
                                        @foreach($tickets as $ticket)
                                            <option value="{{ $ticket->id }}" data-airline-id="{{ $ticket->airline_id }}">
                                                {{ $ticket->title }}{{ $ticket->airline ? ' — '.$ticket->airline->name : '' }}{{ ($ticket->from_airport && $ticket->to_airport) ? ' ('.$ticket->from_airport->code.' → '.$ticket->to_airport->code.')' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="button" class="fcm-add-route-btn" onclick="openTicketRouteModal('create_ticket_id')"><i class="fas fa-plus"></i> New Route</button>
                            </div>
                            <p id="create_ticket_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select a ticket route</p>
                        </div>
                        <div>
                            <label class="fcm-lbl">Class <sup>*</sup></label>
                            <select id="create_ticket_class" name="ticket_class" class="fcm-select">
                                <option value="economy">Economy</option>
                                <option value="business">Business</option>
                                <option value="first">First Class</option>
                            </select>
                        </div>
                        <div>
                            <label class="fcm-lbl">Departure Date / Time <sup>*</sup></label>
                            <input type="datetime-local" id="create_departure_at" name="departure_at" class="fcm-input">
                            <p id="create_departure_at_msg" class="text-red-500 text-xs mt-1 hidden error-message">Departure date and time are required.</p>
                        </div>
                        <div>
                            <label class="fcm-lbl">Arrival Date / Time <span class="text-gray-400 font-normal">(destination)</span></label>
                            <input type="datetime-local" id="create_arrival_at" name="arrival_at" class="fcm-input">
                            <p id="create_arrival_at_msg" class="text-red-500 text-xs mt-1 hidden error-message">Arrival cannot be before departure.</p>
                        </div>
                    </div>
                </div>

                <div class="fcm-section">
                    <div class="fcm-section-title"><i class="fas fa-users-cog mr-1.5"></i> Handling Type</div>
                    <div class="handling-grid">
                        <label class="handling-card"><input type="radio" name="handling_type" value="manpower_wise" checked><div class="handling-title"><i class="fas fa-suitcase"></i> Manpower-wise</div><div class="handling-sub">No immigration officer needed - boarding support only.</div></label>
                        <label class="handling-card"><input type="radio" name="handling_type" value="immigration_wise"><div class="handling-title"><i class="fas fa-passport"></i> Immigration-wise</div><div class="handling-sub">An officer handles boarding and immigration.</div></label>
                    </div>
                </div>

                <div class="fcm-section">
                    <div class="fcm-section-title"><i class="fas fa-user-shield mr-1.5"></i> Officers &amp; Process</div>
                    <div class="fcm-grid-3">
                        <div>
                            <label class="fcm-lbl">Boarding Officer <sup>*</sup></label>
                            <select id="create_boarding_officer_id" name="boarding_officer_id" class="fcm-select officer-select" style="width:100%">
                                <option value="">Select boarding officer</option>
                                @foreach($officers as $officer)
                                    @if(in_array('boarding', $officer->work_roles ?? []))
                                        <option value="{{ $officer->id }}" data-airline-id="{{ $officer->airline_id }}">{{ $officer->user?->name }}{{ $officer->airline ? ' - '.$officer->airline->name : '' }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <p id="create_boarding_officer_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select a boarding officer.</p>
                        </div>
                        <div>
                            <label class="fcm-lbl">Immigration Officer</label>
                            <select id="create_immigration_officer_id" name="immigration_officer_id" class="fcm-select officer-select immigration-officer" style="width:100%">
                                <option value="">Select immigration officer</option>
                                @foreach($officers as $officer)
                                    @if(in_array('immigration', $officer->work_roles ?? []))
                                        <option value="{{ $officer->id }}" data-airline-id="{{ $officer->airline_id }}">{{ $officer->user?->name }}{{ $officer->airline ? ' - '.$officer->airline->name : '' }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <p id="create_immigration_officer_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Immigration officer is required for immigration-wise handling.</p>
                        </div>
                        <div>
                            <label class="fcm-lbl">Manpower Handler / Agent</label>
                            <select id="create_agent_id" name="agent_id" class="fcm-select" style="width:100%">
                                <option value="">Direct / In-house</option>
                                @foreach($agents as $agent)<option value="{{ $agent->id }}">{{ $agent->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="fcm-lbl">Vendor (payable to)</label>
                            <select id="create_vendor_id" name="vendor_id" class="fcm-select" style="width:100%" onchange="flightOnVendorChange(this)">
                                <option value="">— No Vendor / Unassigned —</option>
                                @foreach($vendors as $vendor)<option value="{{ $vendor->id }}">{{ $vendor->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="fcm-lbl">Portal</label>
                            <select id="create_portal_id" name="portal_id" class="fcm-select" style="width:100%" onchange="flightOnPortalChange(this)">
                                <option value="">— No Portal —</option>
                                @foreach($portals as $portal)<option value="{{ $portal->id }}">{{ $portal->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="fcm-lbl">Purchase Date</label>
                            <input type="date" id="create_purchase_date" name="purchase_date" class="fcm-input">
                        </div>
                        <div>
                            <label class="fcm-lbl">Cost Paid (৳)</label>
                            <input type="number" min="0" step="0.01" id="create_cost_paid_amount" name="cost_paid_amount" class="fcm-input" value="0">
                        </div>
                        <div id="create_cost_bank_wrap">
                            <label class="fcm-lbl">Cost Bank</label>
                            <select id="create_cost_bank_id" name="cost_bank_id" class="fcm-select" style="width:100%">
                                <option value="">— Select Bank —</option>
                                @foreach($banks as $bank)<option value="{{ $bank->id }}">{{ $bank->name }} — {{ $bank->account_number }}</option>@endforeach
                            </select>
                        </div>
                        <div id="create_cost_sched_wrap">
                            <label class="fcm-lbl">Cost Due Date <span class="text-gray-400 font-normal">(payable schedule)</span></label>
                            <input type="date" id="create_payable_date" name="payable_date" class="fcm-input">
                        </div>
                    </div>
                </div>

                <div class="fcm-section">
                    <div class="fcm-section-title"><i class="fas fa-coins mr-1.5"></i> Price Builder</div>
                    <div class="fcm-grid-4">
                        <div>
                            <label class="fcm-lbl">Ticket / Pax (BDT)</label>
                            <input type="number" id="create_ticket_cost_per_pax" name="ticket_cost_per_pax" min="0" step="0.01" class="fcm-input price-input" value="0">
                        </div>
                        <div>
                            <label class="fcm-lbl">Manpower / Pax (BDT)</label>
                            <input type="number" id="create_manpower_cost_per_pax" name="manpower_cost_per_pax" min="0" step="0.01" class="fcm-input price-input" value="0">
                        </div>
                        <div>
                            <label class="fcm-lbl">Boarding / Pax (BDT)</label>
                            <input type="number" id="create_boarding_cost_per_pax" name="boarding_cost_per_pax" min="0" step="0.01" class="fcm-input price-input" value="0">
                        </div>
                        <div>
                            <label class="fcm-lbl">Immigration / Pax (BDT)</label>
                            <input type="number" id="create_immigration_cost_per_pax" name="immigration_cost_per_pax" min="0" step="0.01" class="fcm-input price-input immigration-cost" value="0">
                        </div>
                        <div>
                            <label class="fcm-lbl">Total Cost / Pax</label>
                            <input type="number" id="create_total_cost_per_pax" class="fcm-input fcm-readonly" readonly value="0">
                        </div>
                        <div>
                            <label class="fcm-lbl">Sale Price / Pax <sup>*</sup></label>
                            <input type="number" id="create_sale_price_per_pax" name="sale_price_per_pax" min="0" step="0.01" class="fcm-input price-input" value="0">
                            <p id="create_sale_price_per_pax_msg" class="text-red-500 text-xs mt-1 hidden error-message">Sale price is required and cannot be negative.</p>
                        </div>
                        <div>
                            <label class="fcm-lbl">Profit / Pax</label>
                            <input type="number" id="create_profit_per_pax" class="fcm-input fcm-positive" readonly value="0">
                        </div>
                        <div>
                            <label class="fcm-lbl">Margin</label>
                            <input type="text" id="create_margin" class="fcm-input fcm-readonly" readonly value="0%">
                        </div>
                    </div>
                    <div id="create_preset_hint" class="fcm-help">Select an airline to load the closest matching active pricing preset.</div>
                </div>

                <div class="fcm-section">
                    <div class="fcm-section-title"><i class="fas fa-passport mr-1.5"></i> Passengers</div>
                    <label class="fcm-lbl">Passport Holders</label>
                    <div class="fcm-passenger-row">
                        <select id="create_passenger_ids" name="passenger_ids[]" class="passenger-select" multiple style="width:100%">
                            @foreach($passportHolders as $holder)
                                <option value="{{ $holder->id }}">{{ $holder->name }} - {{ $holder->passport_no }}{{ $holder->phone ? ' - '.$holder->phone : '' }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="fcm-mini-add" onclick="openFlightPassengerModal('create_passenger_ids')" title="Add passport holder"><i class="fas fa-plus"></i></button>
                    </div>
                </div>

                {{-- Capacity and totals --}}
                <div class="fcm-section">
                    <div class="fcm-section-title"><i class="fas fa-chair mr-1.5"></i> Capacity &amp; Totals</div>
                    <div class="fcm-grid-4">
                        <div>
                            <label class="fcm-lbl">Total Seats <sup>*</sup></label>
                            <input type="number" id="create_total_seats" name="total_seats" min="0" class="fcm-input" placeholder="0">
                            <p id="create_total_seats_msg" class="text-red-500 text-xs mt-1 hidden error-message">Required</p>
                        </div>
                        <div>
                            <label class="fcm-lbl">Seats Sold</label>
                            <input type="number" id="create_seats_sold" name="seats_sold" min="0" class="fcm-input" placeholder="0">
                            <p id="create_seats_sold_msg" class="text-red-500 text-xs mt-1 hidden error-message">Seats sold cannot exceed total seats.</p>
                        </div>
                        <div>
                            <label class="fcm-lbl">Cost Price (৳)</label>
                            <input type="number" id="create_cost_price" name="cost_price" min="0" step="0.01" class="fcm-input fcm-readonly" value="0" readonly>
                        </div>
                        <div>
                            <label class="fcm-lbl">Revenue (৳)</label>
                            <input type="number" id="create_revenue" name="revenue" min="0" step="0.01" class="fcm-input fcm-readonly" value="0" readonly>
                        </div>
                    </div>
                </div>

                {{-- Assignment & Status --}}
                <div class="fcm-section">
                    <div class="fcm-section-title"><i class="fas fa-user-tie mr-1.5"></i> Assignment &amp; Status</div>
                    <div class="fcm-grid-2">
                        <div style="display:none">
                            <label class="fcm-lbl">Agent <span class="text-gray-400 font-normal">(leave blank for Direct)</span></label>
                            <select class="fcm-select" style="width:100%" disabled>
                                <option value="">— Direct —</option>
                                @foreach($agents as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="fcm-lbl">Status</label>
                            <select id="create_status" name="status" class="fcm-select">
                                <option value="open" selected>Open</option>
                                <option value="boarding">Boarding</option>
                                <option value="departed">Departed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="fcm-full">
                            <label class="fcm-lbl">Notes</label>
                            <textarea id="create_notes" name="notes" rows="2" class="fcm-input" placeholder="Internal notes about this flight..."></textarea>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="fcm-foot">
            <button type="button" class="fcm-btn fcm-btn-ghost modal-close-create">✕ Cancel</button>
            <button id="createSubmit" type="button" class="fcm-btn fcm-btn-save"><i class="fas fa-save"></i> Save Flight</button>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════
     QUICK-CREATE TICKET ROUTE (shared by create & edit modals)
     Reuses the same fields/endpoint as Tickets → Add New Ticket
══════════════════════════════════════════ --}}
<div class="tkr-overlay" id="flightPassengerOverlay">
    <div class="tkr-box">
        <div class="tkr-head" style="background:#2563eb">
            <span class="tkr-head-title"><i class="fas fa-passport"></i> Add Passport Holder</span>
            <button type="button" class="tkr-head-close" onclick="closeFlightPassengerModal()">&times;</button>
        </div>
        <div class="tkr-body">
            <div class="tkr-err" id="flightPassengerError"></div>
            <div class="tkr-grid">
                <div><label class="fcm-lbl">Passenger Name *</label><input id="flightPassengerName" class="fcm-input" placeholder="Full name as passport"></div>
                <div><label class="fcm-lbl">Passport No *</label><input id="flightPassengerPassport" class="fcm-input" placeholder="e.g. EB0912345"></div>
                <div><label class="fcm-lbl">Nationality *</label><input id="flightPassengerNationality" class="fcm-input" placeholder="e.g. Bangladeshi"></div>
                <div><label class="fcm-lbl">Phone</label><input id="flightPassengerPhone" class="fcm-input" placeholder="+880 1XXX-XXXXXX"></div>
                <div class="tkr-full">
                    <label class="fcm-lbl">Passport Holder Category *</label>
                    <select id="flightPassengerCategory" class="fcm-select">
                        <option value="">Select category</option>
                        @foreach($passportHolderCategories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                    </select>
                </div>
                <div class="tkr-full">
                    <label class="fcm-lbl">Type *</label>
                    <select id="flightPassengerType" class="fcm-select">
                        @include('passport-holder.type-options')
                    </select>
                </div>
            </div>
        </div>
        <div class="tkr-foot">
            <button type="button" class="fcm-btn fcm-btn-ghost" onclick="closeFlightPassengerModal()">Cancel</button>
            <button type="button" id="flightPassengerSave" class="fcm-btn fcm-btn-save" onclick="saveFlightPassenger()"><i class="fas fa-save"></i> Save Passenger</button>
        </div>
    </div>
</div>

<div class="tkr-overlay" id="tkrOverlay">
    <div class="tkr-box">
        <div class="tkr-head">
            <span style="font-size:18px;">✈</span>
            <span class="tkr-head-title">Add New Ticket Route</span>
            <button type="button" class="tkr-head-close" onclick="closeTicketRouteModal()">✕</button>
        </div>
        <div class="tkr-body">
            <div class="tkr-err" id="tkrErr"></div>
            <div class="tkr-grid">
                <div class="tkr-full">
                    <label class="fcm-lbl">Ticket Title <span class="req">*</span></label>
                    <input type="text" id="tkrTitle" class="fcm-input" placeholder="e.g. Dhaka to Jeddah">
                </div>
                <div>
                    <label class="fcm-lbl">From Airport</label>
                    <select id="tkrFromAirport" class="fcm-select" style="width:100%">
                        <option value="">— Select airport —</option>
                        @foreach($airports as $airport)
                            <option value="{{ $airport->id }}">{{ $airport->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="fcm-lbl">To Airport</label>
                    <select id="tkrToAirport" class="fcm-select" style="width:100%">
                        <option value="">— Select airport —</option>
                        @foreach($airports as $airport)
                            <option value="{{ $airport->id }}">{{ $airport->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="fcm-lbl">Airline <span class="req">*</span></label>
                    <select id="tkrAirline" class="fcm-select" style="width:100%">
                        <option value="">— Select airline —</option>
                        @foreach($airlines as $airline)
                            <option value="{{ $airline->id }}">{{ $airline->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="fcm-lbl">Vendor <span class="text-gray-400 font-normal" title="At least one of vendor or portal is required">*</span></label>
                    <select id="tkrVendor" class="fcm-select" style="width:100%">
                        <option value="">— Select vendor —</option>
                        @foreach($vendors as $vendor)
                            <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="fcm-lbl">Portal <span class="text-gray-400 font-normal" title="At least one of vendor or portal is required">*</span></label>
                    <select id="tkrPortal" class="fcm-select" style="width:100%">
                        <option value="">— Select portal —</option>
                        @foreach($portals as $portal)
                            <option value="{{ $portal->id }}">{{ $portal->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="fcm-lbl">Default Cost Price</label>
                    <input type="number" id="tkrPrice" min="0" step="0.01" class="fcm-input" value="0" placeholder="0.00">
                </div>
                <div>
                    <label class="fcm-lbl">Total Stock Quantity</label>
                    <input type="number" id="tkrQty" min="0" class="fcm-input" value="0" placeholder="0">
                </div>
            </div>
        </div>
        <div class="tkr-foot">
            <button type="button" class="fcm-btn fcm-btn-ghost" onclick="closeTicketRouteModal()">✕ Cancel</button>
            <button type="button" class="fcm-btn" style="background:#7c3aed;color:#fff;" id="tkrSaveBtn" onclick="submitTicketRouteModal()">💾 Save Route</button>
        </div>
    </div>
</div>
