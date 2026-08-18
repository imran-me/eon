@php
    $flightEditTitle = $flightEditTitle ?? 'Edit Contract Flight';
    $flightEditSub = $flightEditSub ?? 'Update seats, pricing & status';
@endphp

<div id="editModal" class="fcm-overlay hidden modal-backdrop">
    <div class="fcm-box">
        <div class="fcm-head" style="background:linear-gradient(135deg,#7c3aed,#6d28d9);">
            <div class="fcm-head-ic"><i class="fas fa-pencil-alt"></i></div>
            <div>
                <div class="fcm-title">{{ $flightEditTitle }}</div>
                <div class="fcm-sub">{{ $flightEditSub }}</div>
            </div>
            <button type="button" class="fcm-close modal-close-edit">✕</button>
        </div>
        <div class="fcm-body">
            <form id="editeForm" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" id="editItemId" name="id">

                {{-- Trip and ticket --}}
                <div class="fcm-section">
                    <div class="fcm-section-title"><i class="fas fa-plane mr-1.5"></i> Trip &amp; Ticket</div>
                    <div class="fcm-grid-4">
                        <div>
                            <label class="fcm-lbl">Flight Category <sup>*</sup></label>
                            <select id="edit_flight_category_id" name="flight_category_id" class="fcm-select" style="width:100%">
                                <option value="">— Select Category —</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                            <p id="edit_flight_category_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select a category</p>
                        </div>
                        <div>
                            <label class="fcm-lbl">Category Type</label>
                            <select id="edit_flight_category_type_id" name="flight_category_type_id" class="fcm-select category-type-schedule" style="width:100%">
                                <option value="">Select category type</option>
                                @foreach($categoryTypes as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="fcm-lbl">Airline <sup>*</sup></label>
                            <select id="edit_pricing_airline_id" class="fcm-select pricing-airline" style="width:100%">
                                <option value="">Select airline</option>
                                @foreach($airlines as $airline)<option value="{{ $airline->id }}">{{ $airline->name }}{{ $airline->code ? ' ('.$airline->code.')' : '' }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="fcm-lbl">Airline Flight No <sup>*</sup></label>
                            <input type="text" id="edit_airline_flight_no" name="airline_flight_no" class="fcm-input" placeholder="e.g. BG-088">
                            <p id="edit_airline_flight_no_msg" class="text-red-500 text-xs mt-1 hidden error-message">Flight number is required.</p>
                        </div>
                        <div class="fcm-full">
                            <label class="fcm-lbl">Ticket Route <sup>*</sup> <span class="text-gray-400 font-normal">(from Ticketing — Airline, From/To Airport)</span></label>
                            <div class="fcm-route-row">
                                <div class="fcm-select-wrap">
                                    <select id="edit_ticket_id" name="ticket_id" class="ticket-route-select" style="width:100%">
                                        <option value="">— Select Ticket Route —</option>
                                        @foreach($tickets as $ticket)
                                            <option value="{{ $ticket->id }}" data-airline-id="{{ $ticket->airline_id }}">
                                                {{ $ticket->title }}{{ $ticket->airline ? ' — '.$ticket->airline->name : '' }}{{ ($ticket->from_airport && $ticket->to_airport) ? ' ('.$ticket->from_airport->code.' → '.$ticket->to_airport->code.')' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="button" class="fcm-add-route-btn" onclick="openTicketRouteModal('edit_ticket_id')"><i class="fas fa-plus"></i> New Route</button>
                            </div>
                            <p id="edit_ticket_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select a ticket route</p>
                        </div>
                        <div>
                            <label class="fcm-lbl">Class <sup>*</sup></label>
                            <select id="edit_ticket_class" name="ticket_class" class="fcm-select">
                                <option value="economy">Economy</option><option value="business">Business</option><option value="first">First Class</option>
                            </select>
                        </div>
                        <div>
                            <label class="fcm-lbl">Departure Date / Time <sup>*</sup></label>
                            <input type="datetime-local" id="edit_departure_at" name="departure_at" class="fcm-input">
                            <p id="edit_departure_at_msg" class="text-red-500 text-xs mt-1 hidden error-message">Departure date and time are required.</p>
                        </div>
                        <div>
                            <label class="fcm-lbl">Arrival Date / Time <span class="text-gray-400 font-normal">(destination)</span></label>
                            <input type="datetime-local" id="edit_arrival_at" name="arrival_at" class="fcm-input">
                            <p id="edit_arrival_at_msg" class="text-red-500 text-xs mt-1 hidden error-message">Arrival cannot be before departure.</p>
                        </div>
                    </div>
                </div>

                <div class="fcm-section">
                    <div class="fcm-section-title"><i class="fas fa-users-cog mr-1.5"></i> Handling Type</div>
                    <div class="handling-grid">
                        <label class="handling-card"><input type="radio" name="handling_type" value="manpower_wise"><div class="handling-title"><i class="fas fa-suitcase"></i> Manpower-wise</div><div class="handling-sub">No immigration officer needed - boarding support only.</div></label>
                        <label class="handling-card"><input type="radio" name="handling_type" value="immigration_wise"><div class="handling-title"><i class="fas fa-passport"></i> Immigration-wise</div><div class="handling-sub">An officer handles boarding and immigration.</div></label>
                    </div>
                </div>

                <div class="fcm-section">
                    <div class="fcm-section-title"><i class="fas fa-user-shield mr-1.5"></i> Officers &amp; Process</div>
                    <div class="fcm-grid-2">
                        <div>
                            <label class="fcm-lbl">Boarding Officer <sup>*</sup></label>
                            <select id="edit_boarding_officer_id" name="boarding_officer_id" class="fcm-select officer-select" style="width:100%">
                                <option value="">Select boarding officer</option>
                                @foreach($officers as $officer)
                                    @if(in_array('boarding', $officer->work_roles ?? []))
                                        <option value="{{ $officer->id }}" data-airline-id="{{ $officer->airline_id }}">{{ $officer->user?->name }}{{ $officer->airline ? ' - '.$officer->airline->name : '' }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <p id="edit_boarding_officer_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please select a boarding officer.</p>
                        </div>
                        <div>
                            <label class="fcm-lbl">Immigration Officer</label>
                            <select id="edit_immigration_officer_id" name="immigration_officer_id" class="fcm-select officer-select immigration-officer" style="width:100%">
                                <option value="">Select immigration officer</option>
                                @foreach($officers as $officer)
                                    @if(in_array('immigration', $officer->work_roles ?? []))
                                        <option value="{{ $officer->id }}" data-airline-id="{{ $officer->airline_id }}">{{ $officer->user?->name }}{{ $officer->airline ? ' - '.$officer->airline->name : '' }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <p id="edit_immigration_officer_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Immigration officer is required for immigration-wise handling.</p>
                        </div>
                    </div>
                </div>

                <div class="fcm-section">
                    <div class="fcm-section-title"><i class="fas fa-coins mr-1.5"></i> Price Builder</div>
                    <div class="fcm-grid-4">
                        <div><label class="fcm-lbl">Ticket / Pax (BDT)</label><input type="number" id="edit_ticket_cost_per_pax" name="ticket_cost_per_pax" min="0" step="0.01" class="fcm-input price-input" value="0"></div>
                        <div><label class="fcm-lbl">Manpower / Pax (BDT)</label><input type="number" id="edit_manpower_cost_per_pax" name="manpower_cost_per_pax" min="0" step="0.01" class="fcm-input price-input" value="0"></div>
                        <div><label class="fcm-lbl">Boarding / Pax (BDT)</label><input type="number" id="edit_boarding_cost_per_pax" name="boarding_cost_per_pax" min="0" step="0.01" class="fcm-input price-input" value="0"></div>
                        <div><label class="fcm-lbl">Immigration / Pax (BDT)</label><input type="number" id="edit_immigration_cost_per_pax" name="immigration_cost_per_pax" min="0" step="0.01" class="fcm-input price-input immigration-cost" value="0"></div>
                        <div><label class="fcm-lbl">Total Cost / Pax</label><input type="number" id="edit_total_cost_per_pax" class="fcm-input fcm-readonly" readonly value="0"></div>
                        <div><label class="fcm-lbl">Sale Price / Pax <sup>*</sup></label><input type="number" id="edit_sale_price_per_pax" name="sale_price_per_pax" min="0" step="0.01" class="fcm-input price-input" value="0"><p id="edit_sale_price_per_pax_msg" class="text-red-500 text-xs mt-1 hidden error-message">Sale price is required and cannot be negative.</p></div>
                        <div><label class="fcm-lbl">Profit / Pax</label><input type="number" id="edit_profit_per_pax" class="fcm-input fcm-positive" readonly value="0"></div>
                        <div><label class="fcm-lbl">Margin</label><input type="text" id="edit_margin" class="fcm-input fcm-readonly" readonly value="0%"></div>
                    </div>
                    <div id="edit_preset_hint" class="fcm-help">Change airline or category to load a matching pricing preset.</div>
                </div>

                <div class="fcm-section">
                    <div class="fcm-section-title"><i class="fas fa-passport mr-1.5"></i> Passengers</div>
                    <div class="fcm-passenger-row">
                        <select id="edit_passenger_ids" name="passenger_ids[]" class="passenger-select" multiple style="width:100%">
                            @foreach($passportHolders as $holder)<option value="{{ $holder->id }}">{{ $holder->name }} - {{ $holder->passport_no }}</option>@endforeach
                        </select>
                        <button type="button" class="fcm-mini-add" onclick="openFlightPassengerModal('edit_passenger_ids')" title="Add passport holder"><i class="fas fa-plus"></i></button>
                    </div>
                </div>

                {{-- Seats & Pricing --}}
                <div class="fcm-section">
                    <div class="fcm-section-title"><i class="fas fa-chair mr-1.5"></i> Seats &amp; Pricing</div>
                    <div class="fcm-grid-4">
                        <div>
                            <label class="fcm-lbl">Total Seats <sup>*</sup></label>
                            <input type="number" id="edit_total_seats" name="total_seats" min="0" class="fcm-input" placeholder="0">
                            <p id="edit_total_seats_msg" class="text-red-500 text-xs mt-1 hidden error-message">Required</p>
                        </div>
                        <div>
                            <label class="fcm-lbl">Seats Sold</label>
                            <input type="number" id="edit_seats_sold" name="seats_sold" min="0" class="fcm-input" placeholder="0">
                            <p id="edit_seats_sold_msg" class="text-red-500 text-xs mt-1 hidden error-message">Seats sold cannot exceed total seats.</p>
                        </div>
                        <div>
                            <label class="fcm-lbl">Cost Price (৳)</label>
                            <input type="number" id="edit_cost_price" name="cost_price" min="0" step="0.01" class="fcm-input fcm-readonly" value="0" readonly>
                        </div>
                        <div>
                            <label class="fcm-lbl">Revenue (৳)</label>
                            <input type="number" id="edit_revenue" name="revenue" min="0" step="0.01" class="fcm-input fcm-readonly" value="0" readonly>
                        </div>
                    </div>
                </div>

                {{-- Assignment & Status --}}
                <div class="fcm-section">
                    <div class="fcm-section-title"><i class="fas fa-user-tie mr-1.5"></i> Assignment &amp; Status</div>
                    <div class="fcm-grid-2">
                        <div>
                            <label class="fcm-lbl">Agent <span class="text-gray-400 font-normal">(leave blank for Direct)</span></label>
                            <select id="edit_agent_id" name="agent_id" class="fcm-select" style="width:100%">
                                <option value="">— Direct —</option>
                                @foreach($agents as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="fcm-lbl">Status</label>
                            <select id="edit_status" name="status" class="fcm-select">
                                <option value="open">Open</option>
                                <option value="boarding">Boarding</option>
                                <option value="departed">Departed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label class="fcm-lbl">Vendor (payable to)</label>
                            <select id="edit_vendor_id" name="vendor_id" class="fcm-select" style="width:100%" onchange="flightOnVendorChange(this)">
                                <option value="">— No Vendor / Unassigned —</option>
                                @foreach($vendors as $vendor)<option value="{{ $vendor->id }}">{{ $vendor->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="fcm-lbl">Portal</label>
                            <select id="edit_portal_id" name="portal_id" class="fcm-select" style="width:100%" onchange="flightOnPortalChange(this)">
                                <option value="">— No Portal —</option>
                                @foreach($portals as $portal)<option value="{{ $portal->id }}">{{ $portal->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="fcm-lbl">Purchase Date</label>
                            <input type="date" id="edit_purchase_date" name="purchase_date" class="fcm-input">
                        </div>
                        <div>
                            <label class="fcm-lbl">Cost Paid (৳)</label>
                            <input type="number" min="0" step="0.01" id="edit_cost_paid_amount" name="cost_paid_amount" class="fcm-input">
                        </div>
                        <div id="edit_cost_bank_wrap">
                            <label class="fcm-lbl">Cost Bank</label>
                            <select id="edit_cost_bank_id" name="cost_bank_id" class="fcm-select" style="width:100%">
                                <option value="">— Select Bank —</option>
                                @foreach($banks as $bank)<option value="{{ $bank->id }}">{{ $bank->name }} — {{ $bank->account_number }}</option>@endforeach
                            </select>
                        </div>
                        <div id="edit_cost_sched_wrap">
                            <label class="fcm-lbl">Cost Due Date <span class="text-gray-400 font-normal">(payable schedule)</span></label>
                            <input type="date" id="edit_payable_date" name="payable_date" class="fcm-input">
                        </div>
                        <div class="fcm-full">
                            <label class="fcm-lbl">Notes</label>
                            <textarea id="edit_notes" name="notes" rows="2" class="fcm-input"></textarea>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="fcm-foot">
            <button type="button" class="fcm-btn fcm-btn-ghost modal-close-edit">✕ Cancel</button>
            <button id="editSubmit" type="button" class="fcm-btn" style="background:#7c3aed;color:#fff;"><i class="fas fa-save"></i> Update Flight</button>
        </div>
    </div>
</div>
